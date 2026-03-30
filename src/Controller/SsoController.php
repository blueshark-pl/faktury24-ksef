<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;

/**
 * SSO Controller — odbiera podpisany token z systemu księgowego (portal.partnersc.com)
 * i loguje użytkownika do faktury24.
 *
 * Flow:
 *  1. System księgowy buduje payload JSON z danymi firmy/usera
 *  2. Generuje token: base64url(payload) . "." . HMAC-SHA256(base64url(payload), secret)
 *  3. Redirect: GET /sso/login?token=xxx
 *  4. Ten controller weryfikuje HMAC, timestamp, tworzy firmę/usera jeśli brak, loguje do sesji
 */
class SsoController extends AppController
{
    /**
     * Wyłącz CSRF dla SSO (przychodzi GET redirect z innej domeny).
     */
    public function initialize(): void
    {
        parent::initialize();
        if ($this->components()->has('FormProtection')) {
            $this->components()->unload('FormProtection');
        }
    }

    public function beforeFilter(EventInterface $event): void
    {
        // Pozwól na dostęp bez autentykacji
        try {
            $authentication = $this->request->getAttribute('authentication');
            if ($authentication && method_exists($authentication, 'allowUnauthenticated')) {
                $authentication->allowUnauthenticated(['login']);
            }
        } catch (\Throwable) {
        }

        parent::beforeFilter($event);
    }

    /**
     * GET /sso/login?token=xxx[&dry_run=1]
     */
    public function login(): ?Response
    {
        $this->autoRender = false;

        // 1. Pobierz i zdekoduj token
        $rawToken = (string)$this->request->getQuery('token', '');
        if ($rawToken === '') {
            return $this->ssoError('Brak tokenu SSO.', 400);
        }

        $secret = (string)Configure::read('App.ssoSecret', '');
        if ($secret === '') {
            Log::error('SSO: brak App.ssoSecret w konfiguracji.');
            return $this->ssoError('SSO nie jest skonfigurowane.', 500);
        }

        $parts = explode('.', $rawToken, 2);
        if (count($parts) !== 2) {
            return $this->ssoError('Nieprawidłowy format tokenu.', 400);
        }

        [$payloadB64, $signature] = $parts;

        // 2. Weryfikuj HMAC
        $expectedSig = hash_hmac('sha256', $payloadB64, $secret);
        if (!hash_equals($expectedSig, $signature)) {
            return $this->ssoError('Nieprawidłowy podpis tokenu.', 403);
        }

        // 3. Dekoduj payload
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        if ($payloadJson === false) {
            return $this->ssoError('Nie można zdekodować payloadu.', 400);
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return $this->ssoError('Nieprawidłowy payload JSON.', 400);
        }

        // 4. Walidacja timestamp (max 120s drift)
        $timestamp = (int)($payload['timestamp'] ?? 0);
        $now = time();
        if (abs($now - $timestamp) > 120) {
            return $this->ssoError('Token wygasł (timestamp).', 403);
        }

        // 5. Wyciągnij dane z payloadu
        $nip        = preg_replace('/\D/', '', (string)($payload['nip'] ?? ''));
        $companyName = trim((string)($payload['company_name'] ?? ''));
        $email      = strtolower(trim((string)($payload['email'] ?? '')));
        $firstName  = trim((string)($payload['first_name'] ?? ''));
        $lastName   = trim((string)($payload['last_name'] ?? ''));
        $role       = (string)($payload['role'] ?? 'user');
        $target     = (string)($payload['target'] ?? 'invoices');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->ssoError('Brak lub nieprawidłowy email w tokenie.', 400);
        }

        $dryRun = (bool)$this->request->getQuery('dry_run', false);

        // 6. Znajdź lub utwórz firmę
        $Companies = $this->fetchTable('Companies');
        $Users     = $this->fetchTable('Users');
        $InvoiceSeries = $this->fetchTable('InvoiceSeries');

        $company = null;
        if (strlen($nip) === 10) {
            $company = $Companies->find()
                ->where(function ($exp, $q) use ($nip) {
                    return $exp->eq(
                        $q->newExpr("REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '')"),
                        $nip
                    );
                })
                ->first();
        }

        $companyCreated = false;
        if ($company === null && $companyName !== '' && strlen($nip) === 10) {
            if ($dryRun) {
                return $this->jsonResponse([
                    'dry_run'  => true,
                    'action'   => 'would_create_company_and_user',
                    'nip'      => $nip,
                    'company'  => $companyName,
                    'email'    => $email,
                ]);
            }

            $company = $Companies->newEntity([
                'name'              => $companyName,
                'nip'               => $nip,
                'vat_payer'         => true,
                'ksef_mode_enabled' => true,
                'is_active'         => true,
                'profile_mode'      => 'business',
                'country'           => 'PL',
            ]);

            if (!$Companies->save($company)) {
                Log::error('SSO: nie udało się utworzyć firmy NIP=' . $nip . ': ' . json_encode($company->getErrors()));
                return $this->ssoError('Nie udało się utworzyć firmy.', 500);
            }

            $companyCreated = true;

            // Skopiuj systemowe serie faktur
            try {
                $InvoiceSeries->copySystemSeriesForCompany((string)$company->id);
            } catch (\Throwable $e) {
                Log::warning('SSO: nie udało się skopiować serii dla firmy ' . $company->id . ': ' . $e->getMessage());
            }
        }

        if ($company === null) {
            // Brak NIPu lub nazwy — nie możemy utworzyć firmy, ale user może istnieć
            // Sprawdź czy user jest już w systemie z przypisaną firmą
        }

        // 7. Znajdź lub utwórz użytkownika
        $user = $Users->find()
            ->where(['email' => $email])
            ->first();

        $userCreated = false;
        if ($user === null) {
            if ($dryRun) {
                return $this->jsonResponse([
                    'dry_run' => true,
                    'action'  => 'would_create_user',
                    'email'   => $email,
                    'company' => $company ? (string)$company->id : null,
                ]);
            }

            // Generuj losowe hasło (user loguje się przez SSO, nie potrzebuje go znać)
            $randomPassword = bin2hex(random_bytes(16));

            $user = $Users->newEntity([
                'email'      => $email,
                'username'   => $email,
                'password'   => $randomPassword,
                'first_name' => $firstName ?: null,
                'last_name'  => $lastName ?: null,
                'active'     => true,
                'role'       => ($role === 'accountant') ? 'superuser' : 'user',
                'company_id' => $company ? $company->id : null,
            ], ['validate' => false]);

            if (!$Users->save($user, ['checkRules' => false])) {
                Log::error('SSO: nie udało się utworzyć usera ' . $email . ': ' . json_encode($user->getErrors()));
                return $this->ssoError('Nie udało się utworzyć użytkownika.', 500);
            }

            $userCreated = true;
        } else {
            // User istnieje — zawsze przełącz na firmę z tokenu (kontekst SSO)
            if ($company !== null && (string)$user->company_id !== (string)$company->id) {
                $user->company_id = $company->id;
                $Users->save($user, ['checkRules' => false, 'validate' => false]);
            }
        }

        if ($dryRun) {
            return $this->jsonResponse([
                'dry_run'         => true,
                'user_id'         => (string)$user->id,
                'user_email'      => $email,
                'company_id'      => $company ? (string)$company->id : null,
                'company_created' => $companyCreated,
                'user_created'    => $userCreated,
                'target'          => $target,
            ]);
        }

        // 8. Zaloguj do sesji
        try {
            if (!$this->components()->has('Authentication')) {
                $this->loadComponent('Authentication.Authentication');
            }
            // Odczytaj usera z bazy (żeby mieć pełny obiekt entity z company_id)
            $freshUser = $Users->get($user->id);
            $this->Authentication->setIdentity($freshUser);
        } catch (\Throwable $e) {
            Log::error('SSO: nie udało się zalogować usera ' . $email . ': ' . $e->getMessage());
            return $this->ssoError('Nie udało się zalogować.', 500);
        }

        Log::info('SSO: zalogowano ' . $email . ' (user_created=' . ($userCreated ? '1' : '0') . ', company_created=' . ($companyCreated ? '1' : '0') . ')');

        // 9. Redirect na docelową stronę
        $targetUrl = $this->resolveTarget($target);

        return $this->redirect($targetUrl);
    }

    /**
     * Mapowanie starych targetów na nowe URL-e.
     */
    private function resolveTarget(string $target): string
    {
        $map = [
            'faktury'                   => '/invoices',
            'invoices'                  => '/invoices',
            'nowa_faktura_vat'          => '/invoices/add',
            'nowa_faktura_uproszczona'  => '/invoices/add',
            'lista_kontrahentow'        => '/contractors',
            'contractors'               => '/contractors',
            'nowy_kontrahent'           => '/contractors/add',
            'lista_towarow_i_uslug'     => '/services',
            'services'                  => '/services',
            'nowy_towar_lub_usluga'     => '/services/add',
            'rozliczenia'               => '/invoices',
            'twoi_klienci'              => '/',
            'twoj_klient'              => '/',
            'strona_glowna'             => '/',
        ];

        return $map[$target] ?? '/';
    }

    private function ssoError(string $message, int $status): Response
    {
        $this->response = $this->response
            ->withType('application/json')
            ->withStatus($status);

        $this->response->getBody()->write(json_encode([
            'error'   => true,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $this->response;
    }

    private function jsonResponse(array $data): Response
    {
        $this->response = $this->response->withType('application/json');
        $this->response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $this->response;
    }
}

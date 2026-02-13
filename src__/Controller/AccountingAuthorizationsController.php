<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;
use App\Utility\TokenVault;
use Cake\Http\Client;

class AccountingAuthorizationsController extends AppController
{
    public function index()
    {
        $companyId = $this->request->getAttribute('identity')?->get('company_id');

        $query = $this->AccountingAuthorizations
            ->find()
            ->where(['company_id' => $companyId])
            ->orderDesc('created');

        $authorizations = $this->paginate($query);
        $this->set(compact('authorizations'));
    }

    public function view(string $id)
    {
        $auth = $this->AccountingAuthorizations->get($id);
        // (opcjonalnie) weryfikacja przynależności firmy:
        // if ($auth->company_id !== $this->request->getAttribute('identity')->get('company_id')) { throw new \Cake\Http\Exception\ForbiddenException(); }
        $this->set(compact('auth'));
    }

    public function add()
    {
        $auth = $this->AccountingAuthorizations->newEmptyEntity();

        if ($this->request->is('post')) {
            $companyId = (string)$this->request->getData('company_id');
            $token     = trim((string)$this->request->getData('token'));

            if (strlen($token) < 16) {
                $this->Flash->error('Token wygląda na nieprawidłowy.');
                return $this->set(compact('auth'));
            }

            try {
                $cipher = TokenVault::encrypt($token);
            } catch (\Throwable $e) {
                $this->Flash->error('Błąd szyfrowania tokenu: '.$e->getMessage());
                return $this->set(compact('auth'));
            }

            $auth = $this->AccountingAuthorizations->patchEntity($auth, [
                'id'            => Text::uuid(),
                'company_id'    => $companyId,
                'provider'      => $this->request->getData('provider') ?: null,
                'environment'   => $this->request->getData('environment') ?: 'prod',
                'status'        => 'active',
                'is_active'     => true,
                'token_cipher'  => $cipher,
                'token_last4'   => substr($token, -4),
                'valid_from'    => $this->request->getData('valid_from') ?: null,
                'expires_at'    => $this->request->getData('expires_at') ?: null,
                'scopes'        => $this->request->getData('scopes') ?: null,
            ]);

            // jeśli ustawiamy aktywny — wyłącz poprzednie
            if ($auth->is_active) {
                $this->AccountingAuthorizations->updateAll(
                    ['is_active' => false],
                    ['company_id' => $companyId, 'is_active' => true]
                );
            }

            if ($this->AccountingAuthorizations->save($auth)) {
                $this->Flash->success('Token zapisany. Integracja z systemem księgowym gotowa.');
                return $this->redirect(['action' => 'view', $auth->id]);
            }

            $this->Flash->error('Nie udało się zapisać tokenu. Sprawdź poprawność danych.');
        }

        $this->set(compact('auth'));
    }

    public function deactivate(string $id)
    {
        $this->request->allowMethod(['post']);
        $auth = $this->AccountingAuthorizations->get($id);

        if (!$auth->is_active) {
            $this->Flash->info('Ten token jest już nieaktywny.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $auth->is_active = false;
        $auth->status    = 'revoked';

        if ($this->AccountingAuthorizations->save($auth)) {
            $this->Flash->success('Token został dezaktywowany.');
        } else {
            $this->Flash->error('Nie udało się dezaktywować tokenu.');
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Sprawdź połączenie z providerem na podstawie aktywnego tokenu firmy.
     * GET /accounting/check[/:provider]
     *
     * @param string|null $provider np. 'partnersc', 'wfirma', 'infakt' (opcjonalnie)
     */
public function check(?string $provider = null)
{
    $companyId = $this->request->getAttribute('identity')?->get('company_id');
    if (!$companyId) {
        $this->Flash->error(__('Brak kontekstu firmy.'));
        return $this->redirect(['action' => 'index']);
    }

    $conditions = ['company_id' => $companyId, 'is_active' => true, 'status' => 'active'];
    if ($provider) {
        $conditions['provider'] = $provider;
    }

    $auth = $this->AccountingAuthorizations->find()
        ->where($conditions)
        ->orderDesc('created')
        ->first();

    if (!$auth) {
        $this->Flash->warning(__('Nie znaleziono aktywnego tokenu dla tej firmy. Dodaj token, aby kontynuować.'));
        return $this->redirect(['action' => 'add']);
    }

    $endpoint = $this->resolveProviderCheckEndpoint($auth->provider ?? $provider);

    if (!$endpoint) {
        $this->Flash->error(__('Ten dostawca nie ma skonfigurowanego endpointu sprawdzającego.'));
        return $this->redirect(['action' => 'view', $auth->id]);
    }

    try {
        $token = TokenVault::decrypt((string)$auth->token_cipher);
        if ($token === '') {
            throw new \RuntimeException('Token decrypt failed');
        }
    } catch (\Throwable $e) {
        $this->Flash->error(__('Nie udało się odczytać tokenu. Skonfiguruj go ponownie.'));
        return $this->redirect(['action' => 'view', $auth->id]);
    }

    $client = new \Cake\Http\Client([
        'timeout' => 8,
        'headers' => [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);

    $result = [
        'ok'        => false,
        'provider'  => $auth->provider ?? $provider ?? '—',
        'endpoint'  => $endpoint,
        'http_code' => null,
        'body'      => null,
        'error'     => null,
    ];

    try {
        $response = $client->get($endpoint);
        $result['http_code'] = $response->getStatusCode();
        $json = $this->safeJson($response->getJson());

        if ($response->isOk() && isset($json['success']) && $json['success'] === true) {
            // ✅ poprawne połączenie
            $result['ok']   = true;
            $result['body'] = $json;
            $this->Flash->success(__('Połączenie z systemem księgowym zostało pomyślnie zweryfikowane.'));
        } else {
            // ❌ błąd mimo odpowiedzi HTTP 200
            $result['error'] = __('Serwer zwrócił nieprawidłową odpowiedź lub brak parametru "success:true".');
            $result['body']  = $json;
            $this->Flash->error($result['error']);
        }
    } catch (\Throwable $e) {
        $result['error'] = __('Błąd połączenia: {0}', $e->getMessage());
        $this->Flash->error($result['error']);
    }

    $this->set(compact('auth', 'result'));
    $this->render('check');
}


    /**
     * Mapowanie provider -> endpoint sprawdzający zdrowie/autoryzację.
     * Zwraca null, jeśli provider nieobsługiwany.
     */
    private function resolveProviderCheckEndpoint(?string $provider): ?string
    {
        $map = [
            'partnersc' => 'https://portal.partnersc.com/api/check',
            // 'wfirma'   => 'https://api2.wfirma.pl/ping',
            // 'infakt'   => 'https://api.infakt.pl/v3/ping',
            // 'enova365' => 'https://.../api/check',
            // 'optima'   => 'https://.../api/check',
        ];

        // domyślnie traktuj jako partnersc, gdy nie podano
        if (!$provider) {
            return $map['partnersc'];
        }

        $key = strtolower($provider);
        return $map[$key] ?? null;
    }

    /**
     * Bezpieczne dekodowanie JSON (zwraca tablicę lub string skrócony)
     */
    private function safeJson($json)
    {
        if (is_array($json) || is_object($json)) {
            return $json;
        }
        return null;
    }
}

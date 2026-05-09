<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\Routing\Router;
/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    public $currentCompanyId = null;

    private function ensureCompanyFromPrefill(object $user): ?string
    {
        if (!empty($user->company_id)) {
            return (string)$user->company_id;
        }

        $additionalData = (array)($user->additional_data ?? []);
        $prefill = (array)($additionalData['onboarding_prefill'] ?? []);
        if (empty($prefill)) {
            return null;
        }

        $nip = preg_replace('/\D+/', '', (string)($prefill['nip'] ?? ''));
        $name = trim((string)($prefill['name'] ?? ''));
        $street = trim((string)($prefill['street'] ?? ''));
        $postalCode = trim((string)($prefill['postal_code'] ?? ''));
        $city = trim((string)($prefill['city'] ?? ''));
        $country = trim((string)($prefill['country'] ?? ''));

        if ($name === '' || $street === '' || $postalCode === '' || $city === '') {
            return null;
        }

        /** @var \Cake\ORM\Table $Companies */
        $Companies = $this->fetchTable('Companies');
        /** @var \Cake\ORM\Table $CompanyBankAccounts */
        $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');
        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
        /** @var \Cake\ORM\Table $Users */
        $Users = $this->fetchTable('Users');

        $companyId = null;

        if (strlen($nip) === 10) {
            $existingCompany = $Companies->find()
                ->select(['id'])
                ->where(function ($exp, $q) use ($nip) {
                    return $exp->eq(
                        $q->newExpr("REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '')"),
                        $nip
                    );
                })
                ->first();

            if ($existingCompany) {
                $companyId = (string)$existingCompany->id;
            }
        }

        if ($companyId === null) {
            $company = $Companies->newEntity([
                'name' => $name,
                'nip' => strlen($nip) === 10 ? $nip : null,
                'street' => $street,
                'local_number' => trim((string)($prefill['local_number'] ?? '')),
                'postal_code' => $postalCode,
                'city' => $city,
                'country' => $country !== '' ? $country : 'PL',
                'vat_payer' => true,
                'ksef_mode_enabled' => true,
                'is_active' => true,
                'profile_mode' => 'business',
            ]);

            if (!$Companies->save($company)) {
                Log::error('First login: nie udało się utworzyć firmy dla usera ' . (string)($user->id ?? '') . ': ' . json_encode($company->getErrors()), ['register_company']);

                return null;
            }

            $companyId = (string)$company->id;

            $prefillBankAccounts = (array)($prefill['bank_accounts'] ?? []);
            $uniqueIbans = [];
            foreach ($prefillBankAccounts as $rawIban) {
                $iban = strtoupper(preg_replace('/\s+/', '', (string)$rawIban));
                if ($iban === '') {
                    continue;
                }
                if (preg_match('/^\d{26}$/', $iban)) {
                    $iban = 'PL' . $iban;
                }
                $uniqueIbans[$iban] = true;
            }

            $isFirst = true;
            foreach (array_keys($uniqueIbans) as $iban) {
                $bankEntity = $CompanyBankAccounts->newEntity([
                    'company_id' => $companyId,
                    'iban' => $iban,
                    'currency' => 'PLN',
                    'is_default' => $isFirst,
                ]);
                if ($CompanyBankAccounts->save($bankEntity)) {
                    $isFirst = false;
                }
            }

            $InvoiceSeries->copySystemSeriesForCompany($companyId);
        }

        $user->company_id = $companyId;
        unset($additionalData['onboarding_prefill']);
        $user->additional_data = $additionalData;

        if (!$Users->save($user, ['checkRules' => false, 'validate' => false])) {
            Log::error('First login: nie udało się przypisać company_id dla usera ' . (string)($user->id ?? ''), ['register_company']);

            return null;
        }

        return (string)$companyId;
    }

    private function setKsefModeViewVars(?string $companyId): void
    {
        $enabled = true;
        if (!empty($companyId)) {
            try {
                $Companies = $this->fetchTable('Companies');
                $company = $Companies->find()
                    ->select(['id', 'ksef_mode_enabled'])
                    ->where(['id' => $companyId])
                    ->first();
                if ($company !== null) {
                    $enabled = (bool)($company->ksef_mode_enabled ?? true);
                }
            } catch (\Throwable) {
                // migration not applied or temporary DB issue -> default safe behavior
                $enabled = true;
            }
        }

        $this->set('ksefModeEnabled', $enabled);
    }

    private function setRentalViewVars(?string $companyId): void
    {
        $rentalEnabled = false;
        if (!empty($companyId)) {
            try {
                $Companies = $this->fetchTable('Companies');
                $company = $Companies->find()
                    ->select(['id', 'rental_enabled', 'rental_first_name', 'rental_last_name', 'rental_nip', 'rental_street', 'rental_postal_code', 'rental_city'])
                    ->where(['id' => $companyId])
                    ->first();
                if ($company !== null) {
                    $rentalEnabled = !empty($company->rental_enabled)
                        && !empty(trim((string)($company->rental_first_name ?? '')))
                        && !empty(trim((string)($company->rental_last_name ?? '')));
                }
            } catch (\Throwable) {
                $rentalEnabled = false;
            }
        }
        $this->set('rentalEnabled', $rentalEnabled);
    }

    private function setDraftsViewVars(?string $companyId): void
    {
        $draftCount = 0;
        if (!empty($companyId)) {
            try {
                $Invoices = $this->fetchTable('Invoices');
                $draftCount = (int)$Invoices->find()
                    ->where([
                        'company_id' => $companyId,
                        'workflow_status' => 'draft',
                    ])
                    ->count();
            } catch (\Throwable) {
                $draftCount = 0;
            }
        }

        $this->set('draftInvoicesCount', $draftCount);
    }
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        // $this->loadComponent('Authentication.Authentication');
        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        // Ustaw locale (PL/EN) z sesji — używane przez portal klienta (i18n).
        // Pracownicy nie korzystają z tłumaczeń (interfejs hardcoded PL),
        // więc to jest no-op dla ich widoków.
        $session = $this->request->getSession();
        $lang    = $session->read('Config.locale');
        if (!in_array($lang, ['pl', 'en'], true)) {
            $lang = 'pl';
        }
        \Cake\I18n\I18n::setLocale($lang);
        $this->set('currentLocale', $lang);

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $this->set('ksefModeEnabled', true);
            $this->set('rentalEnabled', false);
            $this->set('draftInvoicesCount', 0);
            return; // nie zalogowany → nic nie rób
        }

        // Klient (rola `client`) nie ma company_id naszej firmy i nie przechodzi onboardingu —
        // jego portal działa wyłącznie na podstawie client_profiles.nip.
        $role = strtolower((string)($identity->get('role') ?? ''));
        $this->set('currentRole', $role);
        if ($role === 'client') {
            $this->set('ksefModeEnabled', false);
            $this->set('rentalEnabled', false);
            $this->set('draftInvoicesCount', 0);

            // Klient może być wyłącznie w portalu, na akcjach autoryzacji lub pobierać PDF faktury.
            // Wszystko inne (np. domyślne `/` → Invoices/index po logowaniu) → redirect na /portal.
            // Zapobiega pętli ERR_TOO_MANY_REDIRECTS, bo Authorization odrzuciłby /Invoices/index.
            $controller = (string)$this->request->getParam('controller');
            $action     = (string)$this->request->getParam('action');
            $allowedForClient = [
                'ClientPortal' => '*',
                'Users'        => ['login', 'logout', 'profile', 'webauthn2fa', 'webauthn2faAuthenticate'],
                'Invoices'     => ['print'],
            ];
            $isAllowed = isset($allowedForClient[$controller])
                && ($allowedForClient[$controller] === '*'
                    || in_array($action, $allowedForClient[$controller], true));
            if (!$isAllowed) {
                return $this->redirect(['controller' => 'ClientPortal', 'action' => 'index']);
            }
            return;
        }

        // Jeśli identity nie ma company_id, ale w DB user ma już przypisaną firmę,
        // ustaw kontekst na podstawie DB (zapobiega pętli redirectów po onboardingu).
        if (empty($identity->get('company_id'))) {
            try {
                /** @var \Cake\ORM\Table $Users */
                $Users = $this->fetchTable('Users');
                $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id', 'additional_data']]);

                if (empty($dbUser->company_id)) {
                    $this->ensureCompanyFromPrefill($dbUser);
                    $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id']]);
                }

                if (!empty($dbUser->company_id)) {
                    $this->currentCompanyId = $dbUser->company_id;
                    $this->setKsefModeViewVars((string)$dbUser->company_id);
                    $this->setRentalViewVars((string)$dbUser->company_id);
                    $this->setDraftsViewVars((string)$dbUser->company_id);
                    try {
                        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                        $copied = $InvoiceSeries->copySystemSeriesForCompany((string)$dbUser->company_id);
                        if ($copied > 0) {
                            Log::info('Skopiowano '.$copied.' systemowych serii dla firmy '.$dbUser->company_id.' (stale identity path)', ['series_init']);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Nie udało się skopiować systemowych serii (stale identity path): '.$e->getMessage(), ['series_init']);
                    }
                    try {
                        if (!$this->components()->has('Authentication')) {
                            $this->loadComponent('Authentication.Authentication');
                        }
                        $this->Authentication->setIdentity($dbUser);
                    } catch (\Throwable) {
                        // best-effort
                    }
                    return;
                }
            } catch (\Throwable) {
                // best-effort
            }
        }
        // debug($identity->get('company_id'));
        // jeśli user ma już firmę → zapewnij skopiowanie systemowych serii (idempotentnie)
        if (!empty($identity->get('company_id'))) {
            try {
                $this->currentCompanyId = $identity->get('company_id');
                $this->setKsefModeViewVars((string)$this->currentCompanyId);
                $this->setRentalViewVars((string)$this->currentCompanyId);
                $this->setDraftsViewVars((string)$this->currentCompanyId);
                /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                $copied = $InvoiceSeries->copySystemSeriesForCompany($identity->get('company_id'));
                if ($copied > 0) {
                    Log::info('Skopiowano '.$copied.' systemowych serii dla firmy '.$identity->get('company_id'), ['series_init']);
                }
            } catch (\Throwable $e) {
                Log::warning('Nie udało się skopiować systemowych serii: '.$e->getMessage(), ['series_init']);
            }
            return; // nic więcej – logika poniżej dotyczy braku firmy
        }

        $this->set('ksefModeEnabled', true);
        $this->set('rentalEnabled', false);
        $this->set('draftInvoicesCount', 0);

        // whitelist akcji (żeby nie robić pętli)
        $allowed = [
            'Companies' => ['onboarding', 'saveOnboarding'],
            'Users'     => ['login', 'logout', 'register'],
            'TwoFactor' => ['index', 'enable', 'verify', 'disable'],
            'Invoices'  => ['runPlannedDrafts', 'generatePdfInternal', 'processEmailQueue'],
            'Import'    => ['importLegacyUsers'],
            'Sso'       => ['login'],
        ];

        $controller = $this->request->getParam('controller');
        $action     = $this->request->getParam('action');

        if (isset($allowed[$controller]) && in_array($action, $allowed[$controller], true)) {
            return;
        }

        // jeśli to AJAX/JSON – zwróć błąd
        if ($this->request->is('ajax') || $this->request->is('json')) {
            $this->set([
                'error' => 'onboarding_required',
                '_serialize' => ['error']
            ]);
            $this->response = $this->response->withStatus(428);
            return;
        }

        // domyślnie: redirect do kreatora
        return $this->redirect(Router::url(['controller' => 'Companies', 'action' => 'onboarding']));
    }

    protected function requireAdmin(): ?\Cake\Http\Response
    {
        $identity = $this->request->getAttribute('identity');
        $isAdmin  = (bool)($identity?->get('is_admin') ?? false);
        $role     = strtolower((string)($identity?->get('role') ?? ''));
        if (!$isAdmin && $role !== 'admin') {
            $this->Flash->error('Dostęp tylko dla administratora.');
            return $this->redirect(['controller' => 'Invoices', 'action' => 'index']);
        }
        return null;
    }
}

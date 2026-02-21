<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\InvoicesTable;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\FrozenDate;
use Cake\Datasource\Paging\Exception\PageOutOfBoundsException;
use Cake\Utility\Text;
use App\Utility\TokenVault;
use App\Service\Ksef\N1KsefService;
use App\Service\Ksef\DbKsefTokenStorage;
use App\Service\Ksef\CertificateStorage;
use Psr\Http\Message\UploadedFileInterface;
use N1ebieski\KSEFClient\Requests\Invoices\Download\DownloadRequest;
use N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber as KsefNumberVO;

class KsefAuthorizationsController extends AppController
{
    private InvoicesTable $Invoices;
    
    /**
     * Lazy loaded table for saving booking selections.
     * @var \App\Model\Table\KsefBookingItemsTable
     */
    private $KsefBookingItems;

    public function initialize(): void
    {
        parent::initialize();
        // CakePHP 5: bez loadModel, używamy fetchTable
        $this->Invoices = $this->fetchTable('Invoices');
        // Lazy; created when first used
        $this->KsefBookingItems = $this->fetchTable('KsefBookingItems');
    }


    /**
     * Tymczasowa lista "Faktur otrzymanych z KSeF" — jeszcze bez realnego połączenia z KSeF.
     * W praktyce pobieramy je z lokalnej tabeli Invoices, filtrując "zakupy/otrzymane".
     *
     * Uwaga: dostosuj warunek "purchase direction" do swojej schemy (przykłady w komentarzu).
     */
/**
     * Placeholder listy "Faktur otrzymanych (KSeF)".
     * Nie łączy się z KSeF – bazuje na lokalnej tabeli Invoices.
     * Widok zostaje taki, jak go masz.
     */
    public function received(): void
    {
        $currency = $this->request->getQuery('currency');

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $vm = $ksef->buildReceivedViewModel($companyId, $environment, $this->request->getQueryParams());

        if (!empty($vm['status'])) {
            $this->request->getSession()->write('Ksef.status', $vm['status']);
        }

        if (!empty($vm['flash']) && is_array($vm['flash'])) {
            $type = (string)($vm['flash']['type'] ?? 'info');
            $msg  = (string)($vm['flash']['message'] ?? '');
            if ($msg !== '') {
                if ($type === 'success') {
                    $this->Flash->success($msg);
                } elseif ($type === 'error') {
                    $this->Flash->error($msg);
                } else {
                    $this->Flash->info($msg);
                }
            }
        }

        $invoices  = $vm['invoices'] ?? [];
        $stats     = $vm['stats'] ?? null;
        $apiInfo   = $vm['apiInfo'] ?? [];
        $certInfo  = $vm['certInfo'] ?? null;
        $ksefTrace = $vm['ksefTrace'] ?? [];
        $ksefDiag  = $vm['ksefDiag'] ?? null;
        $ksefRaw   = $vm['ksefRaw'] ?? null;

        $this->set('apiInfo', $apiInfo);
        $this->set('ksefEnv', $environment);
        $this->set('certInfo', $certInfo);
        $this->set('ksefTraceEnabled', $vm['ksefTraceEnabled'] ?? false);
        $this->set('ksefTrace', $ksefTrace);
        $this->set('ksefDiag', $ksefDiag);
        $this->set('ksefRaw', $ksefRaw);

        $this->set(compact('companyId', 'environment', 'invoices', 'stats', 'currency'));
        $this->render('received');
    }

    /**
     * API: Zwraca listę „Faktur otrzymanych (KSeF)” w formacie JSON.
     * GET /api/ksef/received?env=test|prod&as_nip=XXXXXXXXXX&ksef=...&inv=...&seller_nip=...&buyer_nip=...&from=Y-m-d&to=Y-m-d&currency=PLN&page=1&q=...
     */
    public function receivedApi()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $result = $ksef->buildReceivedApiResult($companyId, $this->request->getQueryParams());

        if (!empty($result['status']) && is_array($result['status'])) {
            $this->request->getSession()->write('Ksef.status', $result['status']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result['payload'] ?? ['success' => false, 'error' => 'Invalid payload'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * API: Zwraca listę „Faktur wystawionych (KSeF)” w formacie JSON.
     * GET /api/ksef/issued?env=test|prod&as_nip=XXXXXXXXXX&ksef=...&inv=...&seller_nip=...&buyer_nip=...&from=Y-m-d&to=Y-m-d&currency=PLN&page=1&q=...
     */
    public function issuedApi()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $result = $ksef->buildIssuedApiResult($companyId, $this->request->getQueryParams());

        if (!empty($result['status']) && is_array($result['status'])) {
            $this->request->getSession()->write('Ksef.status', $result['status']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result['payload'] ?? ['success' => false, 'error' => 'Invalid payload'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * API: Zwraca listę uprawnień (personal grants) w formacie JSON.
     * GET /api/ksef/personal-grants?env=test|prod&as_nip=XXXXXXXXXX&page=1&limit=10&context_nip=...&target_type=Nip&target_value=...&permission_state=Active&permission_types[]=InvoiceRead
     */
    public function personalGrantsApi()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $result = $ksef->buildPersonalGrantsApiResult($companyId, $this->request->getQueryParams());

        if (!empty($result['status']) && is_array($result['status'])) {
            $this->request->getSession()->write('Ksef.status', $result['status']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result['payload'] ?? ['success' => false, 'error' => 'Invalid payload'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * API: Prosty "check" uprawnień (personal grants) z komunikatem auth.
     *
     * Zwraca:
     * - gdy brak uwierzytelnienia (np. brak access token): {success:false, message:"Uwierzytelnianie zakończone niepowodzeniem.", details:"..."}
     * - gdy OK: {success:true, message:"OK", permissions:{summary:{...}, items:[...]}}
     *
     * GET /api/ksef/personal-grants/check?env=test|prod&as_nip=XXXXXXXXXX&limit=100
     */
    public function personalGrantsCheckApi()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $query = $this->request->getQueryParams();
        $env = (string)($query['env'] ?? 'test');
        $env = ($env === 'prod') ? 'prod' : 'test';
        $asNip = preg_replace('/\D/', '', (string)($query['as_nip'] ?? ''));
        $limit = (int)($query['limit'] ?? 100);
        if ($limit <= 0) { $limit = 100; }
        if ($limit > 200) { $limit = 200; }

        $permissionState = (string)($query['permission_state'] ?? $query['permissionState'] ?? 'Active');
        if ($permissionState === '') {
            $permissionState = 'Active';
        }

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());

        // Domyślnie interesują nas aktywne uprawnienia (Active), ale pozwalamy nadpisać parametrem.
        try {
            $resp = $ksef->queryPersonalGrants(
                companyId: $companyId,
                environment: $env,
                filters: [
                    'permissionState' => $permissionState,
                ],
                pageOffset: 0,
                pageSize: $limit,
                overrideIdentifierNip: $asNip !== '' ? $asNip : null,
                enableTrace: (string)($query['ksef_trace'] ?? '') === '1'
            );
        } catch (\Throwable $e) {
            $msg = 'Uwierzytelnianie zakończone niepowodzeniem.';
            $details = $e->getMessage();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $msg,
                    'details' => $details,
                    'env' => $env,
                    'as_nip' => $asNip,
                    'permission_state' => $permissionState,
                ], JSON_UNESCAPED_UNICODE));
        }

        $items = [];
        if (is_array($resp)) {
            if (isset($resp['permissions']) && is_array($resp['permissions'])) {
                $items = $resp['permissions'];
            } elseif (isset($resp['items']) && is_array($resp['items'])) {
                $items = $resp['items'];
            }
        }

        // Summary: typy + stany (best-effort, bo API może zwracać różne klucze)
        $types = [];
        $states = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string)($row['permissionType'] ?? $row['permissionScope'] ?? $row['permission'] ?? '');
            $state = (string)($row['permissionState'] ?? $row['state'] ?? '');
            if ($type !== '') { $types[$type] = true; }
            if ($state !== '') { $states[$state] = true; }
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'message' => 'OK',
                'env' => $env,
                'as_nip' => $asNip,
                'permission_state' => $permissionState,
                'permissions' => [
                    'summary' => [
                        'count' => count($items),
                        'types' => array_values(array_keys($types)),
                        'states' => array_values(array_keys($states)),
                    ],
                    'items' => $items,
                ],
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * API: Zwraca listę uprawnień podmiotowych (authorizations grants) w formacie JSON.
     * GET /api/ksef/authorizations-grants?env=test|prod&as_nip=XXXXXXXXXX&page=1&limit=10&query_type=Received|Granted&authorizing_nip=...&authorized_nip=...&permission_types[]=SelfInvoicing
     */
    public function authorizationsGrantsApi()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $result = $ksef->buildAuthorizationsGrantsApiResult($companyId, $this->request->getQueryParams());

        if (!empty($result['status']) && is_array($result['status'])) {
            $this->request->getSession()->write('Ksef.status', $result['status']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result['payload'] ?? ['success' => false, 'error' => 'Invalid payload'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lista "Faktur wystawionych w KSeF" (perspektywa sprzedawcy = Subject2).
     * Implementacja analogiczna do received(), różni się tylko subjectType.
     */
    public function issued(): void
    {
        $currency = $this->request->getQuery('currency');

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $vm = $ksef->buildIssuedViewModel($companyId, $environment, $this->request->getQueryParams());

        if (!empty($vm['status'])) {
            $this->request->getSession()->write('Ksef.status', $vm['status']);
        }

        if (!empty($vm['flash']) && is_array($vm['flash'])) {
            $type = (string)($vm['flash']['type'] ?? 'info');
            $msg  = (string)($vm['flash']['message'] ?? '');
            if ($msg !== '') {
                if ($type === 'success') {
                    $this->Flash->success($msg);
                } elseif ($type === 'error') {
                    $this->Flash->error($msg);
                } else {
                    $this->Flash->info($msg);
                }
            }
        }

        $invoices  = $vm['invoices'] ?? [];
        $stats     = $vm['stats'] ?? null;
        $apiInfo   = $vm['apiInfo'] ?? [];
        $certInfo  = $vm['certInfo'] ?? null;
        $ksefTrace = $vm['ksefTrace'] ?? [];
        $ksefDiag  = $vm['ksefDiag'] ?? null;
        $ksefRaw   = $vm['ksefRaw'] ?? null;

        $this->set('apiInfo', $apiInfo);
        $this->set('ksefEnv', $environment);
        $this->set('certInfo', $certInfo);
        $this->set('ksefTraceEnabled', $vm['ksefTraceEnabled'] ?? false);
        $this->set('ksefTrace', $ksefTrace);
        $this->set('ksefDiag', $ksefDiag);
        $this->set('ksefRaw', $ksefRaw);

        $this->set(compact('companyId', 'environment', 'invoices', 'stats', 'currency'));
        $this->render('issued');
    }

    /**
     * Widok: uprawnienia (personal grants).
     * URL: /ksef-authorizations/personal-grants?env=test|prod&as_nip=...&page=1&limit=10
     */
    public function personalGrants(): void
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $vm = $ksef->buildPersonalGrantsViewModel($companyId, $environment, $this->request->getQueryParams());

        if (!empty($vm['status']) && is_array($vm['status'])) {
            $this->request->getSession()->write('Ksef.status', $vm['status']);
        }

        if (!empty($vm['flash']) && is_array($vm['flash'])) {
            $type = (string)($vm['flash']['type'] ?? 'info');
            $msg  = (string)($vm['flash']['message'] ?? '');
            if ($msg !== '') {
                if ($type === 'success') {
                    $this->Flash->success($msg);
                } elseif ($type === 'error') {
                    $this->Flash->error($msg);
                } else {
                    $this->Flash->info($msg);
                }
            }
        }

        $items     = $vm['items'] ?? [];
        $apiInfo   = $vm['apiInfo'] ?? [];
        $ksefTrace = $vm['ksefTrace'] ?? [];
        $ksefDiag  = $vm['ksefDiag'] ?? null;

        $this->set('apiInfo', $apiInfo);
        $this->set('ksefEnv', $vm['ksefEnv'] ?? $environment);
        $this->set('ksefTraceEnabled', $vm['ksefTraceEnabled'] ?? false);
        $this->set('ksefTrace', $ksefTrace);
        $this->set('ksefDiag', $ksefDiag);
        $this->set('filters', $vm['filters'] ?? []);
        $this->set('page', $vm['page'] ?? 1);
        $this->set('limit', $vm['limit'] ?? 10);
        $this->set('hasMore', $vm['hasMore'] ?? false);
        $this->set('asNip', $vm['asNip'] ?? null);

        $this->set(compact('companyId', 'environment', 'items'));
        $this->render('personal_grants');
    }

    /**
     * Widok: uprawnienia podmiotowe (authorizations grants).
     * URL: /ksef-authorizations/authorizations-grants?env=test|prod&as_nip=...&page=1&limit=10
     */
    public function authorizationsGrants(): void
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $vm = $ksef->buildAuthorizationsGrantsViewModel($companyId, $environment, $this->request->getQueryParams());

        if (!empty($vm['status']) && is_array($vm['status'])) {
            $this->request->getSession()->write('Ksef.status', $vm['status']);
        }

        if (!empty($vm['flash']) && is_array($vm['flash'])) {
            $type = (string)($vm['flash']['type'] ?? 'info');
            $msg  = (string)($vm['flash']['message'] ?? '');
            if ($msg !== '') {
                if ($type === 'success') {
                    $this->Flash->success($msg);
                } elseif ($type === 'error') {
                    $this->Flash->error($msg);
                } else {
                    $this->Flash->info($msg);
                }
            }
        }

        $items     = $vm['items'] ?? [];
        $apiInfo   = $vm['apiInfo'] ?? [];
        $ksefTrace = $vm['ksefTrace'] ?? [];
        $ksefDiag  = $vm['ksefDiag'] ?? null;

        $this->set('apiInfo', $apiInfo);
        $this->set('ksefEnv', $vm['ksefEnv'] ?? $environment);
        $this->set('ksefTraceEnabled', $vm['ksefTraceEnabled'] ?? false);
        $this->set('ksefTrace', $ksefTrace);
        $this->set('ksefDiag', $ksefDiag);
        $this->set('filters', $vm['filters'] ?? []);
        $this->set('page', $vm['page'] ?? 1);
        $this->set('limit', $vm['limit'] ?? 10);
        $this->set('hasMore', $vm['hasMore'] ?? false);
        $this->set('asNip', $vm['asNip'] ?? null);

        $this->set(compact('companyId', 'environment', 'items'));
        $this->render('authorizations_grants');
    }

    /**
     * Normalizuje strukturę odpowiedzi KSeF na listę elementów.
     * Oczekiwane popularne kształty: {result: {elements: [...]}} albo {elements: [...]}, ewentualnie {items: [...]}.
     */
    private function extractKsefItems(array $result): array
    {
        if (isset($result['result']) && is_array($result['result'])) {
            if (isset($result['result']['elements']) && is_array($result['result']['elements'])) {
                return $result['result']['elements'];
            }
            if (isset($result['result']['invoices']) && is_array($result['result']['invoices'])) {
                return $result['result']['invoices'];
            }
        }
        if (isset($result['elements']) && is_array($result['elements'])) {
            return $result['elements'];
        }
        if (isset($result['invoices']) && is_array($result['invoices'])) {
            return $result['invoices'];
        }
        if (isset($result['items']) && is_array($result['items'])) {
            return $result['items'];
        }
        // Jeżeli odpowiedź jest już listą (tablica indeksowana), zwróć jak jest
        if ($result !== [] && array_keys($result) === range(0, count($result) - 1)) {
            return $result;
        }
        return [];
    }

    /**
     * Loguje surową odpowiedź KSeF (skróconą) jeśli w URL jest debug=1.
     */
    private function ksefDebug(string $label, array $result): void
    {
        $debug = (string)$this->request->getQuery('debug') !== '';
        if (!$debug) {
            return;
        }
        $keys = implode(',', array_keys($result));
        $count = (int)($result['result']['count'] ?? $result['count'] ?? $result['total'] ?? 0);
        if ($count === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $count = count($result['invoices']);
        }
        $elements = [];
        if (isset($result['result']['elements']) && is_array($result['result']['elements'])) {
            $elements = $result['result']['elements'];
        } elseif (isset($result['elements']) && is_array($result['elements'])) {
            $elements = $result['elements'];
        } elseif (isset($result['items']) && is_array($result['items'])) {
            $elements = $result['items'];
        } elseif (isset($result['invoices']) && is_array($result['invoices'])) {
            $elements = $result['invoices'];
        }
        $first = $elements[0] ?? null;
        $snippet = json_encode($first, JSON_UNESCAPED_UNICODE);
        if (class_exists('Cake\\Log\\Log')) {
            \Cake\Log\Log::debug(sprintf('[KSeF][%s] keys:%s total:%d first:%s', $label, $keys, $count, (string)$snippet));
        }
        // Udostępnij do ewentualnego podejrzenia w widoku (opcjonalnie użyteczne przy dev):
        $this->set('ksefRaw', $result);
    }

    /**
     * Upload certyfikatu KSeF (.p12 rekomendowane, opcjonalnie .pem) + passphrase.
     */
    public function uploadCertificate()
    {
        if ((bool)(Configure::read('Ksef.forceMasterCert') ?? false)) {
            $this->Flash->error('Wgrywanie certyfikatów jest wyłączone. System używa certyfikatu MASTER.');
            return $this->redirect(['action' => 'index']);
        }

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') {
            $this->Flash->error('Brak powiązania z firmą.');
            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->is('post')) {
            $environment = (string)$this->request->getData('environment', 'test');
            /**
             * Obsługujemy dwa warianty:
             * 1) pojedynczy plik (.p12/.pem) + opcjonalne hasło
             * 2) para plików: private_key (.key) + public_cert (.crt)
             */
            /** @var UploadedFileInterface|null $single */
            $single = $this->request->getData('certificate');
            /** @var UploadedFileInterface|null $priv */
            $priv = $this->request->getData('private_key');
            /** @var UploadedFileInterface|null $pub */
            $pub  = $this->request->getData('public_cert');
            $pass = (string)$this->request->getData('passphrase', '');
                $keyPass = (string)$this->request->getData('private_key_passphrase') ?: null;

            try {
                $storage = new CertificateStorage();
                if ($priv && $priv->getError() === UPLOAD_ERR_OK && $pub && $pub->getError() === UPLOAD_ERR_OK) {
                    // Wariant 2: para .key + .crt → combined PEM
                        $storage->saveKeyAndCertificate($priv, $pub, $companyId, $environment, $keyPass);
                    $this->Flash->success('Klucz prywatny i certyfikat zapisane. Utworzono połączony PEM dla integracji.');
                    return $this->redirect(['action' => 'index']);
                }

                if ($single && $single->getError() === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo((string)$single->getClientFilename(), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['p12', 'pem'], true)) {
                        $this->Flash->error('Dozwolone rozszerzenia: .p12 lub .pem (lub wgraj parę .key + .crt).');
                        return $this->redirect(['action' => 'uploadCertificate']);
                    }
                    $storage->saveUploadedCertificate($single, $companyId, $environment, $pass !== '' ? $pass : null);
                    $this->Flash->success('Certyfikat zapisany. Integracja certyfikatem włączona dla tej firmy/środowiska.');
                    return $this->redirect(['action' => 'index']);
                }

                $this->Flash->error('Wgraj albo pojedynczy plik (.p12/.pem), albo parę plików (.key + .crt).');
            } catch (\Throwable $e) {
                $this->Flash->error('Błąd zapisu certyfikatu: ' . $e->getMessage());
            }
        }

        // Preselect environment based on query ?env=, then last session status env, fallback to 'test'
        $envFromQuery = (string)$this->request->getQuery('env', '');
        $envFromSess  = (string)($this->request->getSession()->read('Ksef.status.env') ?? '');
        $defaultEnv   = $envFromQuery !== '' ? $envFromQuery : ($envFromSess !== '' ? $envFromSess : 'test');

        // Light info whether a cert is already present for this env (no sensitive details)
        $certPresent = false;
        try {
            $storage = new CertificateStorage();
            $certPresent = (bool)$storage->getCertificateFor($companyId, $defaultEnv);
        } catch (\Throwable $e) {
            $certPresent = false;
        }

        $this->set('environments', ['test' => 'Test', 'prod' => 'Production']);
        $this->set(compact('defaultEnv', 'certPresent'));
        $this->render('upload_certificate');
    }

    /**
     * Pobiera plik faktury z KSeF po numerze KSeF i zwraca go jako pobieralny plik.
     * URL: /ksef-authorizations/download/{ksefNumber}?env=test
     */
    public function download(string $ksefNumber)
    {
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $client = $ksef->buildClient($companyId, $environment, $asNip ?: null);
            $response = $client->invoices()->download(new DownloadRequest(KsefNumberVO::from($ksefNumber)));
            $body = $response->body();

            // Domyślnie ustawiamy XML, ale pozostawiamy generowany filename.
            $this->response = $this->response
                ->withType('xml')
                ->withStringBody($body)
                ->withDownload(sprintf('ksef-%s.xml', preg_replace('/[^A-Za-z0-9\-]/', '_', $ksefNumber)));

            return $this->response;
        } catch (\Throwable $e) {
            $this->Flash->error('Nie udało się pobrać pliku z KSeF: ' . $this->formatKsefError($e));
            return $this->redirect($this->referer(['action' => 'received']));
        }
    }

    /**
     * Podgląd pliku faktury z KSeF po numerze KSeF, bez wymuszenia pobrania.
     * Zwraca treść XML jako text/plain do użycia w modalu.
     * URL: /ksef-authorizations/preview/{ksefNumber}?env=test
     */
    public function preview(string $ksefNumber)
    {
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $client = $ksef->buildClient($companyId, $environment, $asNip ?: null);
            $response = $client->invoices()->download(new DownloadRequest(KsefNumberVO::from($ksefNumber)));
            $body = $response->body();

            $this->response = $this->response
                ->withType('text')
                ->withStringBody($body);

            return $this->response;
        } catch (\Throwable $e) {
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => $this->formatKsefError($e)], JSON_UNESCAPED_UNICODE));
            return $this->response;
        }
    }

    /**
     * API: Podgląd XML faktury z KSeF po numerze KSeF.
     * GET /api/ksef/preview/{ksefNumber}?env=test|prod&as_nip=XXXXXXXXXX
     */
    public function previewApi(string $ksefNumber)
    {
        $this->request->allowMethod(['get']);

        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $client = $ksef->buildClient($companyId, $environment, $asNip ?: null);
            $response = $client->invoices()->download(new DownloadRequest(KsefNumberVO::from($ksefNumber)));
            $body = (string)$response->body();

            // Return XML as-is (API consumer can display/parse it)
            return $this->response
                ->withType('application/xml')
                ->withStringBody($body);
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => $this->formatKsefError($e)], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * API: Pobranie XML faktury z KSeF po numerze KSeF jako załącznik.
     * GET /api/ksef/download/{ksefNumber}?env=test|prod&as_nip=XXXXXXXXXX
     */
    public function downloadApi(string $ksefNumber)
    {
        $this->request->allowMethod(['get']);

        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $client = $ksef->buildClient($companyId, $environment, $asNip ?: null);
            $response = $client->invoices()->download(new DownloadRequest(KsefNumberVO::from($ksefNumber)));
            $body = (string)$response->body();

            $safeKsefNumber = preg_replace('/[^A-Za-z0-9\-]/', '_', $ksefNumber);
            $filename = sprintf('ksef-%s.xml', $safeKsefNumber);

            return $this->response
                ->withType('application/xml')
                ->withStringBody($body)
                ->withDownload($filename);
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => $this->formatKsefError($e),
                ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Szybka aktualizacja statusu połączenia z KSeF dla bieżącej firmy/środowiska.
     * URL: /ksef-authorizations/status?env=test|prod&as_nip=XXXXXXXXXX
     * Działanie: wykonuje lekką diagnozę kontekstu oraz próbne zapytanie metadanych (mała strona),
     * zapisuje wynik do sesji 'Ksef.status' i wraca na stronę poprzednią.
     */
    public function status()
    {
        $this->request->allowMethod(['get']);
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        if ($companyId === '') {
            $this->Flash->error('Brak powiązania z firmą.');
            return $this->redirect($this->referer(['controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => $environment]]));
        }

        // NIP firmy (fallback jeśli nie podano as_nip)
        $companyNip = '';
        $companyProfileMode = 'business';
        try {
            /** @var \App\Model\Table\CompaniesTable $Companies */
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()->select(['nip', 'profile_mode'])->where(['Companies.id' => $companyId])->first();
            $companyNip = preg_replace('/\D/', '', (string)($company?->nip ?? ''));
            $companyProfileMode = (string)($company?->profile_mode ?? 'business');
        } catch (\Throwable) {
            $companyNip = '';
            $companyProfileMode = 'business';
        }

        $identifierNip = $asNip !== '' ? $asNip : $companyNip;
        if ($identifierNip === '') {
            $isPrivateRental = $companyProfileMode === 'private_rental';
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => ($environment === 'prod') ? 'prod' : 'test',
                'ts'     => time(),
                'lastError' => $isPrivateRental
                    ? 'Profil najmu prywatnego bez NIP: pominięto weryfikację uprawnień KSeF.'
                    : 'Brak NIP firmy – nie można sprawdzić uprawnień w KSeF.',
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => true,
                'usingMasterMode' => 'forced',
                'identifierNip' => null,
            ]);
            if ($isPrivateRental) {
                $this->Flash->info('KSeF: profil najmu prywatnego bez NIP – pominięto weryfikację uprawnień.');
            } else {
                $this->Flash->error('KSeF: brak NIP firmy – nie można sprawdzić uprawnień.');
            }
            return $this->redirect($this->referer(['controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => $environment]]));
        }

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());

        // Diagnoza kontekstu (łagodne, bez błędu dla UI)
        $diag = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
        } catch (\Throwable $e) {
            // pomiń – status oceni próba zapytania poniżej
        }

        $usingMaster = false;
        $usingMasterMode = null; // configured|fallback|null
        $identifierNip = null;
        if (is_array($diag)) {
            $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
            if (($diag['authMethod'] ?? '') === 'certificate') {
                if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                    $usingMaster = true;
                    $usingMasterMode = 'configured';
                } elseif (($diag['certSource'] ?? null) === 'master') {
                    $usingMaster = true;
                    $usingMasterMode = 'fallback';
                }
            }
        }

        if ((bool)(Configure::read('Ksef.forceMasterCert') ?? false)) {
            $usingMaster = true;
            $usingMasterMode = 'forced';
        }

        // Status = czy mamy uprawnienie InvoiceWrite (wystawianie) w personal grants.
        try {
            $filters = [
                'permissionState' => 'Active',
                'permissionTypes' => ['InvoiceWrite'],
            ];

            $result = $ksef->queryPersonalGrants(
                companyId: $companyId,
                environment: $environment,
                filters: $filters,
                pageOffset: 0,
                pageSize: 10,
                overrideIdentifierNip: $identifierNip,
            );

            $items = [];
            if (is_array($result)) {
                if (isset($result['permissions']) && is_array($result['permissions'])) {
                    $items = $result['permissions'];
                } elseif (isset($result['items']) && is_array($result['items'])) {
                    $items = $result['items'];
                }
            }

            $hasInvoiceWrite = is_array($items) && count($items) > 0;
            $this->request->getSession()->write('Ksef.status', [
                'active' => $hasInvoiceWrite,
                'env'    => ($environment === 'prod') ? 'prod' : 'test',
                'ts'     => time(),
                'lastError' => $hasInvoiceWrite ? null : 'Brak uprawnienia InvoiceWrite (wystawianie) w KSeF.',
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);

            if ($hasInvoiceWrite) {
                $this->Flash->success('KSeF: uprawnienie InvoiceWrite (wystawianie) aktywne.');
            } else {
                $this->Flash->error('KSeF: brak uprawnienia InvoiceWrite (wystawianie).');
            }
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => ($environment === 'prod') ? 'prod' : 'test',
                'ts'     => time(),
                'lastError' => $details,
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $this->Flash->error('KSeF: nie udało się sprawdzić uprawnienia InvoiceWrite – ' . $details);
        }

        // Wróć na referer lub na listę "received"
        return $this->redirect($this->referer(['controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => $environment]]));
    }

    /**
     * AJAX/JSON: sprawdza uprawnienie InvoiceWrite (personal grants) i zapisuje wynik do sesji.
     * Używane przez layout (cache po stronie przeglądarki) – aby nie przeładowywać strony.
     */
    public function statusAjax()
    {
        $this->request->allowMethod(['get']);

        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $environment = ($environment === 'prod') ? 'prod' : 'test';
        $force       = (bool)$this->request->getQuery('force', false);
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        if ($companyId === '') {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'Brak powiązania z firmą.',
                ], JSON_UNESCAPED_UNICODE));
        }

        // Cache w sesji: jeśli status jest świeży, nie odpytywać KSeF.
        $cacheSeconds = (int)(Configure::read('Ksef.statusCacheSeconds') ?? 180);
        if ($cacheSeconds < 10) { $cacheSeconds = 10; }
        if ($cacheSeconds > 900) { $cacheSeconds = 900; }

        if (!$force) {
            $existing = $this->request->getSession()->read('Ksef.status');
            if (is_array($existing)
                && (($existing['env'] ?? null) === $environment)
                && (($existing['checkKind'] ?? null) === 'personalGrants')
                && (($existing['permissionType'] ?? null) === 'InvoiceWrite')
            ) {
                $ts = (int)($existing['ts'] ?? 0);
                if ($ts > 0 && (time() - $ts) < $cacheSeconds) {
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'cached' => true,
                            'status' => $existing,
                        ], JSON_UNESCAPED_UNICODE));
                }
            }
        }

        // NIP firmy (fallback jeśli nie podano as_nip)
        $companyNip = '';
        $companyProfileMode = 'business';
        try {
            /** @var \App\Model\Table\CompaniesTable $Companies */
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()->select(['nip', 'profile_mode'])->where(['Companies.id' => $companyId])->first();
            $companyNip = preg_replace('/\D/', '', (string)($company?->nip ?? ''));
            $companyProfileMode = (string)($company?->profile_mode ?? 'business');
        } catch (\Throwable) {
            $companyNip = '';
            $companyProfileMode = 'business';
        }

        $identifierNip = $asNip !== '' ? $asNip : $companyNip;
        if ($identifierNip === '') {
            $isPrivateRental = $companyProfileMode === 'private_rental';
            $status = [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $isPrivateRental
                    ? 'Profil najmu prywatnego bez NIP: pominięto weryfikację uprawnień KSeF.'
                    : 'Brak NIP firmy – nie można sprawdzić uprawnień w KSeF.',
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => true,
                'usingMasterMode' => (bool)(Configure::read('Ksef.forceMasterCert') ?? false) ? 'forced' : null,
                'identifierNip' => null,
            ];
            $this->request->getSession()->write('Ksef.status', $status);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => $isPrivateRental,
                    'error' => $isPrivateRental ? null : $status['lastError'],
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE));
        }

        // Cache współdzielony po stronie PHP (dla wielu userów tej samej firmy).
        // `force=1` pomija cache odczytu, ale nadal zapisujemy świeży wynik.
        $cacheKey = 'ksef_status_invoicewrite_' . sha1($companyId . '|' . $environment . '|' . $identifierNip);
        if (!$force) {
            $cachedStatus = Cache::read($cacheKey, 'ksefStatus');
            if (is_array($cachedStatus)
                && (($cachedStatus['env'] ?? null) === $environment)
                && (($cachedStatus['checkKind'] ?? null) === 'personalGrants')
                && (($cachedStatus['permissionType'] ?? null) === 'InvoiceWrite')
            ) {
                $this->request->getSession()->write('Ksef.status', $cachedStatus);

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'cached' => true,
                        'cachedScope' => 'php',
                        'status' => $cachedStatus,
                    ], JSON_UNESCAPED_UNICODE));
            }
        }

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());

        // Diagnoza kontekstu (best-effort)
        $diag = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $identifierNip);
        } catch (\Throwable) {
            $diag = null;
        }

        $usingMaster = false;
        $usingMasterMode = null;
        $diagIdentifierNip = null;
        if (is_array($diag)) {
            $diagIdentifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
            if (($diag['authMethod'] ?? '') === 'certificate') {
                if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                    $usingMaster = true;
                    $usingMasterMode = 'configured';
                } elseif (($diag['certSource'] ?? null) === 'master') {
                    $usingMaster = true;
                    $usingMasterMode = 'fallback';
                }
            }
        }
        if ((bool)(Configure::read('Ksef.forceMasterCert') ?? false)) {
            $usingMaster = true;
            $usingMasterMode = 'forced';
        }

        try {
            $filters = [
                'permissionState' => 'Active',
                'permissionTypes' => ['InvoiceWrite'],
            ];
            $result = $ksef->queryPersonalGrants(
                companyId: $companyId,
                environment: $environment,
                filters: $filters,
                pageOffset: 0,
                pageSize: 10,
                overrideIdentifierNip: $identifierNip,
            );

            $items = [];
            if (is_array($result)) {
                if (isset($result['permissions']) && is_array($result['permissions'])) {
                    $items = $result['permissions'];
                } elseif (isset($result['items']) && is_array($result['items'])) {
                    $items = $result['items'];
                }
            }
            $hasInvoiceWrite = is_array($items) && count($items) > 0;

            $status = [
                'active' => $hasInvoiceWrite,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $hasInvoiceWrite ? null : 'Brak uprawnienia InvoiceWrite (wystawianie) w KSeF.',
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $diagIdentifierNip ?: $identifierNip,
                'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
            ];
            $this->request->getSession()->write('Ksef.status', $status);
            Cache::write($cacheKey, $status, 'ksefStatus');

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'cached' => false,
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $status = [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'checkKind' => 'personalGrants',
                'permissionType' => 'InvoiceWrite',
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $diagIdentifierNip ?: $identifierNip,
                'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
            ];
            $this->request->getSession()->write('Ksef.status', $status);
            Cache::write($cacheKey, $status, 'ksefStatus');

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => $details,
                    'cached' => false,
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Zwraca pozycje (wiersze) faktury z KSeF jako JSON do wyboru w modalu.
     * URL: /ksef-authorizations/lines/{ksefNumber}?env=test
     */
    public function lines(string $ksefNumber)
    {
        $this->request->allowMethod(['get']);
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $client = $ksef->buildClient($companyId, $environment, $asNip ?: null);
            $response = $client->invoices()->download(new DownloadRequest(KsefNumberVO::from($ksefNumber)));
            $body = (string)$response->body();

            // Try to obtain plain XML (handle XML, GZIP, ZIP)
            [$xml, $decodeErr] = $this->extractXmlStringFromKsef($body);
            if ($xml === null) {
                $this->response = $this->response
                    ->withStatus(415)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Nieobsługiwany format pliku z KSeF: ' . $decodeErr], JSON_UNESCAPED_UNICODE));
                return $this->response;
            }

            // Fetch already saved booking items for marking in the UI
            /** @var \App\Model\Table\KsefBookingItemsTable $Table */
            $Table = $this->KsefBookingItems ?? $this->fetchTable('KsefBookingItems');
            $savedRows = $Table->find()
                ->select(['line_index','line_id','cost_type','note'])
                ->where([
                    'company_id' => $companyId,
                    'environment' => $environment,
                    'ksef_number' => $ksefNumber,
                ])->all();
            $savedIndexes = [];
            $savedLineIds = [];
            foreach ($savedRows as $sr) {
                $li = $sr->get('line_index');
                $lid= $sr->get('line_id');
                $ct = $sr->get('cost_type');
                $nt = $sr->get('note');
                if ($li !== null) { $savedIndexes[(int)$li] = ['saved' => true, 'cost_type' => $ct, 'note' => $nt]; }
                if ($lid) { $savedLineIds[(string)$lid] = ['saved' => true, 'cost_type' => $ct, 'note' => $nt]; }
            }

            // Parse defensively using XPath on local-name() to find FaWiersz or generic line items
            $items = [];
            $invoiceType = null;
            $dom = new \DOMDocument();
            $loadOk = @$dom->loadXML($xml);
            if ($loadOk) {
                $xp = new \DOMXPath($dom);

                // Detect invoice type (e.g. VAT, ZAL) for UI hints
                $t = $xp->query('//*[local-name()="RodzajFaktury"]');
                if ($t && $t->length) {
                    $invoiceType = trim((string)$t->item(0)?->textContent);
                    if ($invoiceType === '') $invoiceType = null;
                }

                // Prefer FA(3) FaWiersz; fallback to broader selectors
                $nodes = $xp->query('//*[local-name()="FaWiersz"]');
                if (!$nodes || $nodes->length === 0) {
                    $nodes = $xp->query('//*[contains(local-name(), "Wiersz") or contains(local-name(), "Pozyc")]');
                }
                $idx = 0;
                if ($nodes && $nodes->length) {
                    foreach ($nodes as $n) {
                        $idx++;
                        // Build child map (local-name => text)
                        $childMap = [];
                        foreach ($n->childNodes as $c) {
                            if ($c instanceof \DOMElement) {
                                $ln = strtolower($c->localName ?? $c->nodeName);
                                $val = trim((string)$c->textContent);
                                if ($ln !== '') { $childMap[$ln] = $val; }
                            }
                        }
                        $get = function(array $keys) use ($childMap) {
                            foreach ($keys as $k) {
                                $k2 = strtolower($k);
                                if (array_key_exists($k2, $childMap) && $childMap[$k2] !== '') return $childMap[$k2];
                            }
                            return null;
                        };
                        // Support FA(3) tag names + ZAL (ZamowienieWiersz) tags with "Z" suffix
                        $name = $get(['P_7','P_7Z','NazwaTowaruUslugi','Nazwa','OpisPozycji','Opis','PozycjaNazwa']);
                        // In FA(3): P_8A = unit, P_8B = quantity. In ZAL ZamowienieWiersz: P_8AZ = unit, P_8BZ = quantity.
                        $qty  = $get(['P_8B','P_8BZ','Ilosc','LiczbaJednostek','Quantity']);
                        $unit = $get(['P_8A','P_8AZ','JednostkaMiary','Jednostka','Unit']);
                        $price= $get(['P_9A','P_9AZ','CenaJednostkowa','CenaJedn','UnitPrice']);
                        $net  = $get(['P_11','P_11NettoZ','WartoscNetto','Netto','NetAmount']);
                        $vatR = $get(['P_12','P_12Z','StawkaPodatku','StawkaVAT','VatRate']);
                        $vatA = $get(['P_11VatZ','KwotaVat','KwotaVAT','VatAmount']);
                        $grs  = $get(['WartoscBrutto','Brutto','GrossAmount']);
                        $currency = $get(['Waluta','Currency']);
                        $lineId = $get(['NrWierszaFa','NrWierszaZam','NrWiersza','LineId']);

                        // Skip empty entries (no meaningful fields)
                        $hasAny = $name !== null || $qty !== null || $price !== null || $net !== null || $grs !== null;
                        if (!$hasAny) {
                            continue;
                        }
                        $toFloat = function($s) {
                            if ($s === null || $s === '') return null;
                            $s = str_replace(["\xC2\xA0", ' '], ['', ''], (string)$s); // nbsp and spaces
                            $s = str_replace([','], ['.'], $s);
                            return is_numeric($s) ? (float)$s : null;
                        };

                        $netF = $toFloat($net);
                        $vatAF = $toFloat($vatA);
                        $grsF = $toFloat($grs);
                        // For some ZAL structures gross may be missing: derive gross if possible
                        if ($grsF === null && $netF !== null && $vatAF !== null) {
                            $grsF = $netF + $vatAF;
                        }
                        $isSaved = false;
                        $savedType = null;
                        if ($lineId !== null && $lineId !== '' && isset($savedLineIds[(string)$lineId])) {
                            $isSaved = true;
                            $savedType = $savedLineIds[(string)$lineId]['cost_type'] ?? null;
                            $savedNote = $savedLineIds[(string)$lineId]['note'] ?? null;
                        } elseif (isset($savedIndexes[$idx])) {
                            $isSaved = true;
                            $savedType = $savedIndexes[$idx]['cost_type'] ?? null;
                            $savedNote = $savedIndexes[$idx]['note'] ?? null;
                        }

                        $items[] = [
                            'index' => $idx,
                            'line_id' => $lineId,
                            'name' => $name,
                            'description' => $name,
                            'quantity' => $toFloat($qty),
                            'unit' => $unit,
                            'unit_price' => $toFloat($price),
                            'net_amount' => $netF,
                            'vat_rate' => $vatR,
                            'vat_amount' => $vatAF,
                            'gross_amount' => $grsF,
                            'currency' => $currency,
                            'saved' => $isSaved,
                            'cost_type' => $savedType,
                            'note' => $savedNote ?? null,
                        ];
                    }
                }
            }

            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['items' => $items, 'invoice_type' => $invoiceType], JSON_UNESCAPED_UNICODE));
            return $this->response;
        } catch (\Throwable $e) {
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => $this->formatKsefError($e)], JSON_UNESCAPED_UNICODE));
            return $this->response;
        }
    }

    /**
     * API: Pozycje (wiersze) faktury z KSeF jako JSON.
     * GET /api/ksef/lines/{ksefNumber}?env=test|prod&as_nip=XXXXXXXXXX
     */
    public function linesApi(string $ksefNumber)
    {
        // lines() already returns JSON {items:[...]}
        return $this->lines($ksefNumber);
    }

    /**
     * Attempts to decode KSeF response body into a plain XML string.
     * Supports: plain XML, GZIP (\x1F\x8B), ZIP (PK\x03\x04) containing XML.
     * @return array{0: (string|null), 1: (string|null)} [xml, error]
     */
    private function extractXmlStringFromKsef(string $body): array
    {
        $trim = ltrim($body);
        // Remove UTF-8 BOM if present
        if (strncmp($trim, "\xEF\xBB\xBF", 3) === 0) {
            $trim = substr($trim, 3);
        }
        if ($trim !== '' && $trim[0] === '<') {
            return [$trim, null];
        }
        // GZIP
        if (strncmp($trim, "\x1F\x8B", 2) === 0) {
            if (function_exists('gzdecode')) {
                $xml = @gzdecode($trim);
                if (is_string($xml) && $xml !== '') {
                    return [$xml, null];
                }
                return [null, 'gzdecode failed'];
            }
            return [null, 'gzdecode not available'];
        }
        // ZIP
        if (strncmp($trim, "PK\x03\x04", 4) === 0) {
            $tmp = tempnam(sys_get_temp_dir(), 'ksefzip_') ?: null;
            if ($tmp === null) return [null, 'tempfile failed'];
            file_put_contents($tmp, $trim);
            $zip = new \ZipArchive();
            if ($zip->open($tmp) === true) {
                $xmlContent = null;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $name = $stat['name'] ?? '';
                    if (str_ends_with(strtolower($name), '.xml')) {
                        $xmlContent = $zip->getFromIndex($i);
                        break;
                    }
                }
                $zip->close();
                @unlink($tmp);
                if (is_string($xmlContent) && $xmlContent !== '') {
                    return [$xmlContent, null];
                }
                return [null, 'No XML file in ZIP'];
            }
            @unlink($tmp);
            return [null, 'ZIP open failed'];
        }
        return [null, 'Unknown format'];
    }

    /**
     * Returns cost categories for current company as JSON.
     * URL: /ksef-authorizations/costCategories
     */
    public function costCategories()
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        /** @var \App\Model\Table\CostCategoriesTable $Cats */
        $Cats = $this->fetchTable('CostCategories');
        $rows = $Cats->find()
            ->select(['id','name','code'])
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderAsc('sort_order')
            ->orderAsc('name')
            ->all();
        $items = array_map(function($r){
            return [
                'id' => $r->get('id'),
                'name' => (string)$r->get('name'),
                'code' => (string)($r->get('code') ?? ''),
            ];
        }, iterator_to_array($rows));
        if (empty($items)) {
            // Fallback minimal list if no categories configured
            $items = [
                ['id' => 'default-towary', 'name' => 'towary', 'code' => 'towary'],
                ['id' => 'default-materialy', 'name' => 'materiały', 'code' => 'materiały'],
                ['id' => 'default-uslugi', 'name' => 'usługi obce', 'code' => 'usługi obce'],
                ['id' => 'default-inne', 'name' => 'inne', 'code' => 'inne'],
            ];
        }
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['items' => $items], JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    /**
     * Returns booking summary for a given KSeF number: count of saved items and first non-empty note.
     * URL: /ksef-authorizations/bookingSummary/{ksefNumber}?env=test|prod
     */
    public function bookingSummary(string $ksefNumber)
    {
        $this->request->allowMethod(['get']);
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        /** @var \App\Model\Table\KsefBookingItemsTable $Table */
        $Table = $this->fetchTable('KsefBookingItems');
        $count = $Table->find()
            ->where([
                'company_id' => $companyId,
                'environment' => $environment,
                'ksef_number' => $ksefNumber,
            ])->count();

        // Count items without category (cost_type empty or null)
        $withoutCategory = $Table->find()
            ->where([
                'company_id' => $companyId,
                'environment' => $environment,
                'ksef_number' => $ksefNumber,
            ])
            ->andWhere(function($exp){
                return $exp->or([
                    'cost_type IS' => null,
                    'cost_type' => ''
                ]);
            })
            ->count();

        $firstNote = $Table->find()
            ->select(['note'])
            ->where([
                'company_id' => $companyId,
                'environment' => $environment,
                'ksef_number' => $ksefNumber,
                'note IS NOT' => null,
            ])
            ->andWhere(function($exp){ return $exp->notEq('note', ''); })
            ->orderAsc('created')
            ->first()?->get('note');

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'count' => (int)$count,
                'without_category' => (int)$withoutCategory,
                'first_note' => (string)($firstNote ?? ''),
            ], JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    /**
     * Diagnostyka aktualnego kontekstu autoryzacji KSeF.
     * URL: /ksef-authorizations/certDiagnostics?env=test|prod&as_nip=XXXXXXXXXX
     */
    public function certDiagnostics()
    {
        $this->request->allowMethod(['get']);
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $asNip       = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['diagnostics' => $diag], JSON_UNESCAPED_UNICODE));
            return $this->response;
        } catch (\Throwable $e) {
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => $this->formatKsefError($e)], JSON_UNESCAPED_UNICODE));
            return $this->response;
        }
    }

    /**
     * Zapisuje wybrane pozycje do tabeli ksef_booking_items (nadpisuje poprzedni wybór dla danej faktury/env/firmy).
     * URL: POST /ksef-authorizations/saveBookingItems
     * Body: { env: 'test'|'prod', ksef_number: '...', items: [ { index, name, ... } ] }
     */
    public function saveBookingItems()
    {
        $this->request->allowMethod(['post']);
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $payload = json_decode((string)$this->request->getBody(), true);
        $environment = (string)($payload['env'] ?? 'test');
        $ksefNumber  = (string)($payload['ksef_number'] ?? '');
        $items       = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($ksefNumber === '' || $companyId === '') {
            $this->response = $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak wymaganych danych (ksef_number/company).'], JSON_UNESCAPED_UNICODE));
            return $this->response;
        }

        /** @var \App\Model\Table\KsefBookingItemsTable $Table */
        $Table = $this->KsefBookingItems;

        $conn = $Table->getConnection();
        $conn->begin();
        try {
            // Remove previous selection for this invoice/env/company
            $Table->deleteAll([
                'company_id' => $companyId,
                'environment' => $environment,
                'ksef_number' => $ksefNumber,
            ]);

            $entities = [];
            $now = new \DateTimeImmutable();
            foreach ($items as $i) {
                $entities[] = $Table->newEntity([
                    'id' => \Cake\Utility\Text::uuid(),
                    'company_id' => $companyId,
                    'environment'=> $environment,
                    'ksef_number'=> $ksefNumber,
                    'line_index' => (int)($i['index'] ?? 0),
                    'line_id'    => (string)($i['line_id'] ?? ''),
                    'name'       => (string)($i['name'] ?? $i['description'] ?? ''),
                    'quantity'   => isset($i['quantity']) ? (float)$i['quantity'] : null,
                    'unit'       => (string)($i['unit'] ?? ''),
                    'unit_price' => isset($i['unit_price']) ? (float)$i['unit_price'] : null,
                    'net_amount' => isset($i['net_amount']) ? (float)$i['net_amount'] : null,
                    'vat_rate'   => (string)($i['vat_rate'] ?? ''),
                    'vat_amount' => isset($i['vat_amount']) ? (float)$i['vat_amount'] : null,
                    'gross_amount'=> isset($i['gross_amount']) ? (float)$i['gross_amount'] : null,
                    'currency'   => (string)($i['currency'] ?? ''),
                    'cost_type'  => (string)($i['cost_type'] ?? ''),
                    'note'       => (string)($i['note'] ?? ''),
                    'source_json'=> json_encode($i, JSON_UNESCAPED_UNICODE),
                    'created'    => $now,
                    'modified'   => $now,
                ]);
            }

            if (!empty($entities)) {
                if (!$Table->saveMany($entities)) {
                    throw new \RuntimeException('Nie udało się zapisać pozycji.');
                }
            }

            $conn->commit();
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'count' => count($entities)], JSON_UNESCAPED_UNICODE));
            return $this->response;
        } catch (\Throwable $e) {
            $conn->rollback();
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
            return $this->response;
        }
    }

    public function index()
    {
        $companyId = $this->request->getAttribute('identity')?->get('company_id');
        $query = $this->KsefAuthorizations->find()
            ->where(['company_id' => $companyId])
            ->orderDesc('created');

        $authorizations = $this->paginate($query);
        $this->set(compact('authorizations'));
    }

    public function view(string $id)
    {
        $auth = $this->KsefAuthorizations->get($id);
        // (opcjonalnie) sprawdź przynależność do firmy:
        // if ($auth->company_id !== $this->request->getAttribute('identity')->get('company_id')) { throw new ForbiddenException(); }
        $this->set(compact('auth'));
    }
public function deactivate(string $id)
{
    $auth = $this->KsefAuthorizations->get($id);
    if (!$auth->is_active) {
        $this->Flash->info('Ten token jest już nieaktywny.');
        return $this->redirect(['action' => 'view', $id]);
    }

    $auth->is_active = false;
    $auth->status = 'revoked';
    if ($this->KsefAuthorizations->save($auth)) {
        $this->Flash->success('Token został dezaktywowany.');
    } else {
        $this->Flash->error('Nie udało się dezaktywować tokenu.');
    }

    return $this->redirect(['action' => 'view', $id]);
}

    /**
     * Próbuje wyciągnąć z wyjątku jak najwięcej informacji zwrotnych z KSeF/Guzzle.
     */
    private function formatKsefError(\Throwable $e): string
    {
        // Jeżeli to wyjątek Guzzle z odpowiedzią HTTP
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $res = $e->getResponse();
            $status = $res?->getStatusCode();
            $body = (string)$res?->getBody();
            $msg = 'HTTP ' . $status . ' - ' . $e->getMessage();
            // Spróbuj sparsować JSON z treścią błędu KSeF
            $decoded = null;
            if ($body !== '') {
                $decoded = json_decode($body, true);
            }
            if (is_array($decoded)) {
                // Szukamy najczęstszych pól: code/message/details
                $code = $decoded['code'] ?? $decoded['error']['code'] ?? $decoded['exceptionCode'] ?? null;
                $message = $decoded['message'] ?? $decoded['error']['message'] ?? $decoded['exception'] ?? null;
                $more = $decoded['details'] ?? $decoded['detail'] ?? null;
                $parts = array_filter([
                    $msg,
                    $code ? ('kod: ' . $code) : null,
                    $message ? ('komunikat: ' . $message) : null,
                    $more ? ('szczegóły: ' . (is_string($more) ? $more : json_encode($more, JSON_UNESCAPED_UNICODE))) : null,
                ]);
                return implode(' | ', $parts);
            }
            // Nie-JSON – zwróć fragment treści
            if ($body !== '') {
                return $msg . ' | body: ' . substr($body, 0, 500);
            }
            return $msg;
        }

        // Inne wyjątki (np. domenowe z biblioteki) – pokaż klasę + message
        return $e->getMessage();
    }

public function add()
{
    $auth = $this->KsefAuthorizations->newEmptyEntity();

    if ($this->request->is('post')) {
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        if (!$companyId) {
            $this->Flash->error('Brak powiązania z firmą.');
            return;
        }

        // pobierz firmę z bazy
        $Companies = $this->fetchTable('Companies');
        $company = $Companies->find()
            ->select(['id', 'nip'])
            ->where(['id' => $companyId])
            ->first();

        if (!$company?->nip) {
            $this->Flash->error('Brak numeru NIP w danych firmy.');
            return;
        }

        $token = trim((string)$this->request->getData('token'));

        if (strlen($token) < 32) {
            $this->Flash->error('Token wygląda na nieprawidłowy.');
        } else {
            // pakujemy sysToken + NIP w JSON
            $payload = json_encode([
                'v'        => 2,
                'sysToken' => $token,
                'nip'      => preg_replace('/\D/', '', $company->nip),
            ], JSON_UNESCAPED_SLASHES);

            $auth = $this->KsefAuthorizations->patchEntity($auth, [
                'id'              => \Cake\Utility\Text::uuid(),
                'company_id'      => $companyId,
                'environment'     => $this->request->getData('environment') ?: 'prod',
                'status'          => 'active',
                'is_active'       => (bool)$this->request->getData('is_active', true),
                'auth_method'     => 'ksef_token',
                'token_cipher'    => \App\Utility\TokenVault::encrypt($payload),
                'token_last4'     => substr($token, -4),
                'valid_from'      => null,
                'expires_at'      => null,
            ]);

            if ($auth->is_active) {
                // dezaktywuj poprzednie tokeny firmy
                $this->KsefAuthorizations->updateAll(
                    ['is_active' => false],
                    ['company_id' => $companyId, 'is_active' => true]
                );
            }

            if ($this->KsefAuthorizations->save($auth)) {
                $this->Flash->success('Token zapisany. Integracja KSeF gotowa.');
                // return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error('Nie udało się zapisać tokenu.');
                debug($auth->getErrors());
            }
        }
    }

    $this->set(compact('auth'));
}

}

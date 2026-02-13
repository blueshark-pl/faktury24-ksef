<?php
declare(strict_types=1);

namespace App\Controller;
use App\Model\Table\InvoicesTable;
use Cake\I18n\FrozenDate;
use App\Service\Ksef\KsefApiV2Service;
use Cake\Datasource\Paging\Exception\PageOutOfBoundsException;
use Cake\Utility\Text;
use App\Utility\TokenVault;
use App\Service\Ksef\N1KsefService;
use App\Service\Ksef\DbKsefTokenStorage;
use App\Service\Ksef\CertificateStorage;
use Psr\Http\Message\UploadedFileInterface;
use N1ebieski\KSEFClient\Requests\Invoices\Download\DownloadRequest;
use N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber as KsefNumberVO;

;

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

    public function sessions()
    {
        $service = new KsefApiV2Service();  
        $resp = $service->getCurrentSessions(10);

        $this->set(compact('resp'));
        $this->viewBuilder()->setOption('serialize', ['resp']); // jeśli JSON
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
        // --- Filtry z query ---
    $q        = trim((string)$this->request->getQuery('q'));
    $ksefNo   = trim((string)$this->request->getQuery('ksef'));
    $invNo    = trim((string)$this->request->getQuery('inv'));
    $sellerNip= preg_replace('/\D/', '', (string)$this->request->getQuery('seller_nip'));
    $buyerNip = preg_replace('/\D/', '', (string)$this->request->getQuery('buyer_nip'));
        $from     = $this->request->getQuery('from');      // Y-m-d
        $to       = $this->request->getQuery('to');        // Y-m-d
    $currency = $this->request->getQuery('currency');  // PLN/EUR/...
    // Opcjonalny override identyfikatora (NIP), gdy certyfikat ma uprawnienia do innego podmiotu
    $asNip   = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        // --- KSeF: budowanie filtrów do query metadata ---
        $filters = [];
        if ($from || $to) {
            $fromIso = $from ? (new FrozenDate($from))->format('Y-m-d') . 'T00:00:00Z' : null;
            $toIso   = $to   ? (new FrozenDate($to))->format('Y-m-d')   . 'T23:59:59Z' : null;
            $filters['dateRange'] = array_filter([
                'from'     => $fromIso,
                'to'       => $toIso,
                'dateType' => 'Issue', // lub 'Acquisition' zależnie od preferencji
            ]);
        }
    // Wstępne ograniczenie do „otrzymanych” – w KSeF to zwykle perspektywa nabywcy (Subject1)
        $filters['subjectType'] = 'Subject2';
    if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
    if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
    if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
    if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        // --- Paginacja po stronie API (pageOffset/pageSize) ---
        $limit = 25;
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $pageOffset = $page - 1; // KSeF zwykle liczy offset stron od 0
        $pageSize   = $limit;

        // --- Budowa klienta i pobranie metadanych ---
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

    $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        // Diagnostyka: zapisz dyskretny status „usingMaster” do sesji (bez nachalnego Flash)
        $diag = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
        } catch (\Throwable $e) { /* optional */ }
        $usingMaster = true;
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
        try {
            $result = $ksef->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            // Zapisz status połączenia KSeF do sesji na potrzeby paska w sidebarze
            $this->request->getSession()->write('Ksef.status', [
                'active' => true,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => null,
                // dyskretny wskaźnik certyfikatu master
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $this->ksefDebug('received', $result);
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->Flash->error('Błąd połączenia z KSeF: ' . $details);
            if (class_exists('Cake\\Log\\Log')) {
                \Cake\Log\Log::error('[KSeF] Błąd autoryzacji/zapytania: ' . $e::class . ': ' . $e->getMessage());
            }
            // Oznacz połączenie jako niedostępne
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $result = ['items' => [], 'total' => 0];
        }

    // --- Elementy listy i lekka filtracja po "q" lokalnie ---
    $items = $this->extractKsefItems($result);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $sell = (string)($row['sellerName'] ?? $row['supplierName'] ?? '');
                $nip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($sell), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }

        // --- Mapowanie do uproszczonej struktury dla widoku ---
        $invoices = array_map(function ($row) {
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $sellerArr = is_array($row['seller'] ?? null) ? ($row['seller'] ?? []) : [];
            $contractorName = (string)($row['sellerName'] ?? $row['supplierName'] ?? $sellerArr['name'] ?? $sellerArr['fullName'] ?? '');
            $contractorNip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? $sellerArr['nip'] ?? $sellerArr['taxId'] ?? '');
            return [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $date ? new FrozenDate(substr($date, 0, 10)) : null,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'paymentstate'=> null,
                'paymentdate'=> null,
                // Dodatkowe pola do badge’y
                'invoicingMode'   => (string)($row['invoicingMode'] ?? ''),
                'invoiceType'     => (string)($row['invoiceType'] ?? ''),
                'isSelfInvoicing' => (bool)($row['isSelfInvoicing'] ?? false),
                'hasAttachment'   => (bool)($row['hasAttachment'] ?? false),
                'InvoiceContractors' => [
                    'name'   => $contractorName,
                    'tax_id' => $contractorNip,
                ],
            ];
        }, $items);

        // Informacja użytkownikowi o wyniku pobierania z KSeF
    $fetched = count($invoices);
    $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
    if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
        $totalFromApi = count($result['invoices']);
    }
        if ($fetched > 0) {
            $this->Flash->success(sprintf('Pobrano %d pozycji z KSeF (strona %d).%s', $fetched, $page, $totalFromApi ? " Łącznie: $totalFromApi." : ''));
        } else {
            $this->Flash->info('Brak faktur w KSeF dla wybranych filtrów. Zmień filtry i spróbuj ponownie.');
        }

        // --- Proste statystyki z otrzymanych metadanych (bez paymentstate) ---
        $statsCurrency = $currency ? strtoupper($currency) : 'PLN';
        $yearTotal = array_reduce($invoices, fn($c, $i) => $c + (float)($i['total'] ?? 0), 0.0);
        $yearCount = count($invoices);
        $stats = [
            'currency'         => $statsCurrency,
            'year_total'       => $yearTotal,
            'year_count'       => $yearCount,
            'year_paid'        => 0.0,
            'paid_total'       => 0.0,
            'paid_count'       => 0,
            'paid_avg'         => 0.0,
            'pending_count'    => 0,
            'pending_total'    => 0.0,
            'remaining_total'  => 0.0,
            'overdue_count'    => 0,
            'overdue_total'    => 0.0,
            'overdue_max_days' => 0,
            'month_paid_count' => 0,
        ];

        // --- Paginacja: po stronie API użyliśmy pageOffset/pageSize; na widoku możemy użyć prostych danych.

        // --- Zmienne dla widoku (którego nie ruszamy) ---
    // Informacje dodatkowe do widoku
    $apiInfo = [
        'total' => $totalFromApi,
        'hasMore' => (bool)($result['hasMore'] ?? $result['result']['hasMore'] ?? false),
        'isTruncated' => (bool)($result['isTruncated'] ?? $result['result']['isTruncated'] ?? false),
    ];
    $this->set('apiInfo', $apiInfo);
    $this->set('ksefEnv', $environment);
    // Opcjonalnie: próba odczytu meta o certyfikacie
    $certInfo = null;
    $metaPath = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ksef_certs' . DIRECTORY_SEPARATOR . 'company_' . $companyId . DIRECTORY_SEPARATOR . $environment . DIRECTORY_SEPARATOR . 'meta.json';
    if (is_file($metaPath)) {
        $metaJson = @file_get_contents($metaPath);
        $certInfo = json_decode((string)$metaJson, true) ?: null;
    }
    $this->set('certInfo', $certInfo);

    $this->set(compact('invoices', 'stats'));

        // Jeśli masz osobny template `templates/KsefAuthorizations/received.php`, to:
        $this->render('received');

        // Jeśli korzystasz z tego samego widoku co "lista faktur", po prostu:
        // $this->viewBuilder()->setTemplatePath('Invoices');
        // $this->render('index'); // lub nazwa Twojego pliku
    }

    /**
     * API: Zwraca listę „Faktur otrzymanych (KSeF)” w formacie JSON.
     * GET /api/ksef/received?env=test|prod&as_nip=XXXXXXXXXX&ksef=...&inv=...&seller_nip=...&buyer_nip=...&from=Y-m-d&to=Y-m-d&currency=PLN&page=1&q=...
     */
    public function receivedApi()
    {
        $this->request->allowMethod(['get']);

        // --- Filtry z query ---
        $q        = trim((string)$this->request->getQuery('q'));
        $ksefNo   = trim((string)$this->request->getQuery('ksef'));
        $invNo    = trim((string)$this->request->getQuery('inv'));
        $sellerNip= preg_replace('/\D/', '', (string)$this->request->getQuery('seller_nip'));
        $buyerNip = preg_replace('/\D/', '', (string)$this->request->getQuery('buyer_nip'));
        $from     = $this->request->getQuery('from');      // Y-m-d
        $to       = $this->request->getQuery('to');        // Y-m-d
        $currency = $this->request->getQuery('currency');  // PLN/EUR/...
        $asNip    = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $filters = [];
        if ($from || $to) {
            // N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\DateRangeFrom/To serializują do formatu "Y-m-d\TH:i:s" (bez strefy).
            // Utrzymujmy ten sam format, żeby nie wchodzić w różnice parsowania między env.
            $fromIso = $from ? (new FrozenDate($from))->format('Y-m-d') . 'T00:00:00' : null;
            $toIso   = $to   ? (new FrozenDate($to))->format('Y-m-d')   . 'T23:59:59' : null;
            $filters['dateRange'] = array_filter([
                'from'     => $fromIso,
                'to'       => $toIso,
                'dateType' => 'Issue',
            ]);
        }
        // W tym kontrolerze „received” używa Subject2 (jak w istniejącej metodzie)
        $filters['subjectType'] = 'Subject2';
        if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
        if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
        if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
        if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        // Paginacja
        $limit = 25;
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        // KSeF v2 używa pageOffset jako offsetu rekordów (0, 25, 50...), a nie numeru strony.
        $pageOffset = ($page - 1) * $limit;
        $pageSize   = $limit;

        // Kontekst firmy i środowisko
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $envRaw = (string)$this->request->getQuery('env', 'test');
        $environment = ($envRaw === 'prod') ? 'prod' : 'test';

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());

        $diag = null;
        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
            
            if (is_array($diag)) {
                $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
                if (($diag['authMethod'] ?? '') === 'certificate') {
                    if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                        $usingMaster = true; $usingMasterMode = 'configured';
                    } elseif (($diag['certSource'] ?? null) === 'master') {
                        $usingMaster = true; $usingMasterMode = 'fallback';
                    }
                }
            }
        } catch (\Throwable $e) { /* optional */ }

        try {
            $result = $ksef->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            $this->request->getSession()->write('Ksef.status', [
                'active' => true,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => $details,
                ], JSON_UNESCAPED_UNICODE));
        }

        $items = $this->extractKsefItems($result);
        // debug($items);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $sell = (string)($row['sellerName'] ?? $row['supplierName'] ?? '');
                $nip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($sell), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }

        $invoices = array_map(function ($row) {
            $raw = is_array($row) ? $row : [];
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $invoiceType = trim((string)($row['invoiceType'] ?? $row['invoice_type'] ?? ''));
            if ($invoiceType === '') {
                $invoiceType = null;
            }
            $sellerArr = is_array($row['seller'] ?? null) ? ($row['seller'] ?? []) : [];
            $contractorName = (string)($row['sellerName'] ?? $row['supplierName'] ?? $sellerArr['name'] ?? $sellerArr['fullName'] ?? '');
            $contractorNip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? $sellerArr['nip'] ?? $sellerArr['taxId'] ?? '');
            $base = [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $date ? substr($date, 0, 10) : null,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'invoice_type' => $invoiceType,
                'InvoiceContractors' => [
                    'name'   => $contractorName,
                    'tax_id' => $contractorNip,
                ],
            ];

            // Prefer to return *everything* from KSeF metadata, plus our legacy/simplified fields.
            // Union keeps raw keys as-is and adds missing legacy keys.
            return $raw + $base;
        }, $items);

        $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
        if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $totalFromApi = count($result['invoices']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'env' => $environment,
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFromApi,
                'fetched' => count($invoices),
                'filters' => [
                    'q' => $q, 'ksef' => $ksefNo, 'inv' => $invNo, 'seller_nip' => $sellerNip, 'buyer_nip' => $buyerNip,
                    'from' => $from, 'to' => $to, 'currency' => $currency, 'as_nip' => $asNip,
                ],
                'items' => $invoices,
            ], JSON_UNESCAPED_UNICODE));
    }
private function ksefMaster(): \App\Service\Ksef\N1KsefMasterService
{
    return new \App\Service\Ksef\N1KsefMasterService(
        new \App\Service\Ksef\MasterCertProvider(),
        new \App\Service\Ksef\FileMetaStorage(),
    );
}

    /**
     * API: Zwraca listę „Faktur wystawionych (KSeF)” w formacie JSON.
     * GET /api/ksef/issued?env=test|prod&as_nip=XXXXXXXXXX&ksef=...&inv=...&seller_nip=...&buyer_nip=...&from=Y-m-d&to=Y-m-d&currency=PLN&page=1&q=...
     */
    public function issuedApi()
    {
        $this->request->allowMethod(['get']);

        // --- Filtry z query ---
        $q        = trim((string)$this->request->getQuery('q'));
        $ksefNo   = trim((string)$this->request->getQuery('ksef'));
        $invNo    = trim((string)$this->request->getQuery('inv'));
        $sellerNip= preg_replace('/\D/', '', (string)$this->request->getQuery('seller_nip'));
        $buyerNip = preg_replace('/\D/', '', (string)$this->request->getQuery('buyer_nip'));
        $from     = $this->request->getQuery('from');
        $to       = $this->request->getQuery('to');
        $currency = $this->request->getQuery('currency');
        $asNip    = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));

        $filters = [];
        if ($from || $to) {
            $fromIso = $from ? (new FrozenDate($from))->format('Y-m-d') . 'T00:00:00Z' : null;
            $toIso   = $to   ? (new FrozenDate($to))->format('Y-m-d')   . 'T23:59:59Z' : null;
            $filters['dateRange'] = array_filter([
                'from'     => $fromIso,
                'to'       => $toIso,
                'dateType' => 'Issue',
            ]);
        }
        // issued() używa Subject1 – trzymamy to samo w API
        $filters['subjectType'] = 'Subject1';
        if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
        if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
        if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
        if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        // Paginacja
        $limit = 25;
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $pageOffset = $page - 1;
        $pageSize   = $limit;

        // Kontekst firmy i środowisko
        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');

        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());

        $diag = null;
        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
            if (is_array($diag)) {
                $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
                if (($diag['authMethod'] ?? '') === 'certificate') {
                    if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                        $usingMaster = true; $usingMasterMode = 'configured';
                    } elseif (($diag['certSource'] ?? null) === 'master') {
                        $usingMaster = true; $usingMasterMode = 'fallback';
                    }
                }
            }
        } catch (\Throwable $e) { /* optional */ }

        try {
            $result = $ksef->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            $this->request->getSession()->write('Ksef.status', [
                'active' => true,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => $details,
                ], JSON_UNESCAPED_UNICODE));
        }

        $items = $this->extractKsefItems($result);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $buyer = (string)($row['buyerName'] ?? $row['purchaserName'] ?? '');
                $nip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($buyer), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }
        $invoices = array_map(function ($row) {
            $raw = is_array($row) ? $row : [];
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $invoiceType = trim((string)($row['invoiceType'] ?? $row['invoice_type'] ?? ''));
            if ($invoiceType === '') {
                $invoiceType = null;
            }
            $buyerArr = is_array($row['buyer'] ?? null) ? ($row['buyer'] ?? []) : [];
            $buyerName = (string)($row['buyerName'] ?? $row['purchaserName'] ?? $buyerArr['name'] ?? $buyerArr['fullName'] ?? '');
            $buyerNip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? $buyerArr['nip'] ?? $buyerArr['taxId'] ?? '');
            $base = [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $date ? substr($date, 0, 10) : null,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'invoice_type' => $invoiceType,
                'InvoiceContractors' => [
                    'name'   => $buyerName,
                    'tax_id' => $buyerNip,
                ],
            ];

            return $raw + $base;
        }, $items);

        $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
        if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $totalFromApi = count($result['invoices']);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'env' => $environment,
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFromApi,
                'fetched' => count($invoices),
                'filters' => [
                    'q' => $q, 'ksef' => $ksefNo, 'inv' => $invNo, 'seller_nip' => $sellerNip, 'buyer_nip' => $buyerNip,
                    'from' => $from, 'to' => $to, 'currency' => $currency, 'as_nip' => $asNip,
                ],
                'items' => $invoices,
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lista "Faktur wystawionych w KSeF" (perspektywa sprzedawcy = Subject2).
     * Implementacja analogiczna do received(), różni się tylko subjectType.
     */
    public function issued(): void
    {
    $q        = trim((string)$this->request->getQuery('q'));
    $ksefNo   = trim((string)$this->request->getQuery('ksef'));
    $invNo    = trim((string)$this->request->getQuery('inv'));
    $sellerNip= preg_replace('/\D/', '', (string)$this->request->getQuery('seller_nip'));
    $buyerNip = preg_replace('/\D/', '', (string)$this->request->getQuery('buyer_nip'));
    $asNip    = preg_replace('/\D/', '', (string)$this->request->getQuery('as_nip'));
        $from     = $this->request->getQuery('from');
        $to       = $this->request->getQuery('to');
        $currency = $this->request->getQuery('currency');

        $filters = [];
        if ($from || $to) {
            $fromIso = $from ? (new FrozenDate($from))->format('Y-m-d') . 'T00:00:00Z' : null;
            $toIso   = $to   ? (new FrozenDate($to))->format('Y-m-d')   . 'T23:59:59Z' : null;
            $filters['dateRange'] = array_filter([
                'from'     => $fromIso,
                'to'       => $toIso,
                'dateType' => 'Issue',
            ]);
        }
        // Perspektywa sprzedawcy
    $filters['subjectType'] = 'Subject1';
    if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
    if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
    if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
    if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        $limit = 25;
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $pageOffset = $page - 1;
        $pageSize   = $limit;

        $identity    = $this->request->getAttribute('identity');
        $companyId   = (string)($identity?->get('company_id') ?? '');
        $environment = (string)$this->request->getQuery('env', 'test');
        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        // Diagnostyka: zapisz dyskretny status „usingMaster” do sesji (bez Flash)
        $diag = null;
        try {
            $diag = $ksef->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
        } catch (\Throwable $e) { /* optional */ }
        $usingMaster = false;
        $usingMasterMode = null;
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
        try {
            $result = $ksef->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            // Zapisz status połączenia KSeF do sesji (sidebar)
            $this->request->getSession()->write('Ksef.status', [
                'active' => true,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $this->ksefDebug('issued', $result);
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->Flash->error('Błąd połączenia z KSeF: ' . $details);
            if (class_exists('Cake\\Log\\Log')) {
                \Cake\Log\Log::error('[KSeF] Błąd autoryzacji/zapytania (issued): ' . $e::class . ': ' . $e->getMessage());
            }
            // Oznacz połączenie jako niedostępne
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $result = ['items' => [], 'total' => 0];
        }

    $items = $this->extractKsefItems($result);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $buyer = (string)($row['buyerName'] ?? $row['purchaserName'] ?? '');
                $nip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($buyer), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }

        $invoices = array_map(function ($row) {
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            // Spróbuj zagnieżdżonych struktur buyer/seller
            $buyerArr = is_array($row['buyer'] ?? null) ? ($row['buyer'] ?? []) : [];
            $buyerName = (string)($row['buyerName'] ?? $row['purchaserName'] ?? $buyerArr['name'] ?? $buyerArr['fullName'] ?? '');
            $buyerNip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? $buyerArr['nip'] ?? $buyerArr['taxId'] ?? '');
            return [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $date ? new FrozenDate(substr($date, 0, 10)) : null,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'paymentstate'=> null,
                'paymentdate'=> null,
                'invoicingMode'   => (string)($row['invoicingMode'] ?? ''),
                'invoiceType'     => (string)($row['invoiceType'] ?? ''),
                'isSelfInvoicing' => (bool)($row['isSelfInvoicing'] ?? false),
                'hasAttachment'   => (bool)($row['hasAttachment'] ?? false),
                'InvoiceContractors' => [
                    'name'   => $buyerName,
                    'tax_id' => $buyerNip,
                ],
            ];
        }, $items);

        $statsCurrency = $currency ? strtoupper($currency) : 'PLN';
        $yearTotal = array_reduce($invoices, fn($c, $i) => $c + (float)($i['total'] ?? 0), 0.0);
        $yearCount = count($invoices);
        $stats = [
            'currency'         => $statsCurrency,
            'year_total'       => $yearTotal,
            'year_count'       => $yearCount,
            'year_paid'        => 0.0,
            'paid_total'       => 0.0,
            'paid_count'       => 0,
            'paid_avg'         => 0.0,
            'pending_count'    => 0,
            'pending_total'    => 0.0,
            'remaining_total'  => 0.0,
            'overdue_count'    => 0,
            'overdue_total'    => 0.0,
            'overdue_max_days' => 0,
            'month_paid_count' => 0,
        ];

    $fetched = count($invoices);
    $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
    if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
        $totalFromApi = count($result['invoices']);
    }
        if ($fetched > 0) {
            $this->Flash->success(sprintf('Pobrano %d wystawionych z KSeF (strona %d).%s', $fetched, $page, $totalFromApi ? " Łącznie: $totalFromApi." : ''));
        } else {
            $this->Flash->info('Brak wystawionych w KSeF dla wybranych filtrów.');
        }

        $apiInfo = [
            'total' => $totalFromApi,
            'hasMore' => (bool)($result['hasMore'] ?? $result['result']['hasMore'] ?? false),
            'isTruncated' => (bool)($result['isTruncated'] ?? $result['result']['isTruncated'] ?? false),
        ];
        $this->set('apiInfo', $apiInfo);
        $this->set('ksefEnv', $environment);
        $certInfo = null;
        $metaPath = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ksef_certs' . DIRECTORY_SEPARATOR . 'company_' . $companyId . DIRECTORY_SEPARATOR . $environment . DIRECTORY_SEPARATOR . 'meta.json';
        if (is_file($metaPath)) {
            $metaJson = @file_get_contents($metaPath);
            $certInfo = json_decode((string)$metaJson, true) ?: null;
        }
        $this->set('certInfo', $certInfo);

        $this->set(compact('invoices', 'stats'));
    $this->render('issued');
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

        // Lekka próba – mała strona metadanych, aby ocenić dostępność
        try {
            $filters = ['subjectType' => 'Subject2'];
            $result = $ksef->queryReceivedMetadata($companyId, $environment, $filters, 0, 1, $asNip ?: null);
            $this->request->getSession()->write('Ksef.status', [
                'active' => true,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $this->Flash->success('Połączenie z KSeF: aktywne.');
        } catch (\Throwable $e) {
            $details = $this->formatKsefError($e);
            $this->request->getSession()->write('Ksef.status', [
                'active' => false,
                'env'    => $environment,
                'ts'     => time(),
                'lastError' => $details,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => $diag['authMethod'] ?? null,
                'certSource' => $diag['certSource'] ?? null,
            ]);
            $this->Flash->error('Połączenie z KSeF: niedostępne – ' . $details);
        }

        // Wróć na referer lub na listę "received"
        return $this->redirect($this->referer(['controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => $environment]]));
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

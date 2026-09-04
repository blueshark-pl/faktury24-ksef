<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\Invoice\InvoiceNumberingService;
use App\Service\Jpk\JpkV7mGenerator;
use App\Service\Jpk\JpkV7mValidator;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * External API — Invoice creation
 *
 * Authentication: Bearer token in Authorization header.
 * Every request must belong to a company identified by the token.
 *
 * POST /api/v1/invoices  →  create VAT invoice
 */
class InvoicesController extends AppController
{
    /**
     * Master read-only token — pozwala portalowi pobierać faktury dowolnej firmy
     * po jej NIP-ie (endpoint bySellerNip). Nie powiązany z `api_tokens`.
     *
     * UWAGA: ten token musi być synchronizowany z portalem
     * (G:/2023/portal.partnersc.com/src/Controller/SalesInvoicesController.php).
     */
    private const MASTER_TOKEN = 'fv_master9a4b8e6c2d7f1a5b9e3c8d6f2a4b7e1c5d9a3b8e6c';

    private InvoiceNumberingService $numbering;

    public function initialize(): void
    {
        parent::initialize();
        $this->numbering = new InvoiceNumberingService();

        // Disable CSRF for API (token auth instead)
        if ($this->components()->has('FormProtection')) {
            $this->components()->unload('FormProtection');
        }
        if ($this->components()->has('Security')) {
            $this->components()->unload('Security');
        }
    }

    /**
     * Allow all API actions without session authentication.
     * Each action authenticates itself via Bearer token.
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        // Przerwa techniczna — API zwraca 503 JSON (fail-open: błąd mechanizmu nie blokuje).
        try {
            $maint = new \App\Service\MaintenanceService();
            if ($maint->isActive()) {
                $event->setResult(
                    $this->response->withStatus(503)
                        ->withHeader('Retry-After', '3600')
                        ->withType('application/json')
                        ->withStringBody(json_encode(
                            ['success' => false, 'error' => 'Trwają prace techniczne. Spróbuj ponownie później.'],
                            JSON_UNESCAPED_UNICODE
                        ))
                );

                return;
            }
        } catch (\Throwable) {}

        // Skip parent beforeFilter (which checks session identity and sets view vars)
        // to avoid redirect-to-login for unauthenticated requests.
        // We handle auth manually via Bearer token in each action.
        try {
            $authentication = $this->request->getAttribute('authentication');
            if ($authentication && method_exists($authentication, 'allowUnauthenticated')) {
                $authentication->allowUnauthenticated(['create', 'index', 'get', 'addPayment', 'status', 'issue', 'sendKsef', 'pdf', 'bySellerNip', 'jpkV7m']);
            }
        } catch (\Throwable) {}
    }

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve company_id from Bearer token.
     * Returns the token entity or sends a 401 JSON response and returns null.
     */
    private function authenticate(): ?\App\Model\Entity\ApiToken
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (!str_starts_with($header, 'Bearer ')) {
            $this->jsonError(401, 'Brak tokenu autoryzacji. Podaj nagłówek: Authorization: Bearer <token>');
            return null;
        }

        $rawToken = substr($header, 7);
        /** @var \App\Model\Table\ApiTokensTable $ApiTokens */
        $ApiTokens = $this->fetchTable('ApiTokens');
        $token = $ApiTokens->findByRawToken($rawToken);

        if ($token === null) {
            $this->jsonError(401, 'Nieprawidłowy lub nieaktywny token.');
            return null;
        }

        return $token;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices
    // -------------------------------------------------------------------------

    public function index(): Response
    {
        $this->request->allowMethod(['get']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $q = $this->request->getQueryParams();

        $Invoices = $this->fetchTable('Invoices');
        $query = $Invoices->find()
            ->select([
                'Invoices.id', 'Invoices.fullnumber', 'Invoices.date', 'Invoices.sold_date',
                'Invoices.type', 'Invoices.total', 'Invoices.netto', 'Invoices.tax',
                'Invoices.currency', 'Invoices.paymentmethod', 'Invoices.paymentdate',
                'Invoices.paymentstate', 'Invoices.alreadypaid', 'Invoices.remaining',
                'Invoices.description', 'Invoices.ksef_number', 'Invoices.ksef_status',
                'Invoices.workflow_status',
                'Invoices.created', 'Invoices.modified',
            ])
            ->contain([
                'InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'name', 'nip', 'city']),
            ])
            ->where(['Invoices.company_id' => $companyId])
            ->orderBy(['Invoices.date' => 'DESC', 'Invoices.created' => 'DESC']);

        // ── Filters ──────────────────────────────────────────────────────────
        if (!empty($q['date_from'])) {
            $query->where(['Invoices.date >=' => $q['date_from']]);
        }
        if (!empty($q['date_to'])) {
            $query->where(['Invoices.date <=' => $q['date_to']]);
        }
        // Filtr po NIP nabywcy (snapshot w invoice_contractors).
        // NIP w bazie bywa zapisany w różnych wariantach („1234567890", „123-456-78-90",
        // „PL1234567890"). Tworzymy listę popularnych zapisów i robimy IN — index-friendly,
        // bez REPLACE() w WHERE.
        if (!empty($q['nip'])) {
            $nipDigits = preg_replace('/\D+/', '', (string)$q['nip']);
            // Pełny polski NIP ma 10 cyfr — formatujemy 3-3-2-2.
            $nipDashed = (strlen($nipDigits) === 10)
                ? substr($nipDigits, 0, 3) . '-' . substr($nipDigits, 3, 3) . '-' . substr($nipDigits, 6, 2) . '-' . substr($nipDigits, 8, 2)
                : null;
            if ($nipDigits !== '') {
                $variants = array_values(array_unique(array_filter([
                    $nipDigits,
                    'PL' . $nipDigits,
                    'pl' . $nipDigits,
                    $nipDashed,
                    (string)$q['nip'],          // pass-through (jeśli ktoś poda np. "PL 123-456-78-90")
                ])));
                $query->leftJoinWith('InvoiceContractors')
                      ->where(['InvoiceContractors.nip IN' => $variants])
                      ->group(['Invoices.id']);
            }
        }
        if (!empty($q['type'])) {
            $query->where(['Invoices.type' => $q['type']]);
        }
        if (!empty($q['paymentstate'])) {
            $query->where(['Invoices.paymentstate' => $q['paymentstate']]);
        }
        // Filtr po workflow_status: draft | issued | sending | sent.
        // Akceptujemy alias „status" oraz wielokrotne wartości po przecinku („draft,issued").
        // „is_draft=1/0" działa jak skrót — true == draft, false == NOT draft.
        $statusParam = $q['workflow_status'] ?? $q['status'] ?? null;
        if ($statusParam !== null && $statusParam !== '') {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string)$statusParam))));
            if (!empty($statuses)) {
                $query->where(['Invoices.workflow_status IN' => $statuses]);
            }
        }
        if (isset($q['is_draft']) && $q['is_draft'] !== '') {
            $wantDraft = in_array(strtolower((string)$q['is_draft']), ['1','true','yes','t'], true);
            $query->where($wantDraft
                ? ['Invoices.workflow_status' => 'draft']
                : ['Invoices.workflow_status !=' => 'draft']);
        }
        if (!empty($q['series'])) {
            $seriesId = $q['series'];
            $isUuid = (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $seriesId);
            if ($isUuid) {
                $query->where(['Invoices.invoice_series_id' => $seriesId]);
            } else {
                $seriesEntity = $this->fetchTable('InvoiceSeries')->find()
                    ->select(['id'])
                    ->where(['company_id' => $companyId, 'name' => $seriesId])
                    ->first();
                if ($seriesEntity) {
                    $query->where(['Invoices.invoice_series_id' => $seriesEntity->id]);
                }
            }
        }
        if (!empty($q['search'])) {
            $like = '%' . $q['search'] . '%';
            $query->where(function ($exp) use ($like) {
                return $exp->or([
                    'Invoices.fullnumber LIKE' => $like,
                    'Invoices.description LIKE' => $like,
                ]);
            });
        }

        // ── Pagination ────────────────────────────────────────────────────────
        $page     = max(1, (int)($q['page'] ?? 1));
        $perPage  = min(100, max(1, (int)($q['per_page'] ?? 25)));
        $total    = $query->count();
        $invoices = $query->limit($perPage)->offset(($page - 1) * $perPage)->all();

        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                'id'              => $inv->id,
                'fullnumber'      => $inv->fullnumber,
                'date'            => $inv->date?->format('Y-m-d'),
                'sold_date'       => $inv->sold_date?->format('Y-m-d'),
                'type'            => $inv->type,
                'workflow_status' => $inv->workflow_status,
                'is_draft'        => ((string)$inv->workflow_status === 'draft'),
                'total'           => (float)$inv->total,
                'netto'           => (float)$inv->netto,
                'tax'             => (float)$inv->tax,
                'currency'        => $inv->currency,
                'paymentmethod'   => $inv->paymentmethod,
                'paymentdate'     => $inv->paymentdate?->format('Y-m-d'),
                'paymentstate'    => $inv->paymentstate,
                'alreadypaid'     => (float)$inv->alreadypaid,
                'remaining'       => (float)$inv->remaining,
                'description'     => $inv->description,
                'ksef_number'     => $inv->ksef_number,
                'ksef_status'     => $inv->ksef_status,
                'buyer'           => $inv->invoice_contractor ? [
                    'name' => $inv->invoice_contractor->name,
                    'nip'  => $inv->invoice_contractor->nip,
                    'city' => $inv->invoice_contractor->city,
                ] : null,
                'created'      => $inv->created?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->jsonOk(200, [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int)ceil($total / $perPage),
            'invoices' => $rows,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices/by-seller-nip
    // Master-token only. Zwraca faktury wystawione przez firmę o danym NIP-ie,
    // z filtrem na rok/miesiąc (sortowanie po Invoices.date DESC).
    // Używane przez portal.partnersc.com → "Dokumenty sprzedażowe".
    // -------------------------------------------------------------------------

    public function bySellerNip(): Response
    {
        $this->request->allowMethod(['get']);

        // Master-token check
        $header = $this->request->getHeaderLine('Authorization');
        if (!str_starts_with($header, 'Bearer ') || substr($header, 7) !== self::MASTER_TOKEN) {
            return $this->jsonError(401, 'Master token wymagany dla tego endpointu.');
        }

        $q = $this->request->getQueryParams();

        // ── NIP firmy sprzedawcy ────────────────────────────────────────────
        $nipDigits = preg_replace('/\D+/', '', (string)($q['nip'] ?? ''));
        if ($nipDigits === '') {
            return $this->jsonError(422, 'Parametr "nip" jest wymagany.');
        }

        // Wyszukaj firmę po NIP-ie (akceptuj różne formaty: 1234567890, PL1234567890, 123-456-78-90)
        $nipDashed = (strlen($nipDigits) === 10)
            ? substr($nipDigits, 0, 3) . '-' . substr($nipDigits, 3, 3) . '-' . substr($nipDigits, 6, 2) . '-' . substr($nipDigits, 8, 2)
            : null;
        $nipVariants = array_values(array_unique(array_filter([
            $nipDigits,
            'PL' . $nipDigits,
            $nipDashed,
        ])));

        $company = $this->fetchTable('Companies')->find()
            ->select(['id', 'name', 'nip'])
            ->where(['nip IN' => $nipVariants])
            ->first();

        if (!$company) {
            return $this->jsonError(404, 'Nie znaleziono firmy o NIP-ie: ' . $nipDigits);
        }

        // ── Walidacja okresu (rok/miesiąc) ─────────────────────────────────
        $rok     = (int)($q['rok'] ?? $q['year'] ?? date('Y'));
        $miesiac = (int)($q['miesiac'] ?? $q['month'] ?? (int)date('n'));
        if ($rok < 2010 || $rok > 2100)   $rok = (int)date('Y');
        if ($miesiac < 1 || $miesiac > 12) $miesiac = (int)date('n');

        $fromDate = sprintf('%04d-%02d-01', $rok, $miesiac);
        $toDate   = (new \DateTime($fromDate))->modify('last day of this month')->format('Y-m-d');

        // ── Pobierz faktury ────────────────────────────────────────────────
        $Invoices = $this->fetchTable('Invoices');
        $invoices = $Invoices->find()
            ->select([
                'Invoices.id', 'Invoices.fullnumber', 'Invoices.date', 'Invoices.sold_date',
                'Invoices.type', 'Invoices.total', 'Invoices.netto', 'Invoices.tax',
                'Invoices.currency', 'Invoices.paymentmethod', 'Invoices.paymentdate',
                'Invoices.paymentstate', 'Invoices.alreadypaid', 'Invoices.remaining',
                'Invoices.description', 'Invoices.ksef_number', 'Invoices.ksef_status',
                'Invoices.workflow_status', 'Invoices.created',
            ])
            ->contain([
                'InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'name', 'nip', 'city']),
            ])
            ->where([
                'Invoices.company_id' => $company->id,
                'Invoices.date >=' => $fromDate,
                'Invoices.date <=' => $toDate,
            ])
            ->orderBy(['Invoices.date' => 'DESC', 'Invoices.created' => 'DESC'])
            ->all();

        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                'id'              => $inv->id,
                'fullnumber'      => $inv->fullnumber,
                'date'            => $inv->date?->format('Y-m-d'),
                'sold_date'       => $inv->sold_date?->format('Y-m-d'),
                'type'            => $inv->type,
                'workflow_status' => $inv->workflow_status,
                'is_draft'        => ((string)$inv->workflow_status === 'draft'),
                'total'           => (float)$inv->total,
                'netto'           => (float)$inv->netto,
                'tax'             => (float)$inv->tax,
                'currency'        => $inv->currency,
                'paymentmethod'   => $inv->paymentmethod,
                'paymentdate'     => $inv->paymentdate?->format('Y-m-d'),
                'paymentstate'    => $inv->paymentstate,
                'alreadypaid'     => (float)$inv->alreadypaid,
                'remaining'       => (float)$inv->remaining,
                'description'     => $inv->description,
                'ksef_number'     => $inv->ksef_number,
                'ksef_status'     => $inv->ksef_status,
                'buyer' => $inv->invoice_contractor ? [
                    'name' => $inv->invoice_contractor->name,
                    'nip'  => $inv->invoice_contractor->nip,
                    'city' => $inv->invoice_contractor->city,
                ] : null,
                'created' => $inv->created?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->jsonOk(200, [
            'company'  => ['id' => $company->id, 'name' => $company->name, 'nip' => $company->nip],
            'rok'      => $rok,
            'miesiac'  => $miesiac,
            'count'    => count($rows),
            'invoices' => $rows,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices/jpk-v7m
    // Master-token only. Generuje plik JPK_V7M (XML wg MF) dla firmy o danym NIP-ie.
    // Zawiera tylko sprzedaż (Ewidencja Sprzedaży + zerowa sekcja zakupów + uproszczona Deklaracja).
    // -------------------------------------------------------------------------

    public function jpkV7m(): Response
    {
        $this->request->allowMethod(['get']);

        // Master-token check
        $header = $this->request->getHeaderLine('Authorization');
        if (!str_starts_with($header, 'Bearer ') || substr($header, 7) !== self::MASTER_TOKEN) {
            return $this->jsonError(401, 'Master token wymagany dla tego endpointu.');
        }

        $q = $this->request->getQueryParams();

        // NIP sprzedawcy
        $nipDigits = preg_replace('/\D+/', '', (string)($q['nip'] ?? ''));
        if ($nipDigits === '') {
            return $this->jsonError(422, 'Parametr "nip" jest wymagany.');
        }

        $nipDashed = (strlen($nipDigits) === 10)
            ? substr($nipDigits, 0, 3) . '-' . substr($nipDigits, 3, 3) . '-' . substr($nipDigits, 6, 2) . '-' . substr($nipDigits, 8, 2)
            : null;
        $nipVariants = array_values(array_unique(array_filter([
            $nipDigits, 'PL' . $nipDigits, $nipDashed,
        ])));

        $company = $this->fetchTable('Companies')->find()
            ->where(['nip IN' => $nipVariants])
            ->first();

        if (!$company) {
            return $this->jsonError(404, 'Nie znaleziono firmy o NIP-ie: ' . $nipDigits);
        }

        // Okres miesięczny (V7M)
        $rok     = (int)($q['rok']     ?? date('Y'));
        $miesiac = (int)($q['miesiac'] ?? (int)date('n'));
        if ($rok < 2010 || $rok > 2100)    $rok = (int)date('Y');
        if ($miesiac < 1 || $miesiac > 12) $miesiac = (int)date('n');

        $dateFrom = sprintf('%04d-%02d-01', $rok, $miesiac);
        $dateTo   = (new \DateTime($dateFrom))->modify('last day of this month')->format('Y-m-d');

        // Default '1471' (US Warszawa-Mokotów) — walidatorowi wystarczy, ale do realnej wysyłki user MUSI podać własny.
        $kodUrzedu   = trim((string)($q['kod_urzedu'] ?? ''));
        if ($kodUrzedu === '' || !preg_match('/^\d{4}$/', $kodUrzedu)) {
            $kodUrzedu = '1471';
        }
        $celRaw      = (string)($q['cel'] ?? '1');
        $celZlozenia = in_array($celRaw, ['1','2'], true) ? $celRaw : '1';
        $email       = trim((string)($q['email']   ?? ''));
        $telefon     = trim((string)($q['telefon'] ?? ''));

        // Pobierz faktury z danymi do JPK (tylko wystawione, nie szkice)
        // Dla korekt — ładuj parent z jego pozycjami, żeby policzyć delty
        $Invoices = $this->fetchTable('Invoices');
        $invoices = $Invoices->find()
            ->contain([
                'InvoiceContractors',
                'InvoiceContents' => ['Vats'],
                'ParentInvoices' => [
                    'InvoiceContents' => ['Vats'],
                ],
            ])
            ->where([
                'Invoices.company_id' => $company->id,
                'Invoices.date >='    => $dateFrom,
                'Invoices.date <='    => $dateTo,
                'Invoices.workflow_status !=' => 'draft',
            ])
            ->orderBy(['Invoices.date' => 'ASC', 'Invoices.created' => 'ASC'])
            ->toArray();

        // Dla faktur końcowych — podpnij rodzeństwo (poprzednie zaliczki tej samej proformy)
        $finalParentIds = [];
        foreach ($invoices as $inv) {
            if (strtolower((string)$inv->type) === 'final' && !empty($inv->parent_id)) {
                $finalParentIds[] = $inv->parent_id;
            }
        }
        if (!empty($finalParentIds)) {
            $advances = $Invoices->find()
                ->contain(['InvoiceContents' => ['Vats']])
                ->where([
                    'Invoices.company_id'  => $company->id,
                    'Invoices.parent_id IN' => array_values(array_unique($finalParentIds)),
                    'Invoices.type'        => 'advance',
                    'Invoices.workflow_status !=' => 'draft',
                ])
                ->all();
            $advByParent = [];
            foreach ($advances as $adv) {
                $advByParent[(string)$adv->parent_id][] = $adv;
            }
            foreach ($invoices as $inv) {
                if (strtolower((string)$inv->type) === 'final' && !empty($advByParent[(string)$inv->parent_id])) {
                    $inv->set('sibling_advances', $advByParent[(string)$inv->parent_id]);
                }
            }
        }

        $generator = new JpkV7mGenerator();
        $xml = $generator->generate(
            $company,
            $invoices,
            $rok,
            $miesiac,
            $kodUrzedu,
            $celZlozenia,
            $email,
            $telefon
        );

        // Opcjonalna walidacja XSD — ?validate=1 zwraca JSON z błędami zamiast XML
        $shouldValidate = !empty($q['validate']) && in_array((string)$q['validate'], ['1','true','yes'], true);
        if ($shouldValidate) {
            $result = (new JpkV7mValidator())->validate($xml);
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success'    => $result['valid'],
                    'valid'      => $result['valid'],
                    'xsd_exists' => $result['xsd_exists'],
                    'errors'     => $result['errors'],
                    'xml_length' => strlen($xml),
                    'invoices_count' => is_array($invoices) ? count($invoices) : $invoices->count(),
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Tryb strict — gdy ?strict=1 i walidacja XSD nie przechodzi → 422 z błędami zamiast XML
        $strict = !empty($q['strict']) && in_array((string)$q['strict'], ['1','true','yes'], true);
        if ($strict) {
            $result = (new JpkV7mValidator())->validate($xml);
            if (!$result['valid']) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success'    => false,
                        'error'      => 'JPK_V7M nie przeszedł walidacji XSD.',
                        'xsd_exists' => $result['xsd_exists'],
                        'errors'     => $result['errors'],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        }

        $filename = 'JPK_V7M_' . preg_replace('/[^a-z0-9]/i', '', (string)$company->nip)
                  . '_' . sprintf('%04d-%02d', $rok, $miesiac)
                  . '.xml';

        return $this->response
            ->withType('application/xml')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($xml);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices/{id}/status
    // -------------------------------------------------------------------------

    public function status(string $id): Response
    {
        $this->request->allowMethod(['get']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;

        try {
            $inv = $this->fetchTable('Invoices')
                ->find()
                ->select([
                    'id', 'fullnumber', 'workflow_status',
                    'ksef_number', 'ksef_status', 'modified',
                ])
                ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
                ->firstOrFail();
        } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        return $this->jsonOk(200, [
            'id'              => $inv->id,
            'fullnumber'      => $inv->fullnumber,
            'workflow_status' => $inv->workflow_status,
            'ksef_number'     => $inv->ksef_number,
            'ksef_status'     => $inv->ksef_status,
            'modified'        => $inv->modified?->format('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices/{id}
    // -------------------------------------------------------------------------

    public function get(string $id): Response
    {
        $this->request->allowMethod(['get']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $Invoices  = $this->fetchTable('Invoices');

        try {
            $inv = $Invoices->find()
                ->contain([
                    'InvoiceContractors',
                    'InvoiceCompanyDetails',
                    'InvoiceContents' => ['Vats'],
                    'InvoiceVatContents' => ['Vats'],
                    'InvoicePayments',
                    'InvoiceSeries' => fn($q) => $q->select(['id', 'name']),
                ])
                ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
                ->firstOrFail();
        } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        $items = [];
        foreach ($inv->invoice_contents ?? [] as $c) {
            $items[] = [
                'id'                => $c->id,
                'name'              => $c->name,
                'description'       => $c->product_desc,
                'quantity'          => (float)$c->quantity,
                'unit'              => $c->unit,
                'price'             => (float)$c->price,
                'gross_unit_price'  => (float)$c->gross_unit_price,
                'discount_percent'  => (float)$c->discount_percent,
                'netto'             => (float)$c->netto,
                'vat_amount'        => (float)$c->vat_amount,
                'brutto'            => (float)$c->brutto,
                'vat'               => $c->vat->name ?? null,
                'gtu_code'          => $c->gtu_code,
                'pkwiu'             => $c->pkwiu,
                'gtin'              => $c->gtin,
                'cn_code'           => $c->cn_code,
            ];
        }

        $vatSummary = [];
        foreach ($inv->invoice_vat_contents ?? [] as $v) {
            $vatSummary[] = [
                'vat'    => $v->vat->name ?? null,
                'netto'  => (float)$v->netto,
                'tax'    => (float)$v->tax,
                'brutto' => (float)$v->brutto,
            ];
        }

        $payments = [];
        foreach ($inv->invoice_payments ?? [] as $p) {
            $payments[] = [
                'id'             => $p->id,
                'payment_date'   => $p->payment_date?->format('Y-m-d'),
                'amount'         => (float)$p->amount,
                'payment_method' => $p->payment_method,
                'description'    => $p->description,
                'created'        => $p->created?->format('Y-m-d H:i:s'),
            ];
        }

        $buyer    = $inv->invoice_contractor;
        $seller   = $inv->invoice_company_detail;

        return $this->jsonOk(200, [
            'id'            => $inv->id,
            'fullnumber'    => $inv->fullnumber,
            'series'        => $inv->invoice_series?->name,
            'type'          => $inv->type,
            'date'          => $inv->date?->format('Y-m-d'),
            'sold_date'     => $inv->sold_date?->format('Y-m-d'),
            'currency'      => $inv->currency,
            'lang'          => $inv->lang,
            'description'   => $inv->description,
            'issuer'        => $inv->issuer,
            'place_of_issue'=> $inv->place_of_issue,
            'footer_text'   => $inv->footer_text,
            'is_split_payment' => (bool)$inv->is_split_payment,
            'paymentmethod' => $inv->paymentmethod,
            'paymentdate'   => $inv->paymentdate?->format('Y-m-d'),
            'paymentstate'  => $inv->paymentstate,
            'total'         => (float)$inv->total,
            'netto'         => (float)$inv->netto,
            'tax'           => (float)$inv->tax,
            'alreadypaid'   => (float)$inv->alreadypaid,
            'remaining'     => (float)$inv->remaining,
            'ksef_number'   => $inv->ksef_number,
            'ksef_status'   => $inv->ksef_status,
            'buyer' => $buyer ? [
                'name'    => $buyer->name,
                'nip'     => $buyer->nip,
                'street'  => $buyer->street,
                'city'    => $buyer->city,
                'zip'     => $buyer->zip,
                'country' => $buyer->country,
                'email'   => $buyer->email,
                'phone'   => $buyer->phone,
            ] : null,
            'seller' => $seller ? [
                'name'         => $seller->name,
                'nip'          => $seller->nip,
                'street'       => $seller->street,
                'city'         => $seller->city,
                'zip'          => $seller->zip,
                'country'      => $seller->country,
                'bank_account' => $seller->bank_account,
                'bank_name'    => $seller->bank_name,
            ] : null,
            'items'       => $items,
            'vat_summary' => $vatSummary,
            'payments'    => $payments,
            'created'     => $inv->created?->format('Y-m-d H:i:s'),
            'modified'    => $inv->modified?->format('Y-m-d H:i:s'),
            'view_url'    => '/invoices/view/' . $inv->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/invoices/{id}/issue  — szkic → wystawiona
    // -------------------------------------------------------------------------

    public function issue(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $Invoices  = $this->fetchTable('Invoices');

        try {
            $inv = $Invoices->find()
                ->select(['id', 'fullnumber', 'workflow_status', 'is_api', 'company_id'])
                ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
                ->firstOrFail();
        } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        if (!$inv->is_api) {
            return $this->jsonError(403, 'Akcja dostępna tylko dla faktur utworzonych przez API.');
        }

        if ((string)($inv->workflow_status ?? '') !== 'draft') {
            return $this->jsonError(422, 'Faktura nie jest szkicem (workflow_status: ' . ($inv->workflow_status ?? 'brak') . ').');
        }

        $inv->set('workflow_status', 'issued');
        $Invoices->save($inv);

        return $this->jsonOk(200, [
            'id'              => $inv->id,
            'fullnumber'      => $inv->fullnumber,
            'workflow_status' => 'issued',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/invoices/{id}/send-ksef  — wystawiona → wysyłka do KSeF
    // -------------------------------------------------------------------------

    public function sendKsef(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $Invoices  = $this->fetchTable('Invoices');

        try {
            $inv = $Invoices->find()
                ->select(['id', 'fullnumber', 'workflow_status', 'ksef_status', 'ksef_number', 'is_api', 'company_id', 'modified'])
                ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
                ->firstOrFail();
        } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        if (!$inv->is_api) {
            return $this->jsonError(403, 'Akcja dostępna tylko dla faktur utworzonych przez API.');
        }

        $status = (string)($inv->workflow_status ?? '');
        if ($status === 'sent') {
            return $this->jsonError(422, 'Faktura już została wysłana do KSeF (status: sent).');
        }
        // 'sending' starsze niż 5 min = zawieszona wysyłka (timeout / fatal PHP w trakcie sesji KSeF).
        // Przepuszczamy do sendInvoiceToKsefCore, który przejmuje nieaktualny lock (ten sam próg 5 min)
        // i ponawia wysyłkę; ewentualny duplikat w KSeF kończy się statusem error + „Uzupełnij nr KSeF".
        // Świeże 'sending' nadal blokujemy, żeby nie dublować trwającej wysyłki.
        if ($status === 'sending') {
            $modifiedTs = ($inv->modified instanceof \DateTimeInterface) ? $inv->modified->getTimestamp() : 0;
            if ($modifiedTs > time() - 300) {
                return $this->jsonError(422, 'Faktura jest w trakcie wysyłki do KSeF (status: sending).');
            }
        }
        if ($status === 'draft') {
            return $this->jsonError(422, 'Faktura jest szkicem — najpierw wywołaj /issue.');
        }

        // Wywołaj sendInvoiceToKsefCore bezpośrednio przez instancję głównego kontrolera
        $mainController = new \App\Controller\InvoicesController(
            $this->request
        );

        $invFull = $Invoices->get($id, contain: ['InvoiceContractors', 'InvoiceCompanyDetails', 'InvoiceContents' => ['Vats'], 'Companies']);

        $send = $mainController->sendInvoiceToKsefCore($invFull, $companyId);

        // Odczytaj aktualny status po wysyłce
        $inv = $Invoices->find()
            ->select(['id', 'fullnumber', 'workflow_status', 'ksef_status', 'ksef_number'])
            ->where(['Invoices.id' => $id])
            ->first();

        $sent = $send['success'] ?? false;

        return $this->jsonOk($sent ? 200 : 422, [
            'id'              => $id,
            'fullnumber'      => $inv->fullnumber ?? null,
            'workflow_status' => $inv->workflow_status ?? null,
            'ksef_status'     => $inv->ksef_status ?? null,
            'ksef_number'     => $inv->ksef_number ?? null,
            'success'         => $sent,
            'error'           => $sent ? null : ($send['error'] ?? 'Błąd wysyłki do KSeF'),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/invoices/{id}/payments
    // -------------------------------------------------------------------------

    public function addPayment(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $Invoices  = $this->fetchTable('Invoices');

        // Verify invoice exists and belongs to this company
        $invoice = $Invoices->find()
            ->select(['id', 'total', 'alreadypaid', 'remaining', 'paymentstate'])
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if (!$invoice) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        // Parse body
        $body = $this->request->getData();
        if (empty($body)) {
            $raw = (string)$this->request->getBody();
            if ($raw !== '') {
                $body = json_decode($raw, true) ?? [];
            }
        }

        // Validate required fields
        if (empty($body['amount']) || !is_numeric($body['amount']) || (float)$body['amount'] <= 0) {
            return $this->jsonError(422, 'Pole "amount" jest wymagane i musi być większe od 0.');
        }
        if (empty($body['payment_date'])) {
            return $this->jsonError(422, 'Pole "payment_date" jest wymagane (format: YYYY-MM-DD).');
        }

        $Payments = $this->fetchTable('InvoicePayments');
        $payment  = $Payments->newEmptyEntity();
        $payment  = $Payments->patchEntity($payment, [
            'invoice_id'     => $id,
            'payment_date'   => (string)$body['payment_date'],
            'amount'         => (float)$body['amount'],
            'payment_method' => (string)($body['payment_method'] ?? 'transfer'),
            'description'    => (string)($body['description'] ?? ''),
        ]);

        if (!$Payments->save($payment)) {
            return $this->jsonError(422, 'Błąd zapisu rozliczenia: ' . json_encode($payment->getErrors()));
        }

        // Reload invoice totals (recalculated by afterSave hook)
        $invoice = $Invoices->get($id, ['fields' => ['id', 'total', 'alreadypaid', 'remaining', 'paymentstate']]);

        return $this->jsonOk(201, [
            'payment' => [
                'id'             => $payment->id,
                'invoice_id'     => $id,
                'payment_date'   => $payment->payment_date->format('Y-m-d'),
                'amount'         => (float)$payment->amount,
                'payment_method' => $payment->payment_method,
                'description'    => $payment->description,
            ],
            'invoice' => [
                'id'           => $invoice->id,
                'total'        => (float)$invoice->total,
                'alreadypaid'  => (float)$invoice->alreadypaid,
                'remaining'    => (float)$invoice->remaining,
                'paymentstate' => $invoice->paymentstate,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/invoices
    // -------------------------------------------------------------------------

    public function create(): Response
    {
        $this->request->allowMethod(['post']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $body      = $this->request->getData();

        // Also accept JSON body
        if (empty($body)) {
            $raw = (string)$this->request->getBody();
            if ($raw !== '') {
                $body = json_decode($raw, true) ?? [];
            }
        }

        try {
            $result = $this->saveVatInvoice($companyId, $body);
            return $this->jsonOk(201, $result);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError(422, $e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->jsonError(500, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Core save logic (VAT invoice)
    // -------------------------------------------------------------------------

    private function saveVatInvoice(string $companyId, array $data): array
    {
        $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;

        // ── 1. Validate required fields ─────────────────────────────────────
        $requiredFields = ['buyer', 'items'];
        foreach ($requiredFields as $f) {
            if (empty($data[$f])) {
                throw new \InvalidArgumentException("Pole \"{$f}\" jest wymagane.");
            }
        }
        if (empty($data['buyer']['name'])) {
            throw new \InvalidArgumentException('Pole "buyer.name" jest wymagane.');
        }
        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new \InvalidArgumentException('Lista pozycji "items" nie może być pusta.');
        }

        // ── 2. Resolve invoice series ────────────────────────────────────────
        $InvoiceSeriesTable = $this->fetchTable('InvoiceSeries');

        $seriesUuid = trim((string)($data['series_id'] ?? ''));
        $seriesName = trim((string)($data['series'] ?? ''));

        $series = null;
        if ($seriesUuid !== '') {
            $series = $InvoiceSeriesTable->find()
                ->contain(['InvoiceSeriesPeriods'])
                ->where(['InvoiceSeries.id' => $seriesUuid, 'InvoiceSeries.company_id' => $companyId])
                ->first();
        }
        if (!$series && $seriesName !== '') {
            $series = $InvoiceSeriesTable->find()
                ->contain(['InvoiceSeriesPeriods'])
                ->where(['InvoiceSeries.company_id' => $companyId, 'InvoiceSeries.name' => $seriesName])
                ->first();
        }
        if (!$series) {
            // Fall back to default series for type=vat
            $series = $InvoiceSeriesTable->find()
                ->contain(['InvoiceSeriesPeriods'])
                ->where(['InvoiceSeries.company_id' => $companyId, 'InvoiceSeries.is_default' => true])
                ->first();
        }
        if (!$series) {
            throw new \InvalidArgumentException('Nie znaleziono serii numeracji. Podaj "series_id" lub "series".');
        }

        // ── 3. Resolve VAT rates ─────────────────────────────────────────────
        $Vats = $this->fetchTable('Vats');
        $vatRows = $Vats->find()->select(['id', 'name', 'rate'])->where(['deleted IS' => false])->all();
        $vatRatesMap  = $vatRows->combine('id', 'rate')->toArray();
        $vatByName    = [];       // 'name' (e.g. "23%") → id
        $vatByRate    = [];       // '23.00' → id (first match)
        foreach ($vatRows as $v) {
            $vatByName[strtolower(trim((string)$v->name))] = $v->id;
            $rateKey = number_format((float)$v->rate, 2);
            if (!isset($vatByRate[$rateKey])) {
                $vatByRate[$rateKey] = $v->id;
            }
        }

        // ── 4. Process line items ────────────────────────────────────────────
        $contents   = [];
        $vatBuckets = [];
        $sumNet = $sumTax = $sumGross = 0.0;

        foreach ($data['items'] as $idx => $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException("Pozycja #" . ($idx + 1) . ": pole \"name\" jest wymagane.");
            }

            // Resolve vat_code_id from various inputs
            $vatCodeId = $row['vat_code_id'] ?? null;
            if (!$vatCodeId && isset($row['vat'])) {
                $vatKey = strtolower(trim((string)$row['vat']));
                // Try name match (e.g. "23%", "zw", "np")
                $vatCodeId = $vatByName[$vatKey]
                    ?? $vatByName[$vatKey . '%']
                    ?? $vatByRate[number_format((float)$row['vat'], 2)]
                    ?? null;
            }
            if (!$vatCodeId || !isset($vatRatesMap[$vatCodeId])) {
                throw new \InvalidArgumentException(
                    "Pozycja #" . ($idx + 1) . ": nieprawidłowa stawka VAT \"{$row['vat']}\". "
                    . "Podaj \"vat_code_id\" lub \"vat\" (np. \"23%\", \"8%\", \"zw\")."
                );
            }

            $rate = (float)$vatRatesMap[$vatCodeId];
            $qty  = $num($row['quantity'] ?? 1);
            $disc = $num($row['discount_percent'] ?? 0);

            // Accept either net or gross unit price
            if (isset($row['price_gross'])) {
                $priceGross = $num($row['price_gross']);
                $price = round($priceGross / (1 + $rate / 100), 6);
            } else {
                $price = $num($row['price'] ?? 0);
            }

            $unitAfterDisc = $price * (1 - $disc / 100);
            $netto  = round($qty * $unitAfterDisc, 2);
            $tax    = round($netto * ($rate / 100), 2);
            $brutto = round($netto + $tax, 2);

            $sumNet   += $netto;
            $sumTax   += $tax;
            $sumGross += $brutto;

            $contents[] = [
                'vat_code_id'       => $vatCodeId,
                'name'              => $name,
                'product_desc'      => (string)($row['description'] ?? $row['product_desc'] ?? ''),
                'quantity'          => $qty,
                'unit'              => (string)($row['unit'] ?? 'szt.'),
                'price'             => $price,
                'discount_percent'  => $disc,
                'discount_amount'   => $disc > 0 ? round($qty * $price * ($disc / 100), 2) : null,
                'netto'             => $netto,
                'brutto'            => $brutto,
                'gtu_code'          => (string)($row['gtu_code'] ?? ''),
                'gtin'              => (string)($row['gtin'] ?? ''),
                'cn_code'           => (string)($row['cn_code'] ?? ''),
                'pkob'              => (string)($row['pkob'] ?? ''),
                'pkwiu'             => (string)($row['pkwiu'] ?? ''),
                'is_attachment15'   => !empty($row['is_attachment15']) ? 1 : 0,
                'excise_amount'     => isset($row['excise_amount']) ? $num($row['excise_amount']) : null,
                'procedure_marking' => (string)($row['procedure_marking'] ?? ''),
                'uu_id'             => Text::uuid(),
                'vat_amount'        => round($netto * ($rate / 100), 2),
                'gross_unit_price'  => round($price * (1 + $rate / 100), 2),
                'line_date'         => !empty($row['line_date']) ? $row['line_date'] : null,
            ];

            $bucketKey = $vatCodeId;
            if (!isset($vatBuckets[$bucketKey])) {
                $vatBuckets[$bucketKey] = ['vat_code_id' => $vatCodeId, 'netto' => 0.0, 'tax' => 0.0, 'brutto' => 0.0];
            }
            $vatBuckets[$bucketKey]['netto']  += $netto;
            $vatBuckets[$bucketKey]['tax']    += $tax;
            $vatBuckets[$bucketKey]['brutto'] += $brutto;
        }

        // ── 5. Resolve payment state ─────────────────────────────────────────
        $alreadyPaid = $num($data['already_paid'] ?? 0);
        $total       = round($sumGross, 2);
        $remaining   = round($total - $alreadyPaid, 2);
        $paymentState = 'unpaid';
        if ($alreadyPaid >= $total) {
            $paymentState = 'paid';
        } elseif ($alreadyPaid > 0) {
            $paymentState = 'partial';
        }

        // ── 6. Generate invoice number ───────────────────────────────────────
        $issueDate  = !empty($data['date']) ? (string)$data['date'] : date('Y-m-d');
        $dateObject = new \DateTime($issueDate);
        $year       = $dateObject->format('Y');
        $month      = $dateObject->format('m');

        $Invoices = $this->fetchTable('Invoices');
        $whereNum = ['company_id' => $companyId, 'invoice_series_id' => $series->id];

        $periodType = 'continuous';
        if ($series->invoice_series_period) {
            $periodName = strtolower($series->invoice_series_period->name ?? '');
            if (str_contains($periodName, 'miesięcz') || str_contains($periodName, 'monthly')) {
                $periodType = 'monthly';
                $whereNum['year']  = (int)$year;
                $whereNum['month'] = (int)$month;
            } elseif (str_contains($periodName, 'roczn') || str_contains($periodName, 'yearly')) {
                $periodType = 'yearly';
                $whereNum['year'] = (int)$year;
            }
        }

        $lastInvoice = $Invoices->find()
            ->select(['number', 'fullnumber'])
            ->where($whereNum)
            ->orderBy(['number' => 'DESC'])
            ->first();

        $lastNumber = 0;
        if ($lastInvoice) {
            $lastNumber = (int)($lastInvoice->number ?? 0);
            if ($lastNumber <= 0 && $lastInvoice->fullnumber) {
                $lastNumber = $this->numbering->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
            }
        }
        $startingNumber = max(1, (int)($series->starting_number ?? 1));
        $nextNumber = max($startingNumber, $lastNumber + 1);
        $template   = $series->series_template ?: '[numer]';
        $fullnumber = $this->numbering->formatPattern($template, $nextNumber, $issueDate);

        // ── Override fullnumber (np. import z zewnętrznego systemu) ──────────
        if (!empty($data['fullnumber_override'])) {
            $fullnumber = (string)$data['fullnumber_override'];
            // wyciągnij numer z override żeby nie zaburzać sekwencji
            $extractedNumber = $this->numbering->extractNumberFromFullnumber($fullnumber);
            if ($extractedNumber > 0) {
                $nextNumber = $extractedNumber;
            }
        }

        // ── 7. Build invoice data ────────────────────────────────────────────
        $payMethod = (string)($data['payment_method'] ?? $data['paymentmethod'] ?? 'transfer');
        $payDate   = !empty($data['payment_date']) ? (string)$data['payment_date']
            : (!empty($data['paymentdate']) ? (string)$data['paymentdate'] : null);

        $invoiceData = [
            'hash'               => substr(md5(uniqid()), 0, 32),
            'company_id'         => $companyId,
            'invoice_series_id'  => $series->id,
            'type'               => 'vat',
            'simplified_invoice' => false,
            'paymentmethod'      => $payMethod,
            'paymentdate'        => $payDate,
            'paymentstate'       => $paymentState,
            'date'               => $issueDate,
            'total'              => $total,
            'netto'              => round($sumNet, 2),
            'tax'                => round($sumTax, 2),
            'alreadypaid'        => $alreadyPaid,
            'remaining'          => $remaining,
            'fullnumber'         => $fullnumber,
            'number'             => $nextNumber,
            'day'                => (int)$dateObject->format('d'),
            'month'              => (int)$dateObject->format('m'),
            'year'               => (int)$dateObject->format('Y'),
            'day_year'           => (int)$dateObject->format('z') + 1,
            'currency'           => 'PLN',
            'currency_exchange'  => 1.0,
            'currency_date'      => $issueDate,
            'description'        => (string)($data['description'] ?? ''),
            'sold_date'          => !empty($data['sold_date']) ? (string)$data['sold_date'] : $issueDate,
            'lang'               => (string)($data['lang'] ?? 'pl'),
            'is_print'           => false,
            'is_sent'            => false,
            'is_api'             => true,
            'workflow_status'    => !empty($data['is_draft']) ? 'draft' : 'issued',
            'issuer'             => (string)($data['issuer'] ?? ''),
            'place_of_issue'     => (string)($data['place_of_issue'] ?? ''),
            'footer_text'        => (string)($data['footer_text'] ?? ''),
            'is_split_payment'   => !empty($data['is_split_payment']) ? 1 : 0,
        ];

        // ── 8. Save in transaction ───────────────────────────────────────────
        $conn = $Invoices->getConnection();
        $conn->begin();
        try {
            $invoiceEntity = $Invoices->newEmptyEntity();
            $invoiceEntity = $Invoices->patchEntity($invoiceEntity, $invoiceData);
            if (!$Invoices->save($invoiceEntity)) {
                throw new \RuntimeException('Błąd zapisu faktury: ' . json_encode($invoiceEntity->getErrors()));
            }
            $invoiceId = (string)$invoiceEntity->id;

            // Company snapshot
            $CompaniesTable = $this->fetchTable('Companies');
            $company = $CompaniesTable->find()
                ->where(['id' => $companyId])
                ->contain(['CompanyRegisters'])
                ->first();
            if ($company) {
                $this->saveCompanySnapshot($invoiceId, $company, $companyId, trim((string)($data['bank_account_id'] ?? '')));
            }

            // Buyer snapshot
            $buyer = $data['buyer'];
            $this->saveBuyerSnapshot($invoiceId, $buyer);

            // Line items
            $InvoiceContentsTable = $this->fetchTable('InvoiceContents');
            foreach ($contents as $contentData) {
                $contentData['invoice_id'] = $invoiceId;
                $contentEntity = $InvoiceContentsTable->newEmptyEntity();
                $contentEntity = $InvoiceContentsTable->patchEntity($contentEntity, $contentData);
                if (!$InvoiceContentsTable->save($contentEntity)) {
                    throw new \RuntimeException('Błąd zapisu pozycji: ' . json_encode($contentEntity->getErrors()));
                }
            }

            // VAT summary rows
            $InvoiceVatContentsTable = $this->fetchTable('InvoiceVatContents');
            foreach ($vatBuckets as $bucket) {
                $vatRow = $InvoiceVatContentsTable->newEmptyEntity();
                $vatRow = $InvoiceVatContentsTable->patchEntity($vatRow, [
                    'invoice_id'  => $invoiceId,
                    'vat_code_id' => $bucket['vat_code_id'],
                    'netto'       => round($bucket['netto'], 2),
                    'tax'         => round($bucket['tax'], 2),
                    'brutto'      => round($bucket['brutto'], 2),
                ]);
                $InvoiceVatContentsTable->save($vatRow);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            throw new \RuntimeException($e->getMessage());
        }

        return [
            'id'          => $invoiceId,
            'fullnumber'  => $fullnumber,
            'date'        => $issueDate,
            'total'       => round($total, 2),
            'netto'       => round($sumNet, 2),
            'tax'         => round($sumTax, 2),
            'currency'    => 'PLN',
            'series'      => $series->name,
            'view_url'    => '/invoices/view/' . $invoiceId,
        ];
    }

    // -------------------------------------------------------------------------
    // Snapshot helpers (mirror handleAdd logic)
    // -------------------------------------------------------------------------

    private function saveCompanySnapshot(string $invoiceId, mixed $company, string $companyId, string $bankAccountId = ''): void
    {
        $InvoiceCompanyDetailsTable = $this->fetchTable('InvoiceCompanyDetails');

        // Bank account — use requested; fall back to default if missing OR invalid
        $snapshotBank = $snapshotBankName = '';
        try {
            $Cba = $this->fetchTable('CompanyBankAccounts');
            $cba = null;
            if ($bankAccountId !== '') {
                $cba = $Cba->find()
                    ->select(['iban', 'bank_name'])
                    ->where(['id' => $bankAccountId, 'company_id' => $companyId])
                    ->first();
            }
            // Fallback: brak ID lub nieprawidłowe/cudze ID → konto domyślne firmy
            if ($cba === null) {
                $cba = $Cba->find()
                    ->select(['iban', 'bank_name'])
                    ->where(['company_id' => $companyId, 'is_default' => 1])
                    ->first();
            }
            $snapshotBank     = (string)($cba->iban ?? '');
            $snapshotBankName = (string)($cba->bank_name ?? '');
        } catch (\Throwable) {}

        $streetLine = trim((string)($company->street ?? ''));
        $localNo    = trim((string)($company->local_number ?? ''));
        if ($localNo !== '') {
            $streetLine = rtrim($streetLine) . '/' . $localNo;
        }

        $detail = $InvoiceCompanyDetailsTable->newEmptyEntity();
        $detail = $InvoiceCompanyDetailsTable->patchEntity($detail, [
            'invoice_id'   => $invoiceId,
            'name'         => (string)($company->name ?? ''),
            'nip'          => (string)($company->nip ?? ''),
            'street'       => $streetLine,
            'city'         => (string)($company->city ?? ''),
            'zip'          => (string)($company->postal_code ?? ''),
            'country'      => (string)($company->country ?? 'Polska'),
            'bank_account' => $snapshotBank,
            'bank_name'    => $snapshotBankName,
            'email'        => (string)($company->email ?? ''),
            'phone'        => (string)($company->phone ?? ''),
            'country_code' => 'PL',
        ]);
        $InvoiceCompanyDetailsTable->save($detail);
    }

    private function saveBuyerSnapshot(string $invoiceId, array $buyer): void
    {
        if (empty($buyer['name'])) {
            return;
        }
        $InvoiceContractorsTable = $this->fetchTable('InvoiceContractors');
        $entity = $InvoiceContractorsTable->newEmptyEntity();
        $entity = $InvoiceContractorsTable->patchEntity($entity, [
            'invoice_id' => $invoiceId,
            'name'       => (string)($buyer['name'] ?? ''),
            'nip'        => (string)($buyer['nip'] ?? $buyer['tax_id'] ?? ''),
            'street'     => (string)($buyer['street'] ?? $buyer['address'] ?? ''),
            'city'       => (string)($buyer['city'] ?? ''),
            'zip'        => (string)($buyer['zip'] ?? $buyer['postal_code'] ?? ''),
            'country'    => (string)($buyer['country'] ?? 'Polska'),
            'email'      => (string)($buyer['email'] ?? ''),
            'phone'      => (string)($buyer['phone'] ?? ''),
        ]);
        $InvoiceContractorsTable->save($entity);
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    private function jsonOk(int $status, array $data): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function jsonError(int $status, string $message): Response
    {
        $this->response = $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE));
        return $this->response;
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/bank-accounts  — lista rachunków bankowych firmy
    // -------------------------------------------------------------------------

    public function bankAccounts(): Response
    {
        $this->request->allowMethod(['get']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $rows = $this->fetchTable('CompanyBankAccounts')
            ->find()
            ->select(['id', 'label', 'iban', 'bank_name', 'is_default'])
            ->where(['company_id' => $companyId])
            ->orderBy(['is_default' => 'DESC', 'label' => 'ASC'])
            ->all();

        $accounts = [];
        foreach ($rows as $a) {
            $accounts[] = [
                'id'         => $a->id,
                'label'      => $a->label ?: $a->iban,
                'iban'       => $a->iban,
                'bank_name'  => $a->bank_name,
                'is_default' => (bool)$a->is_default,
            ];
        }

        return $this->jsonOk(200, ['bank_accounts' => $accounts]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/series  — lista serii numeracji firmy
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // GET /api/v1/invoices/{id}/pdf  — pobierz PDF faktury (binary)
    // -------------------------------------------------------------------------

    public function pdf(string $id): Response
    {
        $this->request->allowMethod(['get']);

        // Master-token bypass: portal pobiera PDF dowolnej faktury (read-only)
        $header   = $this->request->getHeaderLine('Authorization');
        $isMaster = str_starts_with($header, 'Bearer ') && substr($header, 7) === self::MASTER_TOKEN;

        $companyId = null;
        if (!$isMaster) {
            $token = $this->authenticate();
            if ($token === null) {
                return $this->response;
            }
            $companyId = (string)$token->company_id;
        }

        $Invoices  = $this->fetchTable('Invoices');

        try {
            $conditions = ['Invoices.id' => $id];
            if (!$isMaster) {
                $conditions['Invoices.company_id'] = $companyId;
            }
            $invoice = $Invoices->find()
                ->contain([
                    'InvoiceContractors',
                    'InvoiceContents' => ['Vats'],
                    'Companies',
                    'InvoiceCompanyDetails',
                ])
                ->where($conditions)
                ->firstOrFail();
        } catch (\Cake\Datasource\Exception\RecordNotFoundException) {
            return $this->jsonError(404, 'Faktura nie znaleziona.');
        }

        $mainController = new \App\Controller\InvoicesController($this->request);

        $xml = '';
        try {
            $xml = $mainController->buildFa3Xml($invoice);
        } catch (\Throwable $e) {
            return $this->jsonError(500, 'Błąd generowania XML: ' . $e->getMessage());
        }

        if (trim($xml) === '') {
            return $this->jsonError(500, 'Pusty XML faktury.');
        }

        $isDraft = ((string)($invoice->workflow_status ?? '')) === 'draft';
        $apiUrl  = $isDraft
            ? (getenv('INVOICE_DRAFT_API_URL') ?: 'https://faktury24-draft.3ckstudio.pl/api/invoice')
            : (getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice');

        $seller    = $invoice->invoice_company_detail ?? null;
        $nip       = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
        $issueDate = $invoice->date ? $invoice->date->format('d-m-Y') : '';
        $invRef    = (string)($invoice->ksef_invoice_reference ?? '');
        $qrCode    = ($nip !== '' && $issueDate !== '' && $invRef !== '')
            ? ('https://ksef.mf.gov.pl/client-app/invoice/' . $nip . '/' . $issueDate . '/' . $invRef)
            : '';

        try {
            $http = new \Cake\Http\Client(['timeout' => 60]);
            $resp = $http->post($apiUrl, [
                'xml'            => $xml,
                'additionalData' => [
                    'nrKSeF'    => (string)($invoice->ksef_number ?? ''),
                    'qrCode'    => $qrCode,
                    'isPreview' => $isDraft,
                ],
            ], ['type' => 'json']);

            if ($resp->getStatusCode() !== 200) {
                return $this->jsonError(502, 'Błąd API PDF: HTTP ' . $resp->getStatusCode());
            }

            $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
            $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';

            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withStringBody((string)$resp->getBody());
        } catch (\Throwable $e) {
            return $this->jsonError(500, $e->getMessage());
        }
    }

    public function series(): Response
    {
        $this->request->allowMethod(['get']);

        $token = $this->authenticate();
        if ($token === null) {
            return $this->response;
        }

        $companyId = (string)$token->company_id;
        $rows = $this->fetchTable('InvoiceSeries')
            ->find()
            ->select(['id', 'name', 'is_default'])
            ->where(['company_id' => $companyId])
            ->orderBy(['is_default' => 'DESC', 'name' => 'ASC'])
            ->all();

        $series = [];
        foreach ($rows as $s) {
            $series[] = [
                'id'         => $s->id,
                'name'       => $s->name,
                'is_default' => (bool)$s->is_default,
            ];
        }

        return $this->jsonOk(200, ['series' => $series]);
    }
}

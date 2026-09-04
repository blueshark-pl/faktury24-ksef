<?php
declare(strict_types=1);

namespace App\Controller;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use Cake\Http\Response;
use Cake\Http\Client;
use Cake\Routing\Router;
use App\Service\Ksef\DbKsefCredentialsProvider;
use App\Service\Ksef\DbKsefTokenStorage;
use App\Service\Ksef\KsefClient;
use App\Service\Ksef\KsefSessionService;
use App\Service\Ksef\N1KsefService;
use App\Service\Ksef\CertificateStorage;
use Psr\Http\Message\UploadedFileInterface;
use Cake\Http\Exception\BadRequestException;
use App\Model\Entity\Invoice;
use App\Service\Invoice\InvoiceNumberingService;
use App\Service\Invoice\InvoiceDefaultSeriesResolver;

/**
 * Invoices Controller
 *
 * @property \App\Model\Table\InvoicesTable $Invoices
 */
class InvoicesController extends AppController
{
    private InvoiceNumberingService $numberingService;
    private InvoiceDefaultSeriesResolver $defaultSeriesResolver;

    public function initialize(): void
    {
        parent::initialize();
        $this->numberingService      = new InvoiceNumberingService();
        $this->defaultSeriesResolver = new InvoiceDefaultSeriesResolver();
    }

    private const NS_FA3            = 'http://crd.gov.pl/wzor/2025/06/25/13775/';
    private const FORM_CODE         = 'FA';
    private const FORM_CODE_SYSTEM  = 'FA (3)';
    private const FORM_SCHEMA_VER   = '1-0E';
    private const FORM_VARIANT      = '3';

    private function isKsefModeEnabled(?string $companyId): bool
    {
        if (empty($companyId)) {
            return true;
        }
        try {
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()
                ->select(['id', 'ksef_mode_enabled'])
                ->where(['id' => $companyId])
                ->first();
            if ($company === null) {
                return true;
            }

            return (bool)($company->ksef_mode_enabled ?? true);
        } catch (\Throwable) {
            // Fallback-safe: do not block by default if setting unavailable
            return true;
        }
    }

    /**
     * Czy na fakturze pokazać kod QR weryfikacji KSeF.
     * Pokazujemy WYŁĄCZNIE gdy:
     *  - firma ma włączony tryb KSeF (companies.ksef_mode_enabled),
     *  - faktura ma nadany numer (fullnumber),
     *  - faktura jest realnie w KSeF (ma numer KSeF).
     * Dzięki temu drafty, faktury bez numeru oraz wystawione bez numeru KSeF nie dostają QR.
     */
    private function shouldShowKsefQr(Invoice $inv): bool
    {
        if (trim((string)($inv->fullnumber ?? '')) === '') {
            return false;
        }
        if (trim((string)($inv->ksef_number ?? '')) === '') {
            return false;
        }

        return $this->isKsefModeEnabled((string)($inv->company_id ?? ''));
    }

    private function shouldSendToKsefNow(array $data): bool
    {
        if ((int)($data['ksef_send'] ?? 0) === 1) {
            return true;
        }

        return array_key_exists('save_and_send_ksef', $data);
    }

    /**
     * Czy faktura nie ma ŻADNYCH danych nabywcy (nazwa, NIP, VAT UE, inny identyfikator)?
     * Taka faktura poszłaby do KSeF jako Podmiot2 z <BrakID>1</BrakID> bez nazwy — nabywca nigdy jej
     * nie zobaczy w swoim KSeF (zgłoszenie biura z 2026-09-03, FV/1/09/2026).
     */
    private function buyerDataMissing(Invoice $invoice): bool
    {
        $b = $invoice->invoice_contractor ?? null;
        if ($b === null && !empty($invoice->id)) {
            try {
                $b = $this->fetchTable('InvoiceContractors')->find()->where(['invoice_id' => (string)$invoice->id])->first();
            } catch (\Throwable) {
                $b = null;
            }
        }
        if ($b === null) {
            return true;
        }
        foreach (['name', 'nip', 'vat_eu', 'tax_id_other'] as $field) {
            $v = is_array($b) ? ($b[$field] ?? null) : ($b->{$field} ?? null);
            if (trim((string)$v) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Użytkownik świadomie potwierdził wysyłkę bez danych nabywcy (checkbox w formularzu / w oknie wysyłki). */
    private function noBuyerSendConfirmed(): bool
    {
        $v = $this->request->getData('confirm_no_buyer');
        if ($v === null) {
            $v = $this->request->getQuery('confirm_no_buyer');
        }

        return in_array(strtolower(trim((string)$v)), ['1', 'true', 'on', 'yes'], true);
    }

    private const NO_BUYER_ERROR = 'Faktura nie ma danych nabywcy (nazwa, NIP, adres) — nabywca nie zobaczy jej w KSeF. Uzupełnij dane nabywcy w edycji faktury albo potwierdź świadomie wysyłkę bez nabywcy.';

    private function nonDraftConditions(): array
    {
        return [
            'OR' => [
                ['Invoices.workflow_status IS' => null],
                ['Invoices.workflow_status !=' => 'draft'],
            ],
        ];
    }

    private function rowHasUserData(array $row): bool
    {
        $keys = ['name', 'quantity', 'price', 'discount_percent', 'vat_code_id', 'gtu_code', 'product_desc', 'purchase_price'];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
            if (is_numeric($v) && (float)$v !== 0.0) {
                return true;
            }
        }

        return false;
    }

    private function hydrateInvoiceDraftFromData(Invoice $invoice, array $data): void
    {
        $ctr = (array)($data['invoice_contractor'] ?? []);
        if (!empty($ctr)) {
            $invoice->set('invoice_contractor', (object)$ctr);
        }

        $items = (array)($data['items'] ?? []);
        if (!empty($items)) {
            $prefill = [];
            foreach ($items as $row) {
                $r = (array)$row;
                if (!$this->rowHasUserData($r)) {
                    continue;
                }
                $prefill[] = (object)[
                    'name'             => (string)($r['name'] ?? ''),
                    'quantity'         => $r['quantity'] ?? 1,
                    'price'            => $r['price'] ?? 0,
                    'discount_percent' => $r['discount_percent'] ?? 0,
                    'vat_code_id'      => $r['vat_code_id'] ?? null,
                    'gtu_code'         => (string)($r['gtu_code'] ?? ''),
                    'product_desc'     => (string)($r['product_desc'] ?? ''),
                    'purchase_price'   => $r['purchase_price'] ?? 0,
                    'price_mode'       => (string)($r['price_mode'] ?? 'net'),
                ];
            }
            $invoice->set('invoice_contents', $prefill);
        }

        // Pola, które w formularzu mają nazwy niezgodne z kolumnami encji —
        // ustawiamy ręcznie, aby działało uzupełnianie formularza przy błędzie.
        if (!empty($data['flags']['fp'])) {
            $invoice->set('is_receipt_invoice', 1);
        }
    }

    private function logKsefSendEvent(?string $companyId, ?string $invoiceId, string $eventType, array $context = []): void
    {
        try {
            $payload = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $conn = ConnectionManager::get('default');
            $conn->insert('ksef_send_logs', [
                'id' => Text::uuid(),
                'company_id' => (string)($companyId ?? ''),
                'invoice_id' => (string)($invoiceId ?? ''),
                'event_type' => $eventType,
                'status_code' => isset($context['status_code']) ? (string)$context['status_code'] : null,
                'message' => isset($context['message']) ? (string)$context['message'] : null,
                'payload' => $payload,
                'created' => (new FrozenTime())->format('Y-m-d H:i:s'),
            ], [
                'id' => 'string',
                'company_id' => 'string',
                'invoice_id' => 'string',
                'event_type' => 'string',
                'status_code' => 'string',
                'message' => 'string',
                'payload' => 'string',
                'created' => 'datetime',
            ]);
        } catch (\Throwable) {
            // best-effort only
        }
    }

    private function storeUpoXmlForInvoice(string $companyId, string $ksefNumber, string $xml): void
    {
        try {
            $invoice = $this->Invoices->find()
                ->where(['company_id' => $companyId, 'ksef_number' => $ksefNumber])
                ->first();
            if ($invoice === null) {
                return;
            }
            $invoice->set('upo_xml', $xml);
            $invoice->set('upo_downloaded_at', new FrozenTime());
            $this->Invoices->save($invoice);
        } catch (\Throwable) {
            // best-effort only
        }
    }

    private function validateSendDateWindow(Invoice $invoice): ?string
    {
        try {
            $issueDateRaw = $invoice->date;
            if ($issueDateRaw === null || $issueDateRaw === '') {
                return 'Brak daty wystawienia (P_1).';
            }
            $issueDate = new \DateTimeImmutable(is_object($issueDateRaw) && method_exists($issueDateRaw, 'format')
                ? $issueDateRaw->format('Y-m-d')
                : (string)$issueDateRaw);
            $minDate = (new \DateTimeImmutable('today'))->modify('-1 day');
            if ($issueDate < $minDate) {
                return 'Data faktury (P_1) musi być nie wcześniejsza niż wczoraj (' . $minDate->format('Y-m-d') . ').';
            }
        } catch (\Throwable) {
            return 'Nie udało się zweryfikować daty faktury (P_1).';
        }

        return null;
    }

    /**
     * AJAX endpoint: get predicted draft number without saving
     */
    public function ajaxGetDraftNumber()
    {
        $this->request->allowMethod('post');
        $invoiceId = $this->request->getData('invoice_id');
        $seriesId = $this->request->getData('series_id');
        $invoiceDate = $this->request->getData('date') ?? date('Y-m-d');

        $identity = $this->request->getAttribute('identity');
        $companyId = $identity->company_id ?? null;

        if (!$invoiceId || !$seriesId || !$companyId) {
            return $this->response->withStatus(400)->withType('application/json')->withStringBody(
                json_encode(['error' => 'Missing parameters'])
            );
        }

        try {
            $fullnumber = $this->generateDraftNumber($seriesId, $invoiceId, $invoiceDate, $companyId);
            return $this->response->withType('application/json')->withStringBody(
                json_encode(['fullnumber' => $fullnumber])
            );
        } catch (\Exception $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(
                json_encode(['error' => $e->getMessage()])
            );
        }
    }

    /**
     * Generate draft number without modifying database
     */
    private function generateDraftNumber(string $seriesId, string $invoiceId, string $invoiceDate, string $companyId): string
    {
        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
        $series = $InvoiceSeries->find()
            ->contain(['InvoiceSeriesPeriods'])
            ->where(['InvoiceSeries.id' => $seriesId, 'InvoiceSeries.company_id' => $companyId])
            ->first();
        if (!$series) {
            throw new \RuntimeException('Nie znaleziono serii numeracji dla dokumentu.');
        }

        $dateObject = new \DateTimeImmutable($invoiceDate);
        $year = (int)$dateObject->format('Y');
        $month = (int)$dateObject->format('m');

        $where = [
            'company_id' => $companyId,
            'invoice_series_id' => $series->id,
            'fullnumber IS NOT' => null,
            'id !=' => $invoiceId,
        ];

        $periodName = (string)($series->invoice_series_period->name ?? '');
        if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
            $where['year'] = $year;
            $where['month'] = $month;
        } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
            $where['year'] = $year;
        }

        $lastInvoice = $this->Invoices->find()
            ->where($where)
            ->order(['number' => 'DESC', 'id' => 'DESC'])
            ->first();

        $overrideNext = $series->override_next_number ?? null;
        if ($overrideNext !== null && (int)$overrideNext > 0) {
            $nextNumber = (int)$overrideNext;
        } elseif ($lastInvoice) {
            $extractedNumber = !empty($lastInvoice->number)
                ? (int)$lastInvoice->number
                : $this->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
            $nextNumber = $extractedNumber + 1;
        } else {
            $nextNumber = (int)($series->starting_number ?: 1);
        }

        $template = (string)($series->series_template ?: '[numer]');
        return $this->formatInvoicePattern($template, $nextNumber, $invoiceDate);
    }

    private function ensureInvoiceNumberForSend(Invoice $invoice, string $companyId): void
    {
        $fullnumber = trim((string)($invoice->fullnumber ?? ''));
        if ($fullnumber !== '') {
            return;
        }

        $seriesId = (string)($invoice->invoice_series_id ?? '');
        if ($seriesId === '') {
            throw new \RuntimeException('Brak przypisanej serii numeracji dla dokumentu.');
        }

        $issueDateRaw = $invoice->date;
        $issueDate = is_object($issueDateRaw) && method_exists($issueDateRaw, 'format')
            ? $issueDateRaw->format('Y-m-d')
            : (string)$issueDateRaw;
        if ($issueDate === '') {
            $issueDate = date('Y-m-d');
        }

        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
        $series = $InvoiceSeries->find()
            ->contain(['InvoiceSeriesPeriods'])
            ->where(['InvoiceSeries.id' => $seriesId, 'InvoiceSeries.company_id' => $companyId])
            ->first();
        if (!$series) {
            throw new \RuntimeException('Nie znaleziono serii numeracji dla dokumentu.');
        }

        $dateObject = new \DateTimeImmutable($issueDate);
        $year = (int)$dateObject->format('Y');
        $month = (int)$dateObject->format('m');

        $where = [
            'company_id' => $companyId,
            'invoice_series_id' => $series->id,
            'fullnumber IS NOT' => null,
            'id !=' => $invoice->id,
        ];

        $periodName = (string)($series->invoice_series_period->name ?? '');
        if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
            $where['year'] = $year;
            $where['month'] = $month;
        } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
            $where['year'] = $year;
        }

        $lastInvoice = $this->Invoices->find()
            ->where($where)
            ->order(['number' => 'DESC', 'id' => 'DESC'])
            ->first();

        // Jednorazowy override numeru (np. przy migracji z innego systemu)
        $overrideNext = $series->override_next_number ?? null;
        if ($overrideNext !== null && (int)$overrideNext > 0) {
            $nextNumber = (int)$overrideNext;
            $this->Invoices->getConnection()->execute(
                'UPDATE invoice_series SET override_next_number = NULL WHERE id = ?',
                [$series->id]
            );
        } elseif ($lastInvoice) {
            $extractedNumber = !empty($lastInvoice->number)
                ? (int)$lastInvoice->number
                : $this->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
            $nextNumber = $extractedNumber + 1;
        } else {
            $nextNumber = (int)($series->starting_number ?: 1);
        }

        $template = (string)($series->series_template ?: '[numer]');
        $fullnumber = $this->formatInvoicePattern($template, $nextNumber, $issueDate);
        $invoice->set('fullnumber', $fullnumber);
        $invoice->set('number', $nextNumber);
        $invoice->set('day', (int)$dateObject->format('d'));
        $invoice->set('month', (int)$dateObject->format('m'));
        $invoice->set('year', (int)$dateObject->format('Y'));
        $invoice->set('day_year', (int)$dateObject->format('z') + 1);

        if (!$this->Invoices->save($invoice)) {
            throw new \RuntimeException('Nie udało się nadać numeru dokumentu przed wysyłką do KSeF.');
        }
    }

    private function normalizeDraftDateBeforeSend(Invoice $invoice): void
    {
        if ((string)($invoice->workflow_status ?? '') !== 'draft') {
            return;
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $issueDateRaw = $invoice->date;
        $issueDate = is_object($issueDateRaw) && method_exists($issueDateRaw, 'format')
            ? $issueDateRaw->format('Y-m-d')
            : (string)$issueDateRaw;

        if ($issueDate === $today) {
            return;
        }

        $todayObj = new \DateTimeImmutable($today);
        $invoice->set('date', $today);
        $invoice->set('day', (int)$todayObj->format('d'));
        $invoice->set('month', (int)$todayObj->format('m'));
        $invoice->set('year', (int)$todayObj->format('Y'));
        $invoice->set('day_year', (int)$todayObj->format('z') + 1);

        // Wymuś przeliczenie numeru po zmianie daty.
        $invoice->set('fullnumber', null);
        $invoice->set('number', null);

        if (!$this->Invoices->save($invoice, ['checkRules' => false, 'validate' => false])) {
            throw new \RuntimeException('Nie udało się zaktualizować daty dokumentu roboczego przed wysyłką do KSeF.');
        }
    }

    /**
     * Validate invoice form data via AJAX (no save)
     * POST /invoices/validate-ajax
     */
    public function validateAjax(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        // Force JSON response (builder optional)
        $this->viewBuilder()->setClassName('Json');

        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id'); // not used here, but kept for parity

    $data = (array)$this->request->getData();
        $errors = [];
    $isNoVat = strtolower((string)($data['kind'] ?? '')) === 'novat';
    $isCorrection = !empty($data['parent_id']);

        // Basic fields
        $date = $data['date'] ?? null;
        if (!$date) {
            $errors['date'] = 'Podaj datę wystawienia.';
        }

        // Contractor: required minimal data (either selected contractor or snapshot filled)
        $contractorId = $data['contractor_id'] ?? null;
        $ctrName = $data['invoice_contractor']['name'] ?? null;
        if (!$contractorId && !$ctrName && !$this->noBuyerSendConfirmed()) {
            $errors['invoice_contractor.name'] = 'Wybierz kontrahenta lub uzupełnij dane nabywcy.';
        }

        // Items validation
        $items = (array)($data['items'] ?? []);
        if (count($items) === 0) {
            $errors['items'] = 'Dodaj przynajmniej jedną pozycję.';
        }

        // Detect margin-mode heuristically (present in add_margin view)
        $isMargin = array_key_exists('margin_type', $data) || array_key_exists('margin_vat_rate', $data);

        $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;
        if ($isMargin) {
            // Margin: price is gross per unit; purchase_price is internal cost (gross)
            $totalSales = 0.0; $totalPurchase = 0.0;
            foreach ($items as $idx => $row) {
                $name = trim((string)($row['name'] ?? ''));
                $qty  = (float)($row['quantity'] ?? 0);
                $sale = (float)($row['price'] ?? 0); // WARTOŚĆ BRUTTO (szt.)
                $buy  = (float)($row['purchase_price'] ?? 0);

                if ($name === '') {
                    $errors["items.$idx.name"] = 'Nazwa jest wymagana.';
                }
                if (!$isCorrection && $qty <= 0) {
                    $errors["items.$idx.quantity"] = 'Ilość musi być większa od zera.';
                }
                if ($sale < 0) {
                    $errors["items.$idx.price"] = 'Wartość brutto nie może być ujemna.';
                }
                if ($buy < 0) {
                    $errors["items.$idx.purchase_price"] = 'Cena nabycia nie może być ujemna.';
                }

                $totalSales    += round($qty * $sale, 2);
                $totalPurchase += round($qty * $buy, 2);
            }
            $rate = (float)($data['margin_vat_rate'] ?? 23);
            $marginGross = max(0.0, $totalSales - $totalPurchase);
            $vatOnMargin = $rate > 0 ? round($marginGross * ($rate / (100.0 + $rate)), 2) : 0.0;
            // Map totals to UI semantics for margin view
            $sumNet   = round($totalPurchase, 2);
            $sumTax   = round($vatOnMargin, 2);
            $sumGross = round($totalSales, 2);
        } else {
            foreach ($items as $idx => $row) {
                $name = trim((string)($row['name'] ?? ''));
                $qty  = (float)($row['quantity'] ?? 0);
                $price= (float)($row['price'] ?? 0);
                $disc = (float)($row['discount_percent'] ?? 0);
                $vatId= $row['vat_code_id'] ?? null;

                if ($name === '') {
                    $errors["items.$idx.name"] = 'Nazwa jest wymagana.';
                }
                if (!$isCorrection && $qty <= 0) {
                    $errors["items.$idx.quantity"] = 'Ilość musi być większa od zera.';
                }
                if ($price < 0) {
                    $errors["items.$idx.price"] = 'Cena nie może być ujemna.';
                }
                if ($disc < 0 || $disc > 100) {
                    $errors["items.$idx.discount_percent"] = 'Rabat w % musi być w zakresie 0–100.';
                }
                if (!$isNoVat) {
                    if ($vatId === null || $vatId === '') {
                        $errors["items.$idx.vat_code_id"] = 'Wybierz stawkę VAT.';
                    }
                }

                // compute quick totals (rate unknown here -> assume 0 for a fast check)
                $unitAfterDisc = $price * (1 - ($disc / 100));
                $net = round($qty * $unitAfterDisc, 2);
                $rate = $isNoVat ? 0.0 : 0.0; // quick check: no VAT in novat form
                $tax = $isNoVat ? 0.0 : round($net * ($rate/100), 2);
                $gross = round($net + $tax, 2);
                $sumNet += $net; $sumTax += $tax; $sumGross += $gross;
            }
        }

        // Payment date optional but if provided and before date -> warn
        $paymentDate = $data['paymentdate'] ?? null;
        if ($paymentDate && $date && strcmp((string)$paymentDate, (string)$date) < 0) {
            $errors['paymentdate'] = 'Termin płatności nie może być wcześniejszy niż data wystawienia.';
        }

        $resp = [
            'success' => empty($errors),
            'errors'  => $errors,
            'totals'  => [
                'netto' => round($sumNet, 2),
                'tax'   => round($sumTax, 2),
                'gross' => round($sumGross, 2),
            ],
        ];

        return $this->response->withType('application/json')
            ->withStringBody(json_encode($resp));
    }
    public function export(): Response
{
    $this->request->allowMethod(['get']);

    $identity  = $this->getRequest()->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    // Filtry z query (zgodne z widokiem)
    $q        = trim((string)$this->request->getQuery('q', ''));
    $state    = $this->request->getQuery('state');       // unpaid|partial|paid|overdue|null
    $from     = $this->request->getQuery('from');        // Y-m-d
    $to       = $this->request->getQuery('to');          // Y-m-d
    $currency = $this->request->getQuery('currency');    // PLN/EUR/...

    $Invoices = $this->fetchTable('Invoices');

    /** @var SelectQuery $query */
    $query = $Invoices->find()
        ->contain(['InvoiceContractors']) // nazwa, nip/email w CSV
        ->where([
            'Invoices.company_id' => $companyId,
        ]);

    // Wyszukiwanie pełnotekstowe po numerze i kontrahencie
    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
        $query
            ->leftJoinWith('InvoiceContractors')
            ->andWhere(function ($exp) use ($like) {
                return $exp->or_([
                    'Invoices.fullnumber LIKE'                 => $like,
                    'Invoices.id LIKE'                         => $like,
                    'InvoiceContractors.name LIKE'             => $like,
                    'InvoiceContractors.vatid LIKE'            => $like,   // NIP
                    'InvoiceContractors.email LIKE'            => $like,
                ]);
            });
    }

    if ($state !== null && $state !== '') {
        $query->andWhere(['Invoices.paymentstate' => $state]);
    }
    if ($currency !== null && $currency !== '') {
        $query->andWhere(['Invoices.currency' => $currency]);
    }
    if ($from) {
        $query->andWhere(['Invoices.date >=' => $from]);
    }
    if ($to) {
        $query->andWhere(['Invoices.date <=' => $to]);
    }

    $query->orderDesc('Invoices.date')->orderAsc('Invoices.fullnumber');

    // Mapowanie statusu na PL
    $stateLabel = static function (?string $s): string {
        return match ($s) {
            'paid'    => 'Opłacona',
            'unpaid'  => 'Nieopłacona',
            'partial' => 'Częściowo opłacona',
            'overdue' => 'Po terminie',
            default   => '',
        };
    };

    // Nagłówki CSV
    $sep = ';'; $eol = "\r\n"; $bom = "\xEF\xBB\xBF";
    $rows = [];
    $rows[] = [
        'ID',
        'Numer',
        'Data wystawienia',
        'Kontrahent',
        'NIP',
        'E-mail',
        'Kwota brutto',
        'Waluta',
        'Status',
        'Termin płatności',
        'Data utworzenia',
    ];

    foreach ($query as $inv) {
        $rows[] = [
            (string)$inv->id,
            (string)($inv->fullnumber ?: $inv->id),
            $inv->date?->i18nFormat('yyyy-MM-dd') ?? '',
            (string)($inv->invoice_contractor->name  ?? ''),
            (string)($inv->invoice_contractor->vatid ?? ''),   // NIP
            (string)($inv->invoice_contractor->email ?? ''),
            number_format((float)$inv->total, 2, '.', ''),
            (string)($inv->currency ?? 'PLN'),
            $stateLabel($inv->paymentstate),
            $inv->paymentdate?->i18nFormat('yyyy-MM-dd') ?? '',
            $inv->created?->i18nFormat('yyyy-MM-dd HH:mm:ss') ?? '',
        ];
    }

    // Escaping pól CSV
    $escape = static function (string $v) use ($sep): string {
        $need = str_contains($v, $sep) || str_contains($v, '"') || str_contains($v, "\n") || str_contains($v, "\r");
        $v = str_replace('"', '""', $v);
        return $need ? "\"{$v}\"" : $v;
    };

    // Składanie CSV
    $csv = $bom;
    foreach ($rows as $r) {
        $csv .= implode($sep, array_map(fn($x) => $escape((string)$x), $r)) . $eol;
    }

    $filename = 'faktury_' . (new FrozenTime())->i18nFormat('yyyyMMdd_HHmmss') . '.csv';

    return $this->response
        ->withType('csv')
        ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->withHeader('Content-Length', (string)strlen($csv))
        ->withStringBody($csv);
}
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
public function index()
{
    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id'); // char(36)

    $q           = trim((string)$this->request->getQuery('q'));
    $state       = $this->request->getQuery('state');
    $from        = $this->request->getQuery('from');
    $to          = $this->request->getQuery('to');
    $currency    = $this->request->getQuery('currency');
    $emailStatus = $this->request->getQuery('email_status');

    // Netowanie korekt (używane w „Sumie widocznych" ORAZ w kartach/tabeli podsumowania):
    // liczy się tylko finalna wartość — wykluczamy skorygowane oryginały i korekty pośrednie.
    // parent_id korekty wskazuje korygowany dokument. Dzięki temu para (faktura + korekta)
    // na tę samą kwotę nie podwaja sumy.
    $correctedIds = $this->Invoices->find()
        ->select(['parent_id'])
        ->where(['company_id' => $companyId, 'type' => 'correction', 'parent_id IS NOT' => null])
        ->distinct(['parent_id'])
        ->enableHydration(false)
        ->all()->extract('parent_id')->toArray();
    $correctedIdList = array_values(array_filter($correctedIds));

                $query = $this->Invoices->find()
            ->contain([
                'InvoiceContractors' => function($q){ return $q->select(['invoice_id','name','nip','email']); },
                'InvoiceEmailQueue' => function($q){ return $q->select(['invoice_id','email','status','created'])->order(['created' => 'DESC']); }
            ])
            ->where(['Invoices.company_id' => $companyId])
            ->where($this->nonDraftConditions());

    // Wspólne filtry listy — używane do zapytania listy ORAZ do „Sumy widocznych faktur",
    // żeby podsumowanie zgadzało się 1:1 z tym, co realnie widać na liście.
    $applyListFilters = function($qb) use ($q, $state, $currency, $from, $to, $emailStatus) {
        if ($q !== '') {
            $qb->leftJoinWith('InvoiceContractors')
               ->where(function($exp) use ($q) {
                   return $exp->or([
                       'Invoices.fullnumber LIKE' => "%$q%",
                       'InvoiceContractors.name LIKE' => "%$q%",
                       'InvoiceContractors.nip LIKE' => "%$q%",
                   ]);
               });
        }
        if ($state)    { $qb->where(['Invoices.paymentstate' => $state]); }
        if ($currency) { $qb->where(['Invoices.currency' => strtoupper($currency)]); }
        if ($from)     { $qb->where(['Invoices.date >=' => $from]); }
        if ($to)       { $qb->where(['Invoices.date <=' => $to]); }
        if ($emailStatus) {
            if ($emailStatus === 'not_sent') {
                $sub = $this->Invoices->InvoiceEmailQueue->find()->select(['invoice_id'])->distinct(['invoice_id']);
                $qb->where(['Invoices.id NOT IN' => $sub]);
            } else {
                $sub = $this->Invoices->InvoiceEmailQueue->find()->select(['invoice_id'])
                    ->where(['InvoiceEmailQueue.status' => $emailStatus])->distinct(['invoice_id']);
                $qb->where(['Invoices.id IN' => $sub]);
            }
        }
        return $qb;
    };
    $applyListFilters($query);

    $invoices = $this->paginate($query, [
        'limit' => 20,
        'order' => ['date' => 'DESC', 'number' => 'DESC'],
        'sortableFields' => [
            'number',
            'fullnumber',
            'type',
            'date',
            'total',
            'paymentstate',
            'paymentdate',
            'created',
            'InvoiceContractors.name',
        ],
    ]);

    // „Suma widocznych faktur" — suma kolumny total wierszy pasujących do aktywnych filtrów,
    // z NETOWANIEM korekt (finalna wartość): para faktura+korekta na tę samą kwotę nie podwaja.
    // Wykluczamy skorygowane oryginały i korekty pośrednie. Grupujemy po walucie.
    $visibleSumQuery = $this->Invoices->find()
        ->where(['Invoices.company_id' => $companyId])
        ->where($this->nonDraftConditions());
    $applyListFilters($visibleSumQuery);
    if (!empty($correctedIdList)) {
        $visibleSumQuery->where(['Invoices.id NOT IN' => $correctedIdList]);
    }
    $visibleSums = [];
    $visibleCount = 0;
    foreach (
        $visibleSumQuery
            ->select([
                'cur' => 'Invoices.currency',
                's'   => $visibleSumQuery->func()->sum('Invoices.total'),
                'c'   => $visibleSumQuery->func()->count('*'),
            ])
            ->group(['Invoices.currency'])
            ->enableHydration(false)
            ->all()
            ->toArray() as $r
    ) {
        $cur = (string)($r['cur'] ?: 'PLN');
        $visibleSums[$cur] = round((float)($r['s'] ?? 0), 2);
        $visibleCount += (int)($r['c'] ?? 0);
    }

    // Rozbicie „Sumy widocznych" (do modala „jak wyliczono"): per waluta + typ dokumentu,
    // tylko wiersze WLICZONE do sumy (po netowaniu korekt).
    $visibleBreakdown = [];
    $bq = $this->Invoices->find()
        ->where(['Invoices.company_id' => $companyId])
        ->where($this->nonDraftConditions());
    $applyListFilters($bq);
    if (!empty($correctedIdList)) {
        $bq->where(['Invoices.id NOT IN' => $correctedIdList]);
    }
    foreach (
        $bq->select([
                'cur' => 'Invoices.currency',
                'typ' => 'Invoices.type',
                's'   => $bq->func()->sum('Invoices.total'),
                'c'   => $bq->func()->count('*'),
            ])
            ->group(['Invoices.currency', 'Invoices.type'])
            ->enableHydration(false)
            ->all()->toArray() as $r
    ) {
        $visibleBreakdown[] = [
            'currency' => (string)($r['cur'] ?: 'PLN'),
            'type'     => (string)($r['typ'] ?? ''),
            'sum'      => round((float)($r['s'] ?? 0), 2),
            'count'    => (int)($r['c'] ?? 0),
        ];
    }

    // Pominięte skorygowane oryginały / korekty pośrednie pasujące do filtra (wyjaśnienie w modalu).
    $visibleExcluded = [];
    if (!empty($correctedIdList)) {
        $eq = $this->Invoices->find()
            ->where(['Invoices.company_id' => $companyId])
            ->where($this->nonDraftConditions());
        $applyListFilters($eq);
        $eq->where(['Invoices.id IN' => $correctedIdList]);
        foreach (
            $eq->select([
                    'cur' => 'Invoices.currency',
                    's'   => $eq->func()->sum('Invoices.total'),
                    'c'   => $eq->func()->count('*'),
                ])
                ->group(['Invoices.currency'])
                ->enableHydration(false)
                ->all()->toArray() as $r
        ) {
            $visibleExcluded[] = [
                'currency' => (string)($r['cur'] ?: 'PLN'),
                'sum'      => round((float)($r['s'] ?? 0), 2),
                'count'    => (int)($r['c'] ?? 0),
            ];
        }
    }

        // Linkage information for proformas (child advances/final)
        $advanceCounts = [];
        $finalByProforma = [];
        $advancesByProforma = [];
        $proformaIds = [];
        foreach ($invoices as $iv) {
            if (($iv->type ?? null) === 'proforma') {
                $proformaIds[] = $iv->id;
            }
        }
        if ($proformaIds) {
            // Count advance children per proforma
            $Adv = $this->Invoices->find()
                ->select([
                    'parent_id',
                    'cnt' => $this->Invoices->find()->func()->count('*')
                ])
                ->where([
                    'company_id' => $companyId,
                    'parent_id IN' => $proformaIds,
                    'type' => 'advance'
                ])
                ->group('parent_id')
                ->enableHydration(false)
                ->all();
            foreach ($Adv as $row) {
                $advanceCounts[$row['parent_id']] = (int)$row['cnt'];
            }

            // Collect advance list per proforma for download links
            $AdvList = $this->Invoices->find()
                ->select(['id','parent_id','fullnumber','date','total','currency'])
                ->where([
                    'company_id' => $companyId,
                    'parent_id IN' => $proformaIds,
                    'type' => 'advance'
                ])
                ->order(['date' => 'ASC'])
                ->all();
            foreach ($AdvList as $a) {
                $pid = (string)$a->parent_id;
                if (!isset($advancesByProforma[$pid])) $advancesByProforma[$pid] = [];
                $advancesByProforma[$pid][] = [
                    'id'         => $a->id,
                    'fullnumber' => (string)($a->fullnumber ?? ''),
                    'total'      => (float)($a->total ?? 0),
                    'currency'   => (string)($a->currency ?? 'PLN'),
                ];
            }

            // Latest final invoice per proforma (id, fullnumber)
            $Finals = $this->Invoices->find()
                ->select(['id','parent_id','fullnumber','created','paymentstate'])
                ->where([
                    'company_id' => $companyId,
                    'parent_id IN' => $proformaIds,
                    'type' => 'final'
                ])
                ->order(['created' => 'DESC'])
                ->all();
            foreach ($Finals as $f) {
                $pid = (string)$f->parent_id;
                if (!isset($finalByProforma[$pid])) {
                    $finalByProforma[$pid] = [
                        'id'            => $f->id,
                        'fullnumber'    => (string)($f->fullnumber ?? ''),
                        'paymentstate'  => (string)($f->paymentstate ?? ''),
                    ];
                }
            }
        }


    // STATY (przykład — dopasuj nazwy stanów do Twoich)
    $yearStart = (new \DateTimeImmutable('first day of january'))->format('Y-m-d');
    $today     = (new \DateTimeImmutable('today'))->format('Y-m-d');

    $base = $this->Invoices->find()->where(['company_id' => $companyId])->where($this->nonDraftConditions());

    // daty graniczne
$yearStart = (new \DateTimeImmutable('first day of january'))->format('Y-m-d');
$today     = (new \DateTimeImmutable('today'))->format('Y-m-d');
$monthStart= (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');

// małe helpery do agregacji (SQL)
$sum = function(array $where, string $col = 'Invoices.total'): float {
    /** @var \Cake\ORM\Table $T */
    $T = $this->Invoices;
    $q = $T->find();
    $q->select(['s' => $q->func()->sum($col)])
      ->where($where)
      ->enableHydration(false);
    $row = $q->first();
    return (float)($row['s'] ?? 0);
};
$avg = function(array $where, string $col = 'Invoices.total'): float {
    $T = $this->Invoices;
    $q = $T->find();
    $q->select(['a' => $q->func()->avg($col)])
      ->where($where)
      ->enableHydration(false);
    $row = $q->first();
    return (float)($row['a'] ?? 0);
};
$cnt = function(array $where): int {
    return (int)$this->Invoices->find()->where($where)->count();
};

// ── Filtry podsumowania ──────────────────────────────────────────────────
// Podsumowanie (karty + tabela walut) ma dotyczyć aktywnego filtra okresu (from/to)
// oraz waluty, a nie całości. Gdy brak filtra okresu — zostaje bieżący rok (jak dotąd).
// Filtra stanu płatności NIE nakładamy tu, bo karty rozbijają dane wg stanu.
$hasDateFilter = (!empty($from) || !empty($to));
$rangeFrom = !empty($from) ? $from : $yearStart;
$rangeTo   = !empty($to)   ? $to   : $today;
$curFilter = !empty($currency) ? strtoupper((string)$currency) : null;

// $correctedIdList policzone wcześniej (zaraz po filtrach). Netowanie korekt: zostają
// czyste faktury sprzedażowe (nieskorygowane) + najnowsze korekty (niebędące niczyim parentem).
// Dokłada okres (gdy ustawiony/wymuszony), walutę (gdy ustawiona) oraz netowanie korekt.
$applyStatFilters = function(array $where, bool $forceDate = false) use ($hasDateFilter, $rangeFrom, $rangeTo, $curFilter, $correctedIdList): array {
    if ($hasDateFilter || $forceDate) {
        $where['Invoices.date >='] = $rangeFrom;
        $where['Invoices.date <='] = $rangeTo;
    }
    if ($curFilter !== null) {
        $where['Invoices.currency'] = $curFilter;
    }
    if (!empty($correctedIdList)) {
        $where['Invoices.id NOT IN'] = $correctedIdList;
    }
    return $where;
};

// STATYSTYKI
// Przygotuj stats po walutach (do tabeli podsumowania)
$currencyStats = [];
$currencies = $this->Invoices->find()
    ->select(['currency'])
    ->where(['Invoices.company_id' => $companyId])
    ->distinct(['currency'])
    ->enableHydration(false)
    ->all()
    ->extract('currency')
    ->toArray();
// Filtr waluty — pokaż w tabeli tylko wybraną walutę.
if ($curFilter !== null) {
    $currencies = array_values(array_filter($currencies, fn($c) => strtoupper((string)$c) === $curFilter));
}

foreach ($currencies as $curr) {
    $currencyStats[$curr] = [
        'currency'      => $curr,
        'year_netto'    => 0,
        'year_brutto'   => 0,
        'paid_netto'    => 0,
        'paid_brutto'   => 0,
        'pending_brutto'=> 0,
        'overdue_brutto'=> 0,
        'invoice_count' => 0,
        'paid_count'    => 0,
        'pending_count' => 0,
        'overdue_count' => 0,
    ];

    // Oblicz netto i brutto dla okresu (wyklucz draft).
    // Netowanie korekt: bez proform/zaliczek, bez skorygowanych oryginałów i korekt pośrednich
    // (id NOT IN correctedIdList) — zostają czyste faktury + najnowsze korekty (finalna wartość).
    $ciWhere = [
        'Invoices.company_id' => $companyId,
        'Invoices.currency' => $curr,
        'Invoices.date >='  => $rangeFrom,
        'Invoices.date <='  => $rangeTo,
        'Invoices.type NOT IN' => ['proforma', 'advance'],
        'OR' => [
            ['Invoices.workflow_status IS' => null],
            ['Invoices.workflow_status !=' => 'draft']
        ],
    ];
    if (!empty($correctedIdList)) {
        $ciWhere['Invoices.id NOT IN'] = $correctedIdList;
    }
    $yearInvoices = $this->Invoices->find()
        ->select(['id', 'total', 'netto', 'paymentstate', 'currency'])
        ->where($ciWhere)
        ->enableHydration(false)
        ->all();

    foreach ($yearInvoices as $inv) {
        $brutto = (float)($inv['total'] ?? 0);
        $netto = (float)($inv['netto'] ?? 0);

        $currencyStats[$curr]['year_brutto'] += $brutto;
        $currencyStats[$curr]['year_netto'] += $netto;
        $currencyStats[$curr]['invoice_count']++;

        if ($inv['paymentstate'] === 'paid') {
            $currencyStats[$curr]['paid_brutto'] += $brutto;
            $currencyStats[$curr]['paid_netto'] += $netto;
            $currencyStats[$curr]['paid_count']++;
        } elseif (in_array($inv['paymentstate'], ['unpaid', 'partial'])) {
            $currencyStats[$curr]['pending_brutto'] += $brutto;
            $currencyStats[$curr]['pending_count']++;
        } elseif ($inv['paymentstate'] === 'overdue') {
            $currencyStats[$curr]['overdue_brutto'] += $brutto;
            $currencyStats[$curr]['overdue_count']++;
        }
    }

    // Zaokrąglij i oblicz procenty
    foreach ($currencyStats[$curr] as $key => $value) {
        if (is_float($value)) {
            $currencyStats[$curr][$key] = round($value, 2);
        }
    }
    // % opłacenia
    $currencyStats[$curr]['paid_percent'] = $currencyStats[$curr]['year_brutto'] > 0
        ? round(($currencyStats[$curr]['paid_brutto'] / $currencyStats[$curr]['year_brutto']) * 100, 1)
        : 0;
}

$ndraft = ['OR' => [['Invoices.workflow_status IS' => null], ['Invoices.workflow_status !=' => 'draft']]];

$stats = [
    // Etykieta waluty kart: gdy filtr waluty aktywny — pokaż wybraną walutę.
    'currency'         => $curFilter ?? 'PLN',
    'by_currency'      => $currencyStats,
    // Flaga dla widoku: czy podsumowanie jest zawężone filtrem okresu.
    'filtered_period'  => $hasDateFilter,
    'range_from'       => $hasDateFilter ? $rangeFrom : null,
    'range_to'         => $hasDateFilter ? $rangeTo : null,
    // „Suma widocznych faktur" — wg aktywnych filtrów, korekty netowane (per waluta).
    'visible_sums'      => $visibleSums,
    'visible_count'     => $visibleCount,
    'visible_breakdown' => $visibleBreakdown,
    'visible_excluded'  => $visibleExcluded,

    // Przychód w okresie (domyślnie bieżący rok; przy filtrze from/to — wybrany zakres).
    // Netowanie korekt: bez proform/zaliczek, bez skorygowanych oryginałów i korekt pośrednich —
    // zostają czyste faktury + najnowsze korekty (finalna wartość). Korekta do 0 zł → wnosi 0.
    'year_total'       => (function() use ($sum, $companyId, $rangeFrom, $rangeTo, $curFilter, $ndraft, $correctedIdList): float {
        $where = [
            'Invoices.company_id'  => $companyId,
            'Invoices.date >='     => $rangeFrom,
            'Invoices.date <='     => $rangeTo,
            'Invoices.type NOT IN' => ['proforma', 'advance'],
        ] + $ndraft;
        if ($curFilter !== null) { $where['Invoices.currency'] = $curFilter; }
        if (!empty($correctedIdList)) { $where['Invoices.id NOT IN'] = $correctedIdList; }
        return round($sum($where), 2);
    })(),
    'year_count'       => $cnt($applyStatFilters(['Invoices.company_id' => $companyId] + $ndraft, true)),
    'year_paid'        => $sum($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid'] + $ndraft, true)),

    // paid (w okresie gdy filtr aktywny, inaczej całość)
    'paid_total'       => $sum($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid'] + $ndraft)),
    'paid_count'       => $cnt($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid'] + $ndraft)),
    'paid_avg'         => $avg($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid'] + $ndraft)),

    // pending (unpaid/partial)
    'pending_count'    => $cnt($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']] + $ndraft)),
    'pending_total'    => $sum($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']] + $ndraft)),
    'remaining_total'  => $sum($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']] + $ndraft), 'Invoices.remaining'),

    // overdue
    'overdue_count'    => $cnt($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'overdue'] + $ndraft)),
    'overdue_total'    => $sum($applyStatFilters(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'overdue'] + $ndraft)),

    // bieżący miesiąc (z filtrem waluty, bez nadpisywania zakresu miesiąca)
    'month_paid_count' => $cnt(($curFilter !== null ? ['Invoices.currency' => $curFilter] : []) + [
                            'Invoices.company_id'  => $companyId,
                            'Invoices.paymentstate'=> 'paid',
                            'Invoices.date >='     => $monthStart,
                         ] + $ndraft),

    'overdue_max_days' => 0,
];

    $ksefModeEnabled = $this->isKsefModeEnabled((string)$companyId);
    $this->set(compact('invoices','stats','advanceCounts','finalByProforma','advancesByProforma','ksefModeEnabled','q','state','from','to','currency'));
}

    // requireAdmin() jest w AppController

    public function adminInvoices()
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $q       = trim((string)$this->request->getQuery('q'));
        $from    = $this->request->getQuery('from');
        $to      = $this->request->getQuery('to');
        $type    = $this->request->getQuery('type');
        $state   = $this->request->getQuery('state');

        $query = $this->Invoices->find()
            ->contain([
                'InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'name', 'nip']),
                'Companies'          => fn($q) => $q->select(['id', 'name', 'nip']),
            ])
            ->where($this->nonDraftConditions());

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($exp) use ($like) {
                return $exp->or([
                    'Invoices.fullnumber LIKE' => $like,
                    'InvoiceContractors.name LIKE' => $like,
                    'InvoiceContractors.nip LIKE'  => $like,
                    'Companies.name LIKE'          => $like,
                ]);
            });
        }
        if ($from)  { $query->where(['Invoices.date >=' => $from]); }
        if ($to)    { $query->where(['Invoices.date <=' => $to]); }
        if ($type)  { $query->where(['Invoices.type' => $type]); }
        if ($state) { $query->where(['Invoices.paymentstate' => $state]); }

        $invoices = $this->paginate($query, [
            'limit'          => 25,
            'order'          => ['Invoices.date' => 'DESC', 'Invoices.number' => 'DESC'],
            'sortableFields' => ['Invoices.fullnumber', 'Invoices.date', 'Invoices.created', 'Invoices.total', 'Invoices.type', 'Invoices.paymentstate', 'Companies.name', 'InvoiceContractors.name'],
        ]);

        $this->set(compact('invoices'));
    }

    public function adminDeletionLogs()
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $q    = trim((string)$this->request->getQuery('q'));
        $from = $this->request->getQuery('from');
        $to   = $this->request->getQuery('to');

        $LogsTbl = $this->fetchTable('AdminDeletionLogs');
        $query = $LogsTbl->find()->orderDesc('AdminDeletionLogs.created');

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($exp) use ($like) {
                return $exp->or([
                    'AdminDeletionLogs.fullnumber LIKE'       => $like,
                    'AdminDeletionLogs.company_name LIKE'     => $like,
                    'AdminDeletionLogs.contractor_name LIKE'  => $like,
                    'AdminDeletionLogs.deleted_by_username LIKE' => $like,
                ]);
            });
        }
        if ($from) { $query->where(['AdminDeletionLogs.created >=' => $from]); }
        if ($to)   { $query->where(['AdminDeletionLogs.created <=' => $to . ' 23:59:59']); }

        $logs = $this->paginate($query, [
            'limit' => 25,
            'sortableFields' => ['AdminDeletionLogs.created', 'AdminDeletionLogs.fullnumber', 'AdminDeletionLogs.company_name', 'AdminDeletionLogs.deleted_by_username'],
        ]);

        $this->set(compact('logs'));
    }

    public function adminDrafts()
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $q     = trim((string)$this->request->getQuery('q'));
        $from  = $this->request->getQuery('from');
        $to    = $this->request->getQuery('to');
        $type  = $this->request->getQuery('type');

        $query = $this->Invoices->find()
            ->contain([
                'InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'name', 'nip']),
                'Companies'          => fn($q) => $q->select(['id', 'name', 'nip']),
            ])
            ->where(['Invoices.workflow_status' => 'draft']);

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($exp) use ($like) {
                return $exp->or([
                    'Invoices.fullnumber LIKE'     => $like,
                    'InvoiceContractors.name LIKE' => $like,
                    'InvoiceContractors.nip LIKE'  => $like,
                    'Companies.name LIKE'          => $like,
                ]);
            });
        }
        if ($from) { $query->where(['Invoices.created >=' => $from]); }
        if ($to)   { $query->where(['Invoices.created <=' => $to . ' 23:59:59']); }
        if ($type) { $query->where(['Invoices.type' => $type]); }

        $invoices = $this->paginate($query, [
            'limit'          => 25,
            'order'          => ['Invoices.created' => 'DESC'],
            'sortableFields' => ['Invoices.fullnumber', 'Invoices.created', 'Invoices.type', 'Companies.name', 'InvoiceContractors.name'],
        ]);

        $this->set(compact('invoices'));
    }

    public function adminDelete($id = null)
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $this->request->allowMethod(['post']);

        // Weryfikacja kodu admina przesłanego z formularza
        $code = (string)($this->request->getData('admin_code') ?? '');
        if ($code !== '1939') {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nieprawidłowy kod administratora.']));
        }

        try {
            $invoice = $this->Invoices->get($id, contain: [
                'InvoiceContractors',
                'InvoiceCompanyDetails',
                'InvoiceContents',
                'InvoicePayments',
                'Companies' => fn($q) => $q->select(['id', 'name', 'nip']),
            ]);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Faktura nie istnieje.']));
        }

        // Snapshot przed usunięciem
        $identity  = $this->request->getAttribute('identity');
        $snapshot  = json_encode($invoice->toArray(), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $company   = $invoice->company ?? null;
        $contractor = $invoice->invoice_contractor ?? null;

        try {
            $LogsTbl = $this->fetchTable('AdminDeletionLogs');
            $log = $LogsTbl->newEntity([
                'id'                  => \Cake\Utility\Text::uuid(),
                'invoice_id'          => (string)$invoice->id,
                'deleted_by_user_id'  => (string)($identity?->getIdentifier() ?? ''),
                'deleted_by_username' => (string)($identity?->get('username') ?? $identity?->get('email') ?? ''),
                'company_id'          => (string)($invoice->company_id ?? ''),
                'company_name'        => (string)($company?->name ?? ''),
                'fullnumber'          => (string)($invoice->fullnumber ?? ''),
                'invoice_type'        => (string)($invoice->type ?? ''),
                'invoice_date'        => $invoice->date,
                'total'               => $invoice->total,
                'currency'            => (string)($invoice->currency ?? 'PLN'),
                'contractor_name'     => (string)($contractor?->name ?? ''),
                'contractor_nip'      => (string)($contractor?->nip ?? ''),
                'snapshot'            => $snapshot,
            ]);
            $LogsTbl->save($log);
        } catch (\Throwable $e) {
            // log error ale nie blokuj usunięcia
        }

        if ($this->Invoices->delete($invoice)) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'message' => 'Faktura została usunięta.']));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'message' => 'Nie udało się usunąć faktury.']));
    }

    public function drafts()
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        // Filtry z query stringa
        $q     = trim((string)$this->request->getQuery('q', ''));
        $type  = trim((string)$this->request->getQuery('type', ''));
        $from  = trim((string)$this->request->getQuery('from', ''));
        $to    = trim((string)$this->request->getQuery('to', ''));
        $limit = (int)$this->request->getQuery('limit', 20);
        if ($limit < 5)   $limit = 20;
        if ($limit > 200) $limit = 200;

        $query = $this->Invoices->find()
            ->contain(['InvoiceContractors' => function($q){ return $q->select(['invoice_id','name','nip']); }])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.workflow_status' => 'draft',
            ])
            ->orderDesc('Invoices.created');

        if ($q !== '') {
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
            $query->leftJoinWith('InvoiceContractors')
                  ->andWhere(function ($exp) use ($like) {
                      return $exp->or([
                          'Invoices.fullnumber LIKE'        => $like,
                          'InvoiceContractors.name LIKE'    => $like,
                          'InvoiceContractors.nip LIKE'     => $like,
                      ]);
                  })
                  ->group(['Invoices.id']);
        }
        if ($type !== '') {
            $query->andWhere(['Invoices.type' => $type]);
        }
        if ($from !== '') {
            $query->andWhere(['Invoices.date >=' => $from]);
        }
        if ($to !== '') {
            $query->andWhere(['Invoices.date <=' => $to]);
        }

        $drafts = $this->paginate($query, ['limit' => $limit]);

        // Przekaż wartości filtrów do widoku (potrzebne do renderowania pól)
        $this->set(compact('q', 'type', 'from', 'to', 'limit'));

        // Mapa contractor_id → is_person (osoba fizyczna z katalogu) dla decyzji
        // czy pokazać przycisk "Przenieś do faktur" w drafts.php (bez KSeF).
        $contractorIds = [];
        foreach ($drafts as $d) {
            $cid = (string)($d->contractor_id ?? '');
            if ($cid !== '') { $contractorIds[$cid] = true; }
        }
        $personMap = [];
        if (!empty($contractorIds)) {
            $rows = $this->fetchTable('Contractors')->find()
                ->select(['id', 'is_person'])
                ->where(['id IN' => array_keys($contractorIds), 'company_id' => $companyId])
                ->all();
            foreach ($rows as $r) {
                $personMap[(string)$r->id] = ((int)($r->is_person ?? 0) === 1);
            }
        }

        $this->set(compact('drafts', 'personMap'));
    }

    public function promoteToIssued(string $id)
    {
        $this->request->allowMethod(['post']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $invoice = $this->Invoices->find()
            ->where(['id' => $id, 'company_id' => $companyId, 'workflow_status' => 'draft'])
            ->first();

        if ($invoice === null) {
            $this->Flash->error('Nie znaleziono faktury roboczej.');
            return $this->redirect(['action' => 'drafts']);
        }

        try {
            $this->ensureInvoiceNumberForSend($invoice, $companyId);
        } catch (\RuntimeException $e) {
            $this->Flash->error('Nie udało się nadać numeru faktury: ' . $e->getMessage());
            return $this->redirect(['action' => 'drafts']);
        }

        $invoice->set('workflow_status', 'issued');
        $this->Invoices->save($invoice);

        // Auto-wysyłka maila po wystawieniu (helper sam pilnuje reguły KSeF i auto_send)
        $this->enqueueAutoSendEmailIfEnabled($invoice, $companyId, 'promoteToIssued');

        $this->Flash->success('Faktura przeniesiona na listę faktur (nr ' . h($invoice->fullnumber) . ').');
        return $this->redirect(['action' => 'index']);
    }

    public function sendDraftNow(string $id)
    {
        $this->request->allowMethod(['post']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $invoice = $this->Invoices->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            $this->Flash->error('Nie znaleziono faktury roboczej.');
            return $this->redirect(['action' => 'drafts']);
        }
        if ((string)($invoice->workflow_status ?? '') !== 'draft') {
            $this->Flash->warning('Ta faktura nie ma statusu roboczego.');
            return $this->redirect(['action' => 'view', $id]);
        }

        return $this->sendToKsef($id);
    }

    public function scheduleDraft(string $id)
    {
        $this->request->allowMethod(['post']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $invoice = $this->Invoices->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            $this->Flash->error('Nie znaleziono faktury roboczej.');
            return $this->redirect(['action' => 'drafts']);
        }
        if ((string)($invoice->workflow_status ?? '') !== 'draft') {
            $this->Flash->warning('Ta faktura nie ma statusu roboczego.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $planned = trim((string)$this->request->getData('planned_ksef_send_at'));
        if ($planned === '') {
            $invoice->set('planned_ksef_send_at', null);
            $this->Invoices->save($invoice);
            $this->Flash->success('Usunięto datę planowanej wysyłki.');
            return $this->redirect(['action' => 'drafts']);
        }

        try {
            $date = new \DateTimeImmutable($planned);
            $invoice->set('planned_ksef_send_at', $date->format('Y-m-d'));
            $this->Invoices->save($invoice);
            $this->Flash->success('Zapisano termin planowanej wysyłki.');
        } catch (\Throwable) {
            $this->Flash->error('Nieprawidłowa data planowanej wysyłki.');
        }

        return $this->redirect(['action' => 'drafts']);
    }


    /**
     * View method
     *
     * @param string|null $id Invoice id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoice = $this->Invoices->get($id, contain: [
            'Companies',
            'ParentInvoices',
            'InvoiceCompanyDetails',
            'InvoiceContractors',
            'InvoiceContents' => ['Vats'],
            'InvoiceVatContents',
            'ChildInvoices',
            'InvoicePayments',
            'InvoiceAdditionalDescriptions',
            'InvoiceRecipients',
            'InvoiceNewTransports',
            'InvoiceCharges',
            'InvoiceFactorBanks',
            'InvoiceAuthorizedEntities',
            'InvoiceOrderLines',
        ]);

        $this->trackRecentlyViewed(
            'invoices',
            (string)$invoice->id,
            (string)($invoice->fullnumber ?? '(brak numeru)'),
            '/invoices/view/' . $invoice->id,
            (string)($invoice->invoice_contractors->name ?? $invoice->invoice_contractor->name ?? '')
        );

        // Czy faktura jest wystawiona osobie fizycznej?
        // 1) source contractor z katalogu (po contractor_id) ma is_person=1
        // 2) fallback: snapshot kontrahenta nie ma NIP (typowo osoba fizyczna)
        $isPersonInvoice = false;
        $cid = (string)($invoice->contractor_id ?? '');
        if ($cid !== '') {
            $src = $this->fetchTable('Contractors')->find()
                ->select(['id', 'is_person'])
                ->where(['id' => $cid])
                ->first();
            if ($src && (int)($src->is_person ?? 0) === 1) {
                $isPersonInvoice = true;
            }
        }
        if (!$isPersonInvoice) {
            $snapNip = trim((string)($invoice->invoice_contractors->nip ?? $invoice->invoice_contractor->nip ?? ''));
            if ($snapNip === '') {
                $isPersonInvoice = true;
            }
        }

        $this->set(compact('invoice', 'isPersonInvoice'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */

public function add(): Response
{
    $this->request->allowMethod(['get', 'post']); // GET z dropdownu, ewentualnie POST gdybyś kiedyś wrócił do jednej akcji

    // mapowanie typów na akcje:
    $type = strtolower((string)$this->request->getQuery('type', 'vat')); // domyślnie VAT
    $isDemo = (bool)(Configure::read('App.demo') ?? false);
    if ($isDemo && $type !== 'vat') {
        $this->Flash->warning('Wersja demo: dostępna tylko Faktura VAT. Przełączono.');
        return $this->redirect([
            'action' => 'addVat',
            '?'      => $this->request->getQueryParams(),
        ]);
    }
    $map  = [
        'vat'              => 'addVat',
            'currency'         => 'addCurrency',
        'novat'            => 'addNoVat',        // ← NOWE
        'proforma'         => 'addProforma',
        'advance'          => 'addAdvance',
        'correction'       => 'addCorrection',
        'margin'           => 'addMargin',
        'internal'         => 'addInternal',
        'internalevidence' => 'addInternalEvidence',
        'oss'              => 'addOss',
        'rental'           => 'addRental',
        'scheduled'        => 'addScheduled', // jeżeli planujesz w tym kontrolerze
    ];
    // debug($type);
    if (isset($map[$type])) {
        // przekaż oryginalne query paramy dalej (np. kontrahent_id)
        return $this->redirect([
            'action' => $map[$type],
            '?'      => $this->request->getQueryParams(),
        ]);
    }

    // fallback — jeśli typ nieznany, idź na VAT:
    return $this->redirect([
        'action' => $map['vat'],
        '?'      => $this->request->getQueryParams(),
    ]);
}
public function addVat(): ?Response      { return $this->handleAdd('vat'); }
public function addCurrency(): ?Response { return $this->handleAdd('currency'); }
public function addProforma(): ?Response { return $this->handleAdd('proforma'); }
public function addAdvance(): ?Response  { return $this->handleAdd('advance'); }
public function addCorrection(): ?Response { return $this->handleAdd('correction'); }
public function addMargin(): ?Response   { return $this->handleAdd('margin'); }
public function addInternal(): ?Response { return $this->handleAdd('internal'); }
public function addInternalEvidence(): ?Response { return $this->handleAdd('internalEvidence'); }
public function addOss(): ?Response      { return $this->handleAdd('oss'); }
public function addNoVat(): ?\Cake\Http\Response
{
    // własny formularz + wymuszenie zerowych stawek VAT
    return $this->handleAdd('novat', true);
}
public function addRental(): ?\Cake\Http\Response
{
    // Faktura najmu prywatnego — tylko dla firm z włączonym najmem i uzupełnionymi danymi
    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');
    if (!empty($companyId)) {
        try {
            $Companies = $this->fetchTable('Companies');
            $co = $Companies->find()->select(['rental_enabled','rental_first_name','rental_last_name'])->where(['id' => $companyId])->first();
            if (!$co || empty($co->rental_enabled) || empty(trim((string)($co->rental_first_name ?? ''))) || empty(trim((string)($co->rental_last_name ?? '')))) {
                $this->Flash->warning('Uzupełnij dane najmu prywatnego w ustawieniach firmy, aby wystawiać faktury najmu.');
                return $this->redirect(['action' => 'index']);
            }
        } catch (\Throwable) {}
    }
    return $this->handleAdd('rental', false);
}

/**
 * Zwraca ID najnowszego dokumentu w łańcuchu korekt dla danej faktury.
 * Kolejną korektę wystawiamy do AKTUALNEGO stanu (po ostatniej korekcie),
 * a nie do faktury pierwotnej — inaczej kolejna korekta miałaby błędne wartości
 * (ilość/cena/netto/VAT/brutto sprzed wcześniejszych korekt).
 * Idzie po łańcuchu parent_id → najnowsza korekta-dziecko, z zabezpieczeniem przed pętlą.
 */
private function resolveLatestCorrectionId(string $startId, string $companyId): string
{
    $current = $startId;
    $seen = [$current => true];
    for ($i = 0; $i < 50; $i++) {
        $child = $this->Invoices->find()
            ->select(['id'])
            ->where([
                'company_id' => $companyId,
                'parent_id'  => $current,
                'type'       => 'correction',
            ])
            ->orderDesc('created')
            ->orderDesc('id')
            ->first();
        if (!$child) {
            break;
        }
        $cid = (string)$child->id;
        if (isset($seen[$cid])) {
            break;
        }
        $seen[$cid] = true;
        $current = $cid;
    }
    return $current;
}

/**
 * @param string $kind    Typ dokumentu: vat|currency|novat|advance|final|correction|margin|proforma|internal|internalEvidence|oss
 * @param bool   $noVat   Wymuś zerową stawkę VAT — przekazywane TYLKO przez addNoVat(). Dla pozostałych typów zawsze false.
 *                        Nie należy mylić z typem 'novat': parametr $noVat to flaga przetwarzania, $kind to typ zapisany w bazie.
 */
private function handleAdd(string $kind, bool $noVat = false): ?\Cake\Http\Response
{
    $isDemo = (bool)(Configure::read('App.demo') ?? false);
    if ($isDemo && $kind !== 'vat') {
        $this->Flash->warning('Wersja demo: tylko wystawianie Faktury VAT jest dostępne.');
        return $this->redirect([
            'action' => 'addVat',
            '?'      => $this->request->getQueryParams(),
        ]);
    }
    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id'); // char(36)

    $Invoices = $this->fetchTable('Invoices');
    $invoice  = $Invoices->newEmptyEntity();

    // Pre-fill issuer from company on GET and set default series for proforma
    if ($this->request->is('get')) {
        try {
            $CompaniesTbl = $this->fetchTable('Companies');
            $c = $CompaniesTbl->find()->select(['issuer'])->where(['id' => $companyId])->first();
            if ($c && empty($invoice->issuer)) {
                $invoice->set('issuer', (string)($c->issuer ?? ''));
            }
        } catch (\Throwable $e) { /* ignore */ }

        // If creating a correction, try to preload original invoice and sane defaults
        if ($kind === 'correction') {
            try {
                $pass = (array)$this->request->getParam('pass', []);
                $origId = $pass[0] ?? $this->request->getQuery('parent_id') ?? $this->request->getQuery('original_id') ?? $this->request->getQuery('id');
                if (!empty($origId)) {
                    // Kolejna korekta bazuje na NAJNOWSZEJ korekcie (aktualny stan pozycji po
                    // poprzednich korektach), nie na fakturze pierwotnej.
                    $origId = $this->resolveLatestCorrectionId((string)$origId, (string)$companyId);
                    $original = $Invoices->find()
                        ->contain([
                            'InvoiceContractors',
                            'InvoiceRecipients',
                            'InvoiceContents' => ['Vats'],
                            'InvoiceNewTransports',
                            'InvoiceCharges',
                            'InvoiceFactorBanks',
                            'InvoiceAuthorizedEntities',
                            'InvoiceOrderLines',
                        ])
                        ->where(['Invoices.company_id' => $companyId, 'Invoices.id' => $origId])
                        ->first();
                    if ($original) {
                        // Preselect series same as original if not set
                        if (empty($invoice->series) && !empty($original->series)) {
                            $invoice->set('series', (string)$original->series);
                        }
                        // Prefill pól FA(3) + adnotacji z faktury pierwotnej.
                        // Formularz (FormHelper / elementy tab_*) czyta z $invoice, więc kopiujemy do niego.
                        foreach ([
                            'annotations', 'annotations_tax_free', 'annotations_tax_free_field',
                            'skonto_conditions', 'skonto_amount', 'status_info_podatnika',
                            'is_new_transport_wdt', 'p_42_5', 'transaction_conditions_json',
                            'order_total_gross', 'is_split_payment', 'is_receipt_invoice',
                            'buyer_is_jst', 'buyer_in_vat_group', 'place_of_issue', 'footer_text',
                            'payment_link', 'margin_type',
                        ] as $__f) {
                            $__v = $original->get($__f);
                            if ($invoice->get($__f) === null && $__v !== null) {
                                $invoice->set($__f, $__v);
                            }
                        }
                        // Prefill relacji FA(3) (odbiorca/inny podmiot, transport WDT, opłaty,
                        // rachunki faktora, podmioty upoważnione, pozycje zamówienia).
                        foreach ([
                            'invoice_recipient',
                            'invoice_new_transports', 'invoice_charges', 'invoice_factor_banks',
                            'invoice_authorized_entities', 'invoice_order_lines',
                        ] as $__rel) {
                            $__rv = $original->get($__rel);
                            if (!empty($__rv) && empty($invoice->get($__rel))) {
                                $invoice->set($__rel, $__rv);
                            }
                        }
                        // Pass original to the view to prefill form and items
                        $this->set('original', $original);
                        // Faktura PIERWOTNA (korzeń łańcucha) — do nagłówka „korekta do dokumentu".
                        // Wartości formularza bazują na ostatniej korekcie ($original), ale dokumentem
                        // korygowanym wg broszury jest faktura pierwotna.
                        try {
                            $this->set('correctedOriginal', $this->resolveRootOriginalInvoice($original, $Invoices));
                        } catch (\Throwable) {
                            $this->set('correctedOriginal', $original);
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Wybór domyślnej serii dla nowej faktury (delegowane do InvoiceDefaultSeriesResolver)
        try {
            $InvoiceSeries = $this->fetchTable('InvoiceSeries');
            $ser = $this->defaultSeriesResolver->resolve($InvoiceSeries, (string)$companyId, $kind);
            if ($ser && empty($invoice->series)) {
                $invoice->set('series', (string)$ser->name);
                $invoice->set('invoice_series_id', (string)$ser->id);
            }
        } catch (\Throwable $e) { /* ignore — nie blokujemy formularza */ }
    }

    // słowniki VAT – dla noVat nie musimy ładować stawek, ale jeśli chcesz mieć np. "ZW/NP", możesz załadować i ukryć w UI
    $Vats        = $this->fetchTable('Vats');
    $vatRows     = $noVat ? [] : $Vats->find()->select(['id','name','rate'])->order(['rate' => 'DESC'])->all();
    $vats        = $noVat ? [] : $vatRows->combine('id', fn($v) => (string)$v->name)->toArray();
    $vatRatesMap = $noVat ? [] : $vatRows->combine('id', 'rate')->toArray();

        if ($this->request->is('post')) {
        try {
        $data = $this->request->getData();
        $ksefModeEnabled = $this->isKsefModeEnabled((string)$companyId);
        $doSend = $ksefModeEnabled ? $this->shouldSendToKsefNow((array)$data) : false;
        // Proforma i faktura bez VAT nie trafiają do KSeF — zawsze wydawane od razu jako issued
        $saveDraftExplicit = !empty($data['save_draft']);
        $isDraftWorkflow = !in_array($kind, ['proforma', 'novat'], true) && ($saveDraftExplicit || ($ksefModeEnabled && !$doSend));
        $this->hydrateInvoiceDraftFromData($invoice, (array)$data);

        // Wstępny patch tylko pól skalarnych (bez asocjacji) — zapewnia, że przy błędzie
        // walidacji i ponownym renderowaniu formularza wszystkie pola są uzupełnione danymi
        // użytkownika. Asocjacje (invoice_contractor, invoice_contents) pozostają nienaruszone
        // bo ustawia je hydrateInvoiceDraftFromData powyżej.
        $invoice = $Invoices->patchEntity($invoice, $data, [
            'validate'   => false,
            'associated' => [],
        ]);
        // Przywróć contractor/items (patchEntity z associated:[] ich nie tyka,
        // ale wywołujemy ponownie dla pewności)
        $this->hydrateInvoiceDraftFromData($invoice, (array)$data);

        // Mapuj proforma_id → parent_id na encji (pole POST vs kolumna DB)
        if ($kind === 'advance' && !empty($data['proforma_id'])) {
            $invoice->set('parent_id', $data['proforma_id']);
        }

        // Ensure parent binding for corrections
        if ($kind === 'correction') {
            $pass = (array)$this->request->getParam('pass', []);
            $parentFromRoute = $pass[0] ?? null;
            $parentFromQuery = $this->request->getQuery('parent_id') ?? $this->request->getQuery('original_id') ?? $this->request->getQuery('id');
            if (empty($data['parent_id'])) {
                $data['parent_id'] = $parentFromRoute ?? $parentFromQuery ?? null;
            }
        }
        
        // Debug: sprawdź przesłane dane
        \Cake\Log\Log::debug('Invoice form data: ' . json_encode($data));

        // Map correction fields (reason/type)
        if ($kind === 'correction') {
            $invoice->set('correction_reason', (string)($data['correction_reason'] ?? ''));
            $invoice->set('correction_type', in_array((string)($data['correction_type'] ?? ''), ['1','2','3']) ? (string)$data['correction_type'] : ($invoice->correction_type ?? null));
        }

        // parser liczb
        $num = static function($val): float {
            $s = str_replace([' ', ','], ['', '.'], (string)$val);
            return is_numeric($s) ? (float)$s : 0.0;
        };

        // pozycje / advance mode
        $items      = (array)($data['items'] ?? []);
        $contents   = [];
        $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;

        $vatBuckets = []; // Grupowanie VAT

    // Flaga końcowości – domyślnie false; dla innych typów pozostaje false
    $isFinal = false;

    // Korekta faktury marży: pozycje muszą być budowane jak w marży (bez VAT, vat_code_id=null,
    // netto=brutto), inaczej generyczna ścieżka dodaje im błędnie stawkę 0%.
    // Sygnał: korekta + formularz marży (margin_type / margin_vat_rate).
    $marginCorrection = ($kind === 'correction')
        && (!empty($data['margin_type']) || array_key_exists('margin_vat_rate', $data));

    if ($kind === 'margin' || $marginCorrection) {
            // Procedura marży: pozycje zawierają WARTOŚĆ BRUTTO (sprzedaż) oraz CENA NABYCIA (BRUTTO) tylko do wyliczeń
            $totalSales = 0.0; $totalPurchase = 0.0;
            foreach ($items as $idx => $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    if ($this->rowHasUserData((array)$row)) {
                        throw new \RuntimeException('Pozycja #' . ((int)$idx + 1) . ': uzupełnij nazwę produktu/usługi.');
                    }
                    continue;
                }

                $qty        = $num($row['quantity'] ?? 0);
                $saleUnit   = $num($row['price'] ?? 0);           // brutto/szt.
                $buyUnit    = $num($row['purchase_price'] ?? 0);  // brutto/szt. (wewnętrzne)
                $lineGross  = round($qty * $saleUnit, 2);
                $lineBuy    = round($qty * $buyUnit, 2);

                $totalSales    += $lineGross;
                $totalPurchase += $lineBuy;

                // Zapisujemy pozycję bez stawki VAT; netto=brutto ponieważ na pozycji nie wykazujemy VAT (marża)
                $contents[] = [
                    'vat_code_id'      => null,
                    'name'             => $name,
                    'product_desc'     => (string)($row['product_desc'] ?? ''),
                    'quantity'         => $qty,
                    'unit'             => (string)($row['unit'] ?? 'szt.'),
                    'price'            => $saleUnit,
                    'purchase_price'   => $buyUnit,
                    'discount_percent' => 0,
                    'netto'            => $lineGross,
                    'brutto'           => $lineGross,
                    // FA(3)
                    'uu_id'            => (string)($row['uu_id'] ?? \Cake\Utility\Text::uuid()),
                    'line_date'        => !empty($row['line_date']) ? $row['line_date'] : null,
                    'pkwiu'            => (string)($row['pkwiu'] ?? ''),
                ];
            }

            // Walidacja procedury marży
            if ($totalPurchase <= 0.0) {
                $this->Flash->error('Faktura marżowa wymaga podania ceny nabycia (pola „Cena nabycia") dla co najmniej jednej pozycji.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render($marginCorrection ? 'add_correct_margin' : 'add_margin');
                return null;
            }

            // Stawka VAT z zakładki Księgowe (ukryta) – 23% domyślnie; dla sztuki 8%
            $rate = (float)($data['margin_vat_rate'] ?? 23);
            if ($rate < 0.0 || $rate > 100.0) {
                $rate = 23.0; // fallback do bezpiecznej wartości
            }
            $marginGross = max(0.0, $totalSales - $totalPurchase);
            $sumTax   = $rate > 0 ? round($marginGross * ($rate / (100.0 + $rate)), 2) : 0.0; // VAT tylko od marży
            $sumGross = round($totalSales, 2);
            $sumNet   = round($sumGross - $sumTax, 2); // w ujęciu księgowym: netto = total - VAT

            // Adnotacja o procedurze marży do opisu faktury — DOKŁADNY zwrot wymagany
            // przez broszurę FA(3) (np. "procedura marży - towary używane"), wspólna mapa z XML.
            $marginType = (string)($data['margin_type'] ?? '');
            $procedure  = $this->marginProcedureMap()[$marginType] ?? null;
            if ($procedure !== null) {
                $note = $procedure['phrase'] . '.';
                $existing = trim((string)($data['description'] ?? ''));
                if ($existing === '') { $data['description'] = $note; }
                elseif (stripos($existing, 'procedura marży') === false) { $data['description'] = $existing . "\n" . $note; }
            }

    } elseif ($kind === 'advance') {
            // Build a single advance line from posted advance_gross and selected VAT
            $proformaId = $data['proforma_id'] ?? null;
            if (!$proformaId) {
                $this->Flash->error('Wybierz proformę/ofertę do wystawienia zaliczki.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            $advanceGross = $num($data['advance_gross'] ?? 0);
            $isFinalExplicitEarly = !empty($data['is_final']) && (int)$data['is_final'] === 1;
            if ($advanceGross < 0) {
                $this->Flash->error('Kwota zaliczki nie może być ujemna (podano: ' . number_format($advanceGross, 2, ',', ' ') . ').');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            // Kwota 0 dozwolona TYLKO dla faktury rozliczeniowej/końcowej (rozliczenie 100% zaliczek).
            // Dla zwykłej końcowej z kwotą 0 błąd złapią dalsze guardy (kwota = pozostała).
            if ($advanceGross == 0.0 && !$isFinalExplicitEarly) {
                $this->Flash->error('Kwota zaliczki musi być większa od zera.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            $Proformas = $this->fetchTable('Invoices');
            $proforma  = $Proformas->find()
                ->contain(['InvoiceContractors', 'InvoiceContents' => ['Vats']])
                ->where(['Invoices.id' => $proformaId, 'Invoices.company_id' => $companyId, 'Invoices.type' => 'proforma'])
                ->first();
            if (!$proforma) {
                $this->Flash->error('Nie znaleziono wskazanej proformy/oferty.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            // sum of prior advances/final for this proforma
            $sumAdvances = (float)$Proformas->find()
                ->select(['s' => $Proformas->find()->func()->sum('total')])
                ->where([
                    'company_id' => $companyId,
                    'parent_id' => $proformaId,
                    'type IN' => ['advance','final']
                ])
                ->enableHydration(false)
                ->first()['s'] ?? 0.0;
            $remainingToSettle = round(max(0.0, ((float)$proforma->total) - $sumAdvances), 2);
            $hasFinal = (bool)$Proformas->find()
                ->select(['id'])
                ->where([
                    'company_id' => $companyId,
                    'parent_id' => $proformaId,
                    'type' => 'final'
                ])->limit(1)->count();
            // ensure currency, contractor snapshot
            $data['currency'] = (string)($proforma->currency ?? ($data['currency'] ?? 'PLN'));
            // Fill contractor snapshot if not sent from form (or sent empty)
            if (empty($data['invoice_contractor']['name'])) {
                $data['invoice_contractor'] = [
                    'name'           => (string)($proforma->invoice_contractor?->name ?? ''),
                    'nip'            => (string)($proforma->invoice_contractor?->nip ?? ''),
                    'street'         => (string)($proforma->invoice_contractor?->street ?? ''),
                    'zip'            => (string)($proforma->invoice_contractor?->zip ?? ''),
                    'city'           => (string)($proforma->invoice_contractor?->city ?? ''),
                    'country'        => (string)($proforma->invoice_contractor?->country ?? 'PL'),
                    'account_number' => (string)($proforma->invoice_contractor?->account_number ?? ''),
                    'email'          => (string)($proforma->invoice_contractor?->email ?? '') ?: null,
                    'phone'          => (string)($proforma->invoice_contractor?->phone ?? '') ?: null,
                ];
                // Fallback: proforma has no snapshot → load from live Contractors table
                if (empty($data['invoice_contractor']['name']) && !empty($proforma->contractor_id)) {
                    $LiveContractors = $this->fetchTable('Contractors');
                    $liveCtr = $LiveContractors->find()
                        ->select(['name','nip','street','city','postal_code','country','email','phone','vat_prefix','vat_eu','eori','tax_id_other','tax_id_other_country'])
                        ->where(['id' => $proforma->contractor_id, 'company_id' => $companyId])
                        ->first();
                    if ($liveCtr) {
                        $data['invoice_contractor'] = [
                            'name'                 => (string)($liveCtr->name ?? ''),
                            'nip'                  => (string)($liveCtr->nip ?? ''),
                            'street'               => (string)($liveCtr->street ?? ''),
                            'zip'                  => (string)($liveCtr->postal_code ?? ''),
                            'city'                 => (string)($liveCtr->city ?? ''),
                            'country'              => (string)($liveCtr->country ?? 'PL'),
                            'account_number'       => (string)($liveCtr->account_number ?? ''),
                            'email'                => $liveCtr->email ?: null,
                            'phone'                => $liveCtr->phone ?: null,
                            'vat_prefix'           => $liveCtr->vat_prefix ?: null,
                            'vat_eu'               => $liveCtr->vat_eu ?: null,
                            'eori'                 => $liveCtr->eori ?: null,
                            'tax_id_other'         => $liveCtr->tax_id_other ?: null,
                            'tax_id_other_country' => $liveCtr->tax_id_other_country ?: null,
                        ];
                    }
                }
            }
            // ── Buduj grupy stawkowe z pozycji proformy (VAT, GTU, nazwy) ──────────────
            // Proforma jest źródłem prawdy: VAT, GTU i nazwy pozycji zaliczki
            // pochodzą stąd, nie z formularza.
            $proformaItems    = (array)($proforma->invoice_contents ?? []);
            $rateGroups       = [];
            $proformaBruttoTotal = 0.0;
            foreach ($proformaItems as $pi) {
                $piVatCodeId = $pi->vat_code_id;
                $piBrutto    = (float)($pi->brutto ?? 0);
                $piRate      = isset($pi->vat) ? (float)$pi->vat->rate : 0.0;
                $rateKey     = $piVatCodeId !== null ? (string)$piVatCodeId : ('zw_' . $piRate);
                if (!isset($rateGroups[$rateKey])) {
                    $rateGroups[$rateKey] = [
                        'vat_code_id' => $piVatCodeId,
                        'rate'        => $piRate,
                        'brutto'      => 0.0,
                        'names'       => [],
                        'gtu'         => '',
                    ];
                }
                $rateGroups[$rateKey]['brutto'] += $piBrutto;
                $proformaBruttoTotal += $piBrutto;
                $piName = trim((string)($pi->name ?? ''));
                if ($piName !== '') { $rateGroups[$rateKey]['names'][] = $piName; }
                if ($rateGroups[$rateKey]['gtu'] === '') {
                    $piGtu = trim((string)($pi->gtu_code ?? ''));
                    if ($piGtu !== '') { $rateGroups[$rateKey]['gtu'] = $piGtu; }
                }
            }

            // Fallback nazwy gdy proforma nie ma pozycji z nazwą
            $lineNameFallback = ($isFinal ? 'Faktura końcowa do zamówienia ' : 'Zaliczka do zamówienia ')
                . (string)($proforma->fullnumber ?? $proforma->id);

            // VAT code dla single-rate (multi-rate: każda grupa ma swój vat_code_id)
            $firstGroup = !empty($rateGroups) ? reset($rateGroups) : null;
            $vatCodeId  = $firstGroup['vat_code_id'] ?? null;
            $rate       = $noVat ? 0.0 : (float)($vatRatesMap[$vatCodeId] ?? 0);
            // Oblicz netto/tax/brutto z kwoty zaliczki i stawki pierwszej grupy
            $netto  = $rate > 0 ? round($advanceGross / (1 + $rate/100), 2) : round($advanceGross, 2);
            $tax    = round($advanceGross - $netto, 2);
            $brutto = round($advanceGross, 2);

            // Opcjonalna aktualizacja kwoty proformy gdy zaliczka/końcowa ją przekracza
            if (!empty($data['update_proforma_total']) && (int)$data['update_proforma_total'] === 1) {
                $newProformaTotal = round((float)($data['new_proforma_total'] ?? 0), 2);
                if ($newProformaTotal > (float)$proforma->total && $newProformaTotal >= $brutto) {
                    $proforma->set('total', $newProformaTotal);
                    $proforma->set('remaining', $newProformaTotal - $sumAdvances);
                    $Proformas->save($proforma, ['checkRules' => false, 'validate' => false]);
                    $remainingToSettle = round(max(0.0, $newProformaTotal - $sumAdvances), 2);
                }
            }

            // Validate against remaining (prevent overpayment)
            $isFinalExplicit = !empty($data['is_final']) && (int)$data['is_final'] === 1;
            $shouldBeFinalByAmount = ($remainingToSettle > 0.0) && (abs($brutto - $remainingToSettle) <= 0.01) && ($brutto > 0.0);
            // UWAGA: NIE oznaczamy automatycznie faktury jako końcowej przy 100% wpłaty.
            // Sama zapłata 100% nie oznacza wydania towaru/wykonania usługi — decyduje użytkownik
            // (radio „Czy towar wydany/usługa wykonana?" → pole is_final). Patrz add_advance.php.
            $isFinal = $isFinalExplicit;

            // Faktura rozliczeniowa/końcowa po 100% zaliczek (art. 106f ust. 3 ustawy):
            // gdy wcześniejsze zaliczki pokrywają już całość, dopuszczamy późniejszą fakturę
            // rozliczeniową o kwocie pozostałej do zapłaty = 0 (P_15=0). Wymaga jawnego is_final=1.
            $priorAdvancesCover100 = ($remainingToSettle <= 0.0) && ($sumAdvances > 0.0);
            $isZeroSettlement      = $isFinal && $priorAdvancesCover100;

            if ($hasFinal) {
                $this->Flash->error('Faktura końcowa została już wystawiona dla tej oferty. Nie można wystawiać kolejnych dokumentów do tej oferty.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            if ($remainingToSettle <= 0.0 && !$isZeroSettlement) {
                $this->Flash->error('Proforma została już w całości rozliczona. Aby rozliczyć zaliczki, wystaw fakturę rozliczeniową/końcową.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            if ($brutto - $remainingToSettle > 0.01) {
                $this->Flash->error('Kwota zaliczki przekracza pozostałą do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . '). Zaznacz opcję aktualizacji wartości oferty.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            // Dla zwykłej końcowej kwota = pozostała do rozliczenia. Wyjątek: faktura rozliczeniowa
            // po 100% zaliczek (kwota pozostała = 0) — wtedy brutto=0 jest poprawne.
            if ($isFinal && !$shouldBeFinalByAmount && !$isZeroSettlement) {
                $this->Flash->error('Dla faktury końcowej kwota musi równać się pozostałej do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . ').');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            // Faktura rozliczeniowa/końcowa wymaga daty dokonania dostawy / wykonania usługi (data sprzedaży).
            // Faktura zaliczkowa (także 100%) NIE wymaga tej daty.
            if ($isFinal && empty($data['sold_date'])) {
                $this->Flash->error('Dla faktury rozliczeniowej/końcowej podaj datę dokonania dostawy towarów albo wykonania usługi.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }

            // ── Buduj pozycje faktury zaliczkowej ────────────────────────────────────
            $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;

            // Pozycje proformy z dodatnim brutto — do proporcjonalnego podziału kwoty zaliczki.
            $splitItems = [];
            foreach ($proformaItems as $pi) {
                if ((float)($pi->brutto ?? 0) > 0.0) { $splitItems[] = $pi; }
            }

            if (empty($splitItems) || $proformaBruttoTotal <= 0.0) {
                // Fallback: brak pozycji proformy z wartością — jedna linia zbiorcza
                $allNames = [];
                foreach ($rateGroups as $g) {
                    foreach ($g['names'] as $n) { $allNames[] = $n; }
                }
                $lineName = implode(', ', array_unique(array_filter($allNames)));
                if ($lineName === '') { $lineName = $lineNameFallback; }
                $lineGtu  = $firstGroup['gtu'] ?? '';

                $contents[] = [
                    'vat_code_id'      => $noVat ? null : $vatCodeId,
                    'name'             => $lineName,
                    'product_desc'     => '',
                    'quantity'         => 1,
                    'unit'             => 'szt.',
                    'price'            => $netto,
                    'discount_percent' => 0,
                    'netto'            => $netto,
                    'brutto'           => $brutto,
                    'vat_amount'       => $noVat ? null : $tax,
                    'gtu_code'         => $lineGtu !== '' ? $lineGtu : null,
                ];
                $sumNet = $netto; $sumTax = $tax; $sumGross = $brutto;
                $bucketKey = $vatCodeId ?: 'no_vat';
                $vatBuckets[$bucketKey] = ['vat_code_id' => $vatCodeId, 'netto' => $netto, 'tax' => $tax, 'brutto' => $brutto];
            } else {
                // Jedna linia faktury zaliczkowej na KAŻDĄ pozycję proformy.
                // Kwota zaliczki dzielona proporcjonalnie wg udziału brutto pozycji w sumie proformy
                // (podział wg stawek zachowany — każda pozycja niesie własną stawkę; art. 106f ust. 1 pkt 3).
                $remaining = $brutto;
                $cnt = count($splitItems);
                foreach ($splitItems as $idx => $pi) {
                    $isLast     = ($idx === $cnt - 1);
                    $piBrutto   = (float)($pi->brutto ?? 0);
                    $lineBrutto = $isLast
                        ? round($remaining, 2)
                        : round($brutto * ($piBrutto / $proformaBruttoTotal), 2);
                    $remaining -= $lineBrutto;

                    $lineRate  = $noVat ? 0.0 : (isset($pi->vat) ? (float)$pi->vat->rate : 0.0);
                    $lineNetto = $lineRate > 0 ? round($lineBrutto / (1 + $lineRate / 100), 2) : round($lineBrutto, 2);
                    $lineTax   = round($lineBrutto - $lineNetto, 2);

                    $piName      = trim((string)($pi->name ?? '')) ?: $lineNameFallback;
                    $piGtu       = trim((string)($pi->gtu_code ?? ''));
                    $piVatCodeId = $noVat ? null : $pi->vat_code_id;

                    $contents[] = [
                        'vat_code_id'      => $piVatCodeId,
                        'name'             => $piName,
                        'product_desc'     => '',
                        'quantity'         => 1,
                        'unit'             => 'szt.',
                        'price'            => $lineNetto,
                        'discount_percent' => 0,
                        'netto'            => $lineNetto,
                        'brutto'           => $lineBrutto,
                        'vat_amount'       => $noVat ? null : $lineTax,
                        'gtu_code'         => $piGtu !== '' ? $piGtu : null,
                    ];
                    $sumNet   += $lineNetto;
                    $sumTax   += $lineTax;
                    $sumGross += $lineBrutto;

                    // VAT bucket per stawkę — agreguj pozycje o tej samej stawce
                    $bucketKey = $piVatCodeId ?: ('no_vat_' . $lineRate);
                    if (!isset($vatBuckets[$bucketKey])) {
                        $vatBuckets[$bucketKey] = ['vat_code_id' => $piVatCodeId, 'netto' => 0.0, 'tax' => 0.0, 'brutto' => 0.0];
                    }
                    $vatBuckets[$bucketKey]['netto']  += $lineNetto;
                    $vatBuckets[$bucketKey]['tax']    += $lineTax;
                    $vatBuckets[$bucketKey]['brutto'] += $lineBrutto;
                }
            }

            // Bind parent proforma
            $data['parent_id'] = $proforma->id;

            // If marking as final: append references to previous advances into description
            if ($isFinal) {
                // Collect previous advances (excluding this one being created)
                $prev = $Proformas->find()
                    ->select(['fullnumber'])
                    ->where([
                        'company_id' => $companyId,
                        'parent_id' => $proformaId,
                        'type' => 'advance'
                    ])
                    ->orderAsc('date')
                    ->all();
                $nums = [];
                foreach ($prev as $adv) {
                    $fn = trim((string)($adv->fullnumber ?? ''));
                    if ($fn !== '') { $nums[] = $fn; }
                }
                if (!empty($nums)) {
                    $append = 'Rozlicza zaliczki: ' . implode(', ', $nums) . '.';
                    $existing = trim((string)($data['description'] ?? ''));
                    // Deduplikacja: nie dopisuj jeśli identyczny fragment już istnieje
                    if (str_contains($existing, $append) === false) {
                        $data['description'] = $existing !== '' ? ($existing . "\n" . $append) : $append;
                    }
                }
            }
        } else {
            foreach ($items as $idx => $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    if ($this->rowHasUserData((array)$row)) {
                        throw new \RuntimeException('Pozycja #' . ((int)$idx + 1) . ': uzupełnij nazwę produktu/usługi.');
                    }
                    continue;
                }

                $qty       = $num($row['quantity'] ?? 0);
                $price     = $num($row['price'] ?? 0);
                $disc      = $num($row['discount_percent'] ?? 0);

                $vatCodeId = $row['vat_code_id'] ?? null;
                // [noVAT] stawka zawsze 0
                $rate      = $noVat ? 0.0 : (float)($vatRatesMap[$vatCodeId] ?? 0);

                // price_mode: 'gross' → price to cena brutto/szt., obróć do netto
                $priceIsGross = !$noVat && $rate > 0 && (($row['price_mode'] ?? 'net') === 'gross');
                if ($priceIsGross) {
                    $netUnitPrice  = round($price / (1 + $rate / 100), 2);
                    $brutto        = round($qty * $price * (1 - ($disc / 100)), 2);
                    $netto         = round($brutto / (1 + $rate / 100), 2);
                    $tax           = round($brutto - $netto, 2);
                } else {
                    $netUnitPrice  = $price;
                    $unitAfterDisc = $price * (1 - ($disc / 100));
                    $netto  = round($qty * $unitAfterDisc, 2);
                    $tax    = $noVat ? 0.0 : round($netto * ($rate / 100), 2);
                    $brutto = round($netto + $tax, 2);
                }

                $sumNet   += $netto;
                $sumTax   += $tax;
                $sumGross += $brutto;

                // Struktura zgodna z tabelą invoice_contents
                $contents[] = [
                    'vat_code_id'      => $noVat ? null : $vatCodeId,
                    'name'             => $name,
                    'product_desc'     => (string)($row['product_desc'] ?? ''),
                    'quantity'         => $qty,
                    'unit'             => (string)($row['unit'] ?? 'szt.'),
                    'price'            => $netUnitPrice,  // zawsze netto/szt.
                    'purchase_price'   => !empty($row['purchase_price']) ? $num($row['purchase_price']) : null,
                    'discount_percent' => $disc,
                    'discount_amount'  => $disc > 0 ? round($qty * $netUnitPrice * ($disc / 100), 2) : null,
                    'netto'            => $netto,
                    'brutto'           => $brutto,
                    'gtu_code'         => (string)($row['gtu_code'] ?? ''),
                    // Dodatkowe pola pozycji
                    'gtin'             => (string)($row['gtin'] ?? ''),
                    'cn_code'          => (string)($row['cn_code'] ?? ''),
                    'pkob'             => (string)($row['pkob'] ?? ''),
                    'is_attachment15'  => !empty($row['is_attachment15']) ? 1 : 0,
                    'excise_amount'    => !empty($row['excise_amount']) ? (float)$row['excise_amount'] : null,
                    'procedure_marking' => (string)($row['procedure_marking'] ?? ''),
                    // FA(3) — pola pozycji
                    'uu_id'            => (string)($row['uu_id'] ?? \Cake\Utility\Text::uuid()),
                    'vat_amount'       => $noVat ? null : $tax, // = brutto - netto (tryb brutto) / round(netto*stawka) (tryb netto) — spójne z brutto i P_15
                    'line_date'        => !empty($row['line_date']) ? $row['line_date'] : null,
                    'pkwiu'            => (string)($row['pkwiu'] ?? ''),
                    'gross_unit_price' => $priceIsGross ? round($price, 2) : round($netUnitPrice * (1 + ($rate / 100)), 2),
                ];
                
                // Grupowanie VAT
                $bucketKey = $vatCodeId ?: 'no_vat';
                if (!isset($vatBuckets[$bucketKey])) {
                    $vatBuckets[$bucketKey] = [
                        'vat_code_id' => $vatCodeId,
                        'netto' => 0.0,
                        'tax' => 0.0,
                        'brutto' => 0.0,
                    ];
                }
                $vatBuckets[$bucketKey]['netto'] += $netto;
                $vatBuckets[$bucketKey]['tax'] += $tax;
                $vatBuckets[$bucketKey]['brutto'] += $brutto;
            }
        }

        if (empty($contents)) {
            $this->Flash->error('Dodaj co najmniej jedną pozycję.');
            $this->set(compact('invoice','vats','vatRatesMap','kind'));
            if ($kind === 'correction') {
                $origType = isset($original) ? (string)($original->type ?? '') : '';
                if ($origType === 'novat')    { $this->render('add_correct_no_vat'); }
                elseif ($origType === 'margin')   { $this->render('add_correct_margin'); }
                elseif ($origType === 'currency') { $this->render('add_correct_currency'); }
                else                              { $this->render('add_correct'); }
            } else {
                $templateMap = [
                    'novat'    => 'add_no_vat',
                    'rental'   => 'add_rental',
                    'advance'  => 'add_advance',
                    'final'    => 'add_advance',
                    'proforma' => 'add_proforma',
                    'margin'   => 'add_margin',
                    'currency' => 'add_currency',
                ];
                $this->render($templateMap[$kind] ?? 'add');
            }
            return null;
        }

    // podsumowania
        $alreadypaid = $num($data['alreadypaid'] ?? 0);
        $total       = round($sumGross, 2);
        $netto       = round($sumNet, 2);
        $tax         = $noVat ? 0.0 : round($sumTax, 2); // [noVAT]
        $remaining   = round($total - $alreadypaid, 2);

        // status płatności
        $paymentstate = 'unpaid';
        if ($remaining <= 0.0) {
            $paymentstate = 'paid';
        } elseif ($alreadypaid > 0.0) {
            $paymentstate = 'partial';
        }
        if (!empty($data['paymentdate']) && $paymentstate !== 'paid') {
            try {
                $due = new \DateTimeImmutable((string)$data['paymentdate']);
                $today = new \DateTimeImmutable('today');
                if ($due < $today) $paymentstate = 'overdue';
            } catch (\Throwable) { /* ignore */ }
        }
        // Faktura zaliczkowa/końcowa: zaznaczony checkbox "advance_paid" = opłacona.
        // Sam checkbox wystarcza — datę otrzymania zaliczki domyślamy na datę wystawienia,
        // gdy pole puste (wcześniej wymóg obu pól powodował, że zaznaczenie bez daty nic nie robiło).
        if (in_array($kind, ['advance', 'final'], true) && !empty($data['advance_paid'])) {
            $paymentstate = 'paid';
            $alreadypaid  = $total;
            $remaining    = 0.0;
            if (empty($data['advance_received_date'])) {
                $data['advance_received_date'] = !empty($data['date']) ? $data['date'] : date('Y-m-d');
            }
            if (empty($data['paid_at'])) {
                $data['paid_at'] = $data['advance_received_date'];
            }
            // partial_paid_at używana przez buildPaymentXml jako DataZaplaty
            if (empty($data['partial_paid_at'])) {
                $data['partial_paid_at'] = $data['advance_received_date'];
            }
        }

        // VAT grouped rows
        $vatContents = [];
        foreach ($vatBuckets as $bucket) {
            $vatContents[] = [
                'vat_code_id' => $noVat ? null : $bucket['vat_code_id'],
                'netto'       => round($bucket['netto'], 2),
                'tax'         => $noVat ? 0.0 : round($bucket['tax'], 2),
                'brutto'      => round($bucket['brutto'], 2),
            ];
        }

        // Walidacja danych
        // Znajdź serię — preferuj UUID (invoice_series_id), fallback na nazwę (series)
        $InvoiceSeriesTable = $this->fetchTable('InvoiceSeries');
        $series = null;

        $seriesUuid = trim((string)($data['invoice_series_id'] ?? ''));
        $seriesName = trim((string)($data['series'] ?? ''));

        if ($seriesUuid !== '') {
            // Nowy path: UUID z Select2
            $series = $InvoiceSeriesTable->find()
                ->where(['InvoiceSeries.id' => $seriesUuid, 'InvoiceSeries.company_id' => $companyId])
                ->first();
        }

        if (!$series && $seriesName !== '') {
            // Legacy path: lookup po nazwie (backward compat)
            $series = $InvoiceSeriesTable->find()
                ->where(['InvoiceSeries.company_id' => $companyId, 'InvoiceSeries.name' => $seriesName])
                ->first();
        }

        if (!$series) {
            $this->Flash->error('Seria faktury jest wymagana lub nieprawidłowa.');
            $this->set(compact('invoice','vats','vatRatesMap','kind'));
            if ($kind === 'correction') {
                $origType = isset($original) ? (string)($original->type ?? '') : '';
                if ($origType === 'novat')        { $this->render('add_correct_no_vat'); }
                elseif ($origType === 'margin')   { $this->render('add_correct_margin'); }
                elseif ($origType === 'currency') { $this->render('add_correct_currency'); }
                else                              { $this->render('add_correct'); }
            } else {
                $renderView = match($kind) {
                    'novat'    => 'add_no_vat',
                    'rental'   => 'add_rental',
                    'margin'   => 'add_margin',
                    'currency' => 'add_currency',
                    'proforma' => 'add_proforma',
                    'advance', 'final' => 'add_advance',
                    default    => 'add',
                };
                $this->render($renderView);
            }
            return null;
        }

        // Wygeneruj numer faktury jeśli nie podano (z wyjątkiem draftu)
        if (!$isDraftWorkflow && empty($data['fullnumber'])) {
            $issueDate = $data['date'] ?: date('Y-m-d');
            $dateObject = new \DateTime($issueDate);
            $year = $dateObject->format('Y');
            $month = $dateObject->format('m');
            
            // Debug informacji o serii
            \Cake\Log\Log::debug('Invoice numbering - Series info: ' . json_encode([
                'series_id' => $series->id,
                'series_name' => $series->name,
                'starting_number' => $series->starting_number,
                'template' => $series->series_template,
                'period_id' => $series->invoice_series_period_id ?? 'N/A'
            ]));
            
            // Pobierz informację o okresie numeracji jeśli nie została jeszcze załadowana
            if (!isset($series->invoice_series_period)) {
                $series = $InvoiceSeriesTable->find()
                    ->contain(['InvoiceSeriesPeriods'])
                    ->where(['InvoiceSeries.id' => $series->id])
                    ->first();
            }
            
            // Przygotuj warunki wyszukiwania ostatniej faktury
            $whereConditions = [
                'company_id' => $companyId,
                'invoice_series_id' => $series->id
            ];
            
            $periodType = 'continuous'; // domyślnie ciągłe
            
            if ($series && $series->invoice_series_period) {
                $periodName = $series->invoice_series_period->name ?? '';
                
                \Cake\Log\Log::debug('Period name: ' . $periodName);
                
                if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
                    // Miesięczne - dodaj warunki na rok i miesiąc
                    $periodType = 'monthly';
                    $whereConditions['year'] = (int)$year;
                    $whereConditions['month'] = (int)$month;
                    \Cake\Log\Log::debug('Using monthly period for year: ' . $year . ', month: ' . $month);
                } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
                    // Roczne - dodaj warunek na rok
                    $periodType = 'yearly';
                    $whereConditions['year'] = (int)$year;
                    \Cake\Log\Log::debug('Using yearly period for year: ' . $year);
                } else {
                    \Cake\Log\Log::debug('Using continuous period');
                }
            } else {
                \Cake\Log\Log::debug('No period found, using continuous numbering');
            }
            
            // Znajdź ostatnią fakturę w odpowiednim okresie
            \Cake\Log\Log::debug('WHERE conditions: ' . json_encode($whereConditions));
            \Cake\Log\Log::debug('Period type: ' . $periodType);
            
            $query = $Invoices->find()->where($whereConditions);
            
            // Jeśli mamy pola year/month w bazie, używamy ich; jeśli nie, używamy funkcji SQL
            if ($periodType === 'monthly') {
                // Sprawdź czy pola year/month istnieją, jeśli nie - użyj funkcji SQL
                try {
                    // Spróbuj użyć pól year/month jeśli istnieją
                    $testQuery = $Invoices->find()->where(['year IS NOT' => null])->limit(1)->first();
                    if ($testQuery) {
                        // Pola year/month istnieją w bazie
                        \Cake\Log\Log::debug('Using year/month fields from database');
                    } else {
                        // Pola nie istnieją lub są puste - użyj funkcji SQL
                        \Cake\Log\Log::debug('Using SQL functions for year/month');
                        unset($whereConditions['year'], $whereConditions['month']);
                        $query = $Invoices->find()
                            ->where($whereConditions)
                            ->where(function($exp) use ($year, $month) {
                                return $exp
                                    ->eq('YEAR(date)', $year)
                                    ->eq('MONTH(date)', $month);
                            });
                    }
                } catch (\Exception $e) {
                    // Fallback - użyj funkcji SQL
                    \Cake\Log\Log::debug('Fallback to SQL functions: ' . $e->getMessage());
                    unset($whereConditions['year'], $whereConditions['month']);
                    $query = $Invoices->find()
                        ->where($whereConditions)
                        ->where(function($exp) use ($year, $month) {
                            return $exp
                                ->eq('YEAR(date)', $year)
                                ->eq('MONTH(date)', $month);
                        });
                }
            } elseif ($periodType === 'yearly') {
                // Podobnie dla rocznego
                try {
                    $testQuery = $Invoices->find()->where(['year IS NOT' => null])->limit(1)->first();
                    if ($testQuery) {
                        \Cake\Log\Log::debug('Using year field from database');
                    } else {
                        \Cake\Log\Log::debug('Using SQL function for year');
                        unset($whereConditions['year']);
                        $query = $Invoices->find()
                            ->where($whereConditions)
                            ->where(function($exp) use ($year) {
                                return $exp->eq('YEAR(date)', $year);
                            });
                    }
                } catch (\Exception $e) {
                    \Cake\Log\Log::debug('Fallback to SQL function for year: ' . $e->getMessage());
                    unset($whereConditions['year']);
                    $query = $Invoices->find()
                        ->where($whereConditions)
                        ->where(function($exp) use ($year) {
                            return $exp->eq('YEAR(date)', $year);
                        });
                }
            }
            
            $lastInvoice = $query
                ->order(['number' => 'DESC', 'id' => 'DESC'])
                ->first();
            
            // Jednorazowy override numeru (np. przy migracji z innego systemu)
            $overrideNext = $series->override_next_number ?? null;
            if ($overrideNext !== null && (int)$overrideNext > 0) {
                $nextNumber = (int)$overrideNext;
                $Invoices->getConnection()->execute(
                    'UPDATE invoice_series SET override_next_number = NULL WHERE id = ?',
                    [$series->id]
                );
                \Cake\Log\Log::debug('Using override_next_number=' . $nextNumber . ' for series ' . $series->name . ', clearing after use');
            } elseif ($lastInvoice) {
                // Znaleziono fakturę w bieżącym okresie - kontynuuj numerację
                if (isset($lastInvoice->number) && $lastInvoice->number > 0) {
                    $extractedNumber = $lastInvoice->number;
                } else {
                    $extractedNumber = $this->extractNumberFromFullnumber($lastInvoice->fullnumber);
                }
                $nextNumber = $extractedNumber + 1;
                \Cake\Log\Log::debug('Found last invoice in period: ID=' . $lastInvoice->id . ', fullnumber=' . $lastInvoice->fullnumber . ', extracted=' . $extractedNumber . ', next=' . $nextNumber);
            } else {
                // Brak faktur w bieżącym okresie - rozpocznij od numeru startowego
                $nextNumber = $series->starting_number ?: 1;
                
                if ($periodType === 'monthly') {
                    \Cake\Log\Log::debug('No invoice found in current month (' . $year . '-' . $month . '), starting from: ' . $nextNumber);
                } elseif ($periodType === 'yearly') {
                    \Cake\Log\Log::debug('No invoice found in current year (' . $year . '), starting from: ' . $nextNumber);
                } else {
                    \Cake\Log\Log::debug('No previous invoice found (continuous), using starting number: ' . $nextNumber);
                }
            }
            
            // Debug - sprawdź wszystkie faktury w serii i w bieżącym okresie
            $allInvoices = $Invoices->find()
                ->select(['id', 'fullnumber', 'date', 'year', 'month'])
                ->where(['company_id' => $companyId, 'invoice_series_id' => $series->id])
                ->order(['id' => 'ASC'])
                ->limit(10)
                ->toArray();
                
            \Cake\Log\Log::debug('All invoices in series: ' . json_encode(array_map(function($inv) {
                return [
                    'id' => $inv->id,
                    'fullnumber' => $inv->fullnumber,
                    'date' => $inv->date ? $inv->date->format('Y-m-d') : null,
                    'year' => $inv->year ?? null,
                    'month' => $inv->month ?? null
                ];
            }, $allInvoices)));
            
            // Debug - sprawdź faktury w bieżącym okresie
            $currentPeriodInvoices = $query->count();
            \Cake\Log\Log::debug('Invoices found in current period (' . $periodType . '): ' . $currentPeriodInvoices);
            
            // Formatuj według wzorca serii
            $template = $series->series_template ?: '[numer]';
            $data['fullnumber'] = $this->formatInvoicePattern($template, $nextNumber, $issueDate);
            
            // Debug końcowy
            \Cake\Log\Log::debug('Invoice numbering final: ' . json_encode([
                'series_name' => $series->name,
                'period_type' => $periodType,
                'period_name' => $series->invoice_series_period->name ?? 'N/A',
                'last_invoice_fullnumber' => $lastInvoice ? $lastInvoice->fullnumber : 'NONE',
                'last_invoice_id' => $lastInvoice ? $lastInvoice->id : 'NONE',
                'next_number' => $nextNumber,
                'template' => $template,
                'generated_fullnumber' => $data['fullnumber'],
                'starting_number' => $series->starting_number
            ]));
        }

        // Przygotuj dane faktury zgodnie ze strukturą bazy
        $issueDate = $data['date'] ?? date('Y-m-d');
        $dateObject = new \DateTime($issueDate);

        // Waluta: dopracowanie zapisu dla faktur walutowych
        // Mapuj fx_rate -> currency_exchange i wyznacz currency_date
        $cur = strtoupper((string)($data['currency'] ?? 'PLN'));
        $currencyExchange = 1.0;
        $currencyDate = !empty($data['currency_date']) ? (string)$data['currency_date'] : null; // jeśli przesłana
        // weź kurs z pola currency_exchange lub fx_rate (poglądowe)
        $fxRaw = $data['currency_exchange'] ?? $data['fx_rate'] ?? null;
        if ($fxRaw !== null) {
            $currencyExchange = max(0.0001, $num($fxRaw));
        }
        if ($cur === 'PLN') {
            $currencyExchange = 1.0;
            $currencyDate = $currencyDate ?: $issueDate;
        } else {
            // wybór daty bazowej do ustalenia kursu: preferuj sold_date, potem issue date
            $soldDateStr = !empty($data['sold_date']) ? (string)$data['sold_date'] : null;
            $baseDateStr = $soldDateStr ?: $issueDate;
            // jeśli brak kursu – spróbuj pobrać z NBP (średni z dnia poprzedzającego)
            if ($currencyExchange <= 0.0001) {
                try {
                    $base = new \DateTimeImmutable($baseDateStr);
                    $nbp = $this->computeNbpAvgRate($cur, $base);
                    if (!empty($nbp['success']) && !empty($nbp['rate'])) {
                        $currencyExchange = (float)$nbp['rate'];
                        $currencyDate = (string)($nbp['effectiveDate'] ?? $currencyDate ?? $issueDate);
                    }
                } catch (\Throwable) { /* ignore – zostaw wprowadzone/ domyślne */ }
            } else {
                // mamy kurs z formularza; jeśli nie podano daty – postępuj jak NBP: dzień roboczy poprzedzający baseDate
                if (empty($currencyDate)) {
                    try {
                        $base = new \DateTimeImmutable($baseDateStr);
                        $nbp = $this->computeNbpAvgRate($cur, $base);
                        if (!empty($nbp['success']) && !empty($nbp['effectiveDate'])) {
                            $currencyDate = (string)$nbp['effectiveDate'];
                        } else {
                            $currencyDate = $issueDate; // fallback
                        }
                    } catch (\Throwable) { $currencyDate = $issueDate; }
                }
            }
        }
        // Typ dokumentu: dla trybu advance ustal na podstawie wyliczonej flagi $isFinal
        $saveType = $kind;
        if ($kind === 'advance') {
            $saveType = $isFinal ? 'final' : 'advance';
        }
        
        // flags mapping
        $fpFlag = !empty($data['flags']['fp']);

        $resolvedNumber = null;
        if (isset($nextNumber) && is_numeric($nextNumber)) {
            $resolvedNumber = (int)$nextNumber;
        } elseif (!empty($data['fullnumber'])) {
            $resolvedNumber = $this->extractNumberFromFullnumber((string)$data['fullnumber']);
        }
        if (empty($resolvedNumber) || $resolvedNumber < 1) {
            $resolvedNumber = 1;
        }

        // Pobierz issuer i identyfikatory sprzedawcy z profilu firmy przed budowaniem $invoiceData
        $issuerDefault   = null;
        $sellerVatPrefix = null;
        $sellerVatEu     = null;
        $sellerEori      = null;
        try {
            $CompaniesTbl2 = $this->fetchTable('Companies');
            $row = $CompaniesTbl2->find()->select(['issuer', 'seller_vat_prefix', 'seller_vat_eu', 'seller_eori'])->where(['id' => $companyId])->first();
            if ($row) {
                $issuerDefault   = (string)($row->issuer ?? '');
                $sellerVatPrefix = !empty($row->seller_vat_prefix) ? (string)$row->seller_vat_prefix : null;
                $sellerVatEu     = !empty($row->seller_vat_eu)     ? (string)$row->seller_vat_eu     : null;
                $sellerEori      = !empty($row->seller_eori)       ? (string)$row->seller_eori       : null;
            }
        } catch (\Throwable $e) { /* ignore */ }

        $invoiceData = [
            'hash' => substr(md5(uniqid()), 0, 32), // 32-znakowy hash
            'company_id' => $companyId,
            'contractor_id' => !empty($data['contractor_id']) ? $data['contractor_id'] : null,
            'parent_id' => $data['parent_id'] ?? null,
            'invoice_series_id' => $series->id,
            'type' => $saveType,
            'correction_type' => ($kind === 'correction') ? (in_array((string)($data['correction_type'] ?? ''), ['1','2','3']) ? (string)$data['correction_type'] : null) : null,
            'simplified_invoice' => false,
            'paymentmethod' => $data['paymentmethod'] ?? 'transfer',
            'paymentdate' => !empty($data['paymentdate']) ? $data['paymentdate'] : null,
            'paymentstate' => $paymentstate,
            'date' => $issueDate,
            'total' => $total,
            'netto' => $netto,
            'tax' => $tax,
            'alreadypaid' => $alreadypaid,
            'remaining' => $remaining,
            'fullnumber' => $isDraftWorkflow ? null : ($data['fullnumber'] ?? null),
            // NrFaKorygowany — tylko dla korekt; opcjonalne nawet wtedy.
            'corrected_invoice_number' => ($kind === 'correction' && !empty($data['corrected_invoice_number']))
                ? trim((string)$data['corrected_invoice_number'])
                : null,
            'currency' => $cur,
            'currency_date' => $currencyDate,
            'currency_exchange' => $currencyExchange,
            'description' => $data['description'] ?? '',
            'is_print' => false,
            'is_sent' => false,
            'is_api' => false,
            'workflow_status' => $isDraftWorkflow ? 'draft' : 'issued',
            'planned_ksef_send_at' => !empty($data['planned_ksef_send_at']) ? $data['planned_ksef_send_at'] : null,
            // New flags
            'is_receipt_invoice' => (!empty($data['is_receipt_invoice']) || $fpFlag) ? 1 : 0, // Faktura do paragonu (FP)
            'is_split_payment'   => !empty($data['is_split_payment']) ? 1 : 0,   // Mechanizm podzielonej płatności (MPP)
            // Optional: paragon fields (if columns exist)
            'receipt_number'     => $data['receipt_number'] ?? null,
            'receipt_date'       => !empty($data['receipt_date']) ? $data['receipt_date'] : null,
            // Data sprzedaży i daty płatności — fallback na datę wystawienia gdy pole puste
            'sold_date'               => !empty($data['sold_date']) ? $data['sold_date'] : (!empty($data['date']) ? $data['date'] : date('Y-m-d')),
            'advance_received_date'   => !empty($data['advance_received_date']) ? $data['advance_received_date'] : null,
            'paid_at'                 => !empty($data['paid_at']) ? $data['paid_at'] : null,
            'partial_paid_at'         => !empty($data['partial_paid_at']) ? $data['partial_paid_at'] : null,
            // Język, auto-wysyłka, flagi nabywcy
            'lang'               => !empty($data['lang']) ? (string)$data['lang'] : 'pl',
            'auto_send'          => !empty($data['auto_send']) ? 1 : 0,
            'buyer_is_jst'       => !empty($data['buyer_is_jst']) ? 1 : 0,
            'buyer_in_vat_group' => !empty($data['buyer_in_vat_group']) ? 1 : 0,
            // KSeF Adnotacje (JSON)
            'annotations'        => !empty($data['annotations']) ? json_encode($data['annotations'], JSON_UNESCAPED_UNICODE) : null,
            // Zwolnienie z VAT
            'annotations_tax_free'       => !empty($data['annotations_tax_free']) ? (string)$data['annotations_tax_free'] : null,
            'annotations_tax_free_field' => !empty($data['annotations_tax_free_field']) ? (string)$data['annotations_tax_free_field'] : null,
            // Identyfikatory międzynarodowe — sprzedawca (pobierane z profilu firmy, nie z formularza)
            'seller_vat_prefix'  => $sellerVatPrefix,
            'seller_vat_eu'      => $sellerVatEu,
            'seller_eori'        => $sellerEori,
            // Identyfikatory międzynarodowe — nabywca
            'buyer_vat_prefix'   => !empty($data['buyer_vat_prefix']) ? (string)$data['buyer_vat_prefix'] : null,
            'buyer_vat_eu'       => !empty($data['buyer_vat_eu']) ? (string)$data['buyer_vat_eu'] : null,
            'buyer_eori'         => !empty($data['buyer_eori']) ? (string)$data['buyer_eori'] : null,
            'buyer_tax_id_other' => !empty($data['buyer_tax_id_other']) ? (string)$data['buyer_tax_id_other'] : null,
            'buyer_tax_id_other_country' => !empty($data['buyer_tax_id_other_country']) ? (string)$data['buyer_tax_id_other_country'] : null,
            // Rachunek bankowy
            'company_bank_account_id' => !empty($data['company_bank_account_id']) ? (string)$data['company_bank_account_id'] : null,
            // FA(3) — okres faktury (usługi ciągłe / media)
            'period_from'      => !empty($data['period_from']) ? $data['period_from'] : null,
            'period_to'        => !empty($data['period_to']) ? $data['period_to'] : null,
            // FA(3) — numer WZ
            'wz_number'        => !empty($data['wz_number']) ? (string)$data['wz_number'] : null,
            // FA(3) — przyczyna korekty
            'correction_reason' => !empty($data['correction_reason']) ? (string)$data['correction_reason'] : null,
            // FA(3) — miejsce wystawienia
            'place_of_issue'   => !empty($data['place_of_issue']) ? (string)$data['place_of_issue'] : null,
            // FA(3) — tekst stopki (tablica max 3 bloków, zapisywana jako JSON)
            'footer_text'      => (function() use ($data) {
                $raw = $data['footer_text'] ?? null;
                if (is_array($raw)) {
                    $lines = array_values(array_filter(array_map('trim', $raw), fn($v) => $v !== ''));
                    return !empty($lines) ? json_encode($lines, JSON_UNESCAPED_UNICODE) : null;
                }
                return !empty($raw) ? (string)$raw : null;
            })(),
            // FA(3) — link do płatności
            'payment_link'     => !empty($data['payment_link']) ? (string)$data['payment_link'] : null,
            // FA(3) LOW — skonto
            'skonto_conditions'  => !empty($data['skonto_conditions']) ? (string)$data['skonto_conditions'] : null,
            'skonto_amount'      => !empty($data['skonto_amount']) ? (string)$data['skonto_amount'] : null,
            // FA(3) LOW — status podatnika
            'status_info_podatnika' => !empty($data['status_info_podatnika']) ? (int)$data['status_info_podatnika'] : null,
            // FA(3) LOW — nowe środki transportu WDT
            'is_new_transport_wdt' => !empty($data['is_new_transport_wdt']) ? 1 : 0,
            'p_42_5'               => !empty($data['p_42_5']) ? 1 : 0,
            // FA(3) LOW — warunki transakcji (JSON)
            'transaction_conditions_json' => null,
            // FA(3) LOW — wartość zamówienia (advance/final)
            'order_total_gross' => !empty($data['order_total_gross']) ? (string)$data['order_total_gross'] : null,
            // Nowe pola dla składników daty i numeru
            'number' => $resolvedNumber,
            'day' => (int) $dateObject->format('d'),
            'month' => (int) $dateObject->format('m'),
            'year' => (int) $dateObject->format('Y'),
            'day_year' => (int) $dateObject->format('z') + 1, // format 'z' zwraca 0-364, więc dodajemy 1
        ];

        // FA(3) LOW — warunki transakcji z POST (tc_umowy[], tc_zamowienia[])
        $tcUmowy = array_values(array_filter(array_map('trim', (array)($data['tc_umowy'] ?? []))));
        $tcZamowienia = array_values(array_filter(array_map('trim', (array)($data['tc_zamowienia'] ?? []))));
        if (!empty($tcUmowy) || !empty($tcZamowienia)) {
            $invoiceData['transaction_conditions_json'] = json_encode(
                array_filter(['Umowy' => $tcUmowy, 'Zamowienia' => $tcZamowienia]),
                JSON_UNESCAPED_UNICODE
            );
        }

        $conn = $Invoices->getConnection();
        $conn->begin();
        try {
            // Zapisz główną fakturę
            // include issuer before patch
            $invoiceData['issuer'] = (string)($data['issuer'] ?? $issuerDefault ?? '');
            // include margin_type if provided
            if (!empty($data['margin_type'])) {
                $invoiceData['margin_type'] = (string)$data['margin_type'];
            }
            $invoice = $Invoices->patchEntity($invoice, $invoiceData);
            
            if (!$Invoices->save($invoice)) {
                throw new \RuntimeException('Błąd zapisu faktury: ' . json_encode($invoice->getErrors()));
            }
            
            $invoiceId = $invoice->id;
            
            // Debug - sprawdź zapisaną fakturę
            \Cake\Log\Log::debug('Saved invoice: ' . json_encode([
                'id' => $invoice->id,
                'fullnumber' => $invoice->fullnumber,
                'invoice_series_id' => $invoice->invoice_series_id,
                'company_id' => $invoice->company_id
            ]));

            // Zapisz dane sprzedawcy (invoice_company_details) - pobierz z tabeli companies
            $CompaniesTable = $this->fetchTable('Companies');
            $company = $CompaniesTable->find()
                ->where(['id' => $companyId])
                ->contain(['CompanyRegisters'])
                ->first();
                
            if ($company) {
                $InvoiceCompanyDetailsTable = $this->fetchTable('InvoiceCompanyDetails');
                $companyDetailEntity = $InvoiceCompanyDetailsTable->find()
                    ->where(['invoice_id' => $invoiceId])
                    ->first()
                    ?? $InvoiceCompanyDetailsTable->newEmptyEntity();
                // Determine bank account snapshot from CompanyBankAccounts:
                // 1) by company_bank_account_id (selected in form), 2) default account, 3) fallback
                $snapshotBank       = '';
                $snapshotBankName   = '';
                $snapshotBankDesc   = '';
                $snapshotSwift      = '';
                $snapshotBankCoresp = '';
                try {
                    $Cba = $this->fetchTable('CompanyBankAccounts');
                    $selectedBankId = !empty($data['company_bank_account_id']) ? (string)$data['company_bank_account_id'] : null;
                    $cbaRecord = null;
                    if ($selectedBankId !== null) {
                        $cbaRecord = $Cba->find()
                            ->select(['iban', 'bank_name', 'bank_desc', 'swift', 'bank_correspondent'])
                            ->where(['id' => $selectedBankId, 'company_id' => $companyId])
                            ->first();
                    }
                    if (!$cbaRecord) {
                        $cbaRecord = $Cba->find()
                            ->select(['iban', 'bank_name', 'bank_desc', 'swift', 'bank_correspondent'])
                            ->where(['company_id' => $companyId, 'is_default' => 1])
                            ->order(['is_default' => 'DESC', 'created' => 'DESC'])
                            ->first();
                    }
                    if ($cbaRecord) {
                        $snapshotBank       = (string)($cbaRecord->iban ?? '');
                        $snapshotBankName   = (string)($cbaRecord->bank_name ?? '');
                        $snapshotBankDesc   = (string)($cbaRecord->bank_desc ?? '');
                        $snapshotSwift      = (string)($cbaRecord->swift ?? '');
                        $snapshotBankCoresp = (string)($cbaRecord->bank_correspondent ?? '');
                    }
                } catch (\Throwable $e) {
                    // ignore, fallback below
                }
                if ($snapshotBank === '') {
                    $snapshotBank = trim((string)($data['invoice_company_detail']['bank_account'] ?? ''));
                }
                if ($snapshotBank === '') {
                    $snapshotBank = (string)($company->bank_account ?? '');
                }
                // Allow form overrides for bank_name/bank_desc/swift if user typed them manually
                if ($snapshotBankName === '') {
                    $snapshotBankName = (string)($data['invoice_company_detail']['bank_name'] ?? '');
                }
                if ($snapshotBankDesc === '') {
                    $snapshotBankDesc = (string)($data['invoice_company_detail']['bank_desc'] ?? '');
                }
                if ($snapshotSwift === '') {
                    $snapshotSwift = (string)($data['invoice_company_detail']['swift'] ?? '');
                }
                // Street + local number (if provided) e.g. "Kwiatowa 10/5"
                $streetLine = trim((string)($company->street ?? ''));
                $localNo    = trim((string)($company->local_number ?? ''));
                if ($localNo !== '') {
                    // If street ends with a digit/letter, join with '/', otherwise with space
                    $joiner = (preg_match('/[\p{L}\d]$/u', $streetLine) ? '/' : ' ');
                    $streetLine = rtrim($streetLine) . $joiner . $localNo;
                }

                // Dla faktury najmu prywatnego — użyj danych osoby fizycznej zamiast firmy
                if ($kind === 'rental') {
                    $rentalName = trim(trim((string)($company->rental_first_name ?? '')) . ' ' . trim((string)($company->rental_last_name ?? '')));
                    $companyDetailData = [
                        'invoice_id'   => $invoiceId,
                        'name'         => $rentalName,
                        'nip'          => (string)($company->rental_nip ?? $company->nip ?? ''),
                        'street'       => (string)($company->rental_street ?? ''),
                        'city'         => (string)($company->rental_city ?? ''),
                        'zip'          => (string)($company->rental_postal_code ?? ''),
                        'country'      => 'Polska',
                        'bank_account' => $snapshotBank,
                        'email'        => (string)($company->email ?? ''),
                        'phone'        => (string)($company->phone ?? ''),
                        'krs'          => '',
                        'regon'        => '',
                        'bdo'          => '',
                        'registers_json' => $this->buildRegistersJson($company),
                        'bank_name'         => $snapshotBankName,
                        'bank_desc'         => $snapshotBankDesc,
                        'swift'             => $snapshotSwift,
                        'bank_correspondent' => $snapshotBankCoresp,
                        'gln'          => '',
                        'country_code' => 'PL',
                    ];
                } else {
                $companyDetailData = [
                    'invoice_id' => $invoiceId,
                    'name' => $company->name ?? '',
                    'nip' => $company->nip ?? '',
                    'street' => $streetLine,
                    'city' => $company->city ?? '',
                    'zip' => $company->postal_code ?? '',
                    'country' => $company->country ?? 'Polska',
                    'bank_account' => $snapshotBank,
                    // FA(3) — dane kontaktowe i rejestrowe sprzedawcy
                    'email'        => (string)($company->email ?? ''),
                    'phone'        => (string)($company->phone ?? ''),
                    'krs'          => '',
                    'regon'        => '',
                    'bdo'          => '',
                    'registers_json' => $this->buildRegistersJson($company),
                    'bank_name'         => $snapshotBankName,
                    'bank_desc'         => $snapshotBankDesc,
                    'swift'             => $snapshotSwift,
                    'bank_correspondent' => $snapshotBankCoresp,
                    'gln'          => (string)($company->gln ?? ''),
                    'country_code' => (string)($company->country_code ?? 'PL'),
                ];
                } // end else (not rental)

                $companyDetailEntity = $InvoiceCompanyDetailsTable->patchEntity($companyDetailEntity, $companyDetailData);
                if (!$InvoiceCompanyDetailsTable->save($companyDetailEntity)) {
                    throw new \RuntimeException('Błąd zapisu danych sprzedawcy');
                }
            }

            // Zapisz dane nabywcy (invoice_contractors)
            if (!empty($data['invoice_contractor']) && !empty($data['invoice_contractor']['name'])) {
                $contractor = $data['invoice_contractor'];
                $InvoiceContractorsTable = $this->fetchTable('InvoiceContractors');
                $contractorEntity = $InvoiceContractorsTable->find()
                    ->where(['invoice_id' => $invoiceId])
                    ->first()
                    ?? $InvoiceContractorsTable->newEmptyEntity();
                $contractorData = [
                    'invoice_id' => $invoiceId,
                    'name' => $contractor['name'] ?? '',
                    'nip' => $contractor['nip'] ?? '',
                    'street' => $contractor['street'] ?? '',
                    'city' => $contractor['city'] ?? '',
                    'zip' => $contractor['zip'] ?? '',
                    'country' => $contractor['country'] ?? 'Polska',
                    'account_number' => $contractor['account_number'] ?? '',
                    'email' => $contractor['email'] ?? null,
                    'phone' => $contractor['phone'] ?? null,
                    'gln'        => (string)($contractor['gln'] ?? ''),
                    'nr_klienta' => (string)($contractor['nr_klienta'] ?? ''),
                    // FA(3) LOW — adres korespondencyjny nabywcy
                    'koresp_country_code' => !empty($contractor['koresp_country_code']) ? (string)$contractor['koresp_country_code'] : null,
                    'koresp_address_l1'   => !empty($contractor['koresp_address_l1']) ? (string)$contractor['koresp_address_l1'] : null,
                    'koresp_address_l2'   => !empty($contractor['koresp_address_l2']) ? (string)$contractor['koresp_address_l2'] : null,
                    'koresp_gln'          => !empty($contractor['koresp_gln']) ? (string)$contractor['koresp_gln'] : null,
                    // Identyfikatory międzynarodowe
                    'vat_prefix'           => !empty($contractor['vat_prefix']) ? (string)$contractor['vat_prefix'] : null,
                    'vat_eu'               => !empty($contractor['vat_eu']) ? (string)$contractor['vat_eu'] : null,
                    'eori'                 => !empty($contractor['eori']) ? (string)$contractor['eori'] : null,
                    'tax_id_other'         => !empty($contractor['tax_id_other']) ? (string)$contractor['tax_id_other'] : null,
                    'tax_id_other_country' => !empty($contractor['tax_id_other_country']) ? (string)$contractor['tax_id_other_country'] : null,
                ];
                
                $contractorEntity = $InvoiceContractorsTable->patchEntity($contractorEntity, $contractorData);
                if (!$InvoiceContractorsTable->save($contractorEntity)) {
                    throw new \RuntimeException('Błąd zapisu danych nabywcy');
                }

                // Opcjonalnie: zapis do katalogu kontrahentów przy zaznaczeniu w formularzu
                try {
                    $saveToCatalog = (int)($data['save_to_catalog'] ?? 0) === 1;
                    if ($saveToCatalog) {
                        // Spróbuj zaktualizować lub dodać kontrahenta w tabeli 'Contractors'
                        $identity  = $this->request->getAttribute('identity');
                        $companyId = $identity?->get('company_id');
                        $Contractors = $this->fetchTable('Contractors');
                        $existing = null;
                        if (!empty($contractorData['nip'])) {
                            $existing = $Contractors->find()
                                ->where(['company_id' => $companyId, 'nip' => $contractorData['nip']])
                                ->first();
                        }
                        if (!$existing) {
                            $existing = $Contractors->find()
                                ->where(['company_id' => $companyId, 'name' => $contractorData['name']])
                                ->first();
                        }
                        $catEntity = $existing ?: $Contractors->newEmptyEntity();
                        $catalogData = [
                            'company_id' => $companyId,
                            'name'       => $contractorData['name'],
                            'nip'        => $contractorData['nip'],
                            'street'     => $contractorData['street'],
                            'city'       => $contractorData['city'],
                            'zip'        => $contractorData['zip'],
                            'country'    => $contractorData['country'],
                            'email'      => $contractor['email'] ?? null,
                            'phone'      => $contractor['phone'] ?? null,
                            'account_number' => $contractorData['account_number'] ?? null,
                            'source'     => (string)($data['contractor_source'] ?? ''),
                        ];
                        $catEntity = $Contractors->patchEntity($catEntity, $catalogData);
                        // Zapis w katalogu jest opcjonalny – w razie błędu nie blokujemy zapisu faktury
                        $Contractors->save($catEntity);
                    }
                } catch (\Throwable $e) {
                    // Ignoruj problemy katalogu; faktura i snapshot zapisane
                }
            }

            // Zapisz dane odbiorcy (invoice_recipients) — opcjonalnie
            if (!empty($data['invoice_recipient'])) {
                $recipient = $data['invoice_recipient'];
                $hasRecipientData = !empty(trim((string)($recipient['name'] ?? '')))
                    || !empty(trim((string)($recipient['nip'] ?? '')));

                if ($hasRecipientData) {
                    $InvoiceRecipientsTable = $this->fetchTable('InvoiceRecipients');
                    $recipientEntity = $InvoiceRecipientsTable->newEmptyEntity();
                    $recipientData = [
                        'invoice_id' => $invoiceId,
                        'name'    => $recipient['name'] ?? '',
                        'nip'     => $recipient['nip'] ?? '',
                        'street'  => $recipient['street'] ?? '',
                        'city'    => $recipient['city'] ?? '',
                        'zip'     => $recipient['zip'] ?? '',
                        'country' => $recipient['country'] ?? 'Polska',
                        'email'   => $recipient['email'] ?? null,
                        'phone'   => $recipient['phone'] ?? null,
                        // FA(3) — dodatkowe pola odbiorcy
                        'rola'               => !empty($recipient['rola']) ? (int)$recipient['rola'] : null,
                        'rola_opis'          => (string)($recipient['rola_opis'] ?? ''),
                        'vat_prefix'         => (string)($recipient['vat_prefix'] ?? ''),
                        'vat_eu'             => (string)($recipient['vat_eu'] ?? ''),
                        'tax_id_other'       => (string)($recipient['tax_id_other'] ?? ''),
                        'tax_id_other_country' => (string)($recipient['tax_id_other_country'] ?? ''),
                        'gln'                => (string)($recipient['gln'] ?? ''),
                    ];
                    $recipientEntity = $InvoiceRecipientsTable->patchEntity($recipientEntity, $recipientData);
                    if (!$InvoiceRecipientsTable->save($recipientEntity)) {
                        throw new \RuntimeException('Błąd zapisu danych odbiorcy');
                    }
                }
            }

            // Zapisz pozycje faktury (invoice_contents)
            $InvoiceContentsTable = $this->fetchTable('InvoiceContents');
            foreach ($contents as $idx => $contentData) {
                $contentEntity = $InvoiceContentsTable->newEmptyEntity();
                $contentData['invoice_id'] = $invoiceId;
                $contentData['sort_order'] = $idx;

                $contentEntity = $InvoiceContentsTable->patchEntity($contentEntity, $contentData);
                if (!$InvoiceContentsTable->save($contentEntity)) {
                    throw new \RuntimeException('Błąd zapisu pozycji faktury');
                }
            }

            // FA(3) LOW — zapis tabel relacyjnych: charges, factor_banks, authorized_entities, order_lines
            $this->saveInvoiceRelationalFa3($invoiceId, $data);

            $conn->commit();

            // Opcjonalna ścieżka: po zapisie wyślij od razu do KSeF z przesłanego pliku XML (FA (3))
            if ($doSend && $this->buyerDataMissing($invoice) && !$this->noBuyerSendConfirmed()) {
                $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'blocked', [
                    'message' => 'Brak danych nabywcy — automatyczna wysyłka po zapisie wstrzymana.',
                    'source'  => 'add',
                ]);
                $this->Flash->warning('Faktura została zapisana, ale NIE została wysłana do KSeF: brak danych nabywcy (nazwa, NIP, adres). Uzupełnij nabywcę w edycji albo potwierdź wysyłkę bez nabywcy przyciskiem „Wyślij do KSeF”.');
                return $this->redirect(['action' => 'view', $invoice->id]);
            }
            if ($doSend) {
                $dateError = $this->validateSendDateWindow($invoice);
                if ($dateError !== null) {
                    $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'blocked', [
                        'message' => $dateError,
                        'source' => 'add',
                    ]);
                    $this->Flash->error($dateError . ' Wysyłka do KSeF została zablokowana.');
                    return $this->redirect(['action' => 'view', $invoice->id]);
                }

                // Uwaga: NIE ustawiamy tutaj workflow_status='sending' — robi to sendInvoiceToKsefCore()
                // atomowo przed wysyłką, co eliminuje race condition gdy żądanie zostanie przerwane.

                $envRaw = (string)($data['ksef_env'] ?? 'prod');
                $environment = ($envRaw === 'prod') ? 'prod' : 'test';

                // Odczytaj przesłany plik XML (opcjonalny)
                $xml = null;
                $uploaded = $this->request->getData('ksef_xml');
                try {
                    if ($uploaded instanceof UploadedFileInterface) {
                        if ($uploaded->getError() === UPLOAD_ERR_OK && (int)$uploaded->getSize() > 0) {
                            $stream = $uploaded->getStream();
                            $xml = (string)$stream->getContents();
                        }
                    } elseif (is_array($uploaded) && !empty($uploaded['tmp_name']) && is_file($uploaded['tmp_name'])) {
                        $xml = (string)file_get_contents($uploaded['tmp_name']);
                    }
                } catch (\Throwable $e) {
                    // pozostaw $xml jako null
                }

                if (!is_string($xml) || trim($xml) === '') {
                    // Brak uploadu – spróbuj wygenerować minimalny FA(3) z danych faktury
                    try {
                        $fresh = $Invoices->get($invoiceId, contain: ['InvoiceContractors','InvoiceCompanyDetails','InvoiceContents' => ['Vats'], 'Companies']);
                        $xml = $this->buildFa3Xml($fresh);
                    } catch (\Throwable $e) {
                        $xml = '';
                    }
                }

                if (!is_string($xml) || trim($xml) === '') {
                    $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'xml_missing', [
                        'message' => 'Brak XML FA(3) po upload/generowaniu.',
                        'source' => 'add',
                    ]);
                    $this->Flash->warning('Brak pliku XML FA (3) i nie udało się wygenerować poprawnego XML. Zapisano fakturę, ale nie wysłano do KSeF.');
                } else {
                    try {
                        $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'send_attempt', [
                            'source' => 'add',
                            'env' => $environment,
                        ]);
                        $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
                        $res = $service->sendInvoiceXml((string)$companyId, $environment, $xml);

                        // Zapisz wynik w polach ksef_*
                        $desc = (string)($res['statusDesc'] ?? '');
                        $refs = ' [S=' . (string)($res['sessionReference'] ?? '') . ', I=' . (string)($res['invoiceReference'] ?? '') . ']';
                        $invoice->set('ksef_status', (string)($res['statusCode'] ?? ''));
                        $invoice->set('ksef_desc',   trim($desc . $refs));
                        $invoice->set('ksef_number', (string)($res['ksefNumber'] ?? ''));
                        $invoice->set('workflow_status', !empty($res['ok']) ? 'sent' : 'error');
                        $invoice->set('planned_ksef_send_at', null);
                        $Invoices->save($invoice); // best-effort

                        if (class_exists('Cake\\Log\\Log')) {
                            \Cake\Log\Log::info('[KSeF][send] inv=' . $invoice->id . ' env=' . $environment . ' code=' . ($res['statusCode'] ?? '') . ' desc=' . ($res['statusDesc'] ?? '') . ' ksef=' . ($res['ksefNumber'] ?? '') . ' S=' . ($res['sessionReference'] ?? '') . ' I=' . ($res['invoiceReference'] ?? ''));
                        }

                        if (!empty($res['ok'])) {
                            $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'send_success', [
                                'source' => 'add',
                                'env' => $environment,
                                'status_code' => (string)($res['statusCode'] ?? ''),
                                'ksef_number' => (string)($res['ksefNumber'] ?? ''),
                                'session_reference' => (string)($res['sessionReference'] ?? ''),
                            ]);
                            $this->Flash->success('Wysłano do KSeF. Numer KSeF: ' . (string)$res['ksefNumber']);
                        } else {
                            $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'send_error', [
                                'source' => 'add',
                                'env' => $environment,
                                'status_code' => (string)($res['statusCode'] ?? ''),
                                'message' => (string)($res['statusDesc'] ?? ''),
                            ]);
                            $this->Flash->error('Nie udało się wysłać do KSeF (' . (string)($res['statusCode'] ?? '') . '): ' . (string)($res['statusDesc'] ?? ''));
                        }
                    } catch (\Throwable $e) {
                        $invoice->set('workflow_status', 'error');
                        $Invoices->save($invoice);
                        $this->logKsefSendEvent((string)$companyId, (string)$invoice->id, 'send_exception', [
                            'source'          => 'add',
                            'env'             => $environment,
                            'message'         => $e->getMessage() ?: '(brak treści wyjątku)',
                            'exception_class' => get_class($e),
                            'file'            => $e->getFile() . ':' . $e->getLine(),
                            'trace'           => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8)),
                        ]);
                        $this->Flash->error('Błąd wysyłki do KSeF: ' . ($e->getMessage() ?: get_class($e)));
                    }
                }
            } else {
                $this->Flash->success($isDraftWorkflow
                    ? 'Dokument roboczy został zapisany.'
                    : ($kind === 'proforma' ? 'Faktura proforma została utworzona.' : ($kind === 'novat' ? 'Rachunek został utworzony.' : 'Dokument został utworzony.')));
            }

            return $this->redirect(['action' => 'view', $invoice->id]);
            
        } catch (\Throwable $e) {
            $conn->rollback();
            $this->Flash->error('Błąd zapisu: ' . $e->getMessage());
        }
        } catch (\RuntimeException $e) {
            // Wyjątek walidacyjny rzucony przed transakcją (np. pusta nazwa pozycji)
            $this->Flash->error($e->getMessage());
        }
    }

    // GET — osobny widok dla noVAT
    // debug($kind);
    $this->set(compact('invoice','vats','vatRatesMap','kind'));
    if($kind === 'novat') {
        $this->render('add_no_vat');
    } else if ($kind === 'rental') {
        $this->render('add_rental');
    } else if ($kind === 'margin') {
        $this->render('add_margin');
    } else if ($kind === 'proforma') {
        $this->render('add_proforma');
    } else if ($kind === 'advance') {
        $this->render('add_advance');
    } else if ($kind === 'vat') {
        $this->render('add');
    } else if ($kind === 'currency') {
        $this->render('add_currency');
    } else if ($kind === 'internal') {
        $this->render('add');
    } else if ($kind === 'internalEvidence') {
        $this->render('add');
    } else if ($kind === 'oss') {
        $this->render('add');
    } else if ($kind === 'correction') {
        // Ensure original invoice is fully loaded with contractor and items for correction views
        if (!isset($original) || empty($original->invoice_contractor) || empty($original->invoice_contents)) {
            try {
                $pass = (array)$this->request->getParam('pass', []);
                $origId = $pass[0] ?? $this->request->getQuery('parent_id') ?? $this->request->getQuery('original_id') ?? $this->request->getQuery('id');
                if (!empty($origId)) {
                    $original = $this->Invoices->find()
                        ->contain(['InvoiceContractors','InvoiceContents' => ['Vats']])
                        ->where(['Invoices.company_id' => $companyId, 'Invoices.id' => $origId])
                        ->first();
                    if ($original) {
                        $this->set('original', $original);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $originalType = isset($original) ? (string)($original->type ?? '') : '';
        if ($originalType === 'novat') {
            $this->render('add_correct_no_vat');
        } elseif ($originalType === 'margin') {
            // Zaznacz procedurę marży w korekcie na podstawie faktury pierwotnej.
            // resolveMarginType bierze wartość z faktury pierwotnej (a gdy ta jej nie ma — z jej rodzica).
            if (isset($original)) {
                $mt = $this->resolveMarginType($original);
                if ($mt !== '' && empty($invoice->margin_type)) {
                    $invoice->set('margin_type', $mt);
                }
            }
            $this->render('add_correct_margin');
        } elseif ($originalType === 'currency') {
            $this->render('add_correct_currency');
        } else {
            $this->render('add_correct');
        }
    } else {
        $this->render('invalid_kind');
    }
    // $this->render($kind === 'novat' ? 'add_no_vat' : 'add');
    return null;
}
    /**
     * Edit method
     *
     * @param string|null $id Invoice id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        // Załaduj fakturę z pozycjami i snapshotami kontrahenta + danych firmy (rachunek, swift itd.)
        $invoice = $this->Invoices->get($id, contain: [
            'InvoiceSeries',
            'InvoiceContractors',
            'InvoiceCompanyDetails',
            'InvoiceContents' => ['Vats'],
            'InvoicePayments',
            'InvoiceAdditionalDescriptions',
            'InvoiceRecipients',
            'InvoiceNewTransports',
            'InvoiceCharges',
            'InvoiceFactorBanks',
            'InvoiceAuthorizedEntities',
            'InvoiceOrderLines',
        ]);

        $workflowStatus = strtolower(trim((string)($invoice->workflow_status ?? '')));
        if (in_array($workflowStatus, ['sending', 'sent'], true)) {
            $this->Flash->warning('Ten dokument jest już wysłany do KSeF i nie może być edytowany. Wystaw korektę.');
            return $this->redirect(['action' => 'view', $id]);
        }

        // Ujednolić property dla szablonów (część widoków używa invoice_contractor)
        try {
            if (empty($invoice->invoice_contractor) && !empty($invoice->invoice_contractors)) {
                // hasOne returns a single entity, not a collection — assign directly
                $invoice->set('invoice_contractor', $invoice->invoice_contractors);
            }
            // Prefill selecta serii (templates/add*.php używają pola `series`)
            if (empty($invoice->series) && !empty($invoice->invoice_series) && !empty($invoice->invoice_series->name)) {
                $invoice->set('series', (string)$invoice->invoice_series->name);
            }
        } catch (\Throwable) { /* ignore */ }

        $kind = strtolower((string)($invoice->type ?? ''));
        $this->set('kind', $kind);
        $this->set('isEdit', true);

        // Render the same templates as the corresponding add_* forms (where safe)
        // Mapa typ → szablon edycji. Typy reużywają szablonów add_* bo form postuje do bieżącego URL.
        // 'correction' celowo NIE jest w tej mapie — wymaga oddzielnego bloku if poniżej,
        // bo musi wcześniej załadować fakturę pierwotną ($original) do określenia szablonu korekty.
        $templateMap = [
            'vat'              => 'add',
            'currency'         => 'add_currency',
            'novat'            => 'add_no_vat',
            'proforma'         => 'add_proforma',
            'margin'           => 'add_margin',
            'advance'          => 'add_advance',
            'final'            => 'add_advance',
            // Dokumenty wewnętrzne i OSS — reużywają szablonu add.php (tak jak handleAdd)
            'internal'         => 'add',
            'internalevidence' => 'add',
            'oss'              => 'add',
            // fallback dla nieznanych typów
            ''                 => 'add',
        ];
        if ($kind === 'correction') {
            $original = null;
            try {
                $origId = $invoice->parent_id ?? null;
                if (!empty($origId) && !empty($companyId)) {
                    $original = $this->Invoices->find()
                        ->contain(['InvoiceContractors','InvoiceContents' => ['Vats']])
                        ->where(['Invoices.company_id' => $companyId, 'Invoices.id' => $origId])
                        ->first();
                }
            } catch (\Throwable) {
                $original = null;
            }

            if (empty($original)) {
                $this->Flash->warning('Nie znaleziono faktury pierwotnej powiązanej z tą korektą. Edycja może być niekompletna.');
            }

            if (!empty($original)) {
                // normalizacja invoice_contractor dla szablonów korekt
                try {
                    if (empty($original->invoice_contractor) && !empty($original->invoice_contractors)) {
                        $original->set('invoice_contractor', $original->invoice_contractors);
                    }
                } catch (\Throwable) { /* ignore */ }
                $this->set('original', $original);
            }

            $originalType = strtolower((string)($original->type ?? ''));
            if ($originalType === 'novat') {
                $tpl = 'add_correct_no_vat';
            } elseif ($originalType === 'margin') {
                $tpl = 'add_correct_margin';
            } elseif ($originalType === 'currency') {
                $tpl = 'add_correct_currency';
            } else {
                $tpl = 'add_correct';
            }
            $tplPath = ROOT . DS . 'templates' . DS . 'Invoices' . DS . $tpl . '.php';
            if (is_file($tplPath)) {
                $this->viewBuilder()->setTemplate($tpl);
            }
        } else {
            $tpl = $templateMap[$kind] ?? 'add';
            $tplPath = ROOT . DS . 'templates' . DS . 'Invoices' . DS . $tpl . '.php';
            if (is_file($tplPath)) {
                $this->viewBuilder()->setTemplate($tpl);
            }
        }

        // Prefill pól specyficznych dla zaliczki/końcowej (szablon add_advance ma osobne pola)
        if (in_array($kind, ['advance','final'], true)) {
            try {
                $invoice->set('proforma_id', $invoice->parent_id ?? null);
                $invoice->set('advance_gross', (float)($invoice->total ?? 0));
                $firstVat = null;
                if (!empty($invoice->invoice_contents) && is_iterable($invoice->invoice_contents)) {
                    foreach ($invoice->invoice_contents as $it) { $firstVat = $it->vat_code_id ?? null; break; }
                }
                $invoice->set('advance_vat_code_id', $firstVat);
                $invoice->set('is_final', $kind === 'final' ? 1 : 0);
                // Prefill GTU z pierwszej pozycji
                $firstGtu = null;
                if (!empty($invoice->invoice_contents) && is_iterable($invoice->invoice_contents)) {
                    foreach ($invoice->invoice_contents as $it) { $firstGtu = $it->gtu_code ?? null; break; }
                }
                $invoice->set('advance_gtu_code', $firstGtu ?? '');
                // advance_received_date — już załadowane z DB przez ORM
            } catch (\Throwable) { /* ignore */ }
        }

        // Słowniki VAT do widoku
        $Vats        = $this->fetchTable('Vats');
        $vatRows     = $Vats->find()->select(['id','name','rate'])->order(['rate' => 'DESC'])->all();
        $vats        = $vatRows->combine('id', fn($v) => (string)$v->name)->toArray();
        $vatRatesMap = $vatRows->combine('id', 'rate')->toArray();

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = (array)$this->request->getData();
            $this->hydrateInvoiceDraftFromData($invoice, $data);

            $num = static function($val): float {
                $s = str_replace([' ', ','], ['', '.'], (string)$val);
                return is_numeric($s) ? (float)$s : 0.0;
            };

            $noVat   = ($invoice->type === 'novat');
            $items   = (array)($data['items'] ?? []);
            $contents = [];
            $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;

            if (in_array($kind, ['advance','final'], true)) {
                // Edycja zaliczki/końcowej: pozycja wyliczana z pól formularza add_advance
                $proformaId = $data['proforma_id'] ?? ($invoice->parent_id ?? null);
                if (empty($proformaId)) {
                    $this->Flash->error('Wybierz proformę/ofertę do powiązania zaliczki.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }
                $advanceGross = $num($data['advance_gross'] ?? 0);
                $isFinalExplicitEarly = (!empty($data['is_final']) && (int)$data['is_final'] === 1) || ($kind === 'final');
                if ($advanceGross < 0) {
                    $this->Flash->error('Kwota zaliczki nie może być ujemna (podano: ' . number_format($advanceGross, 2, ',', ' ') . ').');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }
                // Kwota 0 dozwolona tylko dla faktury rozliczeniowej/końcowej (rozliczenie 100% zaliczek).
                if ($advanceGross == 0.0 && !$isFinalExplicitEarly) {
                    $this->Flash->error('Kwota zaliczki musi być większa od zera.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                // Pobierz proformę (weryfikacja company_id jeśli dostępne)
                $Proformas = $this->fetchTable('Invoices');
                $proformaQ = $Proformas->find()->contain(['InvoiceContractors', 'InvoiceContents' => ['Vats']])
                    ->where(['Invoices.id' => $proformaId, 'Invoices.type' => 'proforma']);
                if (!empty($companyId)) {
                    $proformaQ->where(['Invoices.company_id' => $companyId]);
                }
                $proforma = $proformaQ->first();
                if (!$proforma) {
                    $this->Flash->error('Nie znaleziono wskazanej proformy/oferty.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                // Suma innych dokumentów do tej proformy (bez aktualnie edytowanego)
                $sumAdvances = (float)($Proformas->find()
                    ->select(['s' => $Proformas->find()->func()->sum('total')])
                    ->where([
                        'parent_id' => $proformaId,
                        'type IN' => ['advance','final'],
                        'id !=' => $invoice->id,
                    ] + (!empty($companyId) ? ['company_id' => $companyId] : []))
                    ->enableHydration(false)
                    ->first()['s'] ?? 0.0);

                $hasFinal = (bool)$Proformas->find()
                    ->select(['id'])
                    ->where([
                        'parent_id' => $proformaId,
                        'type' => 'final',
                        'id !=' => $invoice->id,
                    ] + (!empty($companyId) ? ['company_id' => $companyId] : []))
                    ->limit(1)
                    ->count();

                $remainingToSettle = round(max(0.0, ((float)$proforma->total) - $sumAdvances), 2);

                // Opcjonalna aktualizacja kwoty proformy
                if (!empty($data['update_proforma_total']) && (int)$data['update_proforma_total'] === 1) {
                    $newProformaTotal = round((float)($data['new_proforma_total'] ?? 0), 2);
                    if ($newProformaTotal > (float)$proforma->total && $newProformaTotal >= round((float)($data['advance_gross'] ?? 0), 2)) {
                        $proforma->set('total', $newProformaTotal);
                        $proforma->set('remaining', $newProformaTotal - $sumAdvances);
                        $Proformas->save($proforma, ['checkRules' => false, 'validate' => false]);
                        $remainingToSettle = round(max(0.0, $newProformaTotal - $sumAdvances), 2);
                    }
                }

                // $isFinal: bazujemy na zapisanym typie oraz jawnym polu is_final z formularza.
                // NIE oznaczamy automatycznie jako końcowej przy 100% — decyduje użytkownik.
                $isFinalExplicit = !empty($data['is_final']) && (int)$data['is_final'] === 1;
                $shouldBeFinalByAmount = $remainingToSettle > 0 && abs($advanceGross - $remainingToSettle) < 0.01;
                $isFinal = $isFinalExplicit || ($kind === 'final');

                // Faktura rozliczeniowa po 100% zaliczek (art. 106f ust. 3) — kwota pozostała = 0.
                $priorAdvancesCover100 = ($remainingToSettle <= 0.0) && ($sumAdvances > 0.0);
                $isZeroSettlement      = $isFinal && $priorAdvancesCover100;

                if ($isFinal && empty($data['sold_date'])) {
                    $this->Flash->error('Dla faktury rozliczeniowej/końcowej podaj datę dokonania dostawy towarów albo wykonania usługi.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                if ($hasFinal && !$isFinal) {
                    $this->Flash->error('Faktura końcowa została już wystawiona dla tej oferty.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }
                if ($remainingToSettle <= 0.0 && !$isZeroSettlement) {
                    $this->Flash->error('Proforma została już w całości rozliczona. Aby rozliczyć zaliczki, wystaw fakturę rozliczeniową/końcową.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                // ── Buduj grupy stawkowe z pozycji proformy (VAT, GTU, nazwy) ────────────
                $proformaItems    = (array)($proforma->invoice_contents ?? []);
                $rateGroupsE      = [];
                $proformaBruttoTotalE = 0.0;
                foreach ($proformaItems as $pi) {
                    $piVatCodeId = $pi->vat_code_id;
                    $piBrutto    = (float)($pi->brutto ?? 0);
                    $piRate      = isset($pi->vat) ? (float)$pi->vat->rate : 0.0;
                    $rateKey     = $piVatCodeId !== null ? (string)$piVatCodeId : ('zw_' . $piRate);
                    if (!isset($rateGroupsE[$rateKey])) {
                        $rateGroupsE[$rateKey] = [
                            'vat_code_id' => $piVatCodeId,
                            'rate'        => $piRate,
                            'brutto'      => 0.0,
                            'names'       => [],
                            'gtu'         => '',
                        ];
                    }
                    $rateGroupsE[$rateKey]['brutto'] += $piBrutto;
                    $proformaBruttoTotalE += $piBrutto;
                    $piName = trim((string)($pi->name ?? ''));
                    if ($piName !== '') { $rateGroupsE[$rateKey]['names'][] = $piName; }
                    if ($rateGroupsE[$rateKey]['gtu'] === '') {
                        $piGtu = trim((string)($pi->gtu_code ?? ''));
                        if ($piGtu !== '') { $rateGroupsE[$rateKey]['gtu'] = $piGtu; }
                    }
                }

                $lineNameFallbackE = ($isFinal ? 'Faktura końcowa do zamówienia ' : 'Zaliczka do zamówienia ')
                    . (string)($proforma->fullnumber ?? $proforma->id);

                // VAT code dla single-rate (multi-rate: każda grupa ma swój vat_code_id)
                $firstGroupE = !empty($rateGroupsE) ? reset($rateGroupsE) : null;
                $vatCodeId   = $firstGroupE['vat_code_id'] ?? null;
                $rate   = $noVat ? 0.0 : (float)($vatRatesMap[$vatCodeId] ?? 0);
                $nettoA = $rate > 0 ? round($advanceGross / (1 + $rate/100), 2) : round($advanceGross, 2);
                $taxA   = round($advanceGross - $nettoA, 2);
                $bruttoA = round($advanceGross, 2);

                if (!$isFinal && ($bruttoA - $remainingToSettle > 0.01)) {
                    $this->Flash->error('Kwota zaliczki przekracza pozostałą do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . ').');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }
                if ($isFinal && !$isZeroSettlement && (abs($bruttoA - $remainingToSettle) > 0.01)) {
                    $this->Flash->error('Dla faktury końcowej kwota musi równać się pozostałej do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . ').');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                // ── Buduj pozycje faktury zaliczkowej ────────────────────────────────
                $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;

                // Pozycje proformy z dodatnim brutto — do proporcjonalnego podziału kwoty zaliczki.
                $splitItemsE = [];
                foreach ($proformaItems as $pi) {
                    if ((float)($pi->brutto ?? 0) > 0.0) { $splitItemsE[] = $pi; }
                }

                if (empty($splitItemsE) || $proformaBruttoTotalE <= 0.0) {
                    // Fallback: brak pozycji proformy z wartością — jedna linia zbiorcza
                    $allNamesE = [];
                    foreach ($rateGroupsE as $g) {
                        foreach ($g['names'] as $n) { $allNamesE[] = $n; }
                    }
                    $lineNameE = implode(', ', array_unique(array_filter($allNamesE)));
                    if ($lineNameE === '') { $lineNameE = $lineNameFallbackE; }
                    $lineGtuE  = $firstGroupE['gtu'] ?? '';

                    $contents[] = [
                        'vat_code_id'      => $noVat ? null : $vatCodeId,
                        'name'             => $lineNameE,
                        'product_desc'     => '',
                        'quantity'         => 1,
                        'unit'             => 'szt.',
                        'price'            => $nettoA,
                        'discount_percent' => 0,
                        'netto'            => $nettoA,
                        'brutto'           => $bruttoA,
                        'vat_amount'       => $noVat ? null : $taxA,
                        'gtu_code'         => $lineGtuE !== '' ? $lineGtuE : null,
                    ];
                    $sumNet = $nettoA; $sumTax = $taxA; $sumGross = $bruttoA;
                } else {
                    // Jedna linia faktury zaliczkowej na KAŻDĄ pozycję proformy (podział proporcjonalny).
                    $remaining = $bruttoA;
                    $cntE = count($splitItemsE);
                    foreach ($splitItemsE as $idx => $pi) {
                        $isLast     = ($idx === $cntE - 1);
                        $piBrutto   = (float)($pi->brutto ?? 0);
                        $lineBrutto = $isLast
                            ? round($remaining, 2)
                            : round($bruttoA * ($piBrutto / $proformaBruttoTotalE), 2);
                        $remaining -= $lineBrutto;

                        $lineRate  = $noVat ? 0.0 : (isset($pi->vat) ? (float)$pi->vat->rate : 0.0);
                        $lineNetto = $lineRate > 0 ? round($lineBrutto / (1 + $lineRate / 100), 2) : round($lineBrutto, 2);
                        $lineTax   = round($lineBrutto - $lineNetto, 2);

                        $piName      = trim((string)($pi->name ?? '')) ?: $lineNameFallbackE;
                        $piGtu       = trim((string)($pi->gtu_code ?? ''));
                        $piVatCodeId = $noVat ? null : $pi->vat_code_id;

                        $contents[] = [
                            'vat_code_id'      => $piVatCodeId,
                            'name'             => $piName,
                            'product_desc'     => '',
                            'quantity'         => 1,
                            'unit'             => 'szt.',
                            'price'            => $lineNetto,
                            'discount_percent' => 0,
                            'netto'            => $lineNetto,
                            'brutto'           => $lineBrutto,
                            'vat_amount'       => $noVat ? null : $lineTax,
                            'gtu_code'         => $piGtu !== '' ? $piGtu : null,
                        ];
                        $sumNet   += $lineNetto;
                        $sumTax   += $lineTax;
                        $sumGross += $lineBrutto;
                    }
                }

                // Bind parent proforma + currency from proforma
                $data['parent_id'] = $proforma->id;
                $data['currency'] = (string)($proforma->currency ?? ($data['currency'] ?? $invoice->currency ?? 'PLN'));
                // Snapshot nabywcy: jeśli brak w formularzu, uzupełnij z proformy
                if (empty($data['invoice_contractor']['name'])) {
                    $data['invoice_contractor'] = [
                        'name'                 => (string)($proforma->invoice_contractor?->name ?? ''),
                        'nip'                  => (string)($proforma->invoice_contractor?->nip ?? ''),
                        'street'               => (string)($proforma->invoice_contractor?->street ?? ''),
                        'zip'                  => (string)($proforma->invoice_contractor?->zip ?? ''),
                        'city'                 => (string)($proforma->invoice_contractor?->city ?? ''),
                        'country'              => (string)($proforma->invoice_contractor?->country ?? 'PL'),
                        'account_number'       => (string)($proforma->invoice_contractor?->account_number ?? ''),
                        'email'                => (string)($proforma->invoice_contractor?->email ?? '') ?: null,
                        'phone'                => (string)($proforma->invoice_contractor?->phone ?? '') ?: null,
                        'vat_prefix'           => $proforma->invoice_contractor?->vat_prefix ?: null,
                        'vat_eu'               => $proforma->invoice_contractor?->vat_eu ?: null,
                        'eori'                 => $proforma->invoice_contractor?->eori ?: null,
                        'tax_id_other'         => $proforma->invoice_contractor?->tax_id_other ?: null,
                        'tax_id_other_country' => $proforma->invoice_contractor?->tax_id_other_country ?: null,
                    ];
                    if (empty($data['invoice_contractor']['name']) && !empty($proforma->contractor_id)) {
                        $LiveContractors = $this->fetchTable('Contractors');
                        $liveCtr = $LiveContractors->find()
                            ->select(['name','nip','street','city','postal_code','country','email','phone','vat_prefix','vat_eu','eori','tax_id_other','tax_id_other_country'])
                            ->where(['id' => $proforma->contractor_id, 'company_id' => $companyId])
                            ->first();
                        if ($liveCtr) {
                            $data['invoice_contractor'] = [
                                'name'                 => (string)($liveCtr->name ?? ''),
                                'nip'                  => (string)($liveCtr->nip ?? ''),
                                'street'               => (string)($liveCtr->street ?? ''),
                                'zip'                  => (string)($liveCtr->postal_code ?? ''),
                                'city'                 => (string)($liveCtr->city ?? ''),
                                'country'              => (string)($liveCtr->country ?? 'PL'),
                                'account_number'       => (string)($liveCtr->account_number ?? ''),
                                'email'                => $liveCtr->email ?: null,
                                'phone'                => $liveCtr->phone ?: null,
                                'vat_prefix'           => $liveCtr->vat_prefix ?: null,
                                'vat_eu'               => $liveCtr->vat_eu ?: null,
                                'eori'                 => $liveCtr->eori ?: null,
                                'tax_id_other'         => $liveCtr->tax_id_other ?: null,
                                'tax_id_other_country' => $liveCtr->tax_id_other_country ?: null,
                            ];
                        }
                    }
                }
            } elseif (($invoice->type ?? null) === 'margin'
                || (($invoice->type ?? null) === 'correction'
                    && (!empty($data['margin_type']) || array_key_exists('margin_vat_rate', $data) || $this->resolveMarginType($invoice) !== ''))) {
                // Procedura marży (także KOREKTA marży): pozycje zawierają WARTOŚĆ BRUTTO (sprzedaż)
                // oraz CENA NABYCIA (BRUTTO) tylko do wyliczeń — bez VAT (vat_code_id=null), inaczej dostają błędnie 0%.
                $totalSales = 0.0; $totalPurchase = 0.0;
                foreach ($items as $idx => $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name === '') {
                        if ($this->rowHasUserData((array)$row)) {
                            throw new \RuntimeException('Pozycja #' . ((int)$idx + 1) . ': uzupełnij nazwę produktu/usługi.');
                        }
                        continue;
                    }

                    $qty      = $num($row['quantity'] ?? 0);
                    $saleUnit = $num($row['price'] ?? 0);          // brutto/szt.
                    $buyUnit  = $num($row['purchase_price'] ?? 0); // brutto/szt.

                    $lineGross = round($qty * $saleUnit, 2);
                    $lineBuy   = round($qty * $buyUnit, 2);

                    $totalSales    += $lineGross;
                    $totalPurchase += $lineBuy;

                    $contents[] = [
                        'vat_code_id'      => null,
                        'name'             => $name,
                        'product_desc'     => (string)($row['product_desc'] ?? ''),
                        'quantity'         => $qty,
                        'unit'             => (string)($row['unit'] ?? 'szt.'),
                        'price'            => $saleUnit,
                        'purchase_price'   => $buyUnit,
                        'discount_percent' => 0,
                        'netto'            => $lineGross,
                        'brutto'           => $lineGross,
                        'gtu_code'         => (string)($row['gtu_code'] ?? ''),
                        'gtin'             => (string)($row['gtin'] ?? ''),
                        'cn_code'          => (string)($row['cn_code'] ?? ''),
                        'pkob'             => (string)($row['pkob'] ?? ''),
                        'is_attachment15'  => !empty($row['is_attachment15']) ? 1 : 0,
                        'excise_amount'    => !empty($row['excise_amount']) ? (float)$row['excise_amount'] : null,
                        'procedure_marking' => (string)($row['procedure_marking'] ?? ''),
                        // FA(3)
                        'uu_id'            => (string)($row['uu_id'] ?? \Cake\Utility\Text::uuid()),
                        'line_date'        => !empty($row['line_date']) ? $row['line_date'] : null,
                        'pkwiu'            => (string)($row['pkwiu'] ?? ''),
                    ];
                }

                // Walidacja procedury marży
                if ($totalPurchase <= 0.0) {
                    $this->Flash->error('Faktura marżowa wymaga podania ceny nabycia (pola „Cena nabycia") dla co najmniej jednej pozycji.');
                    $this->set(compact('invoice','vats','vatRatesMap'));
                    return null;
                }

                $rate = (float)($data['margin_vat_rate'] ?? 23);
                if ($rate < 0.0 || $rate > 100.0) {
                    $rate = 23.0;
                }
                $marginGross = max(0.0, $totalSales - $totalPurchase);
                $sumTax   = $rate > 0 ? round($marginGross * ($rate / (100.0 + $rate)), 2) : 0.0;
                $sumGross = round($totalSales, 2);
                $sumNet   = round($sumGross - $sumTax, 2);
            } else {
                foreach ($items as $idx => $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name === '') {
                        if ($this->rowHasUserData((array)$row)) {
                            throw new \RuntimeException('Pozycja #' . ((int)$idx + 1) . ': uzupełnij nazwę produktu/usługi.');
                        }
                        continue;
                    }
                    $qty   = $num($row['quantity'] ?? 0);
                    $price = $num($row['price'] ?? 0);
                    $disc  = $num($row['discount_percent'] ?? 0);
                    $vatId = $row['vat_code_id'] ?? null;
                    $rate  = $noVat ? 0.0 : (float)($vatRatesMap[$vatId] ?? 0);

                    // price_mode: 'gross' → price to cena brutto/szt., obróć do netto
                    $priceIsGross = !$noVat && $rate > 0 && (($row['price_mode'] ?? 'net') === 'gross');
                    if ($priceIsGross) {
                        $netUnitPrice = round($price / (1 + $rate / 100), 2);
                        $brutto       = round($qty * $price * (1 - ($disc / 100)), 2);
                        $netto        = round($brutto / (1 + $rate / 100), 2);
                        $tax          = round($brutto - $netto, 2);
                    } else {
                        $netUnitPrice  = $price;
                        $unitAfterDisc = $price * (1 - ($disc / 100));
                        $netto  = round($qty * $unitAfterDisc, 2);
                        $tax    = $noVat ? 0.0 : round($netto * ($rate / 100), 2);
                        $brutto = round($netto + $tax, 2);
                    }

                    $sumNet   += $netto;
                    $sumTax   += $tax;
                    $sumGross += $brutto;

                    $contents[] = [
                        'vat_code_id'      => $noVat ? null : $vatId,
                        'name'             => $name,
                        'product_desc'     => (string)($row['product_desc'] ?? ''),
                        'quantity'         => $qty,
                        'unit'             => (string)($row['unit'] ?? 'szt.'),
                        'price'            => $netUnitPrice,  // zawsze netto/szt.
                        'purchase_price'   => !empty($row['purchase_price']) ? $num($row['purchase_price']) : null,
                        'discount_percent' => $disc,
                        'discount_amount'  => $disc > 0 ? round($qty * $netUnitPrice * ($disc / 100), 2) : null,
                        'netto'            => $netto,
                        'brutto'           => $brutto,
                        'gtu_code'         => (string)($row['gtu_code'] ?? ''),
                        'gtin'             => (string)($row['gtin'] ?? ''),
                        'cn_code'          => (string)($row['cn_code'] ?? ''),
                        'pkob'             => (string)($row['pkob'] ?? ''),
                        'is_attachment15'  => !empty($row['is_attachment15']) ? 1 : 0,
                        'excise_amount'    => !empty($row['excise_amount']) ? (float)$row['excise_amount'] : null,
                        'procedure_marking' => (string)($row['procedure_marking'] ?? ''),
                        // FA(3)
                        'uu_id'            => (string)($row['uu_id'] ?? \Cake\Utility\Text::uuid()),
                        'vat_amount'       => $noVat ? null : $tax, // = brutto - netto (tryb brutto) / round(netto*stawka) (tryb netto) — spójne z brutto i P_15
                        'line_date'        => !empty($row['line_date']) ? $row['line_date'] : null,
                        'pkwiu'            => (string)($row['pkwiu'] ?? ''),
                        'gross_unit_price' => $priceIsGross ? round($price, 2) : round($netUnitPrice * (1 + ($rate / 100)), 2),
                    ];
                }
            }

            if (empty($contents)) {
                $this->Flash->error('Dodaj co najmniej jedną pozycję.');
                $this->set(compact('invoice','vats','vatRatesMap'));
                return null;
            }

            // Podsumowania i status płatności
            $alreadypaid = $num($data['alreadypaid'] ?? ($invoice->alreadypaid ?? 0));
            $total       = round($sumGross, 2);
            $netto       = round($sumNet, 2);
            $tax         = $noVat ? 0.0 : round($sumTax, 2);
            $remaining   = round($total - $alreadypaid, 2);

            $paymentstate = 'unpaid';
            if ($remaining <= 0.0) {
                $paymentstate = 'paid';
                $remaining = 0.0;
            } elseif ($alreadypaid > 0.0) {
                $paymentstate = 'partial';
            }
            if (!empty($data['paymentdate']) && $paymentstate !== 'paid') {
                try {
                    $due = new \DateTimeImmutable((string)$data['paymentdate']);
                    $today = new \DateTimeImmutable('today');
                    if ($due < $today) $paymentstate = 'overdue';
                } catch (\Throwable) { /* ignore */ }
            }
            // Faktura zaliczkowa/końcowa: zaznaczony checkbox "advance_paid" = opłacona
            // (sam checkbox wystarcza; datę otrzymania domyślamy na datę wystawienia gdy puste).
            if (in_array($kind, ['advance', 'final'], true) && !empty($data['advance_paid'])) {
                $paymentstate = 'paid';
                $alreadypaid  = $total;
                $remaining    = 0.0;
                if (empty($data['advance_received_date'])) {
                    $data['advance_received_date'] = !empty($data['date']) ? $data['date'] : date('Y-m-d');
                }
                if (empty($data['paid_at'])) {
                    $data['paid_at'] = $data['advance_received_date'];
                }
                if (empty($data['partial_paid_at'])) {
                    $data['partial_paid_at'] = $data['advance_received_date'];
                }
            }

            // Seria → invoice_series_id: preferuj UUID, fallback na nazwę
            $editSeriesUuid = trim((string)($data['invoice_series_id'] ?? ''));
            $editSeriesName = trim((string)($data['series'] ?? ''));
            if ($editSeriesUuid !== '' || $editSeriesName !== '') {
                $InvoiceSeriesTable = $this->fetchTable('InvoiceSeries');
                $seriesQuery = $InvoiceSeriesTable->find()
                    ->where(['InvoiceSeries.company_id' => $invoice->company_id]);

                if ($editSeriesUuid !== '') {
                    $seriesQuery->where(['InvoiceSeries.id' => $editSeriesUuid]);
                } else {
                    // Legacy: lookup po nazwie (backward compat ze starymi formularzami)
                    $seriesQuery->where(['InvoiceSeries.name' => $editSeriesName]);
                }

                $ser = $seriesQuery->first();
                if ($ser) {
                    $data['invoice_series_id'] = $ser->id;
                    // Zachowaj nazwę serii w encji dla wyświetlenia
                    if (isset($data['series'])) {
                        $data['series'] = (string)$ser->name;
                    }
                }
            }

            // Aktualizacja pól pochodnych od daty/num.
            if (!empty($data['date'])) {
                $dateObject = new \DateTime($data['date']);
                $data['day'] = (int) $dateObject->format('d');
                $data['month'] = (int) $dateObject->format('m');
                $data['year'] = (int) $dateObject->format('Y');
                $data['day_year'] = (int) $dateObject->format('z') + 1;
            }
            if (!empty($data['fullnumber'])) {
                $data['number'] = $this->extractNumberFromFullnumber($data['fullnumber']);
            }

            // Waluta + kurs: gdy zmienia się data wystawienia/sprzedaży, przelicz z NBP.
            // Wyjątek: jeśli użytkownik *jawnie* nadpisał ORAZ kurs ORAZ datę kursu (oba różne od starych) — uszanuj.
            // UWAGA: Cake\I18n\Date::__toString() używa locale (PL → DD.MM.YYYY).
            // Zawsze używamy ->format('Y-m-d'), żeby validator dostał ISO.
            $toIso = static function ($v): ?string {
                if (empty($v)) return null;
                if (is_object($v) && method_exists($v, 'format')) return $v->format('Y-m-d');
                $s = (string)$v;
                if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $s, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
                return null;
            };

            $cur          = strtoupper((string)($data['currency'] ?? $invoice->currency ?? 'PLN'));
            $issueDateStr = !empty($data['date'])      ? $toIso($data['date'])      : $toIso($invoice->date);
            $soldDateStr  = !empty($data['sold_date']) ? $toIso($data['sold_date']) : $toIso($invoice->sold_date ?? null);
            $baseDateStr  = $soldDateStr ?: $issueDateStr;

            $oldIssueDate = $toIso($invoice->date);
            $oldSoldDate  = $toIso($invoice->sold_date ?? null);
            $oldBaseDate  = $oldSoldDate ?: $oldIssueDate;
            $datesChanged = ($baseDateStr !== $oldBaseDate);

            $formCurrencyDate = !empty($data['currency_date']) ? $toIso($data['currency_date']) : null;
            $formFxRaw = (isset($data['currency_exchange']) && $data['currency_exchange'] !== '')
                ? $data['currency_exchange']
                : ((isset($data['fx_rate']) && $data['fx_rate'] !== '') ? $data['fx_rate'] : null);
            $formRate = $formFxRaw !== null ? max(0.0001, $num($formFxRaw)) : null;

            $oldCurrencyDate = $toIso($invoice->currency_date ?? null);
            $oldRate         = !empty($invoice->currency_exchange) ? (float)$invoice->currency_exchange : null;

            $rateChangedExplicitly         = $formRate !== null && ($oldRate === null || abs($formRate - $oldRate) > 0.00001);
            $currencyDateChangedExplicitly = $formCurrencyDate !== null && $formCurrencyDate !== $oldCurrencyDate;
            $explicitOverride = $rateChangedExplicitly && $currencyDateChangedExplicitly;

            $currencyExchange = $formRate ?? ($oldRate ?? 1.0);
            $currencyDate     = $formCurrencyDate ?? $oldCurrencyDate;

            if ($cur === 'PLN') {
                $currencyExchange = 1.0;
                $currencyDate = $currencyDate ?: $issueDateStr;
            } elseif ($explicitOverride) {
                // użytkownik jawnie nadpisał — nie ruszamy
            } elseif ($datesChanged) {
                try {
                    $base = new \DateTimeImmutable($baseDateStr);
                    $nbp = $this->computeNbpAvgRate($cur, $base);
                    if (!empty($nbp['success']) && !empty($nbp['rate'])) {
                        $currencyExchange = (float)$nbp['rate'];
                        $currencyDate     = $toIso($nbp['effectiveDate'] ?? null) ?: ($currencyDate ?: $issueDateStr);
                    }
                } catch (\Throwable) { /* zostaw poprzednie wartości */ }
            } else {
                // daty się nie zmieniły — uzupełnij brakujące tak jak w handleAdd
                if ($currencyExchange <= 0.0001) {
                    try {
                        $base = new \DateTimeImmutable($baseDateStr);
                        $nbp = $this->computeNbpAvgRate($cur, $base);
                        if (!empty($nbp['success']) && !empty($nbp['rate'])) {
                            $currencyExchange = (float)$nbp['rate'];
                            $currencyDate     = $toIso($nbp['effectiveDate'] ?? null) ?: ($currencyDate ?: $issueDateStr);
                        }
                    } catch (\Throwable) { /* ignore */ }
                } elseif (empty($currencyDate)) {
                    try {
                        $base = new \DateTimeImmutable($baseDateStr);
                        $nbp = $this->computeNbpAvgRate($cur, $base);
                        $currencyDate = $toIso($nbp['effectiveDate'] ?? null) ?: $issueDateStr;
                    } catch (\Throwable) { $currencyDate = $issueDateStr; }
                }
            }

            // Dane do patchowania faktury (whitelist)
            $invoicePatch = [
                'paymentmethod' => $data['paymentmethod'] ?? $invoice->paymentmethod,
                'paymentdate'   => $data['paymentdate'] ?? $invoice->paymentdate,
                'paymentstate'  => $paymentstate,
                'date'          => $data['date'] ?? $invoice->date,
                'sold_date'             => !empty($data['sold_date']) ? $data['sold_date'] : ($invoice->sold_date ?? null),
                'advance_received_date' => !empty($data['advance_received_date']) ? $data['advance_received_date'] : ($invoice->advance_received_date ?? null),
                'paid_at'         => !empty($data['paid_at']) ? $data['paid_at'] : ($invoice->paid_at ?? null),
                'partial_paid_at' => !empty($data['partial_paid_at']) ? $data['partial_paid_at'] : ($invoice->partial_paid_at ?? null),
                'total'         => $total,
                'netto'         => $netto,
                'tax'           => $tax,
                'alreadypaid'   => $alreadypaid,
                'remaining'     => $remaining,
                'fullnumber'    => $invoice->fullnumber ?? null, // zachowaj istniejący numer, nie nadpisuj
                // NrFaKorygowany — poprawny numer faktury w korekcie (opcjonalny, tylko dla korekt)
                'corrected_invoice_number' => ($kind === 'correction')
                    ? (array_key_exists('corrected_invoice_number', $data)
                        ? (trim((string)$data['corrected_invoice_number']) ?: null)
                        : ($invoice->corrected_invoice_number ?? null))
                    : null,
                'currency'      => $cur,
                'currency_date' => $currencyDate,
                'currency_exchange' => $currencyExchange,
                'description'   => $data['description'] ?? $invoice->description,
                'issuer'        => $data['issuer'] ?? $invoice->issuer,
                // New flags
                'is_receipt_invoice' => (isset($data['is_receipt_invoice']) && !empty($data['is_receipt_invoice']))
                    || (!empty($data['flags']['fp']))
                    ? 1 : ($invoice->is_receipt_invoice ?? 0),
                'is_split_payment'   => isset($data['is_split_payment']) ? (int)!empty($data['is_split_payment'])     : ($invoice->is_split_payment ?? 0),
            ];
            // KSeF Adnotacje — zawsze nadpisuj z danych formularza
            $invoicePatch['annotations']             = !empty($data['annotations']) ? json_encode($data['annotations'], JSON_UNESCAPED_UNICODE) : null;
            $invoicePatch['annotations_tax_free']    = !empty($data['annotations_tax_free']) ? (string)$data['annotations_tax_free'] : null;
            $invoicePatch['annotations_tax_free_field'] = !empty($data['annotations_tax_free_field']) ? (string)$data['annotations_tax_free_field'] : null;

            // FA(3) — opcjonalne pola edycji
            foreach (['period_from','period_to','wz_number','correction_reason','place_of_issue','payment_link'] as $k) {
                if (array_key_exists($k, $data)) { $invoicePatch[$k] = $data[$k]; }
            }
            if (array_key_exists('correction_type', $data)) {
                $invoicePatch['correction_type'] = in_array((string)($data['correction_type'] ?? ''), ['1','2','3'])
                    ? (string)$data['correction_type']
                    : null;
            }
            if (array_key_exists('footer_text', $data)) {
                $raw = $data['footer_text'];
                if (is_array($raw)) {
                    $lines = array_values(array_filter(array_map('trim', $raw), fn($v) => $v !== ''));
                    $invoicePatch['footer_text'] = !empty($lines) ? json_encode($lines, JSON_UNESCAPED_UNICODE) : null;
                } else {
                    $invoicePatch['footer_text'] = !empty($raw) ? (string)$raw : null;
                }
            }
            // FA(3) LOW — pola rozszerzone
            foreach (['skonto_conditions','skonto_amount','status_info_podatnika','is_new_transport_wdt','p_42_5','order_total_gross'] as $k) {
                if (array_key_exists($k, $data)) { $invoicePatch[$k] = $data[$k]; }
            }
            // Statusy nabywcy (JST / Członek grupy VAT) — zawsze nadpisuj
            $invoicePatch['buyer_is_jst']       = !empty($data['buyer_is_jst']) ? 1 : 0;
            $invoicePatch['buyer_in_vat_group'] = !empty($data['buyer_in_vat_group']) ? 1 : 0;
            // FA(3) LOW — warunki transakcji z POST (tc_umowy[], tc_zamowienia[])
            $tcUmowy = array_values(array_filter(array_map('trim', (array)($data['tc_umowy'] ?? []))));
            $tcZamowienia = array_values(array_filter(array_map('trim', (array)($data['tc_zamowienia'] ?? []))));
            if (!empty($tcUmowy) || !empty($tcZamowienia)) {
                $invoicePatch['transaction_conditions_json'] = json_encode(
                    array_filter(['Umowy' => $tcUmowy, 'Zamowienia' => $tcZamowienia]),
                    JSON_UNESCAPED_UNICODE
                );
            } elseif (array_key_exists('tc_umowy', $data) || array_key_exists('tc_zamowienia', $data)) {
                $invoicePatch['transaction_conditions_json'] = null;
            }
            // Rachunek bankowy
            if (array_key_exists('company_bank_account_id', $data)) {
                $invoicePatch['company_bank_account_id'] = !empty($data['company_bank_account_id']) ? (string)$data['company_bank_account_id'] : null;
            }
            // Optional: allow updating receipt details if provided
            foreach (['receipt_number','receipt_date'] as $k) {
                if (array_key_exists($k, $data)) {
                    $invoicePatch[$k] = $data[$k];
                }
            }
            foreach (['number','day','month','year','day_year','invoice_series_id'] as $k) {
                if (array_key_exists($k, $data)) { $invoicePatch[$k] = $data[$k]; }
            }
            // Procedura marży — pozwól zmienić przy edycji (faktura/korekta marży)
            if (array_key_exists('margin_type', $data)) {
                $invoicePatch['margin_type'] = !empty($data['margin_type']) ? (string)$data['margin_type'] : null;
            }

            // Powiązanie z proformą przy zaliczce/końcowej
            if (in_array($kind, ['advance','final'], true) && array_key_exists('parent_id', $data)) {
                $invoicePatch['parent_id'] = $data['parent_id'];
            }
            // Dla zaliczki/końcowej: zaktualizuj typ jeśli $isFinal zmienił się względem zapisanego
            if (in_array($kind, ['advance','final'], true) && isset($isFinal)) {
                $invoicePatch['type']     = $isFinal ? 'final' : 'advance';
                $invoicePatch['is_final'] = $isFinal ? 1 : 0;
            }

            $conn = $this->Invoices->getConnection();
            $conn->begin();
            try {
                // Zapisz fakturę
                $invoice = $this->Invoices->patchEntity($invoice, $invoicePatch);
                if (!$this->Invoices->save($invoice)) {
                    throw new \RuntimeException('Błąd zapisu faktury: ' . json_encode($invoice->getErrors()));
                }

                // Snapshot sprzedawcy — aktualizuj rachunek bankowy jeśli zmieniony
                // Aktualizacja snapshotu rachunku — nie tylko IBAN ale też nazwa, opis, swift, bank_correspondent.
                // Bierzemy dane z CompanyBankAccounts na podstawie wybranego company_bank_account_id;
                // form-overrides (jeśli user ręcznie wpisał) mają priorytet.
                $newBankIban   = trim((string)($data['invoice_company_detail']['bank_account'] ?? ''));
                $newBankName   = null;
                $newBankDesc   = null;
                $newSwift      = null;
                $newBankCorr   = null;
                if (!empty($data['company_bank_account_id'])) {
                    try {
                        $cbaRec = $this->fetchTable('CompanyBankAccounts')->find()
                            ->select(['iban', 'bank_name', 'bank_desc', 'swift', 'bank_correspondent'])
                            ->where(['id' => $data['company_bank_account_id'], 'company_id' => $companyId])
                            ->first();
                        if ($cbaRec) {
                            if ($newBankIban === '') {
                                $newBankIban = trim((string)($cbaRec->iban ?? ''));
                            }
                            $newBankName = (string)($cbaRec->bank_name ?? '');
                            $newBankDesc = (string)($cbaRec->bank_desc ?? '');
                            $newSwift    = (string)($cbaRec->swift ?? '');
                            $newBankCorr = (string)($cbaRec->bank_correspondent ?? '');
                        }
                    } catch (\Throwable) {}
                }
                // Form-overrides (user może wpisać ręcznie inną nazwę/opis)
                if (isset($data['invoice_company_detail']['bank_name']) && $data['invoice_company_detail']['bank_name'] !== '') {
                    $newBankName = (string)$data['invoice_company_detail']['bank_name'];
                }
                if (isset($data['invoice_company_detail']['bank_desc']) && $data['invoice_company_detail']['bank_desc'] !== '') {
                    $newBankDesc = (string)$data['invoice_company_detail']['bank_desc'];
                }
                if (isset($data['invoice_company_detail']['swift']) && $data['invoice_company_detail']['swift'] !== '') {
                    $newSwift = (string)$data['invoice_company_detail']['swift'];
                }

                try {
                    $IcdTable = $this->fetchTable('InvoiceCompanyDetails');
                    $icd = $IcdTable->find()->where(['invoice_id' => $invoice->id])->first();
                    if ($icd) {
                        if ($newBankIban !== '')          { $icd->set('bank_account', $newBankIban); }
                        if ($newBankName !== null)        { $icd->set('bank_name', $newBankName); }
                        if ($newBankDesc !== null)        { $icd->set('bank_desc', $newBankDesc); }
                        if ($newSwift !== null)           { $icd->set('swift', $newSwift); }
                        if ($newBankCorr !== null)        { $icd->set('bank_correspondent', $newBankCorr); }
                        $IcdTable->save($icd);
                    }
                } catch (\Throwable) {}

                // Snapshot nabywcy
                if (!empty($data['invoice_contractor'])) {
                    $InvoiceContractors = $this->fetchTable('InvoiceContractors');
                    $ctr = $InvoiceContractors->find()->where(['invoice_id' => $invoice->id])->first() ?? $InvoiceContractors->newEmptyEntity();
                    $ctrData = (array)$data['invoice_contractor'] + ['invoice_id' => $invoice->id];
                    $ctr = $InvoiceContractors->patchEntity($ctr, $ctrData);
                    if (!$InvoiceContractors->save($ctr)) {
                        throw new \RuntimeException('Błąd zapisu danych nabywcy');
                    }
                }

                // Snapshot odbiorcy (opcjonalny)
                if (!empty($data['invoice_recipient']) && !empty(trim((string)($data['invoice_recipient']['name'] ?? '')))) {
                    $InvoiceRecipients = $this->fetchTable('InvoiceRecipients');
                    $rec = $InvoiceRecipients->find()->where(['invoice_id' => $invoice->id])->first() ?? $InvoiceRecipients->newEmptyEntity();
                    $recData = (array)$data['invoice_recipient'] + ['invoice_id' => $invoice->id];
                    $rec = $InvoiceRecipients->patchEntity($rec, $recData);
                    if (!$InvoiceRecipients->save($rec)) {
                        throw new \RuntimeException('Błąd zapisu danych odbiorcy');
                    }
                } elseif (isset($data['invoice_recipient']) && empty(trim((string)($data['invoice_recipient']['name'] ?? '')))) {
                    // Jeśli nazwa pusta — usuń odbiorcę jeśli istnieje
                    $InvoiceRecipients = $this->fetchTable('InvoiceRecipients');
                    $InvoiceRecipients->deleteAll(['invoice_id' => $invoice->id]);
                }

                // Pozycje: prosty model replace-all
                $InvoiceContents = $this->fetchTable('InvoiceContents');
                $InvoiceContents->deleteAll(['invoice_id' => $invoice->id]);
                foreach ($contents as $idx => $c) {
                    $ent = $InvoiceContents->newEmptyEntity();
                    $c['invoice_id'] = $invoice->id;
                    $c['sort_order'] = $idx;
                    $ent = $InvoiceContents->patchEntity($ent, $c);
                    if (!$InvoiceContents->save($ent)) {
                        throw new \RuntimeException('Błąd zapisu pozycji faktury');
                    }
                }

                // FA(3) — relacyjne tabele: charges, factor_banks, authorized_entities,
                // order_lines, additional_descriptions (add_desc). Replace-all wewnątrz metody.
                $this->saveInvoiceRelationalFa3((string)$invoice->id, $data);

                $conn->commit();

                // Jeśli faktura nie ma jeszcze numeru i KSeF jest WYŁĄCZONY — nadaj numer teraz
                if (empty($invoice->fullnumber) && !$this->isKsefModeEnabled((string)$companyId)) {
                    try {
                        $this->ensureInvoiceNumberForSend($invoice, (string)$companyId);
                    } catch (\Throwable $numErr) {
                        $this->Flash->warning('Faktura zapisana, ale nie udało się nadac numeru: ' . $numErr->getMessage());
                    }
                }

                $this->Flash->success('Faktura została zaktualizowana.');
                return $this->redirect(['action' => 'view', $invoice->id]);
            } catch (\Throwable $e) {
                $conn->rollback();
                $this->Flash->error('Błąd zapisu: ' . $e->getMessage());
            }
        }

        $companies = $this->Invoices->Companies->find('list', limit: 200)->all();
        $parentInvoices = $this->Invoices->ParentInvoices->find('list', limit: 200)->all();
        $this->set(compact('invoice', 'companies', 'parentInvoices', 'vats', 'vatRatesMap'));
    }

    public function editVat($id = null)
    {
        return $this->edit($id);
    }

    public function editCurrency($id = null)
    {
        return $this->edit($id);
    }

    public function editNoVat($id = null)
    {
        return $this->edit($id);
    }

    public function editProforma($id = null)
    {
        return $this->edit($id);
    }

    public function editAdvance($id = null)
    {
        return $this->edit($id);
    }

    public function editCorrection($id = null)
    {
        return $this->edit($id);
    }

    public function editMargin($id = null)
    {
        return $this->edit($id);
    }

    public function editInternal($id = null)
    {
        return $this->edit($id);
    }

    public function editInternalEvidence($id = null)
    {
        return $this->edit($id);
    }

    public function editOss($id = null)
    {
        return $this->edit($id);
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoice = $this->Invoices->get($id);
        $wasDraft = empty($invoice->fullnumber);
        $ok = (bool)$this->Invoices->delete($invoice);

        $isAjax = $this->request->is('ajax')
            || str_contains((string)$this->request->getHeaderLine('Accept'), 'application/json');

        if ($isAjax) {
            $this->viewBuilder()->setClassName('Json');
            $this->set('success', $ok);
            $this->set('error', $ok ? null : 'Nie udało się usunąć faktury.');
            $this->viewBuilder()->setOption('serialize', ['success', 'error']);
            return null;
        }

        if ($ok) {
            $this->Flash->success(__('The invoice has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => $wasDraft ? 'drafts' : 'index']);
    }
private function makeClient(string $environment): KsefClient
    {
        $baseUrl = ($environment === 'prod')
            ? 'https://api.ksef.mf.gov.pl/v2'
            : 'https://api-test.ksef.mf.gov.pl/v2';

        return new KsefClient(new DbKsefTokenStorage(), $baseUrl);
    }

    private function sessionService(string $environment): KsefSessionService
    {
        $client  = $this->makeClient($environment);
        $storage = new DbKsefTokenStorage();
        return new KsefSessionService($client, $storage);
    }

    /** GET /invoices/ksef/download?env=test|prod&ksef_number=... */
    public function downloadKsef()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException('Brak company_id w tożsamości.');
        }

        $env = (string)($this->request->getQuery('env') ?? 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $ksefNumber = (string)$this->request->getQuery('ksef_number');
        if ($ksefNumber === '') {
            throw new BadRequestException('Podaj ksef_number.');
        }

        // Użyj oficjalnego klienta n1ebieski (obsługa auto-refresh tokena)
        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $client = $ksef->buildClient((string)$companyId, $environment);
        $req = new \N1ebieski\KSEFClient\Requests\Invoices\Download\DownloadRequest(
            \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
        );
        $body = $client->invoices()->download($req)->body();

        // Robust type sniff: strip UTF-8 BOM and whitespace before checking
        $bin = (string)$body;
        if (str_starts_with($bin, "\xEF\xBB\xBF")) { // UTF-8 BOM
            $bin = substr($bin, 3);
        }
        $binTrim = ltrim($bin);
        $head = substr($binTrim, 0, 200);
        $isPdf = str_starts_with($binTrim, '%PDF');
        $isXml = str_starts_with($binTrim, '<') || (bool)preg_match('/<\?xml|<[^>]+>/', $head);

    $filename = $isPdf ? 'invoice.pdf' : ($isXml ? 'invoice.xml' : 'invoice.bin');
    $type     = $isPdf ? 'application/pdf' : ($isXml ? 'application/xml' : 'application/octet-stream');

        return $this->response
            ->withType($type)
            ->withHeader('Content-Length', (string)strlen($body))
            ->withDownload($filename)
            ->withStringBody($body);
    }

    /** GET /invoices/download-upo?env=test|prod&ksef_number=... */
    public function downloadUpo()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException('Brak company_id w tożsamości.');
        }

        $env = (string)($this->request->getQuery('env') ?? 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $ksefNumber = (string)$this->request->getQuery('ksef_number');
        if ($ksefNumber === '') {
            throw new BadRequestException('Podaj ksef_number.');
        }

        // 1) Ustal session_reference: query → rekord faktury
        $sessionRef = (string)$this->request->getQuery('session_reference', '');
        if ($sessionRef === '') {
            try {
                $invoiceRow = $this->Invoices->find()
                    ->select(['id','ksef_desc','ksef_session_reference'])
                    ->where(['company_id' => $companyId, 'ksef_number' => $ksefNumber])
                    ->first();
                if (!empty($invoiceRow?->ksef_session_reference)) {
                    $sessionRef = (string)$invoiceRow->ksef_session_reference;
                } elseif ($invoiceRow && !empty($invoiceRow->ksef_desc)) {
                    if (preg_match('/S=([A-Z0-9\-]+)/i', (string)$invoiceRow->ksef_desc, $m)) {
                        $sessionRef = (string)$m[1];
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }
        if ($sessionRef === '') {
            throw new BadRequestException('Brak session_reference (S=...). UPO wymaga referencji sesji.');
        }

        // 2) Pobierz UPO przez oficjalnego klienta (preferuj XML)
        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $client = $ksef->buildClient((string)$companyId, $environment);
        $req = new \N1ebieski\KSEFClient\Requests\Sessions\Invoices\KsefUpo\KsefUpoRequest(
            referenceNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber::from($sessionRef),
            ksefNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
        );
        $body = (string)$client->sessions()->invoices()->ksefUpo($req)->body();

        // Detekcja formatu
        $bin = $body;
        if (str_starts_with($bin, "\xEF\xBB\xBF")) { $bin = substr($bin, 3); } // UTF-8 BOM
        $trim = ltrim($bin);
        $head = substr($trim, 0, 200);
        $isPdf = str_starts_with($trim, '%PDF');
        $isXml = str_starts_with($trim, '<') || (bool)preg_match('/<\?xml|<[^>]+>/', $head);

        // 3) Jeśli już mamy PDF z KSeF – zwróć bezpośrednio
        if ($isPdf) {
            $this->logKsefSendEvent((string)$companyId, (string)($invoiceRow->id ?? ''), 'upo_downloaded', [
                'source' => 'downloadUpo',
                'format' => 'pdf',
                'ksef_number' => $ksefNumber,
            ]);
            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Length', (string)strlen($bin))
                ->withDownload('UPO.pdf')
                ->withStringBody($bin);
        }

        // 4) Jeżeli mamy XML – wyślij do lokalnego API, aby wygenerować PDF
        if ($isXml) {
            $this->storeUpoXmlForInvoice((string)$companyId, $ksefNumber, $bin);
            $this->logKsefSendEvent((string)$companyId, (string)($invoiceRow->id ?? ''), 'upo_downloaded', [
                'source' => 'downloadUpo',
                'format' => 'xml',
                'ksef_number' => $ksefNumber,
            ]);
            try {
                $apiUrl = getenv('UPO_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/upo';
                $http = new \Cake\Http\Client(['timeout' => 60]);
                $apiRes = $http->post($apiUrl, ['xml' => $bin], ['type' => 'json']);

                if ($apiRes->getStatusCode() === 200) {
                    $pdf = (string)$apiRes->getBody();
                    if ($pdf !== '') {
                        return $this->response
                            ->withType('application/pdf')
                            ->withHeader('Content-Length', (string)strlen($pdf))
                            ->withDownload('UPO.pdf')
                            ->withStringBody($pdf);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback poniżej
            }

            // Fallback: zwróć XML, jeśli API nie zwróciło poprawnego PDF
            return $this->response
                ->withType('application/xml')
                ->withHeader('Content-Length', (string)strlen($bin))
                ->withDownload('UPO.xml')
                ->withStringBody($bin);
        }

        // 5) Nie rozpoznano typu – wyślij binarkę
        return $this->response
            ->withType('application/octet-stream')
            ->withHeader('Content-Length', (string)strlen($bin))
            ->withDownload('UPO.bin')
            ->withStringBody($bin);
    }

    /** GET /invoices/download-upo-pdf?env=test|prod&ksef_number=...&session_reference=... */
    public function downloadUpoPdf()
    {
        $this->request->allowMethod(['get']);

        // Always use Dompdf to render the result
        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setOptions([
                'pdfConfig' => [
                    'filename' => 'UPO.pdf',
                    'download' => true,
                    'orientation' => 'portrait',
                    'paper' => 'A4',
                    'engine' => 'CakePdf.DomPdf',
                ],
            ]);

        $env = (string)($this->request->getQuery('env') ?? 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';
        $ksefNumber = (string)$this->request->getQuery('ksef_number');
        if ($ksefNumber === '') {
            throw new BadRequestException('Podaj ksef_number.');
        }
        $sessionRef = (string)$this->request->getQuery('session_reference', '');

        // Best-effort fill sessionRef from DB when missing
        if ($sessionRef === '') {
            try {
                $identity  = $this->getRequest()->getAttribute('identity');
                $companyId = $identity?->get('company_id');
                if ($companyId) {
                    $invoiceRow = $this->Invoices->find()
                        ->select(['id','ksef_desc','ksef_session_reference'])
                        ->where(['company_id' => $companyId, 'ksef_number' => $ksefNumber])
                        ->first();
                    if (!empty($invoiceRow?->ksef_session_reference)) {
                        $sessionRef = (string)$invoiceRow->ksef_session_reference;
                    } elseif ($invoiceRow && !empty($invoiceRow->ksef_desc)) {
                        if (preg_match('/S=([A-Z0-9\-]+)/i', (string)$invoiceRow->ksef_desc, $m)) {
                            $sessionRef = (string)$m[1];
                        }
                    }
                }
            } catch (\Throwable) { /* ignore */ }
        }

        // Try to fetch UPO (XML or PDF) to transform with XSL when possible
        $xml = null; $rawPdf = null;
        try {
            $identity  = $this->getRequest()->getAttribute('identity');
            $companyId = $identity?->get('company_id');
            if ($companyId && $sessionRef !== '') {
                $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
                $client = $ksef->buildClient((string)$companyId, $environment);
                $req = new \N1ebieski\KSEFClient\Requests\Sessions\Invoices\KsefUpo\KsefUpoRequest(
                    referenceNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber::from($sessionRef),
                    ksefNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
                );
                $body = (string)$client->sessions()->invoices()->ksefUpo($req)->body();
                $bin = str_starts_with($body, "\xEF\xBB\xBF") ? substr($body, 3) : $body;
                $trim = ltrim($bin);
                if (str_starts_with($trim, '%PDF')) { $rawPdf = $bin; }
                elseif (str_starts_with($trim, '<')) { $xml = $bin; }
            }
        } catch (\Throwable) { /* ignore and try fallback */ }

        // Brak legacy fallbacku: w N1 kliencie bez session_reference nie pobierzemy UPO XML

        // If KSeF already provided PDF, pass-through directly
        if (is_string($rawPdf) && $rawPdf !== '') {
            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Length', (string)strlen($rawPdf))
                ->withDownload('UPO.pdf')
                ->withStringBody($rawPdf);
        }

        // If we have XML and an XSL file, transform to HTML and render as PDF via Dompdf
        if (is_string($xml) && $xml !== '') {
            $xslPath = ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'xsl' . DIRECTORY_SEPARATOR . 'upo.xsl';
            if (is_file($xslPath)) {
                try {
                    $html = $this->transformXmlWithXsl($xml, $xslPath);
                    if (is_string($html) && $html !== '') {
                        $this->viewBuilder()->setTemplate('upo_xsl');
                        $this->set('htmlContent', $html);
                        return null; // PdfView will render transformed HTML
                    }
                } catch (\Throwable) { /* fallback to skeleton */ }
            }
        }

        // Fallback: render a minimal XML skeleton in the PDF
        $this->viewBuilder()->setTemplate('upo_pdf');
        $tsPlaceholder = 'YYYY-MM-DDThh:mm:ssZ';
        $xmlSkeleton = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<UPO xmlns=\"http://ksef.mf.gov.pl/upo\">\n" .
            "  <ReferenceNumber>" . ($sessionRef !== '' ? htmlspecialchars($sessionRef, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '') . "</ReferenceNumber>\n" .
            "  <KSeFNumber>" . htmlspecialchars($ksefNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</KSeFNumber>\n" .
            "  <Timestamp>" . $tsPlaceholder . "</Timestamp>\n" .
            "  <ProcessingIdentifier></ProcessingIdentifier>\n" .
            "  <Hash>\n" .
            "    <Algorithm>SHA-256</Algorithm>\n" .
            "    <Value></Value>\n" .
            "  </Hash>\n" .
            "</UPO>\n";
        $this->set(compact('ksefNumber', 'sessionRef', 'environment', 'xmlSkeleton'));
        return null;
    }

    /** GET /invoices/upo-html?env=test|prod&ksef_number=...&session_reference=... */
    public function upoHtml()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException('Brak company_id w tożsamości.');
        }

        $env = (string)($this->request->getQuery('env') ?? 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $ksefNumber = (string)$this->request->getQuery('ksef_number');
        if ($ksefNumber === '') {
            throw new BadRequestException('Podaj ksef_number.');
        }

        // session_reference opcjonalny; spróbuj odczytać z DB, gdy brak w query
        $sessionRef = (string)$this->request->getQuery('session_reference', '');
        if ($sessionRef === '') {
            try {
                $invoiceRow = $this->Invoices->find()
                    ->select(['id','ksef_desc','ksef_session_reference'])
                    ->where(['company_id' => $companyId, 'ksef_number' => $ksefNumber])
                    ->first();
                if (!empty($invoiceRow?->ksef_session_reference)) {
                    $sessionRef = (string)$invoiceRow->ksef_session_reference;
                } elseif ($invoiceRow && !empty($invoiceRow->ksef_desc)) {
                    if (preg_match('/S=([A-Z0-9\-]+)/i', (string)$invoiceRow->ksef_desc, $m)) {
                        $sessionRef = (string)$m[1];
                    }
                }
            } catch (\Throwable) { /* ignore */ }
        }

        $xml = null;
        // Preferowana ścieżka po sesji
        if ($sessionRef !== '') {
            try {
                $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
                $client = $ksef->buildClient((string)$companyId, $environment);
                $req = new \N1ebieski\KSEFClient\Requests\Sessions\Invoices\KsefUpo\KsefUpoRequest(
                    referenceNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber::from($sessionRef),
                    ksefNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
                );
                $body = $client->sessions()->invoices()->ksefUpo($req)->body();
                $bin = (string)$body; if (str_starts_with($bin, "\xEF\xBB\xBF")) { $bin = substr($bin, 3); }
                $binTrim = ltrim($bin);
                if (str_starts_with($binTrim, '<')) { $xml = $bin; }
            } catch (\Throwable) { /* fallback below */ }
        }

        // Brak legacy fallbacku: w N1 kliencie bez session_reference nie pobierzemy UPO XML

        // Jeśli mamy XML i dostępny jest arkusz XSL, spróbuj przekształcenia do HTML
        if (is_string($xml) && $xml !== '') {
            $xslPath = ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'xsl' . DIRECTORY_SEPARATOR . 'upo.xsl';
            if (is_file($xslPath)) {
                try {
                    $html = $this->transformXmlWithXsl($xml, $xslPath);
                    if (is_string($html) && $html !== '') {
                        return $this->response
                            ->withType('text/html; charset=utf-8')
                            ->withStringBody($html);
                    }
                } catch (\Throwable $e) {
                    // Ignoruj błąd XSLT – pokaż fallback HTML
                }
            }
        }

        // Fallback: nasz prosty HTML
        $this->set(compact('ksefNumber','sessionRef','environment','xml'));
        $this->viewBuilder()->setTemplate('upo_html');
        return null;
    }

    /**
     * Transformuje XML przy użyciu XSL (jeśli rozszerzenie XSL jest dostępne).
     * Zwraca wynik HTML lub null w razie błędu.
     */
    protected function transformXmlWithXsl(string $xml, string $xslPath): ?string
    {
        if (!class_exists('XSLTProcessor')) {
            return null; // brak rozszerzenia XSL
        }
        $domXml = new \DOMDocument('1.0', 'UTF-8');
        $domXsl = new \DOMDocument('1.0', 'UTF-8');
        // Wyczyść ewentualny BOM i niepoprawne sekwencje UTF-8
        $xmlStr = $this->utf8Clean($xml);
        $prevUseErrors = libxml_use_internal_errors(true);
        // Bez NOENT (bez ekspansji encji) dla bezpieczeństwa
        $okXml = $domXml->loadXML($xmlStr, LIBXML_NONET | LIBXML_NOCDATA);
        $okXsl = $domXsl->load($xslPath, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);
        if (!$okXml || !$okXsl) {
            return null;
        }
        $proc = new \XSLTProcessor();
        $proc->importStylesheet($domXsl);
        $out = $proc->transformToXML($domXml);
        if (!is_string($out)) {
            return null;
        }
        // Upewnij się, że mamy czysty UTF-8 bez BOM i bez niedozwolonych znaków sterujących
        return $this->utf8Clean($out);
    }

    /**
     * Czyści łańcuch do bezpiecznego UTF-8: usuwa BOM, usuwa niedozwolone znaki sterujące,
     * i wycina niekompletne sekwencje multibajtowe (IGNORE) w razie potrzeby.
     */
    protected function utf8Clean(string $s): string
    {
        // Usuń BOM
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = substr($s, 3);
        }
        // Usuń ASCII control chars z wyjątkiem \t, \n, \r
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s) ?? $s;
        // Jeżeli niepoprawne UTF-8, spróbuj wyciąć niedozwolone sekwencje
        if (!preg_match('//u', $s)) {
            $tmp = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($tmp !== false) {
                $s = $tmp;
            } else {
                // Spróbuj konwersji z Windows-1250 (częsty w PL) → UTF-8
                $tmp2 = @iconv('Windows-1250', 'UTF-8//IGNORE', $s);
                if ($tmp2 !== false) {
                    $s = $tmp2;
                }
            }
        }
        return $s;
    }

    /**
     * Bezpieczne parsowanie wybranych pól z UPO XML (ignoruje przestrzenie nazw)
     */
    private function parseUpoXmlSafe(string $xml): array
    {
        $result = [
            'ksef_number' => null,
            'reference_number' => null,
            'timestamp' => null,
            'processing_identifier' => null,
            'document_hash' => null,
            'document_type' => null,
        ];

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            $xp = new \DOMXPath($dom);

            $get = function(array $candidates) use ($xp): ?string {
                foreach ($candidates as $name) {
                    $q = "//*[local-name()='{$name}'][1]";
                    $n = $xp->query($q)->item(0);
                    if ($n && $n->textContent !== '') {
                        return trim($n->textContent);
                    }
                }
                return null;
            };

            $result['ksef_number'] = $get(['KSeFNumber','KsefNumber','KSeF','KSeFReferenceNumber']);
            $result['reference_number'] = $get(['ReferenceNumber','SessionReference','Reference']);
            $result['timestamp'] = $get(['Timestamp','Date','DateTime','ReceptionTimestamp','ReceiptDateTime']);
            $result['processing_identifier'] = $get(['ProcessingIdentifier','ProcessingId','Identifier']);
            $result['document_hash'] = $get(['DocumentHash','Hash','DigestValue']);
            $result['document_type'] = $get(['DocumentType','Type']);
        } catch (\Throwable) {
            // ignore and return partials
        }
        return $result;
    }

    /**
     * Usuwa BOM, znaki sterujące i niepoprawne sekwencje, zwraca bezpieczne UTF-8.
     */
    private function utf8Safe(?string $s): string
    {
        if ($s === null || $s === '') { return ''; }
        $s = (string)$s;
        // Usuń BOM (U+FEFF)
        $s = preg_replace('/\x{FEFF}/u', '', $s);
        // Spróbuj wymusić poprawne UTF-8, ignorując niepoprawne bajty
        $c = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($c === false) {
            $enc = mb_detect_encoding($s, ['UTF-8','Windows-1250','ISO-8859-2','ISO-8859-1','ASCII'], true) ?: 'UTF-8';
            $c = @iconv($enc, 'UTF-8//IGNORE', $s);
        }
        if ($c === false) { $c = $s; }
        // Usuń niewidoczne znaki sterujące poza tab/newline/CR
        $c = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/", '', $c);
        return (string)$c;
    }

    /**
     * Sanitizes values of array to safe UTF-8 strings.
     */
    private function utf8CleanArray(array $a): array
    {
        foreach ($a as $k => $v) {
            $a[$k] = is_string($v) ? $this->utf8Safe($v) : (is_null($v) ? '' : $v);
        }
        return $a;
    }

    /**
     * GET /invoices/download-fa3-xml/{id}
     * Generuje lokalny FA(3) XML na podstawie zapisanej faktury i zwraca jako plik do pobrania.
     */
    public function downloadFa3Xml(string $id): Response
    {
        $this->request->allowMethod(['get']);

        try {
            $invoice = $this->Invoices->get($id, contain: ['InvoiceContractors','InvoiceCompanyDetails','InvoiceContents' => ['Vats'], 'Companies']);
        } catch (\Throwable $e) {
            throw new BadRequestException('Nie znaleziono faktury.');
        }

        try {
            $xml = $this->buildFa3Xml($invoice);
        } catch (\Throwable $e) {
            throw new BadRequestException('Nie udało się wygenerować FA(3) XML: ' . $e->getMessage());
        }

        $fname = 'invoice_' . ($invoice->fullnumber ?: $invoice->id) . '.xml';
        return $this->response
            ->withType('application/xml')
            ->withDownload($fname)
            ->withStringBody($xml);
    }

    /** GET /invoices/ksef/metadata?env=test|prod&days=7&pageSize=5 */
    public function metadataKsef()
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException('Brak company_id w tożsamości.');
        }

        $env = (string)($this->request->getQuery('env') ?? 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $days = max(1, (int)($this->request->getQuery('days') ?? 7));
        $pageSize = max(1, min(100, (int)($this->request->getQuery('pageSize') ?? 5)));

        // auto-login w tle
        $sess = $this->sessionService($environment);
        $sess->ensureAccessToken((string)$companyId, $environment);

        $client = $this->makeClient($environment);
        $contextKey = "company:{$companyId}:{$environment}";
        $filters = [
            'subjectType' => 'Subject1',
            'dateRange'   => [
                'from'     => gmdate('c', strtotime("-{$days} days")),
                'to'       => gmdate('c'),
                'dateType' => 'Issue',
            ],
        ];
        $meta = $client->queryInvoiceMetadata($contextKey, $filters, pageOffset: 0, pageSize: $pageSize);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'environment' => $environment,
                'company_id'  => $companyId,
                'filters'     => $filters,
                'metadata'    => $meta,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Wyciąga numer z pełnego numeru faktury (np. "FV/2025/01/0001" -> 1)
     */
    /** @deprecated Użyj $this->numberingService->extractNumberFromFullnumber() */
    private function extractNumberFromFullnumber(string $fullnumber): int
    {
        return $this->numberingService->extractNumberFromFullnumber($fullnumber);
    }

    /** @deprecated Użyj $this->numberingService->formatPattern() */
    private function formatInvoicePattern(string $template, int $number, string $issueDate): string
    {
        return $this->numberingService->formatPattern($template, $number, $issueDate);
    }

    /**
     * Lookup contractor email by invoice_id (via NIP match in contractors table)
     */
    public function contractorEmailLookup(string $id)
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $invoice = $this->Invoices->get($id, [
            'contain' => ['InvoiceContractors'],
            'conditions' => ['Invoices.company_id' => $companyId],
        ]);

        $ct     = $invoice->invoice_contractor ?? null;
        $emails = [];

        // 1) e-mail ze snapshotu faktury
        $snapshotEmail = trim((string)($ct->email ?? ''));
        if ($snapshotEmail !== '') {
            $emails[] = $snapshotEmail;
        }

        // 2) lookup w tabeli contractors po NIP + company_id
        $vatid = trim((string)($ct->vatid ?? $ct->nip ?? ''));
        $nip   = preg_replace('/\D+/', '', $vatid);
        if ($nip !== '') {
            try {
                $Contractors = $this->fetchTable('Contractors');
                $contractor  = $Contractors->find()
                    ->select(['email'])
                    ->where(['company_id' => $companyId, 'nip' => $nip])
                    ->first();
                $contractorEmail = trim((string)($contractor->email ?? ''));
                if ($contractorEmail !== '' && !in_array($contractorEmail, $emails, true)) {
                    $emails[] = $contractorEmail;
                }
            } catch (\Throwable) {}
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['emails' => $emails]));
    }

    /**
     * GET /invoices/ksef-send-logs/:id.json
     * Returns KSeF send log entries for a single invoice (user-friendly format).
     */
    public function ksefSendLogs(string $id)
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $isAdmin   = (bool)($identity?->get('is_admin') ?? false) || strtolower((string)($identity?->get('role') ?? '')) === 'admin';

        // Admin może przeglądać logi dowolnej faktury bez filtra company_id
        $invoiceQuery = $this->Invoices->find()
            ->select(['id', 'fullnumber', 'ksef_status', 'ksef_number'])
            ->where(['Invoices.id' => $id]);
        if (!$isAdmin) {
            $invoiceQuery->where(['Invoices.company_id' => $companyId]);
        }
        $invoice = $invoiceQuery->first();

        if ($invoice === null) {
            return $this->response->withStatus(404)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nie znaleziono faktury.']));
        }

        $conn = ConnectionManager::get('default');
        // Admin widzi logi bez filtra company_id
        if ($isAdmin) {
            $rows = $conn->execute(
                'SELECT event_type, status_code, message, payload, created
                 FROM ksef_send_logs
                 WHERE invoice_id = :id
                 ORDER BY created ASC',
                ['id' => $id],
                ['id' => 'string']
            )->fetchAll('assoc');
        } else {
            $rows = $conn->execute(
                'SELECT event_type, status_code, message, payload, created
                 FROM ksef_send_logs
                 WHERE invoice_id = :id AND company_id = :cid
                 ORDER BY created ASC',
                ['id' => $id, 'cid' => (string)$companyId],
                ['id' => 'string', 'cid' => 'string']
            )->fetchAll('assoc');
        }

        $eventLabels = [
            'send_attempt'   => ['label' => 'Próba wysyłki',       'icon' => 'ri-send-plane-line',       'color' => 'info'],
            'send_success'   => ['label' => 'Wysłano pomyślnie',   'icon' => 'ri-check-double-line',     'color' => 'success'],
            'send_error'     => ['label' => 'Błąd wysyłki',        'icon' => 'ri-close-circle-line',     'color' => 'danger'],
            'send_exception' => ['label' => 'Wyjątek wysyłki',     'icon' => 'ri-bug-line',              'color' => 'danger'],
            'blocked'        => ['label' => 'Zablokowano',         'icon' => 'ri-forbid-line',           'color' => 'warning'],
            'xml_missing'    => ['label' => 'Brak XML',            'icon' => 'ri-file-damage-line',      'color' => 'warning'],
            'xml_invalid'    => ['label' => 'Niepoprawny XML',     'icon' => 'ri-file-warning-line',     'color' => 'warning'],
            'xsd_invalid'    => ['label' => 'Błąd walidacji XSD',  'icon' => 'ri-file-warning-line',     'color' => 'danger'],
            'upo_downloaded' => ['label' => 'Pobrano UPO',         'icon' => 'ri-download-2-line',       'color' => 'success'],
            'ksef_sync'      => ['label' => 'Synchronizacja z KSeF', 'icon' => 'ri-refresh-line',        'color' => 'success'],
        ];

        $logs = [];
        foreach ($rows as $row) {
            $type  = (string)($row['event_type'] ?? '');
            $meta  = $eventLabels[$type] ?? ['label' => $type, 'icon' => 'ri-history-line', 'color' => 'secondary'];
            $payload = [];
            if (!empty($row['payload'])) {
                $payload = json_decode((string)$row['payload'], true) ?? [];
            }
            $created = (string)($row['created'] ?? '');
            $createdFormatted = '';
            if ($created !== '') {
                try {
                    $dt = new \DateTimeImmutable($created);
                    $createdFormatted = $dt->format('d.m.Y H:i:s');
                } catch (\Throwable) {
                    $createdFormatted = $created;
                }
            }
            $logs[] = [
                'event_type'       => $type,
                'label'            => $meta['label'],
                'icon'             => $meta['icon'],
                'color'            => $meta['color'],
                'status_code'      => (string)($row['status_code'] ?? ''),
                'message'          => (string)($row['message'] ?? $payload['message'] ?? ''),
                'env'              => (string)($payload['env'] ?? ''),
                'ksef_number'      => (string)($payload['ksefNumber'] ?? $payload['ksef_number'] ?? ''),
                'exception_class'  => (string)($payload['exception_class'] ?? ''),
                'http_code'        => (string)($payload['http_code'] ?? ''),
                'file'             => (string)($payload['file'] ?? ''),
                'ksef_context'     => $payload['ksef_context'] ?? null,
                'created'          => $createdFormatted,
            ];
        }

        return $this->response->withType('application/json')->withStringBody(json_encode([
            'success'      => true,
            'invoice_id'   => $id,
            'fullnumber'   => (string)($invoice->fullnumber ?? ''),
            'ksef_status'  => (string)($invoice->ksef_status ?? ''),
            'ksef_number'  => (string)($invoice->ksef_number ?? ''),
            'logs'         => $logs,
        ]));
    }

    /**
     * GET /invoices/download-upo-by-invoice/:id
     * Downloads UPO for an invoice identified by its internal UUID.
     * Looks up ksef_number, session_reference and environment from DB/logs.
     */
    public function downloadUpoByInvoice(string $id): Response
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $invoice = $this->Invoices->find()
            ->select(['id', 'ksef_number', 'ksef_session_reference', 'ksef_desc'])
            ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            throw new \Cake\Http\Exception\NotFoundException('Faktura nie istnieje.');
        }

        $ksefNumber = trim((string)($invoice->ksef_number ?? ''));
        if ($ksefNumber === '') {
            throw new \Cake\Http\Exception\BadRequestException('Ta faktura nie ma nadanego numeru KSeF.');
        }

        // Resolve session_reference from invoice or ksef_desc
        $sessionRef = trim((string)($invoice->ksef_session_reference ?? ''));
        if ($sessionRef === '' && !empty($invoice->ksef_desc)) {
            if (preg_match('/S=([A-Z0-9\-]+)/i', (string)$invoice->ksef_desc, $m)) {
                $sessionRef = (string)$m[1];
            }
        }
        // Try to resolve session_reference and env from last send_success log entry
        $environment = 'prod';
        try {
            $conn = ConnectionManager::get('default');
            $logRow = $conn->execute(
                "SELECT payload FROM ksef_send_logs
                 WHERE invoice_id = :iid AND company_id = :cid AND event_type = 'send_success'
                 ORDER BY created DESC LIMIT 1",
                ['iid' => $id, 'cid' => $companyId],
                ['iid' => 'string', 'cid' => 'string']
            )->fetch('assoc');
            if (!empty($logRow['payload'])) {
                $p = json_decode((string)$logRow['payload'], true) ?? [];
                if ($sessionRef === '' && !empty($p['session_reference'])) {
                    $sessionRef = (string)$p['session_reference'];
                }
                if (($p['env'] ?? '') === 'prod') {
                    $environment = 'prod';
                }
            }
        } catch (\Throwable) {}

        if ($sessionRef === '') {
            throw new \Cake\Http\Exception\BadRequestException('Brak referencji sesji KSeF. UPO nie może zostać pobrane.');
        }

        // Build and execute UPO download (reuse same logic as downloadUpo)
        $ksef   = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $client = $ksef->buildClient($companyId, $environment);
        $req = new \N1ebieski\KSEFClient\Requests\Sessions\Invoices\KsefUpo\KsefUpoRequest(
            referenceNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber::from($sessionRef),
            ksefNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
        );
        $body = (string)$client->sessions()->invoices()->ksefUpo($req)->body();

        $bin = $body;
        if (str_starts_with($bin, "\xEF\xBB\xBF")) { $bin = substr($bin, 3); }
        $trim = ltrim($bin);
        $head = substr($trim, 0, 200);
        $isPdf = str_starts_with($trim, '%PDF');
        $isXml = str_starts_with($trim, '<') || (bool)preg_match('/<\?xml|<[^>]+>/', $head);

        $this->logKsefSendEvent($companyId, $id, 'upo_downloaded', [
            'source'      => 'downloadUpoByInvoice',
            'format'      => $isPdf ? 'pdf' : ($isXml ? 'xml' : 'bin'),
            'ksef_number' => $ksefNumber,
            'env'         => $environment,
        ]);

        if ($isPdf) {
            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Length', (string)strlen($bin))
                ->withDownload('UPO_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ksefNumber) . '.pdf')
                ->withStringBody($bin);
        }

        if ($isXml) {
            $this->storeUpoXmlForInvoice($companyId, $ksefNumber, $bin);
            try {
                $apiUrl = getenv('UPO_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/upo';
                $http   = new \Cake\Http\Client(['timeout' => 60]);
                $apiRes = $http->post($apiUrl, ['xml' => $bin], ['type' => 'json']);
                if ($apiRes->getStatusCode() === 200) {
                    $pdf = (string)$apiRes->getBody();
                    if ($pdf !== '') {
                        return $this->response
                            ->withType('application/pdf')
                            ->withHeader('Content-Length', (string)strlen($pdf))
                            ->withDownload('UPO_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ksefNumber) . '.pdf')
                            ->withStringBody($pdf);
                    }
                }
            } catch (\Throwable) {}

            return $this->response
                ->withType('application/xml')
                ->withHeader('Content-Length', (string)strlen($bin))
                ->withDownload('UPO_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ksefNumber) . '.xml')
                ->withStringBody($bin);
        }

        return $this->response
            ->withType('application/octet-stream')
            ->withHeader('Content-Length', (string)strlen($bin))
            ->withDownload('UPO.bin')
            ->withStringBody($bin);
    }

    /**
     * Email invoice PDF to client
     */
    public function emailInvoice(string $id)
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $invoice = $this->Invoices->get($id, [
            'contain' => [
                'InvoiceContractors',
                'InvoiceContents' => ['Vats'],
                'Companies',
                'InvoiceCompanyDetails',
            ],
            'conditions' => ['Invoices.company_id' => $companyId],
        ]);

        // Zbierz i waliduj adresy e-mail
        $rawEmails = (array)($this->request->getData('emails') ?? []);
        $emails = [];
        foreach ($rawEmails as $e) {
            $e = trim((string)$e);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }
        if (empty($emails)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak poprawnego adresu e-mail.']));
        }

        // Wygeneruj PDF
        $pdfContent = '';
        try {
            $xml = $this->buildFa3Xml($invoice);
            if (is_string($xml) && trim($xml) !== '') {
                $isDraft = ((string)($invoice->workflow_status ?? '')) === 'draft';
                $apiUrl = $isDraft
                    ? (getenv('INVOICE_DRAFT_API_URL') ?: 'https://faktury24-draft.3ckstudio.pl/api/invoice')
                    : (getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice');
                $seller   = $invoice->invoice_company_detail ?? null;
                $nip      = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
                $issueDate = $invoice->date ? $invoice->date->format('d-m-Y') : '';
                $invRef   = (string)($invoice->ksef_invoice_reference ?? '');
                $qrCode   = ($this->shouldShowKsefQr($invoice) && $nip !== '' && $issueDate !== '' && $invRef !== '')
                    ? ('https://ksef.mf.gov.pl/client-app/invoice/' . $nip . '/' . $issueDate . '/' . $invRef)
                    : '';
                $http = new \Cake\Http\Client(['timeout' => 60]);
                $resp = $http->post($apiUrl, [
                    'xml' => $xml,
                    'additionalData' => [
                        'nrKSeF'    => (string)($invoice->ksef_number ?? ''),
                        'qrCode'    => $qrCode,
                        'isPreview' => $isDraft,
                    ],
                ], ['type' => 'json']);
                if ($resp->getStatusCode() === 200) {
                    $pdfContent = (string)$resp->getBody();
                }
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('[emailInvoice] PDF generation failed for invoice ' . $id . ': ' . $e->getMessage());
        }

        if ($pdfContent === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Nie udało się wygenerować PDF faktury.']));
        }

        $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
        $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';
        $sellerName = trim((string)($invoice->invoice_company_detail?->name ?? $invoice->company?->name ?? ''));
        $buyerName  = trim((string)($invoice->invoice_contractor?->name ?? ''));
        $subject    = 'Faktura ' . $fullnumber . ($sellerName !== '' ? ' od ' . $sellerName : '');

        // Wyślij e-mail do każdego odbiorcy
        try {
            $mailer = new \Cake\Mailer\Mailer('default');
            $mailer->setTo($emails[0]);
            if (count($emails) > 1) {
                $mailer->addCc(array_slice($emails, 1));
            }
            $mailer->addBcc('k@3ck.pl');
            $mailer->setSubject($subject);
            $mailer->setEmailFormat('html');
            $mailer->setAttachments([
                $filename => ['data' => $pdfContent, 'mimetype' => 'application/pdf'],
            ]);
            $mailer->viewBuilder()->setLayout('default')->setTemplate('invoice_email');
            $mailer->setViewVars([
                'invoice'    => $invoice,
                'fullnumber' => $fullnumber,
                'sellerName' => $sellerName,
            ]);
            $mailer->deliver();
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('[emailInvoice] Sending failed for invoice ' . $id . ': ' . $e->getMessage());
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Błąd wysyłki: ' . $e->getMessage()]));
        }

        // Zapisz znacznik czasu wysyłki
        $this->Invoices->updateAll(
            ['email_sent_at' => new \DateTimeImmutable()],
            ['id' => $invoice->id]
        );

        // Zapisz ślad wysyłki w kolejce e-maili (status 'sent'), aby lista faktur
        // pokazała status wysyłki. Ręczna wysyłka idzie bezpośrednio (nie przez
        // processEmailQueue), więc wpis dokładamy tutaj — po odbiorcy.
        try {
            /** @var \App\Model\Table\InvoiceEmailQueueTable $Queue */
            $Queue = $this->fetchTable('InvoiceEmailQueue');
            $now   = new \DateTimeImmutable();
            foreach ($emails as $addr) {
                $entry = $Queue->newEntity([
                    'invoice_id'   => (string)$invoice->id,
                    'company_id'   => (string)$companyId,
                    'email'        => $addr,
                    'status'       => 'sent',
                    'attempts'     => 1,
                    'sent_at'      => $now,
                    'scheduled_at' => $now->format('Y-m-d H:i:s'),
                ]);
                $Queue->save($entry);
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('[emailInvoice] Nie udało się zapisać statusu wysyłki (InvoiceEmailQueue): ' . $e->getMessage());
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'sent_to' => $emails]));
    }

    /**
     * Print invoice as PDF
     */
    public function print($id = null)
    {
        $this->request->allowMethod(['get']);

        $identity = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $invoice = $this->Invoices->get($id, [
            'contain' => [
                'InvoiceContractors',
                'InvoiceContents' => ['Vats'],
                'Companies',
                'InvoiceCompanyDetails'
            ],
            'conditions' => [
                'Invoices.company_id' => $companyId
            ]
        ]);

        // Proforma NIE jest dokumentem KSeF — pobieramy ją systemowym layoutem (krok 3,
        // szablon CakePdf 'print' → element Invoices/print_preview), tak jak w widoku /invoices/view,
        // a NIE przez generator FA(3) (ksef-pdf-generator), który renderowałby ją jak fakturę VAT.
        $isProforma = (string)($invoice->type ?? '') === 'proforma';

        // 1) Zbuduj FA(3) XML (pomijamy dla proformy)
        $xml = '';
        if (!$isProforma) {
            try {
                $xml = $this->buildFa3Xml($invoice);
            } catch (\Throwable $e) {
                \Cake\Log\Log::error('[print] buildFa3Xml failed for invoice ' . $id . ': ' . $e->getMessage(), ['print_pdf']);
                $xml = '';
            }
        }

        // 2) Wyślij do lokalnego API, aby wygenerować PDF (nie dla proformy)
        if (!$isProforma && is_string($xml) && trim($xml) !== '') {
            try {
                $lang = (string)$this->request->getQuery('lang');
                $isDraft = ((string)($invoice->workflow_status ?? '')) === 'draft';

                if ($lang === 'en') {
                    $apiUrl = getenv('INVOICE_EN_API_URL') ?: 'https://faktury24-en.3ckstudio.pl/api/invoice';
                } elseif ($isDraft) {
                    $apiUrl = getenv('INVOICE_DRAFT_API_URL') ?: 'https://faktury24-draft.3ckstudio.pl/api/invoice';
                } else {
                    $apiUrl = getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice';
                }
                $http = new \Cake\Http\Client(['timeout' => 60]);
                // Build QR code URL for KSeF client app
                $seller = $invoice->invoice_company_detail ?? null;
                $nip = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
                $issueDate = $invoice->date ? $invoice->date->format('d-m-Y') : '';
                $ksefEnv = (\Cake\Core\Configure::read('Ksef.env') === 'test') ? 'test' : 'prod';
                $qrHost = ($ksefEnv === 'test') ? 'https://qr-test.ksef.mf.gov.pl' : 'https://qr.ksef.mf.gov.pl';
                // QR hash musi być z dokładnych bajtów XML wysłanych do KSeF (zapisany przy wysyłce).
                // Fallback: policz z bieżącego XML — ale NIE dla draftów (draft nie jest w KSeF,
                // więc kod QR weryfikacyjny byłby mylący/niepoprawny → drafty bez QR).
                $storedHash = (string)($invoice->ksef_xml_hash ?? '');
                $xmlHash = $storedHash !== ''
                    ? $storedHash
                    : ((!$isDraft && is_string($xml) && trim($xml) !== '')
                        ? rtrim(strtr(base64_encode(hash('sha256', $xml, true)), '+/', '-_'), '=')
                        : '');
                $qrCode = ($this->shouldShowKsefQr($invoice) && $nip !== '' && $issueDate !== '' && $xmlHash !== '')
                    ? ($qrHost . '/invoice/' . $nip . '/' . $issueDate . '/' . $xmlHash)
                    : '';

                $payload = [
                    'xml' => $xml,
                    'additionalData' => [
                        'nrKSeF' => (string)($invoice->ksef_number ?? ''),
                        'qrCode' => $qrCode,
                        'isPreview' => $isDraft,
                        'lang' => $lang === 'en' ? 'en' : 'pl',
                    ],
                ];
                $resp = $http->post($apiUrl, $payload, ['type' => 'json']);
                if ($resp->getStatusCode() === 200) {
                    $pdf = (string)$resp->getBody();
                    if ($pdf !== '') {
                        $download = (bool)$this->request->getQuery('download');
                        $disposition = $download ? 'attachment' : 'inline';
                        $prefix = $lang === 'en' ? 'invoice_' : 'faktura_';
                        $filename = $prefix . ((string)($invoice->fullnumber ?: $invoice->id)) . '.pdf';
                        return $this->response
                            ->withType('application/pdf')
                            ->withHeader('Content-Disposition', $disposition . '; filename="' . $filename . '"')
                            ->withHeader('Content-Length', (string)strlen($pdf))
                            ->withStringBody($pdf);
                    }
                }
            } catch (\Throwable $e) {
                \Cake\Log\Log::error('[print] API call failed for invoice ' . $id . ' url=' . ($apiUrl ?? '?') . ': ' . $e->getMessage(), ['print_pdf']);
                // Fallback to legacy rendering below
            }
        }

        // 3) Fallback: stare renderowanie przez CakePdf
        // For final invoices, collect related advances for settlement list in print view
        if (!empty($invoice->type) && $invoice->type === 'final' && !empty($invoice->parent_id)) {
            $advances = $this->Invoices->find()
                ->select(['id','fullnumber','date','total'])
                ->where([
                    'company_id' => $companyId,
                    'parent_id' => $invoice->parent_id,
                    'type' => 'advance'
                ])
                ->orderAsc('date')
                ->all()
                ->toList();
            $invoice->set('advances', $advances);
        }

        // If foreign currency, compute NBP average rate for printing purposes
        $nbp = null;
        try {
            $cur = strtoupper((string)($invoice->currency ?? 'PLN'));
            if ($cur !== 'PLN' && !empty($invoice->date)) {
                $baseDate = $invoice->date;
                $nbp = $this->computeNbpAvgRate($cur, $baseDate);
            }
        } catch (\Throwable) { /* ignore for printing */ }

        $download = (bool)$this->request->getQuery('download');
        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setTemplate('print')
            ->setOptions([
                'pdfConfig' => [
                    'filename' => 'faktura_' . ($invoice->fullnumber ?: $invoice->id) . '.pdf',
                    'download' => $download,
                    'orientation' => 'portrait',
                    'paper' => 'A4',
                ],
            ]);

        $this->set(compact('invoice','nbp'));
        return null;
    }

    /**
     * API: Generowanie PDF z przesłanego XML (FA(3)).
     * POST /api/invoices/print
     *
     * Obsługuje:
     * - JSON: {"xml":"<...>", "additionalData": {...}, "filename":"...pdf", "download": true|false }
     * - XML body: Content-Type: application/xml (wtedy body jest XML-em)
     */
    public function printApi()
    {
        $this->request->allowMethod(['post']);

        $contentType = strtolower((string)$this->request->getHeaderLine('Content-Type'));
        $xml = '';
        $additionalData = [];
        $filename = '';
        $download = false;

        if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
            $xml = (string)$this->request->getBody();
        } else {
            $data = $this->request->getData();
            $xml = (string)($data['xml'] ?? '');
            $additionalData = is_array($data['additionalData'] ?? null) ? (array)$data['additionalData'] : [];
            $filename = (string)($data['filename'] ?? '');
            $download = (bool)($data['download'] ?? false);
        }

        if (!is_string($xml) || trim($xml) === '') {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => 'Brak XML w żądaniu (pole "xml" lub body application/xml).',
                ], JSON_UNESCAPED_UNICODE));
        }

        $apiUrl = getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice';

        try {
            $http = new \Cake\Http\Client(['timeout' => 60]);
            $payload = ['xml' => $xml];
            if (!empty($additionalData)) {
                $payload['additionalData'] = $additionalData;
            }

            $resp = $http->post($apiUrl, $payload, ['type' => 'json']);
            if ($resp->getStatusCode() !== 200) {
                return $this->response
                    ->withStatus(502)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error' => 'Generator PDF zwrócił błąd (HTTP ' . $resp->getStatusCode() . ').',
                    ], JSON_UNESCAPED_UNICODE));
            }

            $pdf = (string)$resp->getBody();
            if ($pdf === '') {
                return $this->response
                    ->withStatus(502)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error' => 'Generator PDF zwrócił pustą odpowiedź.',
                    ], JSON_UNESCAPED_UNICODE));
            }

            $filename = trim($filename) !== '' ? $filename : 'document.pdf';
            $filename = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $filename);
            if (!str_ends_with(strtolower($filename), '.pdf')) {
                $filename .= '.pdf';
            }

            $downloadQuery = $this->request->getQuery('download');
            if ($downloadQuery !== null) {
                $download = (bool)$downloadQuery;
            }

            $disposition = $download ? 'attachment' : 'inline';

            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Disposition', $disposition . '; filename="' . $filename . '"')
                ->withHeader('Content-Length', (string)strlen($pdf))
                ->withStringBody($pdf);
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Bulk actions for invoices
     */
    public function bulkAction()
    {
        $this->request->allowMethod(['post']);

        $isAjax    = $this->request->is('ajax');
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $jsonError = function(string $msg, int $status = 400) {
            return $this->response->withStatus($status)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
        };

        // Accept both 'action' and 'bulk_action' (hidden fallback set by JS)
        $action = $this->request->getData('bulk_action') ?? $this->request->getData('action');
        $selectedIds = (array) $this->request->getData('selected');

        if (empty($selectedIds)) {
            if ($isAjax) return $jsonError('Nie wybrano żadnych faktur.');
            $this->Flash->error('Nie wybrano żadnych faktur.');
            return $this->redirect(['action' => 'index']);
        }

        // Verify that all selected invoices belong to the current company
        $invoices = $this->Invoices->find()
            ->where([
                'Invoices.id IN' => $selectedIds,
                'Invoices.company_id' => $companyId
            ])
            ->all();

        if ($invoices->count() !== count($selectedIds)) {
            if ($isAjax) return $jsonError('Wybrane faktury nie należą do Twojej firmy.', 403);
            $this->Flash->error('Wybrane faktury nie należą do Twojej firmy.');
            return $this->redirect(['action' => 'index']);
        }

        $flashType = 'success';
        $flashMsg  = '';

        switch ($action) {
            case 'print_selected':
                return $this->printMultiple($selectedIds);

            case 'mark_paid':
                // Utwórz płatności pokrywające pozostałą kwotę dla każdej wybranej faktury
                $Payments = $this->fetchTable('InvoicePayments');
                $created = 0; $skipped = 0; $errors = 0;

                $paymentMethod = (string)($this->request->getData('payment_method') ?? 'transfer');
                $dateMode      = (string)($this->request->getData('date_mode') ?? 'today');
                $customDateRaw = $this->request->getData('payment_date_custom');

                foreach ($invoices as $inv) {
                    $already = (float)($inv->alreadypaid ?? 0);
                    $total   = (float)($inv->total ?? 0);
                    $remain  = round(max(0, $total - $already), 2);

                    if ($remain <= 0) { $skipped++; continue; }

                    // Ustal datę płatności
                    $paymentDate = date('Y-m-d');
                    if ($dateMode === 'due') {
                        if (!empty($inv->paymentdate)) {
                            $paymentDate = method_exists($inv->paymentdate, 'format') ? $inv->paymentdate->format('Y-m-d') : (string)$inv->paymentdate;
                        }
                    } elseif ($dateMode === 'custom') {
                        $candidate = is_string($customDateRaw) ? trim($customDateRaw) : '';
                        if ($candidate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
                            $paymentDate = $candidate;
                        }
                    }

                    $payment = $Payments->newEmptyEntity();
                    $payment = $Payments->patchEntity($payment, [
                        'invoice_id'     => $inv->id,
                        'payment_date'   => $paymentDate,
                        'amount'         => $remain,
                        'payment_method' => $paymentMethod ?: 'transfer',
                        'description'    => 'Oznaczone jako opłacone (akcja zbiorcza)'
                    ]);

                    if ($Payments->save($payment)) { $created++; } else { $errors++; }
                }

                if ($created > 0) {
                    $flashType = 'success';
                    $flashMsg  = "Oznaczono jako opłacone: {$created} faktur." . ($skipped > 0 ? " Pominięte (już opłacone): {$skipped}." : '') . ($errors > 0 ? " Błędy: {$errors}." : '');
                } elseif ($skipped > 0 && $errors === 0) {
                    $flashType = 'info';
                    $flashMsg  = 'Wszystkie wybrane faktury były już opłacone. Nic nie zmieniono.';
                } else {
                    $flashType = 'error';
                    $flashMsg  = "Nie udało się utworzyć płatności (błędy: {$errors}).";
                }
                break;

            case 'send_email':
                $ksefMode = $this->isKsefModeEnabled((string)$companyId);
                /** @var \App\Model\Table\InvoiceEmailQueueTable $Queue */
                $Queue = $this->fetchTable('InvoiceEmailQueue');

                // Załaduj kontrahentów żeby sprawdzić e-mail
                $invWithContractors = $this->Invoices->find()
                    ->contain(['InvoiceContractors' => fn($q) => $q->select(['invoice_id','email'])])
                    ->where([
                        'Invoices.id IN'         => $selectedIds,
                        'Invoices.company_id'    => $companyId,
                    ])
                    ->all();

                $queued = 0; $skippedKsef = 0; $skippedEmail = 0;
                $now = new \DateTimeImmutable();

                foreach ($invWithContractors as $inv) {
                    $type  = (string)($inv->type ?? 'vat');
                    $email = trim((string)($inv->invoice_contractor?->email ?? ''));

                    // Brak e-maila → pomiń
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $skippedEmail++;
                        continue;
                    }

                    // Reguła KSeF: jeśli tryb KSeF włączony, faktura musi mieć numer KSeF
                    // (chyba że to proforma lub rachunek — te nie trafiają do KSeF)
                    $ksefExemptTypes = ['proforma', 'novat', 'rental'];
                    if ($ksefMode && !in_array($type, $ksefExemptTypes, true)) {
                        $ksefNumber = trim((string)($inv->ksef_number ?? ''));
                        if ($ksefNumber === '') {
                            $skippedKsef++;
                            continue;
                        }
                    }

                    // Dodaj do kolejki (unikaj duplikatów pending/sending dla tej faktury+emaila)
                    $exists = $Queue->find()
                        ->where([
                            'invoice_id' => $inv->id,
                            'email'      => $email,
                            'status IN'  => ['pending', 'sending'],
                        ])
                        ->count();

                    if ($exists === 0) {
                        $entry = $Queue->newEntity([
                            'invoice_id'   => $inv->id,
                            'company_id'   => $companyId,
                            'email'        => $email,
                            'status'       => 'pending',
                            'attempts'     => 0,
                            'scheduled_at' => $now->format('Y-m-d H:i:s'),
                        ]);
                        if ($Queue->save($entry)) {
                            $queued++;
                        }
                    } else {
                        $queued++; // liczymy też już istniejące w kolejce
                    }
                }

                $parts = [];
                if ($queued > 0) {
                    $parts[] = "Dodano do kolejki wysyłki: {$queued} faktur.";
                }
                if ($skippedEmail > 0) {
                    $parts[] = "Pominięto (brak e-maila kontrahenta): {$skippedEmail}.";
                }
                if ($skippedKsef > 0) {
                    $parts[] = "Pominięto (brak numeru KSeF): {$skippedKsef}.";
                }

                if ($queued > 0) {
                    $flashType = 'success';
                    $flashMsg  = implode(' ', $parts);
                } elseif (!empty($parts)) {
                    $flashType = 'warning';
                    $flashMsg  = implode(' ', $parts);
                } else {
                    $flashType = 'warning';
                    $flashMsg  = 'Żadna faktura nie spełniła warunków wysyłki.';
                }
                break;

            case 'send_reminder':
                $flashType = 'info';
                $flashMsg  = 'Funkcja wysyłania przypomnień zostanie wkrótce dodana.';
                break;

            case 'delete_selected':
                $count = $this->Invoices->deleteAll([
                    'Invoices.id IN' => $selectedIds,
                    'Invoices.company_id' => $companyId
                ]);
                $flashType = 'success';
                $flashMsg  = "Usunięto {$count} faktur.";
                break;

            default:
                $flashType = 'error';
                $flashMsg  = 'Nieznana akcja.';
        }

        if ($isAjax) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => $flashType !== 'error',
                    'type'    => $flashType,
                    'message' => $flashMsg,
                ], JSON_UNESCAPED_UNICODE));
        }

        $this->Flash->{$flashType}($flashMsg);
        return $this->redirect(['action' => 'index']);
    }
    
    /**
     * Print multiple invoices as single PDF
     */
    private function printMultiple(array $ids)
    {
        $identity = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $invoices = $this->Invoices->find()
            ->contain([
                'InvoiceContractors',
                'InvoiceContents' => ['Vats'], 
                'Companies',
                'InvoiceCompanyDetails'
            ])
            ->where([
                'Invoices.id IN' => $ids,
                'Invoices.company_id' => $companyId
            ])
            ->orderAsc('Invoices.date')
            ->all();

        // Pre-compute NBP rates per invoice (if foreign currency)
        $nbpMap = [];
        foreach ($invoices as $inv) {
            try {
                $cur = strtoupper((string)($inv->currency ?? 'PLN'));
                if ($cur !== 'PLN' && !empty($inv->date)) {
                    $nbpMap[$inv->id] = $this->computeNbpAvgRate($cur, $inv->date);
                }
            } catch (\Throwable) { /* ignore */ }
        }

        // Use CakePdf view to render combined PDF
        $filename = 'faktury_' . date('Y-m-d_H-i-s') . '.pdf';
        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setTemplate('print_multiple')
            ->setOptions([
                'pdfConfig' => [
                    'filename' => $filename,
                    'download' => false,
                    'orientation' => 'portrait',
                    'paper' => 'A4',
                ],
            ]);

        $this->set(compact('invoices','nbpMap'));
        return null;
    }

    /** GET /invoices/proforma-search?q=...&_ext=json */
    public function proformaSearch(): Response
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $q = trim((string)$this->request->getQuery('q', ''));
        $Invoices = $this->fetchTable('Invoices');

        $query = $Invoices->find()
            ->select(['id','fullnumber','date','total'])
            ->where(['company_id' => $companyId, 'type' => 'proforma'])
            ->orderDesc('date')
            ->limit(25);

        if ($q !== '') {
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
            $query->andWhere(['OR' => [
                'fullnumber LIKE' => $like,
                'id LIKE' => $like,
            ]]);
        }

        $results = [];
        foreach ($query as $p) {
            $results[] = [
                'id' => (string)$p->id,
                'text' => sprintf('%s — %s — %0.2f', (string)($p->fullnumber ?? $p->id), $p->date?->i18nFormat('yyyy-MM-dd') ?? '', (float)$p->total),
            ];
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** GET /invoices/proforma-details/{id}._ext=json */
    public function proformaDetails($id)
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Invoices = $this->fetchTable('Invoices');
        $p = $Invoices->find()
            ->contain(['InvoiceContractors','InvoiceContents' => ['Vats']])
            ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId, 'Invoices.type' => 'proforma'])
            ->first();

        if (!$p) {
            return $this->response->withStatus(404)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Not found']));
        }

        // compute advances/final sums and list already issued documents
        // exclude_id: when editing an existing advance, exclude it from the sum so it is not double-counted
        $excludeId = $this->request->getQuery('exclude_id');
        $advWhere = [
            'company_id' => $companyId,
            'parent_id'  => $id,
            'type IN'    => ['advance','final'],
        ];
        if (!empty($excludeId)) {
            $advWhere['id !='] = $excludeId;
        }
        $sumAdvances = (float)$Invoices->find()
            ->select(['s' => $Invoices->find()->func()->sum('total')])
            ->where($advWhere)
            ->enableHydration(false)
            ->first()['s'] ?? 0.0;
        $hasFinalWhere = [
            'company_id' => $companyId,
            'parent_id'  => $id,
            'type'       => 'final',
        ];
        if (!empty($excludeId)) {
            $hasFinalWhere['id !='] = $excludeId;
        }
        $hasFinal = (bool)$Invoices->find()
            ->select(['id'])
            ->where($hasFinalWhere)
            ->limit(1)->count();
        $remaining = round(max(0.0, ((float)$p->total) - $sumAdvances), 2);

        // children list (advance/final) already issued for this offer
        $childrenRows = $Invoices->find()
            ->select(['id','fullnumber','date','total','type','paymentstate','alreadypaid','remaining','paymentdate'])
            ->where([
                'company_id' => $companyId,
                'parent_id' => $id,
                'type IN' => ['advance','final']
            ])
            ->orderAsc('date')
            ->all();
        $children = [];
        foreach ($childrenRows as $ch) {
            $children[] = [
                'id' => (string)$ch->id,
                'fullnumber' => (string)($ch->fullnumber ?? ''),
                'date' => $ch->date?->i18nFormat('yyyy-MM-dd') ?? null,
                'total' => (float)($ch->total ?? 0),
                'type' => (string)($ch->type ?? ''),
                'paymentstate' => (string)($ch->paymentstate ?? ''),
                'alreadypaid' => (float)($ch->alreadypaid ?? 0),
                'remaining' => (float)($ch->remaining ?? 0),
                'paymentdate' => $ch->paymentdate?->i18nFormat('yyyy-MM-dd') ?? null,
            ];
        }

        // Porównaj snapshot kontrahenta z bieżącymi danymi (po NIP)
        $snapshotNip = (string)($p->invoice_contractor?->nip ?? '');
        $contractorChanged = false;
        $contractorCurrent = null;
        if ($snapshotNip !== '') {
            $Contractors = $this->fetchTable('Contractors');
            $currentCtr = $Contractors->find()
                ->select(['name', 'nip', 'street', 'city', 'postal_code', 'country'])
                ->where(['Contractors.company_id' => $companyId, 'Contractors.nip' => $snapshotNip])
                ->first();
            if ($currentCtr !== null) {
                // snapshot uses 'zip', live contractors table uses 'postal_code'
                $diffFields = ['name', 'street', 'city', 'country'];
                foreach ($diffFields as $f) {
                    $snap = trim((string)($p->invoice_contractor?->$f ?? ''));
                    $curr = trim((string)($currentCtr->$f ?? ''));
                    if ($snap !== $curr) {
                        $contractorChanged = true;
                        break;
                    }
                }
                // compare zip (snapshot) vs postal_code (live)
                if (!$contractorChanged) {
                    $snapZip = trim((string)($p->invoice_contractor?->zip ?? ''));
                    $currZip = trim((string)($currentCtr->postal_code ?? ''));
                    if ($snapZip !== $currZip) {
                        $contractorChanged = true;
                    }
                }
                if ($contractorChanged) {
                    $contractorCurrent = [
                        'name'    => (string)($currentCtr->name ?? ''),
                        'nip'     => (string)($currentCtr->nip ?? ''),
                        'street'  => (string)($currentCtr->street ?? ''),
                        'zip'     => (string)($currentCtr->postal_code ?? ''),
                        'city'    => (string)($currentCtr->city ?? ''),
                        'country' => (string)($currentCtr->country ?? 'PL'),
                    ];
                }
            }
        }

        // Build contractor payload — snapshot first, supplement empty fields from live table
        $contractorPayload = [
            'name'           => (string)($p->invoice_contractor?->name ?? ''),
            'nip'            => (string)($p->invoice_contractor?->nip ?? ''),
            'street'         => (string)($p->invoice_contractor?->street ?? ''),
            'zip'            => (string)($p->invoice_contractor?->zip ?? ''),
            'city'           => (string)($p->invoice_contractor?->city ?? ''),
            'country'        => (string)($p->invoice_contractor?->country ?? 'PL'),
            'email'          => (string)($p->invoice_contractor?->email ?? ''),
            'phone'          => (string)($p->invoice_contractor?->phone ?? ''),
            'account_number' => (string)($p->invoice_contractor?->account_number ?? ''),
        ];
        // Supplement any empty fields from the live Contractors record.
        // $currentCtr is already loaded above (for diff-check) when NIP is known;
        // if NIP was empty, fall back via contractor_id.
        $liveForSupplement = $currentCtr ?? null;
        if ($liveForSupplement === null && !empty($p->contractor_id)) {
            $SuppCtr = $this->fetchTable('Contractors');
            $liveForSupplement = $SuppCtr->find()
                ->select(['name','nip','street','city','postal_code','country','email','phone'])
                ->where(['id' => $p->contractor_id, 'company_id' => $companyId])
                ->first();
        }
        if ($liveForSupplement !== null) {
            $supplementMap = [
                'name'           => 'name',
                'nip'            => 'nip',
                'street'         => 'street',
                'zip'            => 'postal_code',   // snapshot col → live col
                'city'           => 'city',
                'country'        => 'country',
                'email'          => 'email',
                'phone'          => 'phone',
                'account_number' => 'account_number',
            ];
            foreach ($supplementMap as $payloadKey => $liveCol) {
                if ($contractorPayload[$payloadKey] === '') {
                    $contractorPayload[$payloadKey] = (string)($liveForSupplement->$liveCol ?? '');
                }
            }
            if ($contractorPayload['country'] === '') {
                $contractorPayload['country'] = 'PL';
            }
        }

        $payload = [
            'id' => (string)$p->id,
            'fullnumber' => (string)($p->fullnumber ?? ''),
            'date' => $p->date?->i18nFormat('yyyy-MM-dd'),
            'currency' => (string)($p->currency ?? 'PLN'),
            'total' => (float)$p->total,
            'advances_total' => (float)$sumAdvances,
            'remaining' => (float)$remaining,
            'final_exists' => $hasFinal,
            'contractor_changed' => $contractorChanged,
            'contractor_current' => $contractorCurrent,
            'contractor' => $contractorPayload,
            'items' => array_map(function($c){
                return [
                    'name' => (string)$c->name,
                    'netto' => (float)$c->netto,
                    'brutto' => (float)$c->brutto,
                    'vat_code_id' => $c->vat_code_id,
                    'vat_rate' => isset($c->vat) ? (float)$c->vat->rate : null,
                ];
            }, (array)$p->invoice_contents),
            'children' => $children,
        ];

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'proforma' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * GET /invoices/nbp-rate?currency=EUR&date=2025-10-28&sold_date=2025-10-27
     * Returns average NBP rate for the working day preceding the provided date.
     */
    public function nbpRate(): Response
    {
        $this->request->allowMethod(['get']);

        $currency = strtoupper((string)$this->request->getQuery('currency', 'PLN'));
        $issue    = (string)$this->request->getQuery('date', '');
        $sold     = (string)$this->request->getQuery('sold_date', '');

        // Choose base date: prefer sold_date if provided; else issue date; else today
        $baseDate = null;
        try { if ($sold) { $baseDate = new \DateTimeImmutable($sold); } } catch (\Throwable) {}
        try { if (!$baseDate && $issue) { $baseDate = new \DateTimeImmutable($issue); } } catch (\Throwable) {}
        if (!$baseDate) { $baseDate = new \DateTimeImmutable('today'); }

        if ($currency === 'PLN') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'currency' => 'PLN',
                    'rate' => 1.0,
                    'effectiveDate' => $baseDate->format('Y-m-d'),
                    'table' => '—',
                    'note' => 'PLN is base currency'
                ]));
        }

        try {
            $res = $this->computeNbpAvgRate($currency, $baseDate);
            if (!empty($res['success'])) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode($res));
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($res));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'NBP rate lookup failed',
                    'error'   => $e->getMessage(),
                ]));
        }
    }

    /**
     * Compute average NBP rate (Tabela A/B) for currency code for the last working day prior to baseDate.
     * Returns array: { success, currency, rate, effectiveDate, table, from, to }
     */
    private function computeNbpAvgRate(string $currency, \DateTimeInterface $baseDate): array
    {
        $code = strtoupper($currency);
        // Use the day before the provided date
        $end = (new \DateTimeImmutable($baseDate->format('Y-m-d')))->modify('-1 day');
        $start = $end->modify('-14 days');
        $fmt = fn($d) => $d->format('Y-m-d');

        $client = new Client(['timeout' => 5]);

        // Try Table A first, fallback to B
        foreach (['A','B'] as $table) {
            $url = sprintf('https://api.nbp.pl/api/exchangerates/rates/%s/%s/%s/%s/?format=json', $table, urlencode($code), $fmt($start), $fmt($end));
            $resp = $client->get($url);
            if ($resp->isOk()) {
                $json = (array)$resp->getJson();
                $rates = isset($json['rates']) && is_array($json['rates']) ? $json['rates'] : [];
                if (!empty($rates)) {
                    $last = end($rates);
                    $rate = (float)($last['mid'] ?? 0);
                    $eff  = (string)($last['effectiveDate'] ?? $fmt($end));
                    if ($rate > 0) {
                        return [
                            'success' => true,
                            'currency' => $code,
                            'rate' => $rate,
                            'effectiveDate' => $eff,
                            'table' => $table,
                            'from' => $fmt($start),
                            'to'   => $fmt($end),
                            'source' => 'NBP API',
                        ];
                    }
                }
            }
        }

        return [
            'success' => false,
            'currency' => $code,
            'message' => 'No NBP rate found for the selected period',
            'from' => $fmt($start),
            'to'   => $fmt($end),
        ];
    }

    /**
     * GET /invoices/nbp-currencies?q=...  → Select2 results of NBP currency codes
     */
    public function nbpCurrencies(): Response
    {
        $this->request->allowMethod(['get']);

        $q = trim((string)$this->request->getQuery('q', ''));
        $client = new Client(['timeout' => 5]);
        $list = [];

        // Always include PLN at top
        $list['PLN'] = 'Złoty polski';

        foreach (['A','B'] as $table) {
            try {
                $resp = $client->get(sprintf('https://api.nbp.pl/api/exchangerates/tables/%s/?format=json', $table));
                if ($resp->isOk()) {
                    $arr = (array)$resp->getJson();
                    if (!empty($arr) && isset($arr[0]['rates']) && is_array($arr[0]['rates'])) {
                        foreach ($arr[0]['rates'] as $r) {
                            $code = strtoupper((string)($r['code'] ?? ''));
                            $name = (string)($r['currency'] ?? '');
                            if ($code) { $list[$code] = $list[$code] ?? $name; }
                        }
                    }
                }
            } catch (\Throwable) { /* ignore and continue */ }
        }

        // Filter by query if provided
        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $list = array_filter($list, function ($name, $code) use ($qLower) {
                return str_contains(mb_strtolower($code, 'UTF-8'), $qLower)
                    || str_contains(mb_strtolower((string)$name, 'UTF-8'), $qLower);
            }, ARRAY_FILTER_USE_BOTH);
        }

        // Build Select2 results sorted by code
        ksort($list);
        $results = [];
        foreach ($list as $code => $name) {
            $text = sprintf('%s - %s', $code, $name ?: '');
            $results[] = ['id' => $code, 'text' => $text, 'code' => $code, 'name' => $name];
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'results' => $results]));
    }

    /**
     * Typy faktur które NIE mogą być wysyłane do KSeF.
     * - proforma: nie jest fakturą VAT, brak podstawy prawnej
     * - internal/internalEvidence: dokument wewnętrzny
     * - oss: rozliczenie OSS poza krajowym KSeF
     */
    private const KSEF_BLOCKED_TYPES = ['proforma', 'internal', 'internalEvidence', 'oss'];

    public function sendInvoiceToKsefCore(Invoice $invoice, string $companyId, string $environment = 'prod', ?string $xml = null, string $source = 'sendToKsef'): array
    {
        if (in_array($invoice->type, self::KSEF_BLOCKED_TYPES, true)) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'blocked', [
                'source' => $source,
                'message' => 'Typ dokumentu "' . $invoice->type . '" nie może być wysłany do KSeF.',
            ]);
            return [
                'success' => false,
                'error'   => 'Dokument typu „' . $invoice->type . '" nie podlega wysyłce do KSeF.',
            ];
        }

        if (!$this->isKsefModeEnabled($companyId)) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'blocked', [
                'source' => $source,
                'message' => 'Tryb KSeF wyłączony dla firmy.',
            ]);
            return ['success' => false, 'error' => 'Tryb KSeF jest wyłączony dla tej firmy.'];
        }

        // Bezpiecznik: brak danych nabywcy → blokada, chyba że użytkownik potwierdził (confirm_no_buyer=1).
        if ($this->buyerDataMissing($invoice) && !$this->noBuyerSendConfirmed()) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'blocked', [
                'source'  => $source,
                'message' => 'Brak danych nabywcy (nazwa/NIP/VAT UE) — wysyłka wymaga świadomego potwierdzenia.',
            ]);
            return ['success' => false, 'errorCode' => 'NO_BUYER', 'error' => self::NO_BUYER_ERROR];
        }
        $dateError = $this->validateSendDateWindow($invoice);
        if ($dateError !== null) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'blocked', [
                'source' => $source,
                'message' => $dateError,
            ]);
            return ['success' => false, 'error' => $dateError . ' Wysyłka do KSeF została zablokowana.'];
        }

        // 1) Idempotencja: faktura już wysłana z numerem KSeF → zwróć istniejące dane.
        // Reason: drugie kliknięcie / refresh + ponowny POST nie może uruchomić ponownej wysyłki
        // i nadpisać ksef_number pustą wartością z błędu "duplikat".
        $existingKsefNumber = trim((string)($invoice->ksef_number ?? ''));
        $existingStatus     = trim((string)($invoice->workflow_status ?? ''));
        if ($existingKsefNumber !== '' && $existingStatus === 'sent') {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'idempotent_skip', [
                'source'      => $source,
                'message'     => 'Faktura już wysłana do KSeF — pomijam ponowną wysyłkę.',
                'ksef_number' => $existingKsefNumber,
            ]);
            return [
                'success' => true,
                'result'  => [
                    'ok'               => true,
                    'statusCode'       => (string)($invoice->ksef_status ?? '200'),
                    'statusDesc'       => (string)($invoice->ksef_desc ?? 'Faktura była już wysłana do KSeF.'),
                    'ksefNumber'       => $existingKsefNumber,
                    'sessionReference' => (string)($invoice->ksef_session_reference ?? ''),
                    'invoiceReference' => (string)($invoice->ksef_invoice_reference ?? ''),
                ],
            ];
        }

        try {
            $this->ensureInvoiceNumberForSend($invoice, $companyId);
        } catch (\Throwable $e) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'blocked', [
                'source' => $source,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // 2) Atomic check-and-set blokady wysyłki: tylko jeden proces na raz może wysyłać tę fakturę.
        // Zwalniamy lock po 5 minutach na wypadek crashu PHP (timeout / fatal) — KSeF zwykle odpowiada w sekundach.
        $conn = $this->Invoices->getConnection();
        $lockStmt = $conn->execute(
            "UPDATE invoices
             SET workflow_status = 'sending', modified = NOW()
             WHERE id = ?
               AND (ksef_number IS NULL OR ksef_number = '')
               AND (workflow_status IS NULL
                    OR workflow_status NOT IN ('sent','sending')
                    OR (workflow_status = 'sending' AND modified < (NOW() - INTERVAL 5 MINUTE)))",
            [(string)$invoice->id]
        );
        $acquired = $lockStmt->rowCount() === 1;
        if (!$acquired) {
            // Re-fetch w celu sprawdzenia czy inny proces właśnie ukończył wysyłkę (race during this exact moment)
            $fresh = $this->Invoices->find()
                ->select(['id', 'workflow_status', 'ksef_number', 'ksef_status', 'ksef_desc', 'ksef_session_reference', 'ksef_invoice_reference'])
                ->where(['id' => (string)$invoice->id])
                ->first();
            $freshKsefNumber = trim((string)($fresh->ksef_number ?? ''));
            if ($freshKsefNumber !== '' && (string)($fresh->workflow_status ?? '') === 'sent') {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'idempotent_skip', [
                    'source'      => $source,
                    'message'     => 'Faktura wysłana w międzyczasie — pomijam.',
                    'ksef_number' => $freshKsefNumber,
                ]);
                return [
                    'success' => true,
                    'result'  => [
                        'ok'               => true,
                        'statusCode'       => (string)($fresh->ksef_status ?? '200'),
                        'statusDesc'       => (string)($fresh->ksef_desc ?? 'Faktura była już wysłana do KSeF.'),
                        'ksefNumber'       => $freshKsefNumber,
                        'sessionReference' => (string)($fresh->ksef_session_reference ?? ''),
                        'invoiceReference' => (string)($fresh->ksef_invoice_reference ?? ''),
                    ],
                ];
            }
            // Inny proces właśnie wysyła — odmów ponownego startu
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'lock_busy', [
                'source'  => $source,
                'message' => 'Wysyłka tej faktury jest już w toku przez inne żądanie.',
            ]);
            return [
                'success' => false,
                'error'   => 'Wysyłka tej faktury jest już w toku. Poczekaj chwilę i odśwież stronę.',
            ];
        }
        // Lock przejęty — synchronizuj encję w pamięci (UPDATE zmienił bazę, ale obiekt jeszcze nie wie)
        $invoice->set('workflow_status', 'sending');

        if (!is_string($xml) || trim($xml) === '') {
            try {
                $xml = $this->buildFa3Xml($invoice);
            } catch (\Throwable) {
                $xml = '';
            }
        }

        if (!is_string($xml) || trim($xml) === '') {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'xml_missing', [
                'source' => $source,
                'message' => 'Brak XML FA(3) po upload/generowaniu.',
            ]);
            $invoice->set('workflow_status', 'error');
            $this->Invoices->save($invoice);
            return ['success' => false, 'error' => 'Brak poprawnego XML FA (3). Operacja przerwana.'];
        }

        try {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'send_attempt', [
                'source' => $source,
                'env' => $environment,
            ]);

            // Walidacja XSD przed wysyłką
            $xsdPath = ROOT . DS . 'src' . DS . 'faktura.xsd';
            if (file_exists($xsdPath)) {
                $dom = new \DOMDocument();
                if (!@$dom->loadXML($xml)) {
                    $this->logKsefSendEvent($companyId, (string)$invoice->id, 'xml_invalid', [
                        'source' => $source,
                        'message' => 'XML nie jest poprawnym dokumentem XML.',
                    ]);
                    $invoice->set('workflow_status', 'error');
                    $this->Invoices->save($invoice);
                    return ['success' => false, 'error' => 'XML faktury jest niepoprawny (błąd parsowania).'];
                }
                $xsdErrors = [];
                libxml_use_internal_errors(true);
                $valid = $dom->schemaValidate($xsdPath);
                if (!$valid) {
                    foreach (libxml_get_errors() as $err) {
                        $xsdErrors[] = trim($err->message) . ' (linia ' . $err->line . ')';
                    }
                    libxml_clear_errors();
                }
                libxml_use_internal_errors(false);
                if (!$valid) {
                    $errMsg = implode('; ', array_slice($xsdErrors, 0, 3));
                    $this->logKsefSendEvent($companyId, (string)$invoice->id, 'xsd_invalid', [
                        'source'  => $source,
                        'message' => $errMsg,
                    ]);
                    $invoice->set('workflow_status', 'error');
                    $this->Invoices->save($invoice);
                    return ['success' => false, 'error' => 'XML faktury nie przeszedł walidacji XSD FA(3): ' . $errMsg];
                }
            }

            $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
            $res = $service->sendInvoiceXml($companyId, $environment, $xml);

            $desc = (string)($res['statusDesc'] ?? '');
            $refs = ' [S=' . (string)($res['sessionReference'] ?? '') . ', I=' . (string)($res['invoiceReference'] ?? '') . ']';
            $invoice->set('ksef_status', (string)($res['statusCode'] ?? ''));
            $invoice->set('ksef_desc', trim($desc . $refs));

            // 3) Nigdy nie nadpisuj niepustych pól KSeF wartością pustą.
            // Reason: odpowiedź "duplikat" z KSeF zwraca brak ksefNumber/sessionReference — ale
            // pierwsza udana wysyłka już wypełniła te pola. Nie wolno ich wyczyścić.
            $newKsefNumber = (string)($res['ksefNumber'] ?? '');
            $newSessionRef = (string)($res['sessionReference'] ?? '');
            $newInvoiceRef = (string)($res['invoiceReference'] ?? '');
            if ($newKsefNumber !== '') $invoice->set('ksef_number',            $newKsefNumber);
            if ($newSessionRef !== '') $invoice->set('ksef_session_reference', $newSessionRef);
            if ($newInvoiceRef !== '') $invoice->set('ksef_invoice_reference', $newInvoiceRef);

            // workflow_status: 'sent' przy ok; przy błędzie NIE downgradujemy do 'error' jeśli mamy już ksef_number
            // (oznacza, że wcześniejsza udana wysyłka istnieje — błąd dotyczy duplikatu / re-trya).
            if (!empty($res['ok'])) {
                $invoice->set('workflow_status', 'sent');
            } elseif (!empty(trim((string)($invoice->ksef_number ?? '')))) {
                $invoice->set('workflow_status', 'sent');
            } else {
                $invoice->set('workflow_status', 'error');
            }
            $invoice->set('planned_ksef_send_at', null);
            if (!empty($res['ok'])) {
                // Zapisz hash dokładnie tych bajtów XML, które trafiły do KSeF – potrzebny do QR kodu.
                $invoice->set('ksef_xml_hash', rtrim(strtr(base64_encode(hash('sha256', $xml, true)), '+/', '-_'), '='));
            }
            $this->Invoices->save($invoice);

            if (!empty($res['ok'])) {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'send_success', [
                    'source' => $source,
                    'env' => $environment,
                    'status_code' => (string)($res['statusCode'] ?? ''),
                    'ksef_number' => (string)($res['ksefNumber'] ?? ''),
                    'session_reference' => (string)($res['sessionReference'] ?? ''),
                ]);

                // Automatyczna wysyłka maila do nabywcy po akceptacji w KSeF (jeśli zaznaczono auto_send)
                $this->enqueueAutoSendEmailIfEnabled($invoice, $companyId, $source);
            } else {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'send_error', [
                    'source' => $source,
                    'env' => $environment,
                    'status_code' => (string)($res['statusCode'] ?? ''),
                    'message' => (string)($res['statusDesc'] ?? ''),
                ]);
            }

            return [
                'success' => !empty($res['ok']),
                'error' => !empty($res['ok']) ? null : ('Nie udało się wysłać do KSeF (' . (string)($res['statusCode'] ?? '') . '): ' . (string)($res['statusDesc'] ?? '')),
                'result' => $res,
            ];
        } catch (\Throwable $e) {
            // Nie downgraduj 'sent' do 'error' jeśli mamy już ksef_number (re-fetch zabezpiecza
            // przed wyścigiem z innym wątkiem, który właśnie zapisał sukces).
            $reload = $this->Invoices->find()
                ->select(['id', 'ksef_number', 'workflow_status'])
                ->where(['id' => (string)$invoice->id])
                ->first();
            $hasKsefNumber = !empty(trim((string)($reload->ksef_number ?? $invoice->ksef_number ?? '')));
            // HTTP 429 (limit KSeF, np. 120 otwarć sesji/h) to błąd przejściowy: faktura wraca do 'issued'
            // z opisem i zostanie ponowiona — zamiast lądować w 'error' bez treści.
            $exInfo = $this->describeKsefException($e);
            $rateLimited = $exInfo['rate_limited'];
            if (!$hasKsefNumber) {
                $invoice->set('workflow_status', $rateLimited ? 'issued' : 'error');
                if ($rateLimited) {
                    $invoice->set('ksef_status', '429');
                    $invoice->set('ksef_desc', 'KSeF: ' . $exInfo['message']);
                }
                $this->Invoices->save($invoice);
            } else {
                // Zostaw status 'sent' jeżeli istnieje numer KSeF — błąd to retry/duplikat
                $invoice->set('workflow_status', 'sent');
                $this->Invoices->save($invoice);
            }
            $ksefContext = null;
            if (property_exists($e, 'context') && $e->context !== null) {
                try { $ksefContext = json_decode(json_encode($e->context), true); } catch (\Throwable) {}
            }
            // Wyciągnij czytelny opis z exceptionDetailList jeśli dostępny
            $ksefMessage = $e->getMessage();
            if (empty($ksefMessage) && is_array($ksefContext)) {
                $details = $ksefContext['exception']['exceptionDetailList'] ?? [];
                if (!empty($details[0])) {
                    $ksefMessage = ($details[0]['exceptionCode'] ?? '') . ' ' . ($details[0]['exceptionDescription'] ?? '');
                    $ksefMessage = trim($ksefMessage);
                }
            }
            if (empty($ksefMessage)) {
                $ksefMessage = $exInfo['message'];
            }
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'send_exception', [
                'source'          => $source,
                'env'             => $environment,
                'message'         => $ksefMessage ?: '(brak treści wyjątku)',
                'exception_class' => get_class($e),
                'http_code'       => $e->getCode(),
                'ksef_context'    => $ksefContext,
                'file'            => $e->getFile() . ':' . $e->getLine(),
                'trace'           => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8)),
            ]);
            // Czytelny komunikat przy HTTP 403 missing-permissions
            $httpCode = (int)$e->getCode();
            if ($httpCode === 403) {
                $presentPerms = is_array($ksefContext) ? ($ksefContext['exception']['presentPermissions'] ?? null) : null;
                if (is_array($presentPerms) && !in_array('InvoiceWrite', $presentPerms, true)) {
                    $return403Msg = 'Brak uprawnienia InvoiceWrite w KSeF.'
                        . ' Certyfikat systemu ma tylko: ' . implode(', ', $presentPerms) . '.'
                        . ' Zaloguj się do panelu KSeF jako właściciel firmy i nadaj certyfikatowi uprawnienie InvoiceWrite.';
                    return ['success' => false, 'error' => $return403Msg];
                }
                return ['success' => false, 'error' => 'Błąd autoryzacji KSeF (403): ' . ($ksefMessage ?: 'Brak dostępu.')];
            }
            $errorMsg = $ksefMessage ?: get_class($e);
            if ($rateLimited) {
                return [
                    'success'    => false,
                    'errorCode'  => 'RATE_LIMIT',
                    'retryAfter' => $exInfo['retry_after'],
                    'error'      => 'Limit KSeF (429): ' . $exInfo['message'],
                ];
            }
            return ['success' => false, 'error' => 'Błąd wysyłki do KSeF (' . $httpCode . '): ' . $errorMsg];
        }
    }

    /**
     * Wysyłka wielu faktur w JEDNEJ sesji interaktywnej KSeF.
     *
     * KSeF (prod) limituje otwieranie sesji do 120/h — sesja per faktura (sendInvoiceToKsefCore) daje
     * więc maksymalnie ~2 faktury/min. W jednej sesji można wysłać wiele dokumentów; limit dotyczy
     * tylko otwarcia. Per faktura stosujemy te same bezpieczniki co Core (typ, tryb KSeF, nabywca,
     * okno dat, idempotencja, atomowy lock 'sending', XML + XSD), potem wspólna sesja i zapis
     * wyników identyczny jak w Core (statusy, numer KSeF, hash XML, logi, auto-mail).
     *
     * @param Invoice[] $invoices
     * @return array{items: array<string, array>, rate_limited: bool, retry_after: ?int, session_reference: ?string}
     */
    public function sendInvoicesToKsefBulk(array $invoices, string $companyId, string $environment = 'prod', string $source = 'api_bulk', string $mode = 'batch'): array
    {
        $items = [];
        $prepared = []; // id => ['invoice' => Invoice, 'xml' => string]
        $xsdPath = ROOT . DS . 'src' . DS . 'faktura.xsd';

        foreach ($invoices as $invoice) {
            $id = (string)$invoice->id;
            $fail = function (string $error, ?string $code = null) use (&$items, $id): void {
                $items[$id] = ['success' => false, 'error' => $error, 'errorCode' => $code];
            };

            if (in_array($invoice->type, self::KSEF_BLOCKED_TYPES, true)) {
                $this->logKsefSendEvent($companyId, $id, 'blocked', ['source' => $source, 'message' => 'Typ dokumentu "' . $invoice->type . '" nie może być wysłany do KSeF.']);
                $fail('Dokument typu „' . $invoice->type . '" nie podlega wysyłce do KSeF.');
                continue;
            }
            if (!$this->isKsefModeEnabled($companyId)) {
                $this->logKsefSendEvent($companyId, $id, 'blocked', ['source' => $source, 'message' => 'Tryb KSeF wyłączony dla firmy.']);
                $fail('Tryb KSeF jest wyłączony dla tej firmy.');
                continue;
            }
            if ($this->buyerDataMissing($invoice) && !$this->noBuyerSendConfirmed()) {
                $this->logKsefSendEvent($companyId, $id, 'blocked', ['source' => $source, 'message' => 'Brak danych nabywcy (nazwa/NIP/VAT UE) — wysyłka wymaga świadomego potwierdzenia.']);
                $fail(self::NO_BUYER_ERROR, 'NO_BUYER');
                continue;
            }
            $dateError = $this->validateSendDateWindow($invoice);
            if ($dateError !== null) {
                $this->logKsefSendEvent($companyId, $id, 'blocked', ['source' => $source, 'message' => $dateError]);
                $fail($dateError . ' Wysyłka do KSeF została zablokowana.');
                continue;
            }
            $existingKsefNumber = trim((string)($invoice->ksef_number ?? ''));
            if ($existingKsefNumber !== '' && trim((string)($invoice->workflow_status ?? '')) === 'sent') {
                $this->logKsefSendEvent($companyId, $id, 'idempotent_skip', ['source' => $source, 'message' => 'Faktura już wysłana do KSeF — pomijam ponowną wysyłkę.', 'ksef_number' => $existingKsefNumber]);
                $items[$id] = ['success' => true, 'result' => [
                    'ok' => true, 'statusCode' => (string)($invoice->ksef_status ?? '200'), 'statusDesc' => (string)($invoice->ksef_desc ?? ''),
                    'ksefNumber' => $existingKsefNumber, 'sessionReference' => (string)($invoice->ksef_session_reference ?? ''), 'invoiceReference' => (string)($invoice->ksef_invoice_reference ?? ''),
                ]];
                continue;
            }
            try {
                $this->ensureInvoiceNumberForSend($invoice, $companyId);
            } catch (\Throwable $e) {
                $this->logKsefSendEvent($companyId, $id, 'blocked', ['source' => $source, 'message' => $e->getMessage()]);
                $fail($e->getMessage());
                continue;
            }

            // Atomowy lock — identyczny jak w sendInvoiceToKsefCore (stale 'sending' > 5 min do przejęcia).
            $lockStmt = $this->Invoices->getConnection()->execute(
                "UPDATE invoices
                 SET workflow_status = 'sending', modified = NOW()
                 WHERE id = ?
                   AND (ksef_number IS NULL OR ksef_number = '')
                   AND (workflow_status IS NULL
                        OR workflow_status NOT IN ('sent','sending')
                        OR (workflow_status = 'sending' AND modified < (NOW() - INTERVAL 5 MINUTE)))",
                [$id]
            );
            if ($lockStmt->rowCount() !== 1) {
                $this->logKsefSendEvent($companyId, $id, 'lock_busy', ['source' => $source, 'message' => 'Wysyłka tej faktury jest już w toku przez inne żądanie.']);
                $fail('Wysyłka tej faktury jest już w toku.', 'LOCK_BUSY');
                continue;
            }
            $invoice->set('workflow_status', 'sending');

            try {
                $xml = $this->buildFa3Xml($invoice);
            } catch (\Throwable $e) {
                $xml = '';
            }
            if (!is_string($xml) || trim($xml) === '') {
                $this->logKsefSendEvent($companyId, $id, 'xml_missing', ['source' => $source, 'message' => 'Brak XML FA(3) po generowaniu.']);
                $invoice->set('workflow_status', 'error');
                $this->Invoices->save($invoice);
                $fail('Brak poprawnego XML FA (3). Operacja przerwana.');
                continue;
            }
            if (file_exists($xsdPath)) {
                $dom = new \DOMDocument();
                $valid = @$dom->loadXML($xml);
                $xsdErrors = [];
                if ($valid) {
                    libxml_use_internal_errors(true);
                    $valid = $dom->schemaValidate($xsdPath);
                    if (!$valid) {
                        foreach (libxml_get_errors() as $err) {
                            $xsdErrors[] = trim($err->message) . ' (linia ' . $err->line . ')';
                        }
                        libxml_clear_errors();
                    }
                    libxml_use_internal_errors(false);
                }
                if (!$valid) {
                    $errMsg = $xsdErrors ? implode('; ', array_slice($xsdErrors, 0, 3)) : 'XML nie jest poprawnym dokumentem XML.';
                    $this->logKsefSendEvent($companyId, $id, $xsdErrors ? 'xsd_invalid' : 'xml_invalid', ['source' => $source, 'message' => $errMsg]);
                    $invoice->set('workflow_status', 'error');
                    $this->Invoices->save($invoice);
                    $fail('XML faktury nie przeszedł walidacji XSD FA(3): ' . $errMsg);
                    continue;
                }
            }

            $this->logKsefSendEvent($companyId, $id, 'send_attempt', ['source' => $source, 'env' => $environment]);
            $prepared[$id] = ['invoice' => $invoice, 'xml' => $xml];
        }

        $rateLimited = false;
        $retryAfter = null;
        $sessionRef = null;
        if (empty($prepared)) {
            return ['items' => $items, 'rate_limited' => false, 'retry_after' => null, 'session_reference' => null];
        }

        $xmls = [];
        foreach ($prepared as $id => $p) {
            $xmls[$id] = $p['xml'];
        }
        $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        try {
            // 'batch' (domyślnie): sesja wsadowa — bez limitu faktur w paczce (60 sesji/h);
            // 'online': sesja interaktywna — limit 180 faktur/h niezależnie od liczby sesji.
            $batch = $mode === 'online'
                ? $service->sendInvoicesXmlBatch($companyId, $environment, $xmls)
                : $service->sendInvoicesXmlBatchSession($companyId, $environment, $xmls);
        } catch (\Throwable $e) {
            // Sesja się nie otworzyła (np. 429 — limit sesji) → nic nie poszło do KSeF: zdejmij locki, faktury wracają do 'issued'.
            $info = $this->describeKsefException($e);
            $rateLimited = $info['rate_limited'];
            $retryAfter = $info['retry_after'];
            foreach ($prepared as $id => $p) {
                $inv = $p['invoice'];
                $inv->set('workflow_status', 'issued');
                $inv->set('ksef_status', (string)(int)$e->getCode());
                $inv->set('ksef_desc', 'KSeF: ' . $info['message']);
                $this->Invoices->save($inv);
                $this->logKsefSendEvent($companyId, $id, 'send_exception', [
                    'source' => $source, 'env' => $environment, 'stage' => 'open_session',
                    'message' => $info['message'], 'exception_class' => get_class($e), 'http_code' => $e->getCode(),
                ]);
                $items[$id] = [
                    'success' => false,
                    'error' => ($rateLimited ? 'Limit KSeF (429): ' : 'Błąd wysyłki do KSeF (' . (int)$e->getCode() . '): ') . $info['message'],
                    'errorCode' => $rateLimited ? 'RATE_LIMIT' : null,
                    'retryAfter' => $retryAfter,
                ];
            }
            return ['items' => $items, 'rate_limited' => $rateLimited, 'retry_after' => $retryAfter, 'session_reference' => null];
        }

        $sessionRef = $batch['sessionReference'] ?? null;
        foreach ($prepared as $id => $p) {
            $invoice = $p['invoice'];
            $res = $batch['items'][$id] ?? [
                'ok' => false, 'statusCode' => 0, 'statusDesc' => 'Brak wyniku z sesji KSeF.', 'ksefNumber' => '',
                'sessionReference' => (string)$sessionRef, 'invoiceReference' => '', 'error' => 'Brak wyniku z sesji KSeF.',
            ];

            if (!empty($res['error'])) {
                // Wysyłka tego dokumentu w sesji nie powiodła się (wyjątek per dokument).
                $ex = $res['exception'] ?? null;
                $info = $ex instanceof \Throwable
                    ? $this->describeKsefException($ex)
                    : ['message' => (string)$res['error'], 'rate_limited' => false, 'retry_after' => null];
                if ($info['rate_limited']) {
                    $rateLimited = true;
                    $retryAfter = $info['retry_after'];
                }
                $invoice->set('workflow_status', $info['rate_limited'] ? 'issued' : 'error');
                $invoice->set('ksef_status', $ex instanceof \Throwable ? (string)(int)$ex->getCode() : '');
                $invoice->set('ksef_desc', 'KSeF: ' . $info['message']);
                if (!empty($res['sessionReference'])) {
                    $invoice->set('ksef_session_reference', (string)$res['sessionReference']);
                }
                $this->Invoices->save($invoice);
                $this->logKsefSendEvent($companyId, $id, 'send_exception', [
                    'source' => $source, 'env' => $environment, 'stage' => 'send',
                    'message' => $info['message'],
                    'exception_class' => $ex instanceof \Throwable ? get_class($ex) : null,
                    'http_code' => $ex instanceof \Throwable ? $ex->getCode() : null,
                ]);
                $items[$id] = [
                    'success' => false,
                    'error' => ($info['rate_limited'] ? 'Limit KSeF (429): ' : 'Błąd wysyłki do KSeF: ') . $info['message'],
                    'errorCode' => $info['rate_limited'] ? 'RATE_LIMIT' : null,
                    'retryAfter' => $info['retry_after'],
                ];
                continue;
            }

            if (!empty($res['pending'])) {
                // Dokument wysłany, ale KSeF nie zwrócił jeszcze statusu — zostaje 'sending' z referencjami;
                // numer dociągnie „Uzupełnij nr KSeF" (refreshKsefNumber).
                if (!empty($res['sessionReference'])) $invoice->set('ksef_session_reference', (string)$res['sessionReference']);
                if (!empty($res['invoiceReference'])) $invoice->set('ksef_invoice_reference', (string)$res['invoiceReference']);
                $invoice->set('ksef_desc', (string)($res['statusDesc'] ?? ''));
                $this->Invoices->save($invoice);
                $this->logKsefSendEvent($companyId, $id, 'send_pending', [
                    'source' => $source, 'env' => $environment,
                    'session_reference' => (string)($res['sessionReference'] ?? ''), 'invoice_reference' => (string)($res['invoiceReference'] ?? ''),
                ]);
                $items[$id] = ['success' => false, 'error' => 'Wysłano do KSeF, status jeszcze niedostępny — numer uzupełni „Uzupełnij nr KSeF".', 'errorCode' => 'PENDING'];
                continue;
            }

            // Wynik — jak w sendInvoiceToKsefCore.
            $items[$id] = $this->applyKsefSendResult($invoice, $companyId, $environment, $source, $res, $p['xml']);
        }

        return ['items' => $items, 'rate_limited' => $rateLimited, 'retry_after' => $retryAfter, 'session_reference' => $sessionRef];
    }

    /**
     * Zapis wyniku wysyłki (status, numer KSeF, referencje, hash XML, logi, auto-mail) — identycznie jak w Core.
     * $xml = null (reconcile) → hash XML pozostaje bez zmian.
     */
    private function applyKsefSendResult(Invoice $invoice, string $companyId, string $environment, string $source, array $res, ?string $xml): array
    {
        $id = (string)$invoice->id;
        $desc = (string)($res['statusDesc'] ?? '');
        $refs = ' [S=' . (string)($res['sessionReference'] ?? '') . ', I=' . (string)($res['invoiceReference'] ?? '') . ']';
        $invoice->set('ksef_status', (string)($res['statusCode'] ?? ''));
        $invoice->set('ksef_desc', trim($desc . $refs));
        if (!empty($res['ksefNumber']))        $invoice->set('ksef_number', (string)$res['ksefNumber']);
        if (!empty($res['sessionReference']))  $invoice->set('ksef_session_reference', (string)$res['sessionReference']);
        if (!empty($res['invoiceReference']))  $invoice->set('ksef_invoice_reference', (string)$res['invoiceReference']);
        if (!empty($res['ok'])) {
            $invoice->set('workflow_status', 'sent');
            if (is_string($xml) && $xml !== '') {
                $invoice->set('ksef_xml_hash', rtrim(strtr(base64_encode(hash('sha256', $xml, true)), '+/', '-_'), '='));
            }
        } elseif (!empty(trim((string)($invoice->ksef_number ?? '')))) {
            $invoice->set('workflow_status', 'sent');
        } else {
            $invoice->set('workflow_status', 'error');
        }
        $invoice->set('planned_ksef_send_at', null);
        $this->Invoices->save($invoice);

        if (!empty($res['ok'])) {
            $this->logKsefSendEvent($companyId, $id, 'send_success', [
                'source' => $source, 'env' => $environment,
                'status_code' => (string)($res['statusCode'] ?? ''), 'ksef_number' => (string)($res['ksefNumber'] ?? ''),
                'session_reference' => (string)($res['sessionReference'] ?? ''),
            ]);
            $this->enqueueAutoSendEmailIfEnabled($invoice, $companyId, $source);

            return ['success' => true, 'result' => $res];
        }
        $this->logKsefSendEvent($companyId, $id, 'send_error', [
            'source' => $source, 'env' => $environment,
            'status_code' => (string)($res['statusCode'] ?? ''), 'message' => $desc,
        ]);

        return ['success' => false, 'error' => 'Nie udało się wysłać do KSeF (' . (string)($res['statusCode'] ?? '') . '): ' . $desc, 'result' => $res];
    }

    /**
     * Dociąga wyniki faktur wysłanych sesją wsadową, których status nie był jeszcze dostępny:
     * workflow 'sending' + ksef_session_reference ustawione + brak numeru KSeF (ostatnie 48 h).
     * Grupuje po sesji, czyta listę faktur sesji i zapisuje wynik jak po zwykłej wysyłce.
     *
     * @return array<string, array> id => item (success/error), tylko faktury z rozstrzygniętym wynikiem
     */
    public function reconcileKsefBatchSessions(string $companyId, string $environment = 'prod', string $source = 'api_bulk'): array
    {
        $items = [];
        try {
            $pending = $this->Invoices->find()
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.workflow_status' => 'sending',
                    'Invoices.ksef_session_reference IS NOT' => null,
                    'Invoices.ksef_session_reference !=' => '',
                    'OR' => [['Invoices.ksef_number IS' => null], ['Invoices.ksef_number' => '']],
                    'Invoices.modified >=' => date('Y-m-d H:i:s', strtotime('-48 hours')),
                ])
                ->all()
                ->toList();
        } catch (\Throwable $e) {
            return $items;
        }
        if (empty($pending)) {
            return $items;
        }
        $bySession = [];
        foreach ($pending as $inv) {
            $bySession[(string)$inv->ksef_session_reference][(string)$inv->id] = $inv;
        }
        $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        foreach ($bySession as $sessionRef => $invoices) {
            $numbersByKey = [];
            foreach ($invoices as $id => $inv) {
                $numbersByKey[$id] = (string)($inv->fullnumber ?? '');
            }
            try {
                $results = $service->reconcileBatchSession($companyId, $environment, $sessionRef, $numbersByKey);
            } catch (\Throwable $e) {
                foreach ($invoices as $id => $inv) {
                    $this->logKsefSendEvent($companyId, (string)$id, 'reconcile_error', [
                        'source' => $source, 'env' => $environment, 'session_reference' => $sessionRef, 'message' => $e->getMessage(),
                    ]);
                }
                continue;
            }
            foreach ($invoices as $id => $inv) {
                $res = $results[$id] ?? null;
                if ($res === null || !empty($res['pending'])) {
                    continue; // nadal przetwarzana
                }
                $items[$id] = $this->applyKsefSendResult($inv, $companyId, $environment, $source, $res, null);
            }
        }

        return $items;
    }

    /**
     * Czytelny opis wyjątku klienta KSeF (status.details / exceptionDetailList) + detekcja limitu 429
     * z czasem odczekania w sekundach wyciągniętym z komunikatu KSeF („Spróbuj ponownie po 8 minutach i 36 sekundach").
     *
     * @return array{message:string, rate_limited:bool, retry_after:?int}
     */
    private function describeKsefException(\Throwable $e): array
    {
        $message = trim((string)$e->getMessage());
        try {
            $ctx = (property_exists($e, 'context') && $e->context !== null) ? json_decode(json_encode($e->context), true) : null;
            if (is_array($ctx)) {
                if (!empty($ctx['status']['details'][0])) {
                    $message = (string)$ctx['status']['details'][0];
                } elseif (!empty($ctx['exception']['exceptionDetailList'][0])) {
                    $d = $ctx['exception']['exceptionDetailList'][0];
                    $message = trim((string)($d['exceptionCode'] ?? '') . ' ' . (string)($d['exceptionDescription'] ?? ''));
                }
            }
        } catch (\Throwable) {
            // ignore
        }
        if ($message === '') {
            $message = get_class($e) . ' (HTTP ' . (int)$e->getCode() . ')';
        }
        $rateLimited = (int)$e->getCode() === 429;
        $retryAfter = null;
        if ($rateLimited) {
            $retryAfter = 0;
            if (preg_match('/(\d+)\s*minut/u', $message, $mm)) { $retryAfter += 60 * (int)$mm[1]; }
            if (preg_match('/(\d+)\s*sekund/u', $message, $ms)) { $retryAfter += (int)$ms[1]; }
            if ($retryAfter <= 0) { $retryAfter = 60; }
        }

        return ['message' => $message, 'rate_limited' => $rateLimited, 'retry_after' => $retryAfter];
    }

    /**
     * Automatyczna wysyłka maila do nabywcy po wystawieniu/akceptacji faktury,
     * jeśli na fakturze zaznaczono `auto_send`.
     *
     * Dodaje wpis do InvoiceEmailQueue (faktyczna wysyłka idzie przez processEmailQueue).
     * Bezpieczne do wołania z wielu miejsc — nie rzuca wyjątków, deduplikuje wpisy i
     * respektuje regułę KSeF (przy włączonym trybie KSeF mail idzie dopiero gdy faktura
     * ma numer KSeF; typy zwolnione: proforma/novat/rental — od razu).
     */
    public function enqueueAutoSendEmailIfEnabled(Invoice $invoice, string $companyId, string $source = ''): void
    {
        try {
            $fresh = $this->Invoices->find()
                ->select(['Invoices.id', 'Invoices.auto_send', 'Invoices.type', 'Invoices.ksef_number'])
                ->contain(['InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'email'])])
                ->where(['Invoices.id' => (string)$invoice->id, 'Invoices.company_id' => $companyId])
                ->first();

            if (!$fresh || empty($fresh->auto_send)) {
                return; // auto-wysyłka nieaktywna
            }

            // Reguła KSeF — spójna z bulk send_email: przy włączonym KSeF i typie niezwolnionym
            // mail wysyłamy dopiero gdy faktura ma numer KSeF (czyli po akceptacji).
            $type = (string)($fresh->type ?? 'vat');
            $ksefExemptTypes = ['proforma', 'novat', 'rental'];
            if ($this->isKsefModeEnabled($companyId) && !in_array($type, $ksefExemptTypes, true)) {
                if (trim((string)($fresh->ksef_number ?? '')) === '') {
                    return; // jeszcze nie w KSeF — trigger zadziała po udanej wysyłce
                }
            }

            $email = trim((string)($fresh->invoice_contractor?->email ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'auto_email_skip', [
                    'source'  => $source,
                    'message' => 'Auto-wysyłka: brak lub niepoprawny e-mail nabywcy.',
                ]);
                return;
            }

            /** @var \App\Model\Table\InvoiceEmailQueueTable $Queue */
            $Queue = $this->fetchTable('InvoiceEmailQueue');

            // Unikaj duplikatów pending/sending dla tej faktury + e-maila
            $exists = $Queue->find()
                ->where([
                    'invoice_id' => (string)$invoice->id,
                    'email'      => $email,
                    'status IN'  => ['pending', 'sending'],
                ])
                ->count();
            if ($exists > 0) {
                return;
            }

            $entry = $Queue->newEntity([
                'invoice_id'   => (string)$invoice->id,
                'company_id'   => $companyId,
                'email'        => $email,
                'status'       => 'pending',
                'attempts'     => 0,
                'scheduled_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            if ($Queue->save($entry)) {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'auto_email_queued', [
                    'source' => $source,
                    'email'  => $email,
                ]);
            }
        } catch (\Throwable $e) {
            // Auto-wysyłka nie może przerwać procesu wystawiania/wysyłki
            try {
                $this->logKsefSendEvent($companyId, (string)$invoice->id, 'auto_email_error', [
                    'source'  => $source,
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable) {}
        }
    }

    /**
     * GET /invoices/preview-ksef-number/{id}
     * Zwraca podgląd numeru który zostanie nadany fakturze przy wysyłce do KSeF — bez zapisu.
     */
    /**
     * POST /invoices/{id}/refresh-ksef-number — synchronizacja numeru KSeF.
     *
     * Scenariusz: wysyłka faktycznie przeszła (KSeF przyjął fakturę), ale aplikacja
     * potraktowała status przejściowy (np. HTTP 150 „Trwa przetwarzanie") jako błąd
     * i nie zapisała numeru KSeF — kolejne próby kończą się 440 „Duplikat faktury",
     * a faktura lokalnie widnieje jako niewysłana. Akcja odpytuje KSeF o faktury
     * WYSTAWIONE (Subject1) po numerze faktury i uzupełnia ksef_number + status.
     * Zwraca JSON: { success, ksef_number?, message }.
     */
    public function refreshKsefNumber(string $id)
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;

        $json = function (array $payload, int $status = 200) {
            return $this->response->withStatus($status)->withType('application/json')
                ->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
        };

        // Twardy try/catch całości: każdy wyjątek ma wrócić jako JSON (frontend pokazuje message),
        // nigdy jako HTML-owa strona błędu („Nieoczekiwana odpowiedź serwera").
        try {
            return $this->doRefreshKsefNumber($id, $json);
        } catch (\Throwable $e) {
            try {
                \Cake\Log\Log::error('[KSEF-SYNC] inv=' . $id . ' ' . get_class($e) . ': ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine());
            } catch (\Throwable) {
                // ignore
            }
            return $json([
                'success' => false,
                'message' => 'Błąd synchronizacji: ' . get_class($e) . ': ' . ($e->getMessage() ?: '(brak treści)'),
            ], 500);
        }
    }

    /** @param callable $json fabryka odpowiedzi JSON z refreshKsefNumber() */
    private function doRefreshKsefNumber(string $id, callable $json)
    {
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $invoice = $this->Invoices->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();
        if (!$invoice) {
            return $json(['success' => false, 'message' => 'Nie znaleziono faktury.'], 404);
        }

        if (trim((string)($invoice->ksef_number ?? '')) !== '') {
            return $json([
                'success' => true,
                'ksef_number' => (string)$invoice->ksef_number,
                'message' => 'Faktura ma już numer KSeF: ' . (string)$invoice->ksef_number,
            ]);
        }

        $envRaw = (string)($this->request->getData('env') ?? $this->request->getQuery('env') ?? 'prod');
        $environment = ($envRaw === 'test') ? 'test' : 'prod';

        $fullnumber = trim((string)($invoice->fullnumber ?? ''));
        if ($fullnumber === '') {
            return $json(['success' => false, 'message' => 'Faktura nie ma numeru — nie można wyszukać w KSeF.']);
        }

        // Zakres dat: od daty faktury (z zapasem) do dziś — filtr po dacie przyjęcia w KSeF.
        $fromStr = date('Y-m-d', strtotime('-60 days'));
        try {
            $d = $invoice->date;
            if (is_object($d) && method_exists($d, 'format')) {
                $fromStr = date('Y-m-d', strtotime($d->format('Y-m-d') . ' -3 days'));
            }
        } catch (\Throwable) {
            // zostaje domyślne -60 dni
        }
        $toStr = date('Y-m-d');

        try {
            $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
            $vm = $service->buildIssuedViewModel($companyId, $environment, [
                'inv'  => $fullnumber,
                'from' => $fromStr,
                'to'   => $toStr,
            ]);
        } catch (\Throwable $e) {
            return $json(['success' => false, 'message' => 'Błąd zapytania do KSeF: ' . $e->getMessage()], 502);
        }
        if (!empty($vm['error'])) {
            return $json(['success' => false, 'message' => (string)$vm['error']], 502);
        }

        // Dokładne dopasowanie numeru faktury + niepusty numer KSeF (deduplikacja po numerze KSeF).
        $rows = is_array($vm['invoices'] ?? null) ? $vm['invoices'] : [];
        $hits = [];
        foreach ($rows as $r) {
            $num = trim((string)($r['fullnumber'] ?? ''));
            $kn  = trim((string)($r['ksef_number'] ?? ''));
            if ($kn !== '' && $num === $fullnumber) {
                $hits[$kn] = $r;
            }
        }
        $hits = array_values($hits);

        if (count($hits) === 0) {
            $this->logKsefSendEvent($companyId, (string)$invoice->id, 'ksef_sync', [
                'env' => $environment,
                'message' => 'Nie znaleziono faktury ' . $fullnumber . ' wśród wystawionych w KSeF (zakres ' . $fromStr . ' – ' . $toStr . ').',
            ]);
            return $json([
                'success' => false,
                'message' => 'Nie znaleziono faktury ' . $fullnumber . ' wśród wystawionych w KSeF (' . $environment . '). '
                    . 'Jeśli wysyłka była przed chwilą, spróbuj ponownie za kilka minut.',
            ]);
        }
        if (count($hits) > 1) {
            return $json([
                'success' => false,
                'message' => 'W KSeF znaleziono ' . count($hits) . ' różne numery KSeF dla faktury ' . $fullnumber
                    . ' — nie można jednoznacznie uzupełnić. Sprawdź ręcznie w KSeF.',
            ]);
        }

        $ksefNumber = trim((string)$hits[0]['ksef_number']);
        $invoice->set('ksef_number', $ksefNumber);
        $invoice->set('ksef_status', '200');
        $desc = trim((string)($invoice->ksef_desc ?? ''));
        $invoice->set('ksef_desc', trim($desc . ' [numer KSeF uzupełniony synchronizacją ' . date('Y-m-d H:i') . ']'));
        $invoice->set('workflow_status', 'sent');
        if (!$this->Invoices->save($invoice)) {
            return $json(['success' => false, 'message' => 'Znaleziono numer ' . $ksefNumber . ', ale nie udało się zapisać faktury.'], 500);
        }
        $this->logKsefSendEvent($companyId, (string)$invoice->id, 'ksef_sync', [
            'env' => $environment,
            'status_code' => '200',
            'ksef_number' => $ksefNumber,
            'message' => 'Numer KSeF uzupełniony synchronizacją — faktura była przyjęta w KSeF.',
        ]);

        return $json([
            'success' => true,
            'ksef_number' => $ksefNumber,
            'message' => 'Uzupełniono numer KSeF: ' . $ksefNumber,
        ]);
    }

    public function previewKsefNumber(string $id)
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $invoice = $this->Invoices->find()
            ->select(['id', 'fullnumber', 'number', 'invoice_series_id', 'date', 'type', 'company_id'])
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if (!$invoice) {
            return $this->response->withStatus(404)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Nie znaleziono faktury.']));
        }

        // Jeśli numer już nadany — zwróć go od razu
        $fullnumber = trim((string)($invoice->fullnumber ?? ''));
        if ($fullnumber !== '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'fullnumber' => $fullnumber, 'already_assigned' => true]));
        }

        // Oblicz przewidywany numer — bez zapisu
        try {
            $seriesId = (string)($invoice->invoice_series_id ?? '');
            if ($seriesId === '') {
                throw new \RuntimeException('Brak przypisanej serii numeracji.');
            }

            $issueDateRaw = $invoice->date;
            $issueDate = is_object($issueDateRaw) && method_exists($issueDateRaw, 'format')
                ? $issueDateRaw->format('Y-m-d')
                : (string)$issueDateRaw;
            if ($issueDate === '') {
                $issueDate = date('Y-m-d');
            }

            $InvoiceSeries = $this->fetchTable('InvoiceSeries');
            $series = $InvoiceSeries->find()
                ->contain(['InvoiceSeriesPeriods'])
                ->where(['InvoiceSeries.id' => $seriesId, 'InvoiceSeries.company_id' => $companyId])
                ->first();
            if (!$series) {
                throw new \RuntimeException('Nie znaleziono serii numeracji.');
            }

            $dateObject = new \DateTimeImmutable($issueDate);
            $year  = (int)$dateObject->format('Y');
            $month = (int)$dateObject->format('m');

            $where = [
                'company_id'        => $companyId,
                'invoice_series_id' => $series->id,
                'fullnumber IS NOT' => null,
                'id !='             => $invoice->id,
            ];

            $periodName = (string)($series->invoice_series_period->name ?? '');
            if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
                $where['year']  = $year;
                $where['month'] = $month;
            } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
                $where['year'] = $year;
            }

            $lastInvoice = $this->Invoices->find()
                ->where($where)
                ->order(['number' => 'DESC', 'id' => 'DESC'])
                ->first();

            // override_next_number — tylko odczyt, bez kasowania
            $overrideNext = $series->override_next_number ?? null;
            if ($overrideNext !== null && (int)$overrideNext > 0) {
                $nextNumber = (int)$overrideNext;
            } elseif ($lastInvoice) {
                $extractedNumber = !empty($lastInvoice->number)
                    ? (int)$lastInvoice->number
                    : $this->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
                $nextNumber = $extractedNumber + 1;
            } else {
                $nextNumber = (int)($series->starting_number ?: 1);
            }

            $template   = (string)($series->series_template ?: '[numer]');
            $fullnumber = $this->formatInvoicePattern($template, $nextNumber, $issueDate);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'fullnumber' => $fullnumber, 'already_assigned' => false]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(400)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * POST /invoices/send-to-ksef/{id}?env=test|prod
     * Opcja ponownego wysłania istniejącej faktury do KSeF (na podstawie przesłanego XML lub generatora FA(3)).
     */
    public function sendToKsef(string $id)
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $env = (string)$this->request->getQuery('env', 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $invoice = $this->Invoices->get($id, contain: ['InvoiceContractors','InvoiceCompanyDetails','InvoiceContents' => ['Vats'], 'Companies']);

        // 1) XML z uploadu
        $xml = null;
        try {
            $uploaded = $this->request->getData('ksef_xml');
            if ($uploaded instanceof UploadedFileInterface) {
                if ($uploaded->getError() === UPLOAD_ERR_OK && (int)$uploaded->getSize() > 0) {
                    $xml = (string)$uploaded->getStream()->getContents();
                }
            } elseif (is_array($uploaded) && !empty($uploaded['tmp_name']) && is_file($uploaded['tmp_name'])) {
                $xml = (string)file_get_contents($uploaded['tmp_name']);
            }
        } catch (\Throwable) { /* ignore */ }

        $jsonMode = $this->request->is('ajax') || $this->request->getQuery('_ext') === 'json' || $this->request->accepts('application/json');
        try {
            $send = $this->sendInvoiceToKsefCore($invoice, $companyId, $environment, $xml, 'sendToKsef');
            $res = (array)($send['result'] ?? []);

            if (!$send['success']) {
                if ($jsonMode) {
                    return $this->response->withStatus(400)->withType('application/json')
                        ->withStringBody(json_encode(['success' => false, 'error' => (string)($send['error'] ?? 'Błąd wysyłki')]));
                }
                $this->Flash->error((string)($send['error'] ?? 'Błąd wysyłki do KSeF.'));
                return $this->redirect(['action' => 'view', $id]);
            }

            if ($jsonMode) {
                $ok = true;
                // Re-fetch fullnumber — may have been assigned during send
                $freshFullnumber = '';
                try {
                    $fresh = $this->Invoices->find()->select(['id', 'fullnumber'])->where(['id' => $id])->first();
                    $freshFullnumber = (string)($fresh->fullnumber ?? '');
                } catch (\Throwable) {}
                $payload = [
                    'success' => $ok,
                    'statusCode' => (int)($res['statusCode'] ?? 0),
                    'statusDesc' => (string)($res['statusDesc'] ?? ''),
                    'ksefNumber' => (string)($res['ksefNumber'] ?? ''),
                    'sessionReference' => (string)($res['sessionReference'] ?? ''),
                    'invoiceReference' => (string)($res['invoiceReference'] ?? ''),
                    'fullnumber' => $freshFullnumber,
                    'links' => [],
                    'messages' => $res['messages'] ?? []
                ];
                if ($ok && !empty($res['ksefNumber'])) {
                    $payload['links']['downloadInvoiceTest'] = Router::url([
                        'controller' => 'Invoices',
                        'action' => 'downloadKsef',
                        '?' => ['env' => $environment, 'ksef_number' => (string)$res['ksefNumber']]
                    ], true);
                    // Przekaż session_reference, aby umożliwić pobranie UPO bez dodatkowych lookupów
                    $payload['links']['downloadUpoTest'] = Router::url([
                        'controller' => 'Invoices',
                        'action' => 'downloadUpo',
                        '?' => [
                            'env' => $environment,
                            'ksef_number' => (string)$res['ksefNumber'],
                            'session_reference' => (string)($res['sessionReference'] ?? '')
                        ]
                    ], true);
                    // Link do pobrania UPO bezpośrednio jako PDF (generowany lokalnie z XML)
                    $payload['links']['downloadUpoPdfTest'] = Router::url([
                        'controller' => 'Invoices',
                        'action' => 'downloadUpoPdf',
                        '?' => [
                            'env' => $environment,
                            'ksef_number' => (string)$res['ksefNumber'],
                            'session_reference' => (string)($res['sessionReference'] ?? '')
                        ]
                    ], true);
                    // Link do podglądu UPO jako czysty HTML
                    $payload['links']['viewUpoHtmlTest'] = Router::url([
                        'controller' => 'Invoices',
                        'action' => 'upoHtml',
                        '?' => [
                            'env' => $environment,
                            'ksef_number' => (string)$res['ksefNumber'],
                            'session_reference' => (string)($res['sessionReference'] ?? '')
                        ]
                    ], true);
                }
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $this->Flash->success('Wysłano do KSeF. Numer KSeF: ' . (string)($res['ksefNumber'] ?? ''));
        } catch (\Throwable $e) {
            if ($jsonMode) {
                return $this->response->withStatus(500)->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
            }
            $this->Flash->error('Błąd wysyłki do KSeF: ' . $e->getMessage());
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * GET /invoices/process-email-queue?key=SCHEDULER_KEY[&limit=20]
     * Przetwarza kolejkę wysyłki faktur e-mailem. Można wywołać z crona jako URL.
     * Zabezpieczony kluczem App.ksefSchedulerKey.
     */
    public function processEmailQueue(): Response
    {
        $this->request->allowMethod(['get', 'post']);

        $identity     = $this->request->getAttribute('identity');
        $schedulerKey = (string)(Configure::read('App.ksefSchedulerKey') ?? '');
        $providedKey  = (string)$this->request->getQuery('key', '');

        if (!$identity) {
            if ($schedulerKey === '' || !hash_equals($schedulerKey, $providedKey)) {
                return $this->response->withStatus(403)->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE));
            }
        }

        $limit = max(1, min(100, (int)$this->request->getQuery('limit', 20)));

        /** @var \App\Model\Table\InvoiceEmailQueueTable $Queue */
        $Queue    = $this->fetchTable('InvoiceEmailQueue');
        $appUrl   = rtrim((string)(getenv('APP_URL') ?: Configure::read('App.fullBaseUrl') ?: ''), '/');

        $pending = $Queue->find()
            ->where([
                'status'          => 'pending',
                'attempts <'      => 3,
                'scheduled_at <=' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
            ->orderAsc('scheduled_at')
            ->limit($limit)
            ->all();

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];

        foreach ($pending as $entry) {
            $entry->status   = 'sending';
            $entry->attempts = $entry->attempts + 1;
            $Queue->save($entry);

            // Pobierz podstawowe dane faktury
            try {
                $invoice = $this->Invoices->get($entry->invoice_id, [
                    'contain' => ['Companies', 'InvoiceCompanyDetails', 'InvoiceContractors'],
                ]);
            } catch (\Throwable $e) {
                $entry->status     = $entry->attempts >= 3 ? 'failed' : 'pending';
                $entry->last_error = 'Faktura nie znaleziona: ' . mb_substr($e->getMessage(), 0, 200);
                $Queue->save($entry);
                $results['failed']++;
                $results['details'][] = ['id' => $entry->invoice_id, 'error' => 'not_found'];
                continue;
            }

            // Generuj PDF przez wewnętrzny endpoint
            $pdfContent = '';
            if ($schedulerKey !== '' && $appUrl !== '') {
                $pdfUrl = $appUrl . '/invoices/generate-pdf-internal/' . $entry->invoice_id
                    . '?key=' . urlencode($schedulerKey)
                    . '&company_id=' . urlencode($entry->company_id);
                try {
                    $http = new \Cake\Http\Client(['timeout' => 60]);
                    $resp = $http->get($pdfUrl);
                    if ($resp->getStatusCode() === 200 && str_starts_with($resp->getHeaderLine('Content-Type'), 'application/pdf')) {
                        $pdfContent = (string)$resp->getBody();
                    } else {
                        $errBody = mb_substr((string)$resp->getBody(), 0, 200);
                        $entry->status     = $entry->attempts >= 3 ? 'failed' : 'pending';
                        $entry->last_error = 'PDF error ' . $resp->getStatusCode() . ': ' . $errBody;
                        $Queue->save($entry);
                        $results['failed']++;
                        continue;
                    }
                } catch (\Throwable $e) {
                    $entry->status     = $entry->attempts >= 3 ? 'failed' : 'pending';
                    $entry->last_error = 'PDF HTTP: ' . mb_substr($e->getMessage(), 0, 200);
                    $Queue->save($entry);
                    $results['failed']++;
                    continue;
                }
            } else {
                $entry->status     = 'pending';
                $entry->last_error = 'Brak APP_KSEF_SCHEDULER_KEY lub APP_URL.';
                $Queue->save($entry);
                $results['skipped']++;
                continue;
            }

            // Wyślij e-mail
            $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
            $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';
            $sellerName = trim((string)($invoice->invoice_company_detail?->name ?? $invoice->company?->name ?? ''));
            $subject    = 'Faktura ' . $fullnumber . ($sellerName !== '' ? ' od ' . $sellerName : '');

            try {
                $mailer = new \Cake\Mailer\Mailer('default');
                $mailer->setTo($entry->email);
                $mailer->addBcc('k@3ck.pl');
                $mailer->setSubject($subject);
                $mailer->setEmailFormat('html');
                $mailer->setAttachments([
                    $filename => ['data' => $pdfContent, 'mimetype' => 'application/pdf'],
                ]);
                $mailer->viewBuilder()->setLayout('default')->setTemplate('invoice_email');
                $mailer->setViewVars([
                    'invoice'    => $invoice,
                    'fullnumber' => $fullnumber,
                    'sellerName' => $sellerName,
                ]);
                $mailer->deliver();

                $entry->status  = 'sent';
                $entry->sent_at = new \DateTimeImmutable();
                $Queue->save($entry);

                $this->Invoices->updateAll(
                    ['email_sent_at' => new \DateTimeImmutable()],
                    ['id' => $entry->invoice_id]
                );

                $results['sent']++;
                $results['details'][] = ['number' => $fullnumber, 'to' => $entry->email];
                \Cake\Log\Log::info('[processEmailQueue] sent invoice=' . $entry->invoice_id . ' to=' . $entry->email);
            } catch (\Throwable $e) {
                $entry->status     = $entry->attempts >= 3 ? 'failed' : 'pending';
                $entry->last_error = mb_substr($e->getMessage(), 0, 500);
                $Queue->save($entry);
                $results['failed']++;
                \Cake\Log\Log::error('[processEmailQueue] mail error invoice=' . $entry->invoice_id . ': ' . $e->getMessage());
            }
        }

        $results['queued_total'] = $pending->count();
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * GET /invoices/generate-pdf-internal/{id}?key=SCHEDULER_KEY
     * Wewnętrzny endpoint dla crona/komendy — generuje PDF faktury bez sesji.
     * Zabezpieczony tym samym kluczem co runPlannedDrafts (App.ksefSchedulerKey).
     */
    public function generatePdfInternal(string $id): Response
    {
        $this->request->allowMethod(['get']);

        $schedulerKey = (string)(Configure::read('App.ksefSchedulerKey') ?? '');
        $providedKey  = (string)$this->request->getQuery('key', '');
        if ($schedulerKey === '' || !hash_equals($schedulerKey, $providedKey)) {
            return $this->response->withStatus(403)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak autoryzacji.']));
        }

        $companyId = (string)$this->request->getQuery('company_id', '');

        try {
            $conditions = ['Invoices.id' => $id];
            if ($companyId !== '') {
                $conditions['Invoices.company_id'] = $companyId;
            }
            $invoice = $this->Invoices->get($id, [
                'contain' => [
                    'InvoiceContractors',
                    'InvoiceContents' => ['Vats'],
                    'Companies',
                    'InvoiceCompanyDetails',
                ],
                'conditions' => $conditions,
            ]);
        } catch (\Throwable $e) {
            return $this->response->withStatus(404)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Faktura nie znaleziona.']));
        }

        $xml = '';
        try {
            $xml = $this->buildFa3Xml($invoice);
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Błąd XML: ' . $e->getMessage()]));
        }

        if (trim($xml) === '') {
            return $this->response->withStatus(500)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Pusty XML.']));
        }

        $isDraft = ((string)($invoice->workflow_status ?? '')) === 'draft';
        $apiUrl  = $isDraft
            ? (getenv('INVOICE_DRAFT_API_URL') ?: 'https://faktury24-draft.3ckstudio.pl/api/invoice')
            : (getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice');

        $seller    = $invoice->invoice_company_detail ?? null;
        $nip       = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
        $issueDate = $invoice->date ? $invoice->date->format('d-m-Y') : '';
        $invRef    = (string)($invoice->ksef_invoice_reference ?? '');
        $qrCode    = ($this->shouldShowKsefQr($invoice) && $nip !== '' && $issueDate !== '' && $invRef !== '')
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
                return $this->response->withStatus(502)->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'error' => 'Błąd API PDF: ' . $resp->getStatusCode()]));
            }

            $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
            $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';

            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withStringBody((string)$resp->getBody());
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * GET|POST /invoices/run-planned-drafts?env=test|prod&limit=50
     * Uruchamia wsadową wysyłkę zaplanowanych dokumentów roboczych (workflow_status=draft).
     */
    public function runPlannedDrafts(): Response
    {
        $this->request->allowMethod(['get', 'post']);

        $identity = $this->request->getAttribute('identity');
        $schedulerKey = (string)(Configure::read('App.ksefSchedulerKey') ?? '');
        $providedKey = (string)$this->request->getQuery('key', '');
        if (!$identity) {
            if ($schedulerKey === '' || !hash_equals($schedulerKey, $providedKey)) {
                return $this->response
                    ->withStatus(403)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error' => 'Brak autoryzacji do uruchomienia scheduler-a.',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        $env = (string)$this->request->getQuery('env', 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));
        $today = (new FrozenTime('today'))->format('Y-m-d');

        $due = $this->Invoices->find()
            ->contain(['InvoiceContractors','InvoiceCompanyDetails','InvoiceContents' => ['Vats'], 'Companies'])
            ->where([
                'Invoices.workflow_status' => 'draft',
                'Invoices.planned_ksef_send_at IS NOT' => null,
                'Invoices.planned_ksef_send_at <=' => $today,
            ])
            ->orderAsc('Invoices.planned_ksef_send_at')
            ->limit($limit)
            ->all();

        $summary = [
            'environment' => $environment,
            'today' => $today,
            'checked' => 0,
            'sent' => 0,
            'failed' => 0,
            'items' => [],
        ];

        foreach ($due as $invoice) {
            $summary['checked']++;
            $companyId = (string)($invoice->company_id ?? '');
            $result = $this->sendInvoiceToKsefCore($invoice, $companyId, $environment, null, 'scheduler');
            if (!empty($result['success'])) {
                $summary['sent']++;
            } else {
                $summary['failed']++;
            }
            $summary['items'][] = [
                'invoice_id' => (string)$invoice->id,
                'company_id' => $companyId,
                'fullnumber' => (string)($invoice->fullnumber ?? ''),
                'success' => (bool)($result['success'] ?? false),
                'error' => (string)($result['error'] ?? ''),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * POST /invoices/refresh-ksef-status/{id}?env=test|prod
     * Próbuje zweryfikować status na podstawie pobrania XML po numerze KSeF.
     */
    public function refreshKsefStatus(string $id)
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $env = (string)$this->request->getQuery('env', 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $invoice = $this->Invoices->get($id);
        $ksefNumber = trim((string)($invoice->ksef_number ?? ''));
        if ($ksefNumber === '') {
            $this->Flash->info('Ta faktura nie ma przypisanego numeru KSeF.');
            return $this->redirect(['action' => 'view', $id]);
        }

        try {
            // Użyj klienta do pobrania XML po numerze KSeF – jeżeli sukces, status=200
            $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
            $client = $ksef->buildClient($companyId, $environment);
            $req = new \N1ebieski\KSEFClient\Requests\Invoices\Download\DownloadRequest(\N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber));
            $resp = $client->invoices()->download($req);
            $body = $resp->body();
            if (is_string($body) && $body !== '') {
                $invoice->set('ksef_status', '200');
                $invoice->set('ksef_desc',   'OK');
                $this->Invoices->save($invoice);
                $this->Flash->success('Status KSeF odświeżony: OK.');
            } else {
                $this->Flash->warning('Nie udało się potwierdzić statusu KSeF.');
            }
        } catch (\Throwable $e) {
            $this->Flash->error('Błąd podczas odświeżania statusu: ' . $e->getMessage());
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * GET|POST /invoices/debug-ksef-xml/{id}?env=test|prod
     * Zwraca raport z prostego debugowania FA(3) w formacie JSON.
     * Jeśli przesłano plik ksef_xml (POST multipart), użyje uploadu; w przeciwnym razie wygeneruje FA(3) z danych faktury.
     */
    public function debugKsefXml(string $id): Response
    {
        $this->request->allowMethod(['get', 'post']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') {
            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak company_id w tożsamości.']));
        }

        $env = (string)$this->request->getQuery('env', 'prod');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        // Pobierz fakturę wraz z danymi potrzebnymi do generatora
        try {
            $invoice = $this->Invoices->get($id, contain: ['InvoiceContractors','InvoiceCompanyDetails','InvoiceContents' => ['Vats'], 'Companies']);
        } catch (\Throwable $e) {
            return $this->response->withStatus(404)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Nie znaleziono faktury.']));
        }

        // Priorytet: upload z pola ksef_xml (POST)
        $xml = null;
        if ($this->request->is('post')) {
            try {
                $uploaded = $this->request->getData('ksef_xml');
                if ($uploaded instanceof UploadedFileInterface) {
                    if ($uploaded->getError() === UPLOAD_ERR_OK && (int)$uploaded->getSize() > 0) {
                        $xml = (string)$uploaded->getStream()->getContents();
                    }
                } elseif (is_array($uploaded) && !empty($uploaded['tmp_name']) && is_file($uploaded['tmp_name'])) {
                    $xml = (string)file_get_contents($uploaded['tmp_name']);
                }
            } catch (\Throwable) { /* ignore */ }
        }

        // Fallback: zbuduj minimalny FA(3)
        if (!is_string($xml) || trim($xml) === '') {
            try { $xml = $this->buildFa3Xml($invoice); } catch (\Throwable) { $xml = ''; }
        }

        if (!is_string($xml) || trim($xml) === '') {
            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak poprawnego XML FA(3).']));
        }

        // Uruchom lokalny debug w serwisie N1KsefService
        $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $report = $service->debugInvoiceXml($companyId, $environment, $xml);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'report' => $report], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Generator FA(3) – router wg typu dokumentu.
     * Faktura podstawowa korzysta z implementacji bazowej (bez zmian zachowania).
     */
    public function buildFa3Xml(\App\Model\Entity\Invoice $inv): string
    {
        $type = strtolower((string)($inv->type ?? ''));
        $currency = strtoupper((string)($inv->currency ?? 'PLN'));

        switch ($type) {
            case 'correction':
            case 'korekta':
                return $this->buildFa3XmlCorrection($inv);
            case 'advance':
            case 'zaliczkowa':
                return $this->buildFa3XmlAdvance($inv);
            case 'final':
            case 'rozliczeniowa':
                return $this->buildFa3XmlFinal($inv);
            case 'margin':
            case 'marza':
                return $this->buildFa3XmlMargin($inv);
            default:
                if ($currency !== 'PLN') {
                    return $this->buildFa3XmlCurrency($inv);
                }
                return $this->buildFa3XmlBase($inv);
        }
    }

    /**
     * Faktura podstawowa (bazowa) – dotychczasowa implementacja 1:1.
     */
    public function buildFa3XmlBase(Invoice $inv): string
    {
        // 🔹 TU: dociągamy dane z faktury pierwotnej, jeśli jest parent_id
        $inv = $this->enrichInvoiceFromParent($inv);

        // Lazy-load relacji potrzebnych do XML (jeśli nie załadowane w contain)
        if (!$inv->has('invoice_payments')) {
            $inv->invoice_payments = $this->fetchTable('InvoicePayments')
                ->find()->where(['invoice_id' => $inv->id])->orderAsc('payment_date')->all()->toArray();
        }
        if (!$inv->has('invoice_additional_descriptions')) {
            $inv->invoice_additional_descriptions = $this->fetchTable('InvoiceAdditionalDescriptions')
                ->find()->where(['invoice_id' => $inv->id])->orderAsc('nr_wiersza')->all()->toArray();
        }
        // FA(3) LOW — lazy-load nowych relacji
        if (!$inv->has('invoice_new_transports')) {
            $inv->invoice_new_transports = $this->fetchTable('InvoiceNewTransports')
                ->find()->where(['invoice_id' => $inv->id])->all()->toArray();
        }
        if (!$inv->has('invoice_charges')) {
            $inv->invoice_charges = $this->fetchTable('InvoiceCharges')
                ->find()->where(['invoice_id' => $inv->id])->all()->toArray();
        }
        if (!$inv->has('invoice_factor_banks')) {
            $inv->invoice_factor_banks = $this->fetchTable('InvoiceFactorBanks')
                ->find()->where(['invoice_id' => $inv->id])->all()->toArray();
        }
        if (!$inv->has('invoice_authorized_entities')) {
            $inv->invoice_authorized_entities = $this->fetchTable('InvoiceAuthorizedEntities')
                ->find()->where(['invoice_id' => $inv->id])->all()->toArray();
        }
        if (!$inv->has('invoice_order_lines')) {
            $inv->invoice_order_lines = $this->fetchTable('InvoiceOrderLines')
                ->find()->where(['invoice_id' => $inv->id])->orderAsc('nr_wiersza')->all()->toArray();
        }

        $seller = $inv->invoice_company_detail ?? null;
        if ($seller === null) {
            try {
                $seller = $this->fetchTable('InvoiceCompanyDetails')
                    ->find()->where(['invoice_id' => $inv->id])->first() ?: null;
                if ($seller !== null) {
                    $inv->set('invoice_company_detail', $seller);
                }
            } catch (\Throwable) { /* ignore */ }
        }
        $buyer  = $inv->invoice_contractor ?? null;
        $items  = (array)($inv->invoice_contents ?? []);

        // Dla ROZ: zastąp pozycje faktury pozycjami proformy (pełne wartości zamówienia/umowy).
        // Wymóg XSD FaWiersz: „W przypadku faktur art. 106f ust. 3 należy wykazać pełne wartości
        // zamówienia lub umowy." P_15 pozostaje jako kwota pozostała = suma oryginalnych invoice_contents.
        $rozP15Override = null; // kwota pozostała do zapłaty (przed podmianą $items)
        if ($this->resolveRodzajFaktury($inv) === 'ROZ' && !empty($inv->parent_id)) {
            // Zapamiętaj sumę brutto z oryginalnych pozycji faktury (= kwota pozostała) PRZED podmianą
            $rozP15Override = array_sum(array_map(
                fn($it) => (float)($it->brutto ?? $it['brutto'] ?? 0),
                $items
            ));
            if ($rozP15Override <= 0.0) {
                $rozP15Override = (float)($inv->total ?? 0);
            }

            $proformaForRoz = $this->fetchTable('Invoices')->find()
                ->where(['id' => $inv->parent_id])
                ->contain(['InvoiceContents' => ['Vats']])
                ->first();
            if ($proformaForRoz && !empty($proformaForRoz->invoice_contents)) {
                $items = (array)$proformaForRoz->invoice_contents;
            } else {
                $rozP15Override = null; // brak proformy – nie podmieniamy
            }
        }

        $issueDate   = $inv->date ? $inv->date->format('Y-m-d') : date('Y-m-d');
        $soldDate    = $inv->sold_date ? $inv->sold_date->format('Y-m-d') : null;
        $isDraftInv  = ((string)($inv->workflow_status ?? '')) === 'draft';
        $number      = $isDraftInv
            ? $this->generateDraftNumber(
                (string)($inv->invoice_series_id ?? ''),
                (string)$inv->id,
                $issueDate,
                (string)($inv->company_id ?? '')
              )
            : (string)($inv->fullnumber ?? $inv->id);
        $currency    = strtoupper((string)($inv->currency ?? 'PLN'));
        $placeIssued = trim((string)($inv->place_of_issue ?? $seller?->city ?? ''));

        $xml = [];

        $xml[] = '<?xml version="1.0" encoding="utf-8"?>';
        $xml[] = sprintf(
            '<Faktura xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
            'xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="%s">',
            self::NS_FA3
        );

        $xml = array_merge($xml, $this->buildHeaderXml());
        $xml = array_merge($xml, $this->buildSellerXml($seller, $inv));
        $xml = array_merge($xml, $this->buildBuyerXml($buyer, $inv));

        // Podmiot3 — odbiorca (opcjonalny, przed PodmiotUpowazniony wg XSD FA3)
        $xml = array_merge($xml, $this->buildRecipientXml($inv));

        // PodmiotUpowazniony — optional, after Podmiot3
        $xml = array_merge($xml, $this->buildPodmiotUpowaznionyXml($inv));

        $xml = array_merge(
            $xml,
            $this->buildFaXml($inv, $items, $currency, $issueDate, $soldDate, $placeIssued, $number, $rozP15Override)
        );

        // Stopka (footer)
        $xml = array_merge($xml, $this->buildStopkaXml($inv, $seller));

        $xml[] = '</Faktura>';

        return implode("\n", $xml);
    }

    // ======================== HELPERY ========================

    private function normalizeCountryCode(string $raw): string
    {
        $v = strtoupper(trim($raw));
        if ($v === '') return 'PL';
        if (preg_match('/^[A-Z]{2}$/', $v)) return $v;
        static $map = [
            'POLSKA' => 'PL', 'POLAND' => 'PL',
            'NIEMCY' => 'DE', 'GERMANY' => 'DE', 'DEUTSCHLAND' => 'DE',
            'FRANCJA' => 'FR', 'FRANCE' => 'FR',
            'WIELKA BRYTANIA' => 'GB', 'UNITED KINGDOM' => 'GB', 'UK' => 'GB',
            'STANY ZJEDNOCZONE' => 'US', 'USA' => 'US', 'UNITED STATES' => 'US',
            'CZECHY' => 'CZ', 'CZECH REPUBLIC' => 'CZ', 'CZECHIA' => 'CZ',
            'SLOWACJA' => 'SK', 'SLOVAKIA' => 'SK',
            'AUSTRIA' => 'AT',
            'BELGIA' => 'BE', 'BELGIUM' => 'BE',
            'HOLANDIA' => 'NL', 'NIDERLANDY' => 'NL', 'NETHERLANDS' => 'NL',
            'SZWAJCARIA' => 'CH', 'SWITZERLAND' => 'CH',
            'WLOCHY' => 'IT', 'ITALY' => 'IT',
            'HISZPANIA' => 'ES', 'SPAIN' => 'ES',
            'PORTUGALIA' => 'PT', 'PORTUGAL' => 'PT',
            'SZWECJA' => 'SE', 'SWEDEN' => 'SE',
            'NORWEGIA' => 'NO', 'NORWAY' => 'NO',
            'DANIA' => 'DK', 'DENMARK' => 'DK',
            'FINLANDIA' => 'FI', 'FINLAND' => 'FI',
            'WEGRY' => 'HU', 'HUNGARY' => 'HU',
            'RUMUNIA' => 'RO', 'ROMANIA' => 'RO',
            'BULGARIA' => 'BG',
            'CHORWACJA' => 'HR', 'CROATIA' => 'HR',
            'LITWA' => 'LT', 'LITHUANIA' => 'LT',
            'LOTWA' => 'LV', 'LATVIA' => 'LV',
            'ESTONIA' => 'EE',
            'UKRAINA' => 'UA', 'UKRAINE' => 'UA',
            'ROSJA' => 'RU', 'RUSSIA' => 'RU',
            'CHINY' => 'CN', 'CHINA' => 'CN',
            'JAPONIA' => 'JP', 'JAPAN' => 'JP',
            'KANADA' => 'CA', 'CANADA' => 'CA',
            'AUSTRALIA' => 'AU',
        ];
        $upper = mb_strtoupper($raw, 'UTF-8');
        return $map[$upper] ?? 'PL';
    }

    /**
     * Prefiks kraju dla numeru VAT-UE (element KodUE, typ TKodyKrajowUE).
     * Różni się od kodu ISO adresu (etd:TKodKraju) tylko dla Grecji: ISO „GR" → VAT „EL".
     * Adres kontrahenta pozostaje „GR" (poprawne), a do numeru VAT-UE Grecja to „EL".
     */
    private function euVatCountryCode(string $iso): string
    {
        $c = strtoupper(trim($iso));
        return $c === 'GR' ? 'EL' : $c;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }

    /**
     * Sprawdza czy NIP jest poprawny wg wzorca XSD FA(3):
     * [1-9]((\d[1-9])|([1-9]\d))\d{7} — dokładnie 10 cyfr, pierwsza != 0,
     * druga i trzecia cyfra nie mogą być jednocześnie 0.
     * Przyjmuje już oczyszczony string (same cyfry).
     */
    private function isValidKsefNip(string $nip): bool
    {
        return (bool)preg_match('/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/', $nip);
    }

    /**
     * Parsuje wartość pola NIP/tax-id i zwraca tablicę:
     *   ['type' => 'NIP',   'value' => '1234567890']               — polski NIP (same cyfry, pasuje do wzorca XSD)
     *   ['type' => 'VatUE', 'prefix' => 'CZ', 'value' => 'CZ05800862'] — zagraniczny VAT-UE (zaczyna się od liter)
     *   ['type' => 'none']                                          — pusty
     *
     * Wartość przekazywana jest bez modyfikacji — tak jak wpisał użytkownik.
     */
    private function parseNip(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return ['type' => 'none'];

        // Zaczyna się od 2 liter — zagraniczny VAT-UE (np. CZ05800862, DE123456789)
        if (preg_match('/^([A-Z]{2})/i', $raw, $m)) {
            $prefix = strtoupper($m[1]);
            if ($prefix === 'PL') {
                // PL-prefix — wyciągnij cyfry i sprawdź jako polski NIP
                $digits = preg_replace('/\D+/', '', substr($raw, 2));
                if ($this->isValidKsefNip($digits)) {
                    return ['type' => 'NIP', 'value' => $digits];
                }
            }
            return ['type' => 'VatUE', 'prefix' => $prefix, 'value' => $raw];
        }

        // Same cyfry — sprawdź jako polski NIP
        $digits = preg_replace('/\D+/', '', $raw);
        if ($this->isValidKsefNip($digits)) {
            return ['type' => 'NIP', 'value' => $digits];
        }

        return ['type' => 'none'];
    }

    private function fmtAmount(float $value, int $scale = 2): string
    {
        return number_format($value, $scale, '.', '');
    }

    private function fmtQty(float $qty, int $scale = 3): string
    {
        return rtrim(rtrim(number_format($qty, $scale, '.', ''), '0'), '.');
    }

    private function emitIfNotNull(array &$xml, string $tag, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $xml[] = sprintf('    <%s>%s</%s>', $tag, $this->esc((string)$value), $tag);
    }

    // ======================== NAGŁÓWEK ========================

    private function buildHeaderXml(): array
    {
        $xml   = [];
        $xml[] = '  <Naglowek>';
        $xml[] = sprintf(
            '    <KodFormularza kodSystemowy="%s" wersjaSchemy="%s">%s</KodFormularza>',
            self::FORM_CODE_SYSTEM,
            self::FORM_SCHEMA_VER,
            self::FORM_CODE
        );
        // WariantFormularza – wariant FA(3)
        $xml[] = '    <WariantFormularza>' . self::FORM_VARIANT . '</WariantFormularza>';
        // DataWytworzeniaFa – data/czas wytworzenia pliku FA
        $xml[] = '    <DataWytworzeniaFa>' . $this->esc(gmdate('c')) . '</DataWytworzeniaFa>';
        // SystemInfo – identyfikator systemu podatnika
        $xml[] = '    <SystemInfo>Aplikacja Podatnika KSeF</SystemInfo>';
        $xml[] = '  </Naglowek>';

        return $xml;
    }

    // ======================== PODMIOT1 ========================

    private function buildSellerXml(?object $seller, Invoice $inv): array
    {
        $xml = [];

        $sellerName   = trim((string)($seller?->name ?? ''));
        $countryCode  = strtoupper((string)($seller?->country_code ?? 'PL'));

        // Identyfikatory międzynarodowe sprzedawcy — z encji Invoice (profil firmy)
        $sellerVatPrefix = trim((string)($inv->seller_vat_prefix ?? ''));
        $sellerVatEu     = trim((string)($inv->seller_vat_eu ?? ''));
        $sellerEori      = trim((string)($inv->seller_eori ?? ''));

        // TPodmiot1 wg XSD FA(3): tylko NIP (polski) w DaneIdentyfikacyjne — bez KodUE/NrVatUE
        $sellerNipParsed = $this->parseNip((string)($seller?->nip ?? ''));
        $sellerNipDigits = $sellerNipParsed['type'] === 'NIP' ? $sellerNipParsed['value'] : '';

        $xml[] = '  <Podmiot1>';
        // Kolejność wg XSD: PrefiksPodatnika? NrEORI? DaneIdentyfikacyjne Adres ...
        if ($sellerVatPrefix !== '') {
            $xml[] = '    <PrefiksPodatnika>' . $this->esc($sellerVatPrefix) . '</PrefiksPodatnika>';
        }
        if ($sellerEori !== '') {
            $xml[] = '    <NrEORI>' . $this->esc($sellerEori) . '</NrEORI>';
        }
        // DaneIdentyfikacyjne: NIP wymagany, Nazwa wymagana (TPodmiot1 bez minOccurs=0)
        $xml[] = '    <DaneIdentyfikacyjne>';
        $xml[] = '      <NIP>' . $this->esc($sellerNipDigits !== '' ? $sellerNipDigits : '0000000000') . '</NIP>';
        $xml[] = '      <Nazwa>' . $this->esc($sellerName !== '' ? $sellerName : '-') . '</Nazwa>';
        $xml[] = '    </DaneIdentyfikacyjne>';
        $xml[] = '    <Adres>';
        // KodKraju – kod kraju sprzedawcy (ISO)
        $xml[] = '      <KodKraju>' . $this->esc($countryCode) . '</KodKraju>';
        // AdresL1 – ulica, nr domu (wymagane w XSD)
        $sellerStreet = trim((string)($seller?->street ?? ''));
        if ($sellerStreet !== '') {
            $xml[] = '      <AdresL1>' . $this->esc($sellerStreet) . '</AdresL1>';
        } else {
            $sellerAdresL1Fallback = trim((string)($seller?->city ?? $sellerName));
            $xml[] = '      <AdresL1>' . $this->esc($sellerAdresL1Fallback !== '' ? $sellerAdresL1Fallback : '-') . '</AdresL1>';
        }
        // AdresL2 – kod + miejscowość
        $sellerL2 = trim(((string)($seller?->zip ?? '')) . ' ' . ((string)($seller?->city ?? '')));
        if ($sellerL2 !== '') {
            $xml[] = '      <AdresL2>' . $this->esc($sellerL2) . '</AdresL2>';
        }
        // GLN sprzedawcy (opcjonalne)
        $sellerGln = trim((string)($seller?->gln ?? ''));
        if ($sellerGln !== '') {
            $xml[] = '      <GLN>' . $this->esc($sellerGln) . '</GLN>';
        }
        $xml[] = '    </Adres>';

        // AdresKoresp — adres korespondencyjny sprzedawcy (opcjonalny, XSD: TAdres)
        $korespL1 = trim((string)($seller?->koresp_address_l1 ?? ''));
        $korespL2 = trim((string)($seller?->koresp_address_l2 ?? ''));
        if ($korespL1 !== '' || $korespL2 !== '') {
            $korespCC = strtoupper(trim((string)($seller?->koresp_country_code ?? 'PL')));
            $xml[] = '    <AdresKoresp>';
            $xml[] = '      <KodKraju>' . $this->esc($korespCC) . '</KodKraju>';
            if ($korespL1 !== '') {
                $xml[] = '      <AdresL1>' . $this->esc($korespL1) . '</AdresL1>';
            }
            if ($korespL2 !== '') {
                $xml[] = '      <AdresL2>' . $this->esc($korespL2) . '</AdresL2>';
            }
            $sellerKorespGln = trim((string)($seller?->koresp_gln ?? ''));
            if ($sellerKorespGln !== '') {
                $xml[] = '      <GLN>' . $this->esc($sellerKorespGln) . '</GLN>';
            }
            $xml[] = '    </AdresKoresp>';
        }

        // DaneKontaktowe — email / telefon sprzedawcy (opcjonalne w FA(3))
        $sellerEmail = trim((string)($seller?->email ?? ''));
        $sellerPhone = trim((string)($seller?->phone ?? ''));
        if ($sellerEmail !== '' || $sellerPhone !== '') {
            $xml[] = '    <DaneKontaktowe>';
            if ($sellerEmail !== '') {
                $xml[] = '      <Email>' . $this->esc($sellerEmail) . '</Email>';
            }
            if ($sellerPhone !== '') {
                $xml[] = '      <Telefon>' . $this->esc($sellerPhone) . '</Telefon>';
            }
            $xml[] = '    </DaneKontaktowe>';
        }

        // StatusInfoPodatnika — status informacyjny podatnika (opcjonalny)
        if (!empty($inv->status_info_podatnika)) {
            $xml[] = '    <StatusInfoPodatnika>' . (int)$inv->status_info_podatnika . '</StatusInfoPodatnika>';
        }

        $xml[] = '  </Podmiot1>';

        return $xml;
    }

    // ======================== PODMIOT2 ========================

    private function buildBuyerXml(?object $buyer, Invoice $inv): array
    {
        $xml = [];

        $buyerName   = trim((string)($buyer->name ?? ''));
        $countryCode = $this->normalizeCountryCode((string)($buyer->country ?? ''));

        // Identyfikatory międzynarodowe z invoice_contractors snapshot.
        // "NONE" to marker z katalogu kontrahentów oznaczający "Brak (spoza UE)" — traktujemy jak puste.
        $buyerVatPrefix  = trim((string)($buyer->vat_prefix ?? ''));
        if (strtoupper($buyerVatPrefix) === 'NONE') { $buyerVatPrefix = ''; }
        $buyerVatEu      = trim((string)($buyer->vat_eu ?? ''));
        $buyerTaxIdOther = trim((string)($buyer->tax_id_other ?? ''));
        $buyerTaxIdOtherCountry = strtoupper(trim((string)($buyer->tax_id_other_country ?? '')));

        // Parsuj NIP nabywcy — obsługa PL i zagranicznych (np. CZ05800862)
        $buyerNipParsed = $this->parseNip((string)($buyer->nip ?? ''));
        if ($buyerNipParsed['type'] === 'VatUE' && $buyerVatEu === '') {
            $buyerVatPrefix = $buyerVatPrefix !== '' ? $buyerVatPrefix : $buyerNipParsed['prefix'];
            $buyerVatEu     = $buyerNipParsed['value'];
        }

        $xml[] = '  <Podmiot2>';
        // NrEORI nabywcy — przed DaneIdentyfikacyjne (kolejność wg XSD)
        $buyerEori = trim((string)($buyer->eori ?? ''));
        if ($buyerEori !== '') {
            $xml[] = '    <NrEORI>' . $this->esc($buyerEori) . '</NrEORI>';
        }
        $xml[] = '    <DaneIdentyfikacyjne>';
        if ($buyerVatEu !== '') {
            // VAT UE: KodUE + NrVatUE
            $kodUE = $buyerVatPrefix !== '' ? $buyerVatPrefix : ($countryCode !== 'PL' ? $countryCode : '');
            $kodUE = $this->euVatCountryCode($kodUE); // Grecja: ISO „GR" → VAT „EL"
            // Jeśli NrVatUE zaczyna się od kodu kraju (np. "FR00951228071" lub "GR..."/"EL..."), odetnij prefix.
            $nrVatUE = $buyerVatEu;
            foreach (array_values(array_unique(array_filter([$kodUE, strtoupper($countryCode)]))) as $__pfx) {
                if ($__pfx !== '' && strncasecmp($nrVatUE, $__pfx, strlen($__pfx)) === 0) {
                    $nrVatUE = substr($nrVatUE, strlen($__pfx));
                    break;
                }
            }
            $xml[] = '      <KodUE>' . $this->esc($kodUE) . '</KodUE>';
            $xml[] = '      <NrVatUE>' . $this->esc($nrVatUE) . '</NrVatUE>';
        } elseif ($buyerTaxIdOther !== '') {
            // Inny identyfikator: opcjonalny KodKraju + NrID
            if ($buyerTaxIdOtherCountry !== '') {
                $xml[] = '      <KodKraju>' . $this->esc($buyerTaxIdOtherCountry) . '</KodKraju>';
            }
            $xml[] = '      <NrID>' . $this->esc($buyerTaxIdOther) . '</NrID>';
        } elseif ($buyerNipParsed['type'] === 'NIP') {
            $xml[] = '      <NIP>' . $this->esc($buyerNipParsed['value']) . '</NIP>';
        } else {
            // XSD wymaga jednego identyfikatora; gdy brak poprawnego → BrakID
            $xml[] = '      <BrakID>1</BrakID>';
        }
        if ($buyerName !== '') {
            $xml[] = '      <Nazwa>' . $this->esc($buyerName) . '</Nazwa>';
        }
        $xml[] = '    </DaneIdentyfikacyjne>';
        $xml[] = '    <Adres>';
        $xml[] = '      <KodKraju>' . $this->esc($countryCode) . '</KodKraju>';
        $buyerStreet = trim((string)($buyer->street ?? ''));
        if ($buyerStreet !== '') {
            $xml[] = '      <AdresL1>' . $this->esc($buyerStreet) . '</AdresL1>';
        } else {
            // AdresL1 jest wymagane w XSD; fallback: miasto lub nazwa kontrahenta
            $adresL1Fallback = trim((string)($buyer->city ?? $buyerName));
            $xml[] = '      <AdresL1>' . $this->esc($adresL1Fallback !== '' ? $adresL1Fallback : '-') . '</AdresL1>';
        }
        $buyerL2 = trim(((string)($buyer->zip ?? '')) . ' ' . ((string)($buyer->city ?? '')));
        if ($buyerL2 !== '') {
            $xml[] = '      <AdresL2>' . $this->esc($buyerL2) . '</AdresL2>';
        }
        // GLN nabywcy (opcjonalne)
        $buyerGln = trim((string)($buyer->gln ?? ''));
        if ($buyerGln !== '') {
            $xml[] = '      <GLN>' . $this->esc($buyerGln) . '</GLN>';
        }
        $xml[] = '    </Adres>';

        // AdresKoresp — adres korespondencyjny nabywcy (opcjonalny, XSD: TAdres)
        $buyerKorespL1 = trim((string)($buyer->koresp_address_l1 ?? ''));
        $buyerKorespL2 = trim((string)($buyer->koresp_address_l2 ?? ''));
        if ($buyerKorespL1 !== '' || $buyerKorespL2 !== '') {
            $buyerKorespCC = strtoupper(trim((string)($buyer->koresp_country_code ?? 'PL')));
            $xml[] = '    <AdresKoresp>';
            $xml[] = '      <KodKraju>' . $this->esc($buyerKorespCC) . '</KodKraju>';
            if ($buyerKorespL1 !== '') {
                $xml[] = '      <AdresL1>' . $this->esc($buyerKorespL1) . '</AdresL1>';
            }
            if ($buyerKorespL2 !== '') {
                $xml[] = '      <AdresL2>' . $this->esc($buyerKorespL2) . '</AdresL2>';
            }
            $buyerKorespGln = trim((string)($buyer->koresp_gln ?? ''));
            if ($buyerKorespGln !== '') {
                $xml[] = '      <GLN>' . $this->esc($buyerKorespGln) . '</GLN>';
            }
            $xml[] = '    </AdresKoresp>';
        }

        // Kolejność wg XSD: NrKlienta? IDNabywcy? JST GV
        $nrKlienta = trim((string)($buyer->nr_klienta ?? ''));
        if ($nrKlienta !== '') {
            $xml[] = '    <NrKlienta>' . $this->esc($nrKlienta) . '</NrKlienta>';
        }
        // JST – jednostka samorządu terytorialnego (1 – JST, 2 – nie JST)
        $jstVal = ((int)($inv->buyer_is_jst ?? 0) === 1) ? '1' : '2';
        // GV – grupa VAT (1 – tak, 2 – nie)
        $gvVal  = ((int)($inv->buyer_in_vat_group ?? 0) === 1) ? '1' : '2';
        $xml[] = '    <JST>' . $jstVal . '</JST>';
        $xml[] = '    <GV>' . $gvVal . '</GV>';

        $xml[] = '  </Podmiot2>';

        return $xml;
    }

    /**
     * Zapisuje relacyjne tabele FA(3) LOW: charges, factor_banks, authorized_entities, order_lines.
     * Strategia: delete-all + insert (replace-all, jak invoice_contents).
     */
    private function saveInvoiceRelationalFa3(string $invoiceId, array $data): void
    {
        // ── InvoiceCharges ──
        $ChargesTable = $this->fetchTable('InvoiceCharges');
        $ChargesTable->deleteAll(['invoice_id' => $invoiceId]);
        $chargesInput = (array)($data['charges'] ?? []);
        foreach ($chargesInput as $ch) {
            if (empty($ch['kwota']) && empty($ch['powod'])) continue;
            $ent = $ChargesTable->newEmptyEntity();
            $ent = $ChargesTable->patchEntity($ent, [
                'invoice_id' => $invoiceId,
                'type'  => in_array($ch['type'] ?? '', ['obciazenie', 'odliczenie'], true) ? $ch['type'] : 'obciazenie',
                'kwota' => (string)($ch['kwota'] ?? '0'),
                'powod' => (string)($ch['powod'] ?? ''),
            ]);
            $ChargesTable->save($ent);
        }

        // ── InvoiceFactorBanks ──
        $FbTable = $this->fetchTable('InvoiceFactorBanks');
        $FbTable->deleteAll(['invoice_id' => $invoiceId]);
        $fbInput = (array)($data['factor_banks'] ?? []);
        foreach ($fbInput as $fb) {
            if (empty(trim((string)($fb['nr_rb'] ?? '')))) continue;
            $ent = $FbTable->newEmptyEntity();
            $ent = $FbTable->patchEntity($ent, [
                'invoice_id' => $invoiceId,
                'nr_rb'              => (string)($fb['nr_rb'] ?? ''),
                'swift'              => (string)($fb['swift'] ?? ''),
                'nazwa_banku'        => (string)($fb['nazwa_banku'] ?? ''),
                'opis_rachunku'      => (string)($fb['opis_rachunku'] ?? ''),
                'rachunek_wlasny_banku' => !empty($fb['rachunek_wlasny_banku']) ? 1 : 0,
            ]);
            $FbTable->save($ent);
        }

        // ── InvoiceAuthorizedEntities ──
        $AeTable = $this->fetchTable('InvoiceAuthorizedEntities');
        $AeTable->deleteAll(['invoice_id' => $invoiceId]);
        $aeInput = (array)($data['auth_entities'] ?? []);
        foreach ($aeInput as $ae) {
            if (empty($ae['rola']) && empty(trim((string)($ae['name'] ?? '')))) continue;
            $ent = $AeTable->newEmptyEntity();
            $ent = $AeTable->patchEntity($ent, [
                'invoice_id'   => $invoiceId,
                'rola'         => !empty($ae['rola']) ? (int)$ae['rola'] : null,
                'name'         => (string)($ae['name'] ?? ''),
                'nip'          => (string)($ae['nip'] ?? ''),
                'nr_eori'      => (string)($ae['nr_eori'] ?? ''),
                'country_code' => (string)($ae['country_code'] ?? ''),
                'address_l1'   => (string)($ae['address_l1'] ?? ''),
                'address_l2'   => (string)($ae['address_l2'] ?? ''),
                'email'        => (string)($ae['email'] ?? ''),
                'phone'        => (string)($ae['phone'] ?? ''),
            ]);
            $AeTable->save($ent);
        }

        // ── InvoiceOrderLines ──
        $OlTable = $this->fetchTable('InvoiceOrderLines');
        $OlTable->deleteAll(['invoice_id' => $invoiceId]);
        $olInput = (array)($data['order_lines'] ?? []);
        foreach ($olInput as $ol) {
            if (empty($ol['nr_wiersza']) && empty(trim((string)($ol['name'] ?? '')))) continue;
            $ent = $OlTable->newEmptyEntity();
            $ent = $OlTable->patchEntity($ent, [
                'invoice_id' => $invoiceId,
                'nr_wiersza' => !empty($ol['nr_wiersza']) ? (int)$ol['nr_wiersza'] : 1,
                'name'       => (string)($ol['name'] ?? ''),
                'unit'       => (string)($ol['unit'] ?? ''),
                'quantity'   => !empty($ol['quantity']) ? (float)$ol['quantity'] : null,
                'price'      => !empty($ol['price']) ? (float)$ol['price'] : null,
                'netto'      => !empty($ol['netto']) ? (float)$ol['netto'] : null,
                'vat_rate'   => !empty($ol['vat_rate']) ? (float)$ol['vat_rate'] : null,
            ]);
            $OlTable->save($ent);
        }

        // ── InvoiceAdditionalDescriptions ──
        $AdTable = $this->fetchTable('InvoiceAdditionalDescriptions');
        $AdTable->deleteAll(['invoice_id' => $invoiceId]);
        $adInput = (array)($data['add_desc'] ?? []);
        foreach ($adInput as $ad) {
            $klucz   = trim((string)($ad['klucz']   ?? ''));
            $wartosc = trim((string)($ad['wartosc'] ?? ''));
            if ($klucz === '' || $wartosc === '') continue;
            $ent = $AdTable->newEmptyEntity();
            $ent = $AdTable->patchEntity($ent, [
                'invoice_id' => $invoiceId,
                'nr_wiersza' => !empty($ad['nr_wiersza']) ? (int)$ad['nr_wiersza'] : null,
                'klucz'      => $klucz,
                'wartosc'    => $wartosc,
            ]);
            $AdTable->save($ent);
        }
    }

/**
 * Zwraca fakturę PIERWOTNĄ (korzeń łańcucha korekt) dla danego rodzica.
 * Idzie w górę po parent_id dopóki natrafia na korektę (type='correction').
 * Pierwsza faktura niebędąca korektą = pierwotna (do DaneFaKorygowanej wg broszury „(pierwotnej)").
 * Gdy rodzic sam nie jest korektą (1. korekta) — zwraca rodzica.
 */
private function resolveRootOriginalInvoice(Invoice $parent, \App\Model\Table\InvoicesTable $Invoices): Invoice
{
    $current = $parent;
    $seen = [(string)$current->id => true];
    for ($i = 0; $i < 50; $i++) {
        if ((string)($current->type ?? '') !== 'correction') {
            break; // to już faktura pierwotna
        }
        if (empty($current->parent_id)) {
            break;
        }
        $pid = (string)$current->parent_id;
        if (isset($seen[$pid])) {
            break;
        }
        $next = $Invoices->find()
            ->select(['id', 'type', 'parent_id', 'fullnumber', 'date', 'ksef_number'])
            ->where(['id' => $pid])
            ->first();
        if (!$next) {
            break;
        }
        $seen[$pid] = true;
        $current = $next;
    }
    return $current;
}

private function enrichInvoiceFromParent(Invoice $inv): Invoice
{
    if (empty($inv->parent_id)) {
        return $inv;
    }

    /** @var \App\Model\Table\InvoicesTable $Invoices */
    $Invoices = $this->fetchTable('Invoices');

    $parent = $Invoices->get($inv->parent_id, [
        'contain' => [
            'InvoiceCompanyDetails',
            'InvoiceContractors',
            'InvoiceContents' => ['Vats'],
        ],
    ]);

    $rodzaj = $this->resolveRodzajFaktury($inv);

    // 🔹 Korekty – KOR / KOR_ZAL / KOR_ROZ
    if (in_array($rodzaj, ['KOR', 'KOR_ZAL', 'KOR_ROZ'], true)) {
        // ODWOŁANIE (DaneFaKorygowanej) → ZAWSZE faktura PIERWOTNA (korzeń łańcucha korekt),
        // zgodnie z broszurą MF: NrFaKorygowanej/NrKSeFFaKorygowanej/DataWyst „(pierwotnej)".
        // Nawet przy 2., 3. korekcie wskazujemy fakturę pierwotną, nie poprzednią korektę.
        $root = $this->resolveRootOriginalInvoice($parent, $Invoices);

        // data wystawienia faktury pierwotnej
        if (!isset($inv->original_issue_date) && isset($root->date)) {
            // dynamiczna właściwość na encji – nie musi być w DB
            $inv->original_issue_date = $root->date;
        }
        // WARTOŚCI „stanu przed korektą" → bezpośredni poprzednik ($parent = ostatnia korekta,
        // lub pierwotna przy 1. korekcie). To ma odzwierciedlać stan tuż przed tą korektą.
        $inv->original_items = $parent->invoice_contents ?? [];
        // numer faktury pierwotnej
        if (!isset($inv->original_number)) {
            $inv->original_number = $root->fullnumber ?? $root->id;
        }

        // numer KSeF faktury pierwotnej (jeśli był wysłany)
        if (!isset($inv->original_ksef_number) && !empty($root->ksef_number)) {
            $inv->original_ksef_number = $root->ksef_number;
        }

        // P_15ZK – kwota przed korektą (brutto z faktury pierwotnej)
        if (!isset($inv->p_15zk) && isset($parent->total)) {
            $inv->p_15zk = (float)$parent->total;
        }

        // KursWalutyZK – kurs z faktury pierwotnej (jeśli była w walucie)
        if (!isset($inv->currency_rate_before_corr) && !empty($parent->currency_exchange)) {
            $inv->currency_rate_before_corr = (float)$parent->currency_exchange;
        }
    }

    // 🔹 Faktura rozliczeniowa (ROZ) po zaliczce – też z parent_id
    if ($rodzaj === 'ROZ') {
        // kwota zaliczek przed rozliczeniem – używamy total z zaliczkowej
        if (!isset($inv->p_15zk) && isset($parent->total)) {
            $inv->p_15zk = (float)$parent->total;
        }
    }

    return $inv;
}



private function buildFaXml(
    Invoice $inv,
    array $items,
    string $currency,
    string $issueDate,
    ?string $soldDate,
    string $placeIssued,
    string $number,
    ?float $rozP15Override = null
): array {
    $xml = [];

    // 🔹 Ustal rodzaj faktury raz – użyjemy dalej i do sekcji korekty, i do RodzajFaktury
    $rodzaj = $this->resolveRodzajFaktury($inv);
    // debug($rodzaj);
    $xml[] = '  <Fa>';
    // KodWaluty – kod waluty (ISO 4217)
    $xml[] = '    <KodWaluty>' . $this->esc($currency) . '</KodWaluty>';
    // P_1 – data wystawienia faktury
    $xml[] = '    <P_1>' . $this->esc($issueDate) . '</P_1>';
    // P_1M – miejsce wystawienia (opcjonalne)
    if ($placeIssued !== '') {
        $xml[] = '    <P_1M>' . $this->esc($placeIssued) . '</P_1M>';
    }
    // P_2 – kolejny numer faktury
    $xml[] = '    <P_2>' . $this->esc($number) . '</P_2>';

    // WZ – numer dokumentu WZ (maks. 1000 powtórzeń; tu zakładamy pojedyncze pole w Invoice)
    if (!empty($inv->wz_number)) {
        $xml[] = '    <WZ>' . $this->esc((string)$inv->wz_number) . '</WZ>';
    }

    // P_6 – data dostawy/usługi lub data otrzymania zapłaty (ZAL: advance_received_date)
    // Dla ZAL/KOR_ZAL P_6 = data otrzymania zaliczki (art. 106b ust. 1 pkt 4 ustawy)
    if (in_array($rodzaj, ['ZAL', 'KOR_ZAL'], true)) {
        $advRecDate = $inv->advance_received_date;
        if ($advRecDate instanceof \DateTimeInterface) {
            $p6 = $advRecDate->format('Y-m-d');
        } elseif (!empty($advRecDate) && is_object($advRecDate) && method_exists($advRecDate, 'format')) {
            // FrozenDate (Chronos 2.x) nie implementuje \DateTimeInterface bezpośrednio,
            // ale ma metodę format() — użyj jej zamiast (string) który zwraca locale-zależny format
            $p6 = $advRecDate->format('Y-m-d');
        } elseif (!empty($advRecDate)) {
            $str = (string)$advRecDate;
            // akceptuj tylko Y-m-d; każdy inny format (np. DD.MM.YYYY z locale) → fallback
            $p6 = preg_match('/^\d{4}-\d{2}-\d{2}/', $str) ? substr($str, 0, 10) : ($soldDate ?? $issueDate);
        } else {
            $p6 = $soldDate ?? $issueDate;
        }
        $xml[] = '    <P_6>' . $this->esc($p6) . '</P_6>';
    } elseif ($soldDate !== null && $soldDate !== $issueDate) {
        $xml[] = '    <P_6>' . $this->esc($soldDate) . '</P_6>';
    }

    // OkresFa – okres rozliczeniowy (np. usługi ciągłe, media) – P_6_Od / P_6_Do
    if (!empty($inv->period_from) && !empty($inv->period_to)) {
        $from = $inv->period_from instanceof \DateTimeInterface
            ? $inv->period_from->format('Y-m-d')
            : (string)$inv->period_from;
        $to   = $inv->period_to instanceof \DateTimeInterface
            ? $inv->period_to->format('Y-m-d')
            : (string)$inv->period_to;

        $xml[] = '    <OkresFa>';
        // P_6_Od – data początkowa okresu
        $xml[] = '      <P_6_Od>' . $this->esc($from) . '</P_6_Od>';
        // P_6_Do – data końcowa okresu
        $xml[] = '      <P_6_Do>' . $this->esc($to) . '</P_6_Do>';
        $xml[] = '    </OkresFa>';
    }

    // VAT summary: P_13/P_14 per stawkę.
    // Dla ROZ: proporcjonalny podział P_15 (kwota pozostała) across stawek proformy.
    // Dla ZAL/zwykłych: suma z pozycji.
[$vatSummaryXml, $sumGross] = $this->buildVatSummaryXml($inv, $items, $rozP15Override);
$xml = array_merge($xml, $vatSummaryXml);

// P_15 — dla ROZ: kwota pozostała do zapłaty (oryginalne pozycje faktury), nie suma proformy
$p15Value = ($rozP15Override !== null) ? $rozP15Override : $sumGross;
$xml[] = '    <P_15>' . $this->fmtAmount($p15Value) . '</P_15>';

// KursWalutyZ – jak miałeś
if (!empty($inv->currency_exchange) && $currency !== 'PLN') {
    $xml[] = '    <KursWalutyZ>' . $this->fmtAmount((float)$inv->currency_exchange, 4) . '</KursWalutyZ>';
}

// ✅ najpierw Adnotacje
$xml = array_merge($xml, $this->buildAnnotationsXml($inv, $items));

// ✅ potem RodzajFaktury
$xml[] = '    <RodzajFaktury>' . $rodzaj . '</RodzajFaktury>';

// DataOtrzym — nie istnieje w schemacie FA(3); data otrzymania zaliczki idzie jako P_6 (powyżej)

// ✅ dopiero teraz sekcja korekty (TypKorekty, DaneFaKorygowanej, itd.)
if (in_array($rodzaj, ['KOR', 'KOR_ZAL', 'KOR_ROZ'], true)) {
    $xml = array_merge($xml, $this->buildCorrectionHeaderXml($inv, $rodzaj));
}

    // FP – faktura do paragonu (art. 109 ust. 3d ustawy)
    // XSD: <FP type="TWybor1" minOccurs="0"/> — wartość "1" gdy faktura wystawiona do paragonu fiskalnego.
    // Pozycja w sekwencji Fa: po sekcji korekty, przed <TP>.
    if (!empty($inv->is_receipt_invoice)) {
        $xml[] = '    <FP>1</FP>';
    }

    // TP – powiązania między nabywcą a sprzedawcą
    // XSD sequence: ...korekta → (ZaliczkaCzesciowa) → (FP) → TP → DodatkowyOpis → FakturaZaliczkowa → ZwrotAkcyzy → FaWiersz
    $annTp = [];
    if (!empty($inv->annotations)) {
        $dec = is_array($inv->annotations) ? $inv->annotations : json_decode((string)$inv->annotations, true);
        if (is_array($dec)) $annTp = $dec;
    }
    if (isset($annTp['tp']) && (string)$annTp['tp'] === '1') {
        $xml[] = '    <TP>1</TP>';
    }

    // DodatkowyOpis — opcjonalne pary klucz-wartość
    $xml = array_merge($xml, $this->buildDodatkowyOpisXml($inv));

    // FakturaZaliczkowa — rozliczenie zaliczek w fakturze końcowej (ROZ)
    $xml = array_merge($xml, $this->buildFakturaZaliczkowaXml($inv, $rodzaj));

    // ZwrotAkcyzy — informacja dodatkowa dla rolników (annotations[excise_return])
    if (!empty($annTp['excise_return'])) {
        $xml[] = '    <ZwrotAkcyzy>1</ZwrotAkcyzy>';
    }

    // Wiersze
    $xml   = array_merge($xml, $this->buildLinesXml($inv, $items));

    // Rozliczenie — Obciążenia / Odliczenia (opcjonalne, FA(3))
    $xml   = array_merge($xml, $this->buildRozliczenieXml($inv));

    // Płatność
    $xml   = array_merge($xml, $this->buildPaymentXml($inv, $issueDate, $soldDate));

    // WarunkiTransakcji — umowy, zamówienia, transport, Incoterms (opcjonalne, FA(3))
    $xml   = array_merge($xml, $this->buildWarunkiTransakcjiXml($inv));

    // Zamówienie — linie zamówienia dla faktur zaliczkowych (opcjonalne, FA(3))
    $xml   = array_merge($xml, $this->buildZamowienieXml($inv));

    $xml[] = '  </Fa>';

    return $xml;
}
/**
 * Sekcja korekty: PrzyczynaKorekty, TypKorekty, DaneFaKorygowanej, P_15ZK, KursWalutyZK.
 *
 * WYMAGANE dla RodzajFaktury: KOR / KOR_ZAL / KOR_ROZ.
 *
 * Zakładane pola w encji Invoice:
 *  - correction_reason          (string|null)     – opis przyczyny korekty
 *  - correction_type            (string|int|null) – "1", "2", "3" wg dokumentacji (typ skutku korekty)
 *  - original_issue_date        (\DateTimeInterface|null) – data wystawienia faktury pierwotnej
 *  - original_number            (string|null)     – numer faktury pierwotnej (NrFaKorygowanej, błędny gdy korygujemy numer)
 *  - original_ksef_number       (string|null)     – numer KSeF faktury pierwotnej (jeśli była w KSeF)
 *  - corrected_invoice_number   (string|null)     – NrFaKorygowany – poprawny numer faktury (tylko gdy przyczyną korekty jest błędny numer)
 *  - p_15zk                     (float|null)      – P_15ZK – kwota przed korektą (zaliczka / pozostała do zapłaty)
 *  - currency_rate_before_corr  (float|null)      – KursWalutyZK – kurs przed korektą (dla zaliczek w walucie)
 */
private function buildCorrectionHeaderXml(Invoice $inv, string $rodzajFaktury): array
{
    $xml = [];
    // debug($inv);
    // 1) PrzyczynaKorekty – opis (fakultatywne, ale w praktyce dobrze mieć zawsze)
    $reason = trim((string)($inv->correction_reason ?? ''));
    if ($reason !== '') {
        $xml[] = '    <PrzyczynaKorekty>' . $this->esc($reason) . '</PrzyczynaKorekty>';
    }

    // 2) TypKorekty – 1/2/3 (skutek w ewidencji VAT); XSD: enumeration 1|2|3
    $typKorektyRaw = (int)($inv->correction_type ?? 0);
    if ($typKorektyRaw >= 1 && $typKorektyRaw <= 3) {
        $xml[] = '    <TypKorekty>' . $typKorektyRaw . '</TypKorekty>';
    } elseif (!empty($inv->correction_type)) {
        // wartość spoza zakresu — emit domyślnego 1 (korekta in plus, bieżący okres)
        $xml[] = '    <TypKorekty>1</TypKorekty>';
    }

    // 3) DaneFaKorygowanej – powiązanie z fakturą pierwotną (w KSeF lub poza)
    // XSD: element WYMAGANY dla RodzajFaktury KOR/KOR_ZAL/KOR_ROZ — brak danych = błąd
    $origDate   = $inv->original_issue_date ?? null;
    $origNumber = trim((string)($inv->original_number ?? ''));
    $origKsef   = trim((string)($inv->original_ksef_number ?? ''));

    if ($origNumber === '' || $origDate === null) {
        throw new \RuntimeException(
            'Faktura korygująca wymaga numeru i daty faktury pierwotnej (DaneFaKorygowanej). ' .
            'Uzupełnij pola „Numer faktury korygowanej" i „Data wystawienia faktury korygowanej".'
        );
    }

    if ($origNumber !== '' && $origDate !== null) {
        // obsłuż zarówno DateTime, jak i string; XSD TDataT wymaga formatu YYYY-MM-DD
        if ($origDate instanceof \DateTimeInterface) {
            $dateStr = $origDate->format('Y-m-d');
        } else {
            $raw = trim((string)$origDate);
            // konwersja z formatu DD.MM.YYYY → YYYY-MM-DD
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $m)) {
                $dateStr = $m[3] . '-' . $m[2] . '-' . $m[1];
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
                $dateStr = $raw; // już ISO
            } else {
                try {
                    $dateStr = (new \DateTime($raw))->format('Y-m-d');
                } catch (\Throwable) {
                    $dateStr = $raw;
                }
            }
        }

        $xml[] = '    <DaneFaKorygowanej>';
        $xml[] = '      <DataWystFaKorygowanej>' . $dateStr . '</DataWystFaKorygowanej>';
        $xml[] = '      <NrFaKorygowanej>' . $this->esc($origNumber) . '</NrFaKorygowanej>';

        if ($origKsef !== '') {
            $xml[] = '      <NrKSeF>1</NrKSeF>';
            $xml[] = '      <NrKSeFFaKorygowanej>' . $this->esc($origKsef) . '</NrKSeFFaKorygowanej>';
        } else {
            $xml[] = '      <NrKSeFN>1</NrKSeFN>';
        }

        $xml[] = '    </DaneFaKorygowanej>';
    }

    // 4) OkresFaKorygowanej – używasz tylko przy zbiorczych rabatach/obniżkach (art. 106j ust. 3)
    // Jeśli będziesz to robił – możesz dodać tu:
    //
    // if ($inv->corr_period_from && $inv->corr_period_to) {
    //     $from = $inv->corr_period_from instanceof \DateTimeInterface
    //         ? $inv->corr_period_from->format('Y-m-d')
    //         : (string)$inv->corr_period_from;
    //     $to   = $inv->corr_period_to instanceof \DateTimeInterface
    //         ? $inv->corr_period_to->format('Y-m-d')
    //         : (string)$inv->corr_period_to;
    //     $xml[] = '    <OkresFaKorygowanej>';
    //     $xml[] = '      <P_6_Od>' . $this->esc($from) . '</P_6_Od>';
    //     $xml[] = '      <P_6_Do>' . $this->esc($to)   . '</P_6_Do>';
    //     $xml[] = '    </OkresFaKorygowanej>';
    // }

    // 4.5) NrFaKorygowany – poprawny numer faktury, gdy przyczyną korekty jest błędny numer.
    // Per XSD FA(3) sequence: po DaneFaKorygowanej i OkresFaKorygowanej, przed Podmiot1K.
    // Per broszura linie 2728-2737: pole opcjonalne; emitujemy TYLKO gdy wypełnione.
    if (!empty($inv->corrected_invoice_number)) {
        $xml[] = '    <NrFaKorygowany>' . $this->esc((string)$inv->corrected_invoice_number) . '</NrFaKorygowany>';
    }

    // 5) P_15ZK – kwota przed korektą dla zaliczek / rozliczeniowych
    //    - przy korektach faktur zaliczkowych – kwota zapłaty przed korektą
    //    - przy korektach faktur "ROZ" – kwota pozostała do zapłaty przed korektą
    if ($inv->p_15zk !== null) {
        $xml[] = '    <P_15ZK>' . $this->fmtAmount((float)$inv->p_15zk) . '</P_15ZK>';
    }

    // 6) KursWalutyZK – kurs z faktury zaliczkowej przed korektą (dla waluty ≠ PLN)
    if (!empty($inv->currency_rate_before_corr) && strtoupper((string)$inv->currency ?? 'PLN') !== 'PLN') {
        $xml[] = '    <KursWalutyZK>' . $this->fmtAmount((float)$inv->currency_rate_before_corr, 4) . '</KursWalutyZK>';
    }

    return $xml;
}


    // ======================== DODATKOWY OPIS ========================

    /**
     * Emituje elementy <DodatkowyOpis> wewnątrz <Fa>.
     * Każdy wpis to para Klucz + Wartość (opcjonalnie NrWiersza).
     */
    private function buildDodatkowyOpisXml(Invoice $inv): array
    {
        $xml = [];

        // Automatyczny wpis z kolumny `invoices.description` (opis faktury)
        $descField = trim((string)($inv->description ?? ''));
        if ($descField !== '') {
            $xml[] = '    <DodatkowyOpis>';
            $xml[] = '      <Klucz>' . $this->esc('Opis faktury') . '</Klucz>';
            $xml[] = '      <Wartosc>' . $this->esc($descField) . '</Wartosc>';
            $xml[] = '    </DodatkowyOpis>';
        }

        // Faktura do paragonu (FP) — dodaj dane paragonu jeśli istnieją.
        // FA(3) nie ma dedykowanych elementów NrParagonu/DataParagonu,
        // dlatego per broszura (Przykład 17) używamy DodatkowyOpis (klucz-wartość).
        if (!empty($inv->is_receipt_invoice)) {
            $receiptNumber = trim((string)($inv->receipt_number ?? ''));
            if ($receiptNumber !== '') {
                $xml[] = '    <DodatkowyOpis>';
                $xml[] = '      <Klucz>' . $this->esc('Numer paragonu') . '</Klucz>';
                $xml[] = '      <Wartosc>' . $this->esc($receiptNumber) . '</Wartosc>';
                $xml[] = '    </DodatkowyOpis>';
            }
            $receiptDateRaw = $inv->receipt_date ?? null;
            $receiptDateStr = '';
            if ($receiptDateRaw !== null && $receiptDateRaw !== '') {
                if (is_object($receiptDateRaw) && method_exists($receiptDateRaw, 'format')) {
                    $receiptDateStr = $receiptDateRaw->format('Y-m-d');
                } else {
                    $s = (string)$receiptDateRaw;
                    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $s, $m)) {
                        $receiptDateStr = "{$m[3]}-{$m[2]}-{$m[1]}";
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
                        $receiptDateStr = substr($s, 0, 10);
                    }
                }
            }
            if ($receiptDateStr !== '') {
                $xml[] = '    <DodatkowyOpis>';
                $xml[] = '      <Klucz>' . $this->esc('Data paragonu') . '</Klucz>';
                $xml[] = '      <Wartosc>' . $this->esc($receiptDateStr) . '</Wartosc>';
                $xml[] = '    </DodatkowyOpis>';
            }
        }

        $descriptions = (array)($inv->invoice_additional_descriptions ?? []);
        foreach ($descriptions as $desc) {
            $klucz  = trim((string)($desc->klucz ?? $desc['klucz'] ?? ''));
            $wartosc = trim((string)($desc->wartosc ?? $desc['wartosc'] ?? ''));
            if ($klucz === '' || $wartosc === '') {
                continue;
            }
            $xml[] = '    <DodatkowyOpis>';
            $nrWiersza = $desc->nr_wiersza ?? $desc['nr_wiersza'] ?? null;
            if ($nrWiersza !== null && (int)$nrWiersza > 0) {
                $xml[] = '      <NrWiersza>' . (int)$nrWiersza . '</NrWiersza>';
            }
            $xml[] = '      <Klucz>' . $this->esc($klucz) . '</Klucz>';
            $xml[] = '      <Wartosc>' . $this->esc($wartosc) . '</Wartosc>';
            $xml[] = '    </DodatkowyOpis>';
        }
        return $xml;
    }


    // ======================== FAKTURA ZALICZKOWA ========================

    /**
     * Emituje elementy <FakturaZaliczkowa> dla faktur końcowych (ROZ).
     * Wylicza powiązane faktury zaliczkowe (ZAL) przez wspólne parent_id.
     */
    private function buildFakturaZaliczkowaXml(Invoice $inv, string $rodzajFaktury): array
    {
        $xml = [];
        if ($rodzajFaktury !== 'ROZ') {
            return $xml;
        }

        $companyId    = $inv->company_id;
        $InvTable     = $this->fetchTable('Invoices');
        $collected    = [];
        $seenIds      = [];

        $addResults = function (array $rows) use (&$collected, &$seenIds): void {
            foreach ($rows as $row) {
                $rid = (string)$row->id;
                if (!isset($seenIds[$rid])) {
                    $seenIds[$rid] = true;
                    $collected[]   = $row;
                }
            }
        };

        // Typy DB odpowiadające ZAL (bieżący + legacy)
        $advanceTypes = ['advance', 'zaliczkowa'];

        // Strategia 1: Faktury zaliczkowe powiązane z tą samą proformą (parent_id = proforma.id)
        if (!empty($inv->parent_id)) {
            $addResults($InvTable->find()
                ->where([
                    'parent_id'  => $inv->parent_id,
                    'id !='      => $inv->id,
                    'type IN'    => $advanceTypes,
                    'company_id' => $companyId,
                ])
                ->orderAsc('date')
                ->all()->toArray()
            );
        }

        // Strategia 2: Faktury zaliczkowe, które mają ROZ jako bezpośredniego rodzica
        // (rzadszy schemat danych, ale możliwy jeśli invoice.parent_id = null lub inny)
        $addResults($InvTable->find()
            ->where([
                'parent_id'  => $inv->id,
                'type IN'    => $advanceTypes,
                'company_id' => $companyId,
            ])
            ->orderAsc('date')
            ->all()->toArray()
        );

        // Sortuj po dacie (łączny wynik obu strategii)
        usort($collected, fn($a, $b) => strcmp((string)($a->date ?? ''), (string)($b->date ?? '')));

        foreach ($collected as $adv) {
            $advKsef   = trim((string)($adv->ksef_number ?? ''));
            $advNumber = trim((string)($adv->fullnumber  ?? ''));

            // Dla szkiców bez numeru: użyj ID jako zastępczego numeru (jak przy P_2 dla draftu)
            if ($advNumber === '' && $advKsef === '') {
                $isDraftAdv = ((string)($adv->workflow_status ?? '')) === 'draft';
                if ($isDraftAdv) {
                    $advNumber = (string)$adv->id . ' - robocza';
                }
            }

            // XSD choice: albo NrKSeFFaZaliczkowej (KSeF), albo NrKSeFZN + NrFaZaliczkowej (poza KSeF)
            if ($advKsef !== '') {
                // Faktura zaliczkowa wystawiona w KSeF — użyj numeru KSeF
                $xml[] = '    <FakturaZaliczkowa>';
                $xml[] = '      <NrKSeFFaZaliczkowej>' . $this->esc($advKsef) . '</NrKSeFFaZaliczkowej>';
                $xml[] = '    </FakturaZaliczkowa>';
            } elseif ($advNumber !== '') {
                // Faktura zaliczkowa wystawiona poza KSeF (lub szkic)
                $xml[] = '    <FakturaZaliczkowa>';
                $xml[] = '      <NrKSeFZN>1</NrKSeFZN>';
                $xml[] = '      <NrFaZaliczkowej>' . $this->esc($advNumber) . '</NrFaZaliczkowej>';
                $xml[] = '    </FakturaZaliczkowa>';
            }
            // Jeśli nie draft i brak numeru — pomijamy (nie powinno się zdarzyć dla wydanych faktur)
        }

        return $xml;
    }


    // ======================== VAT SUMMARY (P_13_x / P_14_x) ========================

    /** Mapuje stawkę VAT (float) + nazwę na klucz grupy zgodny z TStawkaPodatku FA(3). */
    private function rateToGrpKey(float $rate, string $vatName): string
    {
        if ($rate >= 22.5)  return '23';
        if ($rate >= 7.5)   return '8';
        if ($rate >= 4.5)   return '5';
        if ($rate > 0)      return (string)(int)$rate;
        if (str_starts_with($vatName, 'zw'))                                    return 'zw';
        if (str_starts_with($vatName, 'oo'))                                    return 'oo';
        if (str_contains($vatName, 'wdt'))                                      return '0wdt';
        if (str_contains($vatName, 'exp') || str_contains($vatName, 'eks'))     return '0ex';
        // "nie podl. spoza UE" → np I (P_13_8: poza terytorium kraju, nabywca spoza UE)
        if (str_contains($vatName, 'spoza'))                                    return 'np_i';
        // "nie podl. UE" → np II (P_13_9: wewnątrzwspólnotowe usługi, art. 100 ust. 1 pkt 4)
        if (str_contains($vatName, 'ue'))                                       return 'np_ii';
        // "nie podl." bez kwalifikatora → np I (default poza terytorium kraju)
        if (str_contains($vatName, 'nie podl'))                                 return 'np_i';
        if (str_starts_with($vatName, 'np'))                                    return 'np_i';
        return '0kr';
    }

    private function buildVatSummaryXml(Invoice $inv, array $items, ?float $rozP15Override = null): array
{
    $xml    = [];
    $rodzaj = $this->resolveRodzajFaktury($inv);

    $currency          = strtoupper((string)($inv->currency ?? 'PLN'));
    $currencyExchange  = (float)($inv->currency_exchange ?? 1.0);
    $isForeignCurrency = $currency !== 'PLN' && $currencyExchange > 0.0001;

    // ======================
    // 1) ZWYKŁE FAKTURY + ZAL + ROZ
    // ======================
    if (!in_array($rodzaj, ['KOR', 'KOR_ZAL', 'KOR_ROZ'], true)) {
        // Grupy zgodnie z TStawkaPodatku FA(3)
        $grp = [
            '23'    => ['net' => 0.0, 'vat' => 0.0], // P_13_1 / P_14_1
            '8'     => ['net' => 0.0, 'vat' => 0.0], // P_13_2 / P_14_2
            '5'     => ['net' => 0.0, 'vat' => 0.0], // P_13_3 / P_14_3
            'zw'    => ['net' => 0.0, 'vat' => 0.0], // P_13_7 zwolniona
            'np_i'  => ['net' => 0.0, 'vat' => 0.0], // P_13_8 niepodlegające (np I, dostawa poza PL)
            'np_ii' => ['net' => 0.0, 'vat' => 0.0], // P_13_9 niepodlegające (np II, usługi UE art. 100 pkt 4)
            '0kr'   => ['net' => 0.0, 'vat' => 0.0], // P_13_6_1 0% KR (krajowe)
            'oo'    => ['net' => 0.0, 'vat' => 0.0], // P_13_10 odwrotne obciążenie
            '0wdt'  => ['net' => 0.0, 'vat' => 0.0], // P_13_6_2 0% WDT
            '0ex'   => ['net' => 0.0, 'vat' => 0.0], // P_13_6_3 0% eksport
        ];

        $sumGross = 0.0;

        // Procedura marży (art. 119/120): wartość sprzedaży trafia do P_13_11,
        // a NIE do zwykłych grup stawek (m.in. 0% krajowe / P_13_6_1). VAT nie jest wykazywany na fakturze.
        $isMargin = ((string)($inv->type ?? '') === 'margin') || !empty($inv->margin_type);
        $marginSalesGross = 0.0;

        // Dla ROZ: P_13/P_14 = proporcjonalny podział P_15 (kwota pozostała) per stawkę proformy.
        // FaWiersz pokazuje pełne wartości zamówienia, ale P_13/P_14 odzwierciedlają tylko pozostałą część.
        // UWAGA: dopuszczamy override = 0.0 (faktura rozliczeniowa po 100% zaliczek) — wtedy podział
        // daje P_13/P_14 = 0 i P_15 = 0. Bez tego (gdy >0) kod sumowałby pełne pozycje proformy = błąd.
        if ($rodzaj === 'ROZ' && $rozP15Override !== null) {
            // Zsumuj brutto proformy per stawkę
            $proformaBruttoPerGrp = [];
            $proformaBruttoTotal  = 0.0;
            foreach ($items as $it) {
                $rate    = isset($it->vat) ? (float)$it->vat->rate : 0.0;
                $vatName = strtolower(trim((string)($it->vat->name ?? '')));
                $gross   = (float)($it->brutto ?? 0.0);
                $grpKey  = $this->rateToGrpKey($rate, $vatName);
                $proformaBruttoPerGrp[$grpKey] = ($proformaBruttoPerGrp[$grpKey] ?? 0.0) + $gross;
                $proformaBruttoTotal += $gross;
            }

            if ($proformaBruttoTotal > 0.0) {
                $remaining = $rozP15Override;
                $keys = array_keys($proformaBruttoPerGrp);
                foreach ($keys as $idx => $grpKey) {
                    $isLast     = ($idx === count($keys) - 1);
                    $splitGross = $isLast
                        ? round($remaining, 2)
                        : round($rozP15Override * ($proformaBruttoPerGrp[$grpKey] / $proformaBruttoTotal), 2);
                    $remaining -= $splitGross;

                    // Wyznacz rate z klucza (np. '23', '8', '5', lub fallback numeryczny)
                    $splitRate = match(true) {
                        $grpKey === '23'    => 23.0,
                        $grpKey === '8'     => 8.0,
                        $grpKey === '5'     => 5.0,
                        is_numeric($grpKey) => (float)$grpKey,
                        default             => 0.0,
                    };
                    $splitNet = $splitRate > 0 ? round($splitGross / (1 + $splitRate / 100), 2) : round($splitGross, 2);
                    $splitVat = round($splitGross - $splitNet, 2);

                    if (!isset($grp[$grpKey])) {
                        $grp[$grpKey] = ['net' => 0.0, 'vat' => 0.0];
                    }
                    $grp[$grpKey]['net'] += $splitNet;
                    $grp[$grpKey]['vat'] += $splitVat;
                    $sumGross += $splitGross;
                }
            } else {
                $sumGross = $rozP15Override;
            }
        } else {
            // ZAL / zwykłe: sumuj bezpośrednio z pozycji
        foreach ($items as $it) {
            $rate    = isset($it->vat) ? (float)$it->vat->rate : 0.0;
            $vatName = strtolower(trim((string)($it->vat->name ?? '')));
            $net     = (float)($it->netto  ?? 0.0);
            $gross   = (float)($it->brutto ?? 0.0);
            $vat     = (float)($it->vat_amount ?? max(0.0, $gross - $net));

            if ($isMargin) {
                // Marża: wartość sprzedaży → P_13_11; pozycja nie wchodzi do grup stawek
                // (brak P_13_6_1 „0% krajowe", brak P_14_*). VAT od marży nie jest wykazywany.
                $marginSalesGross += $gross;
                $sumGross         += $gross;
                continue;
            }

            $gk = $this->rateToGrpKey($rate, $vatName);
            if (!isset($grp[$gk])) {
                $grp[$gk] = ['net' => 0.0, 'vat' => 0.0];
            }
            $grp[$gk]['net'] += $net;
            $grp[$gk]['vat'] += $vat;
            $sumGross += $gross;
        }
        } // end else (ZAL/zwykłe)

        // P_13_1 / P_14_1 – stawka podstawowa 23/22%
        if ($grp['23']['net'] !== 0.0) {
            $xml[] = '    <P_13_1>' . $this->fmtAmount($grp['23']['net']) . '</P_13_1>';
            $xml[] = '    <P_14_1>' . $this->fmtAmount($grp['23']['vat']) . '</P_14_1>';
            if ($isForeignCurrency) {
                $xml[] = '    <P_14_1W>' . $this->fmtAmount(round($grp['23']['vat'] * $currencyExchange, 2)) . '</P_14_1W>';
            }
        }

        // P_13_2 / P_14_2 – stawka 8/7%
        if ($grp['8']['net'] !== 0.0) {
            $xml[] = '    <P_13_2>' . $this->fmtAmount($grp['8']['net']) . '</P_13_2>';
            $xml[] = '    <P_14_2>' . $this->fmtAmount($grp['8']['vat']) . '</P_14_2>';
            if ($isForeignCurrency) {
                $xml[] = '    <P_14_2W>' . $this->fmtAmount(round($grp['8']['vat'] * $currencyExchange, 2)) . '</P_14_2W>';
            }
        }

        // P_13_3 / P_14_3 – stawka 5%
        if ($grp['5']['net'] !== 0.0) {
            $xml[] = '    <P_13_3>' . $this->fmtAmount($grp['5']['net']) . '</P_13_3>';
            $xml[] = '    <P_14_3>' . $this->fmtAmount($grp['5']['vat']) . '</P_14_3>';
            if ($isForeignCurrency) {
                $xml[] = '    <P_14_3W>' . $this->fmtAmount(round($grp['5']['vat'] * $currencyExchange, 2)) . '</P_14_3W>';
            }
        }

        // P_13_4 / P_14_4 / P_14_4W – ryczałt dla taksówek osobowych
        $this->emitIfNotNull($xml, 'P_13_4', $inv->p_13_4 ?? null);
        $this->emitIfNotNull($xml, 'P_14_4', $inv->p_14_4 ?? null);
        $this->emitIfNotNull($xml, 'P_14_4W', $inv->p_14_4w ?? null);

        // P_13_5 / P_14_5 – procedura szczególna (dział XII rozdz. 6a)
        $this->emitIfNotNull($xml, 'P_13_5', $inv->p_13_5 ?? null);
        $this->emitIfNotNull($xml, 'P_14_5', $inv->p_14_5 ?? null);

        // P_13_6_1 – 0% krajowe (0 KR)
        $p13_6_1 = $grp['0kr']['net']  ?: ($inv->p_13_6_1 ?? null);
        $this->emitIfNotNull($xml, 'P_13_6_1', $p13_6_1 ?: null);
        // P_13_6_2 – 0% WDT
        $p13_6_2 = $grp['0wdt']['net'] ?: ($inv->p_13_6_2 ?? null);
        $this->emitIfNotNull($xml, 'P_13_6_2', $p13_6_2 ?: null);
        // P_13_6_3 – 0% eksport (0 EX)
        $p13_6_3 = $grp['0ex']['net']  ?: ($inv->p_13_6_3 ?? null);
        $this->emitIfNotNull($xml, 'P_13_6_3', $p13_6_3 ?: null);

        // P_13_7 – sprzedaż zwolniona (zw.)
        $p13_7 = $grp['zw']['net'] ?: ($inv->p_13_7 ?? null);
        $this->emitIfNotNull($xml, 'P_13_7', $p13_7 ?: null);

        // P_13_8 – dostawa/usługi poza terytorium kraju (np I), z wył. P_13_5 i P_13_9
        $p13_8 = $grp['np_i']['net'] ?: ($inv->p_13_8 ?? null);
        $this->emitIfNotNull($xml, 'P_13_8', $p13_8 ?: null);
        // P_13_9 – usługi art. 100 ust. 1 pkt 4 (np II, UE)
        $p13_9 = $grp['np_ii']['net'] ?: ($inv->p_13_9 ?? null);
        $this->emitIfNotNull($xml, 'P_13_9', $p13_9 ?: null);
        // P_13_10 – odwrotne obciążenie (oo)
        $p13_10 = $grp['oo']['net'] ?: ($inv->p_13_10 ?? null);
        $this->emitIfNotNull($xml, 'P_13_10', $p13_10 ?: null);
        // P_13_11 – procedura marży (art. 119/120): suma wartości sprzedaży w procedurze marży
        $p13_11 = $isMargin ? ($marginSalesGross ?: null) : ($inv->p_13_11 ?? null);
        $this->emitIfNotNull($xml, 'P_13_11', $p13_11);

        return [$xml, $sumGross];
    }

    // ======================
    // 2) FAKTURY KORYGUJĄCE
    // ======================
    $origItems = (array)($inv->original_items ?? []);

    $grpBefore = [
        '23'    => ['net' => 0.0, 'vat' => 0.0],
        '8'     => ['net' => 0.0, 'vat' => 0.0],
        '5'     => ['net' => 0.0, 'vat' => 0.0],
        'zw'    => ['net' => 0.0, 'vat' => 0.0],
        'np_i'  => ['net' => 0.0, 'vat' => 0.0],
        'np_ii' => ['net' => 0.0, 'vat' => 0.0],
        '0kr'   => ['net' => 0.0, 'vat' => 0.0],
        '0wdt'  => ['net' => 0.0, 'vat' => 0.0],
        '0ex'   => ['net' => 0.0, 'vat' => 0.0],
        'oo'    => ['net' => 0.0, 'vat' => 0.0],
    ];
    $grpAfter = [
        '23'    => ['net' => 0.0, 'vat' => 0.0],
        '8'     => ['net' => 0.0, 'vat' => 0.0],
        '5'     => ['net' => 0.0, 'vat' => 0.0],
        'zw'    => ['net' => 0.0, 'vat' => 0.0],
        'np_i'  => ['net' => 0.0, 'vat' => 0.0],
        'np_ii' => ['net' => 0.0, 'vat' => 0.0],
        '0kr'   => ['net' => 0.0, 'vat' => 0.0],
        '0wdt'  => ['net' => 0.0, 'vat' => 0.0],
        '0ex'   => ['net' => 0.0, 'vat' => 0.0],
        'oo'    => ['net' => 0.0, 'vat' => 0.0],
    ];

    $sumGrossBefore = 0.0;
    $sumGrossAfter  = 0.0;

    // --- przed korektą ---
    foreach ($origItems as $it) {
        $rate     = isset($it->vat) ? (float)$it->vat->rate : 0.0;
        $vatName  = strtolower(trim((string)($it->vat->name ?? '')));
        $net      = (float)($it->netto ?? 0.0);
        $vat      = (float)($it->vat_amount ?? max(0.0, $rate * $net / 100.0));
        $gross    = (float)($it->brutto ?? ($net + $vat));

        if ($rate >= 22.5) {
            $grpBefore['23']['net'] += $net; $grpBefore['23']['vat'] += $vat;
        } elseif ($rate >= 7.5) {
            $grpBefore['8']['net']  += $net; $grpBefore['8']['vat']  += $vat;
        } elseif ($rate >= 4.5) {
            $grpBefore['5']['net']  += $net; $grpBefore['5']['vat']  += $vat;
        } elseif (str_starts_with($vatName, 'zw')) {
            $grpBefore['zw']['net']    += $net;
        } elseif (str_contains($vatName, 'wdt')) {
            $grpBefore['0wdt']['net']  += $net;
        } elseif (str_contains($vatName, 'exp') || str_contains($vatName, 'eks')) {
            $grpBefore['0ex']['net']   += $net;
        } elseif (str_contains($vatName, 'spoza')) {
            $grpBefore['np_i']['net']  += $net;
        } elseif (str_contains($vatName, 'ue')) {
            $grpBefore['np_ii']['net'] += $net;
        } elseif (str_contains($vatName, 'nie podl')) {
            $grpBefore['np_i']['net']  += $net;
        } elseif (str_starts_with($vatName, 'np')) {
            $grpBefore['np_i']['net']  += $net;
        } elseif (str_starts_with($vatName, 'oo')) {
            $grpBefore['oo']['net']    += $net;
        } else {
            $grpBefore['0kr']['net']   += $net;
        }

        $sumGrossBefore += $gross;
    }

    // --- po korekcie ---
    foreach ($items as $it) {
        $rate     = isset($it->vat) ? (float)$it->vat->rate : 0.0;
        $vatName  = strtolower(trim((string)($it->vat->name ?? '')));
        $net      = (float)($it->netto ?? 0.0);
        $vat      = (float)($it->vat_amount ?? max(0.0, $rate * $net / 100.0));
        $gross    = (float)($it->brutto ?? ($net + $vat));

        if ($rate >= 22.5) {
            $grpAfter['23']['net'] += $net; $grpAfter['23']['vat'] += $vat;
        } elseif ($rate >= 7.5) {
            $grpAfter['8']['net']  += $net; $grpAfter['8']['vat']  += $vat;
        } elseif ($rate >= 4.5) {
            $grpAfter['5']['net']  += $net; $grpAfter['5']['vat']  += $vat;
        } elseif (str_starts_with($vatName, 'zw')) {
            $grpAfter['zw']['net']    += $net;
        } elseif (str_contains($vatName, 'wdt')) {
            $grpAfter['0wdt']['net']  += $net;
        } elseif (str_contains($vatName, 'exp') || str_contains($vatName, 'eks')) {
            $grpAfter['0ex']['net']   += $net;
        } elseif (str_contains($vatName, 'spoza')) {
            $grpAfter['np_i']['net']  += $net;
        } elseif (str_contains($vatName, 'ue')) {
            $grpAfter['np_ii']['net'] += $net;
        } elseif (str_contains($vatName, 'nie podl')) {
            $grpAfter['np_i']['net']  += $net;
        } elseif (str_starts_with($vatName, 'np')) {
            $grpAfter['np_i']['net']  += $net;
        } elseif (str_starts_with($vatName, 'oo')) {
            $grpAfter['oo']['net']    += $net;
        } else {
            $grpAfter['0kr']['net']   += $net;
        }

        $sumGrossAfter += $gross;
    }

    // --- różnice (po – przed) ---
    $d23net   = $grpAfter['23']['net']    - $grpBefore['23']['net'];
    $d23vat   = $grpAfter['23']['vat']    - $grpBefore['23']['vat'];
    $d8net    = $grpAfter['8']['net']     - $grpBefore['8']['net'];
    $d8vat    = $grpAfter['8']['vat']     - $grpBefore['8']['vat'];
    $d5net    = $grpAfter['5']['net']     - $grpBefore['5']['net'];
    $d5vat    = $grpAfter['5']['vat']     - $grpBefore['5']['vat'];
    $d0kr     = $grpAfter['0kr']['net']   - $grpBefore['0kr']['net'];
    $d0wdt    = $grpAfter['0wdt']['net']  - $grpBefore['0wdt']['net'];
    $d0ex     = $grpAfter['0ex']['net']   - $grpBefore['0ex']['net'];
    $dzwNet   = $grpAfter['zw']['net']    - $grpBefore['zw']['net'];
    $dnpINet  = $grpAfter['np_i']['net']  - $grpBefore['np_i']['net'];
    $dnpIINet = $grpAfter['np_ii']['net'] - $grpBefore['np_ii']['net'];
    $dooNet   = $grpAfter['oo']['net']    - $grpBefore['oo']['net'];

    if ($d23net !== 0.0) {
        $xml[] = '    <P_13_1>' . $this->fmtAmount($d23net) . '</P_13_1>';
        $xml[] = '    <P_14_1>' . $this->fmtAmount($d23vat) . '</P_14_1>';
        if ($isForeignCurrency) {
            $xml[] = '    <P_14_1W>' . $this->fmtAmount(round($d23vat * $currencyExchange, 2)) . '</P_14_1W>';
        }
    }
    if ($d8net !== 0.0) {
        $xml[] = '    <P_13_2>' . $this->fmtAmount($d8net) . '</P_13_2>';
        $xml[] = '    <P_14_2>' . $this->fmtAmount($d8vat) . '</P_14_2>';
        if ($isForeignCurrency) {
            $xml[] = '    <P_14_2W>' . $this->fmtAmount(round($d8vat * $currencyExchange, 2)) . '</P_14_2W>';
        }
    }
    if ($d5net !== 0.0) {
        $xml[] = '    <P_13_3>' . $this->fmtAmount($d5net) . '</P_13_3>';
        $xml[] = '    <P_14_3>' . $this->fmtAmount($d5vat) . '</P_14_3>';
        if ($isForeignCurrency) {
            $xml[] = '    <P_14_3W>' . $this->fmtAmount(round($d5vat * $currencyExchange, 2)) . '</P_14_3W>';
        }
    }

    $sumGrossDiff = $sumGrossAfter - $sumGrossBefore;

    // P_13_4 / P_14_4 / P_14_4W – ryczałt dla taksówek (ręczne)
    $this->emitIfNotNull($xml, 'P_13_4', $inv->p_13_4 ?? null);
    $this->emitIfNotNull($xml, 'P_14_4', $inv->p_14_4 ?? null);
    $this->emitIfNotNull($xml, 'P_14_4W', $inv->p_14_4w ?? null);

    $this->emitIfNotNull($xml, 'P_13_5', $inv->p_13_5 ?? null);
    $this->emitIfNotNull($xml, 'P_14_5', $inv->p_14_5 ?? null);

    // Korekta marży: pozycje bez VAT trafiają do grupy 0kr, ale wg broszury FA(3)
    // sekwencje stawek (m.in. P_13_6_1) "nie dotyczą procedury marży" — wartość różnicy
    // sprzedaży idzie do P_13_11. Wykrywamy marżę także po fakturze pierwotnej.
    $marginCorr = $this->isMarginInvoice($inv);

    // P_13_6_1 – 0% KR (diff z pozycji + ew. ręczne) — pomijamy dla korekty marży
    if (!$marginCorr) {
        $c13_6_1 = $d0kr    ?: ($inv->p_13_6_1 ?? null);
        $this->emitIfNotNull($xml, 'P_13_6_1', $c13_6_1 ?: null);
    }
    // P_13_6_2 – 0% WDT
    $c13_6_2 = $d0wdt   ?: ($inv->p_13_6_2 ?? null);
    $this->emitIfNotNull($xml, 'P_13_6_2', $c13_6_2 ?: null);
    // P_13_6_3 – 0% eksport (0 EX)
    $c13_6_3 = $d0ex    ?: ($inv->p_13_6_3 ?? null);
    $this->emitIfNotNull($xml, 'P_13_6_3', $c13_6_3 ?: null);

    // P_13_7 – sprzedaż zwolniona (zw.)
    $c13_7 = $dzwNet   ?: ($inv->p_13_7 ?? null);
    $this->emitIfNotNull($xml, 'P_13_7', $c13_7 ?: null);

    // P_13_8 – dostawa/usługi poza terytorium kraju (np I)
    $c13_8 = $dnpINet  ?: ($inv->p_13_8 ?? null);
    $this->emitIfNotNull($xml, 'P_13_8', $c13_8 ?: null);

    // P_13_9 – usługi art. 100 ust. 1 pkt 4 UE (np II)
    $c13_9 = $dnpIINet ?: ($inv->p_13_9 ?? null);
    $this->emitIfNotNull($xml, 'P_13_9', $c13_9 ?: null);

    // P_13_10 – odwrotne obciążenie (oo)
    $c13_10 = $dooNet ?: ($inv->p_13_10 ?? null);
    $this->emitIfNotNull($xml, 'P_13_10', $c13_10 ?: null);
    // P_13_11 – procedura marży: dla korekty marży KWOTA RÓŻNICY wartości sprzedaży
    // (pozycje marży mają netto=brutto, więc różnica netto z grupy 0kr = różnica wartości sprzedaży).
    $c13_11 = $marginCorr ? (round($d0kr, 2) ?: null) : ($inv->p_13_11 ?? null);
    $this->emitIfNotNull($xml, 'P_13_11', $c13_11);

    return [$xml, $sumGrossDiff];
}

    // ======================== ADNOTACJE ========================

    private function buildAnnotationsXml(Invoice $inv, array $items = []): array
    {
        $xml = [];

        // Dekoduj JSON z kolumny `annotations`
        $ann = [];
        if (!empty($inv->annotations)) {
            $decoded = is_array($inv->annotations) ? $inv->annotations : json_decode((string)$inv->annotations, true);
            if (is_array($decoded)) {
                $ann = $decoded;
            }
        }

        $xml[] = '    <Adnotacje>';

        // P_16 – metoda kasowa (1 – TAK, 2 – NIE)
        $p16 = !empty($ann['cash_method']) ? 1 : 2;
        $xml[] = '      <P_16>' . $this->esc((string)$p16) . '</P_16>';

        // P_17 – samofakturowanie (1 – TAK, 2 – NIE); brak formularza, zawsze 2
        $xml[] = '      <P_17>2</P_17>';

        // P_18 – odwrotne obciążenie (reverse charge) (1 – TAK, 2 – NIE)
        $p18 = !empty($ann['reverse_charge']) ? 1 : 2;
        $xml[] = '      <P_18>' . $this->esc((string)$p18) . '</P_18>';

        // P_18A – MPP (mechanizm podzielonej płatności) (1 – TAK, 2 – NIE)
        $p18a = !empty($inv->is_split_payment) ? 1 : 2;
        $xml[] = '      <P_18A>' . $this->esc((string)$p18a) . '</P_18A>';

        // Zwolnienie – sprzedaż zwolniona (P_19, P_19A/B/C, P_19N)
        $xml = array_merge($xml, $this->buildZwolnienieXml($inv, $ann, $items));

        // NoweSrodkiTransportu – WDT nowych środków transportu
        $xml = array_merge($xml, $this->buildNoweSrodkiTransportuXml($inv));

        // P_23 – procedura uproszczona WE (1 – TAK, 2 – NIE)
        $p23 = !empty($ann['triangular']) ? 1 : 2;
        $xml[] = '      <P_23>' . $this->esc((string)$p23) . '</P_23>';

        // PMarzy – procedury marży
        $xml = array_merge($xml, $this->buildPMarzyXml($inv));

        $xml[] = '    </Adnotacje>';

        return $xml;
    }


    private function buildZwolnienieXml(Invoice $inv, array $ann = [], array $items = []): array
    {
        $xml = [];

        // Flaga: czy faktura dokumentuje sprzedaż zwolnioną — wyłącznie na podstawie checkboxa
        $hasExempt = !empty($ann['supply_goods']);

        // Dodatkowe szczegóły z osobnych kolumn DB
        $taxFreeType  = $inv->annotations_tax_free       ?? null; // 'ustawa' / 'dyrektywa' / 'inna'
        $taxFreeField = $inv->annotations_tax_free_field  ?? null; // treść przepisu

        $xml[] = '      <Zwolnienie>';

        if ($hasExempt) {
            // 🔹 WYSTĘPUJE SPRZEDAŻ ZWOLNIONA – NIE WYSYŁAMY P_19N

            // P_19 jest typu etd:TWybor1 — akceptuje wyłącznie wartość "1" (flaga).
            // Rozróżnienie ustawa/dyrektywa/inna odbywa się przez użycie P_19A/P_19B/P_19C.
            $xml[] = '        <P_19>1</P_19>';

            // Mapowanie: annotations_tax_free → P_19A/B/C
            // A = ustawa, B = dyrektywa, C = inna
            $tagMap = ['ustawa' => 'P_19A', 'dyrektywa' => 'P_19B', 'inna' => 'P_19C'];
            $tag = $tagMap[$taxFreeType] ?? 'P_19A';
            // XSD TZnakowy wymaga minLength=1 — jeśli pole puste, użyj domyślnej podstawy prawnej
            $fieldValue = !empty($taxFreeField) ? (string)$taxFreeField : 'art. 43 ust. 1 ustawy o VAT';
            $xml[] = '        <' . $tag . '>' . $this->esc($fieldValue) . '</' . $tag . '>';
        } else {
            // 🔹 BRAK SPRZEDAŻY ZWOLNIONEJ – ZAWSZE P_19N = 1
            $xml[] = '        <P_19N>1</P_19N>';
        }

        $xml[] = '      </Zwolnienie>';

        return $xml;
    }

    private function buildNoweSrodkiTransportuXml(Invoice $inv): array
    {
        $xml = [];

        // Dane z DB (invoice_new_transports) — backing dla NoweSrodkiTransportu
        $isNewTransportWdt = (bool)($inv->is_new_transport_wdt ?? false);
        $rows              = (array)($inv->invoice_new_transports ?? []);

        $xml[] = '      <NoweSrodkiTransportu>';

        if ($isNewTransportWdt && !empty($rows)) {
            // P_22 – 1 gdy jest WDT nowych środków transportu
            $xml[] = '        <P_22>1</P_22>';
            // P_42_5 – obowiązek z art. 42 ust. 5 (1 – tak, 2 – nie)
            if (!empty($inv->p_42_5)) {
                $xml[] = '        <P_42_5>' . $this->esc((string)$inv->p_42_5) . '</P_42_5>';
            }

            // NowySrodekTransportu – max 10 000 wystąpień
            foreach ($rows as $row) {
                $xml[] = '        <NowySrodekTransportu>';
                // P_22A – data dopuszczenia do użytku
                $p22a = $row->p_22a ?? $row['p_22a'] ?? null;
                if (!empty($p22a)) {
                    $xml[] = '          <P_22A>' . $this->esc((string)$p22a) . '</P_22A>';
                }
                // P_NrWierszaNST – nr wiersza faktury
                $nrWiersza = $row->p_nrwierszanst ?? $row['p_nrwierszanst'] ?? null;
                if (!empty($nrWiersza)) {
                    $xml[] = '          <P_NrWierszaNST>' . $this->esc((string)$nrWiersza) . '</P_NrWierszaNST>';
                }
                // P_22BMK / P_22BMD / P_22BK / P_22BNR / P_22BRP – marka/model/kolor/NR rej./rok prod.
                foreach (['p_22bmk' => 'P_22BMK', 'p_22bmd' => 'P_22BMD', 'p_22bk' => 'P_22BK', 'p_22bnr' => 'P_22BNR', 'p_22brp' => 'P_22BRP'] as $dbCol => $tag) {
                    $val = $row->{$dbCol} ?? $row[$dbCol] ?? null;
                    if (!empty($val)) {
                        $xml[] = '          <' . $tag . '>' . $this->esc((string)$val) . '</' . $tag . '>';
                    }
                }
                // P_22B – przebieg pojazdu lądowego
                $p22b = $row->p_22b ?? $row['p_22b'] ?? null;
                if (!empty($p22b)) {
                    $xml[] = '          <P_22B>' . $this->esc((string)$p22b) . '</P_22B>';
                }
                // Numer VIN / nadwozia / podwozia / ramy – P_22B1..P_22B4 (wybór – tylko jedno)
                foreach (['p_22b1' => 'P_22B1', 'p_22b2' => 'P_22B2', 'p_22b3' => 'P_22B3', 'p_22b4' => 'P_22B4'] as $dbCol => $tag) {
                    $val = $row->{$dbCol} ?? $row[$dbCol] ?? null;
                    if (!empty($val)) {
                        $xml[] = '          <' . $tag . '>' . $this->esc((string)$val) . '</' . $tag . '>';
                        break;
                    }
                }
                // P_22BT – typ nowego środka transportu
                $p22bt = $row->p_22bt ?? $row['p_22bt'] ?? null;
                if (!empty($p22bt)) {
                    $xml[] = '          <P_22BT>' . $this->esc((string)$p22bt) . '</P_22BT>';
                }
                // P_22C / P_22C1 – jednostki pływające
                $p22c = $row->p_22c ?? $row['p_22c'] ?? null;
                if (!empty($p22c)) {
                    $xml[] = '          <P_22C>' . $this->esc((string)$p22c) . '</P_22C>';
                }
                $p22c1 = $row->p_22c1 ?? $row['p_22c1'] ?? null;
                if (!empty($p22c1)) {
                    $xml[] = '          <P_22C1>' . $this->esc((string)$p22c1) . '</P_22C1>';
                }
                // P_22D / P_22D1 – statki powietrzne
                $p22d = $row->p_22d ?? $row['p_22d'] ?? null;
                if (!empty($p22d)) {
                    $xml[] = '          <P_22D>' . $this->esc((string)$p22d) . '</P_22D>';
                }
                $p22d1 = $row->p_22d1 ?? $row['p_22d1'] ?? null;
                if (!empty($p22d1)) {
                    $xml[] = '          <P_22D1>' . $this->esc((string)$p22d1) . '</P_22D1>';
                }

                $xml[] = '        </NowySrodekTransportu>';
            }

        } else {
            // P_22N – brak WDT nowych środków transportu
            $xml[] = '        <P_22N>1</P_22N>';
        }

        $xml[] = '      </NoweSrodkiTransportu>';

        return $xml;
    }

    /**
     * Mapa procedury marży: margin_type → element FA(3) (P_PMarzy_*) + wymagany zwrot na fakturze.
     * Zgodnie z broszurą MF FA(3) (Tabela 33). JEDNA mapa dla XML i dla adnotacji w opisie,
     * żeby pole strukturalne i treść faktury się nie rozjeżdżały.
     */
    private function marginProcedureMap(): array
    {
        return [
            'travel'       => ['element' => 'P_PMarzy_2',   'phrase' => 'procedura marży dla biur podróży'],
            'used_goods'   => ['element' => 'P_PMarzy_3_1', 'phrase' => 'procedura marży - towary używane'],
            'art'          => ['element' => 'P_PMarzy_3_2', 'phrase' => 'procedura marży - dzieła sztuki'],
            'collectibles' => ['element' => 'P_PMarzy_3_3', 'phrase' => 'procedura marży - przedmioty kolekcjonerskie i antyki'],
        ];
    }

    /**
     * Zwraca margin_type dla faktury — także dla KOREKTY marży.
     * Jeśli sama faktura nie ma margin_type, a jest korektą (parent_id), bierze go z faktury pierwotnej.
     * Dzięki temu korekta marży poprawnie deklaruje procedurę marży (P_PMarzy) i sumę w P_13_11,
     * nawet jeśli margin_type nie został zapisany na samej korekcie.
     */
    private function resolveMarginType(Invoice $inv): string
    {
        $mt = trim((string)($inv->margin_type ?? ''));
        if ($mt !== '') {
            return $mt;
        }
        if (!empty($inv->parent_id)) {
            try {
                $parent = $this->Invoices->find()
                    ->select(['id', 'margin_type'])
                    ->where(['id' => $inv->parent_id])
                    ->first();
                if ($parent) {
                    return trim((string)($parent->margin_type ?? ''));
                }
            } catch (\Throwable) {
                // ignore — brak rodzica/zapytania nie może wywalić budowania XML
            }
        }

        return '';
    }

    /**
     * Czy faktura jest w procedurze marży (także korekta marży) — detekcja po TYPIE,
     * nie tylko po margin_type. Faktura marża ma type='margin' nawet jeśli margin_type
     * nie został wybrany; korektę wykrywamy po fakturze pierwotnej (parent_id).
     */
    private function isMarginInvoice(Invoice $inv): bool
    {
        if ((string)($inv->type ?? '') === 'margin') {
            return true;
        }
        if (trim((string)($inv->margin_type ?? '')) !== '') {
            return true;
        }
        if (!empty($inv->parent_id)) {
            try {
                $parent = $this->Invoices->find()
                    ->select(['id', 'type', 'margin_type'])
                    ->where(['id' => $inv->parent_id])
                    ->first();
                if ($parent) {
                    if ((string)($parent->type ?? '') === 'margin') {
                        return true;
                    }
                    if (trim((string)($parent->margin_type ?? '')) !== '') {
                        return true;
                    }
                }
            } catch (\Throwable) {
                // ignore — nie wywalaj budowania XML
            }
        }

        return false;
    }

    private function buildPMarzyXml(Invoice $inv): array
    {
        $xml = [];

        // Procedurę marży wyprowadzamy z `margin_type` (także z faktury pierwotnej dla korekty).
        // XSD: gdy P_PMarzy=1 wymagane jest DOKŁADNIE jedno z P_PMarzy_2 / P_PMarzy_3_1..3.
        $marginType = $this->resolveMarginType($inv);
        $procedure  = $this->marginProcedureMap()[$marginType] ?? null;

        $xml[] = '      <PMarzy>';

        if ($procedure !== null) {
            // P_PMarzy – wystąpienie procedur marży (1 – tak)
            $xml[] = '        <P_PMarzy>1</P_PMarzy>';
            // Dokładnie jedna flaga „wyboru” odpowiadająca rodzajowi procedury
            $xml[] = '        <' . $procedure['element'] . '>1</' . $procedure['element'] . '>';
        } else {
            // Brak (lub nieokreślona) procedura marży → P_PMarzyN
            // (bezpieczne dla XSD; faktury marża powinny mieć ustawiony margin_type)
            $xml[] = '        <P_PMarzyN>1</P_PMarzyN>';
        }

        $xml[] = '      </PMarzy>';

        return $xml;
    }

    // ======================== WIERSZE ========================
private function buildLinesXml(Invoice $inv, array $items): array
{
    $xml    = [];
    $rodzaj = $this->resolveRodzajFaktury($inv);
    // Procedura marży (także korekta marży) — pozycje nie mają stawki VAT (P_12 pomijane).
    $isMargin = $this->isMarginInvoice($inv);

    // 🔹 Zwykłe faktury – tak jak było
    if (!in_array($rodzaj, ['KOR', 'KOR_ZAL', 'KOR_ROZ'], true)) {
        foreach ($items as $i => $it) {
            $xml = array_merge($xml, $this->buildSingleLineXml($it, $i + 1, false, $isMargin));
        }
        return $xml;
    }

    // 🔹 Korekty – wiersze "przed" i "po" z tym samym NrWierszaFa
    $origItems = (array)($inv->original_items ?? []);

    $max = max(count($origItems), count($items));

    for ($i = 0; $i < $max; $i++) {
        $rowNo = $i + 1;

        // stan przed
        if (isset($origItems[$i])) {
            $xml = array_merge($xml, $this->buildSingleLineXml($origItems[$i], $rowNo, true, $isMargin));
        }

        // stan po
        if (isset($items[$i])) {
            $xml = array_merge($xml, $this->buildSingleLineXml($items[$i], $rowNo, false, $isMargin));
        }
    }

    return $xml;
}

private function buildSingleLineXml(object $it, int $rowNo, bool $isBeforeCorrection, bool $isMargin = false): array
{
    $xml = [];

    $name  = (string)($it->name ?? 'Pozycja');
    $qty   = (float)($it->quantity ?? 1);
    $unit  = !empty($it->unit) ? (string)$it->unit : 'szt.';
    $rate  = isset($it->vat) ? (float)$it->vat->rate : 0.0;

    $unitNet    = (float)($it->price ?? $it->netto ?? 0.0);
    $netTotal   = (float)($it->netto ?? $unitNet * $qty);
    $grossTotal = (float)($it->brutto ?? $netTotal * (1 + $rate / 100));
    $vatTotal   = $grossTotal - $netTotal;

    $xml[] = '    <FaWiersz>';
    // Kolejność elementów zgodna z XSD FA(3) §FaWiersz sequence:
    $xml[] = '      <NrWierszaFa>' . $rowNo . '</NrWierszaFa>';

    // UU_ID — identyfikator UUID wiersza (dla korekt) [pozycja 2 w XSD]
    // Tymczasowo zakomentowane — nie przesyłać do KSeF
    // if (!empty($it->uu_id)) {
    //     $xml[] = '      <UU_ID>' . $this->esc((string)$it->uu_id) . '</UU_ID>';
    // }

    // P_6A — data dostawy/usługi per-wiersz [pozycja 3 w XSD]
    if (!empty($it->line_date)) {
        $lineDate = ($it->line_date instanceof \DateTimeInterface)
            ? $it->line_date->format('Y-m-d')
            : (string)$it->line_date;
        $xml[] = '      <P_6A>' . $this->esc($lineDate) . '</P_6A>';
    }

    $xml[] = '      <P_7>' . $this->esc($name) . '</P_7>';

    // GTIN [pozycja 6 w XSD — przed PKWiU]
    if (!empty($it->gtin)) {
        $xml[] = '      <GTIN>' . $this->esc((string)$it->gtin) . '</GTIN>';
    }

    // PKWiU [pozycja 7 w XSD]
    if (!empty($it->pkwiu)) {
        $xml[] = '      <PKWiU>' . $this->esc((string)$it->pkwiu) . '</PKWiU>';
    }

    // CN [pozycja 8 w XSD]
    if (!empty($it->cn_code)) {
        $xml[] = '      <CN>' . $this->esc((string)$it->cn_code) . '</CN>';
    }

    $xml[] = '      <P_8A>' . $this->esc($unit) . '</P_8A>';
    $xml[] = '      <P_8B>' . $this->fmtQty($qty) . '</P_8B>';
    $xml[] = '      <P_9A>' . $this->fmtAmount($unitNet) . '</P_9A>';

    // P_9B — cena jednostkowa brutto (opcjonalne)
    if (!empty($it->gross_unit_price)) {
        $xml[] = '      <P_9B>' . $this->fmtAmount((float)$it->gross_unit_price) . '</P_9B>';
    }

    // P_10 — kwota opustu/obniżki (opcjonalne)
    if (!empty($it->discount_amount) && (float)$it->discount_amount > 0) {
        $xml[] = '      <P_10>' . $this->fmtAmount((float)$it->discount_amount) . '</P_10>';
    }

    $xml[] = '      <P_11>' . $this->fmtAmount($netTotal) . '</P_11>';

    // P_11A — wartość brutto wiersza (opcjonalne)
    if ($grossTotal > 0) {
        $xml[] = '      <P_11A>' . $this->fmtAmount($grossTotal) . '</P_11A>';
    }

    // P_11Vat — kwota podatku VAT wiersza
    if (!empty($it->vat_amount)) {
        $xml[] = '      <P_11Vat>' . $this->fmtAmount((float)$it->vat_amount) . '</P_11Vat>';
    } elseif ($vatTotal > 0) {
        $xml[] = '      <P_11Vat>' . $this->fmtAmount($vatTotal) . '</P_11Vat>';
    }

    // P_12 — stawka VAT zgodnie z FA(3) / TStawkaPodatku
    $vatName = strtolower(trim((string)($it->vat->name ?? '')));
    if ($rate >= 22.5) {
        $p12 = '23';
    } elseif ($rate >= 7.5) {
        $p12 = '8';
    } elseif ($rate >= 4.5) {
        $p12 = '5';
    } elseif ($rate > 0) {
        $p12 = (string)(int)$rate;
    } elseif (str_starts_with($vatName, 'zw')) {
        $p12 = 'zw';
    } elseif (str_starts_with($vatName, 'oo')) {
        $p12 = 'oo';
    } elseif (str_contains($vatName, 'wdt')) {
        $p12 = '0 WDT';
    } elseif (str_contains($vatName, 'exp') || str_contains($vatName, 'eks')) {
        $p12 = '0 EX';
    } elseif (str_contains($vatName, 'spoza')) {
        // "nie podl. spoza UE" → np I (P_13_8: poza terytorium kraju, nabywca spoza UE)
        $p12 = 'np I';
    } elseif (str_contains($vatName, 'ue')) {
        // "nie podl. UE" → np II (P_13_9: wewnątrzwspólnotowe usługi, art. 100 ust. 1 pkt 4)
        $p12 = 'np II';
    } elseif (str_contains($vatName, 'nie podl')) {
        // "nie podl." bez kwalifikatora → np I (default poza terytorium kraju)
        $p12 = 'np I';
    } elseif (str_starts_with($vatName, 'np')) {
        $p12 = 'np I';
    } else {
        // 0% krajowe
        $p12 = '0 KR';
    }
    // Procedura marży: stan PO korekcie (oraz zwykła faktura marża) — zawsze "marża",
    // więc pomijamy P_12 (XSD: opcjonalne) → w wierszu jest "marża", a nie "0% KR".
    // Stan PRZED korektą zostaje "jaki był" — emitujemy oryginalną stawkę.
    if (!$isMargin || $isBeforeCorrection) {
        $xml[] = '      <P_12>' . $p12 . '</P_12>';
    }

    // KwotaAkcyzy [pozycja 21 w XSD — po P_12_Zal_15]
    if (!empty($it->excise_amount)) {
        $xml[] = '      <KwotaAkcyzy>' . $this->fmtAmount((float)$it->excise_amount) . '</KwotaAkcyzy>';
    }

    // GTU [pozycja 22 w XSD]
    if (!empty($it->gtu_code)) {
        $xml[] = '      <GTU>' . $this->esc($it->gtu_code) . '</GTU>';
    }

    // Procedura [pozycja 23 w XSD]
    if (!empty($it->procedure_marking)) {
        $proc = $this->normalizeProcedura((string)$it->procedure_marking);
        if ($proc !== '') {
            $xml[] = '      <Procedura>' . $this->esc($proc) . '</Procedura>';
        }
    }

    // KursWaluty — kurs waluty per-wiersz [pozycja 24 w XSD]
    if (!empty($it->kurs_waluty)) {
        $xml[] = '      <KursWaluty>' . $this->fmtAmount((float)$it->kurs_waluty, 6) . '</KursWaluty>';
    }

    if ($isBeforeCorrection) {
        $xml[] = '      <StanPrzed>1</StanPrzed>';
    }

    $xml[] = '    </FaWiersz>';

    return $xml;
}




    // ======================== PŁATNOŚĆ ========================

    private function buildPaymentXml(Invoice $inv, string $issueDate, ?string $soldDate): array
    {
        $xml = [];

        $xml[] = '    <Platnosc>';

        // ZaplataCzesciowa — preferencja: wielokrotne wpłaty z invoice_payments, fallback na skalary
        // XSD <xsd:choice minOccurs="0"> w <Platnosc>:
        //   A) Zaplacono=1 + DataZaplaty              → jedna pełna zapłata jednorazowa
        //   B) ZnacznikZaplatyCzesciowej=1 + ZaplataCzesciowa[]  → zapłata częściowa
        //   C) ZnacznikZaplatyCzesciowej=2 + ZaplataCzesciowa[]  → zapłata w całości poprzez 2+ raty
        $invoiceTotal = round((float)($inv->total ?? 0.0), 2);
        $payments = (array)($inv->invoice_payments ?? []);
        if (!empty($payments)) {
            $sumPaid = round(array_sum(array_map(fn($p) => (float)($p->amount ?? $p['amount'] ?? 0), $payments)), 2);
            if ($sumPaid > 0.0 && $sumPaid < $invoiceTotal) {
                // Przypadek B: zapłata częściowa — ZnacznikZaplatyCzesciowej=1
                $xml[] = '      <ZnacznikZaplatyCzesciowej>1</ZnacznikZaplatyCzesciowej>';
                foreach ($payments as $pay) {
                    $pAmt  = (float)($pay->amount ?? $pay['amount'] ?? 0);
                    $pDate = isset($pay->payment_date)
                        ? ($pay->payment_date instanceof \DateTimeInterface ? $pay->payment_date->format('Y-m-d') : (string)$pay->payment_date)
                        : $issueDate;
                    $xml[] = '      <ZaplataCzesciowa>';
                    $xml[] = '        <KwotaZaplatyCzesciowej>' . $this->fmtAmount($pAmt) . '</KwotaZaplatyCzesciowej>';
                    $xml[] = '        <DataZaplatyCzesciowej>' . $this->esc($pDate) . '</DataZaplatyCzesciowej>';
                    array_push($xml, ...$this->buildFormaPlatnosciXml($pay->payment_method ?? $pay['payment_method'] ?? null, '        '));
                    $xml[] = '      </ZaplataCzesciowa>';
                }
            } elseif ($sumPaid >= $invoiceTotal && $invoiceTotal > 0.0) {
                if (count($payments) >= 2) {
                    // Przypadek C: zapłacono w całości w 2+ ratach — ZnacznikZaplatyCzesciowej=2
                    $xml[] = '      <ZnacznikZaplatyCzesciowej>2</ZnacznikZaplatyCzesciowej>';
                    foreach ($payments as $pay) {
                        $pAmt  = (float)($pay->amount ?? $pay['amount'] ?? 0);
                        $pDate = isset($pay->payment_date)
                            ? ($pay->payment_date instanceof \DateTimeInterface ? $pay->payment_date->format('Y-m-d') : (string)$pay->payment_date)
                            : $issueDate;
                        $xml[] = '      <ZaplataCzesciowa>';
                        $xml[] = '        <KwotaZaplatyCzesciowej>' . $this->fmtAmount($pAmt) . '</KwotaZaplatyCzesciowej>';
                        $xml[] = '        <DataZaplatyCzesciowej>' . $this->esc($pDate) . '</DataZaplatyCzesciowej>';
                        array_push($xml, ...$this->buildFormaPlatnosciXml($pay->payment_method ?? $pay['payment_method'] ?? null, '        '));
                        $xml[] = '      </ZaplataCzesciowa>';
                    }
                } else {
                    // Przypadek A: jedna wpłata = pełna kwota faktury → Zaplacono=1 + DataZaplaty
                    $pay   = $payments[0];
                    $pDate = isset($pay->payment_date)
                        ? ($pay->payment_date instanceof \DateTimeInterface ? $pay->payment_date->format('Y-m-d') : (string)$pay->payment_date)
                        : $issueDate;
                    $xml[] = '      <Zaplacono>1</Zaplacono>';
                    $xml[] = '      <DataZaplaty>' . $this->esc($pDate) . '</DataZaplaty>';
                }
            }
        } else {
            // Fallback: skalarny alreadypaid
            $alreadyPaid = round((float)($inv->alreadypaid ?? 0.0), 2);
            if ($alreadyPaid > 0.0 && $alreadyPaid < $invoiceTotal) {
                // Przypadek B: zapłata częściowa — ZnacznikZaplatyCzesciowej=1
                $xml[] = '      <ZnacznikZaplatyCzesciowej>1</ZnacznikZaplatyCzesciowej>';
                $xml[] = '      <ZaplataCzesciowa>';
                $xml[] = '        <KwotaZaplatyCzesciowej>' . $this->fmtAmount($alreadyPaid) . '</KwotaZaplatyCzesciowej>';
                // Preferuj partial_paid_at, fallback paid_at, ostatecznie issueDate.
                $ppDate = $inv->partial_paid_at
                    ? $inv->partial_paid_at->format('Y-m-d')
                    : ($inv->paid_at ? $inv->paid_at->format('Y-m-d') : $issueDate);
                $xml[] = '        <DataZaplatyCzesciowej>' . $this->esc($ppDate) . '</DataZaplatyCzesciowej>';
                array_push($xml, ...$this->buildFormaPlatnosciXml($inv->paymentmethod ?? null, '        '));
                $xml[] = '      </ZaplataCzesciowa>';
            } elseif ($alreadyPaid >= $invoiceTotal && $invoiceTotal > 0.0) {
                // Przypadek A: kwota zapłaty = kwota faktury → Zaplacono=1 + DataZaplaty
                // Preferuj paid_at (to pole jest wypełniane przez "Oznacz jako opłacone"),
                // fallback partial_paid_at, ostatecznie issueDate.
                $ppDate = $inv->paid_at
                    ? $inv->paid_at->format('Y-m-d')
                    : ($inv->partial_paid_at ? $inv->partial_paid_at->format('Y-m-d') : $issueDate);
                $xml[] = '      <Zaplacono>1</Zaplacono>';
                $xml[] = '      <DataZaplaty>' . $this->esc($ppDate) . '</DataZaplaty>';
            }
        }

        // Fallback wg stanu z UI: jeśli faktura jest w systemie opłacona, a powyżej NIE wyemitowano
        // znacznika zapłaty (np. zaliczka oznaczona jako otrzymana bez zsynchronizowanego alreadypaid
        // / bez wpisu invoice_payments), wykaż pełną zapłatę. Sygnał „opłacona" jak w UI (add_advance.php
        // $__isPaid): paymentstate='paid' LUB dla zaliczki/końcowej ustawiona data otrzymania zaliczki.
        $hasPaymentMarker = false;
        foreach ($xml as $__l) {
            if (str_contains($__l, '<Zaplacono>') || str_contains($__l, '<ZnacznikZaplatyCzesciowej>')) {
                $hasPaymentMarker = true;
                break;
            }
        }
        // UWAGA: bez warunku total>0 — faktura końcowa rozliczająca 100% zaliczek ma kwotę
        // pozostałą = 0, a mimo to jest opłacona (paymentstate='paid') i ma pokazać „Zapłacono".
        $uiPaid = ((string)($inv->paymentstate ?? '') === 'paid')
            || (in_array((string)($inv->type ?? ''), ['advance', 'final'], true) && !empty($inv->advance_received_date));
        if (!$hasPaymentMarker && $uiPaid) {
            $fmtDate = static function ($d) {
                if (empty($d)) { return null; }
                return method_exists($d, 'format') ? $d->format('Y-m-d') : substr((string)$d, 0, 10);
            };
            $payDate = $fmtDate($inv->paid_at)
                ?? $fmtDate($inv->partial_paid_at)
                ?? $fmtDate($inv->advance_received_date ?? null)
                ?? $issueDate;
            $xml[] = '      <Zaplacono>1</Zaplacono>';
            $xml[] = '      <DataZaplaty>' . $this->esc($payDate) . '</DataZaplaty>';
        }

        $due = $inv->paymentdate
            ? $inv->paymentdate->format('Y-m-d')
            : ($soldDate ?: $issueDate);

        $xml[] = '      <TerminPlatnosci>';
        $xml[] = '        <Termin>' . $this->esc($due) . '</Termin>';
        $xml[] = '      </TerminPlatnosci>';
        array_push($xml, ...$this->buildFormaPlatnosciXml($inv->paymentmethod ?? null, '      '));

        $seller      = $inv->invoice_company_detail ?? null;
        $rb          = trim((string)($seller?->bank_account ?? ''));
        $bankName    = trim((string)($seller?->bank_name ?? ''));
        $bankDesc    = trim((string)($seller?->bank_desc ?? ''));
        $swift       = trim((string)($seller?->swift ?? ''));
        $bankCoresp  = trim((string)($seller?->bank_correspondent ?? ''));

        if ($rb !== '' || $bankName !== '' || $bankDesc !== '' || $swift !== '') {
            $xml[] = '      <RachunekBankowy>';
            if ($rb !== '') {
                $xml[] = '        <NrRB>' . $this->esc($rb) . '</NrRB>';
            }
            // SWIFT — po NrRB, przed NazwaBanku (per XSD order)
            if ($swift !== '') {
                $xml[] = '        <SWIFT>' . $this->esc($swift) . '</SWIFT>';
            }
            // RachunekWlasnyBanku (bank_correspondent) — pomijamy celowo
            if ($bankName !== '') {
                $xml[] = '        <NazwaBanku>' . $this->esc($bankName) . '</NazwaBanku>';
            }
            if ($bankDesc !== '') {
                $xml[] = '        <OpisRachunku>' . $this->esc($bankDesc) . '</OpisRachunku>';
            }
            $xml[] = '      </RachunekBankowy>';
        }

        // RachunekBankowyFaktora — rachunek bankowy faktora (max 20, opcjonalny)
        $factorBanks = (array)($inv->invoice_factor_banks ?? []);
        foreach ($factorBanks as $fb) {
            $fbNr = trim((string)($fb->nr_rb ?? $fb['nr_rb'] ?? ''));
            if ($fbNr === '') {
                continue;
            }
            $xml[] = '      <RachunekBankowyFaktora>';
            $xml[] = '        <NrRB>' . $this->esc($fbNr) . '</NrRB>';
            $fbSwift = trim((string)($fb->swift ?? $fb['swift'] ?? ''));
            if ($fbSwift !== '') {
                $xml[] = '        <SWIFT>' . $this->esc($fbSwift) . '</SWIFT>';
            }
            $fbOwn = trim((string)($fb->rachunek_wlasny_banku ?? $fb['rachunek_wlasny_banku'] ?? ''));
            if ($fbOwn !== '') {
                $xml[] = '        <RachunekWlasnyBanku>' . $this->esc($fbOwn) . '</RachunekWlasnyBanku>';
            }
            $fbBankName = trim((string)($fb->nazwa_banku ?? $fb['nazwa_banku'] ?? ''));
            if ($fbBankName !== '') {
                $xml[] = '        <NazwaBanku>' . $this->esc($fbBankName) . '</NazwaBanku>';
            }
            $fbDesc = trim((string)($fb->opis_rachunku ?? $fb['opis_rachunku'] ?? ''));
            if ($fbDesc !== '') {
                $xml[] = '        <OpisRachunku>' . $this->esc($fbDesc) . '</OpisRachunku>';
            }
            $xml[] = '      </RachunekBankowyFaktora>';
        }

        // Skonto — warunki + wysokość (opcjonalny)
        $skontoW = trim((string)($inv->skonto_conditions ?? ''));
        $skontoH = trim((string)($inv->skonto_amount ?? ''));
        if ($skontoW !== '' && $skontoH !== '') {
            $xml[] = '      <Skonto>';
            $xml[] = '        <WarunkiSkonta>' . $this->esc($skontoW) . '</WarunkiSkonta>';
            $xml[] = '        <WysokoscSkonta>' . $this->esc($skontoH) . '</WysokoscSkonta>';
            $xml[] = '      </Skonto>';
        }

        // LinkDoPlatnosci — FA(3) opcjonalny link do płatności online
        $payLink = trim((string)($inv->payment_link ?? ''));
        if ($payLink !== '') {
            $xml[] = '      <LinkDoPlatnosci>' . $this->esc($payLink) . '</LinkDoPlatnosci>';
        }

        $xml[] = '    </Platnosc>';

        // DIAGNOSTYKA płatności (tymczasowe) — pozwala ustalić dlaczego wizualizacja pokazuje „Brak zapłaty".
        try {
            $__markerNow = false;
            foreach ($xml as $__l2) {
                if (str_contains($__l2, '<Zaplacono>') || str_contains($__l2, '<ZnacznikZaplatyCzesciowej>')) { $__markerNow = true; break; }
            }
            \Cake\Log\Log::warning('[PAYDBG] inv=' . (string)($inv->id ?? '?')
                . ' num=' . (string)($inv->fullnumber ?? '')
                . ' type=' . (string)($inv->type ?? '')
                . ' paymentstate=' . (string)($inv->paymentstate ?? '')
                . ' alreadypaid=' . (string)($inv->alreadypaid ?? '')
                . ' total=' . (string)($inv->total ?? '')
                . ' payments=' . count((array)($inv->invoice_payments ?? []))
                . ' advReceived=' . (string)($inv->advance_received_date ?? '')
                . ' paidAt=' . (string)($inv->paid_at ?? '')
                . ' marker=' . ($__markerNow ? 'YES' : 'NO'));
        } catch (\Throwable) { /* diag never fatal */ }

        return $xml;
    }
private function mapPaymentMethod(?string $method): ?string
{
    return match ($method) {
        'cash'     => '1',
        'card'     => '2',
        'voucher'  => '3',
        'cheque'   => '4',
        'credit'   => '5',
        'transfer' => '6',
        'mobile'   => '7',
        default    => null, // 'other' i każda nieznana wartość → PlatnoscInna
    };
}

/**
 * Buduje XML dla formy płatności (FormaPlatnosci lub PlatnoscInna+OpisPlatnosci).
 * @param string $indent Wcięcie (np. '        ' dla 8 spacji)
 */
private function buildFormaPlatnosciXml(?string $method, string $indent): array
{
    $code = $this->mapPaymentMethod($method);
    if ($code !== null) {
        return [$indent . '<FormaPlatnosci>' . $code . '</FormaPlatnosci>'];
    }
    // Nieznana/inna forma — użyj PlatnoscInna + OpisPlatnosci
    $opis = ($method !== null && $method !== '' && $method !== 'other')
        ? ucfirst($method)
        : 'Inna forma płatności';
    return [
        $indent . '<PlatnoscInna>1</PlatnoscInna>',
        $indent . '<OpisPlatnosci>' . $this->esc($opis) . '</OpisPlatnosci>',
    ];
}

    // ======================== ROZLICZENIE (Obciążenia/Odliczenia) ========================

    private function buildRozliczenieXml(Invoice $inv): array
    {
        $charges = (array)($inv->invoice_charges ?? []);

        // Dla ROZ zaliczki NIE trafiają do Rozliczenie/Odliczenia.
        // Per broszura FA(3): P_15 dla art. 106f ust. 3 = "kwota pozostała do zapłaty" (już po zaliczkach).
        // Odliczenie zaliczek jest deklarowane przez element FakturaZaliczkowa (numery ZAL).
        // Rozliczenie/Odliczenia służy tylko do innych odliczeń (np. saldo klienta, opłaty urzędowe).

        if (empty($charges)) {
            return [];
        }

        $xml = [];
        $xml[] = '    <Rozliczenie>';

        // Obciążenia
        $obciazenia = array_filter($charges, fn($c) => ($c->type ?? $c['type'] ?? '') === 'obciazenie');
        $sumaObciazen = 0.0;
        foreach ($obciazenia as $o) {
            $kwota = (float)($o->kwota ?? $o['kwota'] ?? 0);
            $powod = (string)($o->powod ?? $o['powod'] ?? '');
            $sumaObciazen += $kwota;
            $xml[] = '      <Obciazenia>';
            $xml[] = '        <Kwota>' . $this->fmtAmount($kwota) . '</Kwota>';
            $xml[] = '        <Powod>' . $this->esc($powod) . '</Powod>';
            $xml[] = '      </Obciazenia>';
        }
        if ($sumaObciazen > 0) {
            $xml[] = '      <SumaObciazen>' . $this->fmtAmount($sumaObciazen) . '</SumaObciazen>';
        }

        // Odliczenia
        $odliczenia = array_filter($charges, fn($c) => ($c->type ?? $c['type'] ?? '') === 'odliczenie');
        $sumaOdliczen = 0.0;
        foreach ($odliczenia as $o) {
            $kwota = (float)($o->kwota ?? $o['kwota'] ?? 0);
            $powod = (string)($o->powod ?? $o['powod'] ?? '');
            $sumaOdliczen += $kwota;
            $xml[] = '      <Odliczenia>';
            $xml[] = '        <Kwota>' . $this->fmtAmount($kwota) . '</Kwota>';
            $xml[] = '        <Powod>' . $this->esc($powod) . '</Powod>';
            $xml[] = '      </Odliczenia>';
        }
        if ($sumaOdliczen > 0) {
            $xml[] = '      <SumaOdliczen>' . $this->fmtAmount($sumaOdliczen) . '</SumaOdliczen>';
        }

        // DoZaplaty = P_15 + SumaObciazen - SumaOdliczen (per broszura FA(3) s. 105)
        // Dla ROZ: P_15 to już kwota pozostała po zaliczkach; zaliczki nie są w Odliczenia.
        $p15 = (float)($inv->total ?? 0);
        $doZaplaty = $p15 + $sumaObciazen - $sumaOdliczen;
        if ($doZaplaty >= 0) {
            $xml[] = '      <DoZaplaty>' . $this->fmtAmount($doZaplaty) . '</DoZaplaty>';
        } else {
            $xml[] = '      <DoRozliczenia>' . $this->fmtAmount(abs($doZaplaty)) . '</DoRozliczenia>';
        }

        $xml[] = '    </Rozliczenie>';

        return $xml;
    }

    // ======================== WARUNKI TRANSAKCJI ========================

    private function buildWarunkiTransakcjiXml(Invoice $inv): array
    {
        $json = $inv->transaction_conditions_json ?? null;
        if (empty($json)) {
            return [];
        }

        $data = is_string($json) ? json_decode($json, true) : (array)$json;
        if (empty($data)) {
            return [];
        }

        $xml = [];
        $xml[] = '    <WarunkiTransakcji>';

        // Umowy (max 100) — obsługuje zarówno ['umowy'=>[['nr'=>...,'data'=>...]]] jak i ['Umowy'=>['nr1','nr2']]
        $umowy = $data['umowy'] ?? $data['Umowy'] ?? [];
        foreach ($umowy as $u) {
            $nr   = is_array($u) ? ($u['nr']   ?? $u['NrUmowy']   ?? '') : (string)$u;
            $data_ = is_array($u) ? ($u['data'] ?? $u['DataUmowy'] ?? '') : '';
            if ($nr === '' && $data_ === '') continue;
            $xml[] = '      <Umowy>';
            if ($data_ !== '') {
                $xml[] = '        <DataUmowy>' . $this->esc($data_) . '</DataUmowy>';
            }
            if ($nr !== '') {
                $xml[] = '        <NrUmowy>' . $this->esc($nr) . '</NrUmowy>';
            }
            $xml[] = '      </Umowy>';
        }

        // Zamowienia (max 100) — obsługuje zarówno ['zamowienia'=>[['nr'=>...]]] jak i ['Zamowienia'=>['nr1']]
        $zamowienia = $data['zamowienia'] ?? $data['Zamowienia'] ?? [];
        foreach ($zamowienia as $z) {
            $nr   = is_array($z) ? ($z['nr']   ?? $z['NrZamowienia']   ?? '') : (string)$z;
            $data_ = is_array($z) ? ($z['data'] ?? $z['DataZamowienia'] ?? '') : '';
            if ($nr === '' && $data_ === '') continue;
            $xml[] = '      <Zamowienia>';
            if ($data_ !== '') {
                $xml[] = '        <DataZamowienia>' . $this->esc($data_) . '</DataZamowienia>';
            }
            if ($nr !== '') {
                $xml[] = '        <NrZamowienia>' . $this->esc($nr) . '</NrZamowienia>';
            }
            $xml[] = '      </Zamowienia>';
        }

        // NrPartiiTowaru (max 1000)
        foreach (($data['nr_partii'] ?? []) as $nr) {
            $xml[] = '      <NrPartiiTowaru>' . $this->esc((string)$nr) . '</NrPartiiTowaru>';
        }

        // WarunkiDostawy (Incoterms)
        if (!empty($data['warunki_dostawy'])) {
            $xml[] = '      <WarunkiDostawy>' . $this->esc((string)$data['warunki_dostawy']) . '</WarunkiDostawy>';
        }

        // KursUmowny + WalutaUmowna
        if (!empty($data['kurs_umowny'])) {
            $xml[] = '      <KursUmowny>' . $this->fmtAmount((float)$data['kurs_umowny'], 6) . '</KursUmowny>';
        }
        if (!empty($data['waluta_umowna'])) {
            $xml[] = '      <WalutaUmowna>' . $this->esc((string)$data['waluta_umowna']) . '</WalutaUmowna>';
        }

        // Transport (max 20)
        foreach (($data['transport'] ?? []) as $t) {
            $xml[] = '      <Transport>';
            if (!empty($t['rodzaj_transportu'])) {
                $xml[] = '        <RodzajTransportu>' . (int)$t['rodzaj_transportu'] . '</RodzajTransportu>';
            } elseif (!empty($t['transport_inny'])) {
                $xml[] = '        <TransportInny>1</TransportInny>';
                $xml[] = '        <OpisInnegoTransportu>' . $this->esc((string)$t['opis_innego_transportu']) . '</OpisInnegoTransportu>';
            }
            if (!empty($t['nr_zlecenia'])) {
                $xml[] = '        <NrZleceniaTransportu>' . $this->esc((string)$t['nr_zlecenia']) . '</NrZleceniaTransportu>';
            }
            $xml[] = '      </Transport>';
        }

        // PodmiotPosredniczacy
        if (!empty($data['podmiot_posredniczacy'])) {
            $xml[] = '      <PodmiotPosredniczacy>' . (int)$data['podmiot_posredniczacy'] . '</PodmiotPosredniczacy>';
        }

        $xml[] = '    </WarunkiTransakcji>';

        return $xml;
    }

    // ======================== ZAMÓWIENIE (ZAL) ========================

    private function buildZamowienieXml(Invoice $inv): array
    {
        $orderLines = (array)($inv->invoice_order_lines ?? []);
        $rodzaj     = $this->resolveRodzajFaktury($inv);

        // Dla ZAL: jeśli brak ręcznie wpisanych linii zamówienia — ładujemy je z proformy (parent_id)
        // Proforma jest "zamówieniem/umową" w rozumieniu art. 106f ust. 1 pkt 4 ustawy VAT.
        if (empty($orderLines) && $rodzaj === 'ZAL' && !empty($inv->parent_id)) {
            $proforma = $this->fetchTable('Invoices')->find()
                ->select(['id', 'total'])
                ->where(['id' => $inv->parent_id])
                ->contain(['InvoiceContents' => ['Vats']])
                ->first();

            if ($proforma && !empty($proforma->invoice_contents)) {
                $wartoscZamowienia = 0.0;
                $syntheticLines    = [];
                $nr = 1;

                foreach ($proforma->invoice_contents as $ci) {
                    $qty     = (float)($ci->quantity ?? 1);
                    $price   = (float)($ci->price   ?? 0);
                    $netto   = (float)($ci->netto    ?? 0);
                    $brutto  = (float)($ci->brutto   ?? 0);
                    $vatRate = isset($ci->vat) ? (float)$ci->vat->rate : 0.0;
                    $vatAmt  = round($brutto - $netto, 2);

                    $wartoscZamowienia += $brutto;
                    $syntheticLines[]   = [
                        'nr'       => $nr++,
                        'name'     => trim((string)($ci->name ?? '')),
                        'unit'     => trim((string)($ci->unit ?? 'szt.')),
                        'qty'      => $qty,
                        'price'    => $price,
                        'netto'    => $netto,
                        'vat_amt'  => $vatAmt,
                        'vat_rate' => $vatRate,
                        'vat_name' => strtolower(trim((string)($ci->vat->name ?? ''))),
                        'gtu'      => trim((string)($ci->gtu_code ?? '')),
                    ];
                }

                if (!empty($syntheticLines)) {
                    $xml = [];
                    $xml[] = '    <Zamowienie>';
                    $xml[] = '      <WartoscZamowienia>' . $this->fmtAmount($wartoscZamowienia) . '</WartoscZamowienia>';

                    foreach ($syntheticLines as $sl) {
                        $xml[] = '      <ZamowienieWiersz>';
                        $xml[] = '        <NrWierszaZam>' . $sl['nr'] . '</NrWierszaZam>';
                        if ($sl['name'] !== '') {
                            $xml[] = '        <P_7Z>' . $this->esc($sl['name']) . '</P_7Z>';
                        }
                        if ($sl['unit'] !== '') {
                            $xml[] = '        <P_8AZ>' . $this->esc($sl['unit']) . '</P_8AZ>';
                        }
                        $xml[] = '        <P_8BZ>'      . $this->fmtAmount($sl['qty'])      . '</P_8BZ>';
                        $xml[] = '        <P_9AZ>'      . $this->fmtAmount($sl['price'])    . '</P_9AZ>';
                        $xml[] = '        <P_11NettoZ>' . $this->fmtAmount($sl['netto'])    . '</P_11NettoZ>';
                        $xml[] = '        <P_11VatZ>'   . $this->fmtAmount($sl['vat_amt'])  . '</P_11VatZ>';
                        // P_12Z: wartość enumeracyjna per TStawkaPodatku FA(3) (np. "23", "8", "zw", "0 KR")
                        $slRate    = $sl['vat_rate'];
                        $slVatName = $sl['vat_name'];
                        if ($slRate >= 22.5) {
                            $p12z = '23';
                        } elseif ($slRate >= 7.5) {
                            $p12z = '8';
                        } elseif ($slRate >= 4.5) {
                            $p12z = '5';
                        } elseif ($slRate > 0) {
                            $p12z = (string)(int)$slRate;
                        } elseif (str_starts_with($slVatName, 'zw')) {
                            $p12z = 'zw';
                        } elseif (str_starts_with($slVatName, 'oo')) {
                            $p12z = 'oo';
                        } elseif (str_contains($slVatName, 'wdt')) {
                            $p12z = '0 WDT';
                        } elseif (str_contains($slVatName, 'exp') || str_contains($slVatName, 'eks')) {
                            $p12z = '0 EX';
                        } elseif (str_contains($slVatName, 'spoza')) {
                            $p12z = 'np I';
                        } elseif (str_contains($slVatName, 'ue')) {
                            $p12z = 'np II';
                        } elseif (str_contains($slVatName, 'nie podl')) {
                            $p12z = 'np I';
                        } elseif (str_starts_with($slVatName, 'np')) {
                            $p12z = 'np I';
                        } else {
                            $p12z = '0 KR';
                        }
                        $xml[] = '        <P_12Z>' . $p12z . '</P_12Z>';
                        if ($sl['gtu'] !== '') {
                            $xml[] = '        <GTUZ>' . $this->esc($sl['gtu']) . '</GTUZ>';
                        }
                        $xml[] = '      </ZamowienieWiersz>';
                    }

                    $xml[] = '    </Zamowienie>';
                    return $xml;
                }
            }
        }

        if (empty($orderLines) && empty($inv->order_total_gross)) {
            return [];
        }

        $xml = [];
        $xml[] = '    <Zamowienie>';

        // WartoscZamowienia — łączna wartość zamówienia brutto
        if (!empty($inv->order_total_gross)) {
            $xml[] = '      <WartoscZamowienia>' . $this->fmtAmount((float)$inv->order_total_gross) . '</WartoscZamowienia>';
        }

        foreach ($orderLines as $line) {
            $xml[] = '      <ZamowienieWiersz>';
            $xml[] = '        <NrWierszaZam>' . (int)($line->nr_wiersza ?? $line['nr_wiersza'] ?? 0) . '</NrWierszaZam>';

            $fields = [
                'uu_id' => 'UU_IDZ',
                'name' => 'P_7Z',
                'indeks' => 'IndeksZ',
                'gtin' => 'GTINZ',
                'pkwiu' => 'PKWiUZ',
                'cn_code' => 'CNZ',
                'pkob' => 'PKOBZ',
                'unit' => 'P_8AZ',
            ];
            foreach ($fields as $dbCol => $tag) {
                $val = $line->{$dbCol} ?? $line[$dbCol] ?? null;
                if (!empty($val)) {
                    $xml[] = '        <' . $tag . '>' . $this->esc((string)$val) . '</' . $tag . '>';
                }
            }

            // Pola numeryczne
            $numFields = [
                'quantity' => 'P_8BZ',
                'price' => 'P_9AZ',
                'netto' => 'P_11NettoZ',
                'vat_amount' => 'P_11VatZ',
            ];
            foreach ($numFields as $dbCol => $tag) {
                $val = $line->{$dbCol} ?? $line[$dbCol] ?? null;
                if ($val !== null && $val !== '') {
                    $xml[] = '        <' . $tag . '>' . $this->fmtAmount((float)$val) . '</' . $tag . '>';
                }
            }

            // P_12Z stawka — wartość enumeracyjna per TStawkaPodatku FA(3)
            $vatRate = $line->vat_rate ?? $line['vat_rate'] ?? null;
            if ($vatRate !== null && $vatRate !== '') {
                $vatRateFloat = (float)$vatRate;
                $vatRateName  = strtolower(trim((string)$vatRate));
                if ($vatRateFloat >= 22.5) {
                    $p12z = '23';
                } elseif ($vatRateFloat >= 7.5) {
                    $p12z = '8';
                } elseif ($vatRateFloat >= 4.5) {
                    $p12z = '5';
                } elseif ($vatRateFloat > 0) {
                    $p12z = (string)(int)$vatRateFloat;
                } elseif (str_starts_with($vatRateName, 'zw')) {
                    $p12z = 'zw';
                } elseif (str_starts_with($vatRateName, 'oo')) {
                    $p12z = 'oo';
                } elseif (str_contains($vatRateName, 'wdt')) {
                    $p12z = '0 WDT';
                } elseif (str_contains($vatRateName, 'exp') || str_contains($vatRateName, 'eks')) {
                    $p12z = '0 EX';
                } elseif (str_contains($vatRateName, 'spoza')) {
                    $p12z = 'np I';
                } elseif (str_contains($vatRateName, 'ue')) {
                    $p12z = 'np II';
                } elseif (str_contains($vatRateName, 'nie podl')) {
                    $p12z = 'np I';
                } elseif (str_starts_with($vatRateName, 'np')) {
                    $p12z = 'np I';
                } else {
                    $p12z = '0 KR';
                }
                $xml[] = '        <P_12Z>' . $p12z . '</P_12Z>';
            }

            // GTU
            $gtu = $line->gtu_code ?? $line['gtu_code'] ?? null;
            if (!empty($gtu)) {
                $xml[] = '        <GTUZ>' . $this->esc((string)$gtu) . '</GTUZ>';
            }

            // Procedura
            $proc = $line->procedure_marking ?? $line['procedure_marking'] ?? null;
            if (!empty($proc)) {
                $proc = $this->normalizeProcedura((string)$proc);
            }
            if (!empty($proc)) {
                $xml[] = '        <ProceduraZ>' . $this->esc($proc) . '</ProceduraZ>';
            }

            // KwotaAkcyzy
            $excise = $line->excise_amount ?? $line['excise_amount'] ?? null;
            if (!empty($excise)) {
                $xml[] = '        <KwotaAkcyzyZ>' . $this->fmtAmount((float)$excise) . '</KwotaAkcyzyZ>';
            }

            // StanPrzed
            $stanPrzed = $line->is_before_correction ?? $line['is_before_correction'] ?? false;
            if ($stanPrzed) {
                $xml[] = '        <StanPrzedZ>1</StanPrzedZ>';
            }

            $xml[] = '      </ZamowienieWiersz>';
        }

        $xml[] = '    </Zamowienie>';

        return $xml;
    }

    // ======================== PODMIOT UPOWAŻNIONY ========================

    private function buildRecipientXml(Invoice $inv): array
    {
        // Lazy-load jeśli nie załadowane
        $recipient = $inv->invoice_recipient ?? null;
        if ($recipient === null) {
            try {
                $recipient = $this->fetchTable('InvoiceRecipients')
                    ->find()->where(['invoice_id' => $inv->id])->first() ?: null;
                if ($recipient !== null) {
                    $inv->set('invoice_recipient', $recipient);
                }
            } catch (\Throwable) {}
        }

        if ($recipient === null) {
            return [];
        }

        $name = trim((string)($recipient->name ?? ''));
        if ($name === '') {
            return [];
        }

        $xml = [];
        $countryCode = $this->normalizeCountryCode((string)($recipient->country ?? ''));

        $nipParsed  = $this->parseNip((string)($recipient->nip ?? ''));
        $vatEu      = trim((string)($recipient->vat_eu ?? ''));
        $vatPrefix  = trim((string)($recipient->vat_prefix ?? ''));
        $taxIdOther = trim((string)($recipient->tax_id_other ?? ''));
        $taxIdOtherCountry = strtoupper(trim((string)($recipient->tax_id_other_country ?? '')));

        if ($nipParsed['type'] === 'VatUE' && $vatEu === '') {
            $vatPrefix = $vatPrefix !== '' ? $vatPrefix : $nipParsed['prefix'];
            $vatEu     = $nipParsed['value'];
        }

        $xml[] = '  <Podmiot3>';

        $eori = trim((string)($recipient->eori ?? ''));
        if ($eori !== '') {
            $xml[] = '    <NrEORI>' . $this->esc($eori) . '</NrEORI>';
        }

        $xml[] = '    <DaneIdentyfikacyjne>';
        if ($vatEu !== '') {
            $recKodUE = $this->euVatCountryCode($vatPrefix !== '' ? $vatPrefix : ($countryCode !== 'PL' ? $countryCode : '')); // Grecja: GR → EL
            $recNrVatUE = $vatEu;
            foreach (array_values(array_unique(array_filter([$recKodUE, strtoupper($countryCode)]))) as $__pfx) {
                if ($__pfx !== '' && strncasecmp($recNrVatUE, $__pfx, strlen($__pfx)) === 0) {
                    $recNrVatUE = substr($recNrVatUE, strlen($__pfx));
                    break;
                }
            }
            $xml[] = '      <KodUE>' . $this->esc($recKodUE) . '</KodUE>';
            $xml[] = '      <NrVatUE>' . $this->esc($recNrVatUE) . '</NrVatUE>';
        } elseif ($taxIdOther !== '') {
            if ($taxIdOtherCountry !== '') {
                $xml[] = '      <KodKraju>' . $this->esc($taxIdOtherCountry) . '</KodKraju>';
            }
            $xml[] = '      <NrID>' . $this->esc($taxIdOther) . '</NrID>';
        } elseif ($nipParsed['type'] === 'NIP') {
            $xml[] = '      <NIP>' . $this->esc($nipParsed['value']) . '</NIP>';
        } else {
            $xml[] = '      <BrakID>1</BrakID>';
        }
        $xml[] = '      <Nazwa>' . $this->esc($name) . '</Nazwa>';
        $xml[] = '    </DaneIdentyfikacyjne>';

        $xml[] = '    <Adres>';
        $xml[] = '      <KodKraju>' . $this->esc($countryCode) . '</KodKraju>';
        $street = trim((string)($recipient->street ?? ''));
        $adresL1 = $street !== '' ? $street : trim((string)($recipient->city ?? $name));
        $xml[] = '      <AdresL1>' . $this->esc($adresL1 !== '' ? $adresL1 : '-') . '</AdresL1>';
        $adresL2 = trim(((string)($recipient->zip ?? '')) . ' ' . ((string)($recipient->city ?? '')));
        if ($adresL2 !== '') {
            $xml[] = '      <AdresL2>' . $this->esc($adresL2) . '</AdresL2>';
        }
        $gln = trim((string)($recipient->gln ?? ''));
        if ($gln !== '') {
            $xml[] = '      <GLN>' . $this->esc($gln) . '</GLN>';
        }
        $xml[] = '    </Adres>';

        // Rola odbiorcy — wymagana wg XSD (choice: Rola 1-11 | RolaInna+OpisRoli)
        $rola = (int)($recipient->rola ?? 0);
        $rolaOpis = trim((string)($recipient->rola_opis ?? ''));
        if ($rola >= 1 && $rola <= 11) {
            $xml[] = '    <Rola>' . $rola . '</Rola>';
        } else {
            // "Inny podmiot" — RolaInna=1 + OpisRoli
            $xml[] = '    <RolaInna>1</RolaInna>';
            $xml[] = '    <OpisRoli>' . $this->esc($rolaOpis !== '' ? $rolaOpis : 'Odbiorca') . '</OpisRoli>';
        }

        $xml[] = '  </Podmiot3>';

        return $xml;
    }

    private function buildPodmiotUpowaznionyXml(Invoice $inv): array
    {
        $entities = (array)($inv->invoice_authorized_entities ?? []);
        if (empty($entities)) {
            return [];
        }

        $xml = [];
        foreach ($entities as $pu) {
            $xml[] = '  <PodmiotUpowazniony>';

            // NrEORI — opcjonalny
            $eori = trim((string)($pu->nr_eori ?? $pu['nr_eori'] ?? ''));
            if ($eori !== '') {
                $xml[] = '    <NrEORI>' . $this->esc($eori) . '</NrEORI>';
            }

            // DaneIdentyfikacyjne
            $nip  = preg_replace('/\D+/', '', (string)($pu->nip ?? $pu['nip'] ?? ''));
            $name = trim((string)($pu->name ?? $pu['name'] ?? ''));
            if ($this->isValidKsefNip($nip) || $name !== '') {
                $xml[] = '    <DaneIdentyfikacyjne>';
                if ($this->isValidKsefNip($nip)) {
                    $xml[] = '      <NIP>' . $this->esc($nip) . '</NIP>';
                }
                if ($name !== '') {
                    $xml[] = '      <Nazwa>' . $this->esc($name) . '</Nazwa>';
                }
                $xml[] = '    </DaneIdentyfikacyjne>';
            }

            // Adres
            $addrL1 = trim((string)($pu->address_l1 ?? $pu['address_l1'] ?? ''));
            $addrL2 = trim((string)($pu->address_l2 ?? $pu['address_l2'] ?? ''));
            $cc     = strtoupper(trim((string)($pu->country_code ?? $pu['country_code'] ?? 'PL')));
            if ($addrL1 !== '' || $addrL2 !== '') {
                $xml[] = '    <Adres>';
                $xml[] = '      <KodKraju>' . $this->esc($cc) . '</KodKraju>';
                if ($addrL1 !== '') {
                    $xml[] = '      <AdresL1>' . $this->esc($addrL1) . '</AdresL1>';
                }
                if ($addrL2 !== '') {
                    $xml[] = '      <AdresL2>' . $this->esc($addrL2) . '</AdresL2>';
                }
                $gln = trim((string)($pu->gln ?? $pu['gln'] ?? ''));
                if ($gln !== '') {
                    $xml[] = '      <GLN>' . $this->esc($gln) . '</GLN>';
                }
                $xml[] = '    </Adres>';
            }

            // AdresKoresp
            $kL1 = trim((string)($pu->koresp_address_l1 ?? $pu['koresp_address_l1'] ?? ''));
            $kL2 = trim((string)($pu->koresp_address_l2 ?? $pu['koresp_address_l2'] ?? ''));
            if ($kL1 !== '' || $kL2 !== '') {
                $kCC = strtoupper(trim((string)($pu->koresp_country_code ?? $pu['koresp_country_code'] ?? 'PL')));
                $xml[] = '    <AdresKoresp>';
                $xml[] = '      <KodKraju>' . $this->esc($kCC) . '</KodKraju>';
                if ($kL1 !== '') {
                    $xml[] = '      <AdresL1>' . $this->esc($kL1) . '</AdresL1>';
                }
                if ($kL2 !== '') {
                    $xml[] = '      <AdresL2>' . $this->esc($kL2) . '</AdresL2>';
                }
                $xml[] = '    </AdresKoresp>';
            }

            // DaneKontaktowe
            $email = trim((string)($pu->email ?? $pu['email'] ?? ''));
            $phone = trim((string)($pu->phone ?? $pu['phone'] ?? ''));
            if ($email !== '' || $phone !== '') {
                $xml[] = '    <DaneKontaktowe>';
                if ($email !== '') {
                    $xml[] = '      <EmailPU>' . $this->esc($email) . '</EmailPU>';
                }
                if ($phone !== '') {
                    $xml[] = '      <TelefonPU>' . $this->esc($phone) . '</TelefonPU>';
                }
                $xml[] = '    </DaneKontaktowe>';
            }

            // RolaPU
            $rola = (int)($pu->rola ?? $pu['rola'] ?? 0);
            if ($rola > 0) {
                $xml[] = '    <RolaPU>' . $rola . '</RolaPU>';
            }

            $xml[] = '  </PodmiotUpowazniony>';
        }

        return $xml;
    }


    // ======================== STOPKA (Footer) ========================

    private function buildStopkaXml(Invoice $inv, ?object $seller): array
    {
        $xml = [];

        // footer_text: może być JSON array (nowy format) lub zwykły string (stary format)
        $footerLines = [];
        $rawFooter = $inv->footer_text ?? '';
        if (is_string($rawFooter) && str_starts_with(trim($rawFooter), '[')) {
            $decoded = json_decode($rawFooter, true);
            if (is_array($decoded)) {
                $footerLines = array_values(array_filter(array_map('trim', $decoded), fn($v) => $v !== ''));
            }
        } elseif (trim((string)$rawFooter) !== '') {
            $footerLines = [trim((string)$rawFooter)];
        }
        // Max 3 bloki Informacje wg XSD
        $footerLines = array_slice($footerLines, 0, 3);

        // Pobierz rejestry z registers_json — każdy wpis to osobny blok <Rejestry> (maxOccurs=100)
        $rejestrzyXml = [];
        $registersJson = trim((string)($seller?->registers_json ?? ''));
        if ($registersJson !== '') {
            $regs = json_decode($registersJson, true);
            if (is_array($regs)) {
                foreach ($regs as $r) {
                    $krs   = trim((string)($r['krs']   ?? ''));
                    $regon = trim((string)($r['regon'] ?? ''));
                    $bdo   = trim((string)($r['bdo']   ?? ''));
                    $name  = trim((string)($r['name']  ?? ''));

                    // KRS: uzupełnij zerami z lewej do 10 cyfr; odrzuć jeśli > 10 lub nie-cyfry
                    if ($krs !== '' && preg_match('/^\d+$/', $krs)) {
                        $krs = strlen($krs) <= 10 ? str_pad($krs, 10, '0', STR_PAD_LEFT) : '';
                    } elseif ($krs !== '') {
                        $krs = '';
                    }
                    // REGON: pad do 9 cyfr (jeśli ≤ 9) lub 14 (jeśli 10-13); odrzuć jeśli > 14
                    if ($regon !== '' && preg_match('/^\d+$/', $regon)) {
                        $len = strlen($regon);
                        if ($len <= 9) $regon = str_pad($regon, 9, '0', STR_PAD_LEFT);
                        elseif ($len <= 14) $regon = str_pad($regon, 14, '0', STR_PAD_LEFT);
                        else $regon = '';
                    } elseif ($regon !== '') {
                        $regon = '';
                    }
                    // BDO: pad do 7 cyfr (jeśli same cyfry i ≤ 9); odrzuć jeśli > 9 znaków
                    if ($bdo !== '') {
                        if (preg_match('/^\d+$/', $bdo)) {
                            $bdo = strlen($bdo) <= 9 ? str_pad($bdo, 7, '0', STR_PAD_LEFT) : '';
                        } elseif (strlen($bdo) > 9) {
                            $bdo = '';
                        }
                    }

                    $krsOk   = $krs !== '' && preg_match('/^\d{10}$/', $krs);
                    $regonOk = $regon !== '' && (preg_match('/^\d{9}$/', $regon) || preg_match('/^\d{14}$/', $regon));
                    $bdoOk   = $bdo !== '';

                    if (!$krsOk && !$regonOk && !$bdoOk) continue;

                    $block = ['    <Rejestry>'];
                    if ($name !== '') {
                        $block[] = '      <PelnaNazwa>' . $this->esc($name) . '</PelnaNazwa>';
                    }
                    if ($krsOk)   $block[] = '      <KRS>'   . $this->esc($krs)   . '</KRS>';
                    if ($regonOk) $block[] = '      <REGON>' . $this->esc($regon) . '</REGON>';
                    if ($bdoOk)   $block[] = '      <BDO>'   . $this->esc($bdo)   . '</BDO>';
                    $block[] = '    </Rejestry>';
                    $rejestrzyXml[] = $block;
                }
            }
        }

        if (empty($footerLines) && empty($rejestrzyXml)) {
            return $xml;
        }

        $xml[] = '  <Stopka>';
        foreach ($footerLines as $line) {
            $xml[] = '    <Informacje>';
            $xml[] = '      <StopkaFaktury>' . $this->esc($line) . '</StopkaFaktury>';
            $xml[] = '    </Informacje>';
        }
        foreach ($rejestrzyXml as $block) {
            foreach ($block as $line) {
                $xml[] = $line;
            }
        }
        $xml[] = '  </Stopka>';

        return $xml;
    }

    /**
     * Normalizuje oznaczenie procedury do wartości dopuszczalnej przez XSD FA(3).
     * Mapuje stare/skrócone wartości na aktualne i odrzuca nieznane.
     *
     * TOznaczenieProcedury (FaWiersz): WSTO_EE, IED, TT_D, I_42, I_63, B_SPV, B_SPV_DOSTAWA, B_MPV_PROWIZJA
     * TOznaczenieProceduryZ (Zamowienie): WSTO_EE, IED, TT_D, B_SPV, B_SPV_DOSTAWA, B_MPV_PROWIZJA
     */
    private function normalizeProcedura(string $value): string
    {
        // Mapa starych/skróconych wartości na aktualne
        $map = [
            'B_SPV_DO'       => 'B_SPV_DOSTAWA',
            'B_SPV_DOSTAWA'  => 'B_SPV_DOSTAWA',
            'B_MPV_PROWIZJA' => 'B_MPV_PROWIZJA',
            'B_SPV'          => 'B_SPV',
            'WSTO_EE'        => 'WSTO_EE',
            'IED'            => 'IED',
            'TT_D'           => 'TT_D',
            'I_42'           => 'I_42',
            'I_63'           => 'I_63',
        ];
        return $map[strtoupper(trim($value))] ?? '';
    }

    private function resolveRodzajFaktury(Invoice $inv): string
    {
        $type = $inv->type ?? 'vat';

        return match ($type) {
            'advance', 'zaliczkowa'           => 'ZAL',
            'novat'                           => 'VAT',
            'margin'                          => 'VAT',
            'currency'                        => 'VAT',
            'final', 'rozliczeniowa'          => 'ROZ',
            'correction'                      => 'KOR',
            'zal_korekta'                     => 'KOR_ZAL',
            'roz_korekta'                     => 'KOR_ROZ',
            'upr'                             => 'UPR',
            // Typy blokowane przez KSEF_BLOCKED_TYPES — nie powinny tu dotrzeć,
            // ale jako zabezpieczenie zwracamy VAT (buildFa3Xml nie jest wywoływany dla tych typów)
            'proforma'                        => 'VAT',
            'internal'                        => 'VAT',
            'internalEvidence'                => 'VAT',
            'oss'                             => 'VAT',
            default                           => 'VAT',
        };
    }
    private function buildFa3XmlCurrency(\App\Model\Entity\Invoice $inv): string
    {
        return $this->buildFa3XmlBase($inv);
    }

    private function buildFa3XmlCorrection(\App\Model\Entity\Invoice $inv): string
    {
        return $this->buildFa3XmlBase($inv);
    }

    private function buildFa3XmlAdvance(\App\Model\Entity\Invoice $inv): string
    {
        return $this->buildFa3XmlBase($inv);
    }

    private function buildFa3XmlFinal(\App\Model\Entity\Invoice $inv): string
    {
        return $this->buildFa3XmlBase($inv);
    }

    private function buildFa3XmlMargin(\App\Model\Entity\Invoice $inv): string
    {
        return $this->buildFa3XmlBase($inv);
    }

    /**
     * Buduje JSON snapshot wszystkich rejestrów firmy (company_registers).
     * Format: [{"name":"...","krs":"...","regon":"...","bdo":"..."}]
     */
    private function buildRegistersJson(mixed $company): ?string
    {
        if ($company === null) {
            return null;
        }
        $registers = $company->company_registers ?? [];
        $rows = [];
        foreach ($registers as $reg) {
            $name  = trim((string)($reg->name ?? ''));
            $krs   = trim((string)($reg->krs ?? ''));
            $regon = trim((string)($reg->regon ?? ''));
            $bdo   = trim((string)($reg->bdo ?? ''));
            if ($name === '' && $krs === '' && $regon === '' && $bdo === '') {
                continue;
            }
            $rows[] = array_filter([
                'name'  => $name !== '' ? $name : null,
                'krs'   => $krs !== '' ? $krs : null,
                'regon' => $regon !== '' ? $regon : null,
                'bdo'   => $bdo !== '' ? $bdo : null,
            ], fn($v) => $v !== null);
        }
        if (empty($rows)) {
            return null;
        }
        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Zwraca wartość pola rejestrowego z registers_json lub (fallback) z company_registers.
     * Używane przy budowaniu XML FA(3) — zwraca pierwsze niepuste.
     */
    private function resolveCompanyRegisterField(mixed $company, string $field): string
    {
        if ($company === null) {
            return '';
        }
        $registers = $company->company_registers ?? [];
        foreach ($registers as $reg) {
            $val = trim((string)($reg->{$field} ?? ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ADMIN: Support Tickets
    // ═══════════════════════════════════════════════════════════════════════

    public function adminSupport(): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return null;

        $q      = trim((string)$this->request->getQuery('q'));
        $status = $this->request->getQuery('status');
        $type   = $this->request->getQuery('type');

        $SupportTickets = $this->fetchTable('SupportTickets');
        $query = $SupportTickets->find()->orderDesc('SupportTickets.created');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($exp) use ($like) {
                return $exp->or([
                    'SupportTickets.title LIKE'       => $like,
                    'SupportTickets.description LIKE' => $like,
                ]);
            });
        }
        if ($status) { $query->where(['SupportTickets.status' => $status]); }
        if ($type)   { $query->where(['SupportTickets.type'   => $type]); }

        $tickets      = $this->paginate($query, ['limit' => 25]);
        $newCount     = $SupportTickets->find()->where(['status' => 'nowe'])->count();
        $statuses     = \App\Model\Table\SupportTicketsTable::STATUSES;
        $types        = \App\Model\Table\SupportTicketsTable::TYPES;
        $categories   = \App\Model\Table\SupportTicketsTable::CATEGORIES;

        $this->set(compact('tickets', 'newCount', 'statuses', 'types', 'categories'));
        return null;
    }

    public function adminSupportView(int $id): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return null;

        $identity = $this->request->getAttribute('identity');
        $SupportTickets = $this->fetchTable('SupportTickets');

        $ticket = $SupportTickets->find()
            ->where(['SupportTickets.id' => $id])
            ->contain(['SupportTicketReplies', 'Users', 'Companies'])
            ->first();

        if (!$ticket) {
            $this->Flash->error('Zgłoszenie nie istnieje.');
            return $this->redirect(['action' => 'adminSupport']);
        }

        $statuses   = \App\Model\Table\SupportTicketsTable::STATUSES;
        $types      = \App\Model\Table\SupportTicketsTable::TYPES;
        $categories = \App\Model\Table\SupportTicketsTable::CATEGORIES;

        if ($this->request->is('post')) {
            $action = $this->request->getData('_action');

            if ($action === 'reply') {
                // Dodaj odpowiedź admina
                $Replies = $this->fetchTable('SupportTicketReplies');
                $reply   = $Replies->newEmptyEntity();
                $reply   = $Replies->patchEntity($reply, ['message' => $this->request->getData('message')]);
                $reply->support_ticket_id = $ticket->id;
                $reply->user_id           = null;
                $reply->author_name       = 'Administrator';
                $reply->is_admin_reply    = true;

                if ($Replies->save($reply)) {
                    // Automatycznie zmień status na "w_toku" jeśli był "nowe"
                    if ($ticket->status === 'nowe') {
                        $ticket->status = 'w_toku';
                        $SupportTickets->save($ticket);
                    }
                    // Email do użytkownika
                    try {
                        $this->_sendAdminReplyEmail($ticket, $reply);
                    } catch (\Throwable $e) {
                        \Cake\Log\Log::error('[AdminSupport] Email do usera nieudany: ' . $e->getMessage());
                    }
                    $this->Flash->success('Odpowiedź została wysłana.');
                } else {
                    $this->Flash->error('Nie udało się zapisać odpowiedzi.');
                }

            } elseif ($action === 'update') {
                // Zmień status / typ / notatkę
                $ticket = $SupportTickets->patchEntity($ticket, [
                    'status'             => $this->request->getData('status'),
                    'type'               => $this->request->getData('type') ?: null,
                    'admin_note'         => $this->request->getData('admin_note'),
                    'is_for_accountant'  => (bool)$this->request->getData('is_for_accountant'),
                ]);
                if ($SupportTickets->save($ticket)) {
                    $this->Flash->success('Zgłoszenie zaktualizowane.');
                } else {
                    $this->Flash->error('Błąd zapisu.');
                }
            }

            return $this->redirect(['action' => 'adminSupportView', $id]);
        }

        $this->set(compact('ticket', 'statuses', 'types', 'categories'));
        return null;
    }

    /**
     * Duplicate invoice: create a new draft copy with all content
     */
    public function duplicateInvoice($id = null)
    {
        $this->request->allowMethod('post');

        $identity = $this->request->getAttribute('identity');
        $companyId = $identity->company_id ?? null;
        if (empty($companyId)) {
            return $this->response->withStatus(403)->withStringBody('Access denied');
        }

        // Get payment status preference from request
        $paymentStatusPreference = $this->request->getData('paymentStatus') ?? 'unpaid';

        // Load source invoice with all relationships
        $sourceInvoice = $this->Invoices->find()
            ->contain([
                'InvoiceContractors',
                'InvoiceCompanyDetails',
                'InvoiceRecipients',
                'InvoiceContents' => ['Vats'],
                'InvoiceVatContents',
                'InvoiceNewTransports',
                'InvoiceCharges',
                'InvoiceFactorBanks',
                'InvoiceAuthorizedEntities',
                'InvoiceOrderLines',
                'InvoiceAdditionalDescriptions',
            ])
            ->where(['Invoices.id' => $id, 'Invoices.company_id' => $companyId])
            ->first();

        if (!$sourceInvoice) {
            return $this->response->withStatus(404);
        }

        // Check if type is allowed (vat, proforma, currency, margin only)
        $allowedTypes = ['vat', 'proforma', 'currency', 'margin'];
        $invoiceType = strtolower((string)($sourceInvoice->type ?? ''));
        if (!in_array($invoiceType, $allowedTypes, true)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Duplikacja nie jest dostępna dla tego typu faktury.'
                ]));
        }

        try {
            // Create new invoice entity from source
            $newInvoice = $this->Invoices->newEmptyEntity();

            // Copy all safe fields (paymentstate handled separately)
            $copyFields = [
                'company_id', 'invoice_series_id', 'contractor_id',
                'parent_id', 'type', 'currency', 'total', 'netto', 'tax',
                'alreadypaid', 'remaining', 'paymentmethod', 'paymentdate',
                'simplified_invoice', 'is_receipt_invoice', 'is_split_payment',
                'buyer_is_jst', 'buyer_in_vat_group', 'seller_vat_prefix',
                'seller_vat_eu', 'seller_eori', 'buyer_vat_prefix', 'buyer_vat_eu',
                'buyer_eori', 'buyer_tax_id_other', 'buyer_tax_id_other_country',
                'sold_date', 'advance_received_date', 'receipt_date', 'receipt_number',
                'period_from', 'period_to', 'currency_date', 'currency_exchange',
                'company_bank_account_id', 'description', 'margin_type',
                'lang', 'auto_send', 'footer_text', 'place_of_issue', 'issuer',
                'correction_type', 'correction_reason',
                // FA(3) — adnotacje i oznaczenia faktury (m.in. podstawa zwolnienia, supply_goods)
                'annotations', 'annotations_tax_free', 'annotations_tax_free_field',
                'wz_number', 'payment_link',
                'skonto_conditions', 'skonto_amount', 'status_info_podatnika',
                'is_new_transport_wdt', 'p_42_5', 'transaction_conditions_json',
                'order_total_gross',
            ];

            foreach ($copyFields as $field) {
                if ($sourceInvoice->has($field)) {
                    $newInvoice->set($field, $sourceInvoice->get($field));
                }
            }

            // Set paymentstate based on user preference
            if ($paymentStatusPreference === 'unpaid') {
                $newInvoice->set('paymentstate', 'unpaid');
                $newInvoice->set('alreadypaid', 0);
                $newInvoice->set('remaining', $sourceInvoice->total ?? 0);
                // wyzeruj znaczniki rozliczenia
                $newInvoice->set('paid_at', null);
                $newInvoice->set('partial_paid_at', null);
            } else {
                // 'original' - kopiuj stan rozliczenia 1:1
                $newInvoice->set('paymentstate', $sourceInvoice->paymentstate ?? 'unpaid');
                $newInvoice->set('paid_at', $sourceInvoice->paid_at ?? null);
                $newInvoice->set('partial_paid_at', $sourceInvoice->partial_paid_at ?? null);
            }

            // Set workflow to draft and reset identity fields
            $newInvoice->set('workflow_status', 'draft');
            $newInvoice->set('fullnumber', null);

            // Set date to today (issued date)
            $todayStr = (new \DateTime())->format('Y-m-d');
            $todayObj = new \DateTime($todayStr);
            $newInvoice->set('date', $todayObj);

            // number: tak jak przy wystawianiu szkicu — przewidywany kolejny numer w serii
            // (draft jest wykluczony z numeracji; numer zostanie przeliczony przy wysyłce do KSeF).
            // Kolumna 'number' jest NOT NULL, więc nie może być null.
            $draftNumber = 1;
            try {
                $seriesIdForNum = (string)($newInvoice->invoice_series_id ?? '');
                if ($seriesIdForNum !== '') {
                    // pusty id wykluczenia — nowy draft nie jest jeszcze zapisany,
                    // więc liczymy prawdziwy kolejny numer (bez wykluczania źródła)
                    $previewFullnumber = $this->generateDraftNumber(
                        $seriesIdForNum,
                        '',
                        $todayStr,
                        (string)$companyId
                    );
                    $extracted = $this->extractNumberFromFullnumber($previewFullnumber);
                    if ($extracted >= 1) {
                        $draftNumber = $extracted;
                    }
                }
            } catch (\Throwable $e) {
                $draftNumber = 1; // bezpieczny fallback
            }
            $newInvoice->set('number', $draftNumber);

            // Składniki daty (NOT NULL w bazie) — ustaw z dzisiejszej daty
            $newInvoice->set('day', (int)$todayObj->format('d'));
            $newInvoice->set('month', (int)$todayObj->format('m'));
            $newInvoice->set('year', (int)$todayObj->format('Y'));
            $newInvoice->set('day_year', (int)$todayObj->format('z') + 1);

            // Reset KSeF, email, payment tracking fields
            $newInvoice->set('ksef_status', null);
            $newInvoice->set('ksef_number', null);
            $newInvoice->set('ksef_session_reference', null);
            $newInvoice->set('ksef_invoice_reference', null);
            $newInvoice->set('ksef_xml_hash', null);
            $newInvoice->set('ksef_desc', null);
            $newInvoice->set('upo_xml', null);
            $newInvoice->set('upo_downloaded_at', null);
            $newInvoice->set('planned_ksef_send_at', null);
            $newInvoice->set('email_sent_at', null);
            $newInvoice->set('is_sent', false);
            $newInvoice->set('is_api', false);

            // Generate new hash
            $newInvoice->set('hash', md5(uniqid('inv_', true)));

            // Save new invoice
            if (!$this->Invoices->save($newInvoice)) {
                $this->Flash->error('Nie udało się zduplikować faktury.');
                return $this->redirect(['action' => 'view', $id]);
            }

            $newInvoiceId = $newInvoice->id;

            // Copy InvoiceContractors snapshot
            if ($sourceInvoice->invoice_contractor) {
                $newContractor = $this->fetchTable('InvoiceContractors')->newEmptyEntity();
                $contractorData = $sourceInvoice->invoice_contractor->toArray();
                unset($contractorData['id']);
                unset($contractorData['invoice_id']);
                unset($contractorData['created']);
                unset($contractorData['modified']);
                $contractorData['invoice_id'] = $newInvoiceId;
                $newContractor = $this->fetchTable('InvoiceContractors')->patchEntity($newContractor, $contractorData);
                $this->fetchTable('InvoiceContractors')->save($newContractor);
            }

            // Copy InvoiceCompanyDetails snapshot
            if ($sourceInvoice->invoice_company_detail) {
                $newCompanyDetail = $this->fetchTable('InvoiceCompanyDetails')->newEmptyEntity();
                $companyData = $sourceInvoice->invoice_company_detail->toArray();
                unset($companyData['id']);
                unset($companyData['invoice_id']);
                unset($companyData['created']);
                unset($companyData['modified']);
                $companyData['invoice_id'] = $newInvoiceId;
                $newCompanyDetail = $this->fetchTable('InvoiceCompanyDetails')->patchEntity($newCompanyDetail, $companyData);
                $this->fetchTable('InvoiceCompanyDetails')->save($newCompanyDetail);
            }

            // Copy InvoiceRecipients snapshot if present
            if ($sourceInvoice->invoice_recipient) {
                $newRecipient = $this->fetchTable('InvoiceRecipients')->newEmptyEntity();
                $recipientData = $sourceInvoice->invoice_recipient->toArray();
                unset($recipientData['id']);
                unset($recipientData['invoice_id']);
                unset($recipientData['created']);
                unset($recipientData['modified']);
                $recipientData['invoice_id'] = $newInvoiceId;
                $newRecipient = $this->fetchTable('InvoiceRecipients')->patchEntity($newRecipient, $recipientData);
                $this->fetchTable('InvoiceRecipients')->save($newRecipient);
            }

            // Copy InvoiceContents (line items) - replace-all pattern
            $InvoiceContentsTable = $this->fetchTable('InvoiceContents');
            $InvoiceContentsTable->deleteAll(['invoice_id' => $newInvoiceId]);

            if (!empty($sourceInvoice->invoice_contents)) {
                foreach ($sourceInvoice->invoice_contents as $idx => $item) {
                    $newItem = $InvoiceContentsTable->newEmptyEntity();
                    $itemData = $item->toArray();
                    unset($itemData['id']);
                    unset($itemData['invoice_id']);
                    unset($itemData['created']);
                    unset($itemData['modified']);
                    $itemData['invoice_id'] = $newInvoiceId;
                    $itemData['sort_order'] = $idx;
                    $newItem = $InvoiceContentsTable->patchEntity($newItem, $itemData);
                    $InvoiceContentsTable->save($newItem);
                }
            }

            // Copy InvoiceVatContents
            $InvoiceVatTable = $this->fetchTable('InvoiceVatContents');
            $InvoiceVatTable->deleteAll(['invoice_id' => $newInvoiceId]);

            if (!empty($sourceInvoice->invoice_vat_contents)) {
                foreach ($sourceInvoice->invoice_vat_contents as $vat) {
                    $newVat = $InvoiceVatTable->newEmptyEntity();
                    $vatData = $vat->toArray();
                    unset($vatData['id']);
                    unset($vatData['invoice_id']);
                    unset($vatData['created']);
                    unset($vatData['modified']);
                    $vatData['invoice_id'] = $newInvoiceId;
                    $newVat = $InvoiceVatTable->patchEntity($newVat, $vatData);
                    $InvoiceVatTable->save($newVat);
                }
            }

            // Copy FA(3) relational data - using existing pattern
            $fa3Tables = [
                'InvoiceNewTransports',
                'InvoiceCharges',
                'InvoiceFactorBanks',
                'InvoiceAuthorizedEntities',
                'InvoiceOrderLines',
                'InvoiceAdditionalDescriptions',
            ];

            foreach ($fa3Tables as $tableName) {
                $Table = $this->fetchTable($tableName);
                $Table->deleteAll(['invoice_id' => $newInvoiceId]);

                // Właściwość encji dla hasMany jest w snake_case (np. invoice_new_transports),
                // NIE camelCase — wcześniejsze lcfirst(camelize()) dawało zły klucz i nic nie kopiowało.
                $sourceRelation = \Cake\Utility\Inflector::underscore($tableName);
                if ($sourceInvoice->has($sourceRelation) && !empty($sourceInvoice->get($sourceRelation))) {
                    foreach ($sourceInvoice->get($sourceRelation) as $relation) {
                        $newRel = $Table->newEmptyEntity();
                        $relData = $relation->toArray();
                        unset($relData['id']);
                        unset($relData['invoice_id']);
                        unset($relData['created']);
                        unset($relData['modified']);
                        $relData['invoice_id'] = $newInvoiceId;
                        $newRel = $Table->patchEntity($newRel, $relData);
                        $Table->save($newRel);
                    }
                }
            }

            // Success! Return JSON response
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'duplicatedInvoiceId' => $newInvoiceId,
                    'message' => 'Faktura zduplikowana pomyślnie'
                ]));

        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Invoice duplication error: ' . $e->getMessage(), ['invoice_duplicate']);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Błąd przy duplikacji faktury: ' . $e->getMessage()
                ]));
        }
    }

    public function dashboard(): void
    {
        // Pobranie danych użytkownika
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity->company_id ?? null;

        if (empty($companyId)) {
            $this->Flash->error('Brak dostępu do firmy.');
            return;
        }

        // Filtry: dateFrom, dateTo, currency, amount_type
        $dateFrom = $this->request->getQuery('dateFrom');
        $dateTo = $this->request->getQuery('dateTo');
        $selectedCurrency = $this->request->getQuery('currency');
        $amountType = $this->request->getQuery('amount_type') === 'netto' ? 'netto' : 'brutto';

        // Defaults: ostatni rok
        $today = new \DateTime();
        $yearStart = (new \DateTime())->setDate((int)$today->format('Y'), 1, 1);

        if (!empty($dateFrom)) {
            $dateFrom = new \DateTime($dateFrom);
        } else {
            $dateFrom = $yearStart;
        }

        if (!empty($dateTo)) {
            $dateTo = new \DateTime($dateTo);
        } else {
            $dateTo = $today;
        }

        // Helper functions
        $sum = function(array $where, string $col = 'Invoices.total'): float {
            $q = $this->Invoices->find();
            $q->select(['s' => $q->func()->sum($col)])
              ->where($where)
              ->enableHydration(false);
            $row = $q->first();
            return (float)($row['s'] ?? 0);
        };

        $cnt = function(array $where): int {
            return (int)$this->Invoices->find()->where($where)->count();
        };

        // Helper: wyklucz faktury robocze (draft)
        $notDraftWhere = ['OR' => [
            ['Invoices.workflow_status IS' => null],
            ['Invoices.workflow_status !=' => 'draft']
        ]];

        // Helper: filtruj po walucie jeśli wybrany
        $currencyWhere = [];
        if (!empty($selectedCurrency)) {
            $currencyWhere = ['Invoices.currency' => $selectedCurrency];
        }

        // ===== 1. REVENUE TREND (po miesiącach) =====
        $monthlyRevenue = [];
        $period = new \DatePeriod($dateFrom, new \DateInterval('P1M'), $dateTo);

        foreach ($period as $date) {
            $monthStart = (clone $date)->setDate((int)$date->format('Y'), (int)$date->format('m'), 1);
            $monthEnd = (clone $monthStart)->modify('last day of this month');

            $invoices = $this->Invoices->find()
                ->select(['total'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $monthStart->format('Y-m-d'),
                    'Invoices.date <=' => $monthEnd->format('Y-m-d'),
                    'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                    $notDraftWhere,
                    $currencyWhere,
                ])
                ->enableHydration(false)
                ->all();

            $monthlyRevenue[$date->format('Y-m')] = array_sum(array_column($invoices->toArray(), 'total'));
        }

        // NETTO monthly revenue
        $monthlyRevenueNetto = [];
        $period = new \DatePeriod($dateFrom, new \DateInterval('P1M'), $dateTo->modify('+1 day'));
        foreach ($period as $date) {
            $monthStart = (clone $date)->setDate((int)$date->format('Y'), (int)$date->format('m'), 1);
            $monthEnd = (clone $monthStart)->modify('last day of this month');

            $invoicesNetto = $this->Invoices->find()
                ->select(['netto'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $monthStart->format('Y-m-d'),
                    'Invoices.date <=' => $monthEnd->format('Y-m-d'),
                    'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                    $notDraftWhere,
                    $currencyWhere,
                ])
                ->enableHydration(false)
                ->all();

            $monthlyRevenueNetto[$date->format('Y-m')] = array_sum(array_column($invoicesNetto->toArray(), 'netto'));
        }

        // ===== 2. PAYMENT STATUS (pie chart) =====
        $paymentStatus = [];
        foreach (['paid', 'unpaid', 'partial', 'overdue'] as $state) {
            $count = $this->Invoices->find()
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.paymentstate' => $state,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                    $notDraftWhere,
                    $currencyWhere,
                ])
                ->count();

            $total = $this->Invoices->find()
                ->select(['total'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.paymentstate' => $state,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                    $notDraftWhere,
                ])
                ->enableHydration(false)
                ->all();

            $paymentStatus[$state] = [
                'count' => $count,
                'total' => array_sum(array_column($total->toArray(), 'total')),
            ];
        }

        // ===== 3. REVENUE BY CURRENCY (bar chart) =====
        $currencyData = $this->Invoices->find()
            ->select(['currency', 'total', 'netto'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
                $currencyWhere,
            ])
            ->enableHydration(false)
            ->all()
            ->groupBy('currency')
            ->map(function($group) {
                return [
                    'brutto' => array_sum(array_column($group, 'total')),
                    'netto' => array_sum(array_column($group, 'netto')),
                ];
            });

        // ===== 4. TOP CONTRACTORS (bar chart) =====
        $topContractorsRaw = $this->Invoices
            ->find('topContractors', [
                'companyId' => $companyId,
                'dateFrom' => $dateFrom->format('Y-m-d'),
                'dateTo' => $dateTo->format('Y-m-d'),
            ])
            ->all()
            ->groupBy('contractor_name')
            ->map(function($group) {
                return array_sum(array_column($group, 'total'));
            })
            ->toArray();

        // Sortuj descending i weź top 10
        arsort($topContractorsRaw);
        $topContractors = array_slice($topContractorsRaw, 0, 10, true);

        // ===== 5. KPI CARDS =====
        $allInvoices = $this->Invoices->find()
            ->select(['total', 'remaining', 'paymentstate'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
                $currencyWhere,
            ])
            ->enableHydration(false)
            ->all();

        // BRUTTO values
        $totalRevenue = array_sum(array_column($allInvoices->toArray(), 'total'));
        $invoiceCount = $allInvoices->count();
        $avgInvoiceValue = $invoiceCount > 0 ? $totalRevenue / $invoiceCount : 0;

        $paidTotal = array_sum(array_map(function($inv) {
            return $inv['paymentstate'] === 'paid' ? $inv['total'] : 0;
        }, $allInvoices->toArray()));

        $paymentPercent = $totalRevenue > 0 ? round(($paidTotal / $totalRevenue) * 100, 1) : 0;

        // NETTO values (same data but with 'netto' field)
        $nettoInvoices = $this->Invoices->find()
            ->select(['netto', 'remaining', 'paymentstate'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
                $currencyWhere,
            ])
            ->enableHydration(false)
            ->all();

        $totalRevenueNetto = array_sum(array_column($nettoInvoices->toArray(), 'netto'));
        $avgInvoiceValueNetto = $invoiceCount > 0 ? $totalRevenueNetto / $invoiceCount : 0;

        $paidTotalNetto = array_sum(array_map(function($inv) {
            return $inv['paymentstate'] === 'paid' ? $inv['netto'] : 0;
        }, $nettoInvoices->toArray()));

        $paymentPercentNetto = $totalRevenueNetto > 0 ? round(($paidTotalNetto / $totalRevenueNetto) * 100, 1) : 0;

        // ===== 6. BIGGEST INVOICE =====
        $biggestInvoice = $this->Invoices->find()
            ->select(['id', 'fullnumber', 'date', 'total', 'currency'])
            ->contain(['InvoiceContractors'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
            ])
            ->order(['total' => 'DESC'])
            ->first();

        // ===== 7. AVERAGE PAYMENT TIME (dni do zapłaty) =====
        $paidInvoices = $this->Invoices->find()
            ->select(['date', 'paymentdate'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.paymentstate' => 'paid',
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                'Invoices.paymentdate IS NOT' => null,
                $notDraftWhere,
            ])
            ->enableHydration(false)
            ->all();

        $avgPaymentDays = 0;
        if ($paidInvoices->count() > 0) {
            $totalDays = 0;
            foreach ($paidInvoices as $inv) {
                $invoiceDate = new \DateTime((string)$inv['date']);
                $paymentDate = new \DateTime((string)$inv['paymentdate']);
                $totalDays += $paymentDate->diff($invoiceDate)->days;
            }
            $avgPaymentDays = round($totalDays / $paidInvoices->count(), 1);
        }

        // ===== 8. INVOICE TYPE BREAKDOWN =====
        $invoiceTypes = [];
        $types = ['vat', 'proforma', 'advance', 'currency', 'margin', 'novat'];
        foreach ($types as $type) {
            $count = $this->Invoices->find()
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.type' => $type,
                    $notDraftWhere,
                ])
                ->count();

            $total = $this->Invoices->find()
                ->select(['total'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.type' => $type,
                    $notDraftWhere,
                ])
                ->enableHydration(false)
                ->all();

            if ($count > 0) {
                $invoiceTypes[$type] = [
                    'type' => $type,
                    'count' => $count,
                    'total' => array_sum(array_column($total->toArray(), 'total')),
                ];
            }
        }

        // ===== 9. PAYMENT METHOD BREAKDOWN =====
        $paymentMethods = [];
        $methods = ['transfer', 'cash', 'card', 'other'];
        foreach ($methods as $method) {
            $count = $this->Invoices->find()
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.paymentmethod' => $method,
                    $notDraftWhere,
                ])
                ->count();

            $total = $this->Invoices->find()
                ->select(['total'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.paymentmethod' => $method,
                    $notDraftWhere,
                ])
                ->enableHydration(false)
                ->all();

            if ($count > 0) {
                $paymentMethods[$method] = [
                    'method' => $method,
                    'count' => $count,
                    'total' => array_sum(array_column($total->toArray(), 'total')),
                ];
            }
        }

        // ===== 10. DAYS OVERDUE DISTRIBUTION =====
        $overdueInvoices = $this->Invoices->find()
            ->select(['paymentdate'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.paymentstate' => 'overdue',
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
            ])
            ->enableHydration(false)
            ->all();

        $overdueDistribution = ['0-7' => 0, '8-14' => 0, '15-30' => 0, '30+' => 0];
        $today = new \DateTime();
        foreach ($overdueInvoices as $inv) {
            if ($inv['paymentdate']) {
                $dueDate = new \DateTime((string)$inv['paymentdate']);
                $daysOverdue = $today->diff($dueDate)->days;

                if ($daysOverdue <= 7) {
                    $overdueDistribution['0-7']++;
                } elseif ($daysOverdue <= 14) {
                    $overdueDistribution['8-14']++;
                } elseif ($daysOverdue <= 30) {
                    $overdueDistribution['15-30']++;
                } else {
                    $overdueDistribution['30+']++;
                }
            }
        }

        // ===== 11. YEAR-OVER-YEAR COMPARISON =====
        $currentYear = (int)$today->format('Y');
        $currentYearTotal = $sum([
            'Invoices.company_id' => $companyId,
            'Invoices.date >=' => new \DateTime($currentYear . '-01-01'),
            'Invoices.date <=' => $today,
            'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
        ]);

        $lastYearStart = new \DateTime(($currentYear - 1) . '-01-01');
        $lastYearEnd = new \DateTime(($currentYear - 1) . '-12-31');
        $lastYearTotal = $sum([
            'Invoices.company_id' => $companyId,
            'Invoices.date >=' => $lastYearStart,
            'Invoices.date <=' => $lastYearEnd,
            'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
        ]);

        $yoyGrowth = $lastYearTotal > 0
            ? round((($currentYearTotal - $lastYearTotal) / $lastYearTotal) * 100, 1)
            : 0;

        // ===== 12. AVERAGE CONTRACTOR VALUE =====
        $avgContractorValue = [];
        $ContractorInvoices = $this->Invoices->find()
            ->select(['Invoices.total'])
            ->contain(['InvoiceContractors'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                'Invoices.date <=' => $dateTo->format('Y-m-d'),
                'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                $notDraftWhere,
            ])
            ->enableHydration(false)
            ->all()
            ->groupBy(function($inv) {
                return $inv['invoice_contractors']['name'] ?? 'Unknown';
            })
            ->map(function($group) {
                return [
                    'count' => count($group),
                    'total' => array_sum(array_column($group, 'total')),
                    'avg' => array_sum(array_column($group, 'total')) / count($group),
                ];
            })
            ->toArray();

        // ===== 6. KPI CARDS PER CURRENCY =====
        $currencyMetrics = [];
        $currencies = $this->Invoices->find()
            ->select(['currency'])
            ->where(['Invoices.company_id' => $companyId])
            ->distinct(['currency'])
            ->enableHydration(false)
            ->all()
            ->extract('currency')
            ->toArray();

        foreach ($currencies as $curr) {
            $currInvoices = $this->Invoices->find()
                ->select(['total', 'netto', 'paymentstate'])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.currency' => $curr,
                    'Invoices.date >=' => $dateFrom->format('Y-m-d'),
                    'Invoices.date <=' => $dateTo->format('Y-m-d'),
                    'Invoices.type NOT IN' => ['correction', 'proforma', 'advance'],
                    $notDraftWhere,
                ])
                ->enableHydration(false)
                ->all();

            $currTotal = array_sum(array_column($currInvoices->toArray(), 'total'));
            $currCount = $currInvoices->count();
            $currAvg = $currCount > 0 ? $currTotal / $currCount : 0;
            $currPaid = array_sum(array_map(function($inv) {
                return $inv['paymentstate'] === 'paid' ? $inv['total'] : 0;
            }, $currInvoices->toArray()));
            $currPaidPercent = $currTotal > 0 ? round(($currPaid / $currTotal) * 100, 1) : 0;
            $currPending = $currTotal - $currPaid;

            $currencyMetrics[$curr] = [
                'currency' => $curr,
                'total' => $currTotal,
                'count' => $currCount,
                'avg' => $currAvg,
                'paid' => $currPaid,
                'paid_percent' => $currPaidPercent,
                'pending' => $currPending,
            ];
        }

        // Prepare dual-amount data for frontend switching
        $dualAmountData = json_encode([
            'brutto' => [
                'monthlyRevenue' => $monthlyRevenue,
                'totalRevenue' => $totalRevenue,
                'avgInvoiceValue' => $avgInvoiceValue,
                'paymentPercent' => $paymentPercent,
            ],
            'netto' => [
                'monthlyRevenue' => $monthlyRevenueNetto,
                'totalRevenue' => $totalRevenueNetto,
                'avgInvoiceValue' => $avgInvoiceValueNetto,
                'paymentPercent' => $paymentPercentNetto,
            ]
        ]);

        // Przekaż dane do widoku
        $this->set(compact(
            'monthlyRevenue',
            'monthlyRevenueNetto',
            'paymentStatus',
            'currencyData',
            'topContractors',
            'totalRevenue',
            'totalRevenueNetto',
            'invoiceCount',
            'avgInvoiceValue',
            'avgInvoiceValueNetto',
            'paymentPercent',
            'paymentPercentNetto',
            'currencyMetrics',
            'currencies',
            'biggestInvoice',
            'avgPaymentDays',
            'invoiceTypes',
            'paymentMethods',
            'overdueDistribution',
            'currentYearTotal',
            'lastYearTotal',
            'yoyGrowth',
            'avgContractorValue',
            'selectedCurrency',
            'amountType',
            'dualAmountData',
            'dateFrom',
            'dateTo'
        ));
    }

    private function _sendAdminReplyEmail(\App\Model\Entity\SupportTicket $ticket, \App\Model\Entity\SupportTicketReply $reply): void
    {
        // Pobierz email użytkownika
        $Users = $this->fetchTable('Users');
        $user  = $Users->find()->where(['id' => $ticket->user_id])->select(['email'])->first();
        $email = $user?->email ?? null;
        if (empty($email)) return;

        $mailer = new \Cake\Mailer\Mailer('default');
        $mailer->setTo($email)
            ->setSubject('Odpowiedź na Twoje zgłoszenie #' . $ticket->id . ' – ' . $ticket->title)
            ->setEmailFormat('html')
            ->viewBuilder()->setLayout('default')->setTemplate('support_admin_reply');
        $mailer->setViewVars([
            'ticket' => $ticket,
            'reply'  => $reply,
        ]);
        $mailer->deliver();
    }
}

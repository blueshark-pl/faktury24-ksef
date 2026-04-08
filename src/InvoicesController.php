<?php
/**
 * @deprecated LEGACY — ten plik NIE jest używany przez aplikację.
 *
 * Aktywna wersja kontrolera: src/Controller/InvoicesController.php
 *
 * Ten plik to starszy snapshot (brakuje wielu metod: drafts, scheduleDraft,
 * sendDraftNow, editVat/editCurrency/..., runPlannedDrafts itp.).
 * CakePHP autoloader nie ładuje tego pliku bo ścieżka nie odpowiada
 * konwencji PSR-4 (App\Controller → src/Controller/).
 *
 * Można bezpiecznie usunąć po przeglądzie historii git.
 * Commit: duplikat wykryty 2026-03-18, README TODO "Invoices".
 */
declare(strict_types=1);

namespace App\Controller;
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

/**
 * Invoices Controller
 *
 * @property \App\Model\Table\InvoicesTable $Invoices
 */
class InvoicesController extends AppController
{
    /**
     * Validate invoice form data via AJAX (no save)
     * POST /invoices/validate-ajax
     */
    public function validateAjax(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);

        // Force JSON response (builder optional)
        $this->viewBuilder()->setClassName('Json');

        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id'); // not used here, but kept for parity

    $data = (array)$this->request->getData();
        $errors = [];
    $isNoVat = strtolower((string)($data['kind'] ?? '')) === 'novat';

        // Basic fields
        $date = $data['date'] ?? null;
        if (!$date) {
            $errors['date'] = 'Podaj datę wystawienia.';
        }

        // Contractor: required minimal data (either selected contractor or snapshot filled)
        $contractorId = $data['contractor_id'] ?? null;
        $ctrName = $data['invoice_contractor']['name'] ?? null;
        if (!$contractorId && !$ctrName) {
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
                if ($qty <= 0) {
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
                if ($qty <= 0) {
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

    $q        = trim((string)$this->request->getQuery('q'));
    $state    = $this->request->getQuery('state');
    $from     = $this->request->getQuery('from');
    $to       = $this->request->getQuery('to');
    $currency = $this->request->getQuery('currency');

        $query = $this->Invoices->find()
            ->contain(['InvoiceContractors' => function($q){ return $q->select(['invoice_id','name','nip']); }])
      ->where(['Invoices.company_id' => $companyId]);

    if ($q !== '') {
      $query->where(function($exp) use ($q) {
        return $exp->or_([
          'Invoices.fullnumber LIKE' => "%$q%",
          'InvoiceContractors.name LIKE' => "%$q%",
          'InvoiceContractors.nip LIKE' => "%$q%",
        ]);
      });
    }
    if ($state) {
      $query->where(['Invoices.paymentstate' => $state]);
    }
    if ($currency) {
      $query->where(['Invoices.currency' => strtoupper($currency)]);
    }
    if ($from) { $query->where(['Invoices.date >=' => $from]); }
    if ($to)   { $query->where(['Invoices.date <=' => $to]); }

    $query->order(['Invoices.date' => 'DESC', 'Invoices.id' => 'DESC']);

        $invoices = $this->paginate($query, ['limit' => 20]);

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

    $base = $this->Invoices->find()->where(['company_id' => $companyId]);

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

// STATYSTYKI
$stats = [
    'currency'         => 'PLN',

    // rok bieżący
    'year_total'       => $sum([
                            'Invoices.company_id' => $companyId,
                            'Invoices.date >='    => $yearStart,
                            'Invoices.date <='    => $today,
                         ]),
    'year_count'       => $cnt([
                            'Invoices.company_id' => $companyId,
                            'Invoices.date >='    => $yearStart,
                            'Invoices.date <='    => $today,
                         ]),
    'year_paid'        => $sum([
                            'Invoices.company_id' => $companyId,
                            'Invoices.paymentstate'=> 'paid',
                            'Invoices.date >='    => $yearStart,
                            'Invoices.date <='    => $today,
                         ]),

    // paid
    'paid_total'       => $sum(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid']),
    'paid_count'       => $cnt(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid']),
    'paid_avg'         => $avg(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'paid']),

    // pending (unpaid/partial)
    'pending_count'    => $cnt(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']]),
    'pending_total'    => $sum(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']]),
    'remaining_total'  => $sum(['Invoices.company_id' => $companyId, 'Invoices.paymentstate IN' => ['unpaid','partial']], 'Invoices.remaining'),

    // overdue
    'overdue_count'    => $cnt(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'overdue']),
    'overdue_total'    => $sum(['Invoices.company_id' => $companyId, 'Invoices.paymentstate' => 'overdue']),

    // bieżący miesiąc
    'month_paid_count' => $cnt([
                            'Invoices.company_id' => $companyId,
                            'Invoices.paymentstate'=> 'paid',
                            'Invoices.date >='    => $monthStart,
                         ]),

    // opcjonalnie: maks. opóźnienie w dniach (jeśli chcesz policzyć – tu zostaw 0 albo zrób osobne zapytanie)
    'overdue_max_days' => 0,
];

    $this->set(compact('invoices','stats','advanceCounts','finalByProforma','advancesByProforma'));
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
            'ChildInvoices'
        ]);
        $this->set(compact('invoice'));
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
private function handleAdd(string $kind, bool $noVat = false): ?\Cake\Http\Response
{
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
                    $original = $Invoices->find()
                        ->contain(['InvoiceContractors','InvoiceContents' => ['Vats']])
                        ->where(['Invoices.company_id' => $companyId, 'Invoices.id' => $origId])
                        ->first();
                    if ($original) {
                        // Preselect series same as original if not set
                        if (empty($invoice->series) && !empty($original->series)) {
                            $invoice->set('series', (string)$original->series);
                        }
                        // Pass original to the view to prefill form and items
                        $this->set('original', $original);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Default series for proforma: prefer default series whose name contains 'proforma'
        if ($kind === 'proforma') {
            try {
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                $ser = $InvoiceSeries->find()
                    ->where(['company_id' => $companyId, 'is_default' => 1])
                    ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%proforma%'); })
                    ->first();
                if (!$ser) {
                    $ser = $InvoiceSeries->find()->where(['company_id' => $companyId, 'is_default' => 1])->first();
                }
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId])
                        ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%proforma%'); })
                        ->first();
                }
                if ($ser && empty($invoice->series)) {
                    $invoice->set('series', (string)$ser->name);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        // Default series for currency invoices: prefer type='currency' and is_default
        if ($kind === 'currency') {
            try {
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                // 1) Prefer default currency series
                $ser = $InvoiceSeries->find()
                    ->where(['company_id' => $companyId, 'type' => 'currency', 'is_default' => 1])
                    ->first();
                // 2) Any currency series
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'type' => 'currency'])
                        ->orderAsc('name')
                        ->first();
                }
                // 3) Fallback to global default
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'is_default' => 1])
                        ->first();
                }
                // 4) Last-resort: name hint contains "walut"
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId])
                        ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%walut%'); })
                        ->first();
                }
                if ($ser && empty($invoice->series)) {
                    $invoice->set('series', (string)$ser->name);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        // Default series for VAT invoices (and corrections treated like VAT): prefer type='vat' and is_default
        if ($kind === 'vat' || $kind === 'correction') {
            try {
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                // 1) Prefer default VAT series
                $ser = $InvoiceSeries->find()
                    ->where(['company_id' => $companyId, 'type' => 'vat', 'is_default' => 1])
                    ->first();
                // 2) Any VAT series
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'type' => 'vat'])
                        ->orderAsc('name')
                        ->first();
                }
                // 3) Fallback to global default
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'is_default' => 1])
                        ->first();
                }
                // 4) Last-resort: name hint contains 'vat' (case-insensitive)
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId])
                        ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%vat%'); })
                        ->first();
                }
                if ($ser && empty($invoice->series)) {
                    $invoice->set('series', (string)$ser->name);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        // Default series for no-VAT invoices: prefer type='novat' and is_default
        if ($kind === 'novat') {
            try {
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                // 1) Prefer default no-VAT series
                $ser = $InvoiceSeries->find()
                    ->where(['company_id' => $companyId, 'type' => 'novat', 'is_default' => 1])
                    ->first();
                // 2) Any no-VAT series
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'type' => 'novat'])
                        ->orderAsc('name')
                        ->first();
                }
                // 3) Fallback to global default
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'is_default' => 1])
                        ->first();
                }
                // 4) Last-resort: try to guess by name
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId])
                        ->andWhere(function($exp){
                            return $exp->or_([
                                $exp->like('LOWER(name)', '%no vat%'),
                                $exp->like('LOWER(name)', '%bez vat%'),
                                $exp->like('LOWER(name)', '%bezvat%'),
                                $exp->like('LOWER(name)', '%novat%'),
                            ]);
                        })
                        ->first();
                }
                if ($ser && empty($invoice->series)) {
                    $invoice->set('series', (string)$ser->name);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        // Default series for advance: prefer default series whose name contains 'zaliczka' or 'advance'
        if ($kind === 'advance') {
            try {
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                $ser = $InvoiceSeries->find()
                    ->where(['company_id' => $companyId, 'is_default' => 1])
                    ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%zaliczka%'); })
                    ->first();
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId, 'is_default' => 1])
                        ->andWhere(function($exp){ return $exp->like('LOWER(name)', '%advance%'); })
                        ->first();
                }
                if (!$ser) {
                    $ser = $InvoiceSeries->find()->where(['company_id' => $companyId, 'is_default' => 1])->first();
                }
                if (!$ser) {
                    $ser = $InvoiceSeries->find()
                        ->where(['company_id' => $companyId])
                        ->andWhere(function($exp){ return $exp->or_([
                            $exp->like('LOWER(name)', '%zaliczka%'),
                            $exp->like('LOWER(name)', '%advance%'),
                        ]); })
                        ->first();
                }
                if ($ser && empty($invoice->series)) {
                    $invoice->set('series', (string)$ser->name);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
    }

    // słowniki VAT – dla noVat nie musimy ładować stawek, ale jeśli chcesz mieć np. "ZW/NP", możesz załadować i ukryć w UI
    $Vats        = $this->fetchTable('Vats');
    $vatRows     = $noVat ? [] : $Vats->find()->select(['id','name','rate'])->order(['rate' => 'DESC'])->all();
    $vats        = $noVat ? [] : $vatRows->combine('id', fn($v) => sprintf('%s (%s%%)', (string)$v->name, rtrim(rtrim((string)$v->rate,'0'),'.')))->toArray();
    $vatRatesMap = $noVat ? [] : $vatRows->combine('id', 'rate')->toArray();

        if ($this->request->is('post')) {
        $data = $this->request->getData();

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

    if ($kind === 'margin') {
            // Procedura marży: pozycje zawierają WARTOŚĆ BRUTTO (sprzedaż) oraz CENA NABYCIA (BRUTTO) tylko do wyliczeń
            $totalSales = 0.0; $totalPurchase = 0.0;
            foreach ($items as $idx => $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') continue;

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
                ];
            }

            // Stawka VAT z zakładki Księgowe (ukryta) – 23% domyślnie; dla sztuki 8%
            $rate = (float)($data['margin_vat_rate'] ?? 23);
            $marginGross = max(0.0, $totalSales - $totalPurchase);
            $sumTax   = $rate > 0 ? round($marginGross * ($rate / (100.0 + $rate)), 2) : 0.0; // VAT tylko od marży
            $sumGross = round($totalSales, 2);
            $sumNet   = round($sumGross - $sumTax, 2); // w ujęciu księgowym: netto = total - VAT

            // Opcjonalna adnotacja o procedurze marży do opisu faktury
            $marginType = (string)($data['margin_type'] ?? '');
            $map = [
                'used_goods'   => 'towary używane',
                'art'          => 'dzieła sztuki',
                'collectibles' => 'przedmioty kolekcjonerskie',
                'travel'       => 'usługi turystyki',
            ];
            if ($marginType !== '') {
                $note = 'Procedura marży – ' . ($map[$marginType] ?? $marginType) . '.';
                $existing = trim((string)($data['description'] ?? ''));
                if ($existing === '') { $data['description'] = $note; }
                elseif (!str_contains($existing, 'Procedura marży')) { $data['description'] = $existing . "\n" . $note; }
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
            if ($advanceGross <= 0) {
                $this->Flash->error('Kwota zaliczki musi być większa od zera.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            $Proformas = $this->fetchTable('Invoices');
            $proforma  = $Proformas->find()
                ->contain(['InvoiceContractors'])
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
            // Avoid undefined key notice: only set snapshot if missing or not an array
            if (empty($data['invoice_contractor']) || !is_array($data['invoice_contractor'])) {
                $data['invoice_contractor'] = [
                    'name' => (string)($proforma->invoice_contractor->name ?? ''),
                    'nip' => (string)($proforma->invoice_contractor->nip ?? ''),
                    'street' => (string)($proforma->invoice_contractor->street ?? ''),
                    'zip' => (string)($proforma->invoice_contractor->zip ?? ''),
                    'city' => (string)($proforma->invoice_contractor->city ?? ''),
                    'country' => (string)($proforma->invoice_contractor->country ?? 'PL'),
                    'account_number' => (string)($proforma->invoice_contractor->account_number ?? ''),
                ];
            }
            $vatCodeId    = $data['advance_vat_code_id'] ?? null;
            if (!$noVat && !$vatCodeId) {
                $this->Flash->error('Wybierz stawkę VAT dla zaliczki.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            $rate         = $noVat ? 0.0 : (float)($vatRatesMap[$vatCodeId] ?? 0);
            // compute net from gross using rate
            $netto        = $rate > 0 ? round($advanceGross / (1 + $rate/100), 2) : round($advanceGross, 2);
            $tax          = round($advanceGross - $netto, 2);
            $brutto       = round($advanceGross, 2);

            // Validate against remaining (prevent overpayment)
            $isFinalExplicit = !empty($data['is_final']) && (int)$data['is_final'] === 1;
            // Auto-classify as final if amount equals remaining (tolerance)
            $shouldBeFinalByAmount = ($remainingToSettle > 0.0) && (abs($brutto - $remainingToSettle) <= 0.01) && ($brutto > 0.0);
            $isFinal = $isFinalExplicit || $shouldBeFinalByAmount;

            if ($hasFinal) {
                $this->Flash->error('Faktura końcowa została już wystawiona dla tej oferty. Nie można wystawiać kolejnych dokumentów do tej oferty.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            if ($remainingToSettle <= 0.0) {
                $this->Flash->error('Proforma została już w całości rozliczona.');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            if ($brutto - $remainingToSettle > 0.01) {
                $this->Flash->error('Kwota zaliczki przekracza pozostałą do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . ').');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }
            if ($isFinal && !$shouldBeFinalByAmount) {
                $this->Flash->error('Dla faktury końcowej kwota musi równać się pozostałej do rozliczenia (' . number_format($remainingToSettle, 2, ',', ' ') . ').');
                $this->set(compact('invoice','vats','vatRatesMap','kind'));
                $this->render('add_advance');
                return null;
            }

            $sumNet   = $netto;
            $sumTax   = $tax;
            $sumGross = $brutto;

            // Ustal prefiks nazwy pozycji na podstawie wyliczonej flagi $isFinal (a nie tylko danych z formularza)
            $lineNamePrefix = $isFinal ? 'Faktura końcowa do oferty ' : 'Zaliczka do oferty ';
            $contents[] = [
                'vat_code_id'      => $noVat ? null : $vatCodeId,
                'name'             => $lineNamePrefix . (string)($proforma->fullnumber ?? $proforma->id),
                'product_desc'     => '',
                'quantity'         => 1,
                'unit'             => 'szt.',
                'price'            => $netto,
                'discount_percent' => 0,
                'netto'            => $netto,
                'brutto'           => $brutto,
            ];

            $bucketKey = $vatCodeId ?: 'no_vat';
            $vatBuckets[$bucketKey] = [
                'vat_code_id' => $vatCodeId,
                'netto' => $netto,
                'tax' => $tax,
                'brutto' => $brutto,
            ];

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
                    $data['description'] = $existing !== '' ? ($existing . "\n" . $append) : $append;
                }
            }
        } else {
            foreach ($items as $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') continue;

                $qty       = $num($row['quantity'] ?? 0);
                $price     = $num($row['price'] ?? 0);
                $disc      = $num($row['discount_percent'] ?? 0);

                $vatCodeId = $row['vat_code_id'] ?? null;
                // [noVAT] stawka zawsze 0
                $rate      = $noVat ? 0.0 : (float)($vatRatesMap[$vatCodeId] ?? 0);

                $unitAfterDisc = $price * (1 - ($disc / 100));
                $netto  = round($qty * $unitAfterDisc, 2);
                $tax    = $noVat ? 0.0 : round($netto * ($rate / 100), 2); // [noVAT]
                $brutto = round($netto + $tax, 2);

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
                    'price'            => $price,
                    'discount_percent' => $disc,
                    'netto'            => $netto,
                    'brutto'           => $brutto,
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
            // różne templaty per-typ:
            $this->render($kind === 'novat' ? 'add_no_vat' : 'add');
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
        if (empty($data['series'])) {
            $this->Flash->error('Seria faktury jest wymagana.');
            $this->set(compact('invoice','vats','vatRatesMap','kind'));
            $this->render($kind === 'novat' ? 'add_no_vat' : 'add');
            return null;
        }

        // Znajdź serię faktury
        $InvoiceSeriesTable = $this->fetchTable('InvoiceSeries');
        $series = $InvoiceSeriesTable->find()
            ->where(['InvoiceSeries.company_id' => $companyId, 'InvoiceSeries.name' => $data['series']])
            ->first();
            
        if (!$series) {
            $this->Flash->error('Nieprawidłowa seria faktury.');
            $this->set(compact('invoice','vats','vatRatesMap','kind'));
            $this->render($kind === 'novat' ? 'add_no_vat' : 'add');
            return null;
        }

        // Wygeneruj numer faktury jeśli nie podano
        if (empty($data['fullnumber'])) {
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
            
            // Wyciągnij numer z ostatniej faktury lub użyj startowego
            if ($lastInvoice) {
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
            'fullnumber' => $data['fullnumber'],
            'currency' => $cur,
            'currency_date' => $currencyDate,
            'currency_exchange' => $currencyExchange,
            'description' => $data['description'] ?? '',
            'is_print' => false,
            'is_sent' => false,
            'is_api' => false,
            // New flags
            'is_receipt_invoice' => (!empty($data['is_receipt_invoice']) || $fpFlag) ? 1 : 0, // Faktura do paragonu (FP)
            'is_split_payment'   => !empty($data['is_split_payment']) ? 1 : 0,   // Mechanizm podzielonej płatności (MPP)
            // Optional: paragon fields (if columns exist)
            'receipt_number'     => $data['receipt_number'] ?? null,
            'receipt_date'       => !empty($data['receipt_date']) ? $data['receipt_date'] : null,
            // Nowe pola dla składników daty i numeru
            'number' => $this->extractNumberFromFullnumber($data['fullnumber']),
            'day' => (int) $dateObject->format('d'),
            'month' => (int) $dateObject->format('m'),
            'year' => (int) $dateObject->format('Y'),
            'day_year' => (int) $dateObject->format('z') + 1, // format 'z' zwraca 0-364, więc dodajemy 1
        ];

        // Default issuer from company if not provided
        $issuerDefault = null;
        try {
            $CompaniesTbl2 = $this->fetchTable('Companies');
            $row = $CompaniesTbl2->find()->select(['issuer'])->where(['id' => $companyId])->first();
            if ($row) { $issuerDefault = (string)($row->issuer ?? ''); }
        } catch (\Throwable $e) { /* ignore */ }

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
                ->first();
                
            if ($company) {
                $InvoiceCompanyDetailsTable = $this->fetchTable('InvoiceCompanyDetails');
                $companyDetailEntity = $InvoiceCompanyDetailsTable->newEmptyEntity();
                // Determine bank account snapshot: prefer posted value; otherwise default company bank account (from CompanyBankAccounts.is_default), fallback to Companies.bank_account
                $postedBank = trim((string)($data['invoice_company_detail']['bank_account'] ?? ''));
                $snapshotBank = $postedBank;
                if ($snapshotBank === '') {
                    try {
                        $Cba = $this->fetchTable('CompanyBankAccounts');
                        $def = $Cba->find()
                            ->select(['iban'])
                            ->where(['company_id' => $companyId, 'is_default' => 1])
                            ->order(['is_default' => 'DESC', 'created' => 'DESC'])
                            ->first();
                        if ($def && !empty($def->iban)) {
                            $snapshotBank = (string)$def->iban;
                        }
                    } catch (\Throwable $e) {
                        // ignore, fallback below
                    }
                }
                if ($snapshotBank === '') {
                    $snapshotBank = (string)($company->bank_account ?? '');
                }
                // Street + local number (if provided) e.g. "Kwiatowa 10/5"
                $streetLine = trim((string)($company->street ?? ''));
                $localNo    = trim((string)($company->local_number ?? ''));
                if ($localNo !== '') {
                    // If street ends with a digit/letter, join with '/', otherwise with space
                    $joiner = (preg_match('/[\p{L}\d]$/u', $streetLine) ? '/' : ' ');
                    $streetLine = rtrim($streetLine) . $joiner . $localNo;
                }

                $companyDetailData = [
                    'invoice_id' => $invoiceId,
                    'name' => $company->name ?? '',
                    'nip' => $company->nip ?? '',
                    'street' => $streetLine,
                    'city' => $company->city ?? '',
                    'zip' => $company->postal_code ?? '',
                    'country' => $company->country ?? 'Polska',
                    'bank_account' => $snapshotBank,
                ];
                
                $companyDetailEntity = $InvoiceCompanyDetailsTable->patchEntity($companyDetailEntity, $companyDetailData);
                if (!$InvoiceCompanyDetailsTable->save($companyDetailEntity)) {
                    throw new \RuntimeException('Błąd zapisu danych sprzedawcy');
                }
            }

            // Zapisz dane nabywcy (invoice_contractors)
            if (!empty($data['invoice_contractor'])) {
                $contractor = $data['invoice_contractor'];
                $InvoiceContractorsTable = $this->fetchTable('InvoiceContractors');
                $contractorEntity = $InvoiceContractorsTable->newEmptyEntity();
                $contractorData = [
                    'invoice_id' => $invoiceId,
                    'name' => $contractor['name'] ?? '',
                    'nip' => $contractor['nip'] ?? '',
                    'street' => $contractor['street'] ?? '',
                    'city' => $contractor['city'] ?? '',
                    'zip' => $contractor['zip'] ?? '',
                    'country' => $contractor['country'] ?? 'Polska',
                    'account_number' => $contractor['account_number'] ?? '',
                ];
                
                $contractorEntity = $InvoiceContractorsTable->patchEntity($contractorEntity, $contractorData);
                if (!$InvoiceContractorsTable->save($contractorEntity)) {
                    throw new \RuntimeException('Błąd zapisu danych nabywcy');
                }
            }

            // Zapisz pozycje faktury (invoice_contents)
            $InvoiceContentsTable = $this->fetchTable('InvoiceContents');
            foreach ($contents as $contentData) {
                $contentEntity = $InvoiceContentsTable->newEmptyEntity();
                $contentData['invoice_id'] = $invoiceId;
                
                $contentEntity = $InvoiceContentsTable->patchEntity($contentEntity, $contentData);
                if (!$InvoiceContentsTable->save($contentEntity)) {
                    throw new \RuntimeException('Błąd zapisu pozycji faktury');
                }
            }

            $conn->commit();

            // Opcjonalna ścieżka: po zapisie wyślij od razu do KSeF z przesłanego pliku XML (FA (3))
            $doSend = (int)($data['ksef_send'] ?? 0) === 1;
            if ($doSend) {
                $envRaw = (string)($data['ksef_env'] ?? 'test');
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
                    $this->Flash->warning('Brak pliku XML FA (3) i nie udało się wygenerować poprawnego XML. Zapisano fakturę, ale nie wysłano do KSeF.');
                } else {
                    try {
                        $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
                        $res = $service->sendInvoiceXml((string)$companyId, $environment, $xml);

                        // Zapisz wynik w polach ksef_*
                        $desc = (string)($res['statusDesc'] ?? '');
                        $refs = ' [S=' . (string)($res['sessionReference'] ?? '') . ', I=' . (string)($res['invoiceReference'] ?? '') . ']';
                        $invoice->set('ksef_status', (string)($res['statusCode'] ?? ''));
                        $invoice->set('ksef_desc',   trim($desc . $refs));
                        $invoice->set('ksef_number', (string)($res['ksefNumber'] ?? ''));
                        $Invoices->save($invoice); // best-effort

                        if (class_exists('Cake\\Log\\Log')) {
                            \Cake\Log\Log::info('[KSeF][send] inv=' . $invoice->id . ' env=' . $environment . ' code=' . ($res['statusCode'] ?? '') . ' desc=' . ($res['statusDesc'] ?? '') . ' ksef=' . ($res['ksefNumber'] ?? '') . ' S=' . ($res['sessionReference'] ?? '') . ' I=' . ($res['invoiceReference'] ?? ''));
                        }

                        if (!empty($res['ok'])) {
                            $this->Flash->success('Wysłano do KSeF. Numer KSeF: ' . (string)$res['ksefNumber']);
                        } else {
                            $this->Flash->error('Nie udało się wysłać do KSeF (' . (string)($res['statusCode'] ?? '') . '): ' . (string)($res['statusDesc'] ?? ''));
                        }
                    } catch (\Throwable $e) {
                        $this->Flash->error('Błąd wysyłki do KSeF: ' . $e->getMessage());
                    }
                }
            } else {
                $this->Flash->success($kind === 'novat' ? 'Faktura bez VAT została utworzona.' : 'Dokument został utworzony.');
            }

            return $this->redirect(['action' => 'view', $invoice->id]);
            
        } catch (\Throwable $e) {
            $conn->rollback();
            $this->Flash->error('Błąd zapisu: ' . $e->getMessage());
        }
    }

    // GET — osobny widok dla noVAT
    // debug($kind);
    $this->set(compact('invoice','vats','vatRatesMap','kind'));
    if($kind === 'novat') {
        $this->render('add_no_vat');
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
    } else if ($kind === 'correction') {
        $this->render('add_correct');
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
        // Załaduj fakturę z pozycjami i snapshotem kontrahenta
        $invoice = $this->Invoices->get($id, contain: [
            'InvoiceContractors',
            'InvoiceContents' => ['Vats']
        ]);

        // Słowniki VAT do widoku
        $Vats        = $this->fetchTable('Vats');
        $vatRows     = $Vats->find()->select(['id','name','rate'])->order(['rate' => 'DESC'])->all();
        $vats        = $vatRows->combine('id', fn($v) => sprintf('%s (%s%%)', (string)$v->name, rtrim(rtrim((string)$v->rate,'0'),'.')))->toArray();
        $vatRatesMap = $vatRows->combine('id', 'rate')->toArray();

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = (array)$this->request->getData();

            $num = static function($val): float {
                $s = str_replace([' ', ','], ['', '.'], (string)$val);
                return is_numeric($s) ? (float)$s : 0.0;
            };

            $noVat   = ($invoice->type === 'novat');
            $items   = (array)($data['items'] ?? []);
            $contents = [];
            $sumNet = 0.0; $sumTax = 0.0; $sumGross = 0.0;
            foreach ($items as $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') { continue; }
                $qty   = $num($row['quantity'] ?? 0);
                $price = $num($row['price'] ?? 0);
                $disc  = $num($row['discount_percent'] ?? 0);
                $vatId = $row['vat_code_id'] ?? null;
                $rate  = $noVat ? 0.0 : (float)($vatRatesMap[$vatId] ?? 0);

                $unitAfterDisc = $price * (1 - ($disc/100));
                $netto  = round($qty * $unitAfterDisc, 2);
                $tax    = $noVat ? 0.0 : round($netto * ($rate/100), 2);
                $brutto = round($netto + $tax, 2);

                $sumNet   += $netto;
                $sumTax   += $tax;
                $sumGross += $brutto;

                $contents[] = [
                    'vat_code_id'      => $noVat ? null : $vatId,
                    'name'             => $name,
                    'product_desc'     => (string)($row['product_desc'] ?? ''),
                    'quantity'         => $qty,
                    'unit'             => (string)($row['unit'] ?? 'szt.'),
                    'price'            => $price,
                    'discount_percent' => $disc,
                    'netto'            => $netto,
                    'brutto'           => $brutto,
                    'gtu_code'         => (string)($row['gtu_code'] ?? ''),
                ];
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

            // Seria -> invoice_series_id (bez automatycznego nadawania numeru)
            if (!empty($data['series'])) {
                $InvoiceSeriesTable = $this->fetchTable('InvoiceSeries');
                $series = $InvoiceSeriesTable->find()
                    ->where(['InvoiceSeries.company_id' => $invoice->company_id, 'InvoiceSeries.name' => $data['series']])
                    ->first();
                if ($series) {
                    $data['invoice_series_id'] = $series->id;
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

            // Dane do patchowania faktury (whitelist)
            $invoicePatch = [
                'paymentmethod' => $data['paymentmethod'] ?? $invoice->paymentmethod,
                'paymentdate'   => $data['paymentdate'] ?? $invoice->paymentdate,
                'paymentstate'  => $paymentstate,
                'date'          => $data['date'] ?? $invoice->date,
                'total'         => $total,
                'netto'         => $netto,
                'tax'           => $tax,
                'alreadypaid'   => $alreadypaid,
                'remaining'     => $remaining,
                'fullnumber'    => $data['fullnumber'] ?? $invoice->fullnumber,
                'currency'      => $data['currency'] ?? $invoice->currency,
                'currency_date' => $data['currency_date'] ?? $invoice->currency_date,
                'currency_exchange' => $data['currency_exchange'] ?? $invoice->currency_exchange,
                'description'   => $data['description'] ?? $invoice->description,
                'issuer'        => $data['issuer'] ?? $invoice->issuer,
                // New flags
                'is_receipt_invoice' => (isset($data['is_receipt_invoice']) && !empty($data['is_receipt_invoice']))
                    || (!empty($data['flags']['fp']))
                    ? 1 : ($invoice->is_receipt_invoice ?? 0),
                'is_split_payment'   => isset($data['is_split_payment']) ? (int)!empty($data['is_split_payment'])     : ($invoice->is_split_payment ?? 0),
            ];
            // Optional: allow updating receipt details if provided
            foreach (['receipt_number','receipt_date'] as $k) {
                if (array_key_exists($k, $data)) {
                    $invoicePatch[$k] = $data[$k];
                }
            }
            foreach (['number','day','month','year','day_year','invoice_series_id'] as $k) {
                if (array_key_exists($k, $data)) { $invoicePatch[$k] = $data[$k]; }
            }

            $conn = $this->Invoices->getConnection();
            $conn->begin();
            try {
                // Zapisz fakturę
                $invoice = $this->Invoices->patchEntity($invoice, $invoicePatch);
                if (!$this->Invoices->save($invoice)) {
                    throw new \RuntimeException('Błąd zapisu faktury: ' . json_encode($invoice->getErrors()));
                }

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

                // Pozycje: prosty model replace-all
                $InvoiceContents = $this->fetchTable('InvoiceContents');
                $InvoiceContents->deleteAll(['invoice_id' => $invoice->id]);
                foreach ($contents as $c) {
                    $ent = $InvoiceContents->newEmptyEntity();
                    $c['invoice_id'] = $invoice->id;
                    $ent = $InvoiceContents->patchEntity($ent, $c);
                    if (!$InvoiceContents->save($ent)) {
                        throw new \RuntimeException('Błąd zapisu pozycji faktury');
                    }
                }

                $conn->commit();
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
        if ($this->Invoices->delete($invoice)) {
            $this->Flash->success(__('The invoice has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
private function makeClient(string $environment): KsefClient
    {
        $baseUrl = ($environment === 'prod')
            ? 'https://ksef.mf.gov.pl/api/v2'
            : 'https://ksef-test.mf.gov.pl/api/v2';

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

        $env = (string)($this->request->getQuery('env') ?? 'test');
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

        $env = (string)($this->request->getQuery('env') ?? 'test');
        $environment = ($env === 'prod') ? 'prod' : 'test';

        $ksefNumber = (string)$this->request->getQuery('ksef_number');
        if ($ksefNumber === '') {
            throw new BadRequestException('Podaj ksef_number.');
        }

        // session_reference opcjonalnie z query; jeżeli brak, spróbuj odczytać z rekordu faktury (z ksef_desc "[S=..., I=...]")
        $sessionRef = (string)$this->request->getQuery('session_reference', '');
        if ($sessionRef === '') {
            try {
                $invoiceRow = $this->Invoices->find()
                    ->select(['id','ksef_desc','ksef_session_reference'])
                    ->where(['company_id' => $companyId, 'ksef_number' => $ksefNumber])
                    ->first();
                if ($invoiceRow && !empty($invoiceRow->ksef_desc)) {
                    if (preg_match('/S=([A-Z0-9\-]+)/i', (string)$invoiceRow->ksef_desc, $m)) {
                        $sessionRef = (string)$m[1];
                    }
                }
                if (!$sessionRef && !empty($invoiceRow?->ksef_session_reference)) {
                    $sessionRef = (string)$invoiceRow->ksef_session_reference;
                }
            } catch (\Throwable) {
                // ignore
            }
        }
        if ($sessionRef === '') {
            // W trybie N1 klienta wymagamy session_reference do pobrania UPO
            throw new BadRequestException('Brak session_reference (S=...). UPO w N1 kliencie wymaga referencji sesji. Uzupełnij S lub wyślij fakturę ponownie, aby zapisać S w bazie.');
        }

        // Zbuduj klienta oficjalnej biblioteki i pobierz UPO przez ścieżkę sessions → invoices → ksefUpo
        $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
        $client = $ksef->buildClient((string)$companyId, $environment);
        $req = new \N1ebieski\KSEFClient\Requests\Sessions\Invoices\KsefUpo\KsefUpoRequest(
            referenceNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber::from($sessionRef),
            ksefNumber: \N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber::from($ksefNumber)
        );
        $body = $client->sessions()->invoices()->ksefUpo($req)->body();

        // Robust type sniff: strip UTF-8 BOM and whitespace before checking
        $bin = (string)$body;
        if (str_starts_with($bin, "\xEF\xBB\xBF")) { // UTF-8 BOM
            $bin = substr($bin, 3);
        }
        $binTrim = ltrim($bin);
        $head = substr($binTrim, 0, 200);
        $isPdf = str_starts_with($binTrim, '%PDF');
        $isXml = str_starts_with($binTrim, '<') || (bool)preg_match('/<\?xml|<[^>]+>/', $head);

        $filename = $isPdf ? 'UPO.pdf' : ($isXml ? 'UPO.xml' : 'UPO.bin');
        $type     = $isPdf ? 'application/pdf' : ($isXml ? 'application/xml' : 'application/octet-stream');

        return $this->response
            ->withType($type)
            ->withHeader('Content-Length', (string)strlen($body))
            ->withDownload($filename)
            ->withStringBody($body);
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

        $env = (string)($this->request->getQuery('env') ?? 'test');
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

        $env = (string)($this->request->getQuery('env') ?? 'test');
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

        $env = (string)($this->request->getQuery('env') ?? 'test');
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
    private function extractNumberFromFullnumber(string $fullnumber): int
    {
        // Debug
        \Cake\Log\Log::debug('Extracting number from: ' . $fullnumber);
        
        // Dla wzorców typu EPIC/01/10/2025 - wyciągaj pierwszy numer po pierwszym słowie/prefiksie
        // Wzorzec: prefix/NUMER/miesiąc/rok
        
        // Najpierw spróbuj znaleźć wzorzec z separatorami
        if (preg_match('/^[A-Za-z]+\/0*(\d+)\//', $fullnumber, $matches)) {
            // Wzorzec typu EPIC/01/10/2025 - bierz pierwszy numer po prefiksie
            $extracted = (int) $matches[1];
            \Cake\Log\Log::debug('Extracted number (prefix/number/...): ' . $extracted);
            return $extracted;
        }
        
        // Fallback - szukaj pierwszego numeru
        if (preg_match('/\b0*(\d+)\b/', $fullnumber, $matches)) {
            $extracted = (int) $matches[1];
            \Cake\Log\Log::debug('Extracted number (first found): ' . $extracted);
            return $extracted;
        }
        
        \Cake\Log\Log::debug('No number found, returning 0');
        return 0;
    }

    /**
     * Formatuje wzorzec numeru faktury z zaawansowanymi placeholderami
     */
    private function formatInvoicePattern(string $template, int $number, string $issueDate): string
    {
        $date = new \DateTime($issueDate);
        
        // Podstawowe placeholdery
        $replacements = [
            '[numer]' => (string) $number,
            '[rok]' => $date->format('Y'),
            '[miesiac]' => $date->format('m'),
            '[miesiąc]' => $date->format('m'), // Polskie znaki
            '[dzien]' => $date->format('d'),
            '[dzień]' => $date->format('d'), // Polskie znaki
        ];
        
        $result = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Zaawansowane placeholdery z parametrami
        $result = preg_replace_callback('/\[numer:zera_wiodące=(\d+)\]/', function($matches) use ($number) {
            return str_pad((string) $number, (int) $matches[1], '0', STR_PAD_LEFT);
        }, $result);
        
        // Miesiąc z zerami wiodącymi (polskie znaki)
        $result = preg_replace_callback('/\[miesiąc:zera_wiodące=(\d+)\]/', function($matches) use ($date) {
            return str_pad($date->format('m'), (int) $matches[1], '0', STR_PAD_LEFT);
        }, $result);
        
        // Dzień z zerami wiodącymi (polskie znaki)
        $result = preg_replace_callback('/\[dzień:zera_wiodące=(\d+)\]/', function($matches) use ($date) {
            return str_pad($date->format('d'), (int) $matches[1], '0', STR_PAD_LEFT);
        }, $result);
        
        $result = preg_replace_callback('/\[rok:format_dwucyfrowy\]/', function() use ($date) {
            return $date->format('y');
        }, $result);
        
        // Kwartał
        $result = preg_replace_callback('/\[kwartał\]/', function() use ($date) {
            return (string) ceil((int) $date->format('n') / 3);
        }, $result);
        
        return $result;
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
                $baseDate = $invoice->date; // using issue date; adjust if you persist sold_date separately
                $nbp = $this->computeNbpAvgRate($cur, $baseDate);
            }
        } catch (\Throwable) { /* ignore for printing */ }

        // Use CakePdf view to render PDF
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
        // Let PdfView handle response; no manual headers needed
        return null;
    }

    /**
     * Bulk actions for invoices
     */
    public function bulkAction()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        // Accept both 'action' and 'bulk_action' (hidden fallback set by JS)
        $action = $this->request->getData('bulk_action') ?? $this->request->getData('action');
        $selectedIds = (array) $this->request->getData('selected');

        if (empty($selectedIds)) {
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
            $this->Flash->error('Wybrane faktury nie należą do Twojej firmy.');
            return $this->redirect(['action' => 'index']);
        }

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
                    $this->Flash->success("Utworzono płatności dla {$created} faktur. Pominięte: {$skipped}, błędy: {$errors}.");
                } elseif ($skipped > 0 && $errors === 0) {
                    $this->Flash->info('Wszystkie wybrane faktury były już opłacone. Nic nie zmieniono.');
                } else {
                    $this->Flash->error("Nie udało się utworzyć płatności (błędy: {$errors}).");
                }
                break;

            case 'send_reminder':
                $this->Flash->info('Funkcja wysyłania przypomnień zostanie wkrótce dodana.');
                break;

            case 'delete_selected':
                $count = $this->Invoices->deleteAll([
                    'Invoices.id IN' => $selectedIds,
                    'Invoices.company_id' => $companyId
                ]);
                $this->Flash->success("Usunięto {$count} faktur.");
                break;

            default:
                $this->Flash->error('Nieznana akcja.');
        }

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
        $sumAdvances = (float)$Invoices->find()
            ->select(['s' => $Invoices->find()->func()->sum('total')])
            ->where([
                'company_id' => $companyId,
                'parent_id' => $id,
                'type IN' => ['advance','final']
            ])
            ->enableHydration(false)
            ->first()['s'] ?? 0.0;
        $hasFinal = (bool)$Invoices->find()
            ->select(['id'])
            ->where([
                'company_id' => $companyId,
                'parent_id' => $id,
                'type' => 'final'
            ])->limit(1)->count();
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

        $payload = [
            'id' => (string)$p->id,
            'fullnumber' => (string)($p->fullnumber ?? ''),
            'date' => $p->date?->i18nFormat('yyyy-MM-dd'),
            'currency' => (string)($p->currency ?? 'PLN'),
            'total' => (float)$p->total,
            'advances_total' => (float)$sumAdvances,
            'remaining' => (float)$remaining,
            'final_exists' => $hasFinal,
            'contractor' => [
                'name' => (string)($p->invoice_contractor->name ?? ''),
                'nip' => (string)($p->invoice_contractor->nip ?? ''),
                'street' => (string)($p->invoice_contractor->street ?? ''),
                'zip' => (string)($p->invoice_contractor->zip ?? ''),
                'city' => (string)($p->invoice_contractor->city ?? ''),
                'country' => (string)($p->invoice_contractor->country ?? 'PL'),
            ],
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
     * POST /invoices/send-to-ksef/{id}?env=test|prod
     * Opcja ponownego wysłania istniejącej faktury do KSeF (na podstawie przesłanego XML lub generatora FA(3)).
     */
    public function sendToKsef(string $id)
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $env = (string)$this->request->getQuery('env', 'test');
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

        // 2) Jeśli brak uploadu – wygeneruj
        if (!is_string($xml) || trim($xml) === '') {
            try { $xml = $this->buildFa3Xml($invoice); } catch (\Throwable) { $xml = ''; }
        }

        if (!is_string($xml) || trim($xml) === '') {
            $this->Flash->error('Brak poprawnego XML FA (3). Operacja przerwana.');
            return $this->redirect(['action' => 'view', $id]);
        }

        $jsonMode = $this->request->is('ajax') || $this->request->getQuery('_ext') === 'json' || $this->request->accepts('application/json');
        try {
            $service = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
            $res = $service->sendInvoiceXml($companyId, $environment, $xml);

            $desc = (string)($res['statusDesc'] ?? '');
            $refs = ' [S=' . (string)($res['sessionReference'] ?? '') . ', I=' . (string)($res['invoiceReference'] ?? '') . ']';
            $invoice->set('ksef_status', (string)($res['statusCode'] ?? ''));
            $invoice->set('ksef_desc',   trim($desc . $refs));
            $invoice->set('ksef_number', (string)($res['ksefNumber'] ?? ''));
            // Nowe: zapisz referencje w dedykowanych polach
            $invoice->set('ksef_session_reference', (string)($res['sessionReference'] ?? ''));
            $invoice->set('ksef_invoice_reference', (string)($res['invoiceReference'] ?? ''));
            $this->Invoices->save($invoice);

            // Log
            if (class_exists('Cake\\Log\\Log')) {
                \Cake\Log\Log::info('[KSeF][send] inv=' . $id . ' env=' . $environment . ' code=' . ($res['statusCode'] ?? '') . ' desc=' . ($res['statusDesc'] ?? '') . ' ksef=' . ($res['ksefNumber'] ?? '') . ' S=' . ($res['sessionReference'] ?? '') . ' I=' . ($res['invoiceReference'] ?? ''));
            }

            if ($jsonMode) {
                $ok = !empty($res['ok']);
                $payload = [
                    'success' => $ok,
                    'statusCode' => (int)($res['statusCode'] ?? 0),
                    'statusDesc' => (string)($res['statusDesc'] ?? ''),
                    'ksefNumber' => (string)($res['ksefNumber'] ?? ''),
                    'sessionReference' => (string)($res['sessionReference'] ?? ''),
                    'invoiceReference' => (string)($res['invoiceReference'] ?? ''),
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

            if (!empty($res['ok'])) {
                $this->Flash->success('Wysłano do KSeF. Numer KSeF: ' . (string)$res['ksefNumber']);
            } else {
                $this->Flash->error('Nie udało się wysłać do KSeF (' . (string)($res['statusCode'] ?? '') . '): ' . (string)($res['statusDesc'] ?? ''));
            }
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
     * POST /invoices/refresh-ksef-status/{id}?env=test|prod
     * Próbuje zweryfikować status na podstawie pobrania XML po numerze KSeF.
     */
    public function refreshKsefStatus(string $id)
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $env = (string)$this->request->getQuery('env', 'test');
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

        $env = (string)$this->request->getQuery('env', 'test');
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
     * Generator minimalnego FA(3) (best-effort) na podstawie zapisanej faktury.
     * UWAGA: Wersja uproszczona — może wymagać dostosowań do pełnej zgodności XSD.
     */
   private function buildFa3Xml(\App\Model\Entity\Invoice $inv): string
    {
        $seller = $inv->invoice_company_detail ?? null;
        $buyer  = $inv->invoice_contractor ?? null;
        $items  = (array)$inv->invoice_contents;

        $sellerName = trim((string)($seller->name ?? ''));
        $sellerNip  = preg_replace('/\D+/', '', (string)($seller->nip ?? ''));
        $buyerName  = trim((string)($buyer->name ?? ''));
        $buyerNip   = preg_replace('/\D+/', '', (string)($buyer->nip ?? ''));

        $date = $inv->date ? $inv->date->format('Y-m-d') : date('Y-m-d');
        $number = (string)($inv->fullnumber ?? $inv->id);
        $currency = (string)($inv->currency ?? 'PLN');

    $xml = [];
    // Escape only XML-special chars (<, >, &). Keep quotes and original casing as-is in text nodes.
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');

        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<Faktura xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/">';
        $xml[] = '  <Naglowek>';
        $xml[] = '    <KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza>';
        $xml[] = '    <WariantFormularza>3</WariantFormularza>';
        $xml[] = '    <DataWytworzeniaFa>' . $esc(gmdate('c')) . '</DataWytworzeniaFa>';
        $xml[] = '    <SystemInfo>Aplikacja Podatnika KSeF</SystemInfo>';
        $xml[] = '  </Naglowek>';
        $xml[] = '  <Podmiot1>';
        $xml[] = '    <PrefiksPodatnika>PL</PrefiksPodatnika>';
        $xml[] = '    <DaneIdentyfikacyjne>';
        if ($sellerNip !== '') { $xml[] = '      <NIP>' . $esc($sellerNip) . '</NIP>'; }
        if ($sellerName !== '') { $xml[] = '      <Nazwa>' . $esc($sellerName) . '</Nazwa>'; }
        $xml[] = '    </DaneIdentyfikacyjne>';
        $xml[] = '    <Adres>';
        $xml[] = '      <KodKraju>PL</KodKraju>';
        if (!empty($seller?->street)) { $xml[] = '      <AdresL1>' . $esc($seller->street) . '</AdresL1>'; }
        if (!empty($seller?->city)) { $xml[] = '      <AdresL2>' . $esc($seller->city) . '</AdresL2>'; }
        $xml[] = '    </Adres>';
        $xml[] = '  </Podmiot1>';
        $xml[] = '  <Podmiot2>';
        $xml[] = '    <DaneIdentyfikacyjne>';
        if ($buyerNip !== '') { $xml[] = '      <NIP>' . $esc($buyerNip) . '</NIP>'; }
        if ($buyerName !== '') { $xml[] = '      <Nazwa>' . $esc($buyerName) . '</Nazwa>'; }
        $xml[] = '    </DaneIdentyfikacyjne>';
        $xml[] = '    <Adres>';
        $xml[] = '      <KodKraju>PL</KodKraju>';
        if (!empty($buyer?->street)) { $xml[] = '      <AdresL1>' . $esc($buyer->street) . '</AdresL1>'; }
        if (!empty($buyer?->city)) { $xml[] = '      <AdresL2>' . $esc($buyer->city) . '</AdresL2>'; }
    $xml[] = '    </Adres>';
    // Dodatkowe znaczniki zgodnie z wymaganiem: JST i GV
    $xml[] = '    <JST>2</JST>';
    $xml[] = '    <GV>2</GV>';
    $xml[] = '  </Podmiot2>';
        $xml[] = '  <Fa>';
        $xml[] = '    <KodWaluty>' . $esc($currency) . '</KodWaluty>';
        $xml[] = '    <P_1>' . $esc($date) . '</P_1>';
        $xml[] = '    <P_2>' . $esc($number) . '</P_2>';
        $xml[] = '    <P_6>' . $esc($date) . '</P_6>';

        $sumNet = 0.0; $sumVat = 0.0; $sumGross = 0.0;
        $grp = [
            '23' => ['net' => 0.0, 'vat' => 0.0],
            '8'  => ['net' => 0.0, 'vat' => 0.0],
            '5'  => ['net' => 0.0, 'vat' => 0.0],
        ];

        // Prepare item rows first (for totals), but append them later after <RodzajFaktury>
        $rows = [];
        foreach ($items as $idx => $it) {
            $name = (string)($it->name ?? 'Pozycja');
            $qty  = (float)($it->quantity ?? 1);
            $net  = (float)($it->netto ?? 0);
            $gross= (float)($it->brutto ?? 0);
            $rate = isset($it->vat) ? (float)$it->vat->rate : 0.0;
            $unit = (string)($it->unit ?? 'szt.');
            $tax  = max(0.0, $gross - $net);
            $sumNet += $net; $sumVat += $tax; $sumGross += $gross;
            $rateStr = $rate > 0 ? (string)(int)round($rate) : '';
            if (in_array($rateStr, ['23','8','5'], true)) {
                $grp[$rateStr]['net'] += $net;
                $grp[$rateStr]['vat'] += $tax;
            }
            $row = [];
            $row[] = '    <FaWiersz>';
            $row[] = '      <NrWierszaFa>' . ($idx + 1) . '</NrWierszaFa>';
            $row[] = '      <P_7>' . $esc($name) . '</P_7>';
            $row[] = '      <P_8A>' . $esc($unit) . '</P_8A>';
            $row[] = '      <P_8B>' . number_format(max(0.0, $qty), 2, '.', '') . '</P_8B>';
            $row[] = '      <P_9A>' . number_format(($qty > 0 ? $net / $qty : 0), 2, '.', '') . '</P_9A>';
            $row[] = '      <P_11>' . number_format($net, 2, '.', '') . '</P_11>';
            if ($rateStr !== '') {
                $row[] = '      <P_12>' . $rateStr . '</P_12>';
            } else {
                $row[] = '      <P_13_6>zw</P_13_6>';
            }
            $row[] = '    </FaWiersz>';
            $rows[] = $row;
        }

        if ($grp['23']['net'] > 0.0) {
            $xml[] = '    <P_13_1>' . number_format($grp['23']['net'], 2, '.', '') . '</P_13_1>';
            $xml[] = '    <P_14_1>' . number_format($grp['23']['vat'], 2, '.', '') . '</P_14_1>';
        }
        if ($grp['8']['net'] > 0.0) {
            $xml[] = '    <P_13_2>' . number_format($grp['8']['net'], 2, '.', '') . '</P_13_2>';
            $xml[] = '    <P_14_2>' . number_format($grp['8']['vat'], 2, '.', '') . '</P_14_2>';
        }
        if ($grp['5']['net'] > 0.0) {
            $xml[] = '    <P_13_3>' . number_format($grp['5']['net'], 2, '.', '') . '</P_13_3>';
            $xml[] = '    <P_14_3>' . number_format($grp['5']['vat'], 2, '.', '') . '</P_14_3>';
        }

    $xml[] = '    <P_15>' . number_format($sumGross, 2, '.', '') . '</P_15>';
    // Blok Adnotacje zgodnie z wymaganiem (stałe wartości demonstracyjne)
    $xml[] = '    <Adnotacje>';
    $xml[] = '      <P_16>2</P_16>';
    $xml[] = '      <P_17>2</P_17>';
    $xml[] = '      <P_18>2</P_18>';
    $xml[] = '      <P_18A>2</P_18A>';
    $xml[] = '      <Zwolnienie>';
    $xml[] = '        <P_19N>1</P_19N>';
    $xml[] = '      </Zwolnienie>';
    $xml[] = '      <NoweSrodkiTransportu>';
    $xml[] = '        <P_22N>1</P_22N>';
    $xml[] = '      </NoweSrodkiTransportu>';
    $xml[] = '      <P_23>2</P_23>';
    $xml[] = '      <PMarzy>';
    $xml[] = '        <P_PMarzyN>1</P_PMarzyN>';
    $xml[] = '      </PMarzy>';
    $xml[] = '    </Adnotacje>';
    $xml[] = '    <RodzajFaktury>VAT</RodzajFaktury>';
    // Append rows AFTER RodzajFaktury as requested
    foreach ($rows as $row) {
        foreach ($row as $line) { $xml[] = $line; }
    }
        $xml[] = '  </Fa>';
        $xml[] = '</Faktura>';

        return implode("\n", $xml);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacyInvoiceSyncService;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Moduł rozliczeń — lista faktur + dodawanie wpłat (invoice_payments).
 */
class ReconciliationsController extends AppController
{
    /**
     * Widok: tylko faktury z nowego systemu (KSeF / faktury24).
     */
    public function indexKsef(): void
    {
        $this->request = $this->request->withQueryParams(
            array_merge($this->request->getQueryParams(), ['source' => 'system'])
        );
        $this->index();
        $this->set('baseAction', 'indexKsef');
        $this->set('lockSource', 'system');
        $this->set('title', 'Rozliczenia — KSeF (nowe)');
        $this->viewBuilder()->setTemplate('index');
    }

    /**
     * Widok: tylko faktury archiwalne ze Speed (legacy).
     */
    public function indexSpeed(): void
    {
        $this->request = $this->request->withQueryParams(
            array_merge($this->request->getQueryParams(), ['source' => 'legacy'])
        );
        $this->index();
        $this->set('baseAction', 'indexSpeed');
        $this->set('lockSource', 'legacy');
        $this->set('title', 'Rozliczenia — Speed (archiwalne)');
        $this->viewBuilder()->setTemplate('index');
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $search              = trim((string)$this->request->getQuery('q', ''));
        $status              = $this->request->getQuery('status', '');
        $dateFrom            = $this->request->getQuery('date_from', '');
        $dateTo              = $this->request->getQuery('date_to', '');
        $dueDateFrom         = $this->request->getQuery('due_from', '');
        $dueDateTo           = $this->request->getQuery('due_to', '');
        $currencyFilter      = $this->request->getQuery('currency', '');
        $amountFrom          = $this->request->getQuery('amount_from', '');
        $amountTo            = $this->request->getQuery('amount_to', '');
        $typeFilter          = $this->request->getQuery('type', '');
        $bankAccountFilter   = trim((string)$this->request->getQuery('bank_account', ''));
        $sort                = (string)$this->request->getQuery('sort', '');
        $dir                 = $this->request->getQuery('dir', 'asc');
        $page           = max(1, (int)$this->request->getQuery('page', 1));
        $limit          = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $today    = date('Y-m-d');
        $Invoices = $this->fetchTable('Invoices');

        // Rachunki bankowe firmy — do filtra i selecta w modalu
        $companyBankAccounts = $this->fetchTable('CompanyBankAccounts')
            ->find()
            ->where(['company_id' => $companyId])
            ->select(['id', 'iban', 'label', 'currency', 'bank_name', 'is_default'])
            ->orderByDesc('is_default')
            ->orderByAsc('label')
            ->all()->toArray();

        // Typy faktur traktowane jako korekty — wykluczane ze statystyk i listy
        // (pokazywane jako badge na oryginale, nie jako osobne wiersze)
        $correctionTypes = ['correction', 'zal_korekta', 'roz_korekta'];

        // Filtr źródła — '' | 'system' | 'legacy'
        $sourceFilter = $this->request->getQuery('source', '');

        // ── Warunki WHERE wspólne dla głównego zapytania i statystyk ─────────
        $baseConditions = ['Invoices.company_id' => $companyId];

        // Wyszukiwanie po kontrahencie — podzapytanie
        if ($search !== '') {
            $like = '%' . $search . '%';
            $InvoiceContractors = $this->fetchTable('InvoiceContractors');
            $matchIds = $InvoiceContractors->find()
                ->select(['invoice_id'])
                ->where(['OR' => ['name LIKE' => $like, 'nip LIKE' => $like]])
                ->all()->extract('invoice_id')->toList();

            $orCond = ['Invoices.fullnumber LIKE' => $like];
            if (!empty($matchIds)) {
                $orCond['Invoices.id IN'] = $matchIds;
            }
            $baseConditions['OR'] = $orCond;
        }

        // Zakres dat wystawienia
        if ($dateFrom !== '') {
            $baseConditions['Invoices.date >='] = $dateFrom;
        }
        if ($dateTo !== '') {
            $baseConditions['Invoices.date <='] = $dateTo;
        }

        // Zakres terminu płatności
        if ($dueDateFrom !== '') {
            $baseConditions['Invoices.paymentdate >='] = $dueDateFrom;
        }
        if ($dueDateTo !== '') {
            $baseConditions['Invoices.paymentdate <='] = $dueDateTo;
        }

        // Waluta
        $validCurrencies = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK', 'DKK', 'SEK', 'NOK'];
        if (in_array($currencyFilter, $validCurrencies, true)) {
            $baseConditions['Invoices.currency'] = $currencyFilter;
        }

        // Zakres kwoty brutto
        if ($amountFrom !== '' && is_numeric($amountFrom)) {
            $baseConditions['Invoices.total >='] = (float)$amountFrom;
        }
        if ($amountTo !== '' && is_numeric($amountTo)) {
            $baseConditions['Invoices.total <='] = (float)$amountTo;
        }

        // Typ faktury
        $validTypes = ['vat', 'novat', 'currency', 'proforma', 'advance', 'final', 'correction', 'margin', 'rental', 'oss', 'internal', 'internalEvidence'];
        if (in_array($typeFilter, $validTypes, true)) {
            $baseConditions['Invoices.type'] = $typeFilter;
        }

        // Filtr rachunku bankowego — faktury z powiązanym przelewem na danym koncie
        if ($bankAccountFilter !== '') {
            $cleanIban = preg_replace('/[\s\-]/', '', $bankAccountFilter);
            $bankInvoiceIds = $this->fetchTable('BankTransactions')->find()
                ->select(['invoice_id'])
                ->where([
                    'company_id'         => $companyId,
                    'invoice_id IS NOT'  => null,
                    'REPLACE(REPLACE(account_number, " ", ""), "-", "") LIKE' => '%' . $cleanIban . '%',
                ])
                ->all()->extract('invoice_id')->toList();

            if (!empty($bankInvoiceIds)) {
                $baseConditions['Invoices.id IN'] = $bankInvoiceIds;
            } else {
                // Brak pasujących transakcji → brak wyników
                $baseConditions['Invoices.id'] = 'no-match-uuid';
            }
        }

        // Warunki dla statystyk (kafelki) — BEZ filtra statusu, żeby kafelki były zawsze globalne
        // Korekty wykluczone — są pokazywane jako badge na oryginale, nie jako osobne rekordy
        $baseStatsConditions = $baseConditions;
        $baseStatsConditions['Invoices.type NOT IN'] = $correctionTypes;

        // Status płatności — dodaj tylko do warunków listy, NIE do statystyk
        if ($status === 'overdue') {
            $baseConditions['Invoices.paymentstate !='] = 'paid';
            $baseConditions['Invoices.paymentdate <']   = $today;
        } elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
            $baseConditions['Invoices.paymentstate'] = $status;
        }

        // Korekty wykluczone z listy — widoczne tylko jako badge na oryginale
        $baseConditions['Invoices.type NOT IN'] = $correctionTypes;

        // ── Statystyki i lista — system (pomijamy gdy source=legacy) ─────────
        $stats = [
            'count'               => 0,
            'totalReceivablesPln' => 0.0, 'totalReceivablesEur' => 0.0,
            'totalPaidPln'        => 0.0, 'totalPaidEur'        => 0.0,
            'totalRemainingPln'   => 0.0, 'totalRemainingEur'   => 0.0,
            'overduePln'          => 0.0, 'overdueEur'          => 0.0,
            'overdueCount'        => 0,
            'vatPln'              => 0.0, 'vatEur'              => 0.0,
            // compat
            'totalReceivables'    => 0.0, 'totalPaid'           => 0.0,
            'totalRemaining'      => 0.0, 'overdue'             => 0.0,
        ];
        $total    = 0;
        $invoices = [];
        $pages    = 1;

        if ($sourceFilter !== 'legacy') {
            // ── Statystyki (prosta agregacja SQL bez contain) — globalne, bez filtra statusu ──
            $statsRows = $Invoices->find()
                ->select([
                    'total'        => 'Invoices.total',
                    'netto'        => 'Invoices.netto',
                    'alreadypaid'  => 'Invoices.alreadypaid',
                    'remaining'    => 'Invoices.remaining',
                    'currency'     => 'Invoices.currency',
                    'paymentstate' => 'Invoices.paymentstate',
                    'paymentdate'  => 'Invoices.paymentdate',
                ])
                ->where($baseStatsConditions)
                ->where([
                    'OR' => [
                        ['Invoices.workflow_status IS'  => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ])
                ->disableHydration()
                ->all()
                ->toArray();

            $stats = $this->_computeStats($statsRows, $today);

            // ── Główne zapytanie z kontrahentem ───────────────────────────────
            $allowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            // Domyślnie sortujemy po fullnumber (chronologicznie: rok → miesiąc → numer)
            $sortCol = in_array($sort, $allowedSort, true) ? $sort : 'fullnumber';
            $sortDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

            $invoiceQuery = $Invoices->find()
                ->contain([
                    'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                        return $q->select(['id', 'invoice_id', 'name', 'nip']);
                    },
                ])
                ->select([
                    'Invoices.id', 'Invoices.fullnumber', 'Invoices.date',
                    'Invoices.paymentdate', 'Invoices.paymentstate', 'Invoices.paymentmethod',
                    'Invoices.total', 'Invoices.netto', 'Invoices.alreadypaid', 'Invoices.remaining',
                    'Invoices.currency', 'Invoices.currency_exchange',
                    'Invoices.type', 'Invoices.created',
                    'Invoices.sent_at',
                ])
                ->where($baseConditions)
                ->where([
                    'OR' => [
                        ['Invoices.workflow_status IS'  => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ]);

            // Sortowanie po fullnumber: data wystawienia + numer (jako tie-breaker)
            //   1. DATA wystawienia (invoice.date) — chronologicznie wg sortDir
            //   2. NUMER (drugi segment fullnumber po /, CAST UNSIGNED) — żeby FV/10 po FV/9
            // Działa niezależnie od typu (currency/vat/novat/...). Lista jest
            // chronologicznie ułożona, w obrębie tego samego dnia faktury idą po numerze.
            if ($sortCol === 'fullnumber') {
                $numExpr = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(Invoices.fullnumber, '/', 2), '/', -1) AS UNSIGNED)";
                $invoiceQuery->orderBy([
                    'Invoices.date' => $sortDir,                                       // data wystawienia
                    new \Cake\Database\Expression\QueryExpression($numExpr . ' ' . $sortDir),  // numer w obrębie dnia
                    'Invoices.fullnumber' => $sortDir,                                 // tie-breaker
                    'Invoices.created'    => 'DESC',
                ]);
            } else {
                $invoiceQuery->orderBy(['Invoices.' . $sortCol => $sortDir, 'Invoices.created' => 'DESC']);
            }

            $total    = (clone $invoiceQuery)->count();
            $invoices = $invoiceQuery->limit($limit)->offset(($page - 1) * $limit)->all()->toArray();
            $pages    = (int)ceil($total / $limit);
        } else {
            // Gdy source=legacy — $sortDir potrzebne dla legacy query
            $allowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            $sortDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        }

        // ── Korekty do faktur systemowych (per parent_id) ───────────────────
        // Doczytaj korekty dla faktur na aktualnej stronie, żeby pokazać badge na oryginale
        $correctionsByParentId = [];
        if (!empty($invoices)) {
            $invIds = array_column($invoices, 'id');
            $corrRows = $Invoices->find()
                ->select([
                    'Invoices.id', 'Invoices.parent_id', 'Invoices.fullnumber',
                    'Invoices.date', 'Invoices.total', 'Invoices.remaining',
                    'Invoices.currency', 'Invoices.type', 'Invoices.paymentstate',
                ])
                ->where([
                    'Invoices.company_id'  => $companyId,
                    'Invoices.parent_id IN' => $invIds,
                    'Invoices.type IN'      => $correctionTypes,
                ])
                ->orderByAsc('Invoices.date')
                ->all()->toArray();
            foreach ($corrRows as $corr) {
                $pid = (string)$corr->parent_id;
                $correctionsByParentId[$pid][] = $corr;
            }
        }

        // ── Wpłaty per faktura ──────────────────────────────────────────────
        // Źródło prawdy: invoice_payments (konkretne wpłaty na fakturę).
        // NIE używamy bank_transactions.match_status — to oddzielna semantyka
        // (dopasowanie tx ↔ faktura), która może być rozsynchronizowana
        // (np. auto-match podczas importu MT940 ustawia matched bez invoice_payment).
        $bankByInvoice = []; // invoiceId => array of InvoicePayment
        if (!empty($invoices)) {
            $invoiceIds = array_column($invoices, 'id');

            $bankRows = $this->fetchTable('InvoicePayments')->find()
                ->where(['invoice_id IN' => $invoiceIds])
                ->select(['id', 'invoice_id', 'amount', 'currency', 'payment_date',
                          'payment_method', 'description'])
                ->orderByDesc('payment_date')
                ->all()->toArray();

            foreach ($bankRows as $p) {
                // Normalizujemy do tego samego kontraktu co stare bankBadge —
                // template używa `amount`, `currency`, `value_date` (alias).
                $p->value_date = $p->payment_date;
                $iid = (string)$p->invoice_id;
                $bankByInvoice[$iid][] = $p;
            }

            // ── Zlecenia Speed (lista per faktura) ───────────────────────────
            $speedByInvoice = [];
            $speedRows = $this->fetchTable('SpeedOrders')->find()
                ->where(['invoice_id IN' => $invoiceIds])
                ->select(['id', 'invoice_id', 'symbol', 'date_delivery', 'date_ship'])
                ->orderByAsc('symbol')
                ->all()->toArray();

            foreach ($speedRows as $so) {
                $iid = (string)$so->invoice_id;
                $speedByInvoice[$iid][] = $so;
            }
        } else {
            $speedByInvoice = [];
        }

        // ── Faktury archiwalne (legacy) ───────────────────────────────────────
        $legacyInvoices = [];
        $legacyTotal    = 0;
        $legacyPages    = 1;
        $legacyPage     = max(1, (int)$this->request->getQuery('lpage', 1));

        if ($sourceFilter !== 'system' && $typeFilter === '') {
            $LegacyInvoices = $this->fetchTable('LegacyInvoices');

            $legacyConditions = ['LegacyInvoices.company_id' => $companyId];

            if ($search !== '') {
                $like = '%' . $search . '%';
                $legacyConditions['OR'] = [
                    'LegacyInvoices.fullnumber LIKE'      => $like,
                    'LegacyInvoices.contractor_name LIKE' => $like,
                    'LegacyInvoices.contractor_nip LIKE'  => $like,
                ];
            }

            // Warunki dla statystyk (kafelki) — BEZ filtra statusu, żeby kafelki były zawsze globalne
            $legacyStatsConditions = $legacyConditions;

            if ($status === 'overdue') {
                $legacyConditions['LegacyInvoices.paymentstate !='] = 'paid';
                // Łapiemy albo te z paymentdate < dziś, albo te z platnosc-based terminem
                // Pobieramy wszystkie nieopłacone i filtrujemy po obliczonym terminie w PHP
            } elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
                $legacyConditions['LegacyInvoices.paymentstate'] = $status;
            }

            if ($dateFrom !== '') {
                $legacyConditions['LegacyInvoices.date >='] = $dateFrom;
                $legacyStatsConditions['LegacyInvoices.date >='] = $dateFrom;
            }
            if ($dateTo !== '') {
                $legacyConditions['LegacyInvoices.date <='] = $dateTo;
                $legacyStatsConditions['LegacyInvoices.date <='] = $dateTo;
            }

            // Termin płatności (legacy — kolumna paymentdate)
            if ($dueDateFrom !== '') {
                $legacyConditions['LegacyInvoices.paymentdate >='] = $dueDateFrom;
            }
            if ($dueDateTo !== '') {
                $legacyConditions['LegacyInvoices.paymentdate <='] = $dueDateTo;
            }

            // Waluta (legacy)
            if (in_array($currencyFilter, $validCurrencies, true)) {
                $legacyConditions['LegacyInvoices.currency'] = $currencyFilter;
            }

            // Zakres kwoty brutto (legacy)
            if ($amountFrom !== '' && is_numeric($amountFrom)) {
                $legacyConditions['LegacyInvoices.total >='] = (float)$amountFrom;
            }
            if ($amountTo !== '' && is_numeric($amountTo)) {
                $legacyConditions['LegacyInvoices.total <='] = (float)$amountTo;
            }

            // Statystyki legacy — używamy $legacyStatsConditions (bez filtra statusu)
            $legacyStatsRows = $LegacyInvoices->find()
                ->select([
                    'total'         => 'LegacyInvoices.total',
                    'netto'         => 'LegacyInvoices.netto',
                    'alreadypaid'   => 'LegacyInvoices.alreadypaid',
                    'remaining'     => 'LegacyInvoices.remaining',
                    'remaining_wal' => 'LegacyInvoices.remaining_wal',
                    'currency'      => 'LegacyInvoices.currency',
                    'paymentstate'  => 'LegacyInvoices.paymentstate',
                    'paymentdate'   => 'LegacyInvoices.paymentdate',
                    'date'          => 'LegacyInvoices.date',
                    'platnosc'      => 'LegacyInvoices.platnosc',
                ])
                ->where($legacyStatsConditions)
                ->disableHydration()
                ->all()
                ->toArray();

            // Statystyki finansowe (sumy, VAT) — disableHydration dla wydajności
            $legacyStats = $this->_computeStats($legacyStatsRows, $today, true);

            // ── Przeterminowane (overdue) — osobne zapytanie z identyczną logiką co filtr listy ──
            // Używamy pełnych encji (nie disableHydration) żeby mieć ten sam format dat co lista,
            // a filtr PHP jest 1:1 skopiowany z filtra listy overdue — gwarantuje spójność liczb.
            $legacyOverdueRows = $LegacyInvoices->find()
                ->where(array_merge($legacyStatsConditions, ['LegacyInvoices.paymentstate !=' => 'paid']))
                ->select(['id', 'paymentdate', 'platnosc', 'date', 'remaining', 'remaining_wal', 'currency'])
                ->all()->toArray();

            $legacyOverduePln   = 0.0;
            $legacyOverdueEur   = 0.0;
            $legacyOverdueCount = 0;

            foreach ($legacyOverdueRows as $leg) {
                $pdate = null;
                if ($leg->paymentdate !== null) {
                    try { $pdate = $leg->paymentdate->format('Y-m-d'); } catch (\Throwable $e) {
                        $pdate = substr((string)$leg->paymentdate, 0, 10) ?: null;
                    }
                }
                if (!$pdate && !empty($leg->platnosc) && preg_match('/(\d+)\s*dni/i', (string)$leg->platnosc, $m)) {
                    try { $ds = $leg->date->format('Y-m-d'); } catch (\Throwable $e) {
                        $ds = substr((string)$leg->date, 0, 10);
                    }
                    if (!empty($ds)) {
                        $c = \DateTime::createFromFormat('Y-m-d', $ds);
                        if ($c) { $c->modify("+{$m[1]} days"); $pdate = $c->format('Y-m-d'); }
                    }
                }
                if ($pdate !== null && $pdate < $today) {
                    $legacyOverdueCount++;
                    if ((string)($leg->currency ?? 'PLN') === 'EUR') {
                        $legacyOverdueEur += (float)($leg->remaining_wal ?? 0);
                    } else {
                        $legacyOverduePln += (float)($leg->remaining ?? 0);
                    }
                }
            }

            // Nadpisz overdue wartości dokładnymi (z pełnych encji)
            $legacyStats['overduePln']   = $legacyOverduePln;
            $legacyStats['overdueEur']   = $legacyOverdueEur;
            $legacyStats['overdueCount'] = $legacyOverdueCount;
            $legacyStats['overdue']      = $legacyOverduePln + $legacyOverdueEur;

            // Merge statystyk
            $stats['count']               += $legacyStats['count'];
            $stats['totalReceivablesPln'] += $legacyStats['totalReceivablesPln'];
            $stats['totalReceivablesEur'] += $legacyStats['totalReceivablesEur'];
            $stats['totalPaidPln']        += $legacyStats['totalPaidPln'];
            $stats['totalPaidEur']        += $legacyStats['totalPaidEur'];
            $stats['totalRemainingPln']   += $legacyStats['totalRemainingPln'];
            $stats['totalRemainingEur']   += $legacyStats['totalRemainingEur'];
            $stats['overduePln']          += $legacyStats['overduePln'];
            $stats['overdueEur']          += $legacyStats['overdueEur'];
            $stats['overdueCount']        += $legacyStats['overdueCount'];
            $stats['vatPln']              += $legacyStats['vatPln'];
            $stats['vatEur']              += $legacyStats['vatEur'];
            $stats['totalReceivables']    += $legacyStats['totalReceivables'];
            $stats['totalPaid']           += $legacyStats['totalPaid'];
            $stats['totalRemaining']      += $legacyStats['totalRemaining'];
            $stats['overdue']             += $legacyStats['overdue'];

            // Zapytanie legacyInvoices z paginacją
            $legacyAllowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            $legacySortCol = in_array($sort, $legacyAllowedSort, true) ? $sort : 'date';
            $legacySortDir = in_array($sort, $legacyAllowedSort, true) ? $sortDir : 'DESC';

            $legacyQ = $LegacyInvoices->find()
                ->where($legacyConditions)
                ->orderBy(['LegacyInvoices.' . $legacySortCol => $legacySortDir]);

            if ($status === 'overdue') {
                // Dla "przeterminowane" pobieramy wszystkich nieopłaconych i filtrujemy w PHP,
                // bo część terminów pochodzi z pola platnosc (null paymentdate).
                $allUnpaid = $legacyQ->all()->toArray();
                $allUnpaid = array_values(array_filter($allUnpaid, function ($leg) use ($today): bool {
                    $pdate = null;
                    if ($leg->paymentdate !== null) {
                        try { $pdate = $leg->paymentdate->format('Y-m-d'); } catch (\Throwable $e) {
                            $pdate = substr((string)$leg->paymentdate, 0, 10) ?: null;
                        }
                    }
                    if (!$pdate && !empty($leg->platnosc) && preg_match('/(\d+)\s*dni/i', (string)$leg->platnosc, $m)) {
                        try { $ds = $leg->date->format('Y-m-d'); } catch (\Throwable $e) {
                            $ds = substr((string)$leg->date, 0, 10);
                        }
                        if (!empty($ds)) {
                            $c = \DateTime::createFromFormat('Y-m-d', $ds);
                            if ($c) { $c->modify("+{$m[1]} days"); $pdate = $c->format('Y-m-d'); }
                        }
                    }
                    return $pdate !== null && $pdate < $today;
                }));
                $legacyTotal  = count($allUnpaid);
                $legacyPages  = (int)ceil($legacyTotal / $limit) ?: 1;
                $legacyInvoices = array_slice($allUnpaid, ($legacyPage - 1) * $limit, $limit);
            } else {
                $legacyTotal    = (clone $legacyQ)->count();
                $legacyPages    = (int)ceil($legacyTotal / $limit) ?: 1;
                $legacyInvoices = $legacyQ->limit($limit)->offset(($legacyPage - 1) * $limit)->all()->toArray();
            }

            // ── Lokalne wpłaty per faktura legacy ────────────────────────────
            $legacyPaymentsByInvoiceId = [];
            if (!empty($legacyInvoices)) {
                $legacyIds = array_column($legacyInvoices, 'id');
                $legacyPaymentRows = $this->fetchTable('LegacyInvoicePayments')->find()
                    ->where(['legacy_invoice_id IN' => $legacyIds])
                    ->select(['id', 'legacy_invoice_id', 'payment_date', 'amount', 'payment_method', 'description'])
                    ->orderByAsc('payment_date')
                    ->all()->toArray();
                foreach ($legacyPaymentRows as $lp) {
                    $legacyPaymentsByInvoiceId[(string)$lp->legacy_invoice_id][] = $lp;
                }
            }
        } else {
            $legacyPaymentsByInvoiceId = [];
        }

        // Ostatnia synchronizacja legacy — dla UI
        $lastSync = null;
        if ($typeFilter === '') {
            $lastSync = $this->fetchTable('LegacySyncLogs')->find()
                ->where(['company_id' => $companyId])
                ->select(['rejestr', 'rok', 'mc', 'records_fetched', 'records_upserted', 'synced_at', 'status'])
                ->orderByDesc('synced_at')
                ->first();
        }

        $this->set(compact(
            'invoices', 'total', 'pages', 'page', 'limit',
            'search', 'status', 'dateFrom', 'dateTo', 'dueDateFrom', 'dueDateTo',
            'currencyFilter', 'amountFrom', 'amountTo', 'bankAccountFilter',
            'typeFilter', 'sort', 'dir',
            'stats', 'bankByInvoice', 'speedByInvoice', 'correctionsByParentId',
            'legacyInvoices', 'legacyTotal', 'legacyPages', 'legacyPage',
            'legacyPaymentsByInvoiceId', 'sourceFilter', 'lastSync',
            'companyBankAccounts'
        ));
        $this->set('title', 'Rozliczenia');
    }

    // ── Dodaj wpłatę do faktury archiwalnej (AJAX / POST) ───────────────────

    public function addLegacyPayment(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $legacyInvoiceId = (string)($this->request->getData('legacy_invoice_id') ?? '');
        $amount          = (float)($this->request->getData('amount') ?? 0);
        $paymentDate     = (string)($this->request->getData('payment_date') ?? date('Y-m-d'));
        $method          = (string)($this->request->getData('payment_method') ?? 'transfer');
        $description     = (string)($this->request->getData('description') ?? '');
        $isAjax          = $this->request->is('ajax') || $this->request->getData('ajax') === '1';

        if ($legacyInvoiceId === '' || $amount <= 0) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Nieprawidłowe dane wpłaty.']));
            }
            $this->Flash->error('Nieprawidłowe dane wpłaty.');
            return $this->redirect(['action' => 'index']);
        }

        $LegacyInvoices = $this->fetchTable('LegacyInvoices');
        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'currency', 'total', 'remaining'])
            ->first();

        if ($invoice === null) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień.']));
            }
            $this->Flash->error('Faktura archiwalna nie istnieje lub brak uprawnień.');
            return $this->redirect(['action' => 'index']);
        }

        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');
        $payment = $LegacyInvoicePayments->newEntity([
            'id'                => \Cake\Utility\Text::uuid(),
            'legacy_invoice_id' => $legacyInvoiceId,
            'company_id'        => $companyId,
            'payment_date'      => $paymentDate,
            'amount'            => $amount,
            'payment_method'    => $method ?: 'transfer',
            'description'       => $description ?: null,
        ]);

        if ($LegacyInvoicePayments->save($payment)) {
            $this->_refreshLegacyPaymentState($legacyInvoiceId);
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => true, 'message' => 'Wpłata zapisana.']));
            }
            $this->Flash->success(sprintf(
                'Wpłata %.2f %s zarejestrowana dla faktury archiwalnej %s.',
                $amount,
                h($invoice->currency ?? 'PLN'),
                h($invoice->fullnumber ?? $legacyInvoiceId)
            ));
        } else {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Nie udało się zapisać wpłaty.']));
            }
            $this->Flash->error('Nie udało się zapisać wpłaty.');
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'index']);
        return $this->redirect($redirect);
    }

    // ── Usuń wpłatę z faktury archiwalnej ────────────────────────────────────

    public function deleteLegacyPayment(string $paymentId): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');
        $payment = $LegacyInvoicePayments->find()
            ->where(['id' => $paymentId, 'company_id' => $companyId])
            ->first();

        if ($payment === null) {
            $this->Flash->error('Wpłata nie istnieje lub brak uprawnień.');
            return $this->redirect($this->request->getData('redirect') ?? ['action' => 'index']);
        }

        $legacyInvoiceId = (string)$payment->legacy_invoice_id;
        if ($LegacyInvoicePayments->delete($payment)) {
            $this->_refreshLegacyPaymentState($legacyInvoiceId);
            $this->Flash->success('Wpłata archiwalna usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć wpłaty.');
        }

        return $this->redirect($this->request->getData('redirect') ?? $this->referer(['action' => 'index']));
    }

    // ── Przelicz stan faktury archiwalnej po zmianie wpłat ───────────────────

    private function _refreshLegacyPaymentState(string $legacyInvoiceId): void
    {
        $LegacyInvoices        = $this->fetchTable('LegacyInvoices');
        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');

        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId])
            ->select(['id', 'total', 'alreadypaid', 'remaining', 'paymentstate'])
            ->first();
        if ($invoice === null) {
            return;
        }

        $localPaid = (float)($LegacyInvoicePayments->find()
            ->select(['s' => $LegacyInvoicePayments->find()->func()->sum('amount')])
            ->where(['legacy_invoice_id' => $legacyInvoiceId])
            ->first()?->s ?? 0);

        $total = (float)$invoice->total;

        $invoice->alreadypaid  = min($localPaid, $total);
        $invoice->remaining    = max(0.0, $total - $localPaid);
        $invoice->paymentstate = match (true) {
            $localPaid <= 0             => 'unpaid',
            $localPaid >= $total - 0.01 => 'paid',
            default                     => 'partial',
        };

        $LegacyInvoices->save($invoice);
    }

    // ── Synchronizacja faktur legacy z zewnętrznego API (AJAX POST) ─────────

    public function syncLegacy(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $userId    = $this->request->getAttribute('identity')?->get('id');
        $userName  = $this->request->getAttribute('identity')?->get('username') ?? '';

        $rejestr = (int)($this->request->getData('rejestr', 130));
        $rok     = (int)($this->request->getData('rok', (int)date('Y')));
        $mc      = $this->request->getData('mc');
        $mc      = ($mc !== '' && $mc !== null) ? (int)$mc : null;

        // Walidacja wejścia
        if ($rejestr <= 0 || $rok < 2010 || $rok > (int)date('Y') + 1) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Nieprawidłowe parametry synchronizacji.']));
        }
        if ($mc !== null && ($mc < 1 || $mc > 12)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Nieprawidłowy miesiąc (1-12).']));
        }

        $LegacySyncLogs = $this->fetchTable('LegacySyncLogs');
        $nowStr = date('Y-m-d H:i:s');

        try {
            $service = new LegacyInvoiceSyncService();
            $result  = $service->syncMonth($rejestr, $rok, $mc, $companyId);

            // Zapisz log sukcesu
            $log = $LegacySyncLogs->newEntity([
                'id'                => Text::uuid(),
                'company_id'        => $companyId,
                'rejestr'           => $rejestr,
                'rok'               => $rok,
                'mc'                => $mc,
                'synced_by_user_id' => $userId,
                'synced_by_name'    => $userName,
                'status'            => 'success',
                'records_fetched'   => $result['fetched'],
                'records_upserted'  => $result['upserted'],
                'records_changed'   => $result['changed'],
                'changes_detail'    => !empty($result['changes']) ? json_encode($result['changes']) : null,
                'error_message'     => null,
                'synced_at'         => $nowStr,
            ]);
            $LegacySyncLogs->save($log);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success'   => true,
                    'fetched'   => $result['fetched'],
                    'upserted'  => $result['upserted'],
                    'changed'   => $result['changed'],
                    'changes'   => $result['changes'],
                    'synced_at' => $nowStr,
                    'message'   => sprintf(
                        'Pobrano %d dokumentów, zapisano %d, zmienił stan: %d.',
                        $result['fetched'],
                        $result['upserted'],
                        $result['changed']
                    ),
                ]));
        } catch (\Throwable $e) {
            // Zapisz log błędu
            $log = $LegacySyncLogs->newEntity([
                'id'                => Text::uuid(),
                'company_id'        => $companyId,
                'rejestr'           => $rejestr,
                'rok'               => $rok,
                'mc'                => $mc,
                'synced_by_user_id' => $userId,
                'synced_by_name'    => $userName,
                'status'            => 'error',
                'records_fetched'   => 0,
                'records_upserted'  => 0,
                'records_changed'   => 0,
                'changes_detail'    => null,
                'error_message'     => $e->getMessage(),
                'synced_at'         => $nowStr,
            ]);
            $LegacySyncLogs->save($log);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'error'   => 'Błąd synchronizacji: ' . $e->getMessage(),
                    'success' => false,
                ]));
        }
    }

    // ── Przelewy bankowe dla faktury archiwalnej (AJAX, GET) ─────────────────

    public function legacyBankTransactions(string $legacyInvoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $LegacyInvoices = $this->fetchTable('LegacyInvoices');
        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'contractor_name', 'contractor_nip', 'date',
                      'remaining', 'total', 'netto', 'remaining_wal', 'total_wal', 'currency', 'exchange_rate'])
            ->first();

        if ($invoice === null) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień']));
        }

        $nip            = $invoice->contractor_nip ?? null;
        $contractorName = $invoice->contractor_name ?? null;
        $invoiceRemaining = (float)($invoice->remaining ?? 0);
        $invoiceTotal     = (float)($invoice->total ?? 0);
        $invoiceCurrency  = (string)($invoice->currency ?? 'PLN');
        $invoiceNetto     = (float)($invoice->netto ?? 0);
        $invoiceRemainWal = (float)($invoice->remaining_wal ?? 0);
        $invoiceTotalWal  = (float)($invoice->total_wal ?? 0);
        $exchangeRate     = (float)($invoice->exchange_rate ?? 0);
        $fullnumber       = (string)($invoice->fullnumber ?? '');

        // Kwota referencyjna PLN: jeśli coś już zapłacono — remaining, wpp total
        $refAmount = $invoiceRemaining > 0.01 ? $invoiceRemaining : $invoiceTotal;

        // Kwota referencyjna w walucie obcej (brutto): dla faktur walutowych
        $refAmountWal = 0.0;
        if ($invoiceCurrency !== 'PLN' && $invoiceNetto > 0.001) {
            $remainBruttoWal = $invoiceRemainWal * $invoiceTotal / $invoiceNetto;
            $refAmountWal = $invoiceRemaining > 0.01 ? $remainBruttoWal : $invoiceTotal;
        }

        // ── Zestaw kwot faktury (do tabelki + do dopasowania) ────────────────
        $vatVal = max(0.0, $invoiceTotal - $invoiceNetto);
        if ($invoiceCurrency !== 'PLN') {
            // Legacy EUR: total/netto są w EUR
            $amounts = [
                'brutto_eur' => round($invoiceTotal, 2),
                'netto_eur'  => round($invoiceNetto, 2),
                'vat_eur'    => round($vatVal, 2),
                'brutto_pln' => $exchangeRate > 0 ? round($invoiceTotal * $exchangeRate, 2) : null,
                'netto_pln'  => $exchangeRate > 0 ? round($invoiceNetto  * $exchangeRate, 2) : null,
                'vat_pln'    => $exchangeRate > 0 ? round($vatVal        * $exchangeRate, 2) : null,
                'rate'       => $exchangeRate > 0 ? $exchangeRate : null,
                'currency'   => $invoiceCurrency,
            ];
        } else {
            $amounts = [
                'brutto_pln' => round($invoiceTotal, 2),
                'netto_pln'  => round($invoiceNetto, 2),
                'vat_pln'    => round($vatVal, 2),
                'currency'   => 'PLN',
            ];
        }

        // Zestaw kwot do porównania z kwotą przelewu (label => wartość)
        $matchAmounts = [];
        if ($invoiceCurrency !== 'PLN') {
            if ($invoiceTotal > 0.01)  $matchAmounts['brutto ' . $invoiceCurrency] = $invoiceTotal;
            if ($invoiceNetto  > 0.01) $matchAmounts['netto '  . $invoiceCurrency] = $invoiceNetto;
            if ($vatVal        > 0.01) $matchAmounts['VAT '    . $invoiceCurrency] = $vatVal;
            if ($exchangeRate  > 0) {
                if ($invoiceTotal > 0.01) $matchAmounts['brutto PLN'] = round($invoiceTotal * $exchangeRate, 2);
                if ($invoiceNetto > 0.01) $matchAmounts['netto PLN']  = round($invoiceNetto  * $exchangeRate, 2);
                if ($vatVal       > 0.01) $matchAmounts['VAT PLN']    = round($vatVal        * $exchangeRate, 2);
            }
        } else {
            if ($invoiceTotal > 0.01) $matchAmounts['brutto PLN'] = $invoiceTotal;
            if ($invoiceNetto > 0.01) $matchAmounts['netto PLN']  = $invoiceNetto;
            if ($vatVal       > 0.01) $matchAmounts['VAT PLN']    = $vatVal;
        }
        if ($refAmount > 0.01) $matchAmounts['PLN pozostało'] = $refAmount;

        // Znormalizowany numer faktury do porównania z tytułem przelewu
        $fullnumberNorm = preg_replace('/[\s\/\-_]/', '', mb_strtolower($fullnumber));

        $BankTransactions = $this->fetchTable('BankTransactions');

        // Locale-agnostic — Cake\I18n\Date (ChronosDate) NIE implementuje DateTimeInterface
        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'format')) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'toDateString')) return $v->toDateString();
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate, $refAmount, $matchAmounts, $fullnumberNorm): array {
            $amount = (float)$tx->amount;

            // Dopasowanie kwotowe — sprawdź wszystkie kwoty faktury (tolerancja 1% lub 1 PLN)
            $amountMatch = false;
            $amountMatchLabel = null;
            foreach ($matchAmounts as $label => $refAmt) {
                if ($refAmt <= 0) continue;
                $diff = abs($amount - $refAmt);
                if ($diff < 1.0 || $diff / $refAmt < 0.01) {
                    $amountMatch = true;
                    $amountMatchLabel = $label;
                    break;
                }
            }

            // Dopasowanie tytułu — czy numer faktury zawiera się w tytule przelewu
            $titleMatch = false;
            if ($fullnumberNorm !== '' && ($tx->title ?? '') !== '') {
                $titleNorm = preg_replace('/[\s\/\-_]/', '', mb_strtolower((string)$tx->title));
                if (str_contains($titleNorm, $fullnumberNorm)) {
                    $titleMatch = true;
                }
            }

            $amountDiff = $refAmount > 0 ? abs($amount - $refAmount) : null;
            return [
                'id'                  => (string)$tx->id,
                'value_date'          => $fmtDate($tx->value_date),
                'amount'              => $amount,
                'direction'           => (string)($tx->direction ?? 'C'),
                'party_name'          => (string)($tx->party_name ?? ''),
                'title'               => (string)($tx->title ?? ''),
                'account_number'      => (string)($tx->account_number ?? ''),
                'match_status'        => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence'    => (int)($tx->match_confidence ?? 0),
                'match_reason'        => (string)($tx->match_reason ?? ''),
                'parsed_inv'          => (string)($tx->parsed_inv ?? ''),
                'amount_match'        => $amountMatch,
                'amount_match_label'  => $amountMatchLabel,
                'title_match'         => $titleMatch,
                'amount_diff'         => $amountDiff !== null ? round($amountDiff, 2) : null,
                'legacy'              => true,
            ];
        };

        // Kandydaci: przelewy pasujące do nazwy/NIP kontrahenta
        $nameOrConditions = [];

        if ($contractorName !== null && $contractorName !== '') {
            $stopWords   = ['sp', 'zoo', 'o.o', 'ltd', 'gmbh', 's.a', 'z', 'i', 'oraz', 'the'];
            $words       = preg_split('/[\s\.,]+/', mb_strtolower($contractorName));
            $significant = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords, true)));
            foreach (array_slice($significant, 0, 3) as $word) {
                $nameOrConditions[] = ['BankTransactions.party_name LIKE' => '%' . $word . '%'];
            }
        }

        if (empty($nameOrConditions) && $nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        } elseif ($nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        }

        // Locale-agnostic parsing (patrz fmtDate w tym pliku)
        $rawDate = $invoice->date;
        $invoiceDateStr = '';
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif (is_object($rawDate) && method_exists($rawDate, 'format')) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif (is_object($rawDate) && method_exists($rawDate, 'toDateString')) {
            $invoiceDateStr = $rawDate->toDateString();
        } elseif ($rawDate) {
            $s = substr((string)$rawDate, 0, 10);
            foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y'] as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, $s);
                if ($dt) { $invoiceDateStr = $dt->format('Y-m-d'); break; }
            }
        }

        $candidates = [];
        if (!empty($nameOrConditions)) {
            $conditions = [
                'BankTransactions.company_id' => $companyId,
                'OR'                          => $nameOrConditions,
            ];
            if ($invoiceDateStr !== '') {
                $conditions['BankTransactions.value_date >='] = $invoiceDateStr;
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
                ->where(['BankTransactions.direction' => 'C'])
                ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                          'account_number', 'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
                ->orderByDesc('value_date')
                ->all()->toArray();
        }

        $mappedCandidates = array_map($mapTx, $candidates);

        // Sortuj: trafienia kwotowe na górze, potem wg daty desc
        usort($mappedCandidates, static function (array $a, array $b): int {
            if ($a['amount_match'] !== $b['amount_match']) {
                return $a['amount_match'] ? -1 : 1;
            }
            return strcmp($b['value_date'], $a['value_date']);
        });

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'nip'             => $nip,
                'contractor'      => $contractorName,
                'legacy'          => true,
                'fullnumber'      => $fullnumber,
                'amounts'         => $amounts,
                'ref_amount'      => $refAmount,
                'ref_amount_wal'  => round($refAmountWal, 2),
                'ref_currency'    => $invoiceCurrency,
                'linked'          => [],
                'candidates'      => $mappedCandidates,
            ]));
    }

    // ── Przelewy bankowe dla kontrahenta faktury (AJAX) ──────────────────────

    public function bankTransactions(string $invoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        // Faktura + kontrahent
        $Invoices = $this->fetchTable('Invoices');
        $invoice  = $Invoices->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip', 'street', 'city', 'zip', 'country',
                                       'email', 'phone', 'account_number']);
                },
            ])
            ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.netto',
                      'Invoices.remaining', 'Invoices.alreadypaid', 'Invoices.currency',
                      'Invoices.currency_exchange', 'Invoices.date', 'Invoices.paymentdate',
                      'Invoices.paymentstate'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień']));
        }

        $contractor        = $invoice->invoice_contractor ?? null;
        $nip               = $contractor->nip ?? null;
        $contractorName    = $contractor->name ?? null;
        $invoiceTotal      = (float)($invoice->total ?? 0);
        $invoiceNetto      = (float)($invoice->netto ?? 0);
        $invoiceRemaining  = (float)($invoice->remaining ?? $invoiceTotal);
        $invoiceCurrency   = (string)($invoice->currency ?? 'PLN');
        $invoiceExchange   = (float)($invoice->currency_exchange ?? 0);
        $invoiceFullnumber = (string)($invoice->fullnumber ?? '');
        $vatVal            = max(0.0, $invoiceTotal - $invoiceNetto);

        // ── Zestaw kwot — analogicznie do /rozliczenia/speed legacy view ─────
        if ($invoiceCurrency !== 'PLN' && $invoiceExchange > 0) {
            // Faktura walutowa: total/netto są w walucie, currency_exchange to kurs do PLN
            $amounts = [
                'brutto_eur' => round($invoiceTotal, 2),
                'netto_eur'  => round($invoiceNetto, 2),
                'vat_eur'    => round($vatVal, 2),
                'brutto_pln' => round($invoiceTotal * $invoiceExchange, 2),
                'netto_pln'  => round($invoiceNetto  * $invoiceExchange, 2),
                'vat_pln'    => round($vatVal        * $invoiceExchange, 2),
                'rate'       => $invoiceExchange,
                'currency'   => $invoiceCurrency,
            ];
        } else {
            $amounts = [
                'brutto_pln' => round($invoiceTotal, 2),
                'netto_pln'  => round($invoiceNetto, 2),
                'vat_pln'    => round($vatVal, 2),
                'currency'   => 'PLN',
            ];
        }

        // ── Dane kontrahenta — pełne, do wyświetlenia w nagłówku modal'a ──
        $contractorData = $contractor ? [
            'name'    => (string)$contractor->name,
            'nip'     => (string)$contractor->nip,
            'street'  => (string)($contractor->street ?? ''),
            'city'    => (string)($contractor->city ?? ''),
            'zip'     => (string)($contractor->zip ?? ''),
            'country' => (string)($contractor->country ?? ''),
            'email'   => (string)($contractor->email ?? ''),
            'phone'   => (string)($contractor->phone ?? ''),
            'account_number' => (string)($contractor->account_number ?? ''),
        ] : null;

        // C) IBAN-y kontrahenta — po NIP odnajdujemy konta w contractor_bank_accounts
        $contractorIbans = [];
        if ($nip !== null && $nip !== '') {
            $cRows = $this->fetchTable('Contractors')->find()
                ->select(['id'])
                ->where(['company_id' => $companyId, 'nip' => $nip])
                ->all();
            $contractorIds = array_column($cRows->toArray(), 'id');
            if (!empty($contractorIds)) {
                $bankRows = $this->fetchTable('ContractorBankAccounts')->find()
                    ->select(['iban'])
                    ->where(['contractor_id IN' => $contractorIds])
                    ->all();
                foreach ($bankRows as $b) {
                    $iban = preg_replace('/\s+/', '', (string)$b->iban);
                    if ($iban !== '') $contractorIbans[] = $iban;
                }
            }
        }

        $BankTransactions = $this->fetchTable('BankTransactions');

        // Locale-agnostic — Cake\I18n\Date (ChronosDate) NIE implementuje DateTimeInterface
        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'format')) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'toDateString')) return $v->toDateString();
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate): array {
            return [
                'id'               => (string)$tx->id,
                'value_date'       => $fmtDate($tx->value_date),
                'amount'           => (float)$tx->amount,
                'currency'         => strtoupper((string)($tx->currency ?? 'PLN')) ?: 'PLN',
                'direction'        => (string)($tx->direction ?? 'C'),
                'party_name'       => (string)($tx->party_name ?? ''),
                'title'            => (string)($tx->title ?? ''),
                'account_number'   => (string)($tx->account_number ?? ''),
                'match_status'     => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence' => (int)($tx->match_confidence ?? 0),
                'match_reason'     => (string)($tx->match_reason ?? ''),
                'parsed_inv'       => (string)($tx->parsed_inv ?? ''),
                'parsed_nip'       => (string)($tx->parsed_nip ?? ''),
            ];
        };

        // Transakcje już powiązane z tą fakturą
        $linked = $BankTransactions->find()
            ->where(['company_id' => $companyId, 'invoice_id' => $invoiceId])
            ->select(['id', 'value_date', 'amount', 'currency', 'direction', 'party_name', 'title',
                      'account_number', 'match_status', 'match_confidence', 'match_reason', 'parsed_inv', 'parsed_nip'])
            ->orderByDesc('value_date')
            ->all()->toArray();

        // Kandydaci — niedopasowane/proponowane pasujące do nazwy kontrahenta / NIP / IBAN
        $candidates = [];
        $linkedIds  = array_column($linked, 'id');

        // D) Rozszerzona lista stop-words + max 5 znaczących słów
        $stopWords = [
            'sp', 'zoo', 'o.o', 'oo', 'spzoo',
            'ltd', 'limited', 'inc', 'incorporated',
            'gmbh', 's.a', 'sa', 's.c', 'sc', 'spk',
            'company', 'corp', 'corporation', 'co',
            'spolka', 'firma', 'jednoosobowa',
            'jdg', 'pphu',
            'group', 'grupa', 'holding',
            'and', 'oraz', 'the', 'und', 'die', 'der', 'das',
            'z', 'i', 'a', 'an', 'or', 'pan', 'pani',
        ];
        $nameOrConditions = [];
        if ($contractorName !== null && $contractorName !== '') {
            $words = preg_split('/[\s\.,\-\/()]+/u', mb_strtolower($contractorName));
            $significant = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords, true)));
            foreach (array_slice($significant, 0, 5) as $word) {
                $nameOrConditions[] = ['BankTransactions.party_name LIKE' => '%' . $word . '%'];
            }
        }
        // NIP jako dodatkowy OR (nie tylko fallback)
        if ($nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        }
        // C) IBAN-y kontrahenta jako dodatkowy OR — najmocniejszy sygnał
        if (!empty($contractorIbans)) {
            $nameOrConditions[] = ['BankTransactions.account_number IN' => $contractorIbans];
        }
        // NAJMOCNIEJSZY sygnał: numer faktury w /INV/ lub w tytule.
        // Format numeru w banku często różni się od formatu w systemie:
        //   system: FW/17/04/2026, bank: FW17/4/26, FW17042026, itd.
        // Generujemy popularne warianty i szukamy LIKE dla każdego.
        if ($invoiceFullnumber !== '') {
            $variations = [$invoiceFullnumber];
            // Bez slashy
            $variations[] = str_replace('/', '', $invoiceFullnumber);
            // Skrócony rok (2026 → 26) — operacja na końcówce
            $shortYear = preg_replace('/(\d{2})(\d{2})$/', '$2', $invoiceFullnumber);
            if ($shortYear && $shortYear !== $invoiceFullnumber) {
                $variations[] = $shortYear;
                $variations[] = str_replace('/', '', $shortYear);
            }
            // Bez zer wiodących w środkowych segmentach (/04/ → /4/)
            $noLeadZero = preg_replace('#/0(\d)(?=/|$)#', '/$1', $invoiceFullnumber);
            if ($noLeadZero && $noLeadZero !== $invoiceFullnumber) {
                $variations[] = $noLeadZero;
                $variations[] = str_replace('/', '', $noLeadZero);
                $shortYearNoZero = preg_replace('/(\d{2})(\d{2})$/', '$2', $noLeadZero);
                if ($shortYearNoZero && $shortYearNoZero !== $noLeadZero) {
                    $variations[] = $shortYearNoZero;
                    $variations[] = str_replace('/', '', $shortYearNoZero);
                }
            }
            $variations = array_unique($variations);

            $nameOrConditions[] = ['BankTransactions.parsed_inv' => $invoiceFullnumber];
            foreach ($variations as $v) {
                $nameOrConditions[] = ['BankTransactions.parsed_inv' => $v];
                $nameOrConditions[] = ['BankTransactions.title LIKE' => '%' . $v . '%'];
            }
        }

        // Data wystawienia faktury — normalizacja Y-m-d (locale-agnostic).
        // UWAGA: Cake\I18n\Date (ChronosDate) NIE implementuje \DateTimeInterface,
        // ale MA metodę format(). (string)$date jest locale-aware → różne wyniki PL/EN.
        $rawDate = $invoice->date;
        $invoiceDateStr = '';
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif (is_object($rawDate) && method_exists($rawDate, 'format')) {
            // Cake\I18n\Date / ChronosDate — locale-agnostic format
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif (is_object($rawDate) && method_exists($rawDate, 'toDateString')) {
            // Chronos fallback
            $invoiceDateStr = $rawDate->toDateString();
        } elseif ($rawDate) {
            // Last resort — string parsing (próbujemy 4 formaty z różnych locale)
            $s  = substr((string)$rawDate, 0, 10);
            foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'm/d/Y'] as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, $s);
                if ($dt) { $invoiceDateStr = $dt->format('Y-m-d'); break; }
            }
            if ($invoiceDateStr === '') {
                \Cake\Log\Log::warning('bankTransactions: cannot parse invoice date string: ' . $s);
            }
        }

        // B) Filtr po kwocie (opcjonalny ±10%) z query param
        $amountFilter    = (string)$this->request->getQuery('amount_filter', '');
        $amountTolerance = (float)$this->request->getQuery('amount_tolerance', '10') / 100.0;

        // ── IBAN history: pobierz znane IBAN-y dla tego kontrahenta z historii ─
        // Bonus do score gdy nowy przelew ma IBAN który już był używany przy
        // potwierdzonych alokacjach z fakturami od tego samego NIPu.
        $ibanHistoryMap = []; // [iban_normalized => confirmed_count]
        if ($nip !== null && $nip !== '') {
            try {
                $histRows = $this->fetchTable('ContractorIbanHistories')->find()
                    ->where([
                        'company_id'     => $companyId,
                        'contractor_nip' => $nip,
                    ])
                    ->select(['iban', 'confirmed_count'])
                    ->disableHydration()
                    ->all();
                foreach ($histRows as $h) {
                    $ibanHistoryMap[(string)$h['iban']] = (int)$h['confirmed_count'];
                }
            } catch (\Exception $e) {
                // Tabela jeszcze nie istnieje (przed migracją) — pomijamy
            }
        }

        if (!empty($nameOrConditions)) {
            $conditions = [
                'BankTransactions.company_id'      => $companyId,
                'BankTransactions.match_status IN' => ['unmatched', 'proposed'],
                'BankTransactions.direction'       => 'C',
                'OR'                               => $nameOrConditions,
            ];
            if ($invoiceDateStr !== '') {
                $conditions['BankTransactions.value_date >='] = $invoiceDateStr;
            }
            if (!empty($linkedIds)) {
                $conditions['BankTransactions.id NOT IN'] = $linkedIds;
            }
            if ($amountFilter === '1' && $invoiceRemaining > 0) {
                $conditions['BankTransactions.amount >='] = round($invoiceRemaining * (1 - $amountTolerance), 2);
                $conditions['BankTransactions.amount <='] = round($invoiceRemaining * (1 + $amountTolerance), 2);
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
                ->select(['id', 'value_date', 'amount', 'currency', 'direction', 'party_name', 'title',
                          'account_number', 'match_status', 'match_confidence', 'match_reason', 'parsed_inv', 'parsed_nip'])
                ->orderByDesc('value_date')
                ->limit(1000)
                ->all()->toArray();
        }

        // A) Smart confidence scoring per kandydat
        $signWords = [];
        if ($contractorName !== null && $contractorName !== '') {
            $w = preg_split('/[\s\.,\-\/()]+/u', mb_strtolower($contractorName));
            $signWords = array_values(array_filter($w, fn($x) => mb_strlen($x) >= 3 && !in_array($x, $stopWords, true)));
        }
        $invoiceDateTs = $invoiceDateStr ? strtotime($invoiceDateStr) : 0;

        // Warianty numeru faktury — żeby match'ować różne formaty (FW/17/04/2026 vs FW17/4/26)
        $fullnumberVariants = [];
        if ($invoiceFullnumber !== '') {
            $fullnumberVariants[] = $invoiceFullnumber;
            $fullnumberVariants[] = str_replace('/', '', $invoiceFullnumber);
            $sy = preg_replace('/(\d{2})(\d{2})$/', '$2', $invoiceFullnumber);
            if ($sy && $sy !== $invoiceFullnumber) {
                $fullnumberVariants[] = $sy;
                $fullnumberVariants[] = str_replace('/', '', $sy);
            }
            $nl = preg_replace('#/0(\d)(?=/|$)#', '/$1', $invoiceFullnumber);
            if ($nl && $nl !== $invoiceFullnumber) {
                $fullnumberVariants[] = $nl;
                $fullnumberVariants[] = str_replace('/', '', $nl);
                $sy2 = preg_replace('/(\d{2})(\d{2})$/', '$2', $nl);
                if ($sy2 && $sy2 !== $nl) {
                    $fullnumberVariants[] = $sy2;
                    $fullnumberVariants[] = str_replace('/', '', $sy2);
                }
            }
            $fullnumberVariants = array_unique($fullnumberVariants);
        }

        $scoreCandidate = function (array $tx) use ($invoiceFullnumber, $fullnumberVariants, $invoiceRemaining, $invoiceCurrency, $invoiceExchange, $nip, $contractorIbans, $ibanHistoryMap, $signWords, $invoiceDateTs): array {
            $score = 0;
            $reasons = [];

            // 1. Numer faktury w /INV/ lub w tytule — sprawdzamy warianty formatu
            $invMatched = false;
            foreach ($fullnumberVariants as $v) {
                if ($tx['parsed_inv'] !== '' && strcasecmp($tx['parsed_inv'], $v) === 0) {
                    $score += 45; $reasons[] = '🎯 nr faktury w /INV/';
                    $invMatched = true;
                    break;
                }
            }
            if (!$invMatched) {
                foreach ($fullnumberVariants as $v) {
                    if (stripos($tx['title'], $v) !== false) {
                        $score += 35; $reasons[] = '🎯 nr faktury w tytule';
                        break;
                    }
                }
            }

            // 2. NIP w /IDC/
            if ($nip !== null && $nip !== '' && $tx['parsed_nip'] === $nip) {
                $score += 25; $reasons[] = '🪪 NIP w /IDC/';
            }

            // 3. IBAN kontrahenta (z tabeli contractor_bank_accounts — ręczne wpisy)
            $txIbanNorm = strtoupper(preg_replace('/[\s\-]/', '', $tx['account_number'] ?? ''));
            if (!empty($contractorIbans) && $txIbanNorm !== ''
                && in_array($txIbanNorm, $contractorIbans, true)) {
                $score += 30; $reasons[] = '🏦 IBAN kontrahenta';
            }

            // 3b. IBAN history — historyczne powiązania (Faza 1 — learning loop)
            // Im więcej razy ten IBAN był używany dla tego NIPu, tym wyższy bonus.
            if ($txIbanNorm !== '' && isset($ibanHistoryMap[$txIbanNorm])) {
                $cnt = $ibanHistoryMap[$txIbanNorm];
                $bonus = min(5 + $cnt * 3, 35); // 1×=8, 2×=11, 5×=20, 10×=35
                $score += $bonus;
                $reasons[] = '📚 IBAN znany (×' . $cnt . ')';
            }

            // 4. Kwota match — KONIECZNA konwersja walut przed porównaniem.
            // 8000 PLN ≠ 8000 EUR! Konwertujemy tx.amount do waluty faktury
            // przez invoice.currency_exchange (kurs faktury).
            if ($invoiceRemaining > 0 && $tx['amount'] > 0) {
                $txAmt    = (float)$tx['amount'];
                $txCurr   = strtoupper((string)($tx['currency'] ?? 'PLN'));
                $invCurr  = strtoupper((string)$invoiceCurrency) ?: 'PLN';
                $rate     = (float)$invoiceExchange;

                $convertible = false;
                $txInInvCurr = null;

                if ($txCurr === $invCurr) {
                    // Te same waluty — porównujemy bezpośrednio
                    $txInInvCurr = $txAmt;
                    $convertible = true;
                } elseif ($rate > 0) {
                    // Różne + mamy kurs → konwertujemy
                    if ($invCurr === 'PLN' && $txCurr !== 'PLN') {
                        $txInInvCurr = $txAmt * $rate;     // EUR * kurs = PLN
                    } elseif ($txCurr === 'PLN' && $invCurr !== 'PLN') {
                        $txInInvCurr = $txAmt / $rate;     // PLN / kurs = EUR
                    } else {
                        // foreign↔foreign — nie umiemy bez 2 kursów
                        $txInInvCurr = null;
                    }
                    $convertible = $txInInvCurr !== null;
                }

                if ($convertible) {
                    $diff = abs($txInInvCurr - $invoiceRemaining) / $invoiceRemaining;
                    $cur  = ($txCurr !== $invCurr) ? ' (' . $txCurr . '→' . $invCurr . ')' : '';
                    if ($diff < 0.001)     { $score += 35; $reasons[] = '💰 dokładna kwota' . $cur; }
                    elseif ($diff <= 0.05) { $score += 30; $reasons[] = '💰 kwota ±5%' . $cur; }
                    elseif ($diff <= 0.10) { $score += 20; $reasons[] = '💰 kwota ±10%' . $cur; }
                    elseif ($diff <= 0.20) { $score += 10; $reasons[] = '💰 kwota ±20%' . $cur; }
                } else {
                    // Niemożliwa konwersja — kara/info żeby user widział że tu jest niezgodność
                    $reasons[] = '⚠ ' . $txCurr . ' vs ' . $invCurr . ' (brak kursu)';
                }
            }

            // 5. Punkty za znaczące słowa nazwy
            $nameLower = mb_strtolower($tx['party_name']);
            $hits = 0;
            foreach ($signWords as $word) {
                if (mb_strpos($nameLower, $word) !== false) $hits++;
            }
            if ($hits > 0) {
                $bonus = min($hits * 5, 20);
                $score += $bonus;
                $reasons[] = '👤 nazwa ×' . $hits;
            }

            // 6. Bliskość daty wystawienia
            if ($invoiceDateTs > 0 && $tx['value_date'] !== '') {
                $diffDays = abs(strtotime($tx['value_date']) - $invoiceDateTs) / 86400;
                if ($diffDays <= 3)       { $score += 8;  $reasons[] = '📅 ≤3 dni'; }
                elseif ($diffDays <= 7)   { $score += 5;  $reasons[] = '📅 ≤7 dni'; }
                elseif ($diffDays <= 14)  { $score += 3; }
            }

            return ['score' => $score, 'reasons' => $reasons];
        };

        $scoredCandidates = array_map(function ($tx) use ($mapTx, $scoreCandidate) {
            $row = $mapTx($tx);
            $s = $scoreCandidate($row);
            $row['match_score']   = $s['score'];
            $row['match_reasons'] = $s['reasons'];
            return $row;
        }, $candidates);

        // Sort po wyliczonej score DESC, drugorzędnie value_date DESC
        usort($scoredCandidates, function ($a, $b) {
            if ($a['match_score'] !== $b['match_score']) return $b['match_score'] - $a['match_score'];
            return strcmp($b['value_date'], $a['value_date']);
        });
        // Top 500 dla performance UI — z 1000 kandydatów z SQL bierzemy najlepsze 500 po score
        $scoredCandidates = array_slice($scoredCandidates, 0, 500);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'nip'               => $nip,
                'contractor'        => $contractorName,
                'contractor_full'   => $contractorData,        // pełne dane do nagłówka modal'a
                'amounts'           => $amounts,               // brutto/netto/VAT EUR+PLN + kurs
                'invoice_remaining' => $invoiceRemaining,
                'invoice_total'     => $invoiceTotal,
                'invoice_currency'  => $invoiceCurrency,
                'invoice_number'    => $invoiceFullnumber,
                'invoice_date'      => $invoiceDateStr,
                'contractor_ibans'  => $contractorIbans,
                'amount_filter'     => $amountFilter === '1',
                'linked'            => array_map($mapTx, $linked),
                'candidates'        => $scoredCandidates,
            ], JSON_UNESCAPED_UNICODE));
    }

    // ── Dodaj wpłatę ─────────────────────────────────────────────────────────

    public function addPayment(): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $invoiceId   = (string)($this->request->getData('invoice_id') ?? '');
        $amount      = (float)($this->request->getData('amount') ?? 0);
        $paymentDate = (string)($this->request->getData('payment_date') ?? date('Y-m-d'));
        $method      = (string)($this->request->getData('payment_method') ?? 'transfer');
        $description = (string)($this->request->getData('description') ?? '');

        if ($invoiceId === '' || $amount <= 0) {
            $this->Flash->error('Nieprawidłowe dane wpłaty.');
            return $this->redirect(['action' => 'index']);
        }

        $Invoices = $this->fetchTable('Invoices');
        $invoice  = $Invoices->find()
            ->where(['id' => $invoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'currency'])
            ->first();

        if ($invoice === null) {
            $this->Flash->error('Faktura nie istnieje lub brak uprawnień.');
            return $this->redirect(['action' => 'index']);
        }

        $InvoicePayments = $this->fetchTable('InvoicePayments');
        $payment = $InvoicePayments->newEntity([
            'id'             => Text::uuid(),
            'invoice_id'     => $invoiceId,
            'payment_date'   => $paymentDate,
            'amount'         => $amount,
            'payment_method' => $method ?: 'transfer',
            'description'    => $description ?: null,
        ]);

        $saved = (bool)$InvoicePayments->save($payment);
        if ($saved) {
            $this->_refreshPaymentState($invoiceId);
        }

        // AJAX: zwróć JSON ze stanem faktury żeby modal mógł odświeżyć się w miejscu
        if ($this->request->is('ajax') || $this->request->is('json')
            || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $this->disableAutoRender();
            $invoiceData = null;
            if ($saved) {
                $inv = $this->fetchTable('Invoices')->find()
                    ->select(['id', 'paymentstate', 'alreadypaid', 'remaining', 'currency'])
                    ->where(['id' => $invoiceId, 'company_id' => $companyId])
                    ->first();
                if ($inv) {
                    $invoiceData = [
                        'id'           => (string)$inv->id,
                        'paymentstate' => (string)$inv->paymentstate,
                        'alreadypaid'  => (float)$inv->alreadypaid,
                        'remaining'    => (float)$inv->remaining,
                        'currency'     => (string)$inv->currency,
                    ];
                }
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'ok'      => $saved,
                    'message' => $saved
                        ? sprintf(__('Wpłata %.2f %s zarejestrowana.'), $amount, $invoice->currency ?? 'PLN')
                        : __('Nie udało się zapisać wpłaty.'),
                    'invoice' => $invoiceData,
                    'errors'  => $saved ? null : $payment->getErrors(),
                ], JSON_UNESCAPED_UNICODE));
        }

        if ($saved) {
            $this->Flash->success(sprintf(
                'Wpłata %.2f %s zarejestrowana dla faktury %s.',
                $amount,
                h($invoice->currency ?? 'PLN'),
                h($invoice->fullnumber ?? $invoiceId)
            ));
        } else {
            $this->Flash->error('Nie udało się zapisać wpłaty: ' . implode(', ', array_keys($payment->getErrors())));
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'index']);
        return $this->redirect($redirect);
    }

    // ── Usuń wpłatę ──────────────────────────────────────────────────────────

    public function deletePayment(string $paymentId): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $InvoicePayments = $this->fetchTable('InvoicePayments');
        $payment = $InvoicePayments->find()
            ->contain(['Invoices' => ['fields' => ['id', 'company_id']]])
            ->where(['InvoicePayments.id' => $paymentId])
            ->first();

        if ($payment === null || (string)$payment->invoice->company_id !== (string)$companyId) {
            $this->Flash->error('Wpłata nie istnieje lub brak uprawnień.');
            return $this->redirect($this->request->getData('redirect') ?? ['action' => 'index']);
        }

        $invoiceId = $payment->invoice_id;
        if ($InvoicePayments->delete($payment)) {
            $this->_refreshPaymentState($invoiceId);
            $this->Flash->success('Wpłata usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć wpłaty.');
        }

        return $this->redirect($this->request->getData('redirect') ?? $this->referer(['action' => 'index']));
    }

    // ── Przelicz stan faktury po zmianie wpłat ───────────────────────────────

    private function _refreshPaymentState(string $invoiceId): void
    {
        $Invoices        = $this->fetchTable('Invoices');
        $InvoicePayments = $this->fetchTable('InvoicePayments');

        $invoice = $Invoices->get($invoiceId, ['fields' => ['id', 'total', 'paymentstate', 'alreadypaid', 'remaining']]);

        $paid = (float)($InvoicePayments->find()
            ->where(['invoice_id' => $invoiceId])
            ->select(['s' => $InvoicePayments->find()->func()->sum('amount')])
            ->first()?->s ?? 0);

        $total = (float)$invoice->total;

        $invoice->alreadypaid  = min($paid, $total);
        $invoice->remaining    = max(0.0, $total - $paid);
        $invoice->paymentstate = match (true) {
            $paid <= 0             => 'unpaid',
            $paid >= $total - 0.01 => 'paid',
            default                => 'partial',
        };

        $Invoices->save($invoice);
    }

    // ── Statystyki ───────────────────────────────────────────────────────────

    // ── Info o kontrahencie z bazy contractors (AJAX) ────────────────────────

    public function contractorInfo(string $invoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        // Pobierz dane kontrahenta z faktury
        $invoice = $this->fetchTable('Invoices')->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip', 'street', 'city', 'zip', 'country', 'email', 'phone']);
                },
            ])
            ->select(['Invoices.id', 'Invoices.fullnumber'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje']));
        }

        $ic = $invoice->invoice_contractor;
        if ($ic === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak danych kontrahenta na fakturze']));
        }

        // Szukaj w tabeli contractors po NIP + company
        $contractor = null;
        $contractorId = null;
        if (!empty($ic->nip)) {
            $found = $this->fetchTable('Contractors')->find()
                ->where(['company_id' => $companyId, 'nip' => $ic->nip])
                ->select(['id', 'name', 'nip', 'email', 'phone', 'street', 'city', 'postal_code', 'country', 'is_active'])
                ->first();

            if ($found !== null) {
                $contractorId = (string)$found->id;
                $contractor   = [
                    'id'          => $contractorId,
                    'name'        => (string)($found->name ?? ''),
                    'nip'         => (string)($found->nip ?? ''),
                    'email'       => (string)($found->email ?? ''),
                    'phone'       => (string)($found->phone ?? ''),
                    'street'      => (string)($found->street ?? ''),
                    'city'        => (string)($found->city ?? ''),
                    'postal_code' => (string)($found->postal_code ?? ''),
                    'country'     => (string)($found->country ?? ''),
                    'is_active'   => (bool)$found->is_active,
                ];
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'invoice_id'   => $invoiceId,
                'fullnumber'   => (string)($invoice->fullnumber ?? ''),
                'invoice_contractor' => [
                    'name'    => (string)($ic->name ?? ''),
                    'nip'     => (string)($ic->nip ?? ''),
                    'street'  => (string)($ic->street ?? ''),
                    'city'    => (string)($ic->city ?? ''),
                    'zip'     => (string)($ic->zip ?? ''),
                    'country' => (string)($ic->country ?? ''),
                    'email'   => (string)($ic->email ?? ''),
                    'phone'   => (string)($ic->phone ?? ''),
                ],
                'contractor_id' => $contractorId,
                'contractor'    => $contractor,
            ]));
    }

    // ── Utwórz kontrahenta z danych faktury (AJAX POST) ──────────────────────

    public function createContractorFromInvoice(string $invoiceId): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $invoice = $this->fetchTable('Invoices')->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip', 'street', 'city', 'zip', 'country', 'email', 'phone', 'vat_prefix', 'vat_eu']);
                },
            ])
            ->select(['Invoices.id'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje']));
        }

        $ic = $invoice->invoice_contractor;
        if ($ic === null || empty($ic->name)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak danych kontrahenta na fakturze']));
        }

        $Contractors = $this->fetchTable('Contractors');

        // Sprawdź czy już istnieje (zabezpieczenie przed duplikate)
        if (!empty($ic->nip)) {
            $existing = $Contractors->find()
                ->where(['company_id' => $companyId, 'nip' => $ic->nip])
                ->select(['id'])
                ->first();

            if ($existing !== null) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success'       => true,
                        'contractor_id' => (string)$existing->id,
                        'message'       => 'Kontrahent już istnieje w bazie.',
                        'already_existed' => true,
                    ]));
            }
        }

        $contractor = $Contractors->newEntity([
            'id'          => \Cake\Utility\Text::uuid(),
            'company_id'  => $companyId,
            'name'        => $ic->name,
            'nip'         => $ic->nip ?: null,
            'vat_prefix'  => $ic->vat_prefix ?: null,
            'vat_eu'      => $ic->vat_eu ?: null,
            'email'       => $ic->email ?: null,
            'phone'       => $ic->phone ?: null,
            'street'      => $ic->street ?: null,
            'city'        => $ic->city ?: null,
            'postal_code' => $ic->zip ?: null,
            'country'     => $ic->country ?: 'PL',
            'is_active'   => true,
        ]);

        if (!$Contractors->save($contractor)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'error'  => 'Nie udało się zapisać kontrahenta.',
                    'errors' => $contractor->getErrors(),
                ]));
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success'       => true,
                'contractor_id' => (string)$contractor->id,
                'message'       => 'Kontrahent został dodany do bazy.',
                'already_existed' => false,
            ]));
    }

    // ── Alokacje przelewów ────────────────────────────────────────────────────

    /**
     * AJAX GET: zwraca alokacje przypisane do faktury (system lub legacy).
     * URL: /rozliczenia/alokacje/{invoiceId}?legacy=1
     */
    public function allocations(string $invoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $isLegacy  = (bool)$this->request->getQuery('legacy');

        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $condition = $isLegacy
            ? ['BankTransactionAllocations.legacy_invoice_id' => $invoiceId]
            : ['BankTransactionAllocations.invoice_id'        => $invoiceId];

        $rows = $Allocations->find()
            ->where(array_merge($condition, ['BankTransactionAllocations.company_id' => $companyId]))
            ->contain([
                'BankTransactions' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'value_date', 'amount', 'currency', 'party_name', 'title', 'account_number']);
                },
            ])
            ->select([
                'BankTransactionAllocations.id',
                'BankTransactionAllocations.bank_transaction_id',
                'BankTransactionAllocations.invoice_payment_id',
                'BankTransactionAllocations.allocated_amount',
                'BankTransactionAllocations.currency',
                'BankTransactionAllocations.allocation_type',
                'BankTransactionAllocations.note',
                'BankTransactionAllocations.created',
            ])
            ->orderByDesc('BankTransactionAllocations.created')
            ->all()->toArray();

        // Locale-agnostic — Cake\I18n\Date (ChronosDate) NIE implementuje DateTimeInterface
        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'format')) return $v->format('Y-m-d');
            if (is_object($v) && method_exists($v, 'toDateString')) return $v->toDateString();
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $result = array_map(static function ($row) use ($fmtDate): array {
            $tx = $row->bank_transaction;
            return [
                'id'               => (string)$row->id,
                'bank_tx_id'       => (string)$row->bank_transaction_id,
                'invoice_payment_id' => $row->invoice_payment_id ? (string)$row->invoice_payment_id : null,
                'allocated_amount' => (float)$row->allocated_amount,
                'currency'         => (string)$row->currency,
                'allocation_type'  => (string)$row->allocation_type,
                'note'             => (string)($row->note ?? ''),
                'created'          => $fmtDate($row->created),
                'tx_date'          => $tx ? $fmtDate($tx->value_date) : null,
                'tx_amount'        => $tx ? (float)$tx->amount : null,
                'tx_currency'      => $tx ? (string)$tx->currency : null,
                'tx_party'         => $tx ? (string)($tx->party_name ?? '') : null,
                'tx_title'         => $tx ? (string)($tx->title ?? '') : null,
            ];
        }, $rows);

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['allocations' => $result]));
    }

    /**
     * AJAX POST: tworzy alokację przelewu do faktury + opcjonalnie wpłatę.
     * Body JSON: bank_transaction_id, invoice_id|legacy_invoice_id, allocated_amount,
     *            currency, allocation_type (gross|net|vat), note
     */
    public function addAllocation(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $data      = (array)$this->request->getData();

        $txId           = trim((string)($data['bank_transaction_id'] ?? ''));
        $invoiceId      = trim((string)($data['invoice_id'] ?? ''));
        $legacyId       = trim((string)($data['legacy_invoice_id'] ?? ''));
        $amount         = (float)($data['allocated_amount'] ?? 0);
        $currency       = strtoupper(trim((string)($data['currency'] ?? 'PLN')));
        $allocationType = (string)($data['allocation_type'] ?? 'gross');
        $note           = trim((string)($data['note'] ?? ''));

        if ($txId === '' || ($invoiceId === '' && $legacyId === '') || $amount <= 0) {
            return $this->_jsonError('Brakujące lub nieprawidłowe dane.');
        }
        if (!in_array($allocationType, ['gross', 'net', 'vat'], true)) {
            $allocationType = 'gross';
        }

        // Weryfikacja przelewu
        $BankTransactions = $this->fetchTable('BankTransactions');
        $tx = $BankTransactions->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->select(['id', 'amount', 'currency', 'value_date'])
            ->first();

        if ($tx === null) {
            return $this->_jsonError('Przelew nie istnieje lub brak uprawnień.');
        }

        // Weryfikacja faktury
        if ($invoiceId !== '') {
            $invoice = $this->fetchTable('Invoices')->find()
                ->where(['id' => $invoiceId, 'company_id' => $companyId])
                ->select(['id', 'total', 'netto', 'remaining', 'currency', 'paymentstate', 'currency_exchange'])
                ->first();
            if ($invoice === null) {
                return $this->_jsonError('Faktura nie istnieje lub brak uprawnień.');
            }
        } else {
            $invoice = $this->fetchTable('LegacyInvoices')->find()
                ->where(['id' => $legacyId, 'company_id' => $companyId])
                ->select(['id', 'total', 'netto', 'remaining', 'remaining_wal', 'currency', 'paymentstate', 'exchange_rate'])
                ->first();
            if ($invoice === null) {
                return $this->_jsonError('Faktura archiwalna nie istnieje lub brak uprawnień.');
            }
        }

        $Allocations = $this->fetchTable('BankTransactionAllocations');

        // Sprawdź czy taka alokacja już istnieje (ten przelew + ta faktura + ten typ)
        $existingCondition = array_merge(
            ['BankTransactionAllocations.bank_transaction_id' => $txId,
             'BankTransactionAllocations.allocation_type'     => $allocationType],
            $invoiceId !== '' ? ['BankTransactionAllocations.invoice_id' => $invoiceId]
                              : ['BankTransactionAllocations.legacy_invoice_id' => $legacyId]
        );
        if ($Allocations->exists($existingCondition)) {
            return $this->_jsonError('Ta alokacja już istnieje (ten przelew, faktura i typ płatności są już połączone).');
        }

        $allocationData = [
            'id'                  => \Cake\Utility\Text::uuid(),
            'company_id'          => $companyId,
            'bank_transaction_id' => $txId,
            'invoice_id'          => $invoiceId !== '' ? $invoiceId : null,
            'legacy_invoice_id'   => $legacyId !== '' ? $legacyId : null,
            'allocated_amount'    => $amount,
            'currency'            => $currency,
            'allocation_type'     => $allocationType,
            'note'                => $note !== '' ? $note : null,
        ];

        $allocation = $Allocations->newEntity($allocationData);
        if (!$Allocations->save($allocation)) {
            return $this->_jsonError('Nie udało się zapisać alokacji.', $allocation->getErrors());
        }

        // ── payment_date — Locale-agnostic format (Cake\I18n\Date NIE implementuje
        // DateTimeInterface, a (string) cast jest locale-aware → walidator ->date()
        // odrzucał save() cicho). Używamy method_exists na format/toDateString.
        $paymentDate = '';
        if ($tx->value_date instanceof \DateTimeInterface) {
            $paymentDate = $tx->value_date->format('Y-m-d');
        } elseif (is_object($tx->value_date) && method_exists($tx->value_date, 'format')) {
            $paymentDate = $tx->value_date->format('Y-m-d');
        } elseif (is_object($tx->value_date) && method_exists($tx->value_date, 'toDateString')) {
            $paymentDate = $tx->value_date->toDateString();
        } elseif ($tx->value_date) {
            $paymentDate = substr((string)$tx->value_date, 0, 10);
        }
        if ($paymentDate === '') {
            $paymentDate = date('Y-m-d');
        }
        $desc = $note !== '' ? $note : ('Przelew bankowy: ' . $paymentDate);

        // ── Utwórz wpłatę i przelicz stan faktury ─────────────────────────────
        $paymentId = null;

        if ($invoiceId !== '') {
            // Faktura systemowa → invoice_payments
            // amount/currency zapisywane w ORYGINALNEJ walucie alokacji
            // (InvoicesTable::recalculatePayments robi konwersję na podstawie tych pól)
            $InvoicePayments = $this->fetchTable('InvoicePayments');
            $payment = $InvoicePayments->newEntity([
                'id'                             => \Cake\Utility\Text::uuid(),
                'invoice_id'                     => $invoiceId,
                'bank_transaction_allocation_id' => (string)$allocation->id,
                'payment_date'                   => $paymentDate,
                'amount'                         => round($amount, 2),
                'currency'                       => $currency,
                'payment_type'                   => $allocationType,
                'payment_method'                 => 'transfer',
                'description'                    => $desc,
            ]);

            if ($InvoicePayments->save($payment)) {
                $paymentId = (string)$payment->id;
                $allocation->invoice_payment_id = $paymentId;
                $Allocations->save($allocation);
                // afterSave na invoice_payments wywołuje InvoicesTable::recalculatePayments
                // (currency-aware). Wywołujemy _recalcInvoicePaymentState jako fallback safe.
                $this->_recalcInvoicePaymentState($invoiceId);
            } else {
                // KRYTYCZNE: bez tego było silent fail (allokacja jest, ale wpłata nie).
                \Cake\Log\Log::error('addAllocation: invoice_payment SAVE FAILED', [
                    'errors'      => $payment->getErrors(),
                    'data'        => $payment->toArray(),
                    'invoice_id'  => $invoiceId,
                    'tx_id'       => $txId,
                    'amount'      => $amount,
                    'currency'    => $currency,
                ]);
                // Rollback alokacji — żeby nie zostawiać orphana
                $Allocations->delete($allocation);
                return $this->_jsonError('Nie udało się zapisać wpłaty.', $payment->getErrors());
            }
        } else {
            // Faktura archiwalna → legacy_invoice_payments (zostaje stary konwert na PLN)
            $plnAmount = $amount;
            if ($currency !== 'PLN' && $allocationType !== 'vat') {
                $rate = (float)($invoice->exchange_rate ?? 0);
                if ($rate > 0) $plnAmount = round($amount * $rate, 2);
            }
            $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');
            $payment = $LegacyInvoicePayments->newEntity([
                'id'                => \Cake\Utility\Text::uuid(),
                'legacy_invoice_id' => $legacyId,
                'company_id'        => $companyId,
                'payment_date'      => $paymentDate,
                'amount'            => $plnAmount,
                'payment_method'    => 'transfer',
                'description'       => $desc,
            ]);

            if ($LegacyInvoicePayments->save($payment)) {
                $paymentId = (string)$payment->id;
                $allocation->invoice_payment_id = $paymentId;
                $Allocations->save($allocation);
                $this->_refreshLegacyPaymentState($legacyId);
            }
        }

        // ── Aktualizacja stanu bank_transaction po dodaniu alokacji ──────────
        // Suma alokacji dla tego tx vs amount → matched/proposed.
        $this->_updateBankTxMatchState($txId, $companyId);

        // ── Learning loop: zapisz IBAN ↔ kontrahent (dla system invoices) ──
        if ($invoiceId !== '') {
            $this->_recordIbanHistoryForAllocation($txId, $invoiceId, $companyId);
        }

        // ── Twardo wymuś recalc faktury — żeby UI od razu pokazywał paid/partial
        // (afterSave na invoice_payments też to robi, ale dla pewności).
        $invoiceStateAfter = null;
        if ($invoiceId !== '') {
            try {
                $this->fetchTable('Invoices')->recalculatePayments($invoiceId);
                $invAfter = $this->fetchTable('Invoices')->find()
                    ->where(['id' => $invoiceId])
                    ->select(['paymentstate', 'alreadypaid', 'remaining'])
                    ->first();
                if ($invAfter) {
                    $invoiceStateAfter = [
                        'paymentstate' => (string)$invAfter->paymentstate,
                        'alreadypaid'  => (float)$invAfter->alreadypaid,
                        'remaining'    => (float)$invAfter->remaining,
                    ];
                }
            } catch (\Exception $e) {
                \Cake\Log\Log::warning('addAllocation: recalc failed - ' . $e->getMessage());
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success'            => true,
                'allocation_id'      => (string)$allocation->id,
                'invoice_payment_id' => $paymentId,
                'invoice_state'      => $invoiceStateAfter, // do podglądu w JS
            ]));
    }

    /**
     * Learning loop: po addAllocation zapisz IBAN ↔ NIP kontrahenta do
     * contractor_iban_history (do scoringu kandydatów przyszłych przelewów).
     */
    private function _recordIbanHistoryForAllocation(string $txId, string $invoiceId, string $companyId): void
    {
        try {
            $BankTxs = $this->fetchTable('BankTransactions');
            $tx = $BankTxs->find()
                ->where(['id' => $txId, 'company_id' => $companyId])
                ->select(['account_number', 'amount'])
                ->first();
            if ($tx === null || empty($tx->account_number)) return;

            $InvoiceContractors = $this->fetchTable('InvoiceContractors');
            $contractor = $InvoiceContractors->find()
                ->where(['invoice_id' => $invoiceId])
                ->select(['nip', 'name'])
                ->first();
            if ($contractor === null || empty($contractor->nip)) return;

            $this->fetchTable('ContractorIbanHistories')->record(
                $companyId,
                (string)$contractor->nip,
                (string)$tx->account_number,
                (string)($contractor->name ?? null),
                (float)($tx->amount ?? 0)
            );
        } catch (\Exception $e) {
            \Cake\Log\Log::warning('IBAN history recording failed: ' . $e->getMessage());
        }
    }

    /**
     * Aktualizuje bank_transaction match_status i invoice_id na podstawie
     * sumy alokacji.
     *   - sum_alloc >= tx.amount → matched, confidence=100
     *   - 0 < sum_alloc < tx.amount → proposed (jest częściowo rozdysponowany)
     *   - tx.invoice_id ustawiamy tylko gdy jest dokładnie 1 alokacja do system invoice
     */
    private function _updateBankTxMatchState(string $txId, string $companyId): void
    {
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $tx = $BankTxs->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->first();
        if ($tx === null) return;

        $allocs = $Allocations->find()
            ->where(['bank_transaction_id' => $txId])
            ->select(['id', 'invoice_id', 'legacy_invoice_id', 'allocated_amount'])
            ->all()->toArray();

        $sumAllocated = 0.0;
        $invoiceIds = [];
        foreach ($allocs as $a) {
            $sumAllocated += (float)$a->allocated_amount;
            if ($a->invoice_id) $invoiceIds[(string)$a->invoice_id] = true;
        }

        $txAmount = (float)$tx->amount;
        $dirty    = false;

        if ($sumAllocated >= $txAmount - 0.01) {
            if ($tx->match_status !== 'matched') {
                $tx->match_status = 'matched';
                $tx->is_matched   = true;
                $dirty = true;
            }
            if ((int)$tx->match_confidence < 100) {
                $tx->match_confidence = 100;
                $dirty = true;
            }
        } elseif ($sumAllocated > 0.01) {
            if ($tx->match_status !== 'proposed') {
                $tx->match_status = 'proposed';
                $tx->is_matched   = false;
                $dirty = true;
            }
        }

        // invoice_id: ustawiamy tylko gdy dokładnie 1 systemowa alokacja
        if (count($invoiceIds) === 1) {
            $newInvId = array_key_first($invoiceIds);
            if ((string)($tx->invoice_id ?? '') !== $newInvId) {
                $tx->invoice_id = $newInvId;
                $dirty = true;
            }
        }

        if ($dirty) $BankTxs->save($tx);
    }

    /**
     * AJAX DELETE: usuwa alokację (i powiązaną wpłatę jeśli istnieje).
     */
    public function deleteAllocation(string $allocationId): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $Allocations = $this->fetchTable('BankTransactionAllocations');
        $allocation  = $Allocations->find()
            ->where(['id' => $allocationId, 'company_id' => $companyId])
            ->first();

        if ($allocation === null) {
            return $this->_jsonError('Alokacja nie istnieje lub brak uprawnień.');
        }

        $invoiceId = $allocation->invoice_id;
        $legacyId  = $allocation->legacy_invoice_id;
        $txId      = $allocation->bank_transaction_id;

        // Usuń powiązaną wpłatę — invoice_payment (systemowa) lub legacy_invoice_payment
        if ($allocation->invoice_payment_id) {
            if ($invoiceId) {
                $this->fetchTable('InvoicePayments')
                    ->deleteAll(['id' => $allocation->invoice_payment_id]);
            } else {
                $this->fetchTable('LegacyInvoicePayments')
                    ->deleteAll(['id' => $allocation->invoice_payment_id]);
            }
        }

        if (!$Allocations->delete($allocation)) {
            return $this->_jsonError('Nie udało się usunąć alokacji.');
        }

        // ── Resetuj bank_transaction — jeśli to BYŁA jedyna alokacja dla tego tx
        // Inaczej re-evaluate state (zostaje 'proposed' jeśli są jeszcze inne).
        if ($txId) {
            $hasOtherAllocs = $Allocations->exists(['bank_transaction_id' => $txId]);
            if (!$hasOtherAllocs) {
                $BankTxs = $this->fetchTable('BankTransactions');
                $tx = $BankTxs->find()
                    ->where(['id' => $txId, 'company_id' => $companyId])
                    ->first();
                if ($tx !== null) {
                    $tx->invoice_id       = null;
                    $tx->is_matched       = false;
                    $tx->match_status     = 'unmatched';
                    $tx->match_confidence = 0;
                    $BankTxs->save($tx);
                }
            } else {
                // Wciąż są alokacje — re-evaluate stan (matched/proposed)
                $this->_updateBankTxMatchState($txId, $companyId);
            }
        }

        // Przelicz stan faktury
        if ($invoiceId) {
            $this->_recalcInvoicePaymentState($invoiceId);
        } elseif ($legacyId) {
            $this->_refreshLegacyPaymentState($legacyId);
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true]));
    }

    /**
     * AJAX GET: zwraca sumę alokacji i pozostałe do przydzielenia dla przelewu.
     */
    public function transactionAllocatedSummary(string $txId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $tx = $this->fetchTable('BankTransactions')->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->select(['id', 'amount', 'currency'])
            ->first();

        if ($tx === null) {
            return $this->_jsonError('Przelew nie istnieje.');
        }

        $allocated = (float)$this->fetchTable('BankTransactionAllocations')
            ->find()
            ->where(['bank_transaction_id' => $txId])
            ->select(['s' => 'SUM(allocated_amount)'])
            ->disableHydration()
            ->first()['s'];

        $txAmount  = (float)$tx->amount;
        $remaining = round($txAmount - $allocated, 4);

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'tx_amount'        => $txAmount,
                'tx_currency'      => (string)$tx->currency,
                'allocated_amount' => round($allocated, 4),
                'remaining_amount' => $remaining,
            ]));
    }

    // ── Pomocnicze ────────────────────────────────────────────────────────────

    private function _jsonError(string $message, array $errors = []): Response
    {
        $body = ['error' => $message];
        if ($errors) {
            $body['errors'] = $errors;
        }
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($body));
    }

    /**
     * Przelicza alreadypaid / remaining / paymentstate dla faktury systemowej.
     * Deferuje do currency-aware InvoicesTable::recalculatePayments — eliminuje
     * podwójną logikę i niespójności.
     */
    private function _recalcInvoicePaymentState(string $invoiceId): void
    {
        try {
            $this->fetchTable('Invoices')->recalculatePayments($invoiceId);
        } catch (\Exception $e) {
            \Cake\Log\Log::warning('_recalcInvoicePaymentState: ' . $e->getMessage());
        }
    }

    /**
     * Oblicza statystyki finansowe dla zestawu wierszy faktur.
     *
     * @param array  $rows      Wiersze z bazy (disableHydration)
     * @param string $today     Data dzisiejsza Y-m-d (do wykrywania przeterminowanych)
     * @param bool   $isLegacy  true = faktury archiwalne (legacy), gdzie total/netto EUR-owych są w EUR
     */
    private function _computeStats(array $rows, string $today, bool $isLegacy = false): array
    {
        $totalReceivablesPln = 0.0;
        $totalReceivablesEur = 0.0;
        $totalPaidPln        = 0.0;
        $totalPaidEur        = 0.0;
        $totalRemainingPln   = 0.0;
        $totalRemainingEur   = 0.0;
        $overduePln          = 0.0;
        $overdueEur          = 0.0;
        $overdueCount        = 0;
        $vatPln              = 0.0;
        $vatEur              = 0.0;

        foreach ($rows as $r) {
            $total        = (float)($r['total']         ?? 0);
            $netto        = (float)($r['netto']         ?? 0);
            $remaining    = (float)($r['remaining']     ?? 0);
            $paid         = (float)($r['alreadypaid']   ?? 0);
            $remainingWal = (float)($r['remaining_wal'] ?? 0);
            $currency     = (string)($r['currency']     ?? 'PLN');
            $state        = (string)($r['paymentstate'] ?? 'unpaid');
            $pdate        = isset($r['paymentdate']) && $r['paymentdate']
                ? (string)(is_string($r['paymentdate'])
                    ? substr($r['paymentdate'], 0, 10)
                    : $r['paymentdate']->format('Y-m-d'))
                : '';

            // Legacy EUR: total/netto są w EUR (brutto/netto EUR), remaining_wal = EUR remaining
            // System: total/netto zawsze PLN niezależnie od waluty faktury
            $isEurBucket = $isLegacy && $currency === 'EUR';

            if ($isEurBucket) {
                // total = EUR brutto, remaining_wal = EUR pozostało
                $paidEur = max(0.0, $total - $remainingWal);
                $totalReceivablesEur += $total;
                $totalPaidEur        += $paidEur;
                $totalRemainingEur   += $remainingWal;
                $vatEur              += max(0.0, $total - $netto);

                if ($state !== 'paid' && $pdate !== '' && $pdate < $today) {
                    $overdueEur += $remainingWal;
                    $overdueCount++;
                }
            } else {
                // total = PLN brutto, remaining = PLN pozostało
                $totalReceivablesPln += $total;
                $totalPaidPln        += $paid;
                $totalRemainingPln   += $remaining;
                $vatPln              += max(0.0, $total - $netto);

                if ($state !== 'paid' && $pdate !== '' && $pdate < $today) {
                    $overduePln += $remaining;
                    $overdueCount++;
                }
            }
        }

        return [
            'count'               => count($rows),
            'totalReceivablesPln' => $totalReceivablesPln,
            'totalReceivablesEur' => $totalReceivablesEur,
            'totalPaidPln'        => $totalPaidPln,
            'totalPaidEur'        => $totalPaidEur,
            'totalRemainingPln'   => $totalRemainingPln,
            'totalRemainingEur'   => $totalRemainingEur,
            'overduePln'          => $overduePln,
            'overdueEur'          => $overdueEur,
            'overdueCount'        => $overdueCount,
            'vatPln'              => $vatPln,
            'vatEur'              => $vatEur,
            // Pola compat (suma PLN + EUR) — do zachowania wstecznej kompatybilności
            'totalReceivables'    => $totalReceivablesPln + $totalReceivablesEur,
            'totalPaid'           => $totalPaidPln + $totalPaidEur,
            'totalRemaining'      => $totalRemainingPln + $totalRemainingEur,
            'overdue'             => $overduePln + $overdueEur,
        ];
    }

    // ── ADMIN: Sprawdzenie integralności bank_tx ↔ allocation ↔ invoice_payment ──

    /**
     * Skanuje dane i wykrywa 5 typów problemów:
     *  A) invoice_payments bez bank_transaction_allocation_id (sieroty z confirmMatch sprzed fixa)
     *  B) bank_transactions matched (manual) bez bank_transaction_allocation
     *  C) bank_transaction_allocations bez invoice_payment_id (brak back-link)
     *  D) invoice_payment.currency != bank_transaction.currency (zła waluta na wpłacie)
     *  E) bank_transactions z auto-match (confidence < 100) — błędnie oznaczone jako "Wpłata"
     */
    public function checkIntegrity(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $Payments     = $this->fetchTable('InvoicePayments');
        $BankTxs      = $this->fetchTable('BankTransactions');
        $Allocations  = $this->fetchTable('BankTransactionAllocations');

        // A) invoice_payments z method=transfer ale bez allocation
        $orphanPayments = $Payments->find()
            ->contain([
                'Invoices' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'fullnumber', 'company_id', 'currency', 'currency_exchange', 'total']);
                },
            ])
            ->where([
                'InvoicePayments.payment_method' => 'transfer',
                'InvoicePayments.bank_transaction_allocation_id IS' => null,
                'Invoices.company_id' => $companyId,
            ])
            ->select([
                'InvoicePayments.id', 'InvoicePayments.invoice_id', 'InvoicePayments.amount',
                'InvoicePayments.currency', 'InvoicePayments.payment_date',
                'InvoicePayments.description', 'InvoicePayments.payment_type',
            ])
            ->all()->toArray();

        // B) bank_transactions matched (manual) bez allocation
        $matchedTxs = $BankTxs->find()
            ->where([
                'BankTransactions.company_id'        => $companyId,
                'BankTransactions.match_status'      => 'matched',
                'BankTransactions.invoice_id IS NOT' => null,
                'BankTransactions.match_confidence >=' => 100,
            ])
            ->select(['id', 'invoice_id', 'amount', 'currency', 'value_date', 'party_name', 'title'])
            ->all()->toArray();

        $matchedIds = array_column($matchedTxs, 'id');
        $allocTxIds = [];
        if (!empty($matchedIds)) {
            $allocRows = $Allocations->find()
                ->where(['bank_transaction_id IN' => $matchedIds])
                ->select(['bank_transaction_id'])
                ->all();
            foreach ($allocRows as $a) {
                $allocTxIds[(string)$a->bank_transaction_id] = true;
            }
        }
        $txsWithoutAlloc = array_filter($matchedTxs, fn($t) => !isset($allocTxIds[(string)$t->id]));

        // C) allocations bez invoice_payment_id
        $orphanAllocs = $Allocations->find()
            ->where([
                'BankTransactionAllocations.company_id' => $companyId,
                'BankTransactionAllocations.invoice_payment_id IS' => null,
                'BankTransactionAllocations.invoice_id IS NOT' => null,
            ])
            ->select(['id', 'bank_transaction_id', 'invoice_id', 'allocated_amount', 'currency', 'allocation_type', 'created'])
            ->all()->toArray();

        // D) Niezgodność waluty: invoice_payment.currency != linked bank_transaction.currency
        // (np. payment zapisane jako PLN, ale tx jest w EUR — stare confirmMatch sprzed fixa)
        $currencyMismatches = [];
        $linkedPayments = $Payments->find()
            ->contain([
                'Invoices' => function ($q) { return $q->select(['id', 'company_id']); },
            ])
            ->where([
                'InvoicePayments.bank_transaction_allocation_id IS NOT' => null,
                'Invoices.company_id' => $companyId,
            ])
            ->select(['InvoicePayments.id', 'InvoicePayments.invoice_id', 'InvoicePayments.amount',
                      'InvoicePayments.currency', 'InvoicePayments.payment_date',
                      'InvoicePayments.bank_transaction_allocation_id'])
            ->all()->toArray();

        if (!empty($linkedPayments)) {
            $allocIds = array_filter(array_map(fn($p) => $p->bank_transaction_allocation_id, $linkedPayments));
            $allocMap = [];
            if (!empty($allocIds)) {
                $allocRows = $Allocations->find()
                    ->where(['id IN' => $allocIds])
                    ->select(['id', 'bank_transaction_id', 'currency'])
                    ->all();
                foreach ($allocRows as $a) {
                    $allocMap[(string)$a->id] = $a;
                }
            }
            $txIds = array_unique(array_filter(array_map(fn($a) => $a->bank_transaction_id, $allocMap)));
            $txMap = [];
            if (!empty($txIds)) {
                $txRows = $BankTxs->find()
                    ->where(['id IN' => $txIds, 'company_id' => $companyId])
                    ->select(['id', 'amount', 'currency'])
                    ->all();
                foreach ($txRows as $t) {
                    $txMap[(string)$t->id] = $t;
                }
            }
            foreach ($linkedPayments as $p) {
                $alloc = $allocMap[(string)$p->bank_transaction_allocation_id] ?? null;
                if (!$alloc) continue;
                $tx = $txMap[(string)$alloc->bank_transaction_id] ?? null;
                if (!$tx) continue;
                $payCurr = strtoupper((string)($p->currency ?? 'PLN'));
                $txCurr  = strtoupper((string)($tx->currency ?? 'PLN'));
                if ($payCurr !== $txCurr) {
                    $p->_real_currency = $txCurr;
                    $p->_real_amount   = (float)$tx->amount;
                    $currencyMismatches[] = $p;
                }
            }
        }

        // E) Auto-matched bank_transactions (confidence < 100) — sprzed wyłączenia auto-match
        // Te są oznaczone jako "matched" ale nie były ręcznie kliknięte przez usera.
        // Najczęściej tworzą fałszywe wpłaty/alokacje z importu MT940.
        $autoMatched = $BankTxs->find()
            ->where([
                'BankTransactions.company_id'        => $companyId,
                'BankTransactions.match_status'      => 'matched',
                'BankTransactions.invoice_id IS NOT' => null,
                'BankTransactions.match_confidence <' => 100,
            ])
            ->select(['id', 'invoice_id', 'amount', 'currency', 'value_date',
                      'party_name', 'match_confidence', 'match_reason',
                      'parsed_inv', 'parsed_nip'])
            ->orderByDesc('value_date')
            ->all()->toArray();

        // Pomocniczne statystyki — liczymy z już-pobranych danych (B + E) + 1 query
        // (Wcześniej osobne count() queries dawały 0 z niejasnego powodu — może cache/scope)
        $manualMatched = count($matchedTxs);       // matched + invoice_id + confidence>=100
        $autoMatchedCount = count($autoMatched);   // matched + invoice_id + confidence<100
        $proposedCount = $BankTxs->find()->where([
            'BankTransactions.company_id'   => $companyId,
            'BankTransactions.match_status' => 'proposed',
        ])->count();

        $stats = [
            'total_matched'        => $manualMatched + $autoMatchedCount,
            'matched_with_invoice' => $manualMatched + $autoMatchedCount,
            'manual_matched'       => $manualMatched,
            'auto_matched'         => $autoMatchedCount,
            'proposed'             => $proposedCount,
        ];

        $this->set(compact('orphanPayments', 'txsWithoutAlloc', 'orphanAllocs', 'currencyMismatches', 'autoMatched', 'stats'));
    }

    /**
     * Naprawia jeden wpis (per row, AJAX-friendly).
     * type ∈ {payment, tx, alloc}
     * id = ID rekordu danego typu
     */
    public function fixOneIntegrity(string $type, string $id): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $result = match ($type) {
            'payment'  => $this->_fixOrphanPayment($id, $companyId),
            'tx'       => $this->_fixTxWithoutAlloc($id, $companyId),
            'alloc'    => $this->_fixOrphanAllocation($id, $companyId),
            'currency' => $this->_fixCurrencyMismatch($id, $companyId),
            'unlink'   => $this->_unlinkAutoMatch($id, $companyId),
            default    => ['ok' => false, 'message' => 'Nieznany typ: ' . $type],
        };

        if ($this->request->is('ajax') || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        if ($result['ok']) {
            $this->Flash->success($result['message'] ?? 'Naprawiono.');
        } else {
            $this->Flash->error($result['message'] ?? 'Nie udało się naprawić.');
        }
        return $this->redirect(['action' => 'checkIntegrity']);
    }

    /**
     * Backfill contractor_iban_history z istniejących potwierdzonych alokacji.
     * Przechodzi po wszystkich bank_transaction_allocations dla system invoices,
     * łączy z bank_transactions (account_number) i invoice_contractors (nip)
     * i wpisuje/inkrementuje rekordy w contractor_iban_history.
     */
    public function backfillIbanHistory(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        // Sprawdzenie czy tabela istnieje (migracja musiała być uruchomiona)
        try {
            $IbanHistory = $this->fetchTable('ContractorIbanHistories');
            $IbanHistory->getSchema(); // wywołuje describeColumns — błąd jeśli brak tabeli
        } catch (\Exception $e) {
            $this->Flash->error('Tabela contractor_iban_history nie istnieje. Uruchom migrację: bin/cake migrations migrate');
            \Cake\Log\Log::error('backfillIbanHistory: ' . $e->getMessage());
            return $this->redirect(['action' => 'checkIntegrity']);
        }

        $Allocations  = $this->fetchTable('BankTransactionAllocations');

        // Pobierz wszystkie alokacje system + tx + contractor — 1 zapytanie
        $rows = $Allocations->find()
            ->contain([
                'BankTransactions' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'account_number', 'amount', 'currency']);
                },
            ])
            ->where([
                'BankTransactionAllocations.company_id'     => $companyId,
                'BankTransactionAllocations.invoice_id IS NOT' => null,
            ])
            ->select([
                'BankTransactionAllocations.id',
                'BankTransactionAllocations.invoice_id',
                'BankTransactionAllocations.bank_transaction_id',
                'BankTransactionAllocations.allocated_amount',
                'BankTransactionAllocations.created',
            ])
            ->all()
            ->toArray();

        // Pobierz wszystkich kontrahentów dla invoice_ids
        $invIds = array_unique(array_filter(array_map(fn($r) => $r->invoice_id, $rows)));
        $contractorMap = []; // invoice_id => ['nip' => ..., 'name' => ...]
        if (!empty($invIds)) {
            $InvoiceContractors = $this->fetchTable('InvoiceContractors');
            $contracts = $InvoiceContractors->find()
                ->where(['invoice_id IN' => $invIds])
                ->select(['invoice_id', 'nip', 'name'])
                ->all();
            foreach ($contracts as $c) {
                $contractorMap[(string)$c->invoice_id] = [
                    'nip'  => (string)($c->nip ?? ''),
                    'name' => (string)($c->name ?? ''),
                ];
            }
        }

        // Agreguj w PHP — żeby ograniczyć ilość zapytań INSERT/UPDATE
        // klucz = nip|iban
        $aggregated = []; // key => ['nip', 'iban', 'name', 'count', 'amount', 'first', 'last']
        foreach ($rows as $r) {
            $tx = $r->bank_transaction ?? null;
            if (!$tx || empty($tx->account_number)) continue;
            $iban = \App\Model\Table\ContractorIbanHistoriesTable::normalizeIban((string)$tx->account_number);
            if ($iban === '') continue;
            $contractor = $contractorMap[(string)$r->invoice_id] ?? null;
            if (!$contractor || empty($contractor['nip'])) continue;
            $nip = $contractor['nip'];
            $key = $nip . '|' . $iban;

            $created = $r->created;
            $createdStr = '';
            if ($created instanceof \DateTimeInterface) $createdStr = $created->format('Y-m-d H:i:s');
            elseif (is_object($created) && method_exists($created, 'format')) $createdStr = $created->format('Y-m-d H:i:s');
            elseif ($created) $createdStr = (string)$created;
            if ($createdStr === '') $createdStr = date('Y-m-d H:i:s');

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'nip'    => $nip,
                    'iban'   => $iban,
                    'name'   => $contractor['name'],
                    'count'  => 0,
                    'amount' => 0.0,
                    'first'  => $createdStr,
                    'last'   => $createdStr,
                ];
            }
            $aggregated[$key]['count']++;
            $aggregated[$key]['amount'] += (float)($r->allocated_amount ?? 0);
            if ($createdStr < $aggregated[$key]['first']) $aggregated[$key]['first'] = $createdStr;
            if ($createdStr > $aggregated[$key]['last'])  $aggregated[$key]['last']  = $createdStr;
        }

        // Zapisz/zaktualizuj rekordy
        $inserted = 0;
        $updated  = 0;
        foreach ($aggregated as $key => $a) {
            $existing = $IbanHistory->find()
                ->where([
                    'company_id'     => $companyId,
                    'contractor_nip' => $a['nip'],
                    'iban'           => $a['iban'],
                ])
                ->first();
            if ($existing !== null) {
                // Aktualizuj count (zsumuj z istniejącym), amount, last_used (max)
                $existing->confirmed_count   = (int)$existing->confirmed_count + $a['count'];
                $existing->total_amount_pln  = (float)$existing->total_amount_pln + $a['amount'];
                if ($a['last'] > (string)$existing->last_used) $existing->last_used = $a['last'];
                if (empty($existing->first_used) || $a['first'] < (string)$existing->first_used) {
                    $existing->first_used = $a['first'];
                }
                if ($a['name'] && empty($existing->contractor_name_snapshot)) {
                    $existing->contractor_name_snapshot = $a['name'];
                }
                if ($IbanHistory->save($existing)) $updated++;
            } else {
                $entity = $IbanHistory->newEntity([
                    'id'                       => \Cake\Utility\Text::uuid(),
                    'company_id'               => $companyId,
                    'contractor_nip'           => $a['nip'],
                    'contractor_name_snapshot' => $a['name'],
                    'iban'                     => $a['iban'],
                    'confirmed_count'          => $a['count'],
                    'total_amount_pln'         => $a['amount'],
                    'first_used'               => $a['first'],
                    'last_used'                => $a['last'],
                ]);
                if ($IbanHistory->save($entity)) $inserted++;
            }
        }

        $this->Flash->success(sprintf(
            'IBAN history backfill: utworzono %d nowych powiązań, zaktualizowano %d. Łącznie pokrycie: %d par (NIP, IBAN).',
            $inserted, $updated, count($aggregated)
        ));
        return $this->redirect(['action' => 'checkIntegrity']);
    }

    /**
     * Bulk: przelicza paymentstate/alreadypaid/remaining wszystkich faktur
     * używając currency-aware InvoicesTable::recalculatePayments.
     * Naprawia ujemne remaining z czasów przed fixem konwersji walut.
     */
    public function refreshAllPaymentStates(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $Invoices  = $this->fetchTable('Invoices');

        $invIds = $Invoices->find()
            ->where(['company_id' => $companyId])
            ->select(['id'])
            ->all()
            ->extract('id')
            ->toList();

        $count = 0;
        foreach ($invIds as $id) {
            try {
                $Invoices->recalculatePayments((string)$id);
                $count++;
            } catch (\Exception $e) {
                \Cake\Log\Log::warning('refreshAllPaymentStates: ' . $e->getMessage());
            }
        }

        $this->Flash->success(sprintf('Przeliczono paymentstate dla %d faktur.', $count));
        return $this->redirect(['action' => 'checkIntegrity']);
    }

    /**
     * Odpinanie zbiorcze wszystkich matched z określonej kategorii.
     * category=B → wszystkie matched bez alokacji (confidence>=100)
     * category=E → wszystkie auto-matched (confidence<100) — równoważne fixIntegrity dla E
     */
    public function unlinkAllCategory(string $category): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $BankTxs   = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $txsToUnlink = [];
        if ($category === 'B') {
            // Manual matched bez alokacji
            $matchedTxs = $BankTxs->find()
                ->where([
                    'BankTransactions.company_id'         => $companyId,
                    'BankTransactions.match_status'       => 'matched',
                    'BankTransactions.invoice_id IS NOT'  => null,
                    'BankTransactions.match_confidence >=' => 100,
                ])
                ->select(['id'])
                ->all()->toArray();
            $matchedIds = array_column($matchedTxs, 'id');
            if (!empty($matchedIds)) {
                $allocRows = $Allocations->find()
                    ->where(['bank_transaction_id IN' => $matchedIds])
                    ->select(['bank_transaction_id'])
                    ->all();
                $hasAlloc = [];
                foreach ($allocRows as $a) $hasAlloc[(string)$a->bank_transaction_id] = true;
                foreach ($matchedTxs as $t) {
                    if (!isset($hasAlloc[(string)$t->id])) $txsToUnlink[] = (string)$t->id;
                }
            }
        } elseif ($category === 'E') {
            $autoTxs = $BankTxs->find()
                ->where([
                    'BankTransactions.company_id'        => $companyId,
                    'BankTransactions.match_status'      => 'matched',
                    'BankTransactions.invoice_id IS NOT' => null,
                    'BankTransactions.match_confidence <' => 100,
                ])
                ->select(['id'])
                ->all();
            foreach ($autoTxs as $t) $txsToUnlink[] = (string)$t->id;
        } else {
            $this->Flash->error('Nieznana kategoria: ' . $category);
            return $this->redirect(['action' => 'checkIntegrity']);
        }

        $unlinked = 0;
        $errors   = [];
        foreach ($txsToUnlink as $txId) {
            $r = $this->_unlinkAutoMatch($txId, $companyId);
            if ($r['ok']) $unlinked++;
            else $errors[] = $r['message'] ?? '';
        }

        $this->Flash->success(sprintf('Odpięto %d przelewów z kategorii %s.', $unlinked, $category));
        if (!empty($errors)) {
            $this->Flash->error('Błędy: ' . implode(' | ', array_slice($errors, 0, 5)));
        }
        return $this->redirect(['action' => 'checkIntegrity']);
    }

    /**
     * Naprawia wszystkie wykryte problemy integralności (bulk).
     */
    public function fixIntegrity(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $Payments    = $this->fetchTable('InvoicePayments');
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $fixed = ['orphan_payments' => 0, 'tx_without_alloc' => 0, 'orphan_allocs' => 0, 'currency_fixes' => 0, 'errors' => []];

        // A) Orphan invoice_payments
        $orphanPayments = $Payments->find()
            ->contain(['Invoices' => function ($q) { return $q->select(['id', 'company_id']); }])
            ->where([
                'InvoicePayments.payment_method' => 'transfer',
                'InvoicePayments.bank_transaction_allocation_id IS' => null,
                'Invoices.company_id' => $companyId,
            ])
            ->all();
        foreach ($orphanPayments as $p) {
            $r = $this->_fixOrphanPayment((string)$p->id, $companyId);
            if ($r['ok']) $fixed['orphan_payments']++;
            else $fixed['errors'][] = 'A: ' . ($r['message'] ?? '');
        }

        // B) Matched bank_tx bez allocation
        $matchedTxs = $BankTxs->find()
            ->where([
                'BankTransactions.company_id'        => $companyId,
                'BankTransactions.match_status'      => 'matched',
                'BankTransactions.invoice_id IS NOT' => null,
                'BankTransactions.match_confidence >=' => 100,
            ])
            ->all();
        foreach ($matchedTxs as $t) {
            if ($Allocations->exists(['bank_transaction_id' => $t->id])) continue;
            $r = $this->_fixTxWithoutAlloc((string)$t->id, $companyId);
            if ($r['ok']) $fixed['tx_without_alloc']++;
            else $fixed['errors'][] = 'B: ' . ($r['message'] ?? '');
        }

        // C) Allocations bez invoice_payment_id
        $orphanAllocs = $Allocations->find()
            ->where([
                'company_id' => $companyId,
                'invoice_payment_id IS' => null,
                'invoice_id IS NOT' => null,
            ])
            ->all();
        foreach ($orphanAllocs as $a) {
            $r = $this->_fixOrphanAllocation((string)$a->id, $companyId);
            if ($r['ok']) $fixed['orphan_allocs']++;
            else $fixed['errors'][] = 'C: ' . ($r['message'] ?? '');
        }

        // D) Niezgodności waluty payment vs tx
        $allLinkedPayments = $Payments->find()
            ->contain(['Invoices' => function ($q) { return $q->select(['id', 'company_id']); }])
            ->where([
                'InvoicePayments.bank_transaction_allocation_id IS NOT' => null,
                'Invoices.company_id' => $companyId,
            ])
            ->all();
        foreach ($allLinkedPayments as $p) {
            $r = $this->_fixCurrencyMismatch((string)$p->id, $companyId);
            if ($r['ok']) $fixed['currency_fixes']++;
            // Note: returns 'Waluty już zgodne' for already-correct → nie liczymy jako błąd
        }

        // E) Odpinanie auto-matched (confidence < 100)
        $autoTxs = $BankTxs->find()
            ->where([
                'BankTransactions.company_id'        => $companyId,
                'BankTransactions.match_status'      => 'matched',
                'BankTransactions.invoice_id IS NOT' => null,
                'BankTransactions.match_confidence <' => 100,
            ])
            ->all();
        $fixed['unlinked'] = 0;
        foreach ($autoTxs as $t) {
            $r = $this->_unlinkAutoMatch((string)$t->id, $companyId);
            if ($r['ok']) $fixed['unlinked']++;
            else $fixed['errors'][] = 'E: ' . ($r['message'] ?? '');
        }

        $this->Flash->success(sprintf(
            'Naprawiono: %d sierot wpłat, %d brakujących alokacji bank_tx, %d back-linków alokacji, %d niezgodności walut, %d odpiętych auto-match.',
            $fixed['orphan_payments'], $fixed['tx_without_alloc'], $fixed['orphan_allocs'], $fixed['currency_fixes'], $fixed['unlinked']
        ));
        if (!empty($fixed['errors'])) {
            $this->Flash->error('Błędy: ' . implode(' | ', array_slice($fixed['errors'], 0, 5)));
        }
        return $this->redirect(['action' => 'checkIntegrity']);
    }

    // ── Per-issue fix helpers (zwracają ['ok'=>bool, 'message'=>string]) ──

    private function _fixOrphanPayment(string $paymentId, string $companyId): array
    {
        $Payments    = $this->fetchTable('InvoicePayments');
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $payment = $Payments->find()
            ->contain(['Invoices' => function ($q) { return $q->select(['id', 'company_id']); }])
            ->where(['InvoicePayments.id' => $paymentId, 'Invoices.company_id' => $companyId])
            ->first();
        if ($payment === null) return ['ok' => false, 'message' => 'Wpłata nie istnieje lub brak uprawnień.'];
        if ($payment->bank_transaction_allocation_id !== null) {
            return ['ok' => false, 'message' => 'Wpłata już ma alokację.'];
        }

        $pAmt = round((float)$payment->amount, 2);
        $minAmt = $pAmt - 0.01;
        $maxAmt = $pAmt + 0.01;

        // ── KROK 0: Czy istnieje już orphan allocation (kat. C) dla tego invoice + kwota?
        // Wtedy tylko linkujemy istniejącą zamiast tworzyć nową.
        $orphanAlloc = $Allocations->find()
            ->where([
                'company_id'              => $companyId,
                'invoice_id'              => $payment->invoice_id,
                'invoice_payment_id IS'   => null,
                'allocated_amount >='     => $minAmt,
                'allocated_amount <='     => $maxAmt,
            ])
            ->first();
        if ($orphanAlloc !== null) {
            // Linkuj istniejącą alokację z tą wpłatą
            $orphanAlloc->invoice_payment_id = (string)$payment->id;
            if (!$Allocations->save($orphanAlloc)) {
                return ['ok' => false, 'message' => 'Link orphan alokacji: ' . json_encode($orphanAlloc->getErrors())];
            }
            $payment->bank_transaction_allocation_id = (string)$orphanAlloc->id;
            // Zsynchronizuj currency z allocation
            $allocCurr = strtoupper((string)$orphanAlloc->currency) ?: 'PLN';
            if (strtoupper((string)$payment->currency) !== $allocCurr) {
                $payment->currency = $allocCurr;
            }
            if (!$Payments->save($payment)) {
                return ['ok' => false, 'message' => 'Link wpłaty: ' . json_encode($payment->getErrors())];
            }
            return ['ok' => true, 'message' => 'Połączono istniejącą alokację z wpłatą (waluta: ' . $allocCurr . ').'];
        }

        // Brak orphan allocation → szukaj bank_tx (strategia 3-poziomowa)
        //   1. Dokładny match: ten sam invoice_id + kwota (±0.01)
        //   2. Fallback: tx z NULL invoice_id + kwota + data ±60 dni od payment_date
        //   3. Ostatnia próba: tx z parsed_inv = invoice.fullnumber + kwota

        // Krok 1: exact match po invoice_id
        $tx = $BankTxs->find()
            ->where([
                'BankTransactions.company_id'  => $companyId,
                'BankTransactions.invoice_id'  => $payment->invoice_id,
                'BankTransactions.amount >='   => $minAmt,
                'BankTransactions.amount <='   => $maxAmt,
            ])
            ->orderByDesc('value_date')
            ->first();
        if ($tx !== null && $Allocations->exists(['bank_transaction_id' => $tx->id])) {
            $tx = null;
        }

        // Krok 2: fallback — tx z NULL invoice_id + ta sama kwota + bliska data
        if ($tx === null) {
            $pDate = $payment->payment_date;
            $pDateStr = '';
            if ($pDate instanceof \DateTimeInterface) {
                $pDateStr = $pDate->format('Y-m-d');
            } elseif (is_object($pDate) && method_exists($pDate, 'format')) {
                $pDateStr = $pDate->format('Y-m-d');
            } elseif ($pDate) {
                $pDateStr = substr((string)$pDate, 0, 10);
            }

            $candidates = $BankTxs->find()
                ->where([
                    'BankTransactions.company_id'         => $companyId,
                    'BankTransactions.invoice_id IS'      => null,
                    'BankTransactions.direction'          => 'C',
                    'BankTransactions.amount >='          => $minAmt,
                    'BankTransactions.amount <='          => $maxAmt,
                ])
                ->orderByDesc('value_date')
                ->all();

            foreach ($candidates as $cand) {
                if ($Allocations->exists(['bank_transaction_id' => $cand->id])) continue;
                // Sprawdź bliskość daty (±60 dni)
                if ($pDateStr) {
                    $cDate = $cand->value_date;
                    $cDateStr = '';
                    if ($cDate instanceof \DateTimeInterface) $cDateStr = $cDate->format('Y-m-d');
                    elseif (is_object($cDate) && method_exists($cDate, 'format')) $cDateStr = $cDate->format('Y-m-d');
                    elseif ($cDate) $cDateStr = substr((string)$cDate, 0, 10);

                    if ($cDateStr) {
                        $diff = abs((new \DateTime($pDateStr))->diff(new \DateTime($cDateStr))->days);
                        if ($diff > 60) continue;
                    }
                }
                $tx = $cand;
                break;
            }
        }

        // Krok 3: ostatnia próba — tx z parsed_inv matching fullnumber + amount
        if ($tx === null) {
            $invoice = $this->fetchTable('Invoices')->find()
                ->where(['id' => $payment->invoice_id])
                ->select(['fullnumber'])
                ->first();
            if ($invoice && $invoice->fullnumber) {
                $tx = $BankTxs->find()
                    ->where([
                        'BankTransactions.company_id'    => $companyId,
                        'BankTransactions.parsed_inv'    => $invoice->fullnumber,
                        'BankTransactions.amount >='     => $minAmt,
                        'BankTransactions.amount <='     => $maxAmt,
                    ])
                    ->orderByDesc('value_date')
                    ->first();
                if ($tx !== null && $Allocations->exists(['bank_transaction_id' => $tx->id])) {
                    $tx = null;
                }
            }
        }

        if ($tx === null) {
            return ['ok' => false, 'message' => 'Brak pasującego przelewu (invoice_id + kwota ' . number_format($pAmt, 2, ',', ' ') . '). Sprawdzone: dokładny match, NULL invoice_id ±60 dni, parsed_inv.'];
        }

        // Jeśli znaleziony tx nie ma invoice_id — ustawiamy go (linkujemy tx → invoice)
        if (empty($tx->invoice_id)) {
            $tx->invoice_id = $payment->invoice_id;
            $BankTxs->save($tx);
        }

        $realCurrency = strtoupper((string)($tx->currency ?? 'PLN')) ?: 'PLN';

        $allocation = $Allocations->newEntity([
            'id'                  => \Cake\Utility\Text::uuid(),
            'company_id'          => $companyId,
            'bank_transaction_id' => (string)$tx->id,
            'invoice_id'          => $payment->invoice_id,
            'invoice_payment_id'  => (string)$payment->id,
            'allocated_amount'    => (float)$tx->amount,    // z bank_tx (źródło prawdy)
            'currency'            => $realCurrency,         // z bank_tx (nie payment!)
            'allocation_type'     => (string)($payment->payment_type ?? 'gross'),
            'note'                => 'Naprawa integralności (z confirmMatch)',
        ]);
        if (!$Allocations->save($allocation)) {
            return ['ok' => false, 'message' => 'Zapis alokacji: ' . json_encode($allocation->getErrors())];
        }

        // Aktualizuj WPŁATĘ — link do alokacji + waluta z tx (poprawienie starej PLN-domyślki)
        $payment->bank_transaction_allocation_id = (string)$allocation->id;
        if (strtoupper((string)$payment->currency) !== $realCurrency) {
            $payment->currency = $realCurrency;
        }
        if (!$Payments->save($payment)) {
            return ['ok' => false, 'message' => 'Link wpłaty: ' . json_encode($payment->getErrors())];
        }
        return ['ok' => true, 'message' => 'Połączono wpłatę z przelewem (waluta: ' . $realCurrency . ').'];
    }

    private function _fixTxWithoutAlloc(string $txId, string $companyId): array
    {
        $Payments    = $this->fetchTable('InvoicePayments');
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $tx = $BankTxs->find()
            ->where(['id' => $txId, 'company_id' => $companyId, 'match_status' => 'matched'])
            ->first();
        if ($tx === null) return ['ok' => false, 'message' => 'Przelew nie istnieje lub niepotwierdzony.'];
        if ($Allocations->exists(['bank_transaction_id' => $tx->id])) {
            return ['ok' => false, 'message' => 'Przelew już ma alokację.'];
        }
        if (empty($tx->invoice_id)) {
            return ['ok' => false, 'message' => 'Przelew nie ma invoice_id.'];
        }

        $paymentDate = '';
        if ($tx->value_date instanceof \DateTimeInterface) $paymentDate = $tx->value_date->format('Y-m-d');
        elseif (is_object($tx->value_date) && method_exists($tx->value_date, 'format')) $paymentDate = $tx->value_date->format('Y-m-d');
        else $paymentDate = substr((string)$tx->value_date, 0, 10) ?: date('Y-m-d');

        $allocation = $Allocations->newEntity([
            'id'                  => \Cake\Utility\Text::uuid(),
            'company_id'          => $companyId,
            'bank_transaction_id' => (string)$tx->id,
            'invoice_id'          => (string)$tx->invoice_id,
            'allocated_amount'    => (float)$tx->amount,
            'currency'            => (string)($tx->currency ?? 'PLN'),
            'allocation_type'     => 'gross',
            'note'                => 'Naprawa integralności (brakująca alokacja)',
        ]);
        if (!$Allocations->save($allocation)) {
            return ['ok' => false, 'message' => 'Zapis alokacji: ' . json_encode($allocation->getErrors())];
        }

        // Szukamy istniejącego "wolnego" payment (z tolerancją ±0.01)
        $tAmt = round((float)$tx->amount, 2);
        $payment = $Payments->find()
            ->where([
                'invoice_id'                        => $tx->invoice_id,
                'amount >='                         => $tAmt - 0.01,
                'amount <='                         => $tAmt + 0.01,
                'bank_transaction_allocation_id IS' => null,
            ])
            ->first();
        if ($payment !== null) {
            $payment->bank_transaction_allocation_id = (string)$allocation->id;
            $Payments->save($payment);
            $allocation->invoice_payment_id = (string)$payment->id;
            $Allocations->save($allocation);
            return ['ok' => true, 'message' => 'Połączono istniejącą wpłatę z przelewem.'];
        }

        // Brak — tworzymy nowy
        $payment = $Payments->newEntity([
            'id'                             => \Cake\Utility\Text::uuid(),
            'invoice_id'                     => (string)$tx->invoice_id,
            'bank_transaction_allocation_id' => (string)$allocation->id,
            'payment_date'                   => $paymentDate,
            'amount'                         => (float)$tx->amount,
            'currency'                       => (string)($tx->currency ?? 'PLN'),
            'payment_type'                   => 'gross',
            'payment_method'                 => 'transfer',
            'description'                    => 'Przelew bankowy: ' . ($tx->bank_reference ?? ''),
        ]);
        if (!$Payments->save($payment)) {
            $Allocations->delete($allocation);
            return ['ok' => false, 'message' => 'Zapis wpłaty: ' . json_encode($payment->getErrors())];
        }
        $allocation->invoice_payment_id = (string)$payment->id;
        $Allocations->save($allocation);
        return ['ok' => true, 'message' => 'Utworzono alokację i wpłatę.'];
    }

    /**
     * Niezgodność waluty: invoice_payment.currency != bank_transaction.currency.
     * Naprawia ustawiając payment.currency i payment.amount na wartości z bank_tx
     * (z proporcjonalnym uwzględnieniem allocated_amount jeśli alokacja częściowa).
     * Aktualizuje też allocation.currency.
     */
    private function _fixCurrencyMismatch(string $paymentId, string $companyId): array
    {
        $Payments    = $this->fetchTable('InvoicePayments');
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $payment = $Payments->find()
            ->contain(['Invoices' => function ($q) { return $q->select(['id', 'company_id']); }])
            ->where(['InvoicePayments.id' => $paymentId, 'Invoices.company_id' => $companyId])
            ->first();
        if ($payment === null) return ['ok' => false, 'message' => 'Wpłata nie istnieje.'];
        if ($payment->bank_transaction_allocation_id === null) {
            return ['ok' => false, 'message' => 'Wpłata nie ma alokacji — użyj typu "payment".'];
        }

        $alloc = $Allocations->find()
            ->where(['id' => $payment->bank_transaction_allocation_id, 'company_id' => $companyId])
            ->first();
        if ($alloc === null) return ['ok' => false, 'message' => 'Alokacja nie istnieje.'];

        $tx = $BankTxs->find()
            ->where(['id' => $alloc->bank_transaction_id, 'company_id' => $companyId])
            ->first();
        if ($tx === null) return ['ok' => false, 'message' => 'Przelew nie istnieje.'];

        $realCurr = strtoupper((string)($tx->currency ?? 'PLN')) ?: 'PLN';
        $payCurr  = strtoupper((string)($payment->currency ?? 'PLN'));
        if ($payCurr === $realCurr) {
            return ['ok' => false, 'message' => 'Waluty już zgodne.'];
        }

        // Ustaw poprawne wartości na payment + allocation
        $payment->currency = $realCurr;
        $payment->amount   = (float)$tx->amount;
        if (!$Payments->save($payment)) {
            return ['ok' => false, 'message' => 'Zapis wpłaty: ' . json_encode($payment->getErrors())];
        }

        $alloc->currency         = $realCurr;
        $alloc->allocated_amount = (float)$tx->amount;
        if (!$Allocations->save($alloc)) {
            return ['ok' => false, 'message' => 'Zapis alokacji: ' . json_encode($alloc->getErrors())];
        }

        return ['ok' => true, 'message' => 'Waluta wpłaty + alokacji ustawiona na ' . $realCurr . ' (' . number_format((float)$tx->amount, 2) . ').'];
    }

    /**
     * Odpina bank_transaction od faktury (cofa auto-match LUB manual):
     *   - Usuwa invoice_payments i bank_transaction_allocations powiązane z tym tx
     *   - Usuwa sieroce invoice_payments (z confirmMatch sprzed allocation fixa)
     *   - Resetuje bank_transaction: invoice_id=NULL, match_status='unmatched',
     *     match_confidence=0, is_matched=false
     *   - Przelicza paymentstate faktury (po usunięciu wpłat)
     */
    private function _unlinkAutoMatch(string $txId, string $companyId): array
    {
        $Payments    = $this->fetchTable('InvoicePayments');
        $BankTxs     = $this->fetchTable('BankTransactions');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $tx = $BankTxs->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->first();
        if ($tx === null) return ['ok' => false, 'message' => 'Przelew nie istnieje.'];

        $invoiceId = $tx->invoice_id;

        // 1. Usuń alokacje i powiązane wpłaty
        $allocs = $Allocations->find()
            ->where(['bank_transaction_id' => $txId, 'company_id' => $companyId])
            ->all();

        $deletedPayments  = 0;
        $deletedAllocs    = 0;
        foreach ($allocs as $alloc) {
            // Najpierw payment (jeśli istnieje)
            if ($alloc->invoice_payment_id) {
                $payment = $Payments->find()
                    ->where(['id' => $alloc->invoice_payment_id])
                    ->first();
                if ($payment !== null && $Payments->delete($payment)) {
                    $deletedPayments++;
                }
            }
            if ($Allocations->delete($alloc)) {
                $deletedAllocs++;
            }
        }

        // 2. Dodatkowo: usuń sieroce invoice_payments z payment_method='transfer'
        //    powiązane z tą fakturą i kwotą (z confirmMatch sprzed allocation fixa)
        if ($invoiceId !== null) {
            $orphanPayments = $Payments->find()
                ->where([
                    'invoice_id'                        => $invoiceId,
                    'amount'                            => (float)$tx->amount,
                    'payment_method'                    => 'transfer',
                    'bank_transaction_allocation_id IS' => null,
                ])
                ->all();
            foreach ($orphanPayments as $op) {
                if ($Payments->delete($op)) $deletedPayments++;
            }
        }

        // 3. Resetuj bank_transaction
        $tx->invoice_id       = null;
        $tx->is_matched       = false;
        $tx->match_status     = 'unmatched';
        $tx->match_confidence = 0;
        if (!$BankTxs->save($tx)) {
            return ['ok' => false, 'message' => 'Reset bank_tx: ' . json_encode($tx->getErrors())];
        }

        // 4. Przelicz stan faktury (alreadypaid/remaining/paymentstate)
        if ($invoiceId !== null) {
            $this->_recalcInvoicePaymentState((string)$invoiceId);
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                'Odpięto przelew (usunięto %d wpłat, %d alokacji).',
                $deletedPayments, $deletedAllocs
            ),
        ];
    }

    private function _fixOrphanAllocation(string $allocId, string $companyId): array
    {
        $Payments    = $this->fetchTable('InvoicePayments');
        $Allocations = $this->fetchTable('BankTransactionAllocations');

        $alloc = $Allocations->find()
            ->where(['id' => $allocId, 'company_id' => $companyId])
            ->first();
        if ($alloc === null) return ['ok' => false, 'message' => 'Alokacja nie istnieje.'];
        if ($alloc->invoice_payment_id !== null) {
            return ['ok' => false, 'message' => 'Alokacja już ma back-link.'];
        }

        // Może istnieje payment wskazujący na tę alokację?
        $payment = $Payments->find()
            ->where(['bank_transaction_allocation_id' => $alloc->id])
            ->first();
        if ($payment === null) {
            // Szukamy po (invoice_id, amount) z tolerancją ±0.01
            $aAmt = round((float)$alloc->allocated_amount, 2);
            $payment = $Payments->find()
                ->where([
                    'invoice_id'                        => $alloc->invoice_id,
                    'amount >='                         => $aAmt - 0.01,
                    'amount <='                         => $aAmt + 0.01,
                    'bank_transaction_allocation_id IS' => null,
                ])
                ->first();
            if ($payment === null) {
                return ['ok' => false, 'message' => 'Brak pasującej wpłaty (invoice_id + kwota).'];
            }
            $payment->bank_transaction_allocation_id = (string)$alloc->id;
            if (!$Payments->save($payment)) {
                return ['ok' => false, 'message' => 'Link wpłaty: ' . json_encode($payment->getErrors())];
            }
        }

        $alloc->invoice_payment_id = (string)$payment->id;
        if (!$Allocations->save($alloc)) {
            return ['ok' => false, 'message' => 'Zapis back-linku: ' . json_encode($alloc->getErrors())];
        }
        return ['ok' => true, 'message' => 'Połączono back-link z wpłatą.'];
    }
}

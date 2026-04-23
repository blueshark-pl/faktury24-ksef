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
            $sortCol = in_array($sort, $allowedSort, true) ? $sort : 'paymentdate';
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
                    'Invoices.total', 'Invoices.alreadypaid', 'Invoices.remaining',
                    'Invoices.currency', 'Invoices.type', 'Invoices.created',
                    'Invoices.sent_at',
                ])
                ->where($baseConditions)
                ->where([
                    'OR' => [
                        ['Invoices.workflow_status IS'  => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ])
                ->orderBy(['Invoices.' . $sortCol => $sortDir, 'Invoices.created' => 'DESC']);

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

        // ── Przelewy bankowe (per faktura) ──────────────────────────────────
        $bankByInvoice = [];
        if (!empty($invoices)) {
            $invoiceIds = array_column($invoices, 'id');

            $bankRows = $this->fetchTable('BankTransactions')->find()
                ->where([
                    'company_id'     => $companyId,
                    'invoice_id IN'  => $invoiceIds,
                    'match_status IN' => ['matched', 'proposed'],
                ])
                ->select(['id', 'invoice_id', 'match_status', 'amount', 'value_date', 'party_name'])
                ->orderByDesc('value_date')
                ->all()->toArray();

            foreach ($bankRows as $bt) {
                $iid = (string)$bt->invoice_id;
                $bankByInvoice[$iid] ??= $bt;
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
                      'remaining', 'total', 'netto', 'remaining_wal', 'total_wal', 'currency'])
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

        // Kwota referencyjna PLN: jeśli coś już zapłacono — remaining, wpp total
        $refAmount = $invoiceRemaining > 0.01 ? $invoiceRemaining : $invoiceTotal;

        // Kwota referencyjna w walucie obcej (brutto): dla faktur walutowych
        // W legacy: total = EUR brutto, netto = EUR netto, remaining_wal = EUR netto remaining
        // Gross EUR remaining = remaining_wal * total / netto (skalowanie przez współczynnik VAT)
        $refAmountWal = 0.0;
        if ($invoiceCurrency !== 'PLN' && $invoiceNetto > 0.001) {
            $remainBruttoWal = $invoiceRemainWal * $invoiceTotal / $invoiceNetto;
            // Gdy faktura nieopłacona → brutto remaining; gdy brak remaining → brutto total (= $invoiceTotal)
            $refAmountWal = $invoiceRemaining > 0.01 ? $remainBruttoWal : $invoiceTotal;
        }

        $BankTransactions = $this->fetchTable('BankTransactions');

        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate, $refAmount): array {
            $amount     = (float)$tx->amount;
            $amountDiff = $refAmount > 0 ? abs($amount - $refAmount) : null;
            // Trafienie kwotowe: różnica < 1% lub < 1 PLN
            $amountMatch = $amountDiff !== null && ($amountDiff < 1.0 || ($refAmount > 0 && $amountDiff / $refAmount < 0.01));
            return [
                'id'               => (string)$tx->id,
                'value_date'       => $fmtDate($tx->value_date),
                'amount'           => $amount,
                'direction'        => (string)($tx->direction ?? 'C'),
                'party_name'       => (string)($tx->party_name ?? ''),
                'title'            => (string)($tx->title ?? ''),
                'account_number'   => (string)($tx->account_number ?? ''),
                'match_status'     => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence' => (int)($tx->match_confidence ?? 0),
                'match_reason'     => (string)($tx->match_reason ?? ''),
                'parsed_inv'       => (string)($tx->parsed_inv ?? ''),
                'amount_match'     => $amountMatch,
                'amount_diff'      => $amountDiff !== null ? round($amountDiff, 2) : null,
                'legacy'           => true,
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

        $rawDate = $invoice->date;
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif ($rawDate) {
            $s  = substr((string)$rawDate, 0, 10);
            $dt = \DateTime::createFromFormat('d.m.Y', $s) ?: \DateTime::createFromFormat('Y-m-d', $s);
            $invoiceDateStr = $dt ? $dt->format('Y-m-d') : '';
        } else {
            $invoiceDateStr = '';
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
                ->limit(50)
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
                    return $q->select(['id', 'invoice_id', 'name', 'nip']);
                },
            ])
            ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.remaining', 'Invoices.currency', 'Invoices.date'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień']));
        }

        $nip            = $invoice->invoice_contractor->nip ?? null;
        $contractorName = $invoice->invoice_contractor->name ?? null;

        $BankTransactions = $this->fetchTable('BankTransactions');

        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate): array {
            return [
                'id'               => (string)$tx->id,
                'value_date'       => $fmtDate($tx->value_date),
                'amount'           => (float)$tx->amount,
                'direction'        => (string)($tx->direction ?? 'C'),
                'party_name'       => (string)($tx->party_name ?? ''),
                'title'            => (string)($tx->title ?? ''),
                'account_number'   => (string)($tx->account_number ?? ''),
                'match_status'     => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence' => (int)($tx->match_confidence ?? 0),
                'match_reason'     => (string)($tx->match_reason ?? ''),
                'parsed_inv'       => (string)($tx->parsed_inv ?? ''),
            ];
        };

        // Transakcje już powiązane z tą fakturą
        $linked = $BankTransactions->find()
            ->where(['company_id' => $companyId, 'invoice_id' => $invoiceId])
            ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                      'account_number', 'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
            ->orderByDesc('value_date')
            ->all()->toArray();

        // Kandydaci — niedopasowane/proponowane pasujące do nazwy kontrahenta lub NIP
        $candidates = [];
        $linkedIds  = array_column($linked, 'id');

        // Buduj warunki wyszukiwania po nazwie (party_name LIKE) + opcjonalnie NIP
        $nameOrConditions = [];

        // Wyciągnij znaczące słowa z nazwy kontrahenta (dł. >= 3, pomijaj "sp.", "z", "o.o." itp.)
        if ($contractorName !== null && $contractorName !== '') {
            $stopWords = ['sp', 'zoo', 'o.o', 'ltd', 'gmbh', 's.a', 'z', 'i', 'oraz', 'the'];
            $words = preg_split('/[\s\.,]+/', mb_strtolower($contractorName));
            $significant = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords, true)));
            foreach (array_slice($significant, 0, 3) as $word) {
                $nameOrConditions[] = ['BankTransactions.party_name LIKE' => '%' . $word . '%'];
            }
        }

        // Fallback na NIP jeśli nie ma nazwy
        if (empty($nameOrConditions) && $nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        }

        // Data wystawienia faktury — przelew musi być >= tej daty
        // Normalizuj do Y-m-d niezależnie od formatu (DateTimeInterface lub string d.m.Y)
        $rawDate = $invoice->date;
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif ($rawDate) {
            $s  = substr((string)$rawDate, 0, 10);
            $dt = \DateTime::createFromFormat('d.m.Y', $s) ?: \DateTime::createFromFormat('Y-m-d', $s);
            $invoiceDateStr = $dt ? $dt->format('Y-m-d') : '';
        } else {
            $invoiceDateStr = '';
        }

        if (!empty($nameOrConditions)) {
            $conditions = [
                'BankTransactions.company_id'      => $companyId,
                'BankTransactions.match_status IN' => ['unmatched', 'proposed'],
                'OR'                               => $nameOrConditions,
            ];
            if ($invoiceDateStr !== '') {
                $conditions['BankTransactions.value_date >='] = $invoiceDateStr;
            }
            if (!empty($linkedIds)) {
                $conditions['BankTransactions.id NOT IN'] = $linkedIds;
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
                ->where(['BankTransactions.direction' => 'C'])
                ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                          'account_number', 'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
                ->orderByDesc('value_date')
                ->limit(30)
                ->all()->toArray();
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'nip'        => $nip,
                'contractor' => $contractorName,
                'linked'     => array_map($mapTx, $linked),
                'candidates' => array_map($mapTx, $candidates),
            ]));
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

        if ($InvoicePayments->save($payment)) {
            $this->_refreshPaymentState($invoiceId);
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
}

<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Dashboard analytics — czyta z operational_events, speed_orders, invoices.
 *
 * Akcje:
 *   index  GET /analytics
 */
class AnalyticsController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $days = (int)$this->request->getQuery('days', 90);
        if ($days < 7) $days = 7;
        if ($days > 365) $days = 365;
        $from = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d');

        $SO = $this->fetchTable('SpeedOrders');
        $Invoices = $this->fetchTable('Invoices');
        $OE = $this->fetchTable('OperationalEvents');

        // === Top 10 tras ===
        $topRoutes = [];
        try {
            $rows = $SO->find()
                ->select([
                    'route' => 'CONCAT(COALESCE(place_from_name, "?"), " → ", COALESCE(place_to_name, "?"))',
                    'cnt'   => 'COUNT(*)',
                ])
                ->where([
                    'company_id' => $companyId,
                    'date_doc >=' => $from,
                    'place_from_name IS NOT' => null,
                    'place_to_name IS NOT' => null,
                ])
                ->group(['route'])
                ->orderByDesc('cnt')
                ->limit(10)
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $topRoutes[] = ['route' => $r['route'], 'count' => (int)$r['cnt']];
            }
        } catch (\Throwable) {}

        // === Top klienci (po ilosci zlecen + suma faktur) ===
        $topClients = [];
        try {
            $rows = $SO->find()
                ->select([
                    'buyer_name' => 'buyer_name',
                    'buyer_nip' => 'buyer_nip',
                    'cnt' => 'COUNT(*)',
                ])
                ->where([
                    'company_id' => $companyId,
                    'date_doc >=' => $from,
                    'buyer_nip IS NOT' => null,
                ])
                ->group(['buyer_nip', 'buyer_name'])
                ->orderByDesc('cnt')
                ->limit(10)
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $topClients[] = [
                    'name' => (string)($r['buyer_name'] ?? ''),
                    'nip'  => (string)($r['buyer_nip'] ?? ''),
                    'orders_count' => (int)$r['cnt'],
                ];
            }
        } catch (\Throwable) {}

        // === Trend miesieczny — ilosc zlecen ===
        $monthlyTrend = [];
        try {
            $rows = $SO->find()
                ->select([
                    'month' => 'DATE_FORMAT(date_doc, "%Y-%m")',
                    'cnt' => 'COUNT(*)',
                ])
                ->where(['company_id' => $companyId, 'date_doc >=' => $from])
                ->group(['month'])
                ->orderByAsc('month')
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $monthlyTrend[] = ['month' => (string)$r['month'], 'count' => (int)$r['cnt']];
            }
        } catch (\Throwable) {}

        // === Trend miesieczny — suma faktur w PLN (przelicz walutowe) ===
        $invoicesTrend = [];
        try {
            $rows = $Invoices->find()
                ->select([
                    'month' => 'DATE_FORMAT(date, "%Y-%m")',
                    'sum_pln' => "SUM(CASE WHEN currency = 'PLN' THEN total ELSE total * COALESCE(currency_exchange, 1) END)",
                    'cnt' => 'COUNT(*)',
                ])
                ->where([
                    'company_id' => $companyId,
                    'date >=' => $from,
                    'workflow_status' => 'issued',
                ])
                ->group(['month'])
                ->orderByAsc('month')
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $invoicesTrend[] = [
                    'month'   => (string)$r['month'],
                    'sum_pln' => round((float)$r['sum_pln'], 2),
                    'count'   => (int)$r['cnt'],
                ];
            }
        } catch (\Throwable) {}

        // === Aktywnosc operacyjna (z operational_events) ===
        $eventStats = [];
        try {
            $rows = $OE->find()
                ->select([
                    'entity_type' => 'entity_type',
                    'event_name' => 'event_name',
                    'cnt' => 'COUNT(*)',
                ])
                ->where([
                    'company_id' => $companyId,
                    'created >=' => $from,
                ])
                ->group(['entity_type', 'event_name'])
                ->orderByDesc('cnt')
                ->limit(20)
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $eventStats[] = [
                    'entity' => (string)$r['entity_type'],
                    'event'  => (string)$r['event_name'],
                    'count'  => (int)$r['cnt'],
                ];
            }
        } catch (\Throwable) {}

        // === Kluczowe wskazniki (KPI) ===
        $kpi = [
            'orders_total'   => 0,
            'invoices_total' => 0,
            'invoices_sum_pln' => 0.0,
            'avg_order_price_pln' => 0.0,
            'unpaid_pln' => 0.0,
        ];
        try {
            $kpi['orders_total'] = $SO->find()
                ->where(['company_id' => $companyId, 'date_doc >=' => $from])
                ->count();
            $kpi['invoices_total'] = $Invoices->find()
                ->where(['company_id' => $companyId, 'date >=' => $from, 'workflow_status' => 'issued'])
                ->count();
            $sumRow = $Invoices->find()
                ->select(['sum_pln' => "SUM(CASE WHEN currency = 'PLN' THEN total ELSE total * COALESCE(currency_exchange, 1) END)"])
                ->where(['company_id' => $companyId, 'date >=' => $from, 'workflow_status' => 'issued'])
                ->disableHydration()
                ->first();
            $kpi['invoices_sum_pln'] = round((float)($sumRow['sum_pln'] ?? 0), 2);
            if ($kpi['invoices_total'] > 0) {
                $kpi['avg_order_price_pln'] = round($kpi['invoices_sum_pln'] / $kpi['invoices_total'], 2);
            }
            $unpaidRow = $Invoices->find()
                ->select(['sum' => "SUM(CASE WHEN currency = 'PLN' THEN remaining ELSE remaining * COALESCE(currency_exchange, 1) END)"])
                ->where(['company_id' => $companyId, 'paymentstate IN' => ['unpaid', 'partial']])
                ->disableHydration()
                ->first();
            $kpi['unpaid_pln'] = round((float)($unpaidRow['sum'] ?? 0), 2);
        } catch (\Throwable) {}

        $this->set(compact('kpi', 'topRoutes', 'topClients', 'monthlyTrend', 'invoicesTrend', 'eventStats', 'days'));
        $this->set('title', 'Analytics');
    }
}

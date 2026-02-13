<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;

class DashboardController extends AppController
{
    public function index(): void
    {
        $today = FrozenDate::today();
        $monthStart = new FrozenDate('first day of this month');


        // poprawnie:
        $prevMonthStart = (clone $monthStart)->subMonths(1);
        $prevMonthEnd   = (clone $monthStart)->subDays(1);

        // --- KPI (symulacja) ---
        $kpis = [
            'currency'          => 'PLN',
            'revenue_month'     => 128_540.35,
            'revenue_prevmonth' => 109_210.10,
            'paid_rate'         => 0.82,    // 82% faktur opłaconych w tym miesiącu
            'avg_payment_days'  => 7.4,     // średni czas spłaty
            'overdue_count'     => 12,
            'overdue_total'     => 19_880.00,
            'draft_count'       => 5,
            'issued_count'      => 67,
            'received_count'    => 54,      // faktury otrzymane (zakupowe)
        ];

        // --- Trend miesięczny (ostatnie 12 mies.) ---
        // Później podmienisz na sumy z GROUP BY DATE_TRUNC('month', date)
        $months = [];
        $revenueSeries = [];
        $paidSeries = [];
        for ($i = 11; $i >= 0; $i--) {
            $label = (clone $today)->subMonths($i)->format('M y');
            $months[] = $label;
            $base = 80_000 + (mt_rand(0, 50_000));
            $revenueSeries[] = $base;
            $paidSeries[] = $base * (0.78 + mt_rand(0, 12) / 100); // 78–90%
        }

        // --- A/B/C: Najlepsi kontrahenci po sprzedaży (Top 5) ---
        $topContractors = [
            ['name' => 'ACME Sp. z o.o.',          'nip' => '525-00-00-000', 'value' => 38_120.00, 'count' => 7],
            ['name' => 'FutureTech S.A.',          'nip' => '113-22-33-444', 'value' => 27_905.50, 'count' => 6],
            ['name' => 'Kowalski i Wspólnicy',     'nip' => '657-19-88-123', 'value' => 19_740.00, 'count' => 5],
            ['name' => 'MediaCraft sp.k.',         'nip' => '521-77-11-222', 'value' => 12_990.00, 'count' => 3],
            ['name' => 'Nordic Logistic Polska',   'nip' => '778-55-44-333', 'value' => 11_210.00, 'count' => 4],
        ];

        // --- Aging (przeterminowane należności) ---
        $aging = [
            ['bucket' => '0–7 dni',   'count' => 6,  'amount' => 6_210.00],
            ['bucket' => '8–14 dni',  'count' => 3,  'amount' => 4_380.00],
            ['bucket' => '15–30 dni', 'count' => 2,  'amount' => 5_100.00],
            ['bucket' => '31+ dni',   'count' => 1,  'amount' => 4_190.00],
        ];

        // --- Ostatnie faktury sprzedażowe (5 szt.) ---
        $recentInvoices = [
            [
                'id' => 'a1', 'fullnumber' => 'FV/10/00125/2025', 'date' => $today,
                'contractor' => 'ACME Sp. z o.o.', 'total' => 12_300.00, 'currency' => 'PLN', 'state' => 'paid',
            ],
            [
                'id' => 'a2', 'fullnumber' => 'FV/10/00126/2025', 'date' => $today->subDays(1),
                'contractor' => 'FutureTech S.A.', 'total' => 8_950.00, 'currency' => 'PLN', 'state' => 'unpaid',
            ],
            [
                'id' => 'a3', 'fullnumber' => 'FV/10/00127/2025', 'date' => $today->subDays(2),
                'contractor' => 'Nordic Logistic Polska', 'total' => 4_199.99, 'currency' => 'PLN', 'state' => 'partial',
            ],
            [
                'id' => 'a4', 'fullnumber' => 'FV/10/00128/2025', 'date' => $today->subDays(3),
                'contractor' => 'MediaCraft sp.k.', 'total' => 2_499.00, 'currency' => 'PLN', 'state' => 'overdue',
            ],
            [
                'id' => 'a5', 'fullnumber' => 'FV/10/00129/2025', 'date' => $today->subDays(4),
                'contractor' => 'Kowalski i Wspólnicy', 'total' => 5_700.00, 'currency' => 'PLN', 'state' => 'paid',
            ],
        ];

        // --- Zadania / przypomnienia (np. do windykacji, wysyłki) ---
        $todos = [
            ['text' => 'Wyślij przypomnienia o płatności do 4 kontrahentów', 'severity' => 'warning'],
            ['text' => 'Sprawdź faktury otrzymane z KSeF (brak połączenia)', 'severity' => 'info'],
            ['text' => 'Uzupełnij NIP dla 2 kontrahentów', 'severity' => 'secondary'],
        ];

        // --- Alert o symulacji/KSeF ---
        $alerts = [
            'ksef' => [
                'type' => 'warning',
                'title' => 'Integracja z KSeF nieaktywna',
                'text'  => 'To są dane poglądowe (symulacja). Po połączeniu z KSeF pojawią się rzeczywiste „faktury otrzymane”.',
            ],
        ];

        // Porównania miesiąc do miesiąca
        $growth = [
            'revenue_mom' => $kpis['revenue_prevmonth'] > 0
                ? ($kpis['revenue_month'] - $kpis['revenue_prevmonth']) / $kpis['revenue_prevmonth']
                : 0.0,
            'period' => [
                'current' => [$monthStart->format('Y-m-d'), $today->format('Y-m-d')],
                'prev'    => [$prevMonthStart->format('Y-m-d'), $prevMonthEnd->format('Y-m-d')],
            ],
        ];

        $this->set(compact(
            'kpis', 'months', 'revenueSeries', 'paidSeries',
            'topContractors', 'aging', 'recentInvoices',
            'todos', 'alerts', 'growth'
        ));
    }
}

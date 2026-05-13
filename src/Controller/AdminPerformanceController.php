<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Admin — dashboard wydajności pracowników.
 *
 * Agreguje dane z:
 *  - speed_orders.pol_by / pod_by / fk_by / fs_by (kto oznaczył checkbox)
 *  - user_action_logs (POST'y do controllerów na fakturach/zleceniach)
 *  - user_login_logs (aktywność)
 *
 * Nie wymaga nowej migracji — używa istniejących tabel.
 */
class AdminPerformanceController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($r = $this->requireAdmin()) {
            $event->setResult($r);
        }
    }

    public function index(): ?Response
    {
        // Period: 7d, 30d, 90d, all
        $period = (string)$this->request->getQuery('period', '30d');
        $sinceMap = [
            '7d'  => '-7 days',
            '30d' => '-30 days',
            '90d' => '-90 days',
            'all' => '-10 years',
        ];
        $sinceExpr = $sinceMap[$period] ?? '-30 days';
        $since = date('Y-m-d 00:00:00', strtotime($sinceExpr));

        $conn = $this->fetchTable('Users')->getConnection();

        // 1) Speed orders — kto oznaczał POL/POD/FK/FS w okresie
        // Zliczamy po pol_at/pod_at — jeśli set w okresie + by nie null
        $speedStats = $conn->execute(
            "SELECT by_col AS username, by_col, action_name, COUNT(*) AS cnt FROM (
                SELECT pol_by AS by_col, 'POL' AS action_name FROM speed_orders WHERE pol_by IS NOT NULL AND pol_at >= :since
                UNION ALL
                SELECT pod_by AS by_col, 'POD' AS action_name FROM speed_orders WHERE pod_by IS NOT NULL AND pod_at >= :since
                UNION ALL
                SELECT fk_by  AS by_col, 'FK'  AS action_name FROM speed_orders WHERE fk_by  IS NOT NULL AND fk_at  >= :since
                UNION ALL
                SELECT fs_by  AS by_col, 'FS'  AS action_name FROM speed_orders WHERE fs_by  IS NOT NULL AND fs_at  >= :since
             ) t
             GROUP BY by_col, action_name
             ORDER BY by_col, action_name",
            ['since' => $since]
        )->fetchAll('assoc');

        // Pivot do struktury username → [POL=>X, POD=>Y, FK=>Z, FS=>W]
        $speedByUser = [];
        foreach ($speedStats as $row) {
            $u = (string)$row['username'];
            $speedByUser[$u][$row['action_name']] = (int)$row['cnt'];
        }

        // 2) Faktury — z user_action_logs (POST do Invoices::add*)
        $invoiceStats = $conn->execute(
            "SELECT username, COUNT(*) AS cnt
             FROM user_action_logs
             WHERE controller = 'Invoices' AND action LIKE 'add%' AND method = 'POST' AND created >= :since
             GROUP BY username
             ORDER BY cnt DESC",
            ['since' => $since]
        )->fetchAll('assoc');

        // 3) Logowania w okresie per user
        $loginStats = $conn->execute(
            "SELECT username, COUNT(*) AS cnt
             FROM user_login_logs
             WHERE logged_at >= :since
             GROUP BY username
             ORDER BY cnt DESC",
            ['since' => $since]
        )->fetchAll('assoc');

        // KPI ogółem
        $stats = [
            'speed_pol' => (int)$conn->execute("SELECT COUNT(*) c FROM speed_orders WHERE pol_at >= :since", ['since' => $since])->fetch()[0],
            'speed_pod' => (int)$conn->execute("SELECT COUNT(*) c FROM speed_orders WHERE pod_at >= :since", ['since' => $since])->fetch()[0],
            'invoices'  => (int)$conn->execute("SELECT COUNT(*) c FROM invoices WHERE created >= :since", ['since' => $since])->fetch()[0],
            'logins'    => (int)$conn->execute("SELECT COUNT(*) c FROM user_login_logs WHERE logged_at >= :since", ['since' => $since])->fetch()[0],
        ];

        // Średni czas POL → POD (godziny) w okresie
        $avgPolPod = (float)$conn->execute(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, pol_at, pod_at)) AS avg_h
             FROM speed_orders
             WHERE pol_at IS NOT NULL AND pod_at IS NOT NULL AND pod_at >= :since AND pod_at > pol_at",
            ['since' => $since]
        )->fetch()[0];

        // Top 5 wystawiających faktury (z user_action_logs)
        $topInvoiceUsers = array_slice($invoiceStats, 0, 5);
        // Top 5 POL handlers
        $polByUser = [];
        foreach ($speedByUser as $u => $counts) {
            if (!empty($counts['POL'])) $polByUser[$u] = $counts['POL'];
        }
        arsort($polByUser);
        $topPolUsers = array_slice($polByUser, 0, 5, true);

        $this->set(compact('period', 'speedByUser', 'invoiceStats', 'loginStats', 'stats', 'avgPolPod', 'topInvoiceUsers', 'topPolUsers'));
        return null;
    }
}

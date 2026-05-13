<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Admin — historia logowań użytkowników.
 *
 * Lista z paginacją + filtry (user, rola, data, IP).
 * Każdy wpis = pojedyncze logowanie raz na sesję.
 */
class AdminLoginLogsController extends AppController
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
        $LoginLogs = $this->fetchTable('UserLoginLogs');

        $q       = trim((string)$this->request->getQuery('q', ''));
        $role    = (string)$this->request->getQuery('role', '');
        $dateFrom = (string)$this->request->getQuery('date_from', '');
        $dateTo   = (string)$this->request->getQuery('date_to', '');

        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(10, min(200, (int)$this->request->getQuery('limit', 50)));

        $query = $LoginLogs->find()->orderByDesc('logged_at');

        if ($q !== '') {
            $query->where(['OR' => [
                'username LIKE' => '%' . $q . '%',
                'ip LIKE'       => '%' . $q . '%',
            ]]);
        }
        if ($role !== '') {
            $query->where(['role' => $role]);
        }
        if ($dateFrom !== '') {
            $query->where(['logged_at >=' => $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $query->where(['logged_at <=' => $dateTo . ' 23:59:59']);
        }

        $total = $query->count();
        $pages = (int)ceil($total / $limit);
        $logs  = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Lista ról do dropdown'a (unikalna z bazy)
        $roles = $LoginLogs->find()
            ->select(['role'])
            ->where(['role IS NOT' => null, 'role !=' => ''])
            ->groupBy('role')
            ->orderByAsc('role')
            ->disableHydration()
            ->all()
            ->extract('role')
            ->toArray();

        // Statystyki: total, dzisiaj, ostatnie 24h, unikalni userzy 7 dni
        $today = date('Y-m-d');
        $stats = [
            'total'      => $LoginLogs->find()->count(),
            'today'      => $LoginLogs->find()->where(['logged_at >=' => $today . ' 00:00:00'])->count(),
            'last_24h'   => $LoginLogs->find()->where(['logged_at >=' => date('Y-m-d H:i:s', time() - 86400)])->count(),
            'unique_7d'  => $LoginLogs->find()
                ->select(['user_id'])
                ->where(['logged_at >=' => date('Y-m-d 00:00:00', time() - 7 * 86400)])
                ->distinct(['user_id'])
                ->count(),
        ];

        $this->set(compact('logs', 'total', 'page', 'pages', 'limit', 'q', 'role', 'dateFrom', 'dateTo', 'roles', 'stats'));
        return null;
    }
}

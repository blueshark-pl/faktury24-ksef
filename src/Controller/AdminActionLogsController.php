<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class AdminActionLogsController extends AppController
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
        $Logs = $this->fetchTable('UserActionLogs');

        $q          = trim((string)$this->request->getQuery('q', ''));
        $controller = (string)$this->request->getQuery('controller', '');
        $method     = (string)$this->request->getQuery('method', '');
        $dateFrom   = (string)$this->request->getQuery('date_from', '');
        $dateTo     = (string)$this->request->getQuery('date_to', '');

        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(10, min(200, (int)$this->request->getQuery('limit', 50)));

        $query = $Logs->find()->orderByDesc('created');

        if ($q !== '') {
            $query->where(['OR' => [
                'username LIKE'  => '%' . $q . '%',
                'url LIKE'       => '%' . $q . '%',
                'entity_id LIKE' => '%' . $q . '%',
            ]]);
        }
        if ($controller !== '') $query->where(['controller' => $controller]);
        if ($method !== '')     $query->where(['method' => $method]);
        if ($dateFrom !== '')   $query->where(['created >=' => $dateFrom . ' 00:00:00']);
        if ($dateTo !== '')     $query->where(['created <=' => $dateTo . ' 23:59:59']);

        $total = $query->count();
        $pages = (int)ceil($total / $limit);
        $logs  = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Dropdown options
        $controllers = $Logs->find()
            ->select(['controller'])->groupBy('controller')->orderByAsc('controller')
            ->disableHydration()->all()->extract('controller')->toArray();

        $stats = [
            'total'     => $Logs->find()->count(),
            'today'     => $Logs->find()->where(['created >=' => date('Y-m-d') . ' 00:00:00'])->count(),
            'last_24h'  => $Logs->find()->where(['created >=' => date('Y-m-d H:i:s', time() - 86400)])->count(),
            'unique_users_7d' => $Logs->find()
                ->select(['user_id'])
                ->where(['created >=' => date('Y-m-d 00:00:00', time() - 7 * 86400)])
                ->distinct(['user_id'])
                ->count(),
        ];

        $this->set(compact('logs', 'total', 'page', 'pages', 'limit', 'q', 'controller', 'method', 'dateFrom', 'dateTo', 'controllers', 'stats'));
        return null;
    }
}

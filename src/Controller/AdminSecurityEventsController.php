<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Admin — wydarzenia bezpieczeństwa per user.
 *
 * Pokazuje: failed_login, 2fa_enabled/disabled/failed, webauthn_added/removed,
 * password_changed, password_reset_sent, login_locked.
 */
class AdminSecurityEventsController extends AppController
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
        $Events = $this->fetchTable('UserSecurityEvents');

        $q       = trim((string)$this->request->getQuery('q', ''));
        $type    = (string)$this->request->getQuery('type', '');
        $dateFrom = (string)$this->request->getQuery('date_from', '');
        $dateTo   = (string)$this->request->getQuery('date_to', '');

        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(10, min(200, (int)$this->request->getQuery('limit', 50)));

        $query = $Events->find()->orderByDesc('created');

        if ($q !== '') {
            $query->where(['OR' => [
                'username LIKE' => '%' . $q . '%',
                'ip LIKE'       => '%' . $q . '%',
            ]]);
        }
        if ($type !== '') {
            $query->where(['event_type' => $type]);
        }
        if ($dateFrom !== '') {
            $query->where(['created >=' => $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $query->where(['created <=' => $dateTo . ' 23:59:59']);
        }

        $total = $query->count();
        $pages = (int)ceil($total / $limit);
        $events = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $types = $Events->find()
            ->select(['event_type'])
            ->groupBy('event_type')
            ->orderByAsc('event_type')
            ->disableHydration()
            ->all()
            ->extract('event_type')
            ->toArray();

        // Stats — ostatnie 24h
        $since24h = date('Y-m-d H:i:s', time() - 86400);
        $stats = [
            'total'         => $Events->find()->count(),
            'last_24h'      => $Events->find()->where(['created >=' => $since24h])->count(),
            'failed_logins' => $Events->find()->where(['event_type' => 'failed_login', 'created >=' => $since24h])->count(),
            'unique_ips_24h'=> $Events->find()
                ->select(['ip'])
                ->where(['created >=' => $since24h, 'ip IS NOT' => null])
                ->distinct(['ip'])
                ->count(),
        ];

        $this->set(compact('events', 'total', 'page', 'pages', 'limit', 'q', 'type', 'dateFrom', 'dateTo', 'types', 'stats'));
        return null;
    }
}

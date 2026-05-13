<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Powiadomienia in-app per user.
 *
 * Akcje:
 *  - index() — pełna strona z paginacją + filtrami (all / unread)
 *  - recent() — AJAX feed dla dropdown w nagłówku (ostatnie 10)
 *  - count() — AJAX licznik nieprzeczytanych (do badge auto-refresh)
 *  - markRead($id) — POST oznacz jedną jako przeczytaną
 *  - markAllRead() — POST oznacz wszystkie usera
 *  - delete($id) — POST usuń własną notyfikację
 *
 * Wszystkie akcje wymagają zalogowania (beforeFilter). Każda notyfikacja
 * jest scope'owana do user_id z identity — nie można podejrzeć cudzych.
 */
class NotificationsController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $event->setResult($this->redirect('/users/login'));
        }
    }

    public function index(): ?Response
    {
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();

        $Notifications = $this->fetchTable('Notifications');
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = 30;
        $filter = (string)$this->request->getQuery('filter', 'all'); // all | unread

        $query = $Notifications->find()
            ->where(['user_id' => $userId])
            ->orderByDesc('created');
        if ($filter === 'unread') {
            $query->where(['is_read' => false]);
        }

        $total = $query->count();
        $pages = (int)ceil($total / $limit);
        $notifications = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $unreadCount = $Notifications->find()
            ->where(['user_id' => $userId, 'is_read' => false])
            ->count();

        $this->set(compact('notifications', 'total', 'page', 'pages', 'limit', 'filter', 'unreadCount'));
        return null;
    }

    public function recent(): Response
    {
        $this->disableAutoRender();
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();

        $rows = $this->fetchTable('Notifications')->find()
            ->where(['user_id' => $userId])
            ->orderByDesc('created')
            ->limit(10)
            ->all();

        $items = [];
        foreach ($rows as $n) {
            $items[] = [
                'id'       => (string)$n->id,
                'type'     => (string)$n->type,
                'severity' => (string)$n->severity,
                'title'    => (string)$n->title,
                'message'  => mb_substr((string)$n->message, 0, 200),
                'url'      => (string)($n->action_url ?? ''),
                'is_read'  => (bool)$n->is_read,
                'created'  => $n->created instanceof \DateTimeInterface ? $n->created->format('c') : (string)$n->created,
            ];
        }
        $unread = $this->fetchTable('Notifications')->find()
            ->where(['user_id' => $userId, 'is_read' => false])
            ->count();

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['items' => $items, 'unread' => $unread]));
    }

    public function count(): Response
    {
        $this->disableAutoRender();
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();
        $unread = $this->fetchTable('Notifications')->find()
            ->where(['user_id' => $userId, 'is_read' => false])
            ->count();
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['unread' => $unread]));
    }

    public function markRead(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();

        $this->fetchTable('Notifications')->updateAll(
            ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')],
            ['id' => $id, 'user_id' => $userId]
        );
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true]));
    }

    public function markAllRead(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();

        $this->fetchTable('Notifications')->updateAll(
            ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')],
            ['user_id' => $userId, 'is_read' => false]
        );
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true]));
    }

    public function delete(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post', 'delete']);
        $userId = (string)$this->request->getAttribute('identity')->getIdentifier();
        $this->fetchTable('Notifications')->deleteAll(['id' => $id, 'user_id' => $userId]);
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true]));
    }
}

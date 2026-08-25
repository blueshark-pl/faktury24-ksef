<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * FALA extras: Browser Push Notifications.
 * Uzytkownik zezwala na notifikacje w przegladarce → js zapisuje subscription
 * przez POST /crm/push/subscribe. Serwer dostarcza wiadomosci przez Web Push Protocol
 * (wymaga VAPID keys w Configure Push.vapidPublicKey/vapidPrivateKey).
 *
 * MVP: zapisujemy subscription + endpoint 'test-notify' do wyslania testowej
 * notifikacji dla self via minimal Web Push implementation (bez zewn. biblioteki).
 */
class CrmPushController extends AppController
{
    /**
     * POST /crm/push/subscribe - JS wysyla subscription po zezwoleniu usera
     * Body: endpoint, keys.p256dh, keys.auth, user_agent
     */
    public function subscribe(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $userId = $identity?->get('id');
        $companyId = $identity?->get('company_id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data));
            return $this->response;
        };

        if (!$userId) return $json(['ok' => false, 'error' => 'not authenticated'], 401);

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('crm_push_subscriptions', $conn->getSchemaCollection()->listTables(), true)) {
                return $json(['ok' => false, 'error' => 'Migracja crm_push_subscriptions nie odpalona'], 400);
            }
        } catch (\Throwable $e) {}

        $data = (array)$this->request->getData();
        $endpoint = trim((string)($data['endpoint'] ?? ''));
        $p256dh = trim((string)($data['keys']['p256dh'] ?? $data['p256dh_key'] ?? ''));
        $auth = trim((string)($data['keys']['auth'] ?? $data['auth_key'] ?? ''));
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return $json(['ok' => false, 'error' => 'brak endpoint/p256dh/auth'], 400);
        }

        try {
            $T = $this->fetchTable('CrmPushSubscriptions');
            // Dedup: jesli subscription juz istnieje (per user + endpoint) - update
            $existing = $T->find()->where(['user_id' => $userId, 'endpoint' => $endpoint])->first();
            $ua = mb_substr((string)$this->request->getHeaderLine('User-Agent'), 0, 500);
            if ($existing) {
                $existing->p256dh_key = $p256dh;
                $existing->auth_key = $auth;
                $existing->user_agent = $ua;
                $existing->is_active = true;
                $existing->last_used_at = new \Cake\I18n\DateTime();
                $T->save($existing);
                return $json(['ok' => true, 'action' => 'updated']);
            } else {
                $sub = $T->newEntity([
                    'id' => \Cake\Utility\Text::uuid(),
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'endpoint' => $endpoint,
                    'p256dh_key' => $p256dh,
                    'auth_key' => $auth,
                    'user_agent' => $ua,
                    'is_active' => true,
                ]);
                $T->save($sub);
                return $json(['ok' => true, 'action' => 'created', 'id' => $sub->id]);
            }
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /crm/push/unsubscribe
     */
    public function unsubscribe(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $userId = $identity?->get('id');
        $endpoint = trim((string)$this->request->getData('endpoint'));

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data));
            return $this->response;
        };

        if (!$userId || $endpoint === '') return $json(['ok' => false, 'error' => 'invalid'], 400);

        try {
            $T = $this->fetchTable('CrmPushSubscriptions');
            $T->updateAll(['is_active' => false], ['user_id' => $userId, 'endpoint' => $endpoint]);
            return $json(['ok' => true]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /crm/push/status - czy user ma aktywna subscription
     */
    public function status(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $userId = $identity?->get('id');
        $this->response = $this->response->withType('application/json');

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('crm_push_subscriptions', $conn->getSchemaCollection()->listTables(), true)) {
                return $this->response->withStringBody(json_encode(['ok' => true, 'has_subscription' => false, 'migration_missing' => true]));
            }
            $T = $this->fetchTable('CrmPushSubscriptions');
            $count = $T->find()->where(['user_id' => $userId, 'is_active' => true])->count();
            $vapid = trim((string)\Cake\Core\Configure::read('Push.vapidPublicKey'));
            return $this->response->withStringBody(json_encode([
                'ok' => true,
                'has_subscription' => $count > 0,
                'subscriptions_count' => $count,
                'vapid_public_key' => $vapid,
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStringBody(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        }
    }
}

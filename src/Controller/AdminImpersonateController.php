<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\ForbiddenException;

/**
 * Wcielanie się admina w innego usera (impersonation).
 *
 * Mechanizm:
 *  - start($userId): zapisuje oryginalną tożsamość admina w sesji
 *    (Impersonation.original_user_id), podmienia 'Auth' session key
 *    z target user data, dodaje wpis do user_impersonation_logs.
 *  - stop(): czyta Impersonation.original_user_id, ładuje admina,
 *    przywraca 'Auth' session key, zamyka log (ended_at=NOW).
 *  - searchUsers(): AJAX wyszukiwarka do header dropdown'a.
 *
 * Auth: tylko admin (requireAdmin w beforeFilter).
 */
class AdminImpersonateController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Wszystkie akcje wymagają admina. Wyjątek: stop() — pozwalamy
        // jeśli sesja ma flagę impersonation (admin chce wrócić, ale
        // identity w sesji to teraz target user, niekoniecznie admin).
        $action = (string)$this->request->getParam('action');
        if ($action === 'stop') {
            return; // walidacja w samej akcji przez Impersonation.original_user_id
        }
        if ($r = $this->requireAdmin()) {
            $event->setResult($r);
        }
    }

    /**
     * POST /admin/impersonate/start/{userId}
     * Wcielenie się w usera o ID = $userId.
     */
    public function start(string $userId): ?Response
    {
        $this->request->allowMethod(['post']);

        $identity = $this->request->getAttribute('identity');
        $adminId  = $identity?->getIdentifier();
        if (!$adminId) {
            throw new ForbiddenException(__('Brak aktywnej sesji admina.'));
        }

        $session = $this->request->getSession();

        // Już w trybie impersonacji — najpierw stop, potem nowy start
        if ($session->read('Impersonation.original_user_id')) {
            $this->Flash->warning(__('Najpierw zakończ obecne wcielenie.'));
            return $this->redirect($this->request->referer() ?: '/');
        }

        $Users = $this->fetchTable('Users');
        $target = $Users->find()
            ->where(['Users.id' => $userId])
            ->disableHydration()
            ->first();
        if (!$target) {
            throw new NotFoundException(__('Użytkownik nie istnieje.'));
        }
        if ((string)$target['id'] === (string)$adminId) {
            $this->Flash->warning(__('Nie możesz wcielić się w siebie.'));
            return $this->redirect($this->request->referer() ?: '/');
        }

        // Zachowaj oryginał + audyt log
        $adminUsername = (string)($identity->get('username') ?? $identity->get('email') ?? $adminId);
        $session->write('Impersonation.original_user_id', $adminId);
        $session->write('Impersonation.original_username', $adminUsername);

        try {
            $Logs = $this->fetchTable('UserImpersonationLogs');
            $log = $Logs->newEntity([
                'admin_user_id'   => (string)$adminId,
                'admin_username'  => $adminUsername,
                'target_user_id'  => (string)$target['id'],
                'target_username' => (string)($target['username'] ?? $target['email'] ?? ''),
                'target_role'     => (string)($target['role'] ?? ''),
                'ip'              => $this->request->clientIp() ?: null,
                'user_agent'      => mb_substr((string)$this->request->getHeaderLine('User-Agent'), 0, 500) ?: null,
                'started_at'      => date('Y-m-d H:i:s'),
            ]);
            $Logs->save($log);
            $session->write('Impersonation.log_id', $log->id);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('[impersonate] failed to write log: ' . $e->getMessage());
        }

        // Podmiana identity w sesji — Cake Authentication SessionAuthenticator
        // odczytuje z klucza 'Auth' (default). Zapisujemy tam pełne dane usera.
        $session->write('Auth', $target);
        // Reset login-log flag żeby user_login_logs nie podwajał wpisu pod tę tożsamość
        $session->write('Login.logged', true);

        $this->Flash->success(sprintf(__('Wcielono jako: %s'), (string)($target['username'] ?? $target['email'])));

        // Klient → portal, inni → dashboard
        $targetRole = strtolower((string)($target['role'] ?? ''));
        return $this->redirect($targetRole === 'client' ? '/portal' : '/');
    }

    /**
     * POST /admin/impersonate/stop
     * Przywrócenie sesji admina.
     */
    public function stop(): ?Response
    {
        $this->request->allowMethod(['post']);
        $session = $this->request->getSession();

        $originalId = $session->read('Impersonation.original_user_id');
        if (!$originalId) {
            $this->Flash->warning(__('Brak aktywnej impersonacji.'));
            return $this->redirect('/');
        }

        $Users = $this->fetchTable('Users');
        $admin = $Users->find()
            ->where(['Users.id' => $originalId])
            ->disableHydration()
            ->first();
        if (!$admin) {
            // Edge case — admin usunięty podczas impersonacji. Wylogowujemy.
            $session->destroy();
            return $this->redirect('/users/login');
        }

        // Zamknij audyt log
        try {
            $logId = $session->read('Impersonation.log_id');
            if ($logId) {
                $this->fetchTable('UserImpersonationLogs')->updateAll(
                    ['ended_at' => date('Y-m-d H:i:s')],
                    ['id' => $logId]
                );
            }
        } catch (\Throwable) { /* ignore */ }

        // Przywróć identity admina
        $session->write('Auth', $admin);
        $session->delete('Impersonation');
        $session->write('Login.logged', true); // nie loguj ponownie

        $this->Flash->success(__('Wróciłeś do swojego konta admina.'));
        return $this->redirect('/');
    }

    /**
     * GET /admin/impersonate/search?q=...
     * AJAX — wyszukiwanie userów do dropdownu (max 10 wyników).
     */
    public function search(): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();
        $this->disableAutoRender();

        $q = trim((string)$this->request->getQuery('q', ''));
        if (mb_strlen($q) < 1) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['results' => []]));
        }

        $like = '%' . $q . '%';
        $Users = $this->fetchTable('Users');
        $rows = $Users->find()
            ->select(['id', 'username', 'email', 'first_name', 'last_name', 'role', 'avatar', 'active'])
            ->where(['OR' => [
                'username LIKE'   => $like,
                'email LIKE'      => $like,
                'first_name LIKE' => $like,
                'last_name LIKE'  => $like,
            ]])
            ->orderBy(['active' => 'DESC', 'username' => 'ASC'])
            ->limit(10)
            ->disableHydration()
            ->all();

        $identity = $this->request->getAttribute('identity');
        $selfId   = $identity?->getIdentifier();
        $results = [];
        foreach ($rows as $r) {
            if ((string)$r['id'] === (string)$selfId) continue; // pomijamy siebie
            $name = trim(((string)$r['first_name']) . ' ' . ((string)$r['last_name']));
            $avatar = !empty($r['avatar']) ? (string)$r['avatar'] : null;
            // Guard: file_exists na avatar
            if ($avatar && str_starts_with($avatar, '/files/avatars/')) {
                if (!is_file(WWW_ROOT . ltrim($avatar, '/'))) {
                    $avatar = null;
                }
            }
            $results[] = [
                'id'       => (string)$r['id'],
                'username' => (string)($r['username'] ?? ''),
                'email'    => (string)($r['email'] ?? ''),
                'name'     => $name !== '' ? $name : (string)($r['username'] ?? $r['email']),
                'role'     => (string)($r['role'] ?? ''),
                'avatar'   => $avatar,
                'active'   => !empty($r['active']),
            ];
        }

        return $this->response->withType('application/json')->withStringBody(json_encode(['results' => $results]));
    }
}

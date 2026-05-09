<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Panel admina — zarządzanie klientami portalu (rola `client`).
 *
 * Każda akcja sprawdza uprawnienia administratora poprzez requireAdmin().
 */
class AdminClientsController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($r = $this->requireAdmin()) {
            $event->setResult($r);
        }
    }

    // -------------------------------------------------------------------------
    // Lista klientów (users z rolą `client` + ich profil)
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $q     = trim((string)$this->request->getQuery('q', ''));
        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = 50;

        $Users = $this->fetchTable('Users');
        $query = $Users->find()
            ->where(['Users.role' => 'client'])
            ->orderByDesc('Users.created');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'Users.email LIKE'      => $like,
                'Users.username LIKE'   => $like,
                'Users.first_name LIKE' => $like,
                'Users.last_name LIKE'  => $like,
            ]]);
        }

        $total = (clone $query)->count();
        $pages = max(1, (int)ceil($total / $limit));
        $page  = min($page, $pages);

        $users = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Profile klientów per user_id
        $profileMap = [];
        $userIds = array_map(fn($u) => (string)$u->id, $users->toArray());
        if (!empty($userIds)) {
            $profiles = $this->fetchTable('ClientProfiles')->find()
                ->where(['user_id IN' => $userIds])
                ->all();
            foreach ($profiles as $p) {
                $profileMap[(string)$p->user_id] = $p;
            }
        }

        // Mapa: NIP → liczba zleceń
        $orderCountMap = [];
        $nips = array_unique(array_filter(array_map(fn($p) => $p->nip, $profileMap)));
        if (!empty($nips)) {
            $rows = $this->fetchTable('SpeedOrders')->find()
                ->select(['buyer_nip', 'cnt' => $query->func()->count('*')])
                ->where(['buyer_nip IN' => $nips])
                ->groupBy('buyer_nip')
                ->all();
            foreach ($rows as $r) {
                $orderCountMap[(string)$r->buyer_nip] = (int)$r->cnt;
            }
        }

        $this->set(compact('users', 'profileMap', 'orderCountMap', 'q', 'total', 'page', 'pages', 'limit'));
    }

    // -------------------------------------------------------------------------
    // Dodanie nowego klienta — tworzy usera + profile w jednej transakcji
    // -------------------------------------------------------------------------
    public function add(): ?Response
    {
        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user    = $Users->newEmptyEntity();
        $profile = $ClientProfiles->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            $email    = trim((string)($data['email'] ?? ''));
            $username = trim((string)($data['username'] ?? '')) ?: $email;
            $password = (string)($data['password'] ?? '');
            // NIP może być krajowy (PL) lub zagraniczny (np. DE…, FR…) — nie czyścimy go,
            // tylko ujednolicamy: trim + upper, bo kontrahent w Speed ERP może być zapisany różnie.
            $nip      = strtoupper(trim((string)($data['nip'] ?? '')));
            $name     = trim((string)($data['company_name'] ?? ''));

            $errors = [];
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Podaj poprawny e-mail.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
            }
            if (strlen($nip) < 5) {
                $errors[] = 'NIP musi mieć co najmniej 5 znaków.';
            }
            if ($Users->exists(['email' => $email])) {
                $errors[] = 'Użytkownik z tym e-mailem już istnieje.';
            }
            if ($ClientProfiles->exists(['nip' => $nip])) {
                $errors[] = 'Profil klienta z tym NIP-em już istnieje.';
            }

            if (empty($errors)) {
                $conn = $Users->getConnection();
                try {
                    $conn->begin();

                    $user = $Users->newEntity([
                        'username'        => $username,
                        'email'           => $email,
                        'password'        => $password,
                        'first_name'      => trim((string)($data['first_name'] ?? '')) ?: null,
                        'last_name'       => trim((string)($data['last_name']  ?? '')) ?: null,
                        'active'          => 1,
                        'activation_date' => \Cake\I18n\DateTime::now(),
                    ], ['validate' => false]);
                    // role i is_superuser mają _accessible=false w CakeDC/Users User entity —
                    // ustawiamy je bezpośrednio na encji, by ominąć mass-assignment guard.
                    $user->role         = 'client';
                    $user->is_superuser = false;

                    if (!$Users->save($user, ['checkRules' => false])) {
                        throw new \RuntimeException('Nie udało się zapisać konta: ' . json_encode($user->getErrors()));
                    }

                    $profile = $ClientProfiles->newEntity([
                        'user_id'      => (string)$user->id,
                        'nip'          => $nip,
                        'company_name' => $name !== '' ? $name : null,
                        'locale'       => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                    ]);

                    if (!$ClientProfiles->save($profile)) {
                        throw new \RuntimeException('Nie udało się zapisać profilu: ' . json_encode($profile->getErrors()));
                    }

                    $conn->commit();
                    $this->Flash->success('Klient dodany. Login: ' . h($email));
                    return $this->redirect(['action' => 'index']);
                } catch (\Throwable $e) {
                    $conn->rollback();
                    $errors[] = $e->getMessage();
                }
            }

            foreach ($errors as $err) {
                $this->Flash->error($err);
            }
        }

        $this->set(compact('user', 'profile'));
        return null;
    }

    // -------------------------------------------------------------------------
    // Edycja klienta — profil + opcjonalna zmiana hasła
    // -------------------------------------------------------------------------
    public function edit(string $userId): ?Response
    {
        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user = $Users->find()
            ->where(['Users.id' => $userId, 'Users.role' => 'client'])
            ->first();
        if (!$user) {
            throw new NotFoundException('Klient nie istnieje.');
        }
        $profile = $ClientProfiles->find()->where(['user_id' => $userId])->first()
            ?? $ClientProfiles->newEmptyEntity();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();

            $errors = [];
            // NIP może być krajowy lub zagraniczny — nie czyścimy, tylko trim + upper.
            $nip    = strtoupper(trim((string)($data['nip'] ?? '')));
            $name   = trim((string)($data['company_name'] ?? ''));

            if (strlen($nip) < 5) {
                $errors[] = 'NIP musi mieć co najmniej 5 znaków.';
            }
            // sprawdź czy NIP nie należy do innego klienta
            if (empty($errors)) {
                $conflict = $ClientProfiles->find()
                    ->where(['nip' => $nip, 'user_id !=' => $userId])
                    ->count();
                if ($conflict > 0) {
                    $errors[] = 'NIP jest już przypisany do innego klienta.';
                }
            }

            $newPassword = (string)($data['password'] ?? '');
            if ($newPassword !== '' && strlen($newPassword) < 8) {
                $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
            }

            if (empty($errors)) {
                $conn = $Users->getConnection();
                try {
                    $conn->begin();

                    // Zmiana danych user-a
                    $userPatch = [
                        'first_name' => trim((string)($data['first_name'] ?? '')) ?: null,
                        'last_name'  => trim((string)($data['last_name']  ?? '')) ?: null,
                        'active'     => !empty($data['active']) ? 1 : 0,
                    ];
                    if ($newPassword !== '') {
                        $userPatch['password'] = $newPassword;
                    }
                    $user = $Users->patchEntity($user, $userPatch, ['validate' => false]);
                    if (!$Users->save($user, ['checkRules' => false])) {
                        throw new \RuntimeException('Nie udało się zapisać konta: ' . json_encode($user->getErrors()));
                    }

                    // Zmiana profilu
                    $profilePatch = [
                        'user_id'      => (string)$userId,
                        'nip'          => $nip,
                        'company_name' => $name !== '' ? $name : null,
                        'locale'       => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                    ];
                    $profile = $profile->isNew()
                        ? $ClientProfiles->newEntity($profilePatch)
                        : $ClientProfiles->patchEntity($profile, $profilePatch);
                    if (!$ClientProfiles->save($profile)) {
                        throw new \RuntimeException('Nie udało się zapisać profilu: ' . json_encode($profile->getErrors()));
                    }

                    $conn->commit();
                    $this->Flash->success('Zapisano zmiany.');
                    return $this->redirect(['action' => 'edit', $userId]);
                } catch (\Throwable $e) {
                    $conn->rollback();
                    $errors[] = $e->getMessage();
                }
            }

            foreach ($errors as $err) {
                $this->Flash->error($err);
            }
        }

        // Statystyki — ile zleceń ma ten NIP
        $orderCount = 0;
        if ($profile && $profile->nip) {
            $orderCount = $this->fetchTable('SpeedOrders')->find()
                ->where(['buyer_nip' => $profile->nip])
                ->count();
        }

        $this->set(compact('user', 'profile', 'orderCount'));
        return null;
    }

    // -------------------------------------------------------------------------
    // Usunięcie klienta — kasuje user + profile
    // -------------------------------------------------------------------------
    public function delete(string $userId): Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user = $Users->find()
            ->where(['Users.id' => $userId, 'Users.role' => 'client'])
            ->first();
        if (!$user) {
            throw new NotFoundException('Klient nie istnieje.');
        }

        $conn = $Users->getConnection();
        try {
            $conn->begin();
            $ClientProfiles->deleteAll(['user_id' => $userId]);
            if (!$Users->delete($user)) {
                throw new \RuntimeException('Nie udało się usunąć konta.');
            }
            $conn->commit();
            $this->Flash->success('Klient usunięty.');
        } catch (\Throwable $e) {
            $conn->rollback();
            $this->Flash->error($e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }
}

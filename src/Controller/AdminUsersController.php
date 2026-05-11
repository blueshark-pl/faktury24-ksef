<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Admin — zarządzanie wszystkimi użytkownikami (pracownicy + klienci portalu).
 *
 * Każdy user ma rolę (users.role = roles.code). Klient dodatkowo client_profiles (NIP+firma).
 * Akcje:
 *   - index — lista z filtrem po roli, wyszukiwarką, paginacją
 *   - add   — formularz: email + hasło + rola (+ NIP/firma jeśli client)
 *   - edit  — edycja danych, zmiana roli, reset hasła
 *   - delete — kasowanie + powiązany client_profile jeśli istnieje
 */
class AdminUsersController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($r = $this->requireAdmin()) {
            $event->setResult($r);
        }
    }

    public function index(): void
    {
        $roleFilter = trim((string)$this->request->getQuery('role', ''));
        $q          = trim((string)$this->request->getQuery('q', ''));
        $active     = $this->request->getQuery('active');     // '', '1', '0'
        $page       = max(1, (int)$this->request->getQuery('page', 1));
        $limit      = 50;

        $Users = $this->fetchTable('Users');
        $query = $Users->find()->orderByDesc('Users.created');

        if ($roleFilter !== '') {
            $query->where(['Users.role' => $roleFilter]);
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'Users.email LIKE'      => $like,
                'Users.username LIKE'   => $like,
                'Users.first_name LIKE' => $like,
                'Users.last_name LIKE'  => $like,
            ]]);
        }
        if ($active === '1' || $active === '0') {
            $query->where(['Users.active' => (int)$active]);
        }

        $total = (clone $query)->count();
        $pages = max(1, (int)ceil($total / $limit));
        $page  = min($page, $pages);

        $users = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Profile klientów per user_id (gdy filtr=client lub mixed)
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

        // Słownik ról (do filtra i kolumny)
        $rolesList = $this->fetchTable('Roles')->find()
            ->where(['is_active' => true])
            ->orderByDesc('Roles.is_system')
            ->orderBy(['Roles.name' => 'ASC'])
            ->all();
        $roleNameByCode = [];
        foreach ($rolesList as $r) {
            $roleNameByCode[$r->code] = $r->name;
        }

        $this->set(compact(
            'users', 'profileMap', 'rolesList', 'roleNameByCode',
            'roleFilter', 'q', 'active', 'total', 'page', 'pages', 'limit'
        ));
    }

    public function add(): ?Response
    {
        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user    = $Users->newEmptyEntity();
        $profile = $ClientProfiles->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            $email     = trim((string)($data['email'] ?? ''));
            $username  = trim((string)($data['username'] ?? '')) ?: $email;
            $password  = (string)($data['password'] ?? '');
            $roleCode  = trim((string)($data['role'] ?? 'user'));
            $isClient  = ($roleCode === 'client');
            $nip       = $isClient ? strtoupper(trim((string)($data['nip'] ?? ''))) : '';
            $company   = $isClient ? trim((string)($data['company_name'] ?? '')) : '';

            $errors = [];
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('Podaj poprawny e-mail.');
            }
            if (strlen($password) < 8) {
                $errors[] = __('Hasło musi mieć co najmniej 8 znaków.');
            }
            // Sprawdź czy rola istnieje
            if (!$this->fetchTable('Roles')->exists(['code' => $roleCode, 'is_active' => true])) {
                $errors[] = __('Nieprawidłowa rola.');
            }
            if ($Users->exists(['email' => $email])) {
                $errors[] = __('Użytkownik z tym e-mailem już istnieje.');
            }
            if ($isClient) {
                if (strlen($nip) < 5) {
                    $errors[] = __('NIP musi mieć co najmniej 5 znaków.');
                }
                if ($ClientProfiles->exists(['nip' => $nip])) {
                    $errors[] = __('Profil klienta z tym NIP-em już istnieje.');
                }
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
                        'active'          => !empty($data['active']) ? 1 : 1,    // domyślnie aktywne
                        'activation_date' => \Cake\I18n\DateTime::now(),
                    ], ['validate' => false]);
                    // role i is_superuser mają _accessible=false w CakeDC/Users User entity
                    $user->role         = $roleCode;
                    $user->is_superuser = ($roleCode === 'admin');

                    if (!$Users->save($user, ['checkRules' => false])) {
                        throw new \RuntimeException(__('Nie udało się zapisać konta') . ': ' . json_encode($user->getErrors()));
                    }

                    if ($isClient) {
                        $profile = $ClientProfiles->newEntity([
                            'user_id'      => (string)$user->id,
                            'nip'          => $nip,
                            'company_name' => $company !== '' ? $company : null,
                            'locale'       => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                        ]);
                        if (!$ClientProfiles->save($profile)) {
                            throw new \RuntimeException(__('Nie udało się zapisać profilu klienta') . ': ' . json_encode($profile->getErrors()));
                        }
                    }

                    $conn->commit();
                    $this->Flash->success(__('Konto utworzone. Login: {0}', h($email)));
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

        $rolesList = $this->fetchTable('Roles')->find()
            ->where(['is_active' => true])
            ->orderByDesc('Roles.is_system')
            ->orderBy(['Roles.name' => 'ASC'])
            ->all();

        $this->set(compact('user', 'profile', 'rolesList'));
        return null;
    }

    public function edit(string $userId): ?Response
    {
        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user = $Users->find()->where(['Users.id' => $userId])->first();
        if (!$user) {
            throw new NotFoundException(__('Użytkownik nie istnieje.'));
        }
        $profile = $ClientProfiles->find()->where(['user_id' => $userId])->first()
            ?? $ClientProfiles->newEmptyEntity();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();

            $errors = [];

            $newRole  = trim((string)($data['role'] ?? $user->role));
            $isClient = ($newRole === 'client');
            $nip      = strtoupper(trim((string)($data['nip'] ?? '')));
            $company  = trim((string)($data['company_name'] ?? ''));

            if (!$this->fetchTable('Roles')->exists(['code' => $newRole, 'is_active' => true])) {
                $errors[] = __('Nieprawidłowa rola.');
            }
            if ($isClient && strlen($nip) < 5) {
                $errors[] = __('NIP musi mieć co najmniej 5 znaków.');
            }
            if ($isClient) {
                $conflict = $ClientProfiles->find()
                    ->where(['nip' => $nip, 'user_id !=' => $userId])
                    ->count();
                if ($conflict > 0) {
                    $errors[] = __('NIP jest już przypisany do innego klienta.');
                }
            }

            $newPassword = (string)($data['password'] ?? '');
            if ($newPassword !== '' && strlen($newPassword) < 8) {
                $errors[] = __('Hasło musi mieć co najmniej 8 znaków.');
            }

            if (empty($errors)) {
                $conn = $Users->getConnection();
                try {
                    $conn->begin();

                    $patch = [
                        'first_name' => trim((string)($data['first_name'] ?? '')) ?: null,
                        'last_name'  => trim((string)($data['last_name']  ?? '')) ?: null,
                        'active'     => !empty($data['active']) ? 1 : 0,
                    ];
                    if ($newPassword !== '') {
                        $patch['password'] = $newPassword;
                    }
                    $user = $Users->patchEntity($user, $patch, ['validate' => false]);
                    // role + is_superuser via _accessible=false → direct assign
                    $user->role         = $newRole;
                    $user->is_superuser = ($newRole === 'admin');

                    if (!$Users->save($user, ['checkRules' => false])) {
                        throw new \RuntimeException(__('Nie udało się zapisać konta'));
                    }

                    if ($isClient) {
                        $profilePatch = [
                            'user_id'      => (string)$userId,
                            'nip'          => $nip,
                            'company_name' => $company !== '' ? $company : null,
                            'locale'       => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                        ];
                        $profile = $profile->isNew()
                            ? $ClientProfiles->newEntity($profilePatch)
                            : $ClientProfiles->patchEntity($profile, $profilePatch);
                        if (!$ClientProfiles->save($profile)) {
                            throw new \RuntimeException(__('Nie udało się zapisać profilu klienta'));
                        }
                    } else {
                        // Rola zmieniona z client na inną → usuń profil
                        if (!$profile->isNew()) {
                            $ClientProfiles->delete($profile);
                            $profile = $ClientProfiles->newEmptyEntity();
                        }
                    }

                    $conn->commit();
                    $this->Flash->success(__('Zapisano zmiany.'));
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

        $rolesList = $this->fetchTable('Roles')->find()
            ->where(['is_active' => true])
            ->orderByDesc('Roles.is_system')
            ->orderBy(['Roles.name' => 'ASC'])
            ->all();

        // Statystyki — ile zleceń ma NIP klienta
        $orderCount = 0;
        if ($profile && !empty($profile->nip)) {
            $orderCount = $this->fetchTable('SpeedOrders')->find()
                ->where(['buyer_nip' => $profile->nip])
                ->count();
        }

        $this->set(compact('user', 'profile', 'rolesList', 'orderCount'));
        return null;
    }

    public function delete(string $userId): Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $Users          = $this->fetchTable('Users');
        $ClientProfiles = $this->fetchTable('ClientProfiles');

        $user = $Users->find()->where(['Users.id' => $userId])->first();
        if (!$user) {
            throw new NotFoundException(__('Użytkownik nie istnieje.'));
        }

        // Nie pozwól skasować samego siebie
        $identity = $this->request->getAttribute('identity');
        if ($identity && (string)$identity->getIdentifier() === (string)$userId) {
            $this->Flash->error(__('Nie możesz usunąć własnego konta.'));
            return $this->redirect(['action' => 'index']);
        }

        $conn = $Users->getConnection();
        try {
            $conn->begin();
            $ClientProfiles->deleteAll(['user_id' => $userId]);
            if (!$Users->delete($user)) {
                throw new \RuntimeException(__('Nie udało się usunąć konta.'));
            }
            $conn->commit();
            $this->Flash->success(__('Użytkownik usunięty.'));
        } catch (\Throwable $e) {
            $conn->rollback();
            $this->Flash->error($e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }
}

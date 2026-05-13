<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Mailer\MailerAwareTrait;

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
    use MailerAwareTrait;

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

        // Sortowanie
        $sortable = [
            'email'      => 'Users.email',
            'first_name' => 'Users.first_name',
            'last_name'  => 'Users.last_name',
            'role'       => 'Users.role',
            'active'     => 'Users.active',
            'created'    => 'Users.created',
        ];
        $sortKey = (string)$this->request->getQuery('sort', 'created');
        $sortDir = strtolower((string)$this->request->getQuery('direction', 'desc'));
        if (!isset($sortable[$sortKey]))         $sortKey = 'created';
        if (!in_array($sortDir, ['asc','desc'])) $sortDir = 'desc';

        $query = $Users->find()
            ->select([
                'Users.id', 'Users.email', 'Users.username',
                'Users.first_name', 'Users.last_name',
                'Users.role', 'Users.active',
                'Users.company_id', 'Users.created',
            ]);
        if ($sortDir === 'asc') $query->orderByAsc($sortable[$sortKey]);
        else                    $query->orderByDesc($sortable[$sortKey]);

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

        // Mapa avatarów per user_id — osobne zapytanie bo hydratacja przez ORM
        // (find() z CakeDC plugin) gubi to pole w niektórych przypadkach.
        $avatarMap = [];
        if (!empty($userIds)) {
            $rows = $Users->find()
                ->select(['id', 'avatar'])
                ->where(['id IN' => $userIds, 'avatar IS NOT' => null])
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                if (!empty($r['avatar'])) {
                    $avatarMap[(string)$r['id']] = (string)$r['avatar'];
                }
            }
        }

        // Mapa: client_user_id → aktualny opiekun (substitut jeśli aktywny w okresie,
        // inaczej caretaker_user_id) + nazwa pracownika do wyświetlenia
        $caretakerMap = [];
        if (!empty($userIds)) {
            $profiles = $this->fetchTable('ClientProfiles')->find()
                ->where(['user_id IN' => $userIds])
                ->all();
            $caretakerIds = [];
            foreach ($profiles as $p) {
                $current = $p->current_caretaker_user_id;
                if ($current) {
                    $caretakerMap[(string)$p->user_id] = [
                        'caretaker_id'    => $current,
                        'is_substitute'   => $p->is_substitute_active,
                    ];
                    $caretakerIds[$current] = true;
                }
            }
            if (!empty($caretakerIds)) {
                $rows = $Users->find()
                    ->select(['id', 'email', 'first_name', 'last_name'])
                    ->where(['id IN' => array_keys($caretakerIds)])
                    ->disableHydration()
                    ->all();
                foreach ($rows as $r) {
                    $name = trim(((string)$r['first_name']) . ' ' . ((string)$r['last_name']));
                    foreach ($caretakerMap as $clientId => $info) {
                        if ($info['caretaker_id'] === $r['id']) {
                            $caretakerMap[$clientId]['name']  = $name !== '' ? $name : $r['email'];
                            $caretakerMap[$clientId]['email'] = $r['email'];
                        }
                    }
                }
            }
        }

        // Mapa: user_id → ostatni welcome e-mail (created, lang, status)
        $lastWelcomeMap = [];
        if (!empty($userIds)) {
            $UserEmailLogs = $this->fetchTable('UserEmailLogs');
            $cnt = $UserEmailLogs->find()
                ->select(['user_id', 'cnt' => $UserEmailLogs->find()->func()->count('*')])
                ->where(['user_id IN' => $userIds, 'email_type' => 'welcome'])
                ->groupBy('user_id')
                ->disableHydration()
                ->all()
                ->toArray();
            $countByUser = [];
            foreach ($cnt as $c) {
                $countByUser[(string)$c['user_id']] = (int)$c['cnt'];
            }
            // Najnowsze: jedno zapytanie z window-like fallback — pobieramy MAX(created) per user
            $maxDates = $UserEmailLogs->find()
                ->select(['user_id', 'last_sent' => $UserEmailLogs->find()->func()->max('created')])
                ->where(['user_id IN' => $userIds, 'email_type' => 'welcome'])
                ->groupBy('user_id')
                ->disableHydration()
                ->all();
            foreach ($maxDates as $row) {
                $lastWelcomeMap[(string)$row['user_id']] = [
                    'last_sent' => $row['last_sent'],
                    'count'     => $countByUser[(string)$row['user_id']] ?? 0,
                ];
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
            'users', 'profileMap', 'avatarMap', 'caretakerMap', 'lastWelcomeMap',
            'rolesList', 'roleNameByCode',
            'roleFilter', 'q', 'active', 'total', 'page', 'pages', 'limit',
            'sortKey', 'sortDir'
        ));
    }

    /**
     * AJAX endpoint: historia maili powitalnych dla danego usera.
     * GET /admin/uzytkownicy/welcome-history/{userId}
     * Zwraca HTML do wstawienia w modal (klucz: ostatnie 50 welcome maili).
     */
    public function welcomeHistory(string $userId): ?Response
    {
        $Users = $this->fetchTable('Users');
        $user  = $Users->find()->select(['id', 'email', 'first_name', 'last_name'])
            ->where(['id' => $userId])->first();
        if (!$user) {
            throw new NotFoundException(__('Użytkownik nie istnieje.'));
        }

        $logs = $this->fetchTable('UserEmailLogs')->find()
            ->where(['user_id' => $userId, 'email_type' => 'welcome'])
            ->orderByDesc('created')
            ->limit(50)
            ->all();

        $this->viewBuilder()->setLayout('ajax');
        $this->set(compact('user', 'logs'));
        return null;
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

                    // company_id: dla pracowników opcjonalne (mogą przejść onboarding sami),
                    // dla klientów ignorowane (nie używają company_id naszej firmy)
                    $companyId = trim((string)($data['company_id'] ?? ''));
                    if ($isClient || $companyId === '') {
                        $companyId = null;
                    } elseif (!$this->fetchTable('Companies')->exists(['id' => $companyId])) {
                        $companyId = null;
                    }

                    $user = $Users->newEntity([
                        'username'        => $username,
                        'email'           => $email,
                        'password'        => $password,
                        'first_name'      => trim((string)($data['first_name'] ?? '')) ?: null,
                        'last_name'       => trim((string)($data['last_name']  ?? '')) ?: null,
                        'phone'           => trim((string)($data['phone'] ?? '')) ?: null,
                        'company_id'      => $companyId,
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
                            'user_id'            => (string)$user->id,
                            'nip'                => $nip,
                            'company_name'       => $company !== '' ? $company : null,
                            'locale'             => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                            'caretaker_user_id'  => $this->normalizeUserId($data['caretaker_user_id'] ?? null),
                            'substitute_user_id' => $this->normalizeUserId($data['substitute_user_id'] ?? null),
                            'substitute_from'    => $this->normalizeDate($data['substitute_from'] ?? null),
                            'substitute_to'      => $this->normalizeDate($data['substitute_to'] ?? null),
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

        $companiesList = $this->fetchTable('Companies')->find()
            ->select(['id', 'name', 'nip'])
            ->orderBy(['Companies.name' => 'ASC'])
            ->all();

        $employeesList = $this->fetchTable('Users')->find()
            ->select(['id', 'email', 'first_name', 'last_name', 'role'])
            ->where(['Users.role !=' => 'client', 'Users.active' => 1])
            ->orderBy(['Users.first_name' => 'ASC', 'Users.last_name' => 'ASC', 'Users.email' => 'ASC'])
            ->disableHydration()
            ->all()
            ->toArray();

        $this->set(compact('user', 'profile', 'rolesList', 'companiesList', 'employeesList'));
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

                    // company_id: dla pracowników — bierzemy z formularza, dla klientów → null
                    $companyId = trim((string)($data['company_id'] ?? ''));
                    if ($isClient || $companyId === '') {
                        $companyId = null;
                    } elseif (!$this->fetchTable('Companies')->exists(['id' => $companyId])) {
                        $companyId = null;
                    }

                    $patch = [
                        'first_name' => trim((string)($data['first_name'] ?? '')) ?: null,
                        'last_name'  => trim((string)($data['last_name']  ?? '')) ?: null,
                        'phone'      => trim((string)($data['phone'] ?? '')) ?: null,
                        'company_id' => $companyId,
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
                            'user_id'            => (string)$userId,
                            'nip'                => $nip,
                            'company_name'       => $company !== '' ? $company : null,
                            'locale'             => in_array($data['locale'] ?? 'pl', ['pl', 'en'], true) ? $data['locale'] : 'pl',
                            'caretaker_user_id'  => $this->normalizeUserId($data['caretaker_user_id'] ?? null),
                            'substitute_user_id' => $this->normalizeUserId($data['substitute_user_id'] ?? null),
                            'substitute_from'    => $this->normalizeDate($data['substitute_from'] ?? null),
                            'substitute_to'      => $this->normalizeDate($data['substitute_to'] ?? null),
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

        // Historia wysyłki e-maili do tego usera (najnowsze 20)
        $emailLogs = $this->fetchTable('UserEmailLogs')->find()
            ->where(['user_id' => $userId])
            ->orderByDesc('created')
            ->limit(20)
            ->all();

        $companiesList = $this->fetchTable('Companies')->find()
            ->select(['id', 'name', 'nip'])
            ->orderBy(['Companies.name' => 'ASC'])
            ->all();

        // Lista pracowników (nie-klientów) do wyboru jako opiekun/zastępca
        $employeesList = $this->fetchTable('Users')->find()
            ->select(['id', 'email', 'first_name', 'last_name', 'role'])
            ->where(['Users.role !=' => 'client', 'Users.active' => 1])
            ->orderBy(['Users.first_name' => 'ASC', 'Users.last_name' => 'ASC', 'Users.email' => 'ASC'])
            ->disableHydration()
            ->all()
            ->toArray();

        $this->set(compact('user', 'profile', 'rolesList', 'companiesList', 'employeesList', 'orderCount', 'emailLogs'));
        return null;
    }

    /**
     * Wysłanie e-maila powitalnego do usera.
     * POST /admin/uzytkownicy/powitanie/{id}  body: lang=pl|en
     *
     * Treść zależy od roli (admin/user/client/asystent_spedytora/mlodszy_spedytor/
     * spedycja_manager/sales_manager) — wszystkie zawierają link do ustawienia
     * hasła (token resetu) z polityką bezpieczeństwa.
     */
    public function sendWelcome(string $userId): Response
    {
        $this->request->allowMethod(['post']);

        $lang = (string)$this->request->getData('lang', 'pl');
        $lang = in_array($lang, ['pl', 'en'], true) ? $lang : 'pl';

        $Users = $this->fetchTable('Users');
        $user  = $Users->find()->where(['Users.id' => $userId])->first();
        if (!$user) {
            throw new NotFoundException(__('Użytkownik nie istnieje.'));
        }

        try {
            // Wygeneruj świeży token resetu hasła (7 dni ważności)
            $tokenResult = $Users->resetToken($user->email, [
                'expiration' => 86400 * 7,
                'sendEmail'  => false,
            ]);
            if (!$tokenResult) {
                throw new \RuntimeException(__('Nie udało się wygenerować tokenu.'));
            }

            // Tymczasowo przestaw locale na czas renderowania maila
            $prevLocale = \Cake\I18n\I18n::getLocale();
            \Cake\I18n\I18n::setLocale($lang);

            $mailer = $this->getMailer('App.MyUsers');
            $mailer->send('welcome', [$tokenResult, $lang]);

            \Cake\I18n\I18n::setLocale($prevLocale);

            $this->Flash->success(__('E-mail powitalny wysłany na {0} ({1}).', $user->email, strtoupper($lang)));
        } catch (\Throwable $e) {
            $this->Flash->error(__('Nie udało się wysłać e-maila: {0}', $e->getMessage()));
        }

        $referer = $this->request->referer(true);
        return $this->redirect($referer ?: ['action' => 'edit', $userId]);
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

    /**
     * Normalizuje user_id z formularza: pusty/nieistniejący → null.
     */
    private function normalizeUserId($value): ?string
    {
        $v = trim((string)($value ?? ''));
        if ($v === '') {
            return null;
        }
        if (!$this->fetchTable('Users')->exists(['id' => $v])) {
            return null;
        }
        return $v;
    }

    /**
     * Normalizuje datę z formularza ('YYYY-MM-DD'): pusta → null.
     */
    private function normalizeDate($value): ?string
    {
        $v = trim((string)($value ?? ''));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        return $v;
    }
}

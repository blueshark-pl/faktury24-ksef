<?php
declare(strict_types=1);

namespace App\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Response;

/**
 * Bezpieczeństwo — endpointy AJAX dla:
 *  - screen lock unlock (po bezczynności)
 *  - zarządzania PIN-em (set / delete)
 */
class SecurityController extends AppController
{
    private function jsonOk(array $extra = []): Response
    {
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true] + $extra));
    }

    private function jsonError(string $msg, int $status = 200, array $extra = []): Response
    {
        return $this->response->withStatus($status)->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'error' => $msg] + $extra));
    }

    /**
     * POST /unlock — weryfikuje hasło lub PIN dla zalogowanego usera.
     * Body: {credential: '...'}
     * Zwraca: {success: true} albo {success: false, error: '...', attempts_left: N}
     */
    public function unlock(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            return $this->jsonError(__('Brak aktywnej sesji.'), 401);
        }
        $userId = (string)$identity->get('id');
        $credential = (string)($this->request->getData('credential') ?? '');

        if ($credential === '') {
            return $this->jsonError(__('Wpisz hasło lub PIN.'));
        }

        // Pobierz świeże dane usera z DB (password + pin_hash)
        $Users = $this->fetchTable('Users');
        $user = $Users->find()
            ->select(['id', 'password', 'pin_hash', 'active'])
            ->where(['id' => $userId])
            ->disableHydration()
            ->first();
        if (!$user || empty($user['active'])) {
            return $this->jsonError(__('Konto nieaktywne.'), 401);
        }

        $hasher = new DefaultPasswordHasher();
        $ok = false;
        if (!empty($user['password']) && $hasher->check($credential, (string)$user['password'])) {
            $ok = true;
        } elseif (!empty($user['pin_hash']) && $hasher->check($credential, (string)$user['pin_hash'])) {
            $ok = true;
        }

        $maxFailures = (int)(Configure::read('Security.screenLock.maxFailures') ?? 3);
        $cacheKey = 'unlock_fail_' . $userId;

        if ($ok) {
            Cache::delete($cacheKey);
            return $this->jsonOk();
        }

        // Rate-limit: liczymy błędne próby (1 min window)
        $fails = (int)Cache::read($cacheKey) + 1;
        Cache::write($cacheKey, $fails, 'default');

        if ($fails >= $maxFailures) {
            return $this->jsonError(
                __('Zbyt wiele błędnych prób. Wymagane ponowne logowanie.'),
                200,
                ['logout' => true, 'attempts_left' => 0]
            );
        }

        return $this->jsonError(
            __('Nieprawidłowe hasło lub PIN.'),
            200,
            ['attempts_left' => $maxFailures - $fails]
        );
    }

    /**
     * POST /set-pin — ustawia / zmienia PIN.
     * Body: {current_password: '...', new_pin: '1234'}
     */
    public function setPin(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            return $this->jsonError(__('Brak aktywnej sesji.'), 401);
        }
        $userId = (string)$identity->get('id');
        $currentPassword = (string)($this->request->getData('current_password') ?? '');
        $newPin = trim((string)($this->request->getData('new_pin') ?? ''));

        if (!preg_match('/^\d{4,6}$/', $newPin)) {
            return $this->jsonError(__('PIN musi mieć 4–6 cyfr.'));
        }

        $Users = $this->fetchTable('Users');
        $user = $Users->find()->where(['id' => $userId])->first();
        if (!$user) {
            return $this->jsonError(__('Użytkownik nie istnieje.'), 404);
        }

        $hasher = new DefaultPasswordHasher();
        if (!$hasher->check($currentPassword, (string)$user->password)) {
            return $this->jsonError(__('Nieprawidłowe aktualne hasło.'));
        }

        // Zapisujemy bezpośrednio hash — pomija setter password z User entity
        $user->set('pin_hash', $hasher->hash($newPin), ['guard' => false]);
        if (!$Users->save($user, ['checkRules' => false, 'validate' => false])) {
            return $this->jsonError(__('Nie udało się zapisać PIN-u.'));
        }

        return $this->jsonOk();
    }

    /**
     * POST /delete-pin — usuwa PIN (wymaga hasła do potwierdzenia).
     * Body: {current_password: '...'}
     */
    public function deletePin(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            return $this->jsonError(__('Brak aktywnej sesji.'), 401);
        }
        $userId = (string)$identity->get('id');
        $currentPassword = (string)($this->request->getData('current_password') ?? '');

        $Users = $this->fetchTable('Users');
        $user = $Users->find()->where(['id' => $userId])->first();
        if (!$user) {
            return $this->jsonError(__('Użytkownik nie istnieje.'), 404);
        }

        $hasher = new DefaultPasswordHasher();
        if (!$hasher->check($currentPassword, (string)$user->password)) {
            return $this->jsonError(__('Nieprawidłowe aktualne hasło.'));
        }

        $user->set('pin_hash', null, ['guard' => false]);
        if (!$Users->save($user, ['checkRules' => false, 'validate' => false])) {
            return $this->jsonError(__('Nie udało się usunąć PIN-u.'));
        }

        return $this->jsonOk();
    }
}

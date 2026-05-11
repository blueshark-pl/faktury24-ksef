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
    /** Parsuje wartość typu "2M", "512K", "1G" do bajtów. */
    private function _parseIniSize(string $val): int
    {
        $val = trim($val);
        if ($val === '') return 0;
        $last = strtolower(substr($val, -1));
        $num  = (int)$val;
        return match ($last) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }

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

        // Bezpośredni UPDATE — pomija _setPassword setter, _accessible i schema cache
        $updated = $Users->updateAll(
            ['pin_hash' => $hasher->hash($newPin)],
            ['id' => $userId]
        );
        if ($updated < 1) {
            \Cake\Log\Log::error('setPin: UPDATE failed for user ' . $userId);
            return $this->jsonError(__('Nie udało się zapisać PIN-u.'));
        }

        return $this->jsonOk();
    }

    /**
     * POST /upload-avatar — wgrywa zdjęcie profilowe (multipart).
     * Plik: jpg/png/webp, ≤ 5 MB. Skalowany do 400×400 i zapisany jako JPG.
     */
    public function uploadAvatar(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            return $this->jsonError(__('Brak aktywnej sesji.'), 401);
        }
        $userId = (string)$identity->get('id');

        $uploaded = $this->request->getUploadedFile('avatar');
        $errCode  = $uploaded ? $uploaded->getError() : UPLOAD_ERR_NO_FILE;
        if (!$uploaded || $errCode !== UPLOAD_ERR_OK) {
            $iniLimit = (string)(ini_get('upload_max_filesize') ?: '?');
            $msg = match ($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    sprintf((string)__('Plik za duży — serwer akceptuje maks. %s. Skontaktuj się z administratorem aby zwiększyć limit.'), $iniLimit),
                UPLOAD_ERR_PARTIAL   => __('Plik został wgrany tylko częściowo.'),
                UPLOAD_ERR_NO_FILE   => __('Nie wybrano pliku.'),
                UPLOAD_ERR_NO_TMP_DIR=> __('Brak katalogu tymczasowego na serwerze.'),
                UPLOAD_ERR_CANT_WRITE=> __('Nie można zapisać pliku tymczasowego na serwerze.'),
                default              => sprintf((string)__('Nie udało się odebrać pliku (kod %d).'), (int)$errCode),
            };
            return $this->jsonError($msg);
        }

        $mime = (string)$uploaded->getClientMediaType();
        $size = (int)$uploaded->getSize();
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return $this->jsonError(__('Dozwolone formaty: JPG, PNG, WebP.'));
        }
        // Limit dostosowany do faktycznego limitu PHP (zwykle 2 MB na shared hostingu)
        $iniLimit = $this->_parseIniSize((string)(ini_get('upload_max_filesize') ?: '2M'));
        if ($size > $iniLimit) {
            return $this->jsonError(sprintf(
                (string)__('Plik za duży — maks. %s.'),
                ini_get('upload_max_filesize') ?: '2M'
            ));
        }

        // Tymczasowo zapisz do pamięci, otwórz przez GD
        $tmpPath = $uploaded->getStream()->getMetadata('uri');
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png'  => @imagecreatefrompng($tmpPath),
            'image/webp' => @imagecreatefromwebp($tmpPath),
        };
        if (!$src) {
            return $this->jsonError(__('Nie udało się odczytać obrazu.'));
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $target = 400;

        // Crop do kwadratu (center) → resize do 400×400
        $side  = min($w, $h);
        $sx    = (int)(($w - $side) / 2);
        $sy    = (int)(($h - $side) / 2);
        $dst   = imagecreatetruecolor($target, $target);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $target, $target, $side, $side);
        imagedestroy($src);

        // Katalog (utwórz jeśli nie istnieje)
        $dir = WWW_ROOT . 'files' . DS . 'avatars';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

        // Nazwa pliku: avatar_{userId}_{timestamp}.jpg (timestamp = cache-busting)
        $ts       = time();
        $filename = 'avatar_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) . '_' . $ts . '.jpg';
        $fullPath = $dir . DS . $filename;
        $relUrl   = '/files/avatars/' . $filename;

        if (!imagejpeg($dst, $fullPath, 88)) {
            imagedestroy($dst);
            return $this->jsonError(__('Nie udało się zapisać pliku.'));
        }
        imagedestroy($dst);

        // Pobierz aktualną wartość avatara (potrzebne do usunięcia starego pliku)
        $Users = $this->fetchTable('Users');
        $row = $Users->find()
            ->select(['id', 'avatar'])
            ->where(['id' => $userId])
            ->disableHydration()
            ->first();
        if (!$row) {
            @unlink($fullPath);
            return $this->jsonError(__('Użytkownik nie istnieje.'), 404);
        }
        $oldAvatar = (string)($row['avatar'] ?? '');
        if ($oldAvatar !== '' && str_starts_with($oldAvatar, '/files/avatars/')) {
            $oldPath = WWW_ROOT . ltrim($oldAvatar, '/');
            if (is_file($oldPath) && $oldPath !== $fullPath) { @unlink($oldPath); }
        }

        // Bezpośredni UPDATE — pomija _accessible/_setAvatar/setterów + schema cache.
        // Zwraca liczbę zaktualizowanych wierszy.
        $updated = $Users->updateAll(['avatar' => $relUrl], ['id' => $userId]);
        if ($updated < 1) {
            @unlink($fullPath);
            \Cake\Log\Log::error('uploadAvatar: UPDATE failed for user ' . $userId . ' (updated=' . $updated . ')');
            return $this->jsonError(__('Nie udało się zapisać profilu.'));
        }

        return $this->jsonOk(['avatar' => $relUrl]);
    }

    /**
     * POST /delete-avatar — usuwa zdjęcie profilowe.
     */
    public function deleteAvatar(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            return $this->jsonError(__('Brak aktywnej sesji.'), 401);
        }
        $userId = (string)$identity->get('id');

        $Users = $this->fetchTable('Users');
        $row = $Users->find()
            ->select(['id', 'avatar'])
            ->where(['id' => $userId])
            ->disableHydration()
            ->first();
        if (!$row) {
            return $this->jsonError(__('Użytkownik nie istnieje.'), 404);
        }

        $oldAvatar = (string)($row['avatar'] ?? '');
        if ($oldAvatar !== '' && str_starts_with($oldAvatar, '/files/avatars/')) {
            $oldPath = WWW_ROOT . ltrim($oldAvatar, '/');
            if (is_file($oldPath)) { @unlink($oldPath); }
        }

        $updated = $Users->updateAll(['avatar' => null], ['id' => $userId]);
        if ($updated < 1) {
            \Cake\Log\Log::error('deleteAvatar: UPDATE failed for user ' . $userId);
            return $this->jsonError(__('Nie udało się zaktualizować profilu.'));
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

        $updated = $Users->updateAll(['pin_hash' => null], ['id' => $userId]);
        if ($updated < 1) {
            \Cake\Log\Log::error('deletePin: UPDATE failed for user ' . $userId);
            return $this->jsonError(__('Nie udało się usunąć PIN-u.'));
        }

        return $this->jsonOk();
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

class CrmEmailAccountsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $rows = $this->fetchTable('CrmEmailAccounts')->find()
            ->contain(['Users' => function ($q) { return $q->select(['id', 'first_name', 'last_name']); }])
            ->where(['CrmEmailAccounts.company_id' => $companyId])
            ->orderByDesc('CrmEmailAccounts.is_active')
            ->all();
        $this->set(compact('rows'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['company_id'] = $companyId;
            $data['is_active']  = !empty($data['is_active']);
            $data['use_ssl']    = !empty($data['use_ssl']);
            $plain = trim((string)($data['password'] ?? ''));
            unset($data['password']);

            $entity = $EA->patchEntity($entity, $data);
            if ($plain !== '') {
                $entity->password_encrypted = $EA->encryptPassword($plain);
            } else {
                $this->Flash->error(__('Podaj hasło do skrzynki.'));
                $this->set(compact('entity'));
                $this->set('isEdit', false);
                $this->render('add');
                return;
            }

            if ($EA->save($entity)) {
                $this->Flash->success(__('Konto email dodane. Uruchom „bin/cake crm_email_poll" żeby zsynchronizować.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu konta.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            unset($data['company_id']);
            $data['is_active'] = !empty($data['is_active']);
            $data['use_ssl']   = !empty($data['use_ssl']);
            $plain = trim((string)($data['password'] ?? ''));
            unset($data['password']);

            $entity = $EA->patchEntity($entity, $data);
            if ($plain !== '') {
                $entity->password_encrypted = $EA->encryptPassword($plain);
            }

            if ($EA->save($entity)) {
                $this->Flash->success(__('Konto zaktualizowane.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        $EA->delete($entity);
        $this->Flash->success(__('Konto usunięte.'));
        $this->redirect(['action' => 'index']);
    }

    /**
     * FALA 13: OAuth 2.0 z Gmail - redirect do consent screen.
     * GET /crm/email-accounts/google-auth
     */
    public function googleAuth(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        // DEBUG mode - pokaz dokladnie co wysylamy zamiast redirect
        // Wywolaj /crm/email-accounts/google-auth?debug=1 zeby zobaczyc.
        $isDebug = $this->request->getQuery('debug') === '1';

        // Zapisujemy user context w sesji - callback bez auth mozliwosci
        $this->request->getSession()->write('GoogleOauth.pendingCompanyId', $companyId);
        $this->request->getSession()->write('GoogleOauth.pendingUserId', $userId);
        $this->request->getSession()->write('GoogleOauth.state', bin2hex(random_bytes(16)));

        try {
            $service = new \App\Service\GmailApiService();
            $state = (string)$this->request->getSession()->read('GoogleOauth.state');
            $url = $service->getAuthorizationUrl($state);

            if ($isDebug) {
                $this->autoRender = false;
                $parsed = parse_url($url);
                parse_str($parsed['query'] ?? '', $params);
                $redirectUri = $params['redirect_uri'] ?? '(brak!)';
                $clientId    = $params['client_id'] ?? '(brak!)';
                $appBaseUrl  = (string)\Cake\Core\Configure::read('App.fullBaseUrl');
                $configUri   = (string)\Cake\Core\Configure::read('Google.redirectUri');

                // Klucz szyfrowania diagnostyka (FALA 13c)
                $salt = (string)\Cake\Core\Configure::read('Security.salt');
                $crmKey = (string)\Cake\Core\Configure::read('Crm.encryptionKey');
                $saltLen = strlen($salt);
                $crmKeyLen = strlen($crmKey);
                $saltPreview = $saltLen > 0 ? (mb_substr($salt, 0, 8) . '...' . mb_substr($salt, -4)) : '(PUSTY!)';
                $crmPreview  = $crmKeyLen > 0 ? (mb_substr($crmKey, 0, 8) . '...' . mb_substr($crmKey, -4)) : '(PUSTY)';
                $envSalt = getenv('SECURITY_SALT');
                $envSaltStatus = $envSalt === false ? 'NIE USTAWIONE (OK)' :
                    ($envSalt === '' ? '<span style="color:red;">USTAWIONE NA PUSTY STRING (blad!)</span>' :
                    'ustawione (' . strlen($envSalt) . ' znakow)');
                $appLocalPath = CONFIG . 'app_local.php';
                $appLocalExists = file_exists($appLocalPath);
                $appLocalReadable = $appLocalExists && is_readable($appLocalPath);
                $effectiveKey = $crmKey ?: $salt;
                $effectiveLen = strlen($effectiveKey);
                $encStatus = $effectiveLen >= 16 ? '<strong style="color:green;">OK</strong>' : '<strong style="color:red;">BRAK KLUCZA</strong>';

                $html = '<pre style="font-family:monospace; padding:20px; background:#f5f5f5; font-size:13px; line-height:1.6;">';
                $html .= "<strong>=== KLUCZ SZYFROWANIA DIAGNOSTYKA ===</strong>\n";
                $html .= "Status szyfrowania: {$encStatus}\n\n";
                $html .= "Crm.encryptionKey len:   <strong>{$crmKeyLen}</strong> - preview: {$crmPreview}\n";
                $html .= "Security.salt len:       <strong>{$saltLen}</strong> - preview: {$saltPreview}\n";
                $html .= "getenv(SECURITY_SALT):   {$envSaltStatus}\n";
                $html .= "app_local.php path:      " . htmlspecialchars($appLocalPath) . "\n";
                $html .= "app_local.php exists:    " . ($appLocalExists ? 'TAK' : '<span style="color:red;">NIE</span>') . "\n";
                $html .= "app_local.php readable:  " . ($appLocalReadable ? 'TAK' : '<span style="color:red;">NIE</span>') . "\n\n";
                if ($effectiveLen === 0) {
                    $html .= "<strong style='color:red;'>=== BRAK KLUCZA - NAPRAW NATYCHMIAST ===</strong>\n\n";
                    $html .= "Zaloguj sie na cyberfolks i edytuj plik:\n";
                    $html .= "<strong>" . htmlspecialchars($appLocalPath) . "</strong>\n\n";
                    $html .= "Znajdz koniec 'return [' na poczatku pliku i DOKLEJ WEWNATRZ:\n\n";
                    $html .= "    'Crm' => [\n";
                    $html .= "        'encryptionKey' => '4ceb86127366d33d2f2df5e1b50654ec701c639ecd70f23bcb3d5a4264ee8c3e',\n";
                    $html .= "    ],\n\n";
                    $html .= "<em>(To musi byc HARDCODED string, NIE env(). Wygeneruj wlasny przez:</em>\n";
                    $html .= "<em>php -r \"echo bin2hex(random_bytes(32));\")</em>\n\n";
                    $html .= "Potem: rm -rf tmp/cache/*\n\n---\n\n";
                }

                $html .= "<strong>=== Google OAuth DEBUG ===</strong>\n\n";
                $html .= "1. App.fullBaseUrl (config):    <strong>" . htmlspecialchars($appBaseUrl ?: '(pusty!)') . "</strong>\n";
                $html .= "2. Google.redirectUri (config): <strong>" . htmlspecialchars($configUri ?: '(pusty!)') . "</strong>\n";
                $html .= "3. Google.clientId (config):    " . htmlspecialchars(mb_substr($clientId, 0, 20) . '...') . "\n\n";
                $html .= "REDIRECT_URI wysylane do Google (skopiuj DOKLADNIE do Google Cloud Console):\n";
                $html .= "<strong style='background:yellow; padding:3px;'>" . htmlspecialchars($redirectUri) . "</strong>\n\n";
                $html .= "Actual URL request:\n" . htmlspecialchars($url) . "\n\n";
                $html .= "---\n\n<strong>ROZWIAZANIE redirect_uri_mismatch:</strong>\n";
                $html .= "1. Zaloguj sie na https://console.cloud.google.com/apis/credentials\n";
                $html .= "2. Otwórz OAuth 2.0 Client ID uzywany w tej aplikacji\n";
                $html .= "3. W sekcji 'Authorized redirect URIs' dodaj DOKLADNIE ten URL zolty wyzej\n";
                $html .= "4. Kliknij Save i poczekaj 30 sekund\n";
                $html .= "5. Wroc na /crm/email-accounts i kliknij 'Podlacz Gmail'\n";
                $html .= '</pre>';

                return $this->response->withStringBody($html)->withType('text/html');
            }

            $this->redirect($url);
            return null;
        } catch (\Throwable $e) {
            $this->Flash->error(sprintf(__('Google OAuth: %s'), $e->getMessage()));
            $this->redirect(['action' => 'index']);
            return null;
        }
    }

    /**
     * GET /crm/email-accounts/google-callback?code=X&state=Y
     * Google przekierowuje tutaj po consent.
     */
    public function googleCallback(): void
    {
        $this->request->allowMethod(['get']);
        $code = trim((string)$this->request->getQuery('code', ''));
        $state = trim((string)$this->request->getQuery('state', ''));
        $error = trim((string)$this->request->getQuery('error', ''));

        $sessionState = (string)$this->request->getSession()->read('GoogleOauth.state', '');
        $companyId = (string)$this->request->getSession()->read('GoogleOauth.pendingCompanyId', '');
        $userId    = (string)$this->request->getSession()->read('GoogleOauth.pendingUserId', '');

        if ($error !== '') {
            $this->Flash->error(sprintf(__('Google OAuth blad: %s'), $error));
            $this->redirect(['action' => 'index']);
            return;
        }
        if ($code === '' || $state !== $sessionState) {
            $this->Flash->error(__('Blad OAuth - nieprawidlowy state lub brak kodu.'));
            $this->redirect(['action' => 'index']);
            return;
        }
        if ($companyId === '') {
            $this->Flash->error(__('Sesja OAuth wygasla - sprobuj ponownie.'));
            $this->redirect(['action' => 'index']);
            return;
        }

        try {
            $service = new \App\Service\GmailApiService();
            $tokens = $service->exchangeCodeForToken($code);
            $email = $service->getUserEmail($tokens['access_token']) ?: 'unknown@gmail.com';

            $EA = $this->fetchTable('CrmEmailAccounts');
            // Sprawdz czy juz istnieje konto dla tego emaila
            $existing = $EA->find()
                ->where(['company_id' => $companyId, 'username' => $email])
                ->first();
            $entity = $existing ?: $EA->newEmptyEntity();
            $entity->company_id = $companyId;
            $entity->user_id = $userId ?: null;
            $entity->label = $existing?->label ?: ('Gmail: ' . $email);
            $entity->auth_type = 'gmail_oauth';
            $entity->username = $email;
            // IMAP fields nullable dla oauth
            $entity->imap_host = null;
            $entity->imap_port = null;
            $entity->use_ssl = false;
            $entity->password_encrypted = null;
            $entity->folder = 'INBOX';
            $entity->sync_frequency_min = $existing?->sync_frequency_min ?: 5;
            $entity->is_active = true;
            // Encrypt tokens
            $entity->oauth_access_token = $EA->encryptPassword($tokens['access_token']);
            if (!empty($tokens['refresh_token'])) {
                $entity->oauth_refresh_token = $EA->encryptPassword($tokens['refresh_token']);
            }
            $expiresIn = (int)($tokens['expires_in'] ?? 3600);
            $entity->oauth_expires_at = new \Cake\I18n\DateTime('+' . $expiresIn . ' seconds');
            $entity->last_error = null;

            if ($EA->save($entity)) {
                $this->Flash->success(sprintf(__('Konto Gmail %s dodane. Uruchom `bin/cake crm_email_poll` zeby zsynchronizowac.'), $email));
            } else {
                $this->Flash->error(__('Blad zapisu konta OAuth: ') . json_encode($entity->getErrors()));
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Google OAuth callback failed: ' . $e->getMessage());
            $this->Flash->error(sprintf(__('Google OAuth blad: %s'), $e->getMessage()));
        }
        $this->request->getSession()->delete('GoogleOauth');
        $this->redirect(['action' => 'index']);
    }

    /**
     * Test polaczenia IMAP - bez pobierania nowych, tylko login+status folderu.
     */
    public function test(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        if (!function_exists('imap_open')) {
            $this->Flash->error(__('PHP IMAP extension nie jest zainstalowane na serwerze.'));
            $this->redirect(['action' => 'index']);
            return;
        }

        $password = $EA->decryptPassword($entity->password_encrypted);
        $ssl = $entity->use_ssl ? '/ssl' : '';
        $mailbox = sprintf('{%s:%d/imap%s}%s', $entity->imap_host, $entity->imap_port, $ssl, $entity->folder);

        try {
            $conn = @imap_open($mailbox, $entity->username, $password, OP_READONLY);
            if (!$conn) {
                $err = imap_last_error() ?: 'nieznany błąd';
                $entity->last_error = 'TEST: ' . $err;
                $EA->save($entity);
                $this->Flash->error(sprintf(__('Test IMAP nieudany: %s'), $err));
            } else {
                $status = imap_status($conn, $mailbox, SA_MESSAGES | SA_UIDNEXT);
                imap_close($conn);
                $entity->last_error = null;
                $EA->save($entity);
                $this->Flash->success(sprintf(__('Połączenie OK. Wiadomości w folderze: %d, następny UID: %d'),
                    $status->messages ?? 0, $status->uidnext ?? 0));
            }
        } catch (\Throwable $e) {
            $this->Flash->error(sprintf(__('Wyjątek IMAP: %s'), $e->getMessage()));
        }
        $this->redirect(['action' => 'index']);
    }
}

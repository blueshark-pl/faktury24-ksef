<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Datasource\EntityInterface;
use Cake\I18n\I18n;
use Cake\Log\Log;
use Cake\Mailer\Message;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use CakeDC\Users\Mailer\UsersMailer;

class MyUsersMailer extends UsersMailer
{
    /**
     * Metadane do zalogowania po udanej wysyłce — wypełniane przez konfiguracyjne
     * metody (welcome/resetPassword/...). Odczytywane w send() po parent::send().
     *
     * @var array{user_id:?string, email:string, type:string, lang:string}|null
     */
    private ?array $pendingLog = null;

    public function validation(EntityInterface $user, array $options = []): void
    {
        parent::validation($user, $options);

        $firstName = (string)($user->get('first_name') ?? '');
        $subject   = $firstName !== ''
            ? __('{0}, potwierdź rejestrację', $firstName)
            : __('Potwierdź rejestrację');

        $this->setSubject($subject);
        $this->appendLocaleToActivationUrl();
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_validation');

        $this->markForLog($user, 'validation');
    }

    public function resetPassword(EntityInterface $user, array $options = []): void
    {
        parent::resetPassword($user, $options);

        $firstName = (string)($user->get('first_name') ?? '');
        $subject   = $firstName !== ''
            ? __('{0}, ustaw nowe hasło', $firstName)
            : __('Ustaw nowe hasło');

        $this->setSubject($subject);
        $this->appendLocaleToActivationUrl();
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_reset_password');

        $this->markForLog($user, 'reset_password');
    }

    public function socialAccountValidation(EntityInterface $user, EntityInterface $socialAccount): void
    {
        parent::socialAccountValidation($user, $socialAccount);

        $firstName = (string)($user->get('first_name') ?? '');
        $subject   = $firstName !== ''
            ? __('{0}, potwierdź logowanie kontem społecznościowym', $firstName)
            : __('Potwierdź logowanie kontem społecznościowym');

        $this->setSubject($subject);
        $this->appendLocaleToActivationUrl();
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_social_account_validation');

        $this->markForLog($user, 'social_validation');
    }

    /**
     * E-mail powitalny — wysyłany ręcznie z panelu admina po utworzeniu konta.
     * Treść maila zależy od roli usera (role-specific bloki w szablonie).
     * Locale jest już ustawione przez kontroler (I18n::setLocale($lang)) zanim ta
     * metoda jest wywoływana — wszystkie __() w szablonie renderują się w tym języku.
     *
     * @param EntityInterface $user user z polami: email, first_name, role, token
     * @param string $lang 'pl'|'en' — dla URL (?lang=...)
     */
    public function welcome(EntityInterface $user, string $lang = 'pl'): void
    {
        $lang = in_array($lang, ['pl', 'en'], true) ? $lang : 'pl';

        $firstName = (string)($user->get('first_name') ?? '');
        $role      = (string)($user->get('role') ?? 'user');

        $subject = $firstName !== ''
            ? __('{0}, witamy w Booklio TMS!', $firstName)
            : __('Witamy w Booklio TMS!');

        $urlArr = \CakeDC\Users\Utility\UsersUrl::actionUrl('resetPassword', [
            '_full' => true,
            $user->get('token'),
            '?'     => ['lang' => $lang],
        ]);
        $resetUrl = Router::url($urlArr, true);

        $this
            ->setTo($user->get('email'))
            ->setSubject($subject)
            ->setEmailFormat(Message::MESSAGE_BOTH)
            ->setViewVars([
                'user'      => $user,
                'firstName' => $firstName,
                'role'      => $role,
                'resetUrl'  => $resetUrl,
                'lang'      => $lang,
            ]);

        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_welcome');

        $this->markForLog($user, 'welcome', $lang);
    }

    public function sendToken(EntityInterface $user, string $token): void
    {
        parent::sendToken($user, $token);

        $firstName = (string)($user->get('first_name') ?? '');
        $subject   = $firstName !== ''
            ? __('{0}, jednorazowe logowanie', $firstName)
            : __('Jednorazowe logowanie');

        $this->setSubject($subject);
        $this->setEmailFormat(Message::MESSAGE_BOTH);
        $this->appendLocaleToLink('loginLink');
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_onetime_token');

        $this->markForLog($user, 'onetime_token');
    }

    /**
     * Override Mailer::send() — po udanej wysyłce zapisuje rekord do user_email_logs.
     * Wyjątek z parent::send() (transport down itp.) zapisujemy ze statusem 'failed'.
     */
    public function send(?string $action = null, array $args = [], array $headers = []): array
    {
        try {
            $result = parent::send($action, $args, $headers);
            $this->logEmail('sent');
            return $result;
        } catch (\Throwable $e) {
            $this->logEmail('failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Zaznacz że po wysyłce trzeba zalogować. Wywołane z konfig-metod (welcome/...).
     */
    private function markForLog(EntityInterface $user, string $type, ?string $lang = null): void
    {
        if ($lang === null) {
            $locale = (string)I18n::getLocale();
            $lang   = str_starts_with($locale, 'en') ? 'en' : 'pl';
        }
        $this->pendingLog = [
            'user_id' => (string)($user->get('id') ?? ''),
            'email'   => (string)$user->get('email'),
            'type'    => $type,
            'lang'    => $lang,
        ];
    }

    /**
     * Faktyczny zapis do DB. Nie rzuca wyjątkiem — log emaila nie może
     * blokować flow wysyłki/aplikacji.
     */
    private function logEmail(string $status, ?string $errorMessage = null): void
    {
        if (!$this->pendingLog) {
            return;
        }

        try {
            $UserEmailLogs = TableRegistry::getTableLocator()->get('UserEmailLogs');

            // Aktualnie zalogowany user (jeśli można pobrać z request scope) → sender
            $senderId    = null;
            $senderEmail = null;
            try {
                $request = \Cake\Routing\Router::getRequest();
                if ($request) {
                    $identity = $request->getAttribute('identity');
                    if ($identity) {
                        $senderId    = (string)$identity->getIdentifier();
                        $senderEmail = (string)($identity->get('email') ?? '');
                    }
                }
            } catch (\Throwable) { /* best-effort */ }

            $log = $UserEmailLogs->newEmptyEntity();
            $log = $UserEmailLogs->patchEntity($log, [
                'user_id'         => $this->pendingLog['user_id'] ?: null,
                'recipient_email' => $this->pendingLog['email'],
                'email_type'      => $this->pendingLog['type'],
                'lang'            => $this->pendingLog['lang'],
                'subject'         => (string)$this->getSubject(),
                'status'          => $status,
                'error_message'   => $errorMessage,
                'sender_user_id'  => $senderId,
                'sender_email'    => $senderEmail,
                'created'         => \Cake\I18n\DateTime::now(),
            ]);

            if (!$UserEmailLogs->save($log)) {
                Log::warning('UserEmailLogs save failed: ' . json_encode($log->getErrors()));
            }
        } catch (\Throwable $e) {
            Log::warning('UserEmailLogs exception: ' . $e->getMessage());
        } finally {
            $this->pendingLog = null;
        }
    }

    /**
     * Doklej ?lang=pl|en do linku aktywacyjnego — kliknięcie z e-maila
     * automatycznie ustawi locale, którego user używał w momencie wysyłki.
     */
    private function appendLocaleToActivationUrl(): void
    {
        $this->appendLocaleToLink('activationUrl');
    }

    /**
     * Doklej ?lang=pl|en do dowolnej zmiennej widoku zawierającej URL.
     */
    private function appendLocaleToLink(string $varName): void
    {
        $vars = $this->viewBuilder()->getVars();
        $link = (string)($vars[$varName] ?? '');
        if ($link === '') {
            return;
        }

        $locale = (string)I18n::getLocale();
        $lang   = str_starts_with($locale, 'en') ? 'en' : 'pl';

        if (preg_match('/[?&]lang=/', $link)) {
            return;
        }

        $separator = str_contains($link, '?') ? '&' : '?';
        $newLink   = $link . $separator . 'lang=' . $lang;

        $this->setViewVars([$varName => $newLink]);
    }
}

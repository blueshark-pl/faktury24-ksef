<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Datasource\EntityInterface;
use Cake\I18n\I18n;
use Cake\Mailer\Message;
use CakeDC\Users\Mailer\UsersMailer;

class MyUsersMailer extends UsersMailer
{
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

        // Link do ustawienia hasła (token resetu) + ?lang=
        $resetUrl = \CakeDC\Users\Utility\UsersUrl::actionUrl('resetPassword', [
            '_full' => true,
            $user->get('token'),
        ]);
        $separator = str_contains($resetUrl, '?') ? '&' : '?';
        $resetUrl .= $separator . 'lang=' . $lang;

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

        // Bez nadpisywania jeśli już jest lang= w query
        if (preg_match('/[?&]lang=/', $link)) {
            return;
        }

        $separator = str_contains($link, '?') ? '&' : '?';
        $newLink   = $link . $separator . 'lang=' . $lang;

        $this->setViewVars([$varName => $newLink]);
    }
}

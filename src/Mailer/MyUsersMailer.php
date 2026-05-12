<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Datasource\EntityInterface;
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
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_social_account_validation');
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
        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_onetime_token');
    }
}

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
        $prefix = $firstName !== '' ? $firstName . ', ' : '';

        $this->setSubject($prefix . 'Potwierdź rejestrację');

        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_validation');
    }

    public function resetPassword(EntityInterface $user, array $options = []): void
    {
        parent::resetPassword($user, $options);

        $firstName = (string)($user->get('first_name') ?? '');
        $prefix = $firstName !== '' ? $firstName . ', ' : '';

        $this->setSubject($prefix . 'Ustaw nowe hasło');

        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_reset_password');
    }

    public function socialAccountValidation(EntityInterface $user, EntityInterface $socialAccount): void
    {
        parent::socialAccountValidation($user, $socialAccount);

        $firstName = (string)($user->get('first_name') ?? '');
        $prefix = $firstName !== '' ? $firstName . ', ' : '';

        $this->setSubject($prefix . 'Potwierdź logowanie kontem społecznościowym');

        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_social_account_validation');
    }

    public function sendToken(EntityInterface $user, string $token): void
    {
        parent::sendToken($user, $token);

        $firstName = (string)($user->get('first_name') ?? '');
        $prefix = $firstName !== '' ? $firstName . ', ' : '';
        $this->setSubject($prefix . 'Jednorazowe logowanie');

        $this->setEmailFormat(Message::MESSAGE_BOTH);

        $this->viewBuilder()
            ->setLayout('users')
            ->setTemplate('users_onetime_token');
    }
}

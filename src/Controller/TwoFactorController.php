<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use RobThree\Auth\TwoFactorAuth;

class TwoFactorController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        $parentResult = parent::beforeFilter($event);
        if ($parentResult !== null) {
            return $parentResult;
        }

        $identity = $this->getRequest()->getAttribute('identity');
        if (!$identity) {
            return $this->redirect([
                'plugin' => 'CakeDC/Users',
                'controller' => 'Users',
                'action' => 'login',
            ]);
        }

        return null;
    }

    public function index()
    {
        $identity = $this->getRequest()->getAttribute('identity');
        $userId = $identity?->get('id');

        $usersTable = $this->fetchUsersTable();
        $user = $usersTable->get($userId);

        $secret = (string)($user->get('secret') ?? '');
        $secretVerified = (bool)($user->get('secret_verified') ?? false);

        $isEnabled = $secretVerified && $secret !== '';
        $isInSetup = !$isEnabled && $secret !== '';

        $qrDataUri = null;
        if ($isInSetup) {
            $qrDataUri = $this->getTfa()->getQRCodeImageAsDataUri(
                (string)($identity?->get('email') ?? ''),
                $secret,
            );
        }

        $this->set([
            'isEnabled' => $isEnabled,
            'isInSetup' => $isInSetup,
            'qrDataUri' => $qrDataUri,
        ]);
    }

    public function enable()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->getRequest()->getAttribute('identity');
        $userId = $identity?->get('id');

        $usersTable = $this->fetchUsersTable();
        $user = $usersTable->get($userId);

        $secret = (string)($user->get('secret') ?? '');
        if ($secret === '') {
            $secret = $this->getTfa()->createSecret();
        }

        $usersTable
            ->updateQuery()
            ->set([
                'secret' => $secret,
                'secret_verified' => false,
            ])
            ->where(['id' => $userId])
            ->execute();

        $this->Flash->success('Rozpoczęto konfigurację 2FA. Zeskanuj kod QR i wpisz kod z aplikacji.');

        return $this->redirect(['action' => 'index']);
    }

    public function verify()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->getRequest()->getAttribute('identity');
        $userId = $identity?->get('id');

        $code = (string)$this->getRequest()->getData('code');

        $usersTable = $this->fetchUsersTable();
        $user = $usersTable->get($userId);

        $secret = (string)($user->get('secret') ?? '');
        if ($secret === '') {
            $this->Flash->error('Brak rozpoczętej konfiguracji 2FA.');

            return $this->redirect(['action' => 'index']);
        }

        $ok = $this->getTfa()->verifyCode($secret, $code);
        if (!$ok) {
            $this->Flash->error('Kod weryfikacyjny jest nieprawidłowy. Spróbuj ponownie.');

            return $this->redirect(['action' => 'index']);
        }

        $usersTable
            ->updateQuery()
            ->set(['secret_verified' => true])
            ->where(['id' => $userId])
            ->execute();

        $this->Flash->success('2FA zostało włączone dla Twojego konta.');

        return $this->redirect(['action' => 'index']);
    }

    public function disable()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->getRequest()->getAttribute('identity');
        $userId = $identity?->get('id');

        $usersTable = $this->fetchUsersTable();
        $usersTable
            ->updateQuery()
            ->set([
                'secret' => null,
                'secret_verified' => false,
            ])
            ->where(['id' => $userId])
            ->execute();

        $this->Flash->success('2FA zostało wyłączone dla Twojego konta.');

        return $this->redirect(['action' => 'index']);
    }

    private function fetchUsersTable()
    {
        $usersTableAlias = (string)(Configure::read('Users.table') ?: 'CakeDC/Users.Users');

        return $this->fetchTable($usersTableAlias);
    }

    private function getTfa(): TwoFactorAuth
    {
        try {
            return new TwoFactorAuth(
                qrcodeprovider: Configure::read('OneTimePasswordAuthenticator.qrcodeprovider'),
                issuer: Configure::read('OneTimePasswordAuthenticator.issuer'),
                digits: Configure::read('OneTimePasswordAuthenticator.digits'),
                period: Configure::read('OneTimePasswordAuthenticator.period'),
                algorithm: Configure::read('OneTimePasswordAuthenticator.algorithm'),
                rngprovider: Configure::read('OneTimePasswordAuthenticator.rngprovider'),
            );
        } catch (\Throwable $t) {
            throw new \RuntimeException(
                __d(
                    'cake_d_c/users',
                    'An error has occurred configuring OneTimePasswordAuthenticator. {0}',
                    $t->getMessage(),
                ),
                previous: $t,
            );
        }
    }
}

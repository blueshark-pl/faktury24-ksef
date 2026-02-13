<?php
declare(strict_types=1);

namespace App\Authentication;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use CakeDC\Auth\Authentication\OneTimePasswordAuthenticationCheckerInterface;

class OptInOneTimePasswordAuthenticationChecker implements OneTimePasswordAuthenticationCheckerInterface
{
    public function isEnabled(): bool
    {
        return (bool)Configure::read('OneTimePasswordAuthenticator.login');
    }

    public function isRequired(?array $user = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (empty($user)) {
            return false;
        }

        $userId = $user['id'] ?? null;
        if ($userId === null || $userId === '') {
            return false;
        }

        $usersTableAlias = (string)(Configure::read('Users.table') ?: 'CakeDC/Users.Users');
        $usersTable = TableRegistry::getTableLocator()->get($usersTableAlias);

        $entity = $usersTable
            ->find()
            ->select(['id', 'secret', 'secret_verified'])
            ->where(['id' => $userId])
            ->first();

        if ($entity === null) {
            return false;
        }

        $secret = (string)($entity->get('secret') ?? '');
        $secretVerified = (bool)($entity->get('secret_verified') ?? false);

        return $secretVerified && $secret !== '';
    }
}

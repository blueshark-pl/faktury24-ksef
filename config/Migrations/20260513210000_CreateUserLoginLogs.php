<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Log logowań użytkowników — kto, kiedy, z jakiego IP/przeglądarki.
 *
 * Zapisywane raz per sesja w AppController::beforeFilter (session flag chroni
 * przed multi-insertem przy każdym page view). Logout czyści flagę.
 *
 * Pole 'kind' rozróżnia źródło logowania w przyszłości (np. 'web', 'api',
 * 'sso') — na razie zawsze 'web'.
 */
class CreateUserLoginLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_login_logs', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('user_id',    'uuid',     ['null' => true, 'default' => null, 'comment' => 'FK do users.id (null gdy user usunięty)'])
            ->addColumn('username',   'string',   ['limit' => 200, 'null' => false, 'comment' => 'Snapshot login/email z chwili logowania'])
            ->addColumn('role',       'string',   ['limit' => 50,  'null' => true,  'default' => null])
            ->addColumn('ip',         'string',   ['limit' => 45,  'null' => true,  'default' => null, 'comment' => 'IPv4/IPv6']) // INET6
            ->addColumn('user_agent', 'string',   ['limit' => 500, 'null' => true,  'default' => null])
            ->addColumn('kind',       'string',   ['limit' => 20,  'null' => false, 'default' => 'web', 'comment' => 'web|api|sso|2fa'])
            ->addColumn('logged_at',  'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['logged_at'])
            ->addIndex(['user_id', 'logged_at'])
            ->create();
    }
}

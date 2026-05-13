<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Audyt operacji 'wcielania się w użytkownika' (impersonation).
 *
 * Pozwala admin'owi zobaczyć system z perspektywy innego usera — co widzi
 * klient w portalu, jak wygląda interface dla spedytora itp.
 *
 * Każda sesja impersonacji = 1 wpis: started_at + opcjonalnie ended_at
 * (gdy admin świadomie wróci do siebie). Sesje wygaszone naturalnym
 * timeout'em pozostają z ended_at=NULL — wiadomo wtedy że nie był
 * 'stop' kliknięty.
 */
class CreateUserImpersonationLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_impersonation_logs', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('admin_user_id',    'uuid',     ['null' => false])
            ->addColumn('admin_username',   'string',   ['limit' => 200, 'null' => false])
            ->addColumn('target_user_id',   'uuid',     ['null' => false])
            ->addColumn('target_username',  'string',   ['limit' => 200, 'null' => false])
            ->addColumn('target_role',      'string',   ['limit' => 50,  'null' => true,  'default' => null])
            ->addColumn('ip',               'string',   ['limit' => 45,  'null' => true,  'default' => null])
            ->addColumn('user_agent',       'string',   ['limit' => 500, 'null' => true,  'default' => null])
            ->addColumn('started_at',       'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('ended_at',         'datetime', ['null' => true,  'default' => null])
            ->addIndex(['admin_user_id'])
            ->addIndex(['target_user_id'])
            ->addIndex(['started_at'])
            ->create();
    }
}

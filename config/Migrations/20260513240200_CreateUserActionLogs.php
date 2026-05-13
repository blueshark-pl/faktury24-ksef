<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Audyt akcji CRUD: kto, gdzie (controller+action), kiedy, IP, status,
 * opcjonalnie entity_id / payload snapshot.
 *
 * Loguje TYLKO żądania zmieniające stan (POST/PUT/PATCH/DELETE).
 * GET-y nie są zapisywane (zbyt dużo + nie modyfikują danych).
 */
class CreateUserActionLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_action_logs', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('user_id',     'uuid',     ['null' => true, 'default' => null])
            ->addColumn('username',    'string',   ['limit' => 200, 'null' => true, 'default' => null])
            ->addColumn('role',        'string',   ['limit' => 50,  'null' => true, 'default' => null])
            ->addColumn('method',      'string',   ['limit' => 10,  'null' => false, 'comment' => 'POST/PUT/PATCH/DELETE'])
            ->addColumn('controller',  'string',   ['limit' => 100, 'null' => false])
            ->addColumn('action',      'string',   ['limit' => 100, 'null' => false])
            ->addColumn('url',         'string',   ['limit' => 500, 'null' => false])
            ->addColumn('entity_id',   'string',   ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'pass.0 z route — często to ID rekordu'])
            ->addColumn('status_code', 'integer',  ['null' => true, 'default' => null])
            ->addColumn('ip',          'string',   ['limit' => 45,  'null' => true, 'default' => null])
            ->addColumn('user_agent',  'string',   ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('created',     'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['controller', 'action'])
            ->addIndex(['created'])
            ->addIndex(['entity_id'])
            ->create();
    }
}

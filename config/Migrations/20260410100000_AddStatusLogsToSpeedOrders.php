<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Audyt zmian statusów zleceń Speed ERP:
 *  - Pola *_by w speed_orders (kto zaznaczył POL/POD/FK/FS)
 *  - Tabela speed_order_status_logs (pełna historia zmian z userem)
 */
class AddStatusLogsToSpeedOrders extends AbstractMigration
{
    public function change(): void
    {
        // ── Pola "kto zaznaczył" w speed_orders ─────────────────────────────
        $this->table('speed_orders')
            ->addColumn('pol_by', 'string', ['limit' => 200, 'null' => true, 'default' => null,
                'comment' => 'Login/email usera który zaznaczył POL', 'after' => 'pol_at'])
            ->addColumn('pod_by', 'string', ['limit' => 200, 'null' => true, 'default' => null,
                'comment' => 'Login/email usera który zaznaczył POD', 'after' => 'pod_at'])
            ->addColumn('fk_by',  'string', ['limit' => 200, 'null' => true, 'default' => null,
                'comment' => 'Login/email usera który zaznaczył FK',  'after' => 'fk_at'])
            ->addColumn('fs_by',  'string', ['limit' => 200, 'null' => true, 'default' => null,
                'comment' => 'Login/email usera który zaznaczył FS',  'after' => 'fs_at'])
            ->update();

        // ── Tabela historii zmian statusów ───────────────────────────────────
        $this->table('speed_order_status_logs', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('speed_order_id', 'integer', ['null' => false])
            ->addColumn('field',          'string',  ['limit' => 50,  'null' => false, 'comment' => 'pol_at|pod_at|fk_at|fs_at|nordlogis_status'])
            ->addColumn('old_value',      'string',  ['limit' => 200, 'null' => true,  'default' => null])
            ->addColumn('new_value',      'string',  ['limit' => 200, 'null' => true,  'default' => null])
            ->addColumn('user_id',        'integer', ['null' => true,  'default' => null])
            ->addColumn('username',       'string',  ['limit' => 200, 'null' => true,  'default' => null, 'comment' => 'login lub email usera w chwili zmiany'])
            ->addColumn('created',        'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['speed_order_id'])
            ->addIndex(['created'])
            ->create();
    }
}

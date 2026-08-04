<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Rozszerzenie speed_orders o wsparcie recznie tworzonych zlecen.
 *
 *  - speed_id → nullable (bylo NOT NULL UNIQUE, teraz manualne zlecenia
 *    beda mialy speed_id = NULL). Unique index zostaje — MySQL dopuszcza
 *    wiele NULL w unique index.
 *  - source: 'speed' | 'manual' — zrodlo zlecenia. Default 'speed' zeby
 *    backfill istniejacych rekordow zadzialal jednym UPDATE.
 *  - manual_seq: numer kolejny per (company_nip, rok, mc) dla manualnych.
 *    Uzywany do generowania symbolu 'M-NNNN/MM/YYYY'.
 *  - Unique index na (company_nip, source, rok, mc, manual_seq) —
 *    zabezpieczenie przed race condition przy jednoczesnym zapisie.
 */
class AddSourceToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $t = $this->table('speed_orders');

        // speed_id nullable (wczesniej NOT NULL, ale unique zostaje).
        $t->changeColumn('speed_id', 'integer', [
            'null'    => true,
            'signed'  => false,
            'comment' => 'GLO_ID z Speed ERP (NULL dla source=manual)',
        ])->update();

        $t->addColumn('source', 'string', [
                'limit'   => 10,
                'null'    => false,
                'default' => 'speed',
                'after'   => 'speed_id',
                'comment' => 'speed | manual',
            ])
            ->addColumn('manual_seq', 'integer', [
                'null'    => true,
                'signed'  => false,
                'after'   => 'source',
                'comment' => 'Numer kolejny per (company_nip, rok, mc) dla source=manual',
            ])
            ->addIndex(['source'], ['name' => 'BY_SOURCE'])
            ->addIndex(
                ['company_nip', 'source', 'rok', 'mc', 'manual_seq'],
                ['name' => 'UNQ_MANUAL_SEQ', 'unique' => true]
            )
            ->update();

        // Backfill: wszystkie istniejace rekordy pochodza ze Speed.
        $this->execute("UPDATE speed_orders SET source = 'speed' WHERE source IS NULL OR source = ''");
    }

    public function down(): void
    {
        $t = $this->table('speed_orders');
        $t->removeIndexByName('UNQ_MANUAL_SEQ')
            ->removeIndexByName('BY_SOURCE')
            ->removeColumn('manual_seq')
            ->removeColumn('source')
            ->update();

        $t->changeColumn('speed_id', 'integer', [
            'null'    => false,
            'signed'  => false,
            'comment' => 'GLO_ID z Speed ERP',
        ])->update();
    }
}

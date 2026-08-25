<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Etykiety wlasne (Trello-style labels) dla leadow.
 * Kazda firma tworzy swoja liste etykiet, np. 'ADR', 'Pilne', 'Duzy kontrakt'.
 * Kazdy lead moze miec wiele etykiet (many-to-many).
 */
class CreateLeadLabels extends AbstractMigration
{
    public function up(): void
    {
        // Katalog etykiet per firma
        $t = $this->table('lead_labels', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('color', 'string', ['limit' => 7, 'null' => false, 'default' => '#94C81F',
                'comment' => 'Hex color np. #ff5733'])
            ->addColumn('sort_order', 'integer', ['limit' => 5, 'null' => false, 'default' => 100])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['company_id', 'sort_order'], ['name' => 'BY_COMPANY_SORT'])
            ->create();

        // Pivot many-to-many
        $p = $this->table('leads_lead_labels', ['id' => false, 'primary_key' => ['lead_id', 'label_id']]);
        $p->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('label_id', 'char', ['limit' => 36, 'null' => false])
            ->addIndex(['lead_id'], ['name' => 'BY_LEAD'])
            ->addIndex(['label_id'], ['name' => 'BY_LABEL'])
            ->create();
    }

    public function down(): void
    {
        $this->table('leads_lead_labels')->drop()->save();
        $this->table('lead_labels')->drop()->save();
    }
}

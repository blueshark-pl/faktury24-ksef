<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Szablony zlecen dla powtarzajacych sie tras/klientow.
 * User zapisuje konfiguracje (np. "HB RTS standard NL->DE") i pozniej
 * jednym clickiem prefilluje formularz z zapisanego szablonu.
 *
 * Pola snapshot: co ma byc uzupelnione przy zaladowaniu szablonu.
 * NIE zapisujemy pol jednorazowych: symbol, date_doc, our_ref, cmr_number.
 */
class CreateSpeedOrderTemplates extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('speed_order_templates')) return;
        $this->table('speed_order_templates', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false,
                'comment' => 'Nazwa robocza szablonu (np. "HB RTS standard NL->DE")'])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_favorite', 'boolean', ['default' => false,
                'comment' => 'Wypisany na gorze listy - dla najczestszych'])

            // Snapshot pol - JSON zeby elastycznie skladowac wszystko co jest w formie
            ->addColumn('payload_json', 'text', ['null' => false,
                'comment' => 'JSON z polami: buyer_*, load_*, unload_*, cargo_*, transport_*, finance_*, incoterms, contract, itd.'])

            ->addColumn('usage_count', 'integer', ['default' => 0, 'signed' => false,
                'comment' => 'Ile razy uzyto - dla sortowania po popularity'])
            ->addColumn('last_used_at', 'datetime', ['null' => true])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'is_favorite', 'usage_count'], ['name' => 'BY_COMPANY_FAV'])
            ->addIndex(['company_id', 'name'], ['name' => 'BY_COMPANY_NAME'])
            ->create();
    }

    public function down(): void
    {
        $this->table('speed_order_templates')->drop()->save();
    }
}

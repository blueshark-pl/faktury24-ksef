<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Slownik branz per firma + FK leads.industry_id.
 * Admin tworzy liste branz (hutnicza, piekarnia, spozywcza, farmacja itd.),
 * lead ma dokladnie jedna branze.
 */
class CreateLeadIndustries extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('lead_industries', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('sort_order', 'integer', ['limit' => 5, 'null' => false, 'default' => 100])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['company_id', 'sort_order'], ['name' => 'BY_COMPANY_SORT'])
            ->addIndex(['company_id', 'name'], ['unique' => true, 'name' => 'UNQ_COMPANY_NAME'])
            ->create();

        // FK w leads
        $leads = $this->table('leads');
        if (!$leads->hasColumn('industry_id')) {
            $leads->addColumn('industry_id', 'char', [
                'limit' => 36, 'null' => true, 'default' => null,
                'comment' => 'FK do lead_industries (nullable)',
            ])->update();
        }
        if (!$leads->hasIndexByName('BY_INDUSTRY')) {
            $leads->addIndex(['company_id', 'industry_id'], ['name' => 'BY_INDUSTRY'])->update();
        }
    }

    public function down(): void
    {
        $leads = $this->table('leads');
        if ($leads->hasIndexByName('BY_INDUSTRY')) $leads->removeIndexByName('BY_INDUSTRY')->update();
        if ($leads->hasColumn('industry_id')) $leads->removeColumn('industry_id')->update();
        $this->table('lead_industries')->drop()->save();
    }
}

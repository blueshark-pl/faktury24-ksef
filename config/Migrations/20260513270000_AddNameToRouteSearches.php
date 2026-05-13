<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje nazwę do route_searches — wpisy z name są szablonami (sticky list),
 * bez name są zwykłą historią.
 */
class AddNameToRouteSearches extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('route_searches');
        if (!$table->hasColumn('name')) {
            $table
                ->addColumn('name', 'string', ['limit' => 120, 'null' => true, 'after' => 'company_id'])
                ->update();
        }
    }
}

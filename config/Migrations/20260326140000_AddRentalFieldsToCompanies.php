<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddRentalFieldsToCompanies extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('companies');
        $table
            ->addColumn('rental_enabled', 'boolean', ['default' => 0, 'null' => false, 'after' => 'profile_mode'])
            ->addColumn('rental_first_name', 'string', ['limit' => 120, 'null' => true, 'default' => null, 'after' => 'rental_enabled'])
            ->addColumn('rental_last_name', 'string', ['limit' => 120, 'null' => true, 'default' => null, 'after' => 'rental_first_name'])
            ->addColumn('rental_nip', 'string', ['limit' => 10, 'null' => true, 'default' => null, 'after' => 'rental_last_name'])
            ->addColumn('rental_street', 'string', ['limit' => 160, 'null' => true, 'default' => null, 'after' => 'rental_nip'])
            ->addColumn('rental_postal_code', 'string', ['limit' => 6, 'null' => true, 'default' => null, 'after' => 'rental_street'])
            ->addColumn('rental_city', 'string', ['limit' => 120, 'null' => true, 'default' => null, 'after' => 'rental_postal_code'])
            ->update();
    }
}

<?php
// config/Migrations/20251002_AddUniqueNipToCompanies.php
use Migrations\AbstractMigration;

class AddUniqueNipToCompanies extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('companies');

        // NIP jako VARCHAR(10) NULL, jeśli kolumna istnieje – dostosuj
        if ($table->hasColumn('nip')) {
            $this->execute("ALTER TABLE `companies` MODIFY `nip` VARCHAR(10) NULL");
        } else {
            $table->addColumn('nip', 'string', ['limit' => 10, 'null' => true])->update();
        }

        // indeks unikalny
        if (!$table->hasIndexByName('uniq_companies_nip')) {
            $table->addIndex(['nip'], ['unique' => true, 'name' => 'uniq_companies_nip'])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('companies');
        if ($table->hasIndexByName('uniq_companies_nip')) {
            $table->removeIndexByName('uniq_companies_nip')->update();
        }
        // ewentualnie cofnij zmianę kolumny
    }
}

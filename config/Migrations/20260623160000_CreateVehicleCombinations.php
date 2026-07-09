<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Zestawy — ciągnik + naczepa + kierowca (nazwane, klikalne).
 *
 * Pozwala zdefiniować raz "Volvo FH-16 + Krone Cool Liner + Kowalski"
 * i wybrać cały zestaw w planerze tras jednym kliknięciem zamiast
 * osobno dobierać ciągnik, naczepę i kierowcę.
 */
class CreateVehicleCombinations extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('vehicle_combinations', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('name', 'string', [
            'limit' => 150, 'null' => false,
            'comment' => 'Nazwa robocza zestawu, np. "Volvo FH + Krone Cool + Kowalski"',
        ]);
        $table->addColumn('vehicle_id', 'char', [
            'limit' => 36, 'null' => true,
            'comment' => 'FK do vehicles.id (ciągnik lub solo)',
        ]);
        $table->addColumn('trailer_id', 'char', [
            'limit' => 36, 'null' => true,
            'comment' => 'FK do trailers.id (naczepa/przyczepa; NULL dla solo/bus)',
        ]);
        $table->addColumn('driver_id', 'char', [
            'limit' => 36, 'null' => true,
            'comment' => 'FK do drivers.id (kierowca przypisany do zestawu)',
        ]);
        $table->addColumn('notes', 'text', ['null' => true, 'default' => null]);
        $table->addColumn('is_active', 'boolean', ['null' => false, 'default' => true]);
        $table->addColumn('is_default', 'boolean', [
            'null' => false, 'default' => false,
            'comment' => 'Domyślny zestaw firmy (autoselect w planerze)',
        ]);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['company_id', 'is_active'], ['name' => 'BY_COMPANY_ACTIVE']);
        $table->addIndex(['company_id', 'is_default'], ['name' => 'BY_COMPANY_DEFAULT']);
        $table->addIndex(['vehicle_id'], ['name' => 'BY_VEHICLE']);
        $table->addIndex(['trailer_id'], ['name' => 'BY_TRAILER']);
        $table->addIndex(['driver_id'], ['name' => 'BY_DRIVER']);

        $table->create();
    }
}

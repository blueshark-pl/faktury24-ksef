<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje stawkę PLN/km do pojazdów — używana do kalkulacji ceny frachtu
 * w planerze /trasy (Twoja stawka × km + tolls).
 */
class AddRatePerKmToVehicles extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('vehicles');
        if (!$table->hasColumn('rate_per_km')) {
            $table
                ->addColumn('rate_per_km', 'decimal', [
                    'precision' => 8, 'scale' => 2, 'null' => true,
                    'after' => 'length_cm',
                    'comment' => 'stawka PLN/km dla kalkulatora frachtu',
                ])
                ->update();
        }
    }
}

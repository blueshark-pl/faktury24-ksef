<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dodaje pole spalania do pojazdow (l/100km) — do kalkulatora kosztow w planerze
 * tras. Do tej pory spalanie bylo hard-coded 30 l/100km w formularzu planera,
 * niezaleznie od wybranego pojazdu — co dawalo bledne szacunki.
 *
 * Wybor pojazdu w planerze bedzie teraz auto-fill'owal to pole.
 */
class AddFuelConsumptionToVehicles extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('vehicles');
        if (!$table->hasColumn('fuel_consumption_l_per_100km')) {
            $table
                ->addColumn('fuel_consumption_l_per_100km', 'decimal', [
                    'precision' => 5, 'scale' => 1, 'null' => true,
                    'after' => 'rate_per_km',
                    'comment' => 'Srednie spalanie w l/100km (do kalkulatora kosztow paliwa w planerze)',
                ])
                ->addColumn('fuel_type', 'string', [
                    'limit' => 15, 'null' => true, 'default' => 'diesel',
                    'after' => 'fuel_consumption_l_per_100km',
                    'comment' => 'diesel|petrol|lng|electric|hybrid',
                ])
                ->update();
        }
    }
}

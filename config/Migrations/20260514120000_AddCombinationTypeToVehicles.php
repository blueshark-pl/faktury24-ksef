<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje typ zestawu pojazdów do tabeli vehicles. Typ determinuje:
 *  - Standardowe wymiary (auto-fill w formie)
 *  - Kategorię toll (na cennikach autostrad)
 *  - Klasyfikację oversize
 *
 * Wartości (z menu Hogis i polskich standardów):
 *  - 'standard'  — Ciągnik + naczepa standard (16,5×2,55×4,0 m, 40t, 5 osi)
 *  - 'mega'      — Mega (16,5×2,55×3,0 m — niska naczepa, max objętość ~100m³)
 *  - 'fridge'    — Chłodnia (16,5×2,60×4,0 m — dopuszczalna szer. 2,60)
 *  - 'tandem'    — Ciężarówka + przyczepa drawbar (18,75 m max, 40t)
 *  - 'solo'      — Pojedyncza ciężarówka (12 m max)
 *  - 'bus'       — Autobus
 *  - 'oversize'  — Ponadnormatywny (wymaga zezwolenia, własne parametry)
 *  - null        — niezdefiniowany (kompatybilność z istniejącymi rekordami)
 */
class AddCombinationTypeToVehicles extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('vehicles');
        if (!$table->hasColumn('combination_type')) {
            $table->addColumn('combination_type', 'string', [
                'limit' => 20,
                'null' => true,
                'after' => 'type',
                'comment' => 'standard|mega|fridge|tandem|solo|bus|oversize',
            ])->update();
        }
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Naczepy / przyczepy — osobna encja z własnymi parametrami i kosztami.
 *
 * W planerze trasy user wybiera KOMBINACJĘ: ciągnik (vehicles) + naczepa (trailers) + kierowca (drivers).
 * Total: weight = tractor + trailer + cargo, axles = tractor.axle_count + trailer.axle_count.
 *
 * Typy naczep (Hogis-style):
 *  - 'curtain'  — firanka (kurtynowa, najpopularniejsza)
 *  - 'box'      — skrzynia / box
 *  - 'fridge'   — chłodnia (refrigerated)
 *  - 'tanker'   — cysterna
 *  - 'flatbed'  — platforma / lawety
 *  - 'drawbar'  — przyczepa drawbar (do tandem)
 *  - 'mega'    — naczepa mega (low-deck, max volume)
 *  - 'silo'     — silos
 *  - 'container'— do kontenerów
 *  - 'tipper'   — wywrotka
 */
class CreateTrailers extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('trailers')) return;
        $this->table('trailers', ['id' => false, 'primary_key' => ['id'], 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('plate', 'string', ['limit' => 24, 'null' => true, 'comment' => 'Rejestracja naczepy'])
            ->addColumn('vin', 'string', ['limit' => 32, 'null' => true])

            ->addColumn('type', 'string', ['limit' => 20, 'null' => false, 'default' => 'curtain',
                'comment' => 'curtain|box|fridge|tanker|flatbed|drawbar|mega|silo|container|tipper'])

            ->addColumn('axle_count', 'integer', ['null' => true, 'default' => 3])
            ->addColumn('gross_weight_kg', 'integer', ['null' => true, 'comment' => 'DMC naczepy'])
            ->addColumn('payload_kg', 'integer', ['null' => true, 'comment' => 'Ładowność (DMC - waga własna)'])
            ->addColumn('axle_load_kg', 'integer', ['null' => true])

            ->addColumn('length_cm', 'integer', ['null' => true])
            ->addColumn('width_cm', 'integer', ['null' => true])
            ->addColumn('height_cm', 'integer', ['null' => true])
            ->addColumn('volume_m3', 'decimal', ['precision' => 5, 'scale' => 1, 'null' => true,
                'comment' => 'Objętość ładunkowa (typowo 86-100 m³ dla mega)'])

            // Koszty
            ->addColumn('amortization_per_day_pln', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true,
                'comment' => 'Amortyzacja dzienna (np. zakup/leasing / liczba dni roboczych)'])
            ->addColumn('insurance_per_year_pln', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])

            ->addColumn('adr_certified', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Czy naczepa ma certyfikat ADR'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('is_default', 'boolean', ['null' => false, 'default' => false])

            ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'is_active'])
            ->addIndex(['plate'])
            ->create();
    }
}

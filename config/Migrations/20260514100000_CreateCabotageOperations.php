<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Historia operacji cabotage per pojazd. Cabotage = transport pomiędzy dwoma
 * punktami w obrębie OBCEGO kraju (innego niż kraj rejestracji firmy).
 *
 * UE limity (Rozporządzenie 1072/2009):
 *  - Max 3 operacje cabotage w 7 dniach w jednym kraju
 *  - Każda kolejna operacja musi być POPRZEDZONA opuszczeniem kraju
 *  - 4-dniowy cooling-off przed kolejną serią (lipca 2022)
 *
 * Auto-zapis przy każdej kalkulacji trasy (jeśli detekcja wykryje cabotage).
 */
class CreateCabotageOperations extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('cabotage_operations')) {
            return;
        }
        $this->table('cabotage_operations', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'Pojazd realizujący operację (nullable jeśli ręczny wpis)'])
            ->addColumn('route_search_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do route_searches.id jeśli auto-detected z planera'])

            // Kraj cabotage (ISO 3166-1 alpha-3, np. DEU, FRA)
            ->addColumn('country', 'char', ['limit' => 3, 'null' => false])

            // Data operacji
            ->addColumn('operation_date', 'date', ['null' => false])

            // Adresy origin i destination (informacyjnie)
            ->addColumn('origin', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('destination', 'string', ['limit' => 255, 'null' => true])

            // Notatki (np. CMR nr, klient)
            ->addColumn('notes', 'text', ['null' => true])

            // Czy wprowadzono ręcznie czy auto-wykryto
            ->addColumn('source', 'string', ['limit' => 20, 'null' => false, 'default' => 'manual',
                'comment' => 'manual | auto_planner | imported'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'country', 'operation_date'])
            ->addIndex(['vehicle_id', 'operation_date'])
            ->addIndex(['route_search_id'])
            ->create();
    }
}

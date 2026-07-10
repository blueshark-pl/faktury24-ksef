<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Kandydaci na ladunek powrotny (return load) do juz zaplanowanej trasy.
 * Zapobiega pustym powrotom pojazdu z konca trasy.
 *
 * Zrodla kandydatow:
 *  - internal: wlasne speed_orders (otwarte zlecenia w promieniu N km
 *    od punktu koncowego + termin ±X dni)
 *  - market: gieldy transportowe (Trans/Timocom) — future via API
 *  - manual: operator recznie dopisany kandydat
 */
class CreateReturnLoadCandidates extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('return_load_candidates', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => false,
            'comment' => 'Plan trasy dla ktorego szukamy powrotu',
        ]);

        $table->addColumn('candidate_type', 'string', ['limit' => 20, 'null' => false,
            'comment' => 'internal|market|manual',
        ]);

        // Powiazanie z wlasnym zleceniem (dla internal)
        $table->addColumn('speed_order_id', 'integer', ['null' => true,
            'comment' => 'FK do speed_orders gdy kandydat = wlasne zlecenie w bazie',
        ]);
        // Referencja zewnetrzna (dla market)
        $table->addColumn('external_ref', 'string', ['limit' => 100, 'null' => true,
            'comment' => 'ID/nr referencyjny z gieldy (Trans/Timocom)',
        ]);
        $table->addColumn('external_source', 'string', ['limit' => 20, 'null' => true,
            'comment' => 'trans|timocom|inne',
        ]);

        // Geografia
        $table->addColumn('from_city', 'string', ['limit' => 200, 'null' => true]);
        $table->addColumn('from_country', 'char', ['limit' => 2, 'null' => true]);
        $table->addColumn('from_lat', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);
        $table->addColumn('from_lng', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);
        $table->addColumn('to_city', 'string', ['limit' => 200, 'null' => true]);
        $table->addColumn('to_country', 'char', ['limit' => 2, 'null' => true]);
        $table->addColumn('to_lat', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);
        $table->addColumn('to_lng', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);

        // Termin i cena
        $table->addColumn('pickup_from', 'datetime', ['null' => true,
            'comment' => 'Poczatek okna zaladunku',
        ]);
        $table->addColumn('pickup_to', 'datetime', ['null' => true]);
        $table->addColumn('price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true]);
        $table->addColumn('currency', 'string', ['limit' => 3, 'null' => true]);

        // Matching score
        $table->addColumn('distance_from_route_km', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true,
            'comment' => 'Deadhead km od konca trasy do punktu pickup',
        ]);
        $table->addColumn('time_gap_hours', 'decimal', ['precision' => 6, 'scale' => 1, 'null' => true,
            'comment' => 'Roznica godzin miedzy konczem trasy a startem powrotu',
        ]);
        $table->addColumn('match_score', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true,
            'comment' => '0-100, wyzsze = lepsze dopasowanie',
        ]);

        // Status i notatki
        $table->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'suggested',
            'comment' => 'suggested|dismissed|combined',
        ]);
        $table->addColumn('dismissed_reason', 'string', ['limit' => 200, 'null' => true]);
        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_PLAN']);
        $table->addIndex(['route_plan_id', 'match_score'], ['name' => 'BY_PLAN_SCORE']);
        $table->addIndex(['route_plan_id', 'status'], ['name' => 'BY_PLAN_STATUS']);

        $table->create();
    }
}

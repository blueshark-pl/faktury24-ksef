<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Historia obliczonych tras w planerze /trasy — per user.
 * Dedup po `signature` (sha1 wszystkich lat/lng waypointów + vehicle_id).
 */
class CreateRouteSearches extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('route_searches')) {
            return;
        }
        $this->table('route_searches', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => true])

            // Waypoints jako JSON: [{address,label,lat,lng},...]
            ->addColumn('waypoints_json', 'text', ['null' => false])

            // Pojazd użyty (nullable jeśli osobowy)
            ->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true])

            // Podsumowanie (z wyników)
            ->addColumn('distance_km',    'decimal', ['precision' => 10, 'scale' => 1, 'null' => true])
            ->addColumn('duration_min',   'integer', ['null' => true])
            ->addColumn('tolls_total',    'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('tolls_currency', 'string',  ['limit' => 8, 'null' => true])

            // Sygnatura dla dedup (sha1)
            ->addColumn('signature', 'string', ['limit' => 64, 'null' => false])

            ->addTimestamps('created', 'last_used')
            ->addIndex(['user_id'])
            ->addIndex(['signature'])
            ->addIndex(['user_id', 'signature'], ['unique' => true])
            ->create();
    }
}

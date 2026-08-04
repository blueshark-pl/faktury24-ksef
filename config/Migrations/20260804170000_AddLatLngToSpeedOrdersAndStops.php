<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dodaje wspolrzedne geograficzne (lat/lng) do lokalizacji zlecen:
 *  - speed_orders.load_lat/load_lng (miejsce zaladunku)
 *  - speed_orders.unload_lat/unload_lng (miejsce rozladunku)
 *  - speed_order_stops.lat/lng (pojedyncze stopy multi-stop)
 *
 * Wykorzystywane do:
 *  - Rysowania pelnej trasy na mapie HERE (bez ponownego geocodingu)
 *  - Kalkulacji HERE Routing bez opoznien
 *  - Powiazan z trip_events (GPS check)
 *  - Analytics geograficznych (heatmap tras)
 */
class AddLatLngToSpeedOrdersAndStops extends BaseMigration
{
    public function up(): void
    {
        // speed_orders: pickup + delivery lat/lng
        $t = $this->table('speed_orders');
        $t->addColumn('load_lat', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'load_city',
                'comment' => 'Szerokosc geograficzna miejsca zaladunku',
            ])
            ->addColumn('load_lng', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'load_lat',
                'comment' => 'Dlugosc geograficzna miejsca zaladunku',
            ])
            ->addColumn('unload_lat', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'unload_city',
                'comment' => 'Szerokosc geograficzna miejsca rozladunku',
            ])
            ->addColumn('unload_lng', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'unload_lat',
                'comment' => 'Dlugosc geograficzna miejsca rozladunku',
            ])
            ->update();

        // speed_order_stops: lat/lng dla stopow posrednich
        $s = $this->table('speed_order_stops');
        $s->addColumn('lat', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'city',
                'comment' => 'Szerokosc geograficzna stopu',
            ])
            ->addColumn('lng', 'decimal', [
                'precision' => 10, 'scale' => 7, 'null' => true,
                'after' => 'lat',
                'comment' => 'Dlugosc geograficzna stopu',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_order_stops')
            ->removeColumn('lng')
            ->removeColumn('lat')
            ->update();

        $this->table('speed_orders')
            ->removeColumn('unload_lng')
            ->removeColumn('unload_lat')
            ->removeColumn('load_lng')
            ->removeColumn('load_lat')
            ->update();
    }
}

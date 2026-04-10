<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Rozszerzenie tabeli speed_orders:
 *  - wyciągnięcie pól z raw_json do kolumn (filtrowanie, sortowanie, indeksy)
 *  - status operacyjny Nordlogis (niezależny od GLO_STATUS)
 *  - checkboxy POL / POD / FK / FS + flaga KOMPLETNE
 */
class ExpandSpeedOrders extends AbstractMigration
{
    public function change(): void
    {
        $t = $this->table('speed_orders');

        // ── Pola wyciągnięte z raw_json ──────────────────────────────────────

        // Załadunek
        $t->addColumn('load_country', 'string', ['limit' => 10,  'null' => true, 'default' => null, 'comment' => 'GLO_MIE_KRAJ — kraj załadunku', 'after' => 'place_from_country']);
        $t->addColumn('load_postal_code', 'string', ['limit' => 20,  'null' => true, 'default' => null, 'comment' => 'GLO_MIE_KOD — kod pocztowy załadunku', 'after' => 'load_country']);
        $t->addColumn('load_city', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_MIE_POCZTA — miejscowość załadunku', 'after' => 'load_postal_code']);

        // Rozładunek
        $t->addColumn('unload_name', 'string', ['limit' => 200, 'null' => true, 'default' => null, 'comment' => 'GLO_MIE_NAZWA1+NAZWA2 — nazwa miejsca rozładunku', 'after' => 'place_to_country']);
        $t->addColumn('unload_city', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_MIE_MIEJSC — miejscowość rozładunku', 'after' => 'unload_name']);
        $t->addColumn('unload_country', 'string', ['limit' => 10,  'null' => true, 'default' => null, 'comment' => 'kraj rozładunku (z GLO_MIE_KRAJ2 jeśli dostępne)', 'after' => 'unload_city']);

        // Transport
        $t->addColumn('driver', 'string', ['limit' => 200, 'null' => true, 'default' => null, 'comment' => 'GLO_JEDNOSTKA — kierowca', 'after' => 'cargo_type']);
        $t->addColumn('carrier', 'string', ['limit' => 200, 'null' => true, 'default' => null, 'comment' => 'GLO_NAZ8 — przewoźnik', 'after' => 'driver']);
        $t->addColumn('transport_type', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_MIE_RODZAJ — rodzaj transportu', 'after' => 'carrier']);
        $t->addColumn('vehicle_reg', 'string', ['limit' => 50,  'null' => true, 'default' => null, 'comment' => 'GLO_KONTO — rejestracja/środek transportu', 'after' => 'transport_type']);

        // Finansowe / dokumentowe
        $t->addColumn('payment_terms', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_PLATNOSC — warunki płatności np. "Przelew 30 dni"', 'after' => 'exchange_table']);
        $t->addColumn('our_ref', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_POD — nasz numer referencyjny', 'after' => 'payment_terms']);

        // ── Status operacyjny Nordlogis ───────────────────────────────────────
        // 1=Przyjęte 2=Zaplanowane 3=Załadowane 4=Zrealizowane 5=Zafakturowane
        $t->addColumn('nordlogis_status', 'integer', ['limit' => 1, 'null' => false, 'default' => 1, 'comment' => '1=Przyjęte 2=Zaplanowane 3=Załadowane 4=Zrealizowane 5=Zafakturowane', 'after' => 'status']);

        // ── Checkboxy techniczne (data = dowód wykonania) ────────────────────
        $t->addColumn('pol_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'POL — data załadunku (dokument)', 'after' => 'invoiced_at']);
        $t->addColumn('pod_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'POD — data dostawy (dokument)', 'after' => 'pol_at']);
        $t->addColumn('fk_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'FK — faktura kosztowa podpięta', 'after' => 'pod_at']);
        $t->addColumn('fs_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'FS — faktura sprzedażowa wysłana', 'after' => 'fk_at']);
        $t->addColumn('is_complete', 'boolean', ['null' => false, 'default' => false, 'comment' => 'POL+POD+FK+FS = zlecenie kompletne', 'after' => 'fs_at']);

        // ── Indeksy ──────────────────────────────────────────────────────────
        $t->addIndex(['nordlogis_status']);
        $t->addIndex(['pod_at']);
        $t->addIndex(['is_complete']);

        $t->update();
    }
}

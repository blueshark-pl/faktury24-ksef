<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * speed_orders.contract — identyfikator kontraktu (OWN 1, OWN PL 2, OWN X1 itd.).
 *
 * W Speed ERP pole zwane było "spedytor_2" (GLO_NAZ7 w raw_json). Używane
 * jako etykieta kontraktu — który zestaw OWN obsługuje dane zlecenie.
 *
 * Backfill: dla istniejących zleceń odczyt z raw_json.GLO_NAZ7.
 */
class AddContractToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->addColumn('contract', 'string', [
                'limit'   => 50,
                'null'    => true,
                'default' => null,
                'after'   => 'carrier',
                'comment' => 'Kontrakt operacyjny (OWN 1, OWN PL 2, OWN X1 itd.) — z GLO_NAZ7 Speed',
            ])
            ->addIndex(['contract'], ['name' => 'BY_CONTRACT'])
            ->update();

        // Backfill z raw_json — odczyt JSON_EXTRACT, trim, ustawienie wartości.
        // Działa pod MySQL 5.7+ (mamy 8.x).
        $this->execute(
            "UPDATE speed_orders
             SET contract = TRIM(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '\$.GLO_NAZ7')))
             WHERE raw_json IS NOT NULL
               AND JSON_EXTRACT(raw_json, '\$.GLO_NAZ7') IS NOT NULL"
        );
        // Wyzeruj puste stringi które trim mógł zostawić
        $this->execute("UPDATE speed_orders SET contract = NULL WHERE contract = '' OR contract = '='");
    }

    public function down(): void
    {
        $this->table('speed_orders')->removeColumn('contract')->update();
    }
}

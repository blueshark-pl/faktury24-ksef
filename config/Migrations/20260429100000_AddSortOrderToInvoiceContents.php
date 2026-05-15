<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje pole `sort_order` do `invoice_contents`, by zapamiętać kolejność
 * pozycji zgodnie z tym, jak użytkownik je wprowadził. Bez tego kolumny
 * UUID + brak ORDER BY powodują, że MySQL zwraca wiersze w losowej kolejności.
 *
 * Backfill: dla istniejących faktur ustawiamy sort_order narastająco
 * po (created ASC, id ASC) w obrębie każdej `invoice_id`.
 */
class AddSortOrderToInvoiceContents extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('invoice_contents');
        if (!$table->hasColumn('sort_order')) {
            $table->addColumn('sort_order', 'integer', [
                'null'    => true,
                'default' => 0,
                'comment' => 'Kolejność pozycji wg wprowadzania (0..N w obrębie faktury)',
                'after'   => 'invoice_id',
            ])->update();
        }

        // Backfill: dla każdej istniejącej faktury nadaj sort_order po (created, id).
        // BINARY w porównaniu omija konflikt collation między zmienną sesyjną
        // (utf8mb4_general_ci) a kolumną (utf8mb4_unicode_ci).
        $this->execute("SET @rownum := 0, @prev := ''");
        $this->execute(
            "UPDATE invoice_contents ic
             JOIN (
                 SELECT id,
                        @rownum := IF(@prev = BINARY invoice_id, @rownum + 1, 0) AS rn,
                        @prev := invoice_id
                 FROM invoice_contents
                 ORDER BY invoice_id, created, id
             ) ranked ON BINARY ranked.id = BINARY ic.id
             SET ic.sort_order = ranked.rn"
        );

        // Indeks pomocniczy do ORDER BY
        $table = $this->table('invoice_contents');
        if (!$table->hasIndex(['invoice_id', 'sort_order'])) {
            $table->addIndex(['invoice_id', 'sort_order'])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('invoice_contents');
        if ($table->hasIndex(['invoice_id', 'sort_order'])) {
            $table->removeIndexByName('invoice_contents_invoice_id_sort_order')->update();
        }
        if ($table->hasColumn('sort_order')) {
            $table->removeColumn('sort_order')->update();
        }
    }
}

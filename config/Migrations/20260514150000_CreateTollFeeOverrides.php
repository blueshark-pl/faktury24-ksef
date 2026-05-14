<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Korekty opłat drogowych — learning loop dla różnic między cenami HERE
 * a rzeczywistymi cennikami operatorów.
 *
 * Po wykryciu fare z HERE system sprawdza czy istnieje override dla
 * tej kombinacji (country + system + name) i aplikuje:
 *   - 'ignore'    → fare nie wlicza się do sumy, wyświetla się wyszarzona
 *   - 'corrected' → fare używa corrected_price zamiast HERE'owego
 *
 * Hash fare_signature = sha1(country + '|' + system_norm + '|' + name_norm)
 * gdzie norm = lowercase + trim + collapse whitespace.
 * Dzięki temu te same opłaty łączą się między kalkulacjami.
 */
class CreateTollFeeOverrides extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('toll_fee_overrides')) return;
        $this->table('toll_fee_overrides', ['id' => false, 'primary_key' => ['id'], 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('created_by', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do users.id'])

            // Identyfikator fare — sha1(country + '|' + system_norm + '|' + name_norm)
            ->addColumn('fare_signature', 'char', ['limit' => 40, 'null' => false])

            // Dla podglądu/diagnostyki — co user widział
            ->addColumn('country', 'char', ['limit' => 3, 'null' => false])
            ->addColumn('system', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('fare_name', 'string', ['limit' => 255, 'null' => true])

            // Akcja
            ->addColumn('action', 'string', ['limit' => 20, 'null' => false,
                'comment' => 'ignore | corrected | flagged'])
            // Cena po korekcie (tylko dla action=corrected)
            ->addColumn('corrected_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('corrected_currency', 'char', ['limit' => 3, 'null' => true])
            // Cena z HERE (dla referencji)
            ->addColumn('original_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('original_currency', 'char', ['limit' => 3, 'null' => true])

            // Uzasadnienie usera
            ->addColumn('reason', 'text', ['null' => true,
                'comment' => 'Dlaczego user uznał że HERE źle liczy'])

            // Trasa z której pochodzi override (do śledzenia)
            ->addColumn('route_search_id', 'char', ['limit' => 36, 'null' => true])

            // Czy override jest nadal aktywny — user może go wycofać
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            // Liczba użyć — ile razy system zastosował ten override
            ->addColumn('applied_count', 'integer', ['null' => false, 'default' => 0])
            // Ostatnie zastosowanie
            ->addColumn('last_applied_at', 'datetime', ['null' => true])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'fare_signature', 'is_active'],
                ['name' => 'idx_company_signature'])
            ->addIndex(['route_search_id'])
            ->addIndex(['action'])
            ->create();
    }
}

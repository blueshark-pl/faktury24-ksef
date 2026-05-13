<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje domyślną serię numeracyjną dla typu 'credit_note' (nota uznaniowa)
 * dla wszystkich firm które jeszcze jej nie mają.
 *
 * Wzorzec: NU/[numer]/[miesiąc]/[rok] (zgodny z konwencją innych serii:
 * FV/[numer]/[miesiąc]/[rok], FM/[numer]/[miesiąc]/[rok] itd.)
 *
 * - period: miesięczny (jak proforma) — UUID ba09a024-35d6-4049-af1d-811ffcc84f5c
 * - is_default: 1 — żeby InvoiceDefaultSeriesResolver złapał automatycznie
 *   przy pierwszej notce dla danego type
 * - starting_number: 1
 *
 * Idempotent: pomijamy firmy które już mają serię type='credit_note'.
 */
class SeedCreditNoteSeries extends AbstractMigration
{
    public function up(): void
    {
        $monthlyPeriodId = 'ba09a024-35d6-4049-af1d-811ffcc84f5c';

        // Sprawdź czy period istnieje — jeśli nie, fallback na pierwszy dostępny
        $periodExists = (int)$this->fetchRow(
            "SELECT COUNT(*) c FROM invoice_series_periods WHERE id = '" . $monthlyPeriodId . "'"
        )['c'];
        if ($periodExists === 0) {
            $row = $this->fetchRow("SELECT id FROM invoice_series_periods LIMIT 1");
            $monthlyPeriodId = $row ? (string)$row['id'] : null;
            if ($monthlyPeriodId === null) {
                // Brak okresów — pomijamy, niczego nie psujemy
                return;
            }
        }

        // Wstaw 1 serię per company gdy jeszcze nie ma 'credit_note'.
        // Używamy SQL function UUID() do generacji id. Daty bieżące.
        $this->execute(
            "INSERT INTO invoice_series
                (id, company_id, invoice_series_period_id, is_default, name, type, series_template, starting_number, created, modified)
             SELECT
                LOWER(REPLACE(REPLACE(REPLACE(REPLACE(UUID(),'-','-'),'',''),'',''),'','')),
                c.id,
                '" . $monthlyPeriodId . "',
                1,
                'Nota uznaniowa',
                'credit_note',
                'NU/[numer]/[miesiąc]/[rok]',
                1,
                NOW(),
                NOW()
             FROM companies c
             WHERE NOT EXISTS (
                SELECT 1 FROM invoice_series s
                WHERE s.company_id = c.id AND s.type = 'credit_note'
             )"
        );
    }

    public function down(): void
    {
        // Tylko serie utworzone przez tę migrację (wzorzec NU/... i type=credit_note)
        $this->execute(
            "DELETE FROM invoice_series
             WHERE type = 'credit_note'
               AND series_template = 'NU/[numer]/[miesiąc]/[rok]'
               AND name = 'Nota uznaniowa'"
        );
    }
}

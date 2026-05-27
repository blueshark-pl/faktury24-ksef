<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Tabela ucząca się powiązań IBAN → kontrahent z historii rozliczeń.
 * Po każdym confirmMatch/addAllocation insert/update — wagę confirmed_count
 * używamy w scoringu kandydatów do automatycznego dopasowania nowych przelewów.
 *
 * To NIE jest cache contractor_bank_accounts (te są wpisywane ręcznie na
 * fakturze) — to deterministyczny "learning loop" z faktycznie potwierdzonych
 * matchów. Może istnieć IBAN bez wpisu w contractor_bank_accounts ale
 * historycznie używany 15× → wciąż liczy się jako silny sygnał.
 */
class CreateContractorIbanHistory extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('contractor_iban_history', ['id' => false, 'primary_key' => ['id']]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        // Identyfikator kontrahenta — używamy NIP (stabilniejszy niż id)
        $table->addColumn('contractor_nip', 'string', ['limit' => 32, 'null' => false]);
        // Opcjonalnie nazwa do podglądu (pomocnicze, nie do JOIN)
        $table->addColumn('contractor_name_snapshot', 'string', ['limit' => 255, 'null' => true]);

        // IBAN — normalizowany (bez spacji/myślników, uppercase)
        $table->addColumn('iban', 'string', ['limit' => 50, 'null' => false]);

        $table->addColumn('confirmed_count', 'integer', ['default' => 1, 'null' => false]);
        $table->addColumn('total_amount_pln', 'decimal', [
            'precision' => 18, 'scale' => 2, 'null' => true, 'default' => 0,
            'comment' => 'Suma kwot wszystkich potwierdzonych wpłat — do typowości',
        ]);

        $table->addColumn('first_used', 'datetime', ['null' => false]);
        $table->addColumn('last_used', 'datetime', ['null' => false]);
        $table->addTimestamps('created', 'modified');

        // Unique per (company, nip, iban) — żeby ON DUPLICATE KEY działało
        $table->addIndex(['company_id', 'contractor_nip', 'iban'], [
            'unique' => true, 'name' => 'BY_COMPANY_NIP_IBAN',
        ]);
        $table->addIndex(['company_id', 'iban'], ['name' => 'BY_COMPANY_IBAN']);
        $table->addIndex(['company_id', 'contractor_nip'], ['name' => 'BY_COMPANY_NIP']);

        $table->create();
    }
}

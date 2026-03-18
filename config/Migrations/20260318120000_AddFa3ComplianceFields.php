<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FA(3) KSeF compliance — add all missing DB columns identified from the
 * "Broszura informacyjna dotycząca struktury logicznej FA(3)" (wrzesień 2025).
 *
 * HIGH priority items:
 *  - invoice_contents: gtu_code (BUG — column never created), uu_id, vat_amount,
 *    line_date (P_6A), pkwiu, gross_unit_price (P_9B)
 *  - invoices: period_from, period_to (OkresFa), wz_number, correction_reason,
 *    place_of_issue (P_1M), footer_text (StopkaFaktury), payment_link
 *  - invoice_recipients: rola, rola_opis, vat_prefix, vat_eu, etc. + drop unique → non-unique
 *  - invoice_company_details: email, phone, krs, regon, bdo
 */
class AddFa3ComplianceFields extends AbstractMigration
{
    public function change(): void
    {
        // ==================== INVOICE_CONTENTS ====================
        $ic = $this->table('invoice_contents');

        $icColumns = [
            'gtu_code' => ['type' => 'string', 'opts' => [
                'limit' => 16, 'null' => true, 'default' => null,
                'comment' => 'Kod GTU (GTU_01..GTU_13)',
            ]],
            'uu_id' => ['type' => 'char', 'opts' => [
                'limit' => 36, 'null' => true, 'default' => null,
                'comment' => 'UUID wiersza — identyfikator dla korekt FA(3) <UU_ID>',
            ]],
            'vat_amount' => ['type' => 'decimal', 'opts' => [
                'precision' => 18, 'scale' => 2, 'null' => true, 'default' => null,
                'comment' => 'Kwota VAT wiersza — FA(3) P_11Vat',
            ]],
            'line_date' => ['type' => 'date', 'opts' => [
                'null' => true, 'default' => null,
                'comment' => 'Data dostawy/usługi per-wiersz — FA(3) P_6A',
            ]],
            'pkwiu' => ['type' => 'string', 'opts' => [
                'limit' => 16, 'null' => true, 'default' => null,
                'comment' => 'PKWiU — FA(3) <PKWiU>',
            ]],
            'gross_unit_price' => ['type' => 'decimal', 'opts' => [
                'precision' => 18, 'scale' => 2, 'null' => true, 'default' => null,
                'comment' => 'Cena jedn. brutto — FA(3) P_9B',
            ]],
        ];

        foreach ($icColumns as $col => $def) {
            if (!$ic->hasColumn($col)) {
                $ic->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $ic->update();

        // ==================== INVOICES ====================
        $inv = $this->table('invoices');

        $invColumns = [
            'period_from' => ['type' => 'date', 'opts' => [
                'null' => true, 'default' => null,
                'comment' => 'Okres faktury — data od (P_6_Od / OkresFa)',
            ]],
            'period_to' => ['type' => 'date', 'opts' => [
                'null' => true, 'default' => null,
                'comment' => 'Okres faktury — data do (P_6_Do / OkresFa)',
            ]],
            'wz_number' => ['type' => 'string', 'opts' => [
                'limit' => 128, 'null' => true, 'default' => null,
                'comment' => 'Numer dokumentu WZ — FA(3) <WZ>',
            ]],
            'correction_reason' => ['type' => 'text', 'opts' => [
                'null' => true, 'default' => null,
                'comment' => 'Przyczyna korekty — FA(3) <PrzyczynaKorekty>',
            ]],
            'place_of_issue' => ['type' => 'string', 'opts' => [
                'limit' => 128, 'null' => true, 'default' => null,
                'comment' => 'Miejsce wystawienia — FA(3) P_1M',
            ]],
            'footer_text' => ['type' => 'text', 'opts' => [
                'null' => true, 'default' => null,
                'comment' => 'Tekst stopki faktury — FA(3) <StopkaFaktury>',
            ]],
            'payment_link' => ['type' => 'string', 'opts' => [
                'limit' => 512, 'null' => true, 'default' => null,
                'comment' => 'Link do płatności — FA(3) <LinkDoPlatnosci>',
            ]],
        ];

        foreach ($invColumns as $col => $def) {
            if (!$inv->hasColumn($col)) {
                $inv->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $inv->update();

        // ==================== INVOICE_RECIPIENTS ====================
        // Dodaj brakujące kolumny (rola, szczegóły podatkowe itp.)
        $ir = $this->table('invoice_recipients');

        $irColumns = [
            'rola' => ['type' => 'integer', 'opts' => [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'null' => true, 'default' => null,
                'comment' => 'Rola podmiotu trzeciego wg FA(3): 1-Faktor, 2-Odbiorca, 3-Upoważniony, 4-DodatkowyNabywca, 5-Wystawca, 6-Inny',
            ]],
            'rola_opis' => ['type' => 'string', 'opts' => [
                'limit' => 128, 'null' => true, 'default' => null,
                'comment' => 'Opis roli (RolaInna/OpisRoli) — FA(3)',
            ]],
            'vat_prefix' => ['type' => 'string', 'opts' => [
                'limit' => 8, 'null' => true, 'default' => null,
                'comment' => 'Prefix UE VAT podmiotu trzeciego',
            ]],
            'vat_eu' => ['type' => 'string', 'opts' => [
                'limit' => 32, 'null' => true, 'default' => null,
                'comment' => 'Nr VAT-UE podmiotu trzeciego',
            ]],
            'tax_id_other' => ['type' => 'string', 'opts' => [
                'limit' => 64, 'null' => true, 'default' => null,
                'comment' => 'Inny nr identyfikacji podatkowej',
            ]],
            'tax_id_other_country' => ['type' => 'string', 'opts' => [
                'limit' => 8, 'null' => true, 'default' => null,
                'comment' => 'Kraj innego nr podatkowego',
            ]],
            'gln' => ['type' => 'string', 'opts' => [
                'limit' => 16, 'null' => true, 'default' => null,
                'comment' => 'GLN — Global Location Number',
            ]],
        ];

        foreach ($irColumns as $col => $def) {
            if (!$ir->hasColumn($col)) {
                $ir->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $ir->update();

        // Zamień unikalny index na invoice_id na zwykły (fa może mieć >1 Podmiot3)
        if ($ir->hasIndex('invoice_id')) {
            $ir->removeIndex(['invoice_id']);
            $ir->addIndex(['invoice_id']);
            $ir->update();
        }

        // ==================== INVOICE_COMPANY_DETAILS ====================
        $icd = $this->table('invoice_company_details');

        $icdColumns = [
            'email' => ['type' => 'string', 'opts' => [
                'limit' => 128, 'null' => true, 'default' => null,
                'comment' => 'E-mail sprzedawcy — FA(3) DaneKontaktowe/Email',
            ]],
            'phone' => ['type' => 'string', 'opts' => [
                'limit' => 40, 'null' => true, 'default' => null,
                'comment' => 'Telefon sprzedawcy — FA(3) DaneKontaktowe/Telefon',
            ]],
            'krs' => ['type' => 'string', 'opts' => [
                'limit' => 32, 'null' => true, 'default' => null,
                'comment' => 'KRS — numer rejestru sądowego (snapshot z company_registers)',
            ]],
            'regon' => ['type' => 'string', 'opts' => [
                'limit' => 16, 'null' => true, 'default' => null,
                'comment' => 'REGON (snapshot)',
            ]],
            'bdo' => ['type' => 'string', 'opts' => [
                'limit' => 16, 'null' => true, 'default' => null,
                'comment' => 'BDO — nr bazy danych odpadowych (snapshot)',
            ]],
            'bank_name' => ['type' => 'string', 'opts' => [
                'limit' => 128, 'null' => true, 'default' => null,
                'comment' => 'Nazwa banku — FA(3) NazwaBanku',
            ]],
            'bank_desc' => ['type' => 'string', 'opts' => [
                'limit' => 255, 'null' => true, 'default' => null,
                'comment' => 'Opis rachunku — FA(3) OpisRachunku',
            ]],
            'country_code' => ['type' => 'string', 'opts' => [
                'limit' => 8, 'null' => true, 'default' => 'PL',
                'comment' => 'Kod ISO kraju sprzedawcy (PL, DE…)',
            ]],
        ];

        foreach ($icdColumns as $col => $def) {
            if (!$icd->hasColumn($col)) {
                $icd->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $icd->update();

        // ==================== INVOICE_CONTRACTORS ====================
        // Dodaj country_code (ISO) jeśli brak — builder czyta $buyer->country_code
        $icc = $this->table('invoice_contractors');
        if (!$icc->hasColumn('country_code')) {
            $icc->addColumn('country_code', 'string', [
                'limit' => 8, 'null' => true, 'default' => 'PL',
                'comment' => 'Kod ISO kraju nabywcy',
            ]);
            $icc->update();
        }
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje kolumnę `corrected_invoice_number` do `invoices`.
 *
 * Pole odpowiada elementowi <NrFaKorygowany> w XSD FA(3) (sekcja korekty,
 * po DaneFaKorygowanej i OkresFaKorygowanej, przed Podmiot1K).
 *
 * Używane TYLKO gdy przyczyną korekty jest błędny numer faktury pierwotnej.
 * Wtedy w XML idzie:
 *   <NrFaKorygowanej>{stary, błędny}</NrFaKorygowanej>
 *   <NrFaKorygowany>{nowy, poprawny}</NrFaKorygowany>
 *
 * Per broszura MF FA(3) linie 2728-2737 — pole opcjonalne.
 */
class AddCorrectedInvoiceNumberToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoices')
            ->addColumn('corrected_invoice_number', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'after'   => 'fullnumber',
                'comment' => 'NrFaKorygowany — poprawny numer faktury, gdy przyczyną korekty jest błędny numer faktury pierwotnej (XSD FA3)',
            ])
            ->update();
    }
}

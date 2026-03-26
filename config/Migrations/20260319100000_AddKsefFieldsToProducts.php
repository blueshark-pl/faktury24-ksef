<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje pola KSeF/FA(3) do tabeli products:
 *  - gtin              — kod GTIN/EAN produktu
 *  - cn_code           — kod CN (Nomenklatura Scalona)
 *  - pkob              — kod PKOB (budynki/budowle)
 *  - excise_amount     — kwota akcyzy
 *  - procedure_marking — oznaczenie procedury (np. MR_T, MR_UZ, EE, TP…)
 *  - is_attachment15   — towar/usługa z załącznika 15 (odwrotne obciążenie)
 */
class AddKsefFieldsToProducts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('products')
            ->addColumn('gtin', 'string', [
                'limit'   => 30,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod GTIN/EAN produktu (FA(3) element GTU/GTIN)',
                'after'   => 'barcode',
            ])
            ->addColumn('cn_code', 'string', [
                'limit'   => 16,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod CN – Nomenklatura Scalona (FA(3))',
                'after'   => 'gtin',
            ])
            ->addColumn('pkob', 'string', [
                'limit'   => 16,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod PKOB – Polska Klasyfikacja Obiektów Budowlanych',
                'after'   => 'cn_code',
            ])
            ->addColumn('excise_amount', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'null'      => true,
                'default'   => null,
                'comment'   => 'Kwota akcyzy zawarta w cenie',
                'after'     => 'pkob',
            ])
            ->addColumn('procedure_marking', 'string', [
                'limit'   => 8,
                'null'    => true,
                'default' => null,
                'comment' => 'Oznaczenie procedury: MR_T, MR_UZ, EE, TP, TT_D, TT_WNT, I_42, I_63, B_SPV, B_SPV_DOSTAWA, B_MPV_PROWIZJA, MPP',
                'after'   => 'excise_amount',
            ])
            ->addColumn('is_attachment15', 'boolean', [
                'null'    => false,
                'default' => 0,
                'comment' => '1 = towar/usługa wymieniona w zał. 15 do ustawy o VAT (mechanizm podzielonej płatności)',
                'after'   => 'procedure_marking',
            ])
            ->update();
    }
}

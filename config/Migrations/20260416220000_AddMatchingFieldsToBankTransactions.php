<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddMatchingFieldsToBankTransactions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bank_transactions')
            // Status dopasowania: unmatched / proposed / matched / ignored
            ->addColumn('match_status', 'string', [
                'limit'   => 12,
                'default' => 'unmatched',
                'null'    => false,
                'after'   => 'is_matched',
            ])
            // Pewność dopasowania 0–100
            ->addColumn('match_confidence', 'integer', [
                'limit'   => 3,
                'default' => 0,
                'null'    => false,
                'after'   => 'match_status',
            ])
            // Opis powodu dopasowania
            ->addColumn('match_reason', 'string', [
                'limit'  => 100,
                'null'   => true,
                'after'  => 'match_confidence',
            ])
            // Numer faktury wyciągnięty z tytułu (/INV/)
            ->addColumn('parsed_inv', 'string', [
                'limit'  => 100,
                'null'   => true,
                'after'  => 'match_reason',
            ])
            // NIP kontrahenta z tytułu (/IDC/)
            ->addColumn('parsed_nip', 'string', [
                'limit'  => 15,
                'null'   => true,
                'after'  => 'parsed_inv',
            ])
            // Kwota VAT z tytułu (/VAT/)
            ->addColumn('parsed_vat', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'null'      => true,
                'after'     => 'parsed_nip',
            ])
            // Kod transakcji z pola :86: (np. D94, D95)
            ->addColumn('tx_type_code', 'string', [
                'limit'  => 10,
                'null'   => true,
                'after'  => 'parsed_vat',
            ])
            ->addIndex(['match_status'])
            ->addIndex(['parsed_nip'])
            ->save();
    }
}

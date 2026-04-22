<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateE100Transactions extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('e100_transactions', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('e100_account_id', 'uuid', ['null' => false])
            ->addColumn('un_id', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Unikalny identyfikator transakcji E100 (UnID)'])
            ->addColumn('card', 'string', ['limit' => 25, 'null' => true, 'default' => null, 'comment' => 'Pełny numer karty (19 cyfr)'])
            ->addColumn('card_shortname', 'string', ['limit' => 15, 'null' => true, 'default' => null])
            ->addColumn('auto', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'Numer rejestracyjny pojazdu'])
            ->addColumn('date', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Data transakcji'])
            ->addColumn('datetime_insert', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Data dodania w systemie E100'])
            ->addColumn('station_id', 'string', ['limit' => 30, 'null' => true, 'default' => null])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('brand', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('service_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('service_name', 'string', ['limit' => 150, 'null' => true, 'default' => null])
            ->addColumn('volume', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true, 'default' => null])
            ->addColumn('price', 'decimal', ['precision' => 12, 'scale' => 4, 'null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => true, 'default' => 'EUR'])
            ->addColumn('sum', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('discount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('discount_percentage', 'decimal', ['precision' => 8, 'scale' => 4, 'null' => true, 'default' => null])
            ->addColumn('amount_without_discount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('excise', 'decimal', ['precision' => 10, 'scale' => 4, 'null' => true, 'default' => null])
            ->addColumn('ticket', 'string', ['limit' => 30, 'null' => true, 'default' => null, 'comment' => 'Numer paragonu'])
            ->addColumn('confirmed', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('exposed', 'boolean', ['null' => false, 'default' => false, 'comment' => 'Czy faktura wystawiona przez E100'])
            ->addColumn('invoice_ref', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'invoice_id z E100 (numer faktury E100)'])
            ->addColumn('invoice_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('driver', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('card_driver', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('category', 'integer', ['null' => true, 'default' => null])
            ->addColumn('row_version', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['e100_account_id'])
            ->addIndex(['un_id', 'company_id'], ['unique' => true, 'name' => 'uq_e100_tx_un_id'])
            ->addIndex(['date'])
            ->addIndex(['card'])
            ->addIndex(['auto'])
            ->addIndex(['station_id'])
            ->addIndex(['invoice_ref'])
            ->create();
    }
}

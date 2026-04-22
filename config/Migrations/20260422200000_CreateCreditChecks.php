<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCreditChecks extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('credit_checks');
        $table
            // identyfikator z API syntesys (UNIQUE — nie duplikujemy)
            ->addColumn('external_id', 'integer', [
                'null'    => false,
                'signed'  => false,
                'comment' => 'ID z API syntesys (credit-check-advices.id)',
            ])
            // WITH_OPINION / PROCESSING / NO_OPINION / BUSINESS_ERROR
            ->addColumn('list_status', 'string', [
                'limit'   => 30,
                'null'    => false,
                'comment' => 'Status listy: WITH_OPINION | PROCESSING | NO_OPINION | BUSINESS_ERROR',
            ])
            ->addColumn('identifier', 'string', [
                'limit'   => 50,
                'null'    => true,
                'default' => null,
                'comment' => 'Identyfikator (NIP) z request.identifier',
            ])
            ->addColumn('identifier_type_code', 'string', [
                'limit'   => 20,
                'null'    => true,
                'default' => null,
                'comment' => 'Typ identyfikatora, np. CCIT1',
            ])
            ->addColumn('country', 'string', [
                'limit'   => 10,
                'null'    => true,
                'default' => null,
            ])
            // Opinia
            ->addColumn('advice_type_code', 'string', [
                'limit'   => 20,
                'null'    => true,
                'default' => null,
                'comment' => 'CCAT1=Tak, CCAT2=Nie, CCAT3=Brak opinii',
            ])
            ->addColumn('advice_reason_code', 'string', [
                'limit'   => 20,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod przyczyny opinii CCCR*',
            ])
            ->addColumn('advice_json', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'Pełny obiekt advice jako JSON',
            ])
            ->addColumn('client_json', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'Pełny obiekt client jako JSON',
            ])
            ->addColumn('status_code', 'string', [
                'limit'   => 20,
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('error_type_code', 'string', [
                'limit'   => 20,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod błędu CCAN*',
            ])
            // Data i autor z API
            ->addColumn('advice_created_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Pole created z API syntesys',
            ])
            ->addColumn('created_by', 'string', [
                'limit'   => 150,
                'null'    => true,
                'default' => null,
                'comment' => 'createdBy z API syntesys',
            ])
            ->addColumn('latest_advice_with_opinion', 'boolean', [
                'null'    => false,
                'default' => false,
            ])
            ->addColumn('automatic_renewal_excluded', 'boolean', [
                'null'    => false,
                'default' => false,
            ])
            ->addColumn('created_by_automatic_renewal', 'boolean', [
                'null'    => false,
                'default' => false,
            ])
            // Powiązanie z kontrahentem
            ->addColumn('contractor_id', 'uuid', [
                'null'    => true,
                'default' => null,
                'comment' => 'FK do contractors — dopasowanie po NIP',
            ])
            // Kiedy ostatnio zsynchronizowano ten rekord
            ->addColumn('synced_at', 'datetime', [
                'null'    => false,
                'comment' => 'Data ostatniego sync z API',
            ])
            ->addTimestamps('created', 'modified')
            ->addIndex(['external_id'], ['unique' => true])
            ->addIndex(['list_status'])
            ->addIndex(['identifier'])
            ->addIndex(['contractor_id'])
            ->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Limity kredytowe per kontrahent - do warnowania przy nowym zleceniu
 * gdy klient przekroczy limit nieoplaconych faktur.
 *
 * Uwaga: NIE jest to blokada - tylko warning. Ostateczna decyzja
 * nalezy do operatora/managera.
 */
class CreateContractorCreditLimits extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('contractor_credit_limits')) return;
        $this->table('contractor_credit_limits', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do contractors (nullable dla klientow spoza bazy - matchujemy po NIP)'])
            ->addColumn('contractor_nip', 'string', ['limit' => 30, 'null' => false,
                'comment' => 'NIP - klucz matchujacy z buyer_nip w zleceniach'])
            ->addColumn('credit_limit_pln', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'default' => 0,
                'comment' => 'Maksymalna kwota nieoplaconych faktur (PLN)'])
            ->addColumn('warning_threshold_pct', 'integer', ['limit' => 3, 'null' => false, 'default' => 80,
                'comment' => 'Prog % powyzej ktorego pokazujemy yellow warning (default 80%)'])
            ->addColumn('is_blocked', 'boolean', ['default' => false,
                'comment' => 'Jesli true - blokada rozpoczecia nowych zlecen dla tego klienta'])
            ->addColumn('block_reason', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['company_id', 'contractor_nip'], ['unique' => true, 'name' => 'UNQ_COMPANY_NIP'])
            ->addIndex(['contractor_id'], ['name' => 'BY_CONTRACTOR'])
            ->create();
    }

    public function down(): void
    {
        $this->table('contractor_credit_limits')->drop()->save();
    }
}

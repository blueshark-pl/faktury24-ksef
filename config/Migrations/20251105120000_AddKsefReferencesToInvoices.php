<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddKsefReferencesToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('invoices');
        if (!$table->hasColumn('ksef_session_reference')) {
            $table->addColumn('ksef_session_reference', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 64,
                'comment' => 'KSeF session reference (S=...) zapisywany przy wysyłce',
            ]);
        }
        if (!$table->hasColumn('ksef_invoice_reference')) {
            $table->addColumn('ksef_invoice_reference', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 64,
                'comment' => 'KSeF invoice reference (I=...) z odpowiedzi statusu',
            ]);
        }
        $table->update();

        // Opcjonalny indeks ułatwiający wyszukiwanie
        $table = $this->table('invoices');
        if (!$table->hasIndex(['ksef_session_reference'])) {
            $table->addIndex(['ksef_session_reference'], ['name' => 'idx_invoices_ksef_session_reference']);
        }
        if (!$table->hasIndex(['ksef_invoice_reference'])) {
            $table->addIndex(['ksef_invoice_reference'], ['name' => 'idx_invoices_ksef_invoice_reference']);
        }
        $table->update();
    }
}

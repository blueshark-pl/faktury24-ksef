<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Activity log + notatki dla faktur kosztowych — analog `invoice_notes`
 * (który jest dla faktur sprzedażowych). Auto-logi tworzone przez akcje
 * (setCostStatus, markPaid, addPayment, saveLines, assignOrder), plus
 * ręczne notatki użytkownika.
 *
 * note_type:
 *  - 'note'       — ręczna notatka usera
 *  - 'system'     — automatyczny log akcji
 *  - 'reminder'   — przypomnienie wysłane (np. mail)
 *  - 'phone_call' — odnotowana rozmowa
 *  - 'email'      — odnotowana korespondencja
 */
class CreateCostInvoiceNotes extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('cost_invoice_notes', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('cost_invoice_id', 'integer', ['null' => false]);
        $table->addColumn('company_id', 'uuid', ['null' => true, 'default' => null]);
        $table->addColumn('user_id', 'uuid', ['null' => true, 'default' => null,
            'comment' => 'Autor (NULL = system)']);
        $table->addColumn('note_type', 'string', ['limit' => 20, 'null' => false, 'default' => 'note',
            'comment' => 'note|system|reminder|phone_call|email']);
        $table->addColumn('body', 'text', ['null' => false]);
        $table->addColumn('payload_json', 'text', ['null' => true, 'default' => null,
            'comment' => 'Metadane akcji (action, old/new values, ids itp.)']);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['cost_invoice_id'], ['name' => 'BY_COST_INVOICE']);
        $table->addIndex(['user_id'], ['name' => 'BY_USER']);
        $table->addIndex(['note_type'], ['name' => 'BY_TYPE']);

        $table->addForeignKey('cost_invoice_id', 'cost_invoices', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);

        $table->create();
    }
}

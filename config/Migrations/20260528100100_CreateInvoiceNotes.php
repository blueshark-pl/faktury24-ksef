<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Tabela notatek/komentarzy do faktur — dla widoku Kanban (i innych).
 *
 * Każda notatka ma autora i typ:
 *  - 'note': zwykły komentarz operatora
 *  - 'system': wpis systemowy (np. "przesunięto do Spór")
 *  - 'reminder': przypomnienie wysłane do klienta (mailem/SMS)
 *  - 'phone_call': odnotowana rozmowa
 */
class CreateInvoiceNotes extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('invoice_notes', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => true, 'default' => null]);
        $table->addColumn('legacy_invoice_id', 'char', ['limit' => 36, 'null' => true, 'default' => null]);
        $table->addColumn('user_id', 'char', ['limit' => 36, 'null' => true, 'default' => null,
            'comment' => 'Autor notatki (NULL = system)']);
        $table->addColumn('note_type', 'string', ['limit' => 20, 'null' => false, 'default' => 'note',
            'comment' => 'note|system|reminder|phone_call|email']);
        $table->addColumn('body', 'text', ['null' => false]);
        $table->addColumn('payload_json', 'text', ['null' => true, 'default' => null,
            'comment' => 'Dodatkowe metadane akcji (np. nowy status, adresat maila)']);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id', 'invoice_id'], ['name' => 'BY_COMPANY_INVOICE']);
        $table->addIndex(['company_id', 'legacy_invoice_id'], ['name' => 'BY_COMPANY_LEGACY']);
        $table->addIndex(['user_id'], ['name' => 'BY_USER']);
        $table->addIndex(['note_type'], ['name' => 'BY_TYPE']);

        $table->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Załączniki CMR do zleceń Speed ERP.
 *
 * speed_order_attachment_labels — słownik etykiet (CMR Zdjęcie, CMR skan-oryginał, itp.)
 * speed_order_attachments       — pliki przypisane do zleceń z etykietą
 */
class CreateSpeedOrderAttachments extends AbstractMigration
{
    public function change(): void
    {
        // ── Słownik etykiet ──────────────────────────────────────────────────
        $this->table('speed_order_attachment_labels', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('name',     'string',  ['limit' => 100, 'null' => false, 'comment' => 'Nazwa etykiety, np. CMR Zdjęcie'])
            ->addColumn('slug',     'string',  ['limit' => 60,  'null' => false, 'comment' => 'Klucz maszynowy, np. cmr_photo'])
            ->addColumn('sort',     'integer', ['default' => 0, 'null' => false])
            ->addColumn('created',  'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        // ── Domyślne etykiety ────────────────────────────────────────────────
        $this->table('speed_order_attachment_labels')->insert([
            ['name' => 'CMR Zdjęcie',       'slug' => 'cmr_photo',    'sort' => 10, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'CMR Skan-oryginał', 'slug' => 'cmr_scan',     'sort' => 20, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'CMR Potwierdzenie', 'slug' => 'cmr_confirm',  'sort' => 30, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'Inne',              'slug' => 'other',         'sort' => 99, 'created' => date('Y-m-d H:i:s')],
        ])->save();

        // ── Tabela załączników ───────────────────────────────────────────────
        $this->table('speed_order_attachments', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('speed_order_id', 'integer', ['null' => false])
            ->addColumn('label_id',       'integer', ['null' => true, 'default' => null])
            ->addColumn('file_path',      'string',  ['limit' => 500, 'null' => false, 'comment' => 'Ścieżka relatywna od webroot, np. files/speed_orders/cmr/2026/04/xxx.pdf'])
            ->addColumn('original_name',  'string',  ['limit' => 255, 'null' => false])
            ->addColumn('mime_type',      'string',  ['limit' => 100, 'null' => true,  'default' => null])
            ->addColumn('file_size',      'integer', ['null' => true,  'default' => null, 'comment' => 'Rozmiar w bajtach'])
            ->addColumn('uploaded_by',    'string',  ['limit' => 200, 'null' => true,  'default' => null])
            ->addColumn('created',        'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['speed_order_id'])
            ->addIndex(['label_id'])
            ->addIndex(['created'])
            ->create();
    }
}

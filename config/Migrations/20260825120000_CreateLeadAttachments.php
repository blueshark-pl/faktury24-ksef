<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Zalaczniki do leadow.
 * Pliki uploadowane bezposrednio do leada (PDF/JPG/PNG/DOCX/XLSX itd).
 * Storage: webroot/files/lead_attachments/{lead_id}/{uuid}.{ext}
 */
class CreateLeadAttachments extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('lead_attachments', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('uploaded_by_user_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => false,
                'comment' => 'Nazwa pliku do pobrania (oryginalna z uploadu, sanitized)'])
            ->addColumn('path', 'string', ['limit' => 500, 'null' => false,
                'comment' => 'Relative path w webroot np. files/lead_attachments/{lead_id}/{uuid}.pdf'])
            ->addColumn('mime', 'string', ['limit' => 100, 'null' => false, 'default' => 'application/octet-stream'])
            ->addColumn('size', 'integer', ['limit' => 11, 'null' => false, 'default' => 0])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['lead_id', 'created'], ['name' => 'BY_LEAD'])
            ->addIndex(['company_id'], ['name' => 'BY_COMPANY'])
            ->create();
    }

    public function down(): void
    {
        $this->table('lead_attachments')->drop()->save();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddKsefXmlHashToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('invoices');
        if (!$table->hasColumn('ksef_xml_hash')) {
            $table
                ->addColumn('ksef_xml_hash', 'string', [
                    'limit'   => 64,
                    'null'    => true,
                    'default' => null,
                    'after'   => 'ksef_invoice_reference',
                    'comment' => 'Base64URL(SHA-256(bytes XML)) wysłanego do KSeF – używane do QR kodu',
                ])
                ->save();
        }
    }
}

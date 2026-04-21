<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class UpdateCmrAttachmentLabels extends AbstractMigration
{
    public function up(): void
    {
        // Usuń stare etykiety
        $this->execute("DELETE FROM speed_order_attachment_labels WHERE slug IN ('cmr_photo','cmr_scan','cmr_confirm','other')");

        // Wstaw nowe etykiety systemowe
        $this->table('speed_order_attachment_labels')->insert([
            ['name' => 'POL - zdjęcie',       'slug' => 'pol_photo', 'sort' => 10, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'POL - skan/oryginał',  'slug' => 'pol_scan',  'sort' => 20, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'POD - zdjęcie',        'slug' => 'pod_photo', 'sort' => 30, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'POD - skan/oryginał',  'slug' => 'pod_scan',  'sort' => 40, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'Inne',                 'slug' => 'other',     'sort' => 99, 'created' => date('Y-m-d H:i:s')],
        ])->save();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM speed_order_attachment_labels WHERE slug IN ('pol_photo','pol_scan','pod_photo','pod_scan','other')");

        $this->table('speed_order_attachment_labels')->insert([
            ['name' => 'CMR Zdjęcie',       'slug' => 'cmr_photo',   'sort' => 10, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'CMR Skan-oryginał', 'slug' => 'cmr_scan',    'sort' => 20, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'CMR Potwierdzenie', 'slug' => 'cmr_confirm', 'sort' => 30, 'created' => date('Y-m-d H:i:s')],
            ['name' => 'Inne',              'slug' => 'other',        'sort' => 99, 'created' => date('Y-m-d H:i:s')],
        ])->save();
    }
}

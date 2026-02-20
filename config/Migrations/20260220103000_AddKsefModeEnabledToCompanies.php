<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddKsefModeEnabledToCompanies extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('companies');

        if (!$table->hasColumn('ksef_mode_enabled')) {
            $table->addColumn('ksef_mode_enabled', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'vat_payer',
                'comment' => 'Tryb firmy: czy dokumenty mają być obsługiwane przez KSeF',
            ]);
        }

        $table->update();
    }
}

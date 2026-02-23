<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddContractorCorrespondenceAndNotificationMessage extends AbstractMigration
{
    public function change(): void
    {
        $contractors = $this->table('contractors');

        if (!$contractors->hasColumn('correspondence_street')) {
            $contractors->addColumn('correspondence_street', 'string', [
                'limit' => 160,
                'null' => true,
                'after' => 'street',
            ]);
        }
        if (!$contractors->hasColumn('correspondence_postal_code')) {
            $contractors->addColumn('correspondence_postal_code', 'string', [
                'limit' => 16,
                'null' => true,
                'after' => 'postal_code',
            ]);
        }
        if (!$contractors->hasColumn('correspondence_city')) {
            $contractors->addColumn('correspondence_city', 'string', [
                'limit' => 120,
                'null' => true,
                'after' => 'city',
            ]);
        }
        if (!$contractors->hasColumn('correspondence_country')) {
            $contractors->addColumn('correspondence_country', 'string', [
                'limit' => 2,
                'null' => true,
                'after' => 'country',
            ]);
        }

        $contractors->update();

        $settings = $this->table('contractors_settings');
        if (!$settings->hasColumn('notify_invoice_message')) {
            $settings
                ->addColumn('notify_invoice_message', 'text', [
                    'null' => true,
                    'after' => 'notify_email',
                ])
                ->update();
        }
    }
}

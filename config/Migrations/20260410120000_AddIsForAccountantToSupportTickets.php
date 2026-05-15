<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddIsForAccountantToSupportTickets extends AbstractMigration
{
    public function change(): void
    {
        $this->table('support_tickets')
            ->addColumn('is_for_accountant', 'boolean', [
                'null'    => false,
                'default' => false,
                'after'   => 'admin_note',
                'comment' => 'Czy zgłoszenie dotyczy księgowego',
            ])
            ->update();
    }
}

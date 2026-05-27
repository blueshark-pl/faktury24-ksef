<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Pola pod widok Kanban /rozliczenia/kanban.
 *
 * - snooze_until: data odłożenia karty (NULL = aktywna, < today = wraca)
 * - dispute_flag: czy faktura jest w sporze / windykacji
 * - dispute_reason: powód sporu (notatka)
 * - assigned_to_user_id: który użytkownik pilnuje sprawy (FK do users, nullable)
 * - kanban_pinned: przypięcie karty u góry kolumny ręcznie
 */
class AddKanbanFieldsToInvoices extends BaseMigration
{
    public function change(): void
    {
        $this->table('invoices')
            ->addColumn('snooze_until', 'date', [
                'null' => true, 'default' => null,
                'after' => 'paymentdate',
                'comment' => 'Data odłożenia karty Kanban (NULL = aktywna)',
            ])
            ->addColumn('dispute_flag', 'boolean', [
                'null' => false, 'default' => false,
                'after' => 'snooze_until',
                'comment' => 'Faktura w sporze / windykacji (Kanban)',
            ])
            ->addColumn('dispute_reason', 'text', [
                'null' => true, 'default' => null,
                'after' => 'dispute_flag',
                'comment' => 'Powód oznaczenia jako spór',
            ])
            ->addColumn('assigned_to_user_id', 'char', [
                'limit' => 36, 'null' => true, 'default' => null,
                'after' => 'dispute_reason',
                'comment' => 'FK do users.id — kto pilnuje rozliczenia',
            ])
            ->addColumn('kanban_pinned', 'boolean', [
                'null' => false, 'default' => false,
                'after' => 'assigned_to_user_id',
                'comment' => 'Karta przypięta na górze kolumny Kanban',
            ])
            ->addIndex(['snooze_until'], ['name' => 'BY_SNOOZE'])
            ->addIndex(['dispute_flag'], ['name' => 'BY_DISPUTE'])
            ->addIndex(['assigned_to_user_id'], ['name' => 'BY_ASSIGNED'])
            ->save();
    }
}

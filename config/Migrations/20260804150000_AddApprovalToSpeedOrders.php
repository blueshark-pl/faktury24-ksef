<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Approval workflow dla zlecen:
 *  - approval_status: 'not_required' | 'pending' | 'approved' | 'rejected'
 *  - approved_by_user_id + approved_at + approval_note
 *
 * Prog wymagajacy akceptacji jest globalny (Configure.Orders.approvalThresholdPln
 * lub domyslnie 10000 PLN). Sprawdzany serwerowo przy zapisie.
 */
class AddApprovalToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $t = $this->table('speed_orders');
        $t->addColumn('approval_status', 'string', [
                'limit' => 20, 'null' => false, 'default' => 'not_required',
                'after' => 'nordlogis_status',
                'comment' => 'not_required | pending | approved | rejected',
            ])
            ->addColumn('approved_by_user_id', 'char', [
                'limit' => 36, 'null' => true, 'after' => 'approval_status',
                'comment' => 'FK do users.id (nullable az do akceptacji)',
            ])
            ->addColumn('approved_at', 'datetime', [
                'null' => true, 'after' => 'approved_by_user_id',
            ])
            ->addColumn('approval_note', 'string', [
                'limit' => 500, 'null' => true, 'after' => 'approved_at',
                'comment' => 'Komentarz managera przy accept/reject',
            ])
            ->addIndex(['approval_status'], ['name' => 'BY_APPROVAL_STATUS'])
            ->update();

        // Backfill: istniejace zlecenia = not_required (byly wystawione bez tego workflow)
        $this->execute("UPDATE speed_orders SET approval_status = 'not_required' WHERE approval_status IS NULL OR approval_status = ''");
    }

    public function down(): void
    {
        $t = $this->table('speed_orders');
        $t->removeIndexByName('BY_APPROVAL_STATUS')
          ->removeColumn('approval_note')
          ->removeColumn('approved_at')
          ->removeColumn('approved_by_user_id')
          ->removeColumn('approval_status')
          ->update();
    }
}

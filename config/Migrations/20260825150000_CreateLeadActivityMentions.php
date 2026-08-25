<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: @mentions w LeadActivities.
 * Parser wykrywa @email/@login w body/subject aktywnosci, wstawia rekordy
 * do lead_activity_mentions - wspomniany user dostaje notifikacje in-app + push.
 */
class CreateLeadActivityMentions extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('lead_activity_mentions', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('activity_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('mentioned_user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('by_user_id', 'char', ['limit' => 36, 'null' => true, 'comment' => 'Kto wspomnial (autor activity)'])
            ->addColumn('seen_at', 'datetime', ['null' => true, 'comment' => 'Kiedy odczytal - NULL = unread'])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['mentioned_user_id', 'seen_at'], ['name' => 'BY_USER_UNREAD'])
            ->addIndex(['activity_id'], ['name' => 'BY_ACTIVITY'])
            ->addIndex(['company_id'], ['name' => 'BY_COMPANY'])
            ->create();
    }

    public function down(): void
    {
        $this->table('lead_activity_mentions')->drop()->save();
    }
}

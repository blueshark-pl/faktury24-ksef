<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CRM - timeline aktywnosci per lead.
 *
 * Kazdy wpis to jedno zdarzenie: telefon, email, spotkanie, notatka,
 * zaplanowane zadanie, zmiana etapu (system), przypisanie itd.
 *
 * activity_type:
 *   phone_call / email_out / email_in / meeting / note / task / file /
 *   stage_change / assignment / offer_sent / order_won / order_lost
 */
class CreateLeadActivities extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('lead_activities')) return;

        $this->table('lead_activities', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false,
                'comment' => 'FK do leads (CASCADE)'])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'Autor wpisu (NULL = system)'])

            ->addColumn('activity_type', 'string', ['limit' => 30, 'null' => false,
                'comment' => 'phone_call | email_out | email_in | meeting | note | task | file | stage_change | assignment | offer_sent | order_won | order_lost'])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Krotki temat (np. temat maila lub tytul spotkania)'])
            ->addColumn('body', 'text', ['null' => true,
                'comment' => 'Tresc/opis'])

            // Metadata dla poszczegolnych typow
            ->addColumn('duration_min', 'integer', ['null' => true,
                'comment' => 'Czas rozmowy/spotkania w minutach'])
            ->addColumn('happened_at', 'datetime', ['null' => true,
                'comment' => 'Kiedy zdarzenie faktycznie mialo miejsce (dla history)'])
            ->addColumn('due_at', 'datetime', ['null' => true,
                'comment' => 'Termin (dla task/reminder)'])
            ->addColumn('is_done', 'boolean', ['default' => false,
                'comment' => 'Dla task - czy wykonane'])
            ->addColumn('done_at', 'datetime', ['null' => true])

            // Attachment (opcjonalny)
            ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('file_name', 'string', ['limit' => 255, 'null' => true])

            // Metadata JSON (payload akcji - np. old_stage/new_stage dla stage_change)
            ->addColumn('payload_json', 'text', ['null' => true])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['lead_id', 'happened_at'], ['name' => 'BY_LEAD_TIME'])
            ->addIndex(['company_id', 'activity_type'], ['name' => 'BY_COMPANY_TYPE'])
            ->addIndex(['due_at'], ['name' => 'BY_DUE'])
            ->addForeignKey('lead_id', 'leads', 'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('lead_activities')->drop()->save();
    }
}

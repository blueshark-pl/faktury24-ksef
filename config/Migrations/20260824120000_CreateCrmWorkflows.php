<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CRM Workflows engine - if-this-then-that reguly automatyzacji.
 *
 * Trigger types:
 *   - stage_no_activity_days  - lead w danym stage bez activity przez X dni
 *   - lead_age_days           - lead o age X dni bez zmiany stage
 *   - task_overdue            - task typu 'task' przekroczony due_at
 *
 * Action types:
 *   - create_task             - dodaj activity_type=task z due_at+N dni
 *   - send_email              - wysli email do assigned_user leada (template)
 *   - change_stage            - zmien stage leada na zdefiniowany
 *   - notify_slack            - webhook Slack (opcjonalny)
 *
 * Cron `bin/cake crm_workflow_run` co 10 min sprawdza wszystkie is_active workflows.
 * Kazdy workflow ma cooldown_hours zeby nie firowal za czesto na tym samym leadzie.
 */
class CreateCrmWorkflows extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_workflows')) return;

        $this->table('crm_workflows', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])

            // Trigger
            ->addColumn('trigger_type', 'string', ['limit' => 40, 'null' => false,
                'comment' => 'stage_no_activity_days | lead_age_days | task_overdue'])
            ->addColumn('trigger_config', 'text', ['null' => true,
                'comment' => 'JSON: {stage:"offer", days:14} lub {stage:"any", days:30}'])

            // Filtry dodatkowe (JSON)
            ->addColumn('condition_json', 'text', ['null' => true,
                'comment' => 'JSON: {branch_type:"road", country_code:"PL", probability_min:50}'])

            // Action
            ->addColumn('action_type', 'string', ['limit' => 40, 'null' => false,
                'comment' => 'create_task | send_email | change_stage'])
            ->addColumn('action_config', 'text', ['null' => true,
                'comment' => 'JSON: {task_description, due_days} / {email_template} / {new_stage}'])

            // Cooldown i state
            ->addColumn('cooldown_hours', 'integer', ['limit' => 5, 'null' => false, 'default' => 24,
                'comment' => 'Nie firuj ponownie na tym samym leadzie w tym oknie'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('last_run_at', 'datetime', ['null' => true])
            ->addColumn('run_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('leads_triggered_count', 'integer', ['null' => false, 'default' => 0])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'is_active'], ['name' => 'BY_COMPANY_ACTIVE'])
            ->create();

        // Pomocnicza tabela: rekord kiedy workflow ostatnio odpalil sie na konkretnym leadzie (dla cooldown)
        $this->table('crm_workflow_runs', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('workflow_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('run_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('action_result', 'string', ['limit' => 20, 'null' => true,
                'comment' => 'success | skipped | error'])
            ->addColumn('note', 'text', ['null' => true])
            ->addIndex(['workflow_id', 'lead_id', 'run_at'], ['name' => 'BY_WORKFLOW_LEAD_TIME'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_workflow_runs')->drop()->save();
        $this->table('crm_workflows')->drop()->save();
    }
}

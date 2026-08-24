<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * Cron CRM Workflows engine - if-this-then-that automation.
 *
 * Dla kazdego is_active workflow:
 *   1. Ewaluuje trigger (znajduje matchujace leady)
 *   2. Filtruje po condition_json
 *   3. Sprawdza cooldown (crm_workflow_runs)
 *   4. Wykonuje akcje (create_task / send_email / change_stage)
 *   5. Zapisuje run w crm_workflow_runs
 *
 * Trigger types:
 *   stage_no_activity_days  {stage, days}
 *   lead_age_days           {days}
 *   task_overdue            {days_over}
 *
 * Action types:
 *   create_task    {description, due_days}
 *   send_email     {subject, body}       (do assigned_user)
 *   change_stage   {new_stage}
 *
 * Usage:
 *   bin/cake crm_workflow_run           (production, co 10 min)
 *   bin/cake crm_workflow_run --dry     (preview co by zrobil)
 *   bin/cake crm_workflow_run --workflow=<uuid>
 */
class CrmWorkflowRunCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('CRM Workflows engine - odpala aktywne automatyzacje.')
            ->addOption('dry', ['boolean' => true, 'default' => false])
            ->addOption('workflow', ['default' => null, 'help' => 'Odpal tylko jeden workflow (uuid)'])
            ->addOption('company', ['default' => null]);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dry = (bool)$args->getOption('dry');
        $workflowFilter = $args->getOption('workflow');
        $companyFilter = $args->getOption('company');

        $Workflows = TableRegistry::getTableLocator()->get('CrmWorkflows');
        $Runs      = TableRegistry::getTableLocator()->get('CrmWorkflowRuns');
        $Leads     = TableRegistry::getTableLocator()->get('Leads');
        $Acts      = TableRegistry::getTableLocator()->get('LeadActivities');
        $Users     = TableRegistry::getTableLocator()->get('Users');

        $wq = $Workflows->find()->where(['is_active' => true]);
        if ($workflowFilter) $wq->where(['id' => $workflowFilter]);
        if ($companyFilter)  $wq->where(['company_id' => $companyFilter]);
        $workflows = $wq->all();

        $io->out(sprintf('Znaleziono %d aktywnych workflows.', count($workflows)));
        if ($dry) $io->warning('DRY-RUN.');

        $totalActions = 0;
        foreach ($workflows as $wf) {
            $io->out(sprintf('[%s] %s (trigger=%s, action=%s)', $wf->id, $wf->name, $wf->trigger_type, $wf->action_type));

            $trg = json_decode((string)$wf->trigger_config, true) ?: [];
            $cnd = json_decode((string)$wf->condition_json, true) ?: [];
            $act = json_decode((string)$wf->action_config, true) ?: [];

            // Znajdz matchujace leady
            $matches = $this->findMatches($Leads, $Acts, $wf, $trg);
            $matches = $this->applyConditions($matches, $cnd);
            $io->out(sprintf('  Matching leadow: %d', count($matches)));

            $applied = 0;
            $cooldown = max(1, (int)$wf->cooldown_hours);
            $cooldownAgo = (new \DateTimeImmutable())->modify("-{$cooldown} hours");

            foreach ($matches as $lead) {
                // Sprawdz cooldown - czy odpalilismy ten workflow na tym leadzie ostatnio
                $recent = $Runs->find()
                    ->where([
                        'workflow_id' => $wf->id,
                        'lead_id'     => $lead->id,
                        'run_at >='   => new DateTime($cooldownAgo->format('c')),
                    ])
                    ->count();
                if ($recent > 0) {
                    $io->out(sprintf('    Lead %s: cooldown active, skip', $lead->id));
                    continue;
                }

                // Wykonaj akcje
                try {
                    $result = $dry ? 'dry' : $this->executeAction($wf, $act, $lead, $Leads, $Acts, $Users);
                    $io->out(sprintf('    Lead %s (%s): action=%s -> %s',
                        $lead->id, $lead->company_name, $wf->action_type, $result));
                    if (!$dry) {
                        $Runs->save($Runs->newEntity([
                            'id'          => Text::uuid(),
                            'workflow_id' => $wf->id,
                            'lead_id'     => $lead->id,
                            'run_at'      => new DateTime(),
                            'action_result' => $result === 'success' ? 'success' : ($result === 'skipped' ? 'skipped' : 'error'),
                            'note'        => $result,
                        ]));
                        $applied++;
                    }
                } catch (\Throwable $e) {
                    $io->error(sprintf('    Lead %s: exception - %s', $lead->id, $e->getMessage()));
                }
            }

            if (!$dry) {
                $wf->last_run_at = new DateTime();
                $wf->run_count = (int)$wf->run_count + 1;
                $wf->leads_triggered_count = (int)$wf->leads_triggered_count + $applied;
                $Workflows->save($wf);
            }
            $totalActions += $applied;
            $io->success(sprintf('  Zastosowano na %d leadach.', $applied));
        }

        $io->success(sprintf('Zakonczono. Akcji wykonanych: %d.', $totalActions));
        return static::CODE_SUCCESS;
    }

    private function findMatches($Leads, $Acts, $wf, array $trg): array
    {
        $now = new \DateTimeImmutable();

        switch ($wf->trigger_type) {
            case 'stage_no_activity_days':
                $stage = $trg['stage'] ?? 'offer';
                $days  = max(1, (int)($trg['days'] ?? 14));
                $threshold = new DateTime($now->modify("-{$days} days")->format('c'));

                $q = $Leads->find()->where([
                    'Leads.company_id' => $wf->company_id,
                    'Leads.stage'      => $stage,
                    'OR' => [
                        'Leads.last_contacted_at IS' => null,
                        'Leads.last_contacted_at <'  => $threshold,
                    ],
                    'Leads.modified <' => $threshold,
                ]);
                return $q->limit(500)->all()->toArray();

            case 'lead_age_days':
                $days = max(1, (int)($trg['days'] ?? 30));
                $threshold = new DateTime($now->modify("-{$days} days")->format('c'));
                return $Leads->find()->where([
                    'Leads.company_id' => $wf->company_id,
                    'Leads.stage NOT IN' => ['order', 'lost'],
                    'Leads.created <' => $threshold,
                ])->limit(500)->all()->toArray();

            case 'task_overdue':
                $daysOver = (int)($trg['days_over'] ?? 0);
                $threshold = new DateTime($now->modify("-{$daysOver} days")->format('c'));
                // Znajdz leady z overdue tasks
                $leadIds = $Acts->find()
                    ->select(['lead_id'])
                    ->where([
                        'company_id' => $wf->company_id,
                        'activity_type' => 'task',
                        'is_done' => false,
                        'due_at IS NOT' => null,
                        'due_at <' => $threshold,
                    ])
                    ->groupBy('lead_id')
                    ->disableHydration()->toArray();
                $ids = array_column($leadIds, 'lead_id');
                if (empty($ids)) return [];
                return $Leads->find()->where([
                    'Leads.company_id' => $wf->company_id,
                    'Leads.id IN' => $ids,
                ])->all()->toArray();
        }
        return [];
    }

    private function applyConditions(array $leads, array $cnd): array
    {
        if (empty($cnd)) return $leads;
        return array_values(array_filter($leads, function ($l) use ($cnd) {
            if (!empty($cnd['branch_type']) && $l->branch_type !== $cnd['branch_type']) return false;
            if (!empty($cnd['country_code']) && strtoupper((string)$l->country_code) !== strtoupper((string)$cnd['country_code'])) return false;
            if (!empty($cnd['probability_min']) && (int)$l->probability < (int)$cnd['probability_min']) return false;
            if (!empty($cnd['probability_max']) && (int)$l->probability > (int)$cnd['probability_max']) return false;
            if (!empty($cnd['value_min']) && (float)($l->value_pln ?? 0) < (float)$cnd['value_min']) return false;
            return true;
        }));
    }

    private function executeAction($wf, array $act, $lead, $Leads, $Acts, $Users): string
    {
        switch ($wf->action_type) {
            case 'create_task':
                $desc = trim((string)($act['description'] ?? 'Follow-up (auto z workflow)'));
                $dueDays = (int)($act['due_days'] ?? 1);
                $due = new DateTime("+{$dueDays} days");
                $Acts->save($Acts->newEntity([
                    'company_id'   => $wf->company_id,
                    'lead_id'      => $lead->id,
                    'user_id'      => null,
                    'activity_type'=> 'task',
                    'subject'      => $desc,
                    'body'         => sprintf('Auto-utworzony przez workflow: %s', $wf->name),
                    'due_at'       => $due,
                    'is_done'      => false,
                    'happened_at'  => new DateTime(),
                    'payload_json' => json_encode(['workflow_id' => $wf->id]),
                ]));
                return 'success';

            case 'change_stage':
                $newStage = (string)($act['new_stage'] ?? '');
                if (!in_array($newStage, \App\Model\Table\LeadsTable::STAGES, true)) return 'error:invalid_stage';
                if ($lead->stage === $newStage) return 'skipped:already_in_stage';
                $lead->stage = $newStage;
                $Leads->save($lead);
                $Acts->logSystem((string)$wf->company_id, (string)$lead->id, 'stage_change',
                    sprintf('%s (auto workflow: %s)', $newStage, $wf->name),
                    null, ['workflow_id' => $wf->id], null);
                return 'success';

            case 'send_email':
                if (empty($lead->assigned_to_user_id)) return 'skipped:no_owner';
                $owner = $Users->find()->where(['id' => $lead->assigned_to_user_id])->first();
                if (!$owner || empty($owner->email)) return 'skipped:no_owner_email';
                $subject = str_replace(['{{company}}', '{{stage}}'], [$lead->company_name, $lead->stage],
                    (string)($act['subject'] ?? '[CRM] Follow-up: {{company}}'));
                $body    = str_replace(['{{company}}', '{{stage}}'], [$lead->company_name, $lead->stage],
                    (string)($act['body'] ?? "Lead {{company}} w stage {{stage}} - zajmij sie."));
                try {
                    $mailer = new \Cake\Mailer\Mailer('default');
                    $mailer->setTo((string)$owner->email)->setSubject($subject)->deliver($body);
                    return 'success';
                } catch (\Throwable $e) {
                    return 'error:' . $e->getMessage();
                }
        }
        return 'error:unknown_action';
    }
}

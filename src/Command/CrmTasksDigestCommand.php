<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\Mailer\Mailer;
use Cake\ORM\TableRegistry;

/**
 * Cron CRM digest — codzienny email do handlowcow z zadaniami CRM.
 *
 * Wysyla per user z aktywnymi taskami/follow-upami w oknie dni.
 * Grupuje: PRZETERMINOWANE / DZIS / NADCHODZACE (X dni).
 *
 * Usage (cron daily, np. 7:30 rano):
 *   bin/cake crm_tasks_digest
 *   bin/cake crm_tasks_digest --dry
 *   bin/cake crm_tasks_digest --days=7
 *   bin/cake crm_tasks_digest --company=<uuid>
 *   bin/cake crm_tasks_digest --user=<uuid>
 */
class CrmTasksDigestCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Codzienny email do handlowcow z ich zadaniami CRM (przeterminowane + dzisiaj + do X dni).')
            ->addOption('dry', ['boolean' => true, 'default' => false,
                'help' => 'Preview mode — nie wysyla, tylko wyswietla']
            )
            ->addOption('days', ['default' => 7,
                'help' => 'Prog dni do przodu (default 7)']
            )
            ->addOption('company', ['default' => null,
                'help' => 'Ogranicz do jednej firmy (company_id)']
            )
            ->addOption('user', ['default' => null,
                'help' => 'Ogranicz do jednego usera (user_id) - dla testow']
            )
            ->addOption('stale-days', ['default' => 14,
                'help' => 'Prog dni bez aktywnosci dla "Zapomniane leady" (default 14, 0 = wylacz)']
            );
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dry = (bool)$args->getOption('dry');
        $days = (int)$args->getOption('days');
        $companyFilter = $args->getOption('company');
        $userFilter = $args->getOption('user');
        $staleDays = (int)$args->getOption('stale-days');

        $LeadActivities = TableRegistry::getTableLocator()->get('LeadActivities');
        $Leads = TableRegistry::getTableLocator()->get('Leads');
        $Users = TableRegistry::getTableLocator()->get('Users');

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $from = $today->modify('-30 days');  // przeterminowane do 30 dni wstecz
        $to = $today->modify("+{$days} days");

        // Znajdz wszystkie active tasks z lead_activities
        $tasksQuery = $LeadActivities->find()
            ->contain([
                'Leads' => function ($q) {
                    return $q->select(['id', 'company_id', 'company_name', 'nip', 'stage',
                        'city', 'country_code', 'assigned_to_user_id']);
                },
                'Users' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'email']);
                },
            ])
            ->where([
                'LeadActivities.activity_type' => 'task',
                'LeadActivities.is_done'       => false,
                'LeadActivities.due_at IS NOT' => null,
                'LeadActivities.due_at >='     => new DateTime($from->format('c')),
                'LeadActivities.due_at <='     => new DateTime($to->format('c')),
            ]);
        if ($companyFilter) {
            $tasksQuery->where(['LeadActivities.company_id' => $companyFilter]);
        }
        $tasks = $tasksQuery->all();

        // Grupuj po opiekunie leada (assigned_to_user_id) lub autorze taska
        $byUser = [];
        foreach ($tasks as $t) {
            $uid = (string)($t->lead->assigned_to_user_id ?? $t->user_id ?? '');
            if (!$uid) continue;
            if ($userFilter && $uid !== $userFilter) continue;
            $byUser[$uid][] = $t;
        }

        // Dodaj tez leads.next_action_at (follow-upy bez taska)
        $followupsQuery = $Leads->find()
            ->contain([
                'AssignedUser' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'email']);
                },
            ])
            ->where([
                'Leads.next_action_at IS NOT' => null,
                'Leads.next_action_at >=' => new DateTime($from->format('c')),
                'Leads.next_action_at <=' => new DateTime($to->format('c')),
                'Leads.assigned_to_user_id IS NOT' => null,
            ]);
        if ($companyFilter) {
            $followupsQuery->where(['Leads.company_id' => $companyFilter]);
        }
        $followups = $followupsQuery->all();
        $followupsByUser = [];
        foreach ($followups as $l) {
            $uid = (string)$l->assigned_to_user_id;
            if ($userFilter && $uid !== $userFilter) continue;
            $followupsByUser[$uid][] = $l;
        }

        // Zapomniane leady - bez aktywnosci przez X dni (last_contacted_at + activity_since)
        $staleByUser = [];
        if ($staleDays > 0) {
            $staleThreshold = new DateTime("-{$staleDays} days");
            $staleQuery = $Leads->find()
                ->contain([
                    'AssignedUser' => function ($q) {
                        return $q->select(['id', 'first_name', 'last_name', 'email']);
                    },
                ])
                ->where([
                    'Leads.stage IN' => ['new', 'contact', 'inquiry', 'offer'],
                    'Leads.assigned_to_user_id IS NOT' => null,
                    'OR' => [
                        'Leads.last_contacted_at IS' => null,
                        'Leads.last_contacted_at <'  => $staleThreshold,
                    ],
                    // Ignoruj snoozed
                    'OR2' => [
                        'Leads.snooze_until IS' => null,
                        'Leads.snooze_until <=' => new DateTime(),
                    ],
                    'Leads.modified <' => $staleThreshold, // dodatkowo: nie ruszany od X dni
                ]);
            if ($companyFilter) {
                $staleQuery->where(['Leads.company_id' => $companyFilter]);
            }
            $staleLeads = $staleQuery->limit(200)->all();
            foreach ($staleLeads as $l) {
                $uid = (string)$l->assigned_to_user_id;
                if ($userFilter && $uid !== $userFilter) continue;
                $staleByUser[$uid][] = $l;
            }
        }

        $allUserIds = array_unique(array_merge(
            array_keys($byUser),
            array_keys($followupsByUser),
            array_keys($staleByUser)
        ));
        $io->out(sprintf('Znaleziono %d taskow + %d follow-upow + %d zapomnianych dla %d handlowcow.',
            count($tasks), count($followups),
            array_sum(array_map('count', $staleByUser)),
            count($allUserIds)));

        if ($dry) $io->warning('DRY-RUN — nie wysylamy maili.');

        $totalSent = 0;
        foreach ($allUserIds as $uid) {
            $user = $Users->find()->where(['Users.id' => $uid])->first();
            if (!$user || empty($user->email)) {
                $io->warning("Pominieto usera $uid — brak email.");
                continue;
            }

            $userTasks = $byUser[$uid] ?? [];
            $userFollowups = $followupsByUser[$uid] ?? [];
            $userStale = $staleByUser[$uid] ?? [];
            $total = count($userTasks) + count($userFollowups) + count($userStale);
            if ($total === 0) continue;

            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email;

            // Buckety - musimy porownywac po timestampie tylko
            $overdue = [];
            $todayList = [];
            $upcoming = [];
            foreach ($userTasks as $t) {
                $due = new \DateTimeImmutable($t->due_at->format('c'));
                if ($due < $today)      $overdue[]  = ['type' => 'task', 'item' => $t];
                elseif ($due < $tomorrow) $todayList[] = ['type' => 'task', 'item' => $t];
                else                    $upcoming[] = ['type' => 'task', 'item' => $t];
            }
            foreach ($userFollowups as $l) {
                $due = new \DateTimeImmutable($l->next_action_at->format('c'));
                if ($due < $today)      $overdue[]  = ['type' => 'followup', 'item' => $l];
                elseif ($due < $tomorrow) $todayList[] = ['type' => 'followup', 'item' => $l];
                else                    $upcoming[] = ['type' => 'followup', 'item' => $l];
            }

            $io->out(sprintf('[%s] %s → %s (przeterm: %d, dzis: %d, do %d dni: %d, zapomniane: %d)',
                $uid, $userName, $user->email,
                count($overdue), count($todayList), $days, count($upcoming), count($userStale)
            ));

            if ($dry) continue;

            try {
                $mailer = new Mailer('default');
                $mailer->setTo((string)$user->email)
                    ->setSubject(sprintf('[CRM] %s: %d zadań na dziś (%d przeterminowanych)',
                        $userName, count($todayList) + count($overdue), count($overdue)))
                    ->setEmailFormat('html')
                    ->viewBuilder()->setLayout('default')->setTemplate('crm_tasks_digest');
                $mailer->setViewVars([
                    'userName'  => $userName,
                    'overdue'   => $overdue,
                    'todayList' => $todayList,
                    'upcoming'  => $upcoming,
                    'stale'     => $userStale,
                    'staleDays' => $staleDays,
                    'days'      => $days,
                    'baseUrl'   => rtrim((string)\Cake\Core\Configure::read('App.fullBaseUrl'), '/'),
                ]);
                $mailer->deliver();
                $totalSent++;
                $io->success('  Wyslano.');
            } catch (\Throwable $e) {
                $io->error('  Blad: ' . $e->getMessage());
            }
        }

        $io->success("Zakonczono. Digesty wyslane: $totalSent (dry=" . ($dry ? 'yes' : 'no') . ')');
        return static::CODE_SUCCESS;
    }
}

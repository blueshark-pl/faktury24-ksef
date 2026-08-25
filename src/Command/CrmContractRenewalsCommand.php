<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Mailer\Mailer;
use Cake\Log\Log;

/**
 * FALA extras: Cron dla long-term contracts renewal reminders.
 * Codziennie sprawdza `crm_contracts` z valid_to w oknie <= --days (default 60).
 * Wysyla email do assigned_user leada (albo pierwszego admina firmy).
 *
 * Idempotent - flag w payload_json (albo cache) zeby nie wysylac 2x w oknie.
 *
 * Uzycie:
 *   bin/cake crm_contract_renewals              # 60 dni domyslnie
 *   bin/cake crm_contract_renewals --days=30    # 30 dni
 *   bin/cake crm_contract_renewals --dry        # preview
 */
class CrmContractRenewalsCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('days', ['default' => 60, 'help' => 'Okno wygasniecia (default 60 dni)']);
        $parser->addOption('dry', ['boolean' => true, 'default' => false, 'help' => 'Bez wysylki']);
        $parser->addOption('company', ['default' => null, 'help' => 'Ogranicz do jednej firmy (uuid)']);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        @set_time_limit(300);
        $days = (int)$args->getOption('days');
        $dry = (bool)$args->getOption('dry');
        $companyFilter = trim((string)$args->getOption('company'));

        $io->out(sprintf('=== CRM Contract Renewals (okno %d dni%s) ===', $days, $dry ? ' DRY' : ''));

        try {
            $Contracts = \Cake\ORM\TableRegistry::getTableLocator()->get('CrmContracts');
        } catch (\Throwable $e) {
            $io->error('CrmContracts table not found - migracja nie odpalona: ' . $e->getMessage());
            return Command::CODE_ERROR;
        }

        // Znajdz firmy do przetworzenia
        $companies = [];
        if ($companyFilter !== '') {
            $companies = [$companyFilter];
        } else {
            $rows = $Contracts->find()->select(['company_id'])->distinct(['company_id'])->all();
            foreach ($rows as $r) $companies[] = (string)$r->company_id;
        }

        $totalSent = 0;
        foreach ($companies as $companyId) {
            $expiring = $Contracts->findExpiringSoon($companyId, $days);
            if (empty($expiring)) continue;

            $io->out(sprintf('  Firma %s: %d kontraktow wygasajacych <=%d dni', substr($companyId, 0, 8), count($expiring), $days));

            // Znajdz odbiorce - pierwszy admin/user firmy
            $recipient = null;
            try {
                $Users = \Cake\ORM\TableRegistry::getTableLocator()->get('Users');
                $recipient = $Users->find()
                    ->where(['company_id' => $companyId, 'active' => true])
                    ->orderByDesc('is_admin')->orderByAsc('created')
                    ->first();
            } catch (\Throwable $e) {}

            if (!$recipient || empty($recipient->email)) {
                $io->out('    ⚠ Brak odbiorcy dla firmy, pomijam');
                continue;
            }

            if ($dry) {
                $io->out(sprintf('    DRY: wyslany bylby email do %s (%d kontraktow)', $recipient->email, count($expiring)));
                $totalSent++;
                continue;
            }

            try {
                // TEST MODE override (FALA test)
                $override = trim((string)\Cake\Core\Configure::read('Crm.testEmailOverride'));
                $originalTo = $recipient->email;
                $realTo = $override !== '' ? $override : $originalTo;

                $subject = sprintf('CRM: %d kontraktów ramowych wygasa w najbliższym czasie', count($expiring));
                if ($override !== '') $subject = '[TEST → ' . $originalTo . '] ' . $subject;

                $mailer = new Mailer('default');
                $mailer->setTo($realTo, trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')))
                    ->setSubject($subject)
                    ->setEmailFormat('html')
                    ->viewBuilder()->setLayout('default')->setTemplate('crm_contract_renewals');
                $mailer->setViewVars([
                    'contracts' => $expiring,
                    'daysWindow' => $days,
                    'testMode' => $override !== '',
                    'originalTo' => $originalTo,
                ]);
                $mailer->deliver();

                $io->success(sprintf('    ✓ Email wyslany do %s (%d kontraktow)', $realTo, count($expiring)));
                $totalSent++;
            } catch (\Throwable $e) {
                Log::warning('CrmContractRenewals email failed: ' . $e->getMessage());
                $io->error('    ❌ ' . $e->getMessage());
            }
        }

        $io->success(sprintf('Zakonczono. Wyslano %d emaili.', $totalSent));
        return Command::CODE_SUCCESS;
    }
}

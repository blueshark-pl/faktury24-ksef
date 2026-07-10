<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\FrozenTime;
use Cake\Mailer\Mailer;
use Cake\ORM\TableRegistry;

/**
 * Cron alerty dla planera operacyjnego.
 *
 * Sprawdza:
 *  - vehicle_maintenance.valid_until — wygasa w ciagu 30 dni
 *  - alert wysylany maksymalnie raz (alert_sent_at flag)
 *
 * Usage (cron daily):
 *   bin/cake alerts               — wyslij zaległe (dry=false)
 *   bin/cake alerts --dry         — preview co byloby wyslane
 *   bin/cake alerts --days=14     — inny prog dni
 */
class AlertsCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Cron alerts — wysyla mail 30 dni przed wygasnieciem badania/OC/ADR.')
            ->addOption('dry', ['boolean' => true, 'default' => false,
                'help' => 'Preview mode — nie wysyla, tylko wyswietla',
            ])
            ->addOption('days', ['default' => 30,
                'help' => 'Prog dni do wygasniecia (default 30)',
            ])
            ->addOption('company', ['default' => null,
                'help' => 'Ogranicz do jednej firmy (company_id)',
            ]);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dry = (bool)$args->getOption('dry');
        $days = (int)$args->getOption('days');
        $companyFilter = $args->getOption('company');

        $VM = TableRegistry::getTableLocator()->get('VehicleMaintenance');
        $Companies = TableRegistry::getTableLocator()->get('Companies');
        $Users = TableRegistry::getTableLocator()->get('Users');

        $limit = (new \DateTimeImmutable("+{$days} days"))->format('Y-m-d');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        // Znajdz wpisy wygasajace, ktore jeszcze nie mialy alertu
        // (alert_sent_at IS NULL lub wysylalismy dawno)
        $q = $VM->find()
            ->where([
                'VehicleMaintenance.is_active'       => true,
                'VehicleMaintenance.valid_until IS NOT' => null,
                'VehicleMaintenance.valid_until >='  => $today,
                'VehicleMaintenance.valid_until <='  => $limit,
                'OR' => [
                    'VehicleMaintenance.alert_sent_at IS' => null,
                    'VehicleMaintenance.alert_sent_at <'  => (new \DateTimeImmutable('-14 days'))->format('Y-m-d H:i:s'),
                ],
            ])
            ->contain(['Vehicles', 'Trailers']);

        if ($companyFilter) {
            $q->where(['VehicleMaintenance.company_id' => $companyFilter]);
        }

        $records = $q->all();

        $io->out(sprintf('Znaleziono %d wpisow do alertu (prog %d dni).', count($records), $days));
        if ($dry) {
            $io->warning('DRY-RUN — nie wysylamy maili.');
        }

        // Grupuj po company_id — jeden mail per firma z lista wszystkich
        $byCompany = [];
        foreach ($records as $r) {
            $byCompany[(string)$r->company_id][] = $r;
        }

        $totalSent = 0;
        foreach ($byCompany as $companyId => $rows) {
            $company = $Companies->find()->where(['id' => $companyId])->first();
            if (!$company) {
                $io->warning("Pominieto — firma $companyId nie istnieje.");
                continue;
            }

            // Znajdz emaila admina firmy (pierwszy uzytkownik z rola admin lub user)
            $adminEmail = null;
            try {
                $admin = $Users->find()
                    ->where(['company_id' => $companyId])
                    ->orderByAsc('created')
                    ->first();
                if ($admin && !empty($admin->email)) {
                    $adminEmail = (string)$admin->email;
                }
            } catch (\Throwable) {}

            if (!$adminEmail) {
                $adminEmail = (string)\Cake\Core\Configure::read('App.adminEmail');
            }
            if (!$adminEmail) {
                $io->warning("Pominieto firme $companyId — brak email.");
                continue;
            }

            $io->out("[$companyId] {$company->name} → $adminEmail (" . count($rows) . " wpisow)");

            if ($dry) {
                foreach ($rows as $r) {
                    $asset = $r->vehicle ? "Pojazd: {$r->vehicle->name} ({$r->vehicle->plate})"
                        : ($r->trailer ? "Naczepa: {$r->trailer->name} ({$r->trailer->plate})" : 'Brak assetu');
                    $io->out("  - $asset · {$r->maintenance_type} · wygasa {$r->valid_until->format('Y-m-d')}");
                }
                continue;
            }

            // Wyslij mail
            try {
                $mailer = new Mailer('default');
                $mailer->setTo($adminEmail)
                    ->setSubject('[faktury24] Uwaga: ' . count($rows) . ' dokumentów pojazdów wygasa w ciągu ' . $days . ' dni')
                    ->setEmailFormat('html')
                    ->viewBuilder()->setLayout('default')->setTemplate('vehicle_expiring');
                $mailer->setViewVars([
                    'company' => $company,
                    'records' => $rows,
                    'days'    => $days,
                ]);
                $mailer->deliver();

                // Oznacz alert jako wyslany
                foreach ($rows as $r) {
                    $r->alert_sent_at = new FrozenTime();
                    $VM->save($r);
                }
                $totalSent += count($rows);
                $io->success("  Wyslano.");
            } catch (\Throwable $e) {
                $io->error("  Blad: " . $e->getMessage());
            }
        }

        $io->success("Zakonczono. Alerty wyslane: $totalSent (dry=" . ($dry ? 'yes' : 'no') . ")");
        return static::CODE_SUCCESS;
    }
}

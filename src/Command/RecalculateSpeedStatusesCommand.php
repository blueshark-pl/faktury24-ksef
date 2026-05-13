<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

/**
 * Recalculates nordlogis_status + is_complete for all speed_orders
 * based on physical POL/POD attachments + fs_at.
 *
 * Rule:
 *   3 (Załadowane)    ← plik POL (slug pol_*) istnieje
 *   4 (Zrealizowane)  ← plik POD (slug pod_*) istnieje
 *   5 (Zafakturowane) ← fs_at != null
 *
 * Demotes statusy gdy brak uzasadnienia (np. status=3 ale brak POL):
 *   - <3 if no POL and not fs_at and not pod
 *   - Jednocześnie wyzerowuje pol_at/pol_by / pod_at/pod_by gdy brak plików
 *
 * Usage:
 *   bin/cake recalculate_speed_statuses          — apply
 *   bin/cake recalculate_speed_statuses --dry    — preview only
 */
class RecalculateSpeedStatusesCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Przelicz nordlogis_status zleceń wg plików POL/POD + fs_at')
            ->addOption('dry', [
                'help'    => 'Dry run — pokaż statystyki, nic nie zapisuj',
                'boolean' => true,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry');
        $SpeedOrders = TableRegistry::getTableLocator()->get('SpeedOrders');
        $Attachments = TableRegistry::getTableLocator()->get('SpeedOrderAttachments');

        // Załaduj jednym zapytaniem mapę: order_id => ['pol' => bool, 'pod' => bool]
        $io->out('<info>Czytam załączniki…</info>');
        $polPodMap = [];
        $rows = $Attachments->find()
            ->select(['SpeedOrderAttachments.speed_order_id', 'SpeedOrderAttachmentLabels.slug'])
            ->contain(['SpeedOrderAttachmentLabels'])
            ->disableHydration()
            ->all();
        foreach ($rows as $r) {
            $oid  = (int)$r['speed_order_id'];
            $slug = (string)($r['speed_order_attachment_label']['slug'] ?? '');
            if (str_starts_with($slug, 'pol_')) $polPodMap[$oid]['pol'] = true;
            elseif (str_starts_with($slug, 'pod_')) $polPodMap[$oid]['pod'] = true;
        }
        $io->out(sprintf('<info>Załączników: %d unique orders</info>', count($polPodMap)));

        $io->out('<info>Czytam zlecenia…</info>');
        $orders = $SpeedOrders->find()
            ->select(['id', 'nordlogis_status', 'pol_at', 'pol_by', 'pod_at', 'pod_by', 'fk_at', 'fs_at', 'is_complete'])
            ->disableHydration()
            ->all();

        $stats = [
            'total'    => 0,
            'unchanged'=> 0,
            'demoted'  => 0,
            'promoted' => 0,
            'cleared_pol_at' => 0,
            'cleared_pod_at' => 0,
            'completed_changed' => 0,
        ];

        $batchUpdates = [];
        foreach ($orders as $row) {
            $stats['total']++;
            $oid    = (int)$row['id'];
            $hasPol = !empty($polPodMap[$oid]['pol']);
            $hasPod = !empty($polPodMap[$oid]['pod']);

            $oldNs    = (int)($row['nordlogis_status'] ?? 1);
            $oldDone  = (bool)($row['is_complete'] ?? false);
            $oldPolAt = $row['pol_at'] !== null ? (string)$row['pol_at'] : null;
            $oldPodAt = $row['pod_at'] !== null ? (string)$row['pod_at'] : null;

            // Nowy status: max z reguł
            $newNs = 1;
            if ($oldNs >= 2) $newNs = 2; // 'Zaplanowane' — manualne, nie demote tego
            if ($hasPol)             $newNs = max($newNs, 3);
            if ($hasPod)             $newNs = max($newNs, 4);
            if (!empty($row['fs_at'])) $newNs = max($newNs, 5);

            $newDone = $hasPol && $hasPod && !empty($row['fk_at']) && !empty($row['fs_at']);

            $changes = [];
            if ($newNs !== $oldNs) {
                $changes['nordlogis_status'] = $newNs;
            }
            if ($newDone !== $oldDone) {
                $changes['is_complete'] = $newDone;
                $stats['completed_changed']++;
            }
            // Wyzeruj pol_at/pol_by gdy brak plików
            if (!$hasPol && $oldPolAt !== null) {
                $changes['pol_at'] = null;
                $changes['pol_by'] = null;
                $stats['cleared_pol_at']++;
            }
            if (!$hasPod && $oldPodAt !== null) {
                $changes['pod_at'] = null;
                $changes['pod_by'] = null;
                $stats['cleared_pod_at']++;
            }

            if ($newNs > $oldNs) $stats['promoted']++;
            elseif ($newNs < $oldNs) $stats['demoted']++;
            else $stats['unchanged']++;

            if (!empty($changes)) {
                $batchUpdates[$oid] = $changes;
            }
        }

        $io->out('');
        $io->out('<success>Podsumowanie:</success>');
        $io->out("  Total orders:        {$stats['total']}");
        $io->out("  Status unchanged:    {$stats['unchanged']}");
        $io->out("  Status promoted:     {$stats['promoted']}");
        $io->out("  Status demoted:      <warning>{$stats['demoted']}</warning>");
        $io->out("  is_complete changed: {$stats['completed_changed']}");
        $io->out("  pol_at cleared:      {$stats['cleared_pol_at']}");
        $io->out("  pod_at cleared:      {$stats['cleared_pod_at']}");
        $io->out('');
        $io->out(sprintf('Updates to apply:    %d zleceń', count($batchUpdates)));

        if ($dry) {
            $io->out('<warning>Dry run — nie zapisuję.</warning>');
            return self::CODE_SUCCESS;
        }

        if (empty($batchUpdates)) {
            $io->success('Nic do zmiany.');
            return self::CODE_SUCCESS;
        }

        $io->out('<info>Zapisuję…</info>');
        $written = 0;
        foreach ($batchUpdates as $oid => $changes) {
            try {
                $SpeedOrders->updateAll($changes, ['id' => $oid]);
                $written++;
            } catch (\Throwable $e) {
                $io->warning("Błąd zapisu order #{$oid}: " . $e->getMessage());
            }
        }
        $io->success("Zapisano: {$written} / " . count($batchUpdates));

        return self::CODE_SUCCESS;
    }
}

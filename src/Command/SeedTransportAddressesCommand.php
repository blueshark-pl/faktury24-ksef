<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * ETL: wyciąga unikalne adresy załadunku/rozładunku z speed_orders
 * i wstawia do transport_addresses (UNIQ_ADDR po name+city+postal_code+country).
 *
 *   bin/cake seed_transport_addresses          — uruchom
 *   bin/cake seed_transport_addresses --dry    — pokaż liczby, nie zapisuj
 */
class SeedTransportAddressesCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('ETL: dedupe adresów z speed_orders → transport_addresses')
            ->addOption('dry', [
                'help'    => 'Dry run — pokaż statystyki, nic nie zapisuj',
                'boolean' => true,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry');
        $TransportAddresses = TableRegistry::getTableLocator()->get('TransportAddresses');
        $SpeedOrders        = TableRegistry::getTableLocator()->get('SpeedOrders');

        $io->out('<info>Czytam zlecenia ze speed_orders…</info>');

        $rows = $SpeedOrders->find()
            ->select([
                'place_from_name', 'load_city', 'load_postal_code', 'load_country', 'place_from_country',
                'place_to_name',   'unload_city', 'unload_country',  'place_to_country',
            ])
            ->disableHydration()
            ->all();

        $bucket = []; // key → ['data' => [...], 'type' => 'loading|unloading|both', 'count' => N]

        // UWAGA: schema speed_orders ma "śmieciową" semantykę:
        //   place_from_name = kod kraju (PL/DE/...) — NIE nazwa miejsca
        //   place_to_name   = kod pocztowy (59320, 41-250) — NIE nazwa miejsca
        // Faktyczne adresy = load_city / unload_city + load_postal_code / place_to_name (jako postal).
        // Używamy miasta jako 'name' (fallback) bo brak prawdziwej nazwy miejsca.
        foreach ($rows as $r) {
            $loadCountry   = trim((string)(($r['load_country'] ?? '') ?: ($r['place_from_country'] ?? '')));
            $unloadCountry = trim((string)(($r['unload_country'] ?? '') ?: ($r['place_to_country'] ?? '')));

            // place_to_name często zawiera kod pocztowy — używamy go jako postal_code dla unload
            $unloadPostal = (string)($r['place_to_name'] ?? '');
            // jeśli to nie wygląda na postal — ignorujemy
            if (!preg_match('/^\d{2,5}[-\s]?\d{0,4}$/', $unloadPostal)) {
                $unloadPostal = '';
            }

            // ── LOADING side ──
            $this->aggregate($bucket, 'loading',
                $r['load_city']        ?? '', // name = city (brak prawdziwej nazwy miejsca w schemacie)
                $r['load_city']        ?? '',
                $r['load_postal_code'] ?? '',
                $loadCountry
            );

            // ── UNLOADING side ──
            $this->aggregate($bucket, 'unloading',
                $r['unload_city']      ?? '', // name = city
                $r['unload_city']      ?? '',
                $unloadPostal,
                $unloadCountry
            );
        }

        $total = count($bucket);
        $byType = ['loading' => 0, 'unloading' => 0, 'both' => 0];
        foreach ($bucket as $b) $byType[$b['type']]++;

        $io->out("<success>Znaleziono {$total} unikalnych adresów.</success>");
        $io->out("  loading: {$byType['loading']}");
        $io->out("  unloading: {$byType['unloading']}");
        $io->out("  both: {$byType['both']}");

        if ($dry) {
            $io->out('<warning>Dry run — nie zapisuję.</warning>');
            return self::CODE_SUCCESS;
        }

        $io->out('<info>Wstawiam do transport_addresses…</info>');

        $inserted = 0;
        $updated  = 0;
        foreach ($bucket as $b) {
            $conds = [
                'name'    => $b['data']['name'],
                'country' => $b['data']['country'],
            ];
            $conds[empty($b['data']['city'])        ? 'city IS'        : 'city']        = $b['data']['city']        ?: null;
            $conds[empty($b['data']['postal_code']) ? 'postal_code IS' : 'postal_code'] = $b['data']['postal_code'] ?: null;
            $existing = $TransportAddresses->find()->where($conds)->first();

            if ($existing) {
                $existing->times_used = $b['count'];
                $existing->address_type = $this->mergeType($existing->address_type ?? 'both', $b['type']);
                $TransportAddresses->save($existing);
                $updated++;
            } else {
                $entity = $TransportAddresses->newEntity([
                    'id'           => Text::uuid(),
                    'name'         => $b['data']['name'],
                    'address'      => null,
                    'city'         => $b['data']['city'] ?: null,
                    'postal_code'  => $b['data']['postal_code'] ?: null,
                    'country'      => $b['data']['country'],
                    'address_type' => $b['type'],
                    'times_used'   => $b['count'],
                    'is_active'    => true,
                ]);
                $entity->set('id', Text::uuid(), ['guard' => false]);
                if ($TransportAddresses->save($entity)) {
                    $inserted++;
                } else {
                    $io->warning('Błąd zapisu: ' . json_encode($entity->getErrors()));
                }
            }
        }

        $io->success("Zakończono — wstawiono: {$inserted}, zaktualizowano: {$updated}");
        return self::CODE_SUCCESS;
    }

    /**
     * Dodaj rekord do bucketa zdeduplikowanego po normalized key.
     */
    private function aggregate(array &$bucket, string $type, string $name, string $city, string $postal, string $country): void
    {
        $name    = $this->normalize($name);
        $city    = $this->normalize($city);
        $postal  = trim($postal);
        $country = strtoupper(trim($country)) ?: 'PL';
        if ($name === '' && $city === '') {
            return; // skip empty rows
        }
        if ($name === '') {
            $name = $city; // fallback
        }

        $key = mb_strtolower($name . '|' . $city . '|' . $postal . '|' . $country);
        if (!isset($bucket[$key])) {
            $bucket[$key] = [
                'data'  => ['name' => $name, 'city' => $city, 'postal_code' => $postal, 'country' => $country],
                'type'  => $type,
                'count' => 1,
            ];
        } else {
            $bucket[$key]['count']++;
            $bucket[$key]['type'] = $this->mergeType($bucket[$key]['type'], $type);
        }
    }

    private function mergeType(string $existing, string $incoming): string
    {
        if ($existing === $incoming) return $existing;
        return 'both';
    }

    private function normalize(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s) ?? '';
        return $s;
    }
}

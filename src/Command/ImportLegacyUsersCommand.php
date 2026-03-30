<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Http\Client;
use Cake\Utility\Text;

/**
 * Jednorazowy import użytkowników i firm ze starego systemu (archiwum.faktury24.com).
 *
 * Pobiera dane z endpointu get_all_users starego systemu,
 * tworzy firmy (companies) i użytkowników (users) w nowym systemie.
 *
 * Uruchomienie:
 *   bin/cake import_legacy_users
 *
 * Opcje:
 *   --dry-run   Tylko podgląd danych, bez zapisu do bazy
 *   --url       Nadpisanie URL starego systemu
 */
class ImportLegacyUsersCommand extends Command
{
    private const DEFAULT_URL = 'https://archiwum.faktury24.com/ajax/get_all_users';
    private const TOKEN = 'f24sync-8a3Kv9Xm2pLw7QzR';

    public static function defaultName(): string
    {
        return 'import_legacy_users';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Import użytkowników i firm ze starego systemu faktury24 (archiwum).')
            ->addOption('dry-run', [
                'help' => 'Tylko podgląd — nie zapisuje do bazy.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('url', [
                'help' => 'URL endpointu get_all_users (domyślnie archiwum.faktury24.com).',
                'default' => self::DEFAULT_URL,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $url = (string)$args->getOption('url');

        $io->info('Import użytkowników ze starego systemu faktury24');
        $io->hr();

        // 1. Pobierz dane z API
        $io->out('Pobieranie danych z: ' . $url);
        $client = new Client();
        $response = $client->get($url, ['token' => self::TOKEN]);

        if (!$response->isOk()) {
            $io->error('Błąd HTTP: ' . $response->getStatusCode());
            return self::CODE_ERROR;
        }

        $payload = $response->getJson();
        if (empty($payload) || ($payload['status'] ?? 0) !== 200) {
            $io->error('Nieprawidłowa odpowiedź z API: ' . ($payload['message'] ?? 'brak danych'));
            return self::CODE_ERROR;
        }

        $records = $payload['data'] ?? [];
        $io->success(sprintf('Pobrano %d rekordów.', count($records)));

        if (empty($records)) {
            $io->warning('Brak danych do importu.');
            return self::CODE_SUCCESS;
        }

        // 2. Grupuj: firmy (po company_uuid) i użytkownicy
        $companies = [];
        $users = [];

        foreach ($records as $rec) {
            $email = trim($rec['ud_email'] ?? '');
            if ($email === '') {
                continue; // pomijamy użytkowników bez emaila
            }

            $companyUuid = $rec['company_uuid'] ?? null;

            // Zbierz firmę (deduplikacja po UUID)
            if ($companyUuid && !isset($companies[$companyUuid])) {
                $nip = preg_replace('/[^\d]/', '', $rec['company_nip'] ?? '');
                $companyName = trim($rec['company_name'] ?? '');
                if ($companyName === '') {
                    $companyName = trim($rec['company_fna'] ?? '');
                }

                $companies[$companyUuid] = [
                    'old_uuid' => $companyUuid,
                    'name' => $companyName,
                    'nip' => $nip ?: null,
                    'regon' => trim($rec['company_regon'] ?? '') ?: null,
                    'street' => trim($rec['company_street'] ?? '') ?: null,
                    'postal_code' => trim($rec['company_postal_code'] ?? '') ?: null,
                    'city' => trim($rec['company_city'] ?? '') ?: null,
                    'country' => trim($rec['company_country'] ?? '') ?: 'PL',
                    'bank_account' => trim($rec['company_bank_account'] ?? '') ?: null,
                    'bank_name' => trim($rec['company_bank_name'] ?? '') ?: null,
                    'vat_payer' => ($rec['company_vat_payer'] ?? 'f') === 't',
                    'is_active' => true,
                    'profile_mode' => 'business',
                ];
            }

            // Zbierz użytkownika (deduplikacja po emailu)
            $emailLower = mb_strtolower($email);
            if (!isset($users[$emailLower])) {
                $nameParts = $this->splitName(trim($rec['ud_name'] ?? ''));
                $users[$emailLower] = [
                    'old_uuid' => $rec['us_uuid'] ?? null,
                    'email' => $email,
                    'first_name' => $nameParts['first'],
                    'last_name' => $nameParts['last'],
                    'company_old_uuid' => $companyUuid,
                ];
            }
        }

        $io->out(sprintf('Znaleziono %d unikalnych firm i %d unikalnych użytkowników.', count($companies), count($users)));
        $io->hr();

        if ($dryRun) {
            $io->warning('=== DRY RUN — bez zapisu do bazy ===');
            $this->printPreview($io, $companies, $users);
            return self::CODE_SUCCESS;
        }

        // 3. Zapisz firmy
        /** @var \App\Model\Table\CompaniesTable $CompaniesTable */
        $CompaniesTable = $this->fetchTable('Companies');
        /** @var \App\Model\Table\UsersTable $UsersTable */
        $UsersTable = $this->fetchTable('Users');

        $companyIdMap = []; // old_uuid => new_id
        $companiesCreated = 0;
        $companiesSkipped = 0;

        foreach ($companies as $oldUuid => $companyData) {
            // Sprawdź czy firma o tym NIP już istnieje
            $existing = null;
            if (!empty($companyData['nip'])) {
                $existing = $CompaniesTable->find()
                    ->where(['nip' => $companyData['nip']])
                    ->first();
            }

            if ($existing) {
                $companyIdMap[$oldUuid] = $existing->id;
                $companiesSkipped++;
                $io->verbose(sprintf('  [SKIP] Firma "%s" (NIP: %s) już istnieje (id: %s)', $companyData['name'], $companyData['nip'], $existing->id));
                continue;
            }

            unset($companyData['old_uuid']);
            $entity = $CompaniesTable->newEntity($companyData);
            if ($CompaniesTable->save($entity)) {
                $companyIdMap[$oldUuid] = $entity->id;
                $companiesCreated++;
                $io->verbose(sprintf('  [OK] Firma "%s" (NIP: %s) → id: %s', $companyData['name'], $companyData['nip'] ?? '-', $entity->id));
            } else {
                $io->warning(sprintf('  [ERR] Nie udało się zapisać firmy "%s": %s', $companyData['name'], json_encode($entity->getErrors())));
            }
        }

        $io->out(sprintf('Firmy: utworzono %d, pominięto %d (już istniały).', $companiesCreated, $companiesSkipped));

        // 4. Zapisz użytkowników
        $usersCreated = 0;
        $usersSkipped = 0;

        foreach ($users as $emailLower => $userData) {
            // Sprawdź czy user o tym emailu już istnieje
            $existing = $UsersTable->find()
                ->where(['email' => $userData['email']])
                ->first();

            if ($existing) {
                // Jeśli user istnieje, ale nie ma company_id — podepnij firmę
                if (empty($existing->company_id) && !empty($userData['company_old_uuid'])) {
                    $newCompanyId = $companyIdMap[$userData['company_old_uuid']] ?? null;
                    if ($newCompanyId) {
                        $existing->company_id = $newCompanyId;
                        $UsersTable->save($existing);
                        $io->verbose(sprintf('  [UPD] User "%s" — podpięto firmę %s', $userData['email'], $newCompanyId));
                    }
                }
                $usersSkipped++;
                continue;
            }

            $companyId = null;
            if (!empty($userData['company_old_uuid'])) {
                $companyId = $companyIdMap[$userData['company_old_uuid']] ?? null;
            }

            $newUserData = [
                'email' => $userData['email'],
                'username' => $userData['email'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'password' => Text::uuid(), // losowe hasło — user musi zresetować
                'active' => true,
                'company_id' => $companyId,
                'role' => 'user',
            ];

            $entity = $UsersTable->newEntity($newUserData, ['validate' => false]);
            if ($UsersTable->save($entity)) {
                $usersCreated++;
                $io->verbose(sprintf('  [OK] User "%s" → id: %s', $userData['email'], $entity->id));
            } else {
                $io->warning(sprintf('  [ERR] Nie udało się zapisać usera "%s": %s', $userData['email'], json_encode($entity->getErrors())));
            }
        }

        $io->out(sprintf('Użytkownicy: utworzono %d, pominięto %d (już istnieli).', $usersCreated, $usersSkipped));
        $io->hr();
        $io->success('Import zakończony.');

        return self::CODE_SUCCESS;
    }

    /**
     * Rozdziel "imię nazwisko" na first_name / last_name.
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', $fullName, 2);

        return [
            'first' => $parts[0] ?? '',
            'last' => $parts[1] ?? '',
        ];
    }

    /**
     * Podgląd danych (dry-run).
     */
    private function printPreview(ConsoleIo $io, array $companies, array $users): void
    {
        $io->out('');
        $io->out('=== FIRMY ===');
        $i = 0;
        foreach ($companies as $c) {
            $i++;
            $io->out(sprintf(
                '  %d. %s | NIP: %s | %s, %s %s',
                $i,
                $c['name'],
                $c['nip'] ?? '-',
                $c['street'] ?? '-',
                $c['postal_code'] ?? '-',
                $c['city'] ?? '-'
            ));
            if ($i >= 20) {
                $io->out(sprintf('  ... i %d więcej', count($companies) - 20));
                break;
            }
        }

        $io->out('');
        $io->out('=== UŻYTKOWNICY ===');
        $i = 0;
        foreach ($users as $u) {
            $i++;
            $io->out(sprintf(
                '  %d. %s %s <%s> → firma: %s',
                $i,
                $u['first_name'],
                $u['last_name'],
                $u['email'],
                $u['company_old_uuid'] ?? 'brak'
            ));
            if ($i >= 30) {
                $io->out(sprintf('  ... i %d więcej', count($users) - 30));
                break;
            }
        }
    }
}

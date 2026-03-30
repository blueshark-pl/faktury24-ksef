<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Utility\Text;

/**
 * Jednorazowy import użytkowników i firm ze starego systemu.
 *
 * Wywołanie z przeglądarki:
 *   /import-legacy-users?key=SCHEDULER_KEY
 *   /import-legacy-users?key=SCHEDULER_KEY&dry_run=1
 */
class ImportController extends AppController
{
    private const LEGACY_URL = 'https://archiwum.faktury24.com/ajax/get_all_users';
    private const LEGACY_TOKEN = 'f24sync-8a3Kv9Xm2pLw7QzR';

    public function importLegacyUsers(): Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;

        // 1. Autoryzacja kluczem
        $schedulerKey = (string)(Configure::read('App.ksefSchedulerKey') ?? '');
        $providedKey  = (string)$this->request->getQuery('key', '');

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            if ($schedulerKey === '' || !hash_equals($schedulerKey, $providedKey)) {
                return $this->jsonResponse(403, 'Brak autoryzacji. Podaj ?key=SCHEDULER_KEY');
            }
        }

        $dryRun = (bool)$this->request->getQuery('dry_run', false);
        $log = [];
        $log[] = 'Import użytkowników ze starego systemu faktury24';
        $log[] = $dryRun ? '=== DRY RUN ===' : '=== ZAPIS DO BAZY ===';

        // 2. Pobierz dane z API starego systemu
        try {
            $client = new Client(['timeout' => 60]);
            $response = $client->get(self::LEGACY_URL, ['token' => self::LEGACY_TOKEN]);
        } catch (\Exception $e) {
            return $this->jsonResponse(500, 'Błąd połączenia z archiwum: ' . $e->getMessage());
        }

        if (!$response->isOk()) {
            return $this->jsonResponse(500, 'Błąd HTTP z archiwum: ' . $response->getStatusCode());
        }

        $payload = $response->getJson();
        if (empty($payload) || ($payload['status'] ?? 0) !== 200) {
            return $this->jsonResponse(500, 'Nieprawidłowa odpowiedź API: ' . ($payload['message'] ?? 'brak'));
        }

        $records = $payload['data'] ?? [];
        $log[] = sprintf('Pobrano %d rekordów z archiwum.', count($records));

        if (empty($records)) {
            return $this->jsonResponse(200, 'Brak danych do importu.', $log);
        }

        // 3. Grupuj firmy i użytkowników
        $companies = [];
        $users = [];

        foreach ($records as $rec) {
            $email = trim($rec['ud_email'] ?? '');
            if ($email === '') {
                continue;
            }

            $companyUuid = $rec['company_uuid'] ?? null;

            if ($companyUuid && !isset($companies[$companyUuid])) {
                $nip = preg_replace('/[^\d]/', '', $rec['company_nip'] ?? '');
                $companyName = trim($rec['company_name'] ?? '');
                if ($companyName === '') {
                    $companyName = trim($rec['company_fna'] ?? '');
                }

                $companies[$companyUuid] = [
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

            $emailLower = mb_strtolower($email);
            if (!isset($users[$emailLower])) {
                $nameParts = $this->splitName(trim($rec['ud_name'] ?? ''));
                $users[$emailLower] = [
                    'email' => $email,
                    'first_name' => $nameParts['first'],
                    'last_name' => $nameParts['last'],
                    'company_old_uuid' => $companyUuid,
                ];
            }
        }

        $log[] = sprintf('Unikalnych firm: %d, użytkowników: %d.', count($companies), count($users));

        if ($dryRun) {
            $preview = $this->buildPreview($companies, $users);
            return $this->jsonResponse(200, 'Dry run zakończony.', $log, $preview);
        }

        // 4. Zapisz do bazy
        /** @var \App\Model\Table\CompaniesTable $CompaniesTable */
        $CompaniesTable = $this->fetchTable('Companies');
        /** @var \App\Model\Table\UsersTable $UsersTable */
        $UsersTable = $this->fetchTable('Users');

        $companyIdMap = [];
        $stats = ['companies_created' => 0, 'companies_skipped' => 0, 'users_created' => 0, 'users_skipped' => 0, 'users_updated' => 0];

        // 4a. Firmy
        foreach ($companies as $oldUuid => $data) {
            $existing = null;
            if (!empty($data['nip'])) {
                $existing = $CompaniesTable->find()
                    ->where(['nip' => $data['nip']])
                    ->first();
            }

            if ($existing) {
                $companyIdMap[$oldUuid] = $existing->id;
                $stats['companies_skipped']++;
                continue;
            }

            $entity = $CompaniesTable->newEntity($data);
            if ($CompaniesTable->save($entity)) {
                $companyIdMap[$oldUuid] = $entity->id;
                $stats['companies_created']++;
            } else {
                $log[] = sprintf('[ERR] Firma "%s" (NIP: %s): %s', $data['name'], $data['nip'] ?? '-', json_encode($entity->getErrors()));
            }
        }

        // 4b. Użytkownicy
        foreach ($users as $emailLower => $userData) {
            $existing = $UsersTable->find()
                ->where(['email' => $userData['email']])
                ->first();

            if ($existing) {
                if (empty($existing->company_id) && !empty($userData['company_old_uuid'])) {
                    $newCompanyId = $companyIdMap[$userData['company_old_uuid']] ?? null;
                    if ($newCompanyId) {
                        $existing->company_id = $newCompanyId;
                        $UsersTable->save($existing);
                        $stats['users_updated']++;
                    }
                }
                $stats['users_skipped']++;
                continue;
            }

            $companyId = null;
            if (!empty($userData['company_old_uuid'])) {
                $companyId = $companyIdMap[$userData['company_old_uuid']] ?? null;
            }

            $entity = $UsersTable->newEntity([
                'email' => $userData['email'],
                'username' => $userData['email'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'password' => Text::uuid(),
                'active' => true,
                'company_id' => $companyId,
                'role' => 'user',
            ], ['validate' => false]);

            if ($UsersTable->save($entity)) {
                $stats['users_created']++;
            } else {
                $log[] = sprintf('[ERR] User "%s": %s', $userData['email'], json_encode($entity->getErrors()));
            }
        }

        $log[] = sprintf('Firmy: %d utworzono, %d pominięto.', $stats['companies_created'], $stats['companies_skipped']);
        $log[] = sprintf('Użytkownicy: %d utworzono, %d pominięto, %d zaktualizowano.', $stats['users_created'], $stats['users_skipped'], $stats['users_updated']);
        $log[] = 'Import zakończony.';

        Log::info('Legacy import: ' . json_encode($stats), ['import']);

        return $this->jsonResponse(200, 'Import zakończony.', $log, $stats);
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', $fullName, 2);
        return [
            'first' => $parts[0] ?? '',
            'last' => $parts[1] ?? '',
        ];
    }

    private function buildPreview(array $companies, array $users): array
    {
        $companyList = [];
        $i = 0;
        foreach ($companies as $c) {
            $companyList[] = sprintf('%s | NIP: %s | %s, %s %s', $c['name'], $c['nip'] ?? '-', $c['street'] ?? '-', $c['postal_code'] ?? '-', $c['city'] ?? '-');
            if (++$i >= 30) {
                $companyList[] = sprintf('... i %d więcej', count($companies) - 30);
                break;
            }
        }

        $userList = [];
        $i = 0;
        foreach ($users as $u) {
            $userList[] = sprintf('%s %s <%s> → firma: %s', $u['first_name'], $u['last_name'], $u['email'], $u['company_old_uuid'] ?? 'brak');
            if (++$i >= 30) {
                $userList[] = sprintf('... i %d więcej', count($users) - 30);
                break;
            }
        }

        return ['companies' => $companyList, 'users' => $userList];
    }

    private function jsonResponse(int $status, string $message, array $log = [], $data = null): Response
    {
        $body = ['status' => $status, 'message' => $message, 'log' => $log];
        if ($data !== null) {
            $body['data'] = $data;
        }

        return $this->response
            ->withStatus($status === 200 ? 200 : $status)
            ->withType('application/json')
            ->withStringBody(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

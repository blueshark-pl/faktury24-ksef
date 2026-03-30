<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Utility\Text;
use GusApi\GusApi;
use GusApi\Exception\InvalidUserKeyException;
use GusApi\Exception\NotFoundException as GusNotFoundException;

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

        // Import może trwać kilka minut — wyłącz limity
        set_time_limit(0);
        ignore_user_abort(true);

        // 1. Autoryzacja kluczem
        $schedulerKey = 'test';
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

        // GUS API — login raz, użyj wielokrotnie
        $gus = null;
        try {
            $apiKey = (string)Configure::read('Gus.apiKey', '');
            if ($apiKey !== '') {
                $isDev = (bool)Configure::read('Gus.dev', false);
                $gus = $isDev ? new GusApi($apiKey, 'dev') : new GusApi($apiKey);
                $gus->login();
                $log[] = 'GUS API: zalogowano.';
            } else {
                $log[] = 'GUS API: brak klucza — pominięto lookup.';
            }
        } catch (\Throwable $e) {
            $log[] = 'GUS API: błąd logowania — ' . $e->getMessage();
            $gus = null;
        }

        $stats['gus_filled'] = 0;

        // 4a. Firmy
        foreach ($companies as $oldUuid => $data) {
            // Sanityzacja danych
            $data = $this->sanitizeCompanyData($data);

            // Pomijaj firmy bez nazwy i bez ważnego NIP
            if (empty($data['name']) && empty($data['nip'])) {
                $log[] = sprintf('[SKIP] Firma bez nazwy i NIP (old_uuid: %s)', $oldUuid);
                continue;
            }

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

            // GUS fallback: pusta nazwa lub brakujące dane → pobierz z GUS
            if ($gus && !empty($data['nip']) && (empty($data['name']) || empty($data['street']) || empty($data['city']))) {
                $gusData = $this->fetchFromGus($gus, $data['nip']);
                if ($gusData) {
                    if (empty($data['name']))        $data['name']        = $gusData['name'];
                    if (empty($data['street']))       $data['street']       = $gusData['street'];
                    if (empty($data['postal_code']))  $data['postal_code']  = $gusData['postal_code'];
                    if (empty($data['city']))         $data['city']         = $gusData['city'];
                    if (empty($data['regon']))        $data['regon']        = $gusData['regon'] ?? null;
                    $stats['gus_filled']++;
                }
            }

            // Jeśli nadal brak nazwy — użyj NIP jako fallback
            if (empty($data['name'])) {
                $data['name'] = 'Firma NIP ' . ($data['nip'] ?? 'brak');
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

        $log[] = sprintf('Firmy: %d utworzono, %d pominięto, %d uzupełniono z GUS.', $stats['companies_created'], $stats['companies_skipped'], $stats['gus_filled']);
        $log[] = sprintf('Użytkownicy: %d utworzono, %d pominięto, %d zaktualizowano.', $stats['users_created'], $stats['users_skipped'], $stats['users_updated']);
        $log[] = 'Import zakończony.';

        Log::info('Legacy import: ' . json_encode($stats), ['import']);

        return $this->jsonResponse(200, 'Import zakończony.', $log, $stats);
    }

    private function sanitizeCompanyData(array $data): array
    {
        // NIP: tylko cyfry, dokładnie 10
        if (!empty($data['nip'])) {
            $nip = preg_replace('/[^\d]/', '', $data['nip']);
            $data['nip'] = (strlen($nip) === 10) ? $nip : null;
        }

        // REGON: max 14 cyfr
        if (!empty($data['regon'])) {
            $regon = preg_replace('/[^\d]/', '', $data['regon']);
            $data['regon'] = (strlen($regon) <= 14) ? $regon : substr($regon, 0, 14);
        }

        // postal_code: max 6 znaków (XX-XXX)
        if (!empty($data['postal_code'])) {
            $pc = trim($data['postal_code']);
            // Wyciągnij wzorzec XX-XXX
            if (preg_match('/(\d{2}-\d{3})/', $pc, $m)) {
                $data['postal_code'] = $m[1];
            } elseif (preg_match('/^(\d{2})(\d{3})/', $pc, $m)) {
                $data['postal_code'] = $m[1] . '-' . $m[2];
            } else {
                $data['postal_code'] = mb_substr($pc, 0, 6);
            }
        }

        // bank_account: usuń spacje/myślniki, max 34 znaków
        if (!empty($data['bank_account'])) {
            $ba = preg_replace('/[\s\-]/', '', $data['bank_account']);
            $data['bank_account'] = (strlen($ba) <= 34) ? $ba : substr($ba, 0, 34);
        }

        // name: trim, usuń znaki nowej linii
        if (!empty($data['name'])) {
            $data['name'] = trim(preg_replace('/[\r\n]+/', ' ', $data['name']));
        }

        return $data;
    }

    private function fetchFromGus(?GusApi $gus, string $nip): ?array
    {
        if (!$gus || strlen($nip) !== 10) {
            return null;
        }

        try {
            $reports = $gus->getByNip($nip);
            if (empty($reports)) {
                return null;
            }
            $r = $reports[0];

            $zip = (string)$r->getZipCode();
            if (preg_match('/^(\d{2})(\d{3})$/', $zip, $m)) {
                $zip = $m[1] . '-' . $m[2];
            }

            return [
                'name'        => trim((string)$r->getName()),
                'street'      => trim(implode(' ', array_filter([(string)$r->getStreet(), (string)$r->getPropertyNumber()]))),
                'postal_code' => $zip,
                'city'        => trim((string)$r->getCity()),
                'regon'       => preg_replace('/[^\d]/', '', (string)$r->getRegon()),
            ];
        } catch (\Throwable) {
            return null;
        }
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

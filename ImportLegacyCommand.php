<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Http\Client;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Text;

/**
 * Import data from the old Faktury24 system via the /api_export endpoint.
 *
 * Usage:
 *   # Auto-match all companies by NIP and import everything:
 *   bin/cake import_legacy --auto-match
 *
 *   # Auto-match + dry run:
 *   bin/cake import_legacy --auto-match --dry-run
 *
 *   # Import single company (manual UUIDs):
 *   bin/cake import_legacy --company-id=<UUID> --bu-uuid=<UUID>
 *
 *   # Import only contractors for a single company:
 *   bin/cake import_legacy --company-id=<UUID> --bu-uuid=<UUID> --target=contractors
 *
 * Company matching strategy (--auto-match):
 *   1. Fetches all businesses from old system via /api_export/businesses
 *   2. For each old business with a NIP → finds matching company in new system by NIP
 *   3. Imports contractors, products and invoices for each matched pair
 *   4. Unmatched businesses are listed in the summary
 */
class ImportLegacyCommand extends Command
{
    use LocatorAwareTrait;

    /**
     * Shared secret – must match $EXPORT_SECRET in old system's api_export.php
     */
    private const EXPORT_KEY = 'f24-export-2026-xK9m3Qw7Lp';

    /**
     * Mapping: old-system VAT short code → new-system vat_id
     * Will be populated dynamically from the `vats` table.
     * @var array<string, string>
     */
    private array $vatMap = [];

    /**
     * Mapping: old-system unit name → new-system unit_id
     * Will be populated dynamically from the `units` table.
     * @var array<string, int>
     */
    private array $unitMap = [];

    public static function defaultName(): string
    {
        return 'import_legacy';
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Import contractors, products and invoices from the old Faktury24 system.')
            ->addOption('auto-match', [
                'help' => 'Auto-match ALL companies by NIP between old and new system, then import data for each match.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('company-id', [
                'help' => 'UUID of the company in the NEW system to import into. (ignored with --auto-match)',
            ])
            ->addOption('bu-uuid', [
                'help' => 'UUID of the business (bu_uuid) in the OLD system to export from. (ignored with --auto-match)',
            ])
            ->addOption('target', [
                'help' => 'What to import: all, contractors, products, invoices',
                'default' => 'all',
                'choices' => ['all', 'contractors', 'products', 'invoices'],
            ])
            ->addOption('base-url', [
                'help' => 'Base URL of the old Faktury24 system.',
                'default' => 'https://faktury.partnersc.com',
            ])
            ->addOption('dry-run', [
                'help' => 'If set, only fetches data and reports counts without saving.',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $autoMatch = (bool)$args->getOption('auto-match');
        $target    = $args->getOption('target');
        $baseUrl   = rtrim($args->getOption('base-url'), '/');
        $dryRun    = (bool)$args->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN — no data will be saved.');
        }

        // Preload reference data maps
        $this->loadVatMap($io);
        $this->loadUnitMap($io);

        $http = new Client([
            'headers' => ['X-Export-Key' => self::EXPORT_KEY],
            'timeout' => 60,
        ]);

        if ($autoMatch) {
            return $this->executeAutoMatch($http, $baseUrl, $target, $dryRun, $io);
        }

        // ── Manual mode: requires both --company-id and --bu-uuid ──
        $companyId = $args->getOption('company-id');
        $buUuid    = $args->getOption('bu-uuid');

        if (!$companyId || !$buUuid) {
            $io->error('Manual mode requires both --company-id and --bu-uuid. Or use --auto-match.');
            return self::CODE_ERROR;
        }

        $Companies = $this->fetchTable('Companies');
        $company = $Companies->find()->where(['id' => $companyId])->first();
        if (!$company) {
            $io->error("Company with id={$companyId} not found in the new system.");
            return self::CODE_ERROR;
        }

        $io->info("Importing from old system (bu_uuid={$buUuid}) into company \"{$company->name}\" (id={$companyId})");

        $this->importAllTargets($http, $baseUrl, $buUuid, $companyId, $target, $dryRun, $io);

        $io->hr();
        $io->success('Import finished.');
        return self::CODE_SUCCESS;
    }

    // ─── AUTO-MATCH BY NIP ────────────────────────────────────────────

    /**
     * Fetch all businesses from old system, match each to new system by NIP,
     * and import data for every matched pair.
     */
    private function executeAutoMatch(Client $http, string $baseUrl, string $target, bool $dryRun, ConsoleIo $io): int
    {
        $io->info('=== AUTO-MATCH: matching companies by NIP ===');
        $io->hr();

        // 1. Fetch all businesses from old system
        $oldBusinesses = $this->fetchAllOldBusinesses($http, $baseUrl, $io);
        if (empty($oldBusinesses)) {
            $io->error('No businesses found in the old system.');
            return self::CODE_ERROR;
        }
        $io->info('Old system: ' . count($oldBusinesses) . ' businesses found.');

        // 2. Load all companies from new system, indexed by NIP
        $Companies = $this->fetchTable('Companies');
        $newCompanies = $Companies->find()
            ->where(['nip IS NOT' => null, 'nip !=' => ''])
            ->all()
            ->indexBy('nip')
            ->toArray();
        $io->info('New system: ' . count($newCompanies) . ' companies with NIP.');
        $io->hr();

        // 3. Match by NIP
        $matched = [];
        $unmatched = [];

        foreach ($oldBusinesses as $old) {
            $nip = !empty($old['nip']) ? preg_replace('/\D/', '', $old['nip']) : null;
            if (!$nip) {
                $unmatched[] = [
                    'reason' => 'brak NIP',
                    'name'   => $old['full_name'] ?? $old['fna'] ?? '?',
                    'bu_uuid' => $old['bu_uuid'] ?? '?',
                ];
                continue;
            }

            if (isset($newCompanies[$nip])) {
                $matched[] = [
                    'old_bu_uuid'  => $old['bu_uuid'],
                    'old_name'     => $old['full_name'] ?? $old['fna'] ?? '?',
                    'old_nip'      => $nip,
                    'new_id'       => $newCompanies[$nip]->id,
                    'new_name'     => $newCompanies[$nip]->name,
                ];
            } else {
                $unmatched[] = [
                    'reason'  => 'NIP nie znaleziony w nowym systemie',
                    'name'    => $old['full_name'] ?? $old['fna'] ?? '?',
                    'nip'     => $nip,
                    'bu_uuid' => $old['bu_uuid'] ?? '?',
                ];
            }
        }

        // 4. Report matching results
        $io->info(sprintf('Matched: %d | Unmatched: %d', count($matched), count($unmatched)));
        $io->hr();

        if (!empty($unmatched)) {
            $io->warning('Unmatched businesses (skipped):');
            foreach ($unmatched as $u) {
                $io->out(sprintf('  - %s (NIP: %s, bu_uuid: %s) — %s',
                    $u['name'], $u['nip'] ?? '-', $u['bu_uuid'], $u['reason']));
            }
            $io->hr();
        }

        if (empty($matched)) {
            $io->error('No companies matched by NIP. Nothing to import.');
            return self::CODE_ERROR;
        }

        // 5. Import data for each matched pair
        foreach ($matched as $i => $pair) {
            $io->hr();
            $io->info(sprintf(
                '[%d/%d] NIP %s: "%s" (old) → "%s" (new)',
                $i + 1, count($matched),
                $pair['old_nip'], $pair['old_name'], $pair['new_name']
            ));

            $this->importAllTargets(
                $http, $baseUrl,
                $pair['old_bu_uuid'], $pair['new_id'],
                $target, $dryRun, $io
            );
        }

        // 6. Summary
        $io->hr();
        $io->hr();
        $io->success(sprintf(
            'AUTO-MATCH complete. Processed %d matched companies, %d unmatched skipped.',
            count($matched), count($unmatched)
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * Fetch all businesses from the old system (paginated).
     */
    private function fetchAllOldBusinesses(Client $http, string $baseUrl, ConsoleIo $io): array
    {
        $all = [];
        $page = 1;

        do {
            $url = "{$baseUrl}/api_export/businesses?page={$page}&limit=200";
            $io->verbose("Fetching businesses: {$url}");
            $response = $http->get($url);

            if (!$response->isOk()) {
                $io->error("HTTP {$response->getStatusCode()} fetching businesses");
                break;
            }

            $body = $response->getJson();
            $data = $body['data'] ?? [];
            $count = count($data);

            foreach ($data as $row) {
                $all[] = $row;
            }

            $io->verbose("  Page {$page}: {$count} businesses");
            $page++;
        } while ($count >= 200);

        return $all;
    }

    // ─── SHARED IMPORT ORCHESTRATION ──────────────────────────────────

    private function importAllTargets(Client $http, string $baseUrl, string $buUuid, string $companyId, string $target, bool $dryRun, ConsoleIo $io): void
    {
        $targets = $target === 'all'
            ? ['contractors', 'products', 'invoices']
            : [$target];

        foreach ($targets as $t) {
            $io->info("  --- {$t} ---");
            $this->importTarget($http, $baseUrl, $buUuid, $companyId, $t, $dryRun, $io);
        }
    }

    /**
     * Fetch all pages of a given target from the old system and import them.
     */
    private function importTarget(Client $http, string $baseUrl, string $buUuid, string $companyId, string $target, bool $dryRun, ConsoleIo $io): void
    {
        $page = 1;
        $totalImported = 0;
        $totalSkipped = 0;

        do {
            $url = "{$baseUrl}/api_export/{$target}/{$buUuid}?page={$page}&limit=200";
            $io->verbose("Fetching: {$url}");
            $response = $http->get($url);

            if (!$response->isOk()) {
                $io->error("HTTP {$response->getStatusCode()} for {$url}");
                break;
            }

            $body = $response->getJson();
            if (empty($body['data'])) {
                if ($page === 1) {
                    $io->warning("No {$target} found for bu_uuid={$buUuid}");
                }
                break;
            }

            $count = count($body['data']);
            $io->info("Page {$page}: {$count} records");

            foreach ($body['data'] as $row) {
                $result = match ($target) {
                    'contractors' => $this->importContractor($row, $companyId, $dryRun, $io),
                    'products'    => $this->importProduct($row, $companyId, $dryRun, $io),
                    'invoices'    => $this->importInvoice($row, $companyId, $dryRun, $io),
                };

                if ($result) {
                    $totalImported++;
                } else {
                    $totalSkipped++;
                }
            }

            $page++;
        } while ($count >= 200);

        $io->success("{$target}: imported={$totalImported}, skipped={$totalSkipped}");
    }

    // ─── CONTRACTORS ──────────────────────────────────────────────────

    private function importContractor(array $row, string $companyId, bool $dryRun, ConsoleIo $io): bool
    {
        $Contractors = $this->fetchTable('Contractors');

        $nip = !empty($row['nip']) ? preg_replace('/\D/', '', $row['nip']) : null;

        // Skip if contractor with same NIP already exists for this company
        if ($nip) {
            $exists = $Contractors->find()
                ->where(['company_id' => $companyId, 'nip' => $nip])
                ->first();
            if ($exists) {
                $io->verbose("  Contractor NIP={$nip} already exists, skipping.");
                return false;
            }
        }

        // Build full name from name1..name4
        $name = trim($row['full_name'] ?? $row['fna'] ?? '');
        if (empty($name)) {
            $name = trim(implode(' ', array_filter([
                $row['name1'] ?? '',
                $row['name2'] ?? '',
                $row['name3'] ?? '',
                $row['name4'] ?? '',
            ])));
        }

        if (empty($name)) {
            $io->warning("  Skipping contractor with empty name (bd_uuid={$row['bd_uuid']})");
            return false;
        }

        $data = [
            'id'         => Text::uuid(),
            'company_id' => $companyId,
            'name'       => mb_substr($name, 0, 200),
            'nip'        => $nip ? mb_substr($nip, 0, 20) : null,
            'regon'      => !empty($row['regon']) ? mb_substr($row['regon'], 0, 14) : null,
            'eu_vat'     => ($row['vat_eu_payer'] ?? 'f') === 't',
            'country'    => !empty($row['country']) ? mb_substr($row['country'], 0, 2) : 'PL',
            'postal_code' => !empty($row['postal_code']) ? mb_substr($row['postal_code'], 0, 16) : null,
            'city'       => !empty($row['city']) ? mb_substr($row['city'], 0, 120) : null,
            'street'     => !empty($row['street']) ? mb_substr($row['street'], 0, 160) : null,
            'email'      => !empty($row['email']) ? mb_substr($row['email'], 0, 160) : null,
            'notes'      => !empty($row['default_notes']) ? $row['default_notes'] : null,
            'is_active'  => true,
            'is_person'  => false,
        ];

        if ($dryRun) {
            $io->verbose("  [DRY] Would create contractor: {$name}");
            return true;
        }

        $entity = $Contractors->newEntity($data, ['validate' => false]);
        if ($Contractors->save($entity)) {
            $io->verbose("  Created contractor: {$name}");
            return true;
        }

        $io->warning("  Failed to save contractor: {$name}");
        $io->verbose("  Errors: " . json_encode($entity->getErrors()));
        return false;
    }

    // ─── PRODUCTS ─────────────────────────────────────────────────────

    private function importProduct(array $row, string $companyId, bool $dryRun, ConsoleIo $io): bool
    {
        $Products = $this->fetchTable('Products');

        $name = trim($row['name'] ?? '');
        if (empty($name)) {
            $io->warning("  Skipping product with empty name (gs_uuid={$row['gs_uuid']})");
            return false;
        }

        // Check for duplicate by name
        $exists = $Products->find()
            ->where(['company_id' => $companyId, 'name' => $name])
            ->first();
        if ($exists) {
            $io->verbose("  Product \"{$name}\" already exists, skipping.");
            return false;
        }

        // Resolve VAT
        $vatId = $this->resolveVatId($row['vat_short'] ?? $row['vat_rate'] ?? null, $io);
        if (!$vatId) {
            $io->warning("  Cannot resolve VAT for product \"{$name}\" (vat_short={$row['vat_short'] ?? '?'})");
            return false;
        }

        // Resolve Unit
        $unitId = $this->resolveUnitId($row['unit'] ?? 'szt.', $io);

        $data = [
            'id'         => Text::uuid(),
            'company_id' => $companyId,
            'name'       => mb_substr($name, 0, 255),
            'is_service' => false, // old system doesn't distinguish
            'unit_id'    => $unitId,
            'vat_id'     => $vatId,
            'net_price'  => (float)($row['net_price'] ?? 0),
            'currency'   => 'PLN',
            'pkwiu'      => !empty($row['pkwiu']) ? mb_substr($row['pkwiu'], 0, 32) : null,
            'gtu_code'   => null, // old system doesn't have GTU on products
            'is_active'  => true,
            'deleted'    => false,
        ];

        if ($dryRun) {
            $io->verbose("  [DRY] Would create product: {$name}");
            return true;
        }

        $entity = $Products->newEntity($data, ['validate' => false]);
        if ($Products->save($entity)) {
            $io->verbose("  Created product: {$name}");
            return true;
        }

        $io->warning("  Failed to save product: {$name}");
        $io->verbose("  Errors: " . json_encode($entity->getErrors()));
        return false;
    }

    // ─── INVOICES ─────────────────────────────────────────────────────

    private function importInvoice(array $row, string $companyId, bool $dryRun, ConsoleIo $io): bool
    {
        $Invoices = $this->fetchTable('Invoices');

        $fullnumber = trim($row['fullnumber'] ?? '');
        if (empty($fullnumber)) {
            $io->warning("  Skipping invoice with empty number");
            return false;
        }

        // Check for duplicate
        $exists = $Invoices->find()
            ->where(['company_id' => $companyId, 'fullnumber' => $fullnumber])
            ->first();
        if ($exists) {
            $io->verbose("  Invoice \"{$fullnumber}\" already exists, skipping.");
            return false;
        }

        // Map invoice type
        $typeMap = [
            'vat'               => 'invoice',
            'korekta'           => 'correction',
            'bez_vat'           => 'novat',
            'bez_vat_korekta'   => 'correction_novat',
            'proforma'          => 'proforma',
            'zaliczkowa'        => 'advance',
            'koncowa'           => 'final',
            'walutowa'          => 'invoice',
            'walutowa_korekta'  => 'correction',
            'marza'             => 'margin',
            'marza_korekta'     => 'correction_margin',
            'uproszczona'       => 'novat',
            'uproszczona_korekta' => 'correction_novat',
        ];
        $oldType = $row['type_code'] ?? 'vat';
        $newType = $typeMap[$oldType] ?? 'invoice';
        $isCorrection = str_contains($oldType, 'korekta');

        $invoiceId = Text::uuid();
        $data = [
            'id'                => $invoiceId,
            'company_id'        => $companyId,
            'type'              => $newType,
            'correction_type'   => $isCorrection ? 'item' : null,
            'simplified_invoice' => false,
            'fullnumber'        => mb_substr($fullnumber, 0, 64),
            'date'              => $row['issue_date'] ?? date('Y-m-d'),
            'paymentmethod'     => $this->mapPaymentMethod($row['payment_method'] ?? null),
            'paymentdate'       => $row['due_date'] ?? null,
            'paymentstate'      => $this->mapPaymentState($row['remaining_amount'] ?? '0.00', $row['gross_total'] ?? '0.00'),
            'total'             => (float)($row['gross_total'] ?? 0),
            'netto'             => (float)($row['net_total'] ?? 0),
            'tax'               => (float)($row['vat_total'] ?? 0),
            'alreadypaid'       => (float)($row['amount_paid'] ?? 0),
            'remaining'         => (float)($row['remaining_amount'] ?? 0),
            'currency'          => $row['currency'] ?? 'PLN',
            'currency_date'     => null,
            'currency_exchange'  => (float)($row['exchange_rate'] ?? 1),
            'description'       => $row['notes'] ?? null,
            'is_print'          => false,
            'is_sent'           => false,
            'is_api'            => true, // mark as imported via API
            'workflow_status'    => 'issued',
        ];

        if ($dryRun) {
            $itemCount = count($row['items'] ?? []);
            $io->verbose("  [DRY] Would create invoice: {$fullnumber} ({$newType}, {$itemCount} items)");
            return true;
        }

        $entity = $Invoices->newEntity($data, ['validate' => false]);
        if (!$Invoices->save($entity)) {
            $io->warning("  Failed to save invoice: {$fullnumber}");
            $io->verbose("  Errors: " . json_encode($entity->getErrors()));
            return false;
        }

        // Save company details snapshot
        $this->saveInvoiceCompanyDetails($invoiceId, $row);

        // Save contractor snapshot
        $this->saveInvoiceContractor($invoiceId, $row);

        // Save line items
        $this->saveInvoiceContents($invoiceId, $row['items'] ?? [], $io);

        // Save VAT summary
        $this->saveInvoiceVatContents($invoiceId, $row['vat_summary'] ?? [], $io);

        $io->verbose("  Created invoice: {$fullnumber}");
        return true;
    }

    // ─── INVOICE SUB-RECORDS ──────────────────────────────────────────

    private function saveInvoiceCompanyDetails(string $invoiceId, array $row): void
    {
        $table = $this->fetchTable('InvoiceCompanyDetails');
        $entity = $table->newEntity([
            'id'           => Text::uuid(),
            'invoice_id'   => $invoiceId,
            'name'         => mb_substr($row['ih_seller_name'] ?? '', 0, 255),
            'nip'          => $row['ih_seller_tin'] ?? null,
            'street'       => $row['ih_seller_street'] ?? null,
            'city'         => $row['ih_seller_city'] ?? null,
            'zip'          => $row['ih_seller_postal_code'] ?? null,
            'country'      => $row['ih_seller_country'] ?? 'PL',
        ], ['validate' => false]);
        $table->save($entity);
    }

    private function saveInvoiceContractor(string $invoiceId, array $row): void
    {
        $table = $this->fetchTable('InvoiceContractors');
        $entity = $table->newEntity([
            'id'           => Text::uuid(),
            'invoice_id'   => $invoiceId,
            'name'         => mb_substr($row['ih_buyer_name'] ?? '', 0, 255),
            'nip'          => $row['ih_buyer_tin'] ?? null,
            'street'       => $row['ih_buyer_street'] ?? null,
            'city'         => $row['ih_buyer_city'] ?? null,
            'zip'          => $row['ih_buyer_postal_code'] ?? null,
            'country'      => $row['ih_buyer_country'] ?? 'PL',
        ], ['validate' => false]);
        $table->save($entity);
    }

    private function saveInvoiceContents(string $invoiceId, array $items, ConsoleIo $io): void
    {
        $table = $this->fetchTable('InvoiceContents');
        foreach ($items as $item) {
            $vatCodeId = $this->resolveVatId($item['vat_short'] ?? $item['vat_rate'] ?? null, $io);
            $entity = $table->newEntity([
                'id'               => Text::uuid(),
                'invoice_id'       => $invoiceId,
                'vat_code_id'      => $vatCodeId,
                'name'             => mb_substr($item['name'] ?? 'Pozycja', 0, 255),
                'quantity'         => (float)($item['quantity'] ?? 1),
                'unit'             => mb_substr($item['unit'] ?? 'szt.', 0, 16),
                'price'            => (float)($item['net_price'] ?? 0),
                'discount_percent' => (float)($item['discount_percent'] ?? 0),
                'netto'            => (float)($item['net_value'] ?? 0),
                'brutto'           => (float)($item['gross_value'] ?? 0),
                'gtu_code'         => !empty($item['gtu_code']) ? $item['gtu_code'] : null,
            ], ['validate' => false]);
            $table->save($entity);
        }
    }

    private function saveInvoiceVatContents(string $invoiceId, array $vats, ConsoleIo $io): void
    {
        $table = $this->fetchTable('InvoiceVatContents');
        foreach ($vats as $vat) {
            $vatCodeId = $this->resolveVatId($vat['vat_short'] ?? $vat['vat_rate'] ?? null, $io);
            $entity = $table->newEntity([
                'id'         => Text::uuid(),
                'invoice_id' => $invoiceId,
                'vat_code_id' => $vatCodeId,
                'netto'      => (float)($vat['net_value'] ?? 0),
                'tax'        => (float)($vat['vat_value'] ?? 0),
                'brutto'     => (float)($vat['gross_value'] ?? 0),
            ], ['validate' => false]);
            $table->save($entity);
        }
    }

    // ─── REFERENCE DATA ───────────────────────────────────────────────

    private function loadVatMap(ConsoleIo $io): void
    {
        $Vats = $this->fetchTable('Vats');
        $vats = $Vats->find()->where(['deleted' => false])->all();
        foreach ($vats as $vat) {
            // Map by name (e.g., "23%", "8%", "zw", "np") and by rate
            $this->vatMap[strtolower($vat->name)] = $vat->id;
            $this->vatMap[(string)$vat->rate] = $vat->id;
        }
        $io->verbose("Loaded " . count($this->vatMap) . " VAT mappings");
    }

    private function loadUnitMap(ConsoleIo $io): void
    {
        $Units = $this->fetchTable('Units');
        $units = $Units->find()->all();
        foreach ($units as $unit) {
            $this->unitMap[strtolower($unit->name)] = $unit->id;
        }
        $io->verbose("Loaded " . count($this->unitMap) . " unit mappings");
    }

    private function resolveVatId(?string $vatKey, ConsoleIo $io): ?string
    {
        if ($vatKey === null) {
            return null;
        }

        $key = strtolower(trim($vatKey));

        // Direct match (name like "23%", "zw", "np")
        if (isset($this->vatMap[$key])) {
            return $this->vatMap[$key];
        }

        // Try as rate (e.g., "23.00" or "23")
        $rate = rtrim(rtrim($key, '0'), '.');
        if (isset($this->vatMap[$rate])) {
            return $this->vatMap[$rate];
        }

        // Try stripping % sign
        $stripped = str_replace('%', '', $key);
        if (isset($this->vatMap[$stripped])) {
            return $this->vatMap[$stripped];
        }

        $io->verbose("  VAT key not resolved: \"{$vatKey}\"");
        return null;
    }

    private function resolveUnitId(string $unitName, ConsoleIo $io): int
    {
        $key = strtolower(trim($unitName));

        if (isset($this->unitMap[$key])) {
            return $this->unitMap[$key];
        }

        // Common aliases
        $aliases = [
            'szt'  => 'szt.',
            'szt.' => 'szt.',
            'godz' => 'godz.',
            'godz.' => 'godz.',
            'h'    => 'godz.',
            'usł'  => 'usł.',
            'usł.' => 'usł.',
            'kpl'  => 'kpl.',
            'kpl.' => 'kpl.',
            'kg'   => 'kg',
            'mb'   => 'mb',
            'm'    => 'm',
            'l'    => 'l',
            'km'   => 'km',
        ];

        $alias = $aliases[$key] ?? null;
        if ($alias && isset($this->unitMap[strtolower($alias)])) {
            return $this->unitMap[strtolower($alias)];
        }

        // Fallback: return first unit
        $first = reset($this->unitMap);
        if ($first) {
            $io->verbose("  Unit \"{$unitName}\" not matched, using default (id={$first})");
            return $first;
        }

        return 1; // absolute fallback
    }

    // ─── HELPERS ──────────────────────────────────────────────────────

    private function mapPaymentMethod(?string $method): string
    {
        if (!$method) {
            return 'transfer';
        }

        $lower = mb_strtolower($method);
        return match (true) {
            str_contains($lower, 'przelew') => 'transfer',
            str_contains($lower, 'gotówk')  => 'cash',
            str_contains($lower, 'kart')    => 'card',
            str_contains($lower, 'kompens') => 'compensation',
            str_contains($lower, 'pobranie') => 'cod',
            default => 'transfer',
        };
    }

    private function mapPaymentState(string $remaining, string $total): string
    {
        $rem = (float)$remaining;
        $tot = (float)$total;

        if ($rem <= 0) {
            return 'paid';
        }
        if ($rem < $tot) {
            return 'partial';
        }
        return 'unpaid';
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * Synchronizuje faktury z zewnętrznego API legacy (ai-nordlogis) do tabeli legacy_invoices.
 *
 * API: GET https://ai-nordlogis.3ckstudio.pl/dokumenty?rejestr=130&rok=2026&mc=03
 * Brak autoryzacji. Paginacja: page=1..N, limit=100 per request.
 *
 * Dane z API są źródłem prawdy — podczas sync nadpisujemy lokalne rekordy,
 * a zmiany paymentstate są logowane do legacy_sync_logs.
 */
class LegacyInvoiceSyncService
{
    private const API_BASE_URL = 'https://ai-nordlogis.3ckstudio.pl/dokumenty';
    private const PAGE_LIMIT   = 100;
    private const HTTP_TIMEOUT = 20;

    /**
     * Synchronizuje jeden miesiąc (lub rok jeśli $mc === null).
     *
     * @return array{fetched: int, upserted: int, changed: int, changes: list<array>}
     * @throws \RuntimeException gdy API zwróci błąd HTTP lub nieprawidłowy JSON
     */
    public function syncMonth(
        int $rejestr,
        int $rok,
        ?int $mc,
        string $companyId
    ): array {
        $LegacyInvoices = TableRegistry::getTableLocator()->get('LegacyInvoices');

        $allRows  = [];
        $page     = 1;

        // ── Pobierz wszystkie strony z API ────────────────────────────────────
        do {
            $url    = $this->_buildUrl($rejestr, $rok, $mc, $page, self::PAGE_LIMIT);
            $json   = $this->_fetch($url);
            $data   = json_decode($json, true);

            if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
                throw new \RuntimeException('API zwróciło nieprawidłową odpowiedź: ' . substr($json, 0, 200));
            }

            $allRows = array_merge($allRows, $data['data']);
            $totalPages = (int)($data['totalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        // ── UPSERT — pobierz istniejące glo_id dla tej firmy ─────────────────
        $gloIds = array_column($allRows, 'GLO_ID');
        $existing = [];

        if (!empty($gloIds)) {
            $rows = $LegacyInvoices->find()
                ->where(['company_id' => $companyId, 'glo_id IN' => $gloIds])
                ->select(['id', 'glo_id', 'paymentstate', 'remaining', 'alreadypaid'])
                ->all();

            foreach ($rows as $r) {
                $existing[(int)$r->glo_id] = $r;
            }
        }

        $nowStr  = date('Y-m-d H:i:s');
        $upserted = 0;
        $changes  = [];

        foreach ($allRows as $row) {
            $gloId     = (int)($row['GLO_ID'] ?? 0);
            $remaining = $this->_parseDecimal($row['POZOSTALO_PLN'] ?? 0);
            $total     = $this->_parseDecimal($row['GLO_BRUTTO'] ?? 0);
            $paid      = $this->_parseDecimal($row['GLO_ZL_ZAPLATA'] ?? 0);

            $paymentstate = $this->_computePaymentState($remaining, $total, $paid);

            $record = [
                'company_id'        => $companyId,
                'glo_id'            => $gloId,
                'rejestr'           => (int)($row['GLO_REJESTR'] ?? $rejestr),
                'fullnumber'        => (string)($row['GLO_SYMBOL'] ?? ''),
                'date'              => $this->_parseDate($row['GLO_DATA_DOK'] ?? null),
                'paymentdate'       => $this->_parseDate($row['TERMIN'] ?? null),
                'glo_tyt1'          => (string)($row['GLO_TYT1'] ?? '') ?: null,
                'poz_naz7'          => (string)($row['POZ_NAZ7'] ?? '') ?: null,
                'poz_nazwa'         => (string)($row['POZ_NAZWA'] ?? '') ?: null,
                'contractor_name'   => (string)($row['GLO_ODB_NAZWA1'] ?? ''),
                'contractor_nip'    => $this->_cleanNip($row['GLO_ODB_NIP'] ?? null),
                'contractor_city'   => (string)($row['GLO_ODB_POCZTA'] ?? '') ?: null,
                'contractor_country'=> trim((string)($row['GLO_ODB_KRAJ'] ?? '')) ?: null,
                'contractor_skrot'  => (string)($row['GLO_ODB_SKROT'] ?? '') ?: null,
                'total'             => $total,
                'netto'             => $this->_parseDecimal($row['GLO_NETTO'] ?? 0),
                'alreadypaid'       => $paid,
                'remaining'         => $remaining,
                'currency'          => strtoupper(trim((string)($row['GLO_WALUTA'] ?? 'PLN'))) ?: 'PLN',
                'total_wal'         => $this->_parseDecimal($row['GLO_WAL_WARTOSC'] ?? 0),
                'alreadypaid_wal'   => $this->_parseDecimal($row['GLO_WAL_ZAPLATA'] ?? 0),
                'remaining_wal'     => $this->_parseDecimal($row['POZOSTALO_WAL'] ?? 0),
                'exchange_rate'     => $this->_parseDecimalNullable($row['GLO_WAL_PRZEL'] ?? null),
                'paymentstate'      => $paymentstate,
                'dnit'              => isset($row['DNIT']) ? (int)$row['DNIT'] : null,
                'platnosc'          => (string)($row['GLO_PLATNOSC'] ?? '') ?: null,
                'teczka'            => (string)($row['GLO_TECZKA'] ?? '') ?: null,
                'glo_rozrach'       => (string)($row['GLO_ROZRACH'] ?? '') ?: null,
                'synced_at'         => $nowStr,
            ];

            // Sprawdź czy paymentstate się zmieniło (legacy = źródło prawdy → nadpisz)
            if (isset($existing[$gloId])) {
                $prev = $existing[$gloId];
                if ((string)$prev->paymentstate !== $paymentstate) {
                    $changes[] = [
                        'glo_id'       => $gloId,
                        'fullnumber'   => $record['fullnumber'],
                        'from'         => (string)$prev->paymentstate,
                        'to'           => $paymentstate,
                        'remaining'    => $remaining,
                        'alreadypaid'  => $paid,
                    ];
                }

                $entity = $LegacyInvoices->patchEntity($prev, $record);
            } else {
                $record['id'] = Text::uuid();
                $entity = $LegacyInvoices->newEntity($record);
            }

            if ($LegacyInvoices->save($entity)) {
                $upserted++;
            }
        }

        return [
            'fetched'  => count($allRows),
            'upserted' => $upserted,
            'changed'  => count($changes),
            'changes'  => $changes,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function _buildUrl(int $rejestr, int $rok, ?int $mc, int $page, int $limit): string
    {
        $params = [
            'rejestr' => $rejestr,
            'rok'     => $rok,
            'page'    => $page,
            'limit'   => $limit,
        ];
        if ($mc !== null) {
            $params['mc'] = str_pad((string)$mc, 2, '0', STR_PAD_LEFT);
        }

        return self::API_BASE_URL . '?' . http_build_query($params);
    }

    /**
     * HTTP GET z cURL. Nie używa allow_url_fopen.
     *
     * @throws \RuntimeException gdy HTTP error lub timeout
     */
    private function _fetch(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'faktury24-sync/1.0',
            // Weryfikacja SSL
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new \RuntimeException('Błąd połączenia z API: ' . $error);
        }
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('API zwróciło HTTP %d dla: %s', $status, $url));
        }

        return (string)$body;
    }

    private function _parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = (string)$v;
        // ISO datetime "2026-04-16T00:00:00.000Z" lub "2026-03-02"
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    private function _parseDecimal(mixed $v): float
    {
        return is_numeric($v) ? (float)$v : 0.0;
    }

    private function _parseDecimalNullable(mixed $v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $f = (float)$v;
        return ($f === 0.0 || $f === 1.0) ? null : $f; // kurs 1.0 = PLN = brak kursu
    }

    private function _cleanNip(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $nip = preg_replace('/[^0-9]/', '', (string)$v);
        return $nip !== '' ? $nip : null;
    }

    private function _computePaymentState(float $remaining, float $total, float $paid): string
    {
        if ($remaining <= 0.01) {
            return 'paid';
        }
        if ($paid > 0.01) {
            return 'partial';
        }

        return 'unpaid';
    }
}

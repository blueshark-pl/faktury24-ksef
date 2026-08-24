<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Wrapper dla API MS-KRS (Ministerstwo Sprawiedliwosci - Krajowy Rejestr Sadowy).
 *
 * Dokumentacja: https://api-krs.ms.gov.pl/api/krs/
 *
 * Endpointy:
 *   /OdpisAktualny/{krs}?rejestr={P|S}&format=json - odpis aktualny (bez historii)
 *   /OdpisPelny/{krs}?rejestr={P|S}&format=json    - z historia zmian
 *
 * Rejestr:
 *   P = Przedsiebiorcy (spolki handlowe, jawne, cywilne)
 *   S = Stowarzyszenia (fundacje, stowarzyszenia, samorzady zawodowe)
 *
 * NIP -> KRS: brak bezpo?redniego API - user musi wpisac KRS lub uzyc
 * publicznej wyszukiwarki https://wyszukiwarka-krs.ms.gov.pl/. My cache-ujemy
 * NIP w tabeli po pierwszym fetch dla przyszlych szybkich lookup.
 *
 * Cache TTL: 30 dni (dane firmowe rzadko sie zmieniaja). Po tym czasie refetch.
 */
class KrsService
{
    private const BASE_URL = 'https://api-krs.ms.gov.pl/api/krs';
    private const CACHE_TTL_DAYS = 30;

    /**
     * Fetch po KRS z cache lub API MS-KRS.
     * Automatycznie probuje rejestr P (przedsiebiorcy) i S (stowarzyszenia).
     *
     * @param string $krs 10-cyfrowy numer KRS
     * @param bool $forceRefresh Ignoruj cache i fetch od nowa
     * @return array|null Parsed data lub null jesli nie znaleziono
     */
    public function fetchByKrs(string $krs, bool $forceRefresh = false): ?array
    {
        $krs = str_pad(preg_replace('/[^0-9]/', '', $krs), 10, '0', STR_PAD_LEFT);
        if (strlen($krs) !== 10) return null;

        $Cache = TableRegistry::getTableLocator()->get('CrmKrsCache');

        // Cache check
        if (!$forceRefresh) {
            $cached = $Cache->find()->where(['krs' => $krs])->first();
            if ($cached) {
                $age = time() - $cached->fetched_at->getTimestamp();
                if ($age < (self::CACHE_TTL_DAYS * 86400)) {
                    return $this->cacheRowToArray($cached);
                }
            }
        }

        // Fetch z API - probujemy P i S
        $data = null;
        $rejestrUsed = null;
        foreach (['P', 'S'] as $rejestr) {
            $url = sprintf('%s/OdpisAktualny/%s?rejestr=%s&format=json',
                self::BASE_URL, $krs, $rejestr);
            try {
                $client = new Client(['timeout' => 15]);
                $response = $client->get($url);
                if ($response->getStatusCode() === 200) {
                    $body = (string)$response->getBody();
                    $json = json_decode($body, true);
                    if (is_array($json) && !empty($json['odpis'])) {
                        $data = $json;
                        $rejestrUsed = $rejestr;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning(sprintf('KrsService fetch failed (%s / %s): %s', $krs, $rejestr, $e->getMessage()));
            }
        }

        if (!$data) {
            // Zapisz error do cache zeby nie hammer-owac
            $this->saveCacheError($Cache, $krs, 'Nie znaleziono w rejestrze P ani S');
            return null;
        }

        // Parsuj + zapisz cache
        $parsed = $this->parseResponse($krs, $data);
        $this->saveCacheOk($Cache, $krs, $parsed, $data);
        return $parsed;
    }

    /**
     * Fetch po NIP - najpierw cache (nip col), potem prosimy o KRS.
     * @return array|null Zwraca dane jesli KRS byl kiedys cachowany po NIP
     */
    public function fetchByNipFromCache(string $nip): ?array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        if (strlen($nip) !== 10) return null;

        $Cache = TableRegistry::getTableLocator()->get('CrmKrsCache');
        $cached = $Cache->find()->where(['nip' => $nip])->first();
        if (!$cached) return null;

        $age = time() - $cached->fetched_at->getTimestamp();
        if ($age > (self::CACHE_TTL_DAYS * 86400)) {
            // Cache expired - refetch po KRS
            return $this->fetchByKrs((string)$cached->krs, true);
        }
        return $this->cacheRowToArray($cached);
    }

    /**
     * Parsuje odpis MS-KRS -> plaska struktura z kluczowymi polami.
     */
    private function parseResponse(string $krs, array $data): array
    {
        $odpis = $data['odpis'] ?? [];
        $dane  = $odpis['dane'] ?? [];
        $d1    = $dane['dzial1'] ?? [];
        $d2    = $dane['dzial2'] ?? [];
        $d6    = $dane['dzial6'] ?? [];

        $identyfikatory = $d1['danePodmiotu']['identyfikatory'] ?? [];
        $siedziba = $d1['siedzibaIAdres'] ?? [];
        $adres = $siedziba['adres'] ?? [];
        $kapital = $d1['kapital'] ?? [];

        // PKD wszystkie
        $pkdRaw = $d1['przedmiotDzialalnosci']['przedmiotPrzewazajacejDzialalnosci'] ?? [];
        $pkdPozostale = $d1['przedmiotDzialalnosci']['przedmiotPozostalejDzialalnosci'] ?? [];
        $pkdWszystkie = [];
        foreach (array_merge($pkdRaw, $pkdPozostale) as $p) {
            $pkdWszystkie[] = [
                'kod'  => trim(($p['kodPkd'] ?? '') ?: (($p['kod'] ?? ''))),
                'opis' => trim($p['opis'] ?? ''),
            ];
        }
        $pkdGlowne = $pkdWszystkie[0] ?? ['kod' => null, 'opis' => null];

        // Reprezentacja (zarzad)
        $reprezentacja = [
            'sposob' => trim($d2['reprezentacja']['sposobReprezentacji'] ?? ''),
            'czlonkowie' => [],
        ];
        foreach ($d2['reprezentacja']['sklad'] ?? [] as $czlonek) {
            $reprezentacja['czlonkowie'][] = [
                'imie'     => trim($czlonek['osoba']['imiePierwsze'] ?? ''),
                'nazwisko' => trim($czlonek['osoba']['nazwiskoIczlon'] ?? $czlonek['osoba']['nazwisko'] ?? ''),
                'funkcja'  => trim($czlonek['funkcjaWorganie'] ?? ''),
            ];
        }

        // Wspolnicy (dla sp. z o.o.)
        $wspolnicy = [];
        foreach ($d1['wspolnicy'] ?? [] as $w) {
            $osoba = $w['osoba'] ?? [];
            $wspolnicy[] = [
                'imie'     => trim($osoba['imiePierwsze'] ?? ''),
                'nazwisko' => trim($osoba['nazwiskoIczlon'] ?? $osoba['nazwisko'] ?? ''),
                'firma'    => trim($w['nazwa'] ?? ''),
                'udzialy_liczba' => (int)($w['posiadaneUdzialy'][0]['liczbaUdzialow'] ?? 0),
                'udzialy_wartosc' => (float)($w['posiadaneUdzialy'][0]['wartoscUdzialow'] ?? 0),
            ];
        }

        // Status - upadlosc / zakonczenie
        $upadlosc = !empty($d6['postepowanieUpadlosciowe']);
        $zakonczenie = trim($d1['dataZakonczeniaDzialalnosci'] ?? '');

        return [
            'krs'                 => $krs,
            'nip'                 => trim($identyfikatory['nip'] ?? ''),
            'regon'               => trim($identyfikatory['regon'] ?? ''),
            'nazwa'               => trim($d1['danePodmiotu']['nazwa'] ?? ''),
            'forma_prawna'        => trim($d1['danePodmiotu']['formaPrawna'] ?? ''),
            'kod_pocztowy'        => trim($adres['kodPocztowy'] ?? ''),
            'miejscowosc'         => trim($adres['miejscowosc'] ?? ''),
            'ulica'               => trim($adres['ulica'] ?? ''),
            'nr_domu'             => trim($adres['nrDomu'] ?? ''),
            'nr_lokalu'           => trim($adres['nrLokalu'] ?? ''),
            'kraj'                => trim($adres['kraj'] ?? 'POLSKA'),
            'kapital_zakladowy'   => (float)($kapital['wysokoscKapitaluZakladowego']['wartosc'] ?? 0),
            'waluta_kapitalu'     => trim($kapital['wysokoscKapitaluZakladowego']['waluta'] ?? 'PLN'),
            'data_wpisu'          => $this->parseDate($odpis['naglowekA']['dataRejestracji'] ?? null),
            'data_zakonczenia'    => $this->parseDate($zakonczenie ?: null),
            'status_dzialajaca'   => !$upadlosc && !$zakonczenie,
            'pkd_glowne_kod'      => $pkdGlowne['kod'],
            'pkd_glowne_opis'     => $pkdGlowne['opis'],
            'pkd_wszystkie'       => $pkdWszystkie,
            'reprezentacja'       => $reprezentacja,
            'wspolnicy'           => $wspolnicy,
        ];
    }

    private function parseDate(?string $d): ?string
    {
        if (!$d) return null;
        // Formaty: "2015-03-14" lub "14.03.2015"
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $d, $m)) return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        return null;
    }

    private function saveCacheOk($Cache, string $krs, array $parsed, array $raw): void
    {
        try {
            $existing = $Cache->find()->where(['krs' => $krs])->first();
            $entity = $existing ?: $Cache->newEmptyEntity();
            $entity->krs = $krs;
            $entity->nip = $parsed['nip'] ?: null;
            $entity->regon = $parsed['regon'] ?: null;
            $entity->nazwa = $parsed['nazwa'] ?: null;
            $entity->forma_prawna = $parsed['forma_prawna'] ?: null;
            $entity->kod_pocztowy = $parsed['kod_pocztowy'] ?: null;
            $entity->miejscowosc = $parsed['miejscowosc'] ?: null;
            $entity->ulica = $parsed['ulica'] ?: null;
            $entity->nr_domu = $parsed['nr_domu'] ?: null;
            $entity->nr_lokalu = $parsed['nr_lokalu'] ?: null;
            $entity->kraj = $parsed['kraj'] ?: 'POLSKA';
            $entity->kapital_zakladowy = $parsed['kapital_zakladowy'] ?: null;
            $entity->waluta_kapitalu = $parsed['waluta_kapitalu'] ?: 'PLN';
            $entity->data_wpisu = $parsed['data_wpisu'] ? new \Cake\I18n\Date($parsed['data_wpisu']) : null;
            $entity->data_zakonczenia = $parsed['data_zakonczenia'] ? new \Cake\I18n\Date($parsed['data_zakonczenia']) : null;
            $entity->status_dzialajaca = $parsed['status_dzialajaca'];
            $entity->pkd_glowne_kod = $parsed['pkd_glowne_kod'];
            $entity->pkd_glowne_opis = $parsed['pkd_glowne_opis'];
            $entity->pkd_wszystkie_json = json_encode($parsed['pkd_wszystkie'], JSON_UNESCAPED_UNICODE);
            $entity->reprezentacja_json = json_encode($parsed['reprezentacja'], JSON_UNESCAPED_UNICODE);
            $entity->wspolnicy_json = json_encode($parsed['wspolnicy'], JSON_UNESCAPED_UNICODE);
            $entity->raw_json = json_encode($raw, JSON_UNESCAPED_UNICODE);
            $entity->fetched_at = new DateTime();
            $entity->fetch_error = null;
            $Cache->save($entity);
        } catch (\Throwable $e) {
            Log::warning('KrsService saveCacheOk failed: ' . $e->getMessage());
        }
    }

    private function saveCacheError($Cache, string $krs, string $error): void
    {
        try {
            $existing = $Cache->find()->where(['krs' => $krs])->first();
            $entity = $existing ?: $Cache->newEmptyEntity();
            $entity->krs = $krs;
            $entity->fetched_at = new DateTime();
            $entity->fetch_error = $error;
            $Cache->save($entity);
        } catch (\Throwable $e) {
            Log::warning('KrsService saveCacheError failed: ' . $e->getMessage());
        }
    }

    private function cacheRowToArray($cached): array
    {
        return [
            'krs'                 => (string)$cached->krs,
            'nip'                 => (string)($cached->nip ?? ''),
            'regon'               => (string)($cached->regon ?? ''),
            'nazwa'               => (string)($cached->nazwa ?? ''),
            'forma_prawna'        => (string)($cached->forma_prawna ?? ''),
            'kod_pocztowy'        => (string)($cached->kod_pocztowy ?? ''),
            'miejscowosc'         => (string)($cached->miejscowosc ?? ''),
            'ulica'               => (string)($cached->ulica ?? ''),
            'nr_domu'             => (string)($cached->nr_domu ?? ''),
            'nr_lokalu'           => (string)($cached->nr_lokalu ?? ''),
            'kraj'                => (string)($cached->kraj ?? 'POLSKA'),
            'kapital_zakladowy'   => (float)($cached->kapital_zakladowy ?? 0),
            'waluta_kapitalu'     => (string)($cached->waluta_kapitalu ?? 'PLN'),
            'data_wpisu'          => $cached->data_wpisu ? $cached->data_wpisu->format('Y-m-d') : null,
            'data_zakonczenia'    => $cached->data_zakonczenia ? $cached->data_zakonczenia->format('Y-m-d') : null,
            'status_dzialajaca'   => (bool)$cached->status_dzialajaca,
            'pkd_glowne_kod'      => (string)($cached->pkd_glowne_kod ?? ''),
            'pkd_glowne_opis'     => (string)($cached->pkd_glowne_opis ?? ''),
            'pkd_wszystkie'       => json_decode((string)($cached->pkd_wszystkie_json ?? '[]'), true) ?: [],
            'reprezentacja'       => json_decode((string)($cached->reprezentacja_json ?? '{}'), true) ?: [],
            'wspolnicy'           => json_decode((string)($cached->wspolnicy_json ?? '[]'), true) ?: [],
        ];
    }
}

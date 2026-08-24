<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Wyszukiwarka LinkedIn URL przez zewnetrzne Search API.
 *
 * Providery (wybor w Configure Search.provider):
 *   - serper     https://serper.dev  (2500 free na start, potem $50/mies za 2500)
 *   - brave      https://api.search.brave.com  (2000/mies free, potem $3/1000)
 *   - google_cse https://developers.google.com/custom-search  (100/day free)
 *
 * Query strategy:
 *   Person: "<name> <company> site:linkedin.com/in"
 *   Company: "<company> site:linkedin.com/company"
 *
 * Filter: tylko URL zawierajace linkedin.com/in/ lub linkedin.com/company/
 *
 * Cache TTL 90 dni w crm_search_cache (nie palimy kredytow na tych samych
 * zapytaniach).
 */
class LinkedinSearchService
{
    private const CACHE_TTL_DAYS = 90;

    private string $provider;
    private string $apiKey;

    public function __construct(?string $provider = null, ?string $apiKey = null)
    {
        $this->provider = $provider ?: (string)Configure::read('Search.provider', 'serper');
        $configKey = 'Search.' . $this->provider . 'ApiKey';
        $this->apiKey = $apiKey ?: (string)Configure::read($configKey, '');
    }

    /**
     * Wyszukaj profil osoby na LinkedIn.
     * @return array Lista wynikow: [['url', 'title', 'snippet'], ...]
     */
    public function findPerson(string $name, string $company): array
    {
        $name = trim($name);
        $company = trim($company);
        if ($name === '' || $company === '') return [];
        $q = sprintf('"%s" "%s" site:linkedin.com/in', $name, $company);
        return $this->filterLinkedInResults($this->search($q), '/in/');
    }

    /**
     * Wyszukaj profil firmy na LinkedIn.
     */
    public function findCompany(string $company): array
    {
        $company = trim($company);
        if ($company === '') return [];
        $q = sprintf('"%s" site:linkedin.com/company', $company);
        return $this->filterLinkedInResults($this->search($q), '/company/');
    }

    /**
     * Sprawdz czy service jest skonfigurowany (jest API key).
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    // ============= INTERNALS =============

    private function search(string $query): array
    {
        if (!$this->isConfigured()) {
            Log::warning('LinkedinSearchService: brak API key dla provider ' . $this->provider);
            return [];
        }

        // Cache
        $Cache = TableRegistry::getTableLocator()->get('CrmSearchCache');
        $hash = md5(strtolower(trim($query)));
        $cached = $Cache->find()->where(['query_hash' => $hash])->first();
        if ($cached && $cached->fetched_at) {
            $age = time() - $cached->fetched_at->getTimestamp();
            if ($age < (self::CACHE_TTL_DAYS * 86400)) {
                $results = json_decode((string)($cached->results_json ?? '[]'), true);
                return is_array($results) ? $results : [];
            }
        }

        // Wywolaj provider
        try {
            $results = match ($this->provider) {
                'serper'     => $this->searchSerper($query),
                'brave'      => $this->searchBrave($query),
                'google_cse' => $this->searchGoogleCse($query),
                default      => [],
            };
        } catch (\Throwable $e) {
            Log::warning(sprintf('LinkedinSearchService %s failed: %s', $this->provider, $e->getMessage()));
            $this->saveCache($Cache, $hash, $query, [], null, $e->getMessage());
            return [];
        }

        $this->saveCache($Cache, $hash, $query, $results, 200, null);
        return $results;
    }

    /**
     * Serper.dev https://serper.dev
     * POST https://google.serper.dev/search
     * Headers: X-API-KEY, Content-Type: application/json
     * Body: {"q": "query", "num": 10, "gl": "pl", "hl": "pl"}
     * Response: {organic: [{link, title, snippet, position}, ...]}
     */
    private function searchSerper(string $query): array
    {
        $client = new Client(['timeout' => 15]);
        $response = $client->post(
            'https://google.serper.dev/search',
            json_encode(['q' => $query, 'num' => 10, 'gl' => 'pl', 'hl' => 'pl']),
            [
                'headers' => [
                    'X-API-KEY'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]
        );
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Serper HTTP ' . $response->getStatusCode() . ': ' . $response->getStringBody());
        }
        $body = json_decode((string)$response->getBody(), true);
        $organic = $body['organic'] ?? [];
        $out = [];
        foreach ($organic as $r) {
            $out[] = [
                'url'     => (string)($r['link'] ?? ''),
                'title'   => (string)($r['title'] ?? ''),
                'snippet' => (string)($r['snippet'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Brave Search API https://api.search.brave.com/app/documentation/web-search
     * GET https://api.search.brave.com/res/v1/web/search?q=X&count=10&country=PL
     * Headers: X-Subscription-Token
     * Response: {web: {results: [{url, title, description}, ...]}}
     */
    private function searchBrave(string $query): array
    {
        $client = new Client(['timeout' => 15]);
        $response = $client->get(
            'https://api.search.brave.com/res/v1/web/search',
            [
                'q'       => $query,
                'count'   => 10,
                'country' => 'PL',
                'search_lang' => 'pl',
            ],
            [
                'headers' => [
                    'X-Subscription-Token' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]
        );
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Brave HTTP ' . $response->getStatusCode() . ': ' . $response->getStringBody());
        }
        $body = json_decode((string)$response->getBody(), true);
        $results = $body['web']['results'] ?? [];
        $out = [];
        foreach ($results as $r) {
            $out[] = [
                'url'     => (string)($r['url'] ?? ''),
                'title'   => (string)($r['title'] ?? ''),
                'snippet' => (string)($r['description'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Google Custom Search Engine https://developers.google.com/custom-search
     * Wymaga cx (CSE ID) w Configure Search.googleCseCx.
     * GET https://www.googleapis.com/customsearch/v1?key=X&cx=Y&q=Z
     */
    private function searchGoogleCse(string $query): array
    {
        $cx = (string)Configure::read('Search.googleCseCx', '');
        if ($cx === '') throw new \RuntimeException('Brak Search.googleCseCx');

        $client = new Client(['timeout' => 15]);
        $response = $client->get(
            'https://www.googleapis.com/customsearch/v1',
            ['key' => $this->apiKey, 'cx' => $cx, 'q' => $query, 'num' => 10, 'gl' => 'pl']
        );
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('GoogleCSE HTTP ' . $response->getStatusCode() . ': ' . $response->getStringBody());
        }
        $body = json_decode((string)$response->getBody(), true);
        $items = $body['items'] ?? [];
        $out = [];
        foreach ($items as $r) {
            $out[] = [
                'url'     => (string)($r['link'] ?? ''),
                'title'   => (string)($r['title'] ?? ''),
                'snippet' => (string)($r['snippet'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Filtruje wyniki - zostaw tylko URL LinkedIn zawierajace $mustContain.
     * Normalizuje URL (strip query params, trailing slash).
     */
    private function filterLinkedInResults(array $results, string $mustContain): array
    {
        $out = [];
        $seen = [];
        foreach ($results as $r) {
            $url = (string)($r['url'] ?? '');
            if (stripos($url, 'linkedin.com') === false) continue;
            if (stripos($url, $mustContain) === false) continue;
            // Normalize: strip query + trailing /
            $normalized = preg_replace('/\?.*$/', '', $url);
            $normalized = rtrim($normalized, '/');
            // Force https
            $normalized = preg_replace('#^http://#i', 'https://', $normalized);
            if (isset($seen[$normalized])) continue;
            $seen[$normalized] = true;
            $out[] = [
                'url'     => $normalized,
                'title'   => trim((string)($r['title'] ?? '')),
                'snippet' => trim((string)($r['snippet'] ?? '')),
            ];
            if (count($out) >= 5) break;
        }
        return $out;
    }

    private function saveCache($Cache, string $hash, string $query, array $results, ?int $status, ?string $error): void
    {
        try {
            $existing = $Cache->find()->where(['query_hash' => $hash])->first();
            $entity = $existing ?: $Cache->newEmptyEntity();
            $entity->query_hash = $hash;
            $entity->query_text = mb_substr($query, 0, 500);
            $entity->provider = $this->provider;
            $entity->results_json = json_encode($results, JSON_UNESCAPED_UNICODE);
            $entity->result_count = count($results);
            $entity->http_status = $status;
            $entity->error = $error;
            $entity->fetched_at = new DateTime();
            $Cache->save($entity);
        } catch (\Throwable $e) {
            Log::warning('LinkedinSearch saveCache failed: ' . $e->getMessage());
        }
    }
}

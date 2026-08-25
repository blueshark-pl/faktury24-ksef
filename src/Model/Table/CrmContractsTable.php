<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CRM Contracts - kontrakty ramowe z cennikami.
 *
 * @method \App\Model\Entity\CrmContract newEmptyEntity()
 * @method \App\Model\Entity\CrmContract get($id, array|string $finder = 'all', ?\Psr\SimpleCache\CacheInterface|string $cache = null, ?string $cacheKey = null, array $cacheOptions = [])
 */
class CrmContractsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_contracts');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Contractors', ['foreignKey' => 'contractor_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('name')->notEmptyString('name')->maxLength('name', 200)
            ->decimal('price_netto')->notEmptyString('price_netto')
            ->allowEmptyString('contractor_nip')->maxLength('contractor_nip', 30)
            ->allowEmptyString('from_country')->maxLength('from_country', 2)
            ->allowEmptyString('to_country')->maxLength('to_country', 2)
            ->range('vat_rate', [0, 100], 'VAT 0-100%');
        return $validator;
    }

    /**
     * Znajdz najlepiej pasujacy aktywny kontrakt dla danej trasy + klienta.
     *
     * Priorytet matchowania:
     *   1. Exact match: NIP + from_city + to_city
     *   2. NIP + from_country + to_country
     *   3. NIP tylko (jesli klient ma umowe globalna bez trasy)
     *
     * @param string $companyId  Multi-tenant
     * @param string|null $nip   NIP klienta (buyer_nip)
     * @param array $route       ['from_country', 'from_city', 'to_country', 'to_city']
     * @return \App\Model\Entity\CrmContract|null
     */
    public function findBestMatch(string $companyId, ?string $nip, array $route)
    {
        if (empty($nip)) return null;
        $nip = preg_replace('/[^A-Z0-9]/', '', strtoupper($nip));
        if ($nip === '') return null;

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        // Baza zapytania - aktywny + waznosc
        $baseWhere = [
            'company_id'    => $companyId,
            'is_active'     => true,
            'contractor_nip' => $nip,
            'OR' => [
                'valid_from IS' => null,
                'valid_from <=' => $today,
            ],
            'AND' => [
                'OR' => [
                    'valid_to IS' => null,
                    'valid_to >=' => $today,
                ],
            ],
        ];

        $fromCity = strtolower(trim((string)($route['from_city'] ?? '')));
        $toCity   = strtolower(trim((string)($route['to_city'] ?? '')));
        $fromCountry = strtoupper(trim((string)($route['from_country'] ?? '')));
        $toCountry   = strtoupper(trim((string)($route['to_country'] ?? '')));

        // 1. Match exact - city+city
        if ($fromCity !== '' && $toCity !== '') {
            $exact = $this->find()->where($baseWhere)
                ->where([
                    'LOWER(from_city) LIKE' => '%' . $fromCity . '%',
                    'LOWER(to_city) LIKE'   => '%' . $toCity . '%',
                ])
                ->orderByDesc('modified')
                ->first();
            if ($exact) return $exact;
        }

        // 2. Match country - country+country
        if ($fromCountry !== '' && $toCountry !== '') {
            $countryMatch = $this->find()->where($baseWhere)
                ->where([
                    'from_country' => $fromCountry,
                    'to_country'   => $toCountry,
                ])
                ->orderByDesc('modified')
                ->first();
            if ($countryMatch) return $countryMatch;
        }

        // 3. Match global - tylko NIP (kontrakt bez trasy)
        return $this->find()->where($baseWhere)
            ->where([
                'from_country IS' => null,
                'to_country IS'   => null,
            ])
            ->orderByDesc('modified')
            ->first();
    }

    /**
     * Wygasajace kontrakty - dla alertu w dashboard/cron.
     */
    public function findExpiringSoon(string $companyId, int $days = 30): array
    {
        $limit = (new \DateTimeImmutable("+{$days} days"))->format('Y-m-d');
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        return $this->find()
            ->where([
                'company_id' => $companyId,
                'is_active'  => true,
                'valid_to IS NOT' => null,
                'valid_to >='     => $today,
                'valid_to <='     => $limit,
            ])
            ->orderByAsc('valid_to')
            ->toArray();
    }

    /**
     * Auto-increment used_volume dla kontraktu po utworzeniu zlecenia.
     * Best-effort.
     */
    public function incrementUsedVolume(string $contractId): void
    {
        try {
            $c = $this->get($contractId);
            $c->used_volume = ((int)$c->used_volume) + 1;
            $this->save($c);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('CrmContracts::incrementUsedVolume failed: ' . $e->getMessage());
        }
    }

    /**
     * FALA 20: Sugeruj cene dla shipment (kaskada 3-poziomowa).
     *
     * Kolejnosc:
     *   1. Kontrakt ramowy dla tego klienta + trasy (100% pewnosci)
     *   2. Historia zlecen (speed_orders) dla tej trasy - mediana z ost. 6 mies
     *   3. Historia rynkowa (bez klienta) - fallback average
     *
     * @return array {
     *   price: float,
     *   currency: string,
     *   source: 'contract'|'history_client'|'history_market'|'unknown',
     *   reason: string (human-readable),
     *   confidence: 'high'|'medium'|'low',
     *   sample_size: int (ile rekordow uzyto do mediany)
     * }
     */
    public function suggestPrice(string $companyId, ?string $nip, array $shipment, ?string $ownCompanyNip = null): array
    {
        $route = [
            'from_country' => (string)($shipment['from_country'] ?? ''),
            'from_city'    => (string)($shipment['from_city'] ?? ''),
            'to_country'   => (string)($shipment['to_country'] ?? ''),
            'to_city'      => (string)($shipment['to_city'] ?? ''),
        ];

        // POZIOM 1: kontrakt ramowy dla klienta
        if (!empty($nip)) {
            $contract = $this->findBestMatch($companyId, $nip, $route);
            if ($contract && (float)$contract->price_netto > 0) {
                $matchLevel = 'unknown';
                if (!empty($contract->from_city) && !empty($contract->to_city)) $matchLevel = 'city';
                elseif (!empty($contract->from_country) && !empty($contract->to_country)) $matchLevel = 'country';
                else $matchLevel = 'global';
                return [
                    'price' => (float)$contract->price_netto,
                    'currency' => $contract->currency ?: 'EUR',
                    'source' => 'contract',
                    'reason' => sprintf('Kontrakt "%s" (%s match)', $contract->name, $matchLevel),
                    'confidence' => $matchLevel === 'city' ? 'high' : ($matchLevel === 'country' ? 'medium' : 'low'),
                    'sample_size' => 1,
                    'contract_id' => $contract->id,
                ];
            }
        }

        // POZIOM 2 + 3: historia speed_orders dla tej trasy
        try {
            $SO = \Cake\ORM\TableRegistry::getTableLocator()->get('SpeedOrders');
            $sinceDate = (new \DateTimeImmutable('-6 months'))->format('Y-m-d');
            $baseWhere = [
                'netto > ' => 0,
                'date_doc >=' => $sinceDate,
            ];
            if ($ownCompanyNip) $baseWhere['company_nip'] = $ownCompanyNip;

            // Try 1: same klient (buyer_nip) + route city LIKE
            if (!empty($nip) && !empty($route['from_city']) && !empty($route['to_city'])) {
                $rows = $SO->find()->where($baseWhere)
                    ->where([
                        'buyer_nip' => $nip,
                        'LOWER(load_city) LIKE' => '%' . strtolower($route['from_city']) . '%',
                        'LOWER(unload_city) LIKE' => '%' . strtolower($route['to_city']) . '%',
                    ])
                    ->select(['netto', 'currency', 'date_doc', 'currency_exchange'])
                    ->limit(50)->all()->toArray();
                if (count($rows) >= 1) {
                    return $this->buildPriceSuggestion($rows, 'history_client',
                        sprintf('Historia %d zleceń dla klienta na tej trasie (6 mies)', count($rows)));
                }
            }

            // Try 2: any klient + route (mediana rynkowa)
            if (!empty($route['from_city']) && !empty($route['to_city'])) {
                $rows = $SO->find()->where($baseWhere)
                    ->where([
                        'LOWER(load_city) LIKE' => '%' . strtolower($route['from_city']) . '%',
                        'LOWER(unload_city) LIKE' => '%' . strtolower($route['to_city']) . '%',
                    ])
                    ->select(['netto', 'currency', 'date_doc', 'currency_exchange'])
                    ->limit(100)->all()->toArray();
                if (count($rows) >= 3) {
                    return $this->buildPriceSuggestion($rows, 'history_market',
                        sprintf('Mediana %d zleceń rynkowych na tej trasie (6 mies)', count($rows)));
                }
            }

            // Try 3: same kraje (bez miast)
            if (!empty($route['from_country']) && !empty($route['to_country'])) {
                $rows = $SO->find()->where($baseWhere)
                    ->where([
                        'load_country' => strtoupper($route['from_country']),
                        'unload_country' => strtoupper($route['to_country']),
                    ])
                    ->select(['netto', 'currency', 'date_doc', 'currency_exchange'])
                    ->limit(100)->all()->toArray();
                if (count($rows) >= 5) {
                    return $this->buildPriceSuggestion($rows, 'history_market',
                        sprintf('Mediana %d zleceń dla kierunku %s→%s (6 mies)', count($rows),
                            strtoupper($route['from_country']), strtoupper($route['to_country'])));
                }
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('suggestPrice history query failed: ' . $e->getMessage());
        }

        return [
            'price' => 0,
            'currency' => 'EUR',
            'source' => 'unknown',
            'reason' => 'Brak danych historycznych ani kontraktu dla tej trasy',
            'confidence' => 'low',
            'sample_size' => 0,
        ];
    }

    /**
     * Wylicz mediane netto ze zlecen (uwzglednia currency_exchange -> PLN i konwertuje z powrotem do EUR default)
     */
    private function buildPriceSuggestion(array $rows, string $source, string $reason): array
    {
        // Zbierz wszystkie kwoty w PLN dla porownawczej mediany
        $amountsPln = [];
        $currencies = [];
        foreach ($rows as $r) {
            $netto = (float)$r->netto;
            $fx = (float)($r->currency_exchange ?: 1);
            $currencyOrig = strtoupper((string)($r->currency ?: 'PLN'));
            $currencies[$currencyOrig] = ($currencies[$currencyOrig] ?? 0) + 1;
            $amountsPln[] = $currencyOrig === 'PLN' ? $netto : $netto * $fx;
        }
        sort($amountsPln);
        $n = count($amountsPln);
        $median = $n > 0 ? $amountsPln[intdiv($n, 2)] : 0;

        // Wybierz dominujaca walute
        arsort($currencies);
        $dominantCurrency = key($currencies) ?: 'EUR';

        // Convert median z PLN na dominant currency (approx: mediana FX z rows)
        $priceInDominant = $median;
        if ($dominantCurrency !== 'PLN') {
            // Znajdz sredni FX dla tej waluty w rows
            $fxs = [];
            foreach ($rows as $r) {
                if (strtoupper((string)($r->currency ?: 'PLN')) === $dominantCurrency && (float)$r->currency_exchange > 0) {
                    $fxs[] = (float)$r->currency_exchange;
                }
            }
            if (!empty($fxs)) {
                sort($fxs);
                $avgFx = $fxs[intdiv(count($fxs), 2)];
                $priceInDominant = $median / $avgFx;
            }
        }

        return [
            'price' => round($priceInDominant, 2),
            'currency' => $dominantCurrency,
            'source' => $source,
            'reason' => $reason,
            'confidence' => $n >= 5 ? 'high' : ($n >= 3 ? 'medium' : 'low'),
            'sample_size' => $n,
        ];
    }
}

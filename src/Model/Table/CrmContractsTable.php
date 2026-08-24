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
}

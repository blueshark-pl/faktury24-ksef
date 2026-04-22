<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Tabela wniosków o kredyt kupiecki (Allianz Trade / Syntesys).
 */
class CreditChecksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('credit_checks');
        $this->setDisplayField('identifier');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Opcjonalne: powiązanie z kontrahentami (po NIP, opcjonalne FK)
        $this->belongsTo('Contractors', [
            'foreignKey' => 'contractor_id',
            'joinType'   => 'LEFT',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('external_id')
            ->requirePresence('external_id', 'create')
            ->notEmptyString('external_id');

        $validator
            ->scalar('list_status')
            ->requirePresence('list_status', 'create')
            ->notEmptyString('list_status')
            ->inList('list_status', ['WITH_OPINION', 'PROCESSING', 'NO_OPINION', 'BUSINESS_ERROR']);

        $validator
            ->scalar('identifier')
            ->maxLength('identifier', 50)
            ->allowEmptyString('identifier');

        return $validator;
    }

    /**
     * Upsert listy rekordów z API syntesys.
     *
     * Jeśli rekord z danym external_id istnieje — aktualizuje pola.
     * Jeśli nie istnieje — wstawia nowy.
     *
     * @param array $items   Tablica surowych danych z API
     * @param string $listStatus  Np. 'WITH_OPINION'
     * @return array{inserted: int, updated: int, errors: int}
     */
    public function upsertFromApi(array $items, string $listStatus): array
    {
        $inserted = 0;
        $updated  = 0;
        $errors   = 0;
        $now      = new \Cake\I18n\DateTime();

        foreach ($items as $item) {
            $externalId = (int)($item['id'] ?? 0);
            if ($externalId <= 0) {
                $errors++;
                continue;
            }

            // Parsuj pola zagnieżdżone
            $req    = is_array($item['request'] ?? null) ? $item['request'] : [];
            $advice = $item['advice'] ?? null;
            $client = is_array($item['client'] ?? null) ? $item['client'] : null;

            // NIP: z request.identifier, fallback na client.taxNumber
            $identifier = $req['identifier'] ?? ($client['taxNumber'] ?? null);
            // Kraj: z request.country, fallback na client.address.country
            $country = $req['country']
                ?? (is_array($client['address'] ?? null) ? ($client['address']['country'] ?? null) : null);

            $data = [
                'external_id'                  => $externalId,
                'list_status'                  => $listStatus,
                'identifier'                   => $identifier,
                'identifier_type_code'         => $req['identifierTypeCode'] ?? null,
                'country'                      => $country,
                'advice_type_code'             => is_array($advice) ? ($advice['typeCode'] ?? null) : null,
                'advice_reason_code'           => is_array($advice) ? ($advice['reasonCode'] ?? null) : null,
                'advice_json'                  => $advice !== null ? json_encode($advice) : null,
                'client_json'                  => $client !== null ? json_encode($client) : null,
                'status_code'                  => $item['statusCode'] ?? null,
                'error_type_code'              => $item['errorTypeCode'] ?? null,
                'advice_created_at'            => !empty($item['created']) ? new \Cake\I18n\DateTime($item['created']) : null,
                'created_by'                   => $item['createdBy'] ?? null,
                'latest_advice_with_opinion'   => (bool)($item['latestAdviceWithOpinion'] ?? false),
                'automatic_renewal_excluded'   => (bool)($item['automaticRenewalExcluded'] ?? false),
                'created_by_automatic_renewal' => (bool)($item['createdByAutomaticRenewal'] ?? false),
                'synced_at'                    => $now,
            ];

            // Spróbuj dopasować kontrahenta po NIP
            if (!empty($data['identifier'])) {
                $nip = preg_replace('/\D/', '', (string)$data['identifier']);
                if (strlen($nip) >= 9) {
                    $contractor = $this->Contractors->find()
                        ->where(['REPLACE(tax_id, \'-\', \'\') LIKE' => $nip])
                        ->select(['id'])
                        ->first();
                    if ($contractor !== null) {
                        $data['contractor_id'] = $contractor->id;
                    }
                }
            }

            // Szukaj istniejącego rekordu
            $existing = $this->find()->where(['external_id' => $externalId])->first();

            if ($existing !== null) {
                $entity = $this->patchEntity($existing, $data);
            } else {
                $entity = $this->newEntity($data);
                $data['synced_at'] = $now;
            }

            if ($this->save($entity)) {
                if ($existing !== null) {
                    $updated++;
                } else {
                    $inserted++;
                }
            } else {
                $errors++;
            }
        }

        return compact('inserted', 'updated', 'errors');
    }
}

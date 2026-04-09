<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class KsefInvoiceStatusesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('ksef_invoice_statuses');
        $this->setEntityClass('KsefInvoiceStatus');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('company_id');
        $validator->notEmptyString('environment');
        $validator->notEmptyString('ksef_number');
        $validator->integer('cost_status')->range('cost_status', [1, 9]);
        $validator->allowEmptyDate('docs_received_at');
        $validator->allowEmptyDate('payment_due_date');
        $validator->allowEmptyString('rejection_reason');
        $validator->allowEmptyString('notes');
        return $validator;
    }

    /**
     * Zwraca istniejący rekord lub tworzy nowy (upsert po kluczu naturalnym).
     */
    public function upsert(string $companyId, string $environment, string $ksefNumber, array $data): \App\Model\Entity\KsefInvoiceStatus
    {
        $existing = $this->find()->where([
            'company_id'  => $companyId,
            'environment' => $environment,
            'ksef_number' => $ksefNumber,
        ])->first();

        if ($existing) {
            $entity = $this->patchEntity($existing, $data);
        } else {
            $entity = $this->newEntity(array_merge([
                'id'          => \Cake\Utility\Text::uuid(),
                'company_id'  => $companyId,
                'environment' => $environment,
                'ksef_number' => $ksefNumber,
            ], $data));
        }

        return $entity;
    }

    /**
     * Pobiera mapę ksef_number => status record dla listy numerów.
     * Używane do załadowania statusów dla całej strony faktur w received().
     *
     * @param  string   $companyId
     * @param  string   $environment
     * @param  string[] $ksefNumbers
     * @return array<string, \App\Model\Entity\KsefInvoiceStatus>
     */
    public function fetchForNumbers(string $companyId, string $environment, array $ksefNumbers): array
    {
        if (empty($ksefNumbers)) {
            return [];
        }
        $rows = $this->find()->where([
            'company_id'     => $companyId,
            'environment'    => $environment,
            'ksef_number IN' => $ksefNumbers,
        ])->all();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->ksef_number] = $row;
        }
        return $map;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class LegacyInvoicesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('legacy_invoices');
        $this->setDisplayField('fullnumber');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                // synced_at ustawiamy ręcznie — nie używamy auto-timestamps
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('company_id')
            ->notEmptyString('fullnumber')
            ->notEmptyString('contractor_name')
            ->integer('glo_id')
            ->integer('rejestr');

        return $validator;
    }
}

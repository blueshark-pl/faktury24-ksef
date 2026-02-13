<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class KsefBookingItemsTable extends Table
{
    public static function defaultConnectionName(): string
    {
        return parent::defaultConnectionName();
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('ksef_booking_items');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);

        $this->getSchema()
            ->setColumnType('source_json', 'text');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator->requirePresence('company_id', 'create')->notEmptyString('company_id');
        $validator->requirePresence('environment', 'create')->notEmptyString('environment');
        $validator->requirePresence('ksef_number', 'create')->notEmptyString('ksef_number');
        $validator->integer('line_index');

        return $validator;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

class TransportAddressesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('transport_addresses');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');
        $this->addBehavior('Timestamp');
    }

    public function newEmptyEntity(): EntityInterface
    {
        $entity = parent::newEmptyEntity();
        $entity->set('id', Text::uuid(), ['guard' => false, 'setter' => false]);
        return $entity;
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name', 'Nazwa miejsca jest wymagana.')
            ->maxLength('name', 255)
            ->maxLength('city', 120)
            ->maxLength('postal_code', 20)
            ->maxLength('country', 5);

        $validator->add('country', 'upper', [
            'rule' => function ($value) {
                return $value === strtoupper((string)$value);
            },
            'message' => 'Kod kraju musi być UPPERCASE.',
        ]);

        return $validator;
    }
}

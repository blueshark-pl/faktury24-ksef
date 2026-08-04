<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderTemplatesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_templates');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('name')->notEmptyString('name', 'Nazwa jest wymagana')
            ->maxLength('name', 150)
            ->scalar('payload_json')->notEmptyString('payload_json');

        return $validator;
    }
}

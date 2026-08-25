<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class LeadLabelsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('lead_labels');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Leads', [
            'joinTable' => 'leads_lead_labels',
            'foreignKey' => 'label_id',
            'targetForeignKey' => 'lead_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('name')->notEmptyString('name')->maxLength('name', 60)
            ->scalar('color')->notEmptyString('color')->maxLength('color', 7)
            ->add('color', 'hex', ['rule' => ['custom', '/^#[0-9a-fA-F]{6}$/'], 'message' => 'Kolor musi byc #hex (7 znakow)'])
            ->integer('sort_order');
        return $validator;
    }
}

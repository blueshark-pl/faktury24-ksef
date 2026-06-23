<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CostInvoiceLinesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cost_invoice_lines');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('CostInvoices', [
            'foreignKey' => 'cost_invoice_id',
        ]);
        $this->belongsTo('CostCategories', [
            'foreignKey' => 'cost_category_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('cost_invoice_id')
            ->notEmptyString('cost_invoice_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 500);

        return $validator;
    }
}

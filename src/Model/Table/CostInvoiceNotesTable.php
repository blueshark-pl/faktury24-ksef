<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CostInvoiceNotesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cost_invoice_notes');
        $this->setDisplayField('body');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('CostInvoices', ['foreignKey' => 'cost_invoice_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
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
            ->scalar('body')
            ->notEmptyString('body');

        $validator
            ->scalar('note_type')
            ->inList('note_type', ['note', 'system', 'reminder', 'phone_call', 'email']);

        return $validator;
    }
}

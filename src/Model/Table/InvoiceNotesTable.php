<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class InvoiceNotesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_notes');
        $this->setDisplayField('body');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->scalar('note_type')
            ->maxLength('note_type', 20)
            ->inList('note_type', ['note', 'system', 'reminder', 'phone_call', 'email']);

        $validator
            ->scalar('body')
            ->notEmptyString('body');

        return $validator;
    }
}

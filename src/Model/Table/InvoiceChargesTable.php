<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceCharges Model — Rozliczenie: Obciążenia / Odliczenia
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceCharge newEmptyEntity()
 * @method \App\Model\Entity\InvoiceCharge newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceCharge patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceChargesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_charges');
        $this->setDisplayField('powod');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('invoice_id')
            ->notEmptyString('invoice_id');

        $validator
            ->requirePresence('type', 'create')
            ->inList('type', ['obciazenie', 'odliczenie']);

        $validator
            ->decimal('kwota')
            ->notEmptyString('kwota');

        $validator
            ->scalar('powod')
            ->notEmptyString('powod');

        return $validator;
    }
}

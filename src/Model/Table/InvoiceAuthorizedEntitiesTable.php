<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceAuthorizedEntities Model — PodmiotUpowazniony
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceAuthorizedEntity newEmptyEntity()
 * @method \App\Model\Entity\InvoiceAuthorizedEntity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceAuthorizedEntity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceAuthorizedEntitiesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_authorized_entities');
        $this->setDisplayField('name');
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
            ->integer('rola')
            ->notEmptyString('rola');

        return $validator;
    }
}

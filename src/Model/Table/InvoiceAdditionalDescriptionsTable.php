<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceAdditionalDescriptions Model
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceAdditionalDescription newEmptyEntity()
 * @method \App\Model\Entity\InvoiceAdditionalDescription newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceAdditionalDescription patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceAdditionalDescriptionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_additional_descriptions');
        $this->setDisplayField('klucz');
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
            ->requirePresence('klucz', 'create')
            ->notEmptyString('klucz');

        $validator
            ->requirePresence('wartosc', 'create')
            ->notEmptyString('wartosc');

        $validator
            ->integer('nr_wiersza')
            ->allowEmptyString('nr_wiersza');

        return $validator;
    }
}

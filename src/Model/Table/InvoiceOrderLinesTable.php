<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceOrderLines Model — Zamówienie → ZamowienieWiersz
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceOrderLine newEmptyEntity()
 * @method \App\Model\Entity\InvoiceOrderLine newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceOrderLine patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceOrderLinesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_order_lines');
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
            ->integer('nr_wiersza')
            ->notEmptyString('nr_wiersza');

        return $validator;
    }
}

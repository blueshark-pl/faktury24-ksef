<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceNewTransports Model — NoweSrodkiTransportu
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceNewTransport newEmptyEntity()
 * @method \App\Model\Entity\InvoiceNewTransport newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceNewTransport patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceNewTransportsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_new_transports');
        $this->setDisplayField('p_22bmk');
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

        return $validator;
    }
}

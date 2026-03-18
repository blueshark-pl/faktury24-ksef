<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceFactorBanks Model — RachunekBankowyFaktora
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceFactorBank newEmptyEntity()
 * @method \App\Model\Entity\InvoiceFactorBank newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceFactorBank patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class InvoiceFactorBanksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_factor_banks');
        $this->setDisplayField('nr_rb');
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
            ->scalar('nr_rb')
            ->maxLength('nr_rb', 64)
            ->notEmptyString('nr_rb');

        return $validator;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoicePayments Model
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoicePayment newEmptyEntity()
 * @method \App\Model\Entity\InvoicePayment newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoicePayment> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoicePayment get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoicePayment findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoicePayment patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoicePayment> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoicePayment|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoicePayment saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoicePayment>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoicePayment>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoicePayment>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoicePayment> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoicePayment>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoicePayment>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoicePayment>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoicePayment> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoicePaymentsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('invoice_payments');
        $this->setDisplayField('payment_method');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('invoice_id')
            ->notEmptyString('invoice_id');

        $validator
            ->date('payment_date')
            ->requirePresence('payment_date', 'create')
            ->notEmptyDate('payment_date');

        $validator
            ->decimal('amount')
            ->greaterThan('amount', 0)
            ->requirePresence('amount', 'create')
            ->notEmptyString('amount');

        $validator
            ->scalar('payment_method')
            ->maxLength('payment_method', 50)
            ->notEmptyString('payment_method');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['invoice_id'], 'Invoices'), ['errorField' => 'invoice_id']);

        return $rules;
    }

    /**
     * After save callback - recalculate invoice totals
     */
    public function afterSave($event, $entity, $options)
    {
        $this->recalculateInvoicePayments($entity->invoice_id);
    }

    /**
     * After delete callback - recalculate invoice totals
     */
    public function afterDelete($event, $entity, $options)
    {
        $this->recalculateInvoicePayments($entity->invoice_id);
    }

    /**
     * Recalculate invoice payment totals
     */
    protected function recalculateInvoicePayments($invoiceId)
    {
        // Use association target instead of getTableLocator() inside Table class
        return $this->Invoices->recalculatePayments($invoiceId);
    }
}

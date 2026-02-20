<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Invoices Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $ParentInvoices
 * @property \App\Model\Table\InvoiceCompanyDetailsTable&\Cake\ORM\Association\HasOne $InvoiceCompanyDetails
 * @property \App\Model\Table\InvoiceContractorsTable&\Cake\ORM\Association\HasOne $InvoiceContractors
 * @property \App\Model\Table\InvoiceContentsTable&\Cake\ORM\Association\HasMany $InvoiceContents
 * @property \App\Model\Table\InvoiceVatContentsTable&\Cake\ORM\Association\HasMany $InvoiceVatContents
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\HasMany $ChildInvoices
 *
 * @method \App\Model\Entity\Invoice newEmptyEntity()
 * @method \App\Model\Entity\Invoice newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Invoice> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Invoice get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Invoice findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Invoice patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Invoice> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Invoice|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Invoice saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Invoice>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Invoice>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Invoice>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Invoice> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Invoice>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Invoice>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Invoice>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Invoice> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoicesTable extends Table
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

        $this->setTable('invoices');
        $this->setDisplayField('currency');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ParentInvoices', [
            'className' => 'Invoices',
            'foreignKey' => 'parent_id',
        ]);
        $this->belongsTo('InvoiceSeries', [
            'foreignKey' => 'invoice_series_id',
        ]);
        $this->hasOne('InvoiceCompanyDetails', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->hasOne('InvoiceContractors', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->hasMany('InvoiceContents', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->hasMany('InvoiceVatContents', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->hasMany('ChildInvoices', [
            'className' => 'Invoices',
            'foreignKey' => 'parent_id',
        ]);
        $this->hasMany('InvoicePayments', [
            'foreignKey' => 'invoice_id',
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
            ->scalar('hash')
            ->maxLength('hash', 64)
            ->allowEmptyString('hash');

        $validator
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->uuid('parent_id')
            ->allowEmptyString('parent_id');

        $validator
            ->uuid('invoice_series_id')
            ->allowEmptyString('invoice_series_id');

        $validator
            ->scalar('type')
            ->maxLength('type', 24)
            ->allowEmptyString('type');

        $validator
            ->scalar('correction_type')
            ->maxLength('correction_type', 24)
            ->allowEmptyString('correction_type');

        $validator
            ->boolean('simplified_invoice')
            ->notEmptyString('simplified_invoice');

        $validator
            ->scalar('paymentmethod')
            ->maxLength('paymentmethod', 32)
            ->allowEmptyString('paymentmethod');

        $validator
            ->date('paymentdate')
            ->allowEmptyDate('paymentdate');

        $validator
            ->scalar('paymentstate')
            ->maxLength('paymentstate', 16)
            ->allowEmptyString('paymentstate');

        $validator
            ->date('date')
            ->requirePresence('date', 'create')
            ->notEmptyDate('date');

        $validator
            ->decimal('total')
            ->notEmptyString('total');

        $validator
            ->decimal('netto')
            ->notEmptyString('netto');

        $validator
            ->decimal('tax')
            ->notEmptyString('tax');

        $validator
            ->decimal('alreadypaid')
            ->notEmptyString('alreadypaid');

        $validator
            ->decimal('remaining')
            ->notEmptyString('remaining');

        $validator
            ->scalar('fullnumber')
            ->maxLength('fullnumber', 64)
            ->allowEmptyString('fullnumber');

        $validator
            ->integer('number')
            ->allowEmptyString('number');

        $validator
            ->integer('day')
            ->range('day', [1, 31])
            ->allowEmptyString('day');

        $validator
            ->integer('month')
            ->range('month', [1, 12])
            ->allowEmptyString('month');

        $validator
            ->integer('year')
            ->range('year', [1900, 2100])
            ->allowEmptyString('year');

        $validator
            ->integer('day_year')
            ->range('day_year', [1, 366])
            ->allowEmptyString('day_year');

        $validator
            ->scalar('currency')
            ->maxLength('currency', 8)
            ->notEmptyString('currency');

        $validator
            ->date('currency_date')
            ->allowEmptyDate('currency_date');

        $validator
            ->decimal('currency_exchange')
            ->notEmptyString('currency_exchange');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        // Optional: margin procedure type (used_goods/art/collectibles/travel)
        $validator
            ->scalar('margin_type')
            ->maxLength('margin_type', 32)
            ->allowEmptyString('margin_type');

        $validator
            ->boolean('is_print')
            ->notEmptyString('is_print');

        $validator
            ->boolean('is_sent')
            ->notEmptyString('is_sent');

        $validator
            ->boolean('is_api')
            ->notEmptyString('is_api');

        $validator
            ->scalar('workflow_status')
            ->maxLength('workflow_status', 24)
            ->allowEmptyString('workflow_status');

        $validator
            ->date('planned_ksef_send_at')
            ->allowEmptyDate('planned_ksef_send_at');

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
        $rules->add($rules->existsIn(['company_id'], 'Companies'), ['errorField' => 'company_id']);
        $rules->add($rules->existsIn(['parent_id'], 'ParentInvoices'), ['errorField' => 'parent_id']);

        return $rules;
    }

    /**
     * Recalculate invoice payment totals
     */
    public function recalculatePayments($invoiceId)
    {
        $invoice = $this->get($invoiceId, [
            'contain' => ['InvoicePayments']
        ]);

        // Sumuj wszystkie płatności
        $totalPaid = 0;
        if (!empty($invoice->invoice_payments)) {
            foreach ($invoice->invoice_payments as $payment) {
                $totalPaid += $payment->amount;
            }
        }

        // Oblicz pozostałą kwotę
        $remaining = $invoice->total - $totalPaid;
        
        // Określ status płatności
        $paymentstate = 'unpaid';
        if ($totalPaid > 0) {
            if ($remaining <= 0) {
                $paymentstate = 'paid';
                $remaining = 0;
            } else {
                $paymentstate = 'partial';
            }
        }

        // Zaktualizuj fakturę
        $this->patchEntity($invoice, [
            'alreadypaid' => $totalPaid,
            'remaining' => $remaining,
            'paymentstate' => $paymentstate
        ]);

        return $this->save($invoice);
    }
}

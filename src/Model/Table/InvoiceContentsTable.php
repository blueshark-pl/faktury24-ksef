<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceContents Model
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 * @property \App\Model\Table\VatsTable&\Cake\ORM\Association\BelongsTo $Vats
 *
 * @method \App\Model\Entity\InvoiceContent newEmptyEntity()
 * @method \App\Model\Entity\InvoiceContent newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceContent> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceContent get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoiceContent findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoiceContent patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceContent> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceContent|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoiceContent saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceContent>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceContent> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceContent>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceContent> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoiceContentsTable extends Table
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

        $this->setTable('invoice_contents');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Vats', [
            'foreignKey' => 'vat_code_id',
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
            ->uuid('vat_code_id')
            ->allowEmptyString('vat_code_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('product_desc')
            ->allowEmptyString('product_desc');

        $validator
            ->decimal('quantity')
            ->notEmptyString('quantity');

        $validator
            ->scalar('unit')
            ->maxLength('unit', 16)
            ->allowEmptyString('unit');

        $validator
            ->decimal('price')
            ->notEmptyString('price');

        // Internal: purchase price (gross) per unit for margin invoices
        $validator
            ->decimal('purchase_price')
            ->allowEmptyString('purchase_price');

        $validator
            ->decimal('discount_percent')
            ->notEmptyString('discount_percent');

        $validator
            ->decimal('netto')
            ->notEmptyString('netto');

        $validator
            ->decimal('brutto')
            ->notEmptyString('brutto');

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
}

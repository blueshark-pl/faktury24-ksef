<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceVatContents Model
 *
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\BelongsTo $Invoices
 *
 * @method \App\Model\Entity\InvoiceVatContent newEmptyEntity()
 * @method \App\Model\Entity\InvoiceVatContent newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceVatContent> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceVatContent get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoiceVatContent findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoiceVatContent patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceVatContent> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceVatContent|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoiceVatContent saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceVatContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceVatContent>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceVatContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceVatContent> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceVatContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceVatContent>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceVatContent>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceVatContent> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoiceVatContentsTable extends Table
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

        $this->setTable('invoice_vat_contents');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Vats', [
            'foreignKey' => 'vat_code_id',
            'joinType' => 'LEFT',
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
            ->decimal('netto')
            ->notEmptyString('netto');

        $validator
            ->decimal('tax')
            ->notEmptyString('tax');

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

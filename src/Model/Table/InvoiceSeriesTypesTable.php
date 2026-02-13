<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceSeriesTypes Model
 *
 * @property \App\Model\Table\InvoiceSeriesTable&\Cake\ORM\Association\HasMany $InvoiceSeries
 *
 * @method \App\Model\Entity\InvoiceSeriesType newEmptyEntity()
 * @method \App\Model\Entity\InvoiceSeriesType newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeriesType> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesType get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoiceSeriesType findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeriesType> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesType|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesType saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesType>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesType> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesType>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesType>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesType> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoiceSeriesTypesTable extends Table
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

        $this->setTable('invoice_series_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('InvoiceSeriesPeriods', [
            'foreignKey' => 'invoice_series_period_id',
        ]);
        $this->hasMany('InvoiceSeries', [
            'foreignKey' => 'invoice_series_type_id',
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
            ->scalar('name')
            ->maxLength('name', 128)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->uuid('invoice_series_period_id')
            ->allowEmptyString('invoice_series_period_id');

        $validator
            ->scalar('series_template')
            ->maxLength('series_template', 128)
            ->allowEmptyString('series_template');

        $validator
            ->integer('starting_number')
            ->allowEmptyString('starting_number');

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
        $rules->add($rules->existsIn(['invoice_series_period_id'], 'InvoiceSeriesPeriods'), ['errorField' => 'invoice_series_period_id']);
        return $rules;
    }
}
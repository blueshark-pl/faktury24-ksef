<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceSeriesPeriods Model
 *
 * @property \App\Model\Table\InvoiceSeriesTable&\Cake\ORM\Association\HasMany $InvoiceSeries
 *
 * @method \App\Model\Entity\InvoiceSeriesPeriod newEmptyEntity()
 * @method \App\Model\Entity\InvoiceSeriesPeriod newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeriesPeriod> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesPeriod get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoiceSeriesPeriod findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesPeriod patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeriesPeriod> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesPeriod|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoiceSeriesPeriod saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesPeriod>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesPeriod> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesPeriod>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeriesPeriod>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeriesPeriod> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoiceSeriesPeriodsTable extends Table
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

        $this->setTable('invoice_series_periods');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('InvoiceSeries', [
            'foreignKey' => 'invoice_series_period_id',
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

        return $validator;
    }
}
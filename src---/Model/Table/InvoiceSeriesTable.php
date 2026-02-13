<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InvoiceSeries Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\InvoiceSeriesTypesTable&\Cake\ORM\Association\BelongsTo $InvoiceSeriesTypes
 * @property \App\Model\Table\InvoiceSeriesPeriodsTable&\Cake\ORM\Association\BelongsTo $InvoiceSeriesPeriods
 *
 * @method \App\Model\Entity\InvoiceSeries newEmptyEntity()
 * @method \App\Model\Entity\InvoiceSeries newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeries> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeries get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\InvoiceSeries findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\InvoiceSeries patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\InvoiceSeries> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\InvoiceSeries|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\InvoiceSeries saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeries>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeries>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeries>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeries> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeries>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeries>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\InvoiceSeries>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\InvoiceSeries> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class InvoiceSeriesTable extends Table
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

        $this->setTable('invoice_series');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('InvoiceSeriesTypes', [
            'foreignKey' => 'invoice_series_type_id',
        ]);
        $this->belongsTo('InvoiceSeriesPeriods', [
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
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->uuid('invoice_series_type_id')
            ->allowEmptyString('invoice_series_type_id');

        $validator
            ->uuid('invoice_series_period_id')
            ->allowEmptyString('invoice_series_period_id');

        $validator
            ->boolean('is_default')
            ->notEmptyString('is_default');

        $validator
            ->scalar('name')
            ->maxLength('name', 128)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('series_template')
            ->maxLength('series_template', 128)
            ->requirePresence('series_template', 'create')
            ->notEmptyString('series_template');

        $validator
            ->integer('starting_number')
            ->notEmptyString('starting_number');

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
        $rules->add($rules->existsIn(['invoice_series_type_id'], 'InvoiceSeriesTypes'), ['errorField' => 'invoice_series_type_id']);
        $rules->add($rules->existsIn(['invoice_series_period_id'], 'InvoiceSeriesPeriods'), ['errorField' => 'invoice_series_period_id']);

        return $rules;
    }

    /**
     * Copy all system invoice series (is_system = 1) for a freshly created company.
     * Each copied row:
     *  - parent_id     => original id
     *  - company_id    => $companyId
     *  - is_system     => 0 (so it becomes a regular series for that company)
     *  - is_blocked    => 1 (user cannot delete it)
     *  - is_default    => preserve original flag only if company has no other default yet
     *  - starting_number, name, series_template, invoice_series_type_id, invoice_series_period_id copied
     * Safe to call multiple times; it will not duplicate if already copied (parent_id match).
     *
     * @param string $companyId UUID of the new company
     * @return int Number of series copied
     */
    public function copySystemSeriesForCompany(string $companyId): int
    {
        // fetch system series
        $systemSeries = $this->find()
            ->where(['is_system' => 1])
            ->enableHydration(true)
            ->all();
        if (!$systemSeries->count()) {
            return 0;
        }

        // existing copies (parent_id matching system ids)
        $existingParentIds = $this->find()
            ->select(['parent_id'])
            ->where(['company_id' => $companyId, 'parent_id IN' => $systemSeries->extract('id')->toList()])
            ->enableHydration(false)
            ->all()
            ->extract('parent_id')
            ->toList();
        $existingParentIds = array_filter($existingParentIds); // remove nulls

        // (previously we avoided multiple defaults) – per request: force copied series to have is_default = 1

        $copied = 0;
        foreach ($systemSeries as $orig) {
            if ($orig->id && in_array($orig->id, $existingParentIds, true)) {
                continue; // already copied
            }
            $data = [
                'parent_id' => $orig->id,
                'company_id' => $companyId,
                'type' => $orig->type ?? null,
                'name' => $orig->name,
                'series_template' => $orig->series_template,
                'starting_number' => $orig->starting_number,
                'invoice_series_type_id' => $orig->invoice_series_type_id,
                'invoice_series_period_id' => $orig->invoice_series_period_id,
                'is_system' => 0,
                'is_blocked' => 1,
                // force default (per business request to copy with is_default = 1)
                'is_default' => 1,
            ];
            $entity = $this->newEntity($data, ['validate' => true]);
            if ($this->save($entity)) {
                $copied++;
                // no need to toggle flags; all copies marked default intentionally
            }
        }
        return $copied;
    }
}

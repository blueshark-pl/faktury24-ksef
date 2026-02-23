<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Log\Log;
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

        $systemSeriesList = $systemSeries->toList();
        $systemSeriesIds = array_values(array_filter(array_map(static fn ($row) => (string)($row->id ?? ''), $systemSeriesList)));
        if ($systemSeriesIds === []) {
            return 0;
        }

        // existing copies (parent_id matching system ids)
        $existingParentIds = $this->find()
            ->select(['parent_id'])
            ->where(['company_id' => $companyId, 'parent_id IN' => $systemSeriesIds])
            ->enableHydration(false)
            ->all()
            ->extract('parent_id')
            ->toList();
        $existingParentIds = array_filter($existingParentIds); // remove nulls

        // reference sets for nullable FK fields
        $typeIds = $this->InvoiceSeriesTypes->find()->select(['id'])->enableHydration(false)->all()->extract('id')->toList();
        $periodIds = $this->InvoiceSeriesPeriods->find()->select(['id'])->enableHydration(false)->all()->extract('id')->toList();
        $typeIdsMap = array_fill_keys(array_map('strval', $typeIds), true);
        $periodIdsMap = array_fill_keys(array_map('strval', $periodIds), true);

        // default handling: if company has no default, preserve original default from system (first fallback)
        $companyHasDefault = $this->find()
            ->where(['company_id' => $companyId, 'is_default' => 1])
            ->count() > 0;
        $defaultAssigned = $companyHasDefault;

        $copied = 0;
        foreach ($systemSeriesList as $orig) {
            if ($orig->id && in_array($orig->id, $existingParentIds, true)) {
                continue; // already copied
            }

            $typeId = (string)($orig->invoice_series_type_id ?? '');
            if ($typeId !== '' && !isset($typeIdsMap[$typeId])) {
                $typeId = '';
            }

            $periodId = (string)($orig->invoice_series_period_id ?? '');
            if ($periodId !== '' && !isset($periodIdsMap[$periodId])) {
                $periodId = '';
            }

            $isDefault = 0;
            if (!$defaultAssigned && !empty($orig->is_default)) {
                $isDefault = 1;
                $defaultAssigned = true;
            }

            $data = [
                // keep id auto-generated; map required fields explicitly
                'company_id' => $companyId,
                'invoice_series_type_id' => $typeId !== '' ? $typeId : null,
                'parent_id' => $orig->id,
                'invoice_series_period_id' => $periodId !== '' ? $periodId : null,
                'is_default' => $isDefault,
                'name' => $orig->name,
                'type' => $orig->type ?? 'vat',
                'series_template' => $orig->series_template,
                'starting_number' => (int)($orig->starting_number ?? 1),
                'is_system' => 0,
                'is_blocked' => 1,
            ];
            $entity = $this->newEntity($data, ['validate' => true]);
            if ($this->save($entity)) {
                $copied++;
            } else {
                Log::warning('Nie udało się skopiować serii systemowej: ' . json_encode($entity->getErrors()), ['series_init']);
            }
        }

        // fallback: if still no default for company, set first available as default
        if (!$defaultAssigned) {
            $first = $this->find()
                ->select(['id'])
                ->where(['company_id' => $companyId])
                ->orderAsc('created')
                ->first();
            if ($first) {
                $this->updateAll(['is_default' => 1], ['id' => $first->id, 'company_id' => $companyId]);
            }
        }

        return $copied;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ContractorBankAccounts Model
 *
 * @property \App\Model\Table\ContractorsTable&\Cake\ORM\Association\BelongsTo $Contractors
 *
 * @method \App\Model\Entity\ContractorBankAccount newEmptyEntity()
 * @method \App\Model\Entity\ContractorBankAccount newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ContractorBankAccount> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ContractorBankAccount get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ContractorBankAccount findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ContractorBankAccount patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ContractorBankAccount> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ContractorBankAccount|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ContractorBankAccount saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\ContractorBankAccount>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ContractorBankAccount>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ContractorBankAccount>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ContractorBankAccount> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ContractorBankAccount>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ContractorBankAccount>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ContractorBankAccount>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ContractorBankAccount> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ContractorBankAccountsTable extends Table
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

        $this->setTable('contractor_bank_accounts');
        $this->setDisplayField('label');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Contractors', [
            'foreignKey' => 'contractor_id',
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
            ->uuid('contractor_id')
            ->notEmptyString('contractor_id');

        $validator
            ->scalar('iban')
            ->maxLength('iban', 34)
            ->requirePresence('iban', 'create')
            ->notEmptyString('iban');

        $validator
            ->scalar('bank_name')
            ->maxLength('bank_name', 160)
            ->allowEmptyString('bank_name');

        $validator
            ->scalar('currency')
            ->maxLength('currency', 3)
            ->notEmptyString('currency');

        $validator
            ->boolean('is_default')
            ->notEmptyString('is_default');

        $validator
            ->scalar('label')
            ->maxLength('label', 120)
            ->allowEmptyString('label');

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
        $rules->add($rules->existsIn(['contractor_id'], 'Contractors'), ['errorField' => 'contractor_id']);

        return $rules;
    }
}

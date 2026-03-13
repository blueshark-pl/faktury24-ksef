<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CompanyRegisters Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 *
 * @method \App\Model\Entity\CompanyRegister newEmptyEntity()
 * @method \App\Model\Entity\CompanyRegister newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\CompanyRegister> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\CompanyRegister get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\CompanyRegister findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\CompanyRegister patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\CompanyRegister> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\CompanyRegister|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\CompanyRegister saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class CompanyRegistersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('company_registers');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 160)
            ->allowEmptyString('name');

        $validator
            ->scalar('krs')
            ->maxLength('krs', 20)
            ->allowEmptyString('krs');

        $validator
            ->scalar('regon')
            ->maxLength('regon', 20)
            ->allowEmptyString('regon');

        $validator
            ->scalar('bdo')
            ->maxLength('bdo', 20)
            ->allowEmptyString('bdo');

        return $validator;
    }
}

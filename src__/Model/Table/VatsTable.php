<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Vats Model
 *
 * @property \App\Model\Table\ServicesTable&\Cake\ORM\Association\HasMany $Services
 *
 * @method \App\Model\Entity\Vat newEmptyEntity()
 * @method \App\Model\Entity\Vat newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Vat> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Vat get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Vat findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Vat patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Vat> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Vat|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Vat saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Vat>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vat>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vat>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vat> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vat>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vat>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vat>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vat> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class VatsTable extends Table
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

        $this->setTable('vats');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Services', [
            'foreignKey' => 'vat_id',
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
            ->maxLength('name', 32)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->decimal('rate')
            ->requirePresence('rate', 'create')
            ->notEmptyString('rate');

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }
}

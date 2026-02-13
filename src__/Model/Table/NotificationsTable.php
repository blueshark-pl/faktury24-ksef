<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Notifications Model
 *
 * @property \CakeDC\Users\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\Notification newEmptyEntity()
 * @method \App\Model\Entity\Notification newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Notification> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Notification get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Notification findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Notification patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Notification> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Notification|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Notification saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Notification>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Notification>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Notification>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Notification> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Notification>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Notification>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Notification>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Notification> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class NotificationsTable extends Table
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

        $this->setTable('notifications');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('CakeDC/Users.Users', [
            'foreignKey' => 'user_id',
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
            ->uuid('user_id')
            ->allowEmptyString('user_id');

        $validator
            ->scalar('channel')
            ->maxLength('channel', 16)
            ->notEmptyString('channel');

        $validator
            ->scalar('type')
            ->maxLength('type', 64)
            ->notEmptyString('type');

        $validator
            ->scalar('severity')
            ->maxLength('severity', 16)
            ->notEmptyString('severity');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('message')
            ->allowEmptyString('message');

        $validator
            ->scalar('action_url')
            ->maxLength('action_url', 512)
            ->allowEmptyString('action_url');

        $validator
            ->scalar('action_label')
            ->maxLength('action_label', 64)
            ->allowEmptyString('action_label');

        $validator
            ->boolean('is_read')
            ->notEmptyString('is_read');

        $validator
            ->dateTime('read_at')
            ->allowEmptyDateTime('read_at');

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
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }
}

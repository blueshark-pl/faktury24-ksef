<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Uuid;

class CurrencyFavoritesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('currency_favorites');
        $this->setDisplayField('code');
        // Default PK
        $this->setPrimaryKey('id');
        // Detect actual PK column name from DB schema (some environments use `uuid`)
        try {
            $schema = $this->getSchema();
            if ($schema && $schema->hasColumn('uuid') && !$schema->hasColumn('id')) {
                $this->setPrimaryKey('uuid');
            }
        } catch (\Throwable $e) {
            // ignore; fallback to default
        }

        $this->addBehavior('Timestamp');

        // Optional association if Companies table exists
        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);

        // Remove custom type overrides; rely on Cake's introspection
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('company_id')->requirePresence('company_id', 'create')->notEmptyString('company_id')
            ->scalar('code')->lengthBetween('code', [3,3], 'Kod waluty powinien mieć 3 znaki')->requirePresence('code', 'create')->notEmptyString('code');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['company_id','code'], 'Ta waluta jest już na liście ulubionych.'));
        return $rules;
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, $options): void
    {
        // Normalize code to uppercase
        if ($entity->has('code') && is_string($entity->get('code'))){
            $entity->set('code', strtoupper($entity->get('code')));
        }
        // Ensure UUID primary key generated if not provided
        $pkNames = (array)$this->getPrimaryKey();
        if (count($pkNames) === 1) {
            $pkName = $pkNames[0];
            $pk = $entity->get($pkName);
            if (empty($pk)){
                try { $entity->set($pkName, Uuid::uuid4()->toString()); } catch (\Throwable $e) { /* ignore */ }
            }
        }
    }
}

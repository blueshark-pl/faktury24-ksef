<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class KsefAuthorizationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('ksef_authorizations');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', ['foreignKey'=>'company_id']);
        $this->hasMany('KsefDocuments', ['foreignKey'=>'authorization_id']);
    }

    public function validationDefault(Validator $v): Validator
    {
        return $v
            ->uuid('company_id')->requirePresence('company_id')->notEmptyString('company_id')
            ->scalar('environment')->inList('environment', ['prod','test'])
            ->boolean('is_active')
            ->scalar('status')->inList('status', ['active','revoked','expired','invalid','pending'])
            ->allowEmptyString('auth_method')
            ->allowEmptyString('scopes')
            ->allowEmptyDateTime('valid_from')
            ->allowEmptyDateTime('expires_at');
    }
}

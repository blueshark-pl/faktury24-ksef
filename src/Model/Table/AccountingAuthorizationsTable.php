<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class AccountingAuthorizationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('accounting_authorizations');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', ['foreignKey' => 'company_id']);
    }

    public function validationDefault(Validator $v): Validator
    {
        return $v
            ->uuid('company_id')->requirePresence('company_id')->notEmptyString('company_id')
            ->scalar('environment')->inList('environment', ['prod','test'])
            ->scalar('status')->inList('status', ['active','revoked','expired','invalid','pending'])
            ->boolean('is_active')
            ->allowEmptyString('provider')
            ->allowEmptyString('scopes')
            ->allowEmptyDateTime('valid_from')
            ->allowEmptyDateTime('expires_at');
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ClientProfilesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('client_profiles');
        $this->setDisplayField('company_name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'className'  => 'CakeDC/Users.Users',
            'foreignKey' => 'user_id',
        ]);
        $this->belongsTo('Contractors', [
            'foreignKey' => 'contractor_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('user_id')
            ->notEmptyString('user_id')
            ->scalar('nip')
            ->maxLength('nip', 30)
            ->notEmptyString('nip')
            ->scalar('company_name')
            ->maxLength('company_name', 255)
            ->allowEmptyString('company_name')
            ->uuid('contractor_id')
            ->allowEmptyString('contractor_id')
            ->inList('locale', ['pl', 'en']);

        return $validator;
    }
}

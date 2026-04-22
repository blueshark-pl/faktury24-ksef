<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class E100AccountsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('e100_accounts');
        $this->setEntityClass('App\Model\Entity\E100Account');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Cake/ORM.Uuid');

        $this->hasMany('E100Transactions', [
            'foreignKey'   => 'e100_account_id',
            'className'    => 'App\Model\Table\E100TransactionsTable',
            'dependent'    => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('label', 'Podaj nazwę konta')
            ->maxLength('label', 100)
            ->notEmptyString('username', 'Podaj login E100')
            ->maxLength('username', 100);

        return $validator;
    }
}

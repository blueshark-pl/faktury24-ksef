<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class E100TransactionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('e100_transactions');
        $this->setEntityClass('App\Model\Entity\E100Transaction');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('E100Accounts', [
            'foreignKey' => 'e100_account_id',
            'className'  => 'App\Model\Table\E100AccountsTable',
        ]);
    }
}

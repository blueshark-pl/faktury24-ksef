<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmPushSubscriptionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_push_subscriptions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}

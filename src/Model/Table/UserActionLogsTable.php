<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class UserActionLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('user_action_logs');
        $this->setEntityClass('App\Model\Entity\UserActionLog');
    }
}

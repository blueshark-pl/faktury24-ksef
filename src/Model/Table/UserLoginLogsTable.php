<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class UserLoginLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('user_login_logs');
        $this->setEntityClass('App\Model\Entity\UserLoginLog');
        // 'logged_at' ustawiane ręcznie + DB default — bez Timestamp behavior
    }
}

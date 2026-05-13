<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class UserSecurityEventsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('user_security_events');
        $this->setEntityClass('App\Model\Entity\UserSecurityEvent');
    }
}

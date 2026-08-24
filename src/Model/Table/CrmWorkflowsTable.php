<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmWorkflowsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_workflows');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmKrsCacheTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_krs_cache');
        $this->setPrimaryKey('krs');
    }
}

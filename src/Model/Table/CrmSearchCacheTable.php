<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmSearchCacheTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_search_cache');
        $this->setPrimaryKey('query_hash');
    }
}

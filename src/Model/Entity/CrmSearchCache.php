<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CrmSearchCache extends Entity
{
    protected array $_accessible = ['*' => true, 'query_hash' => false];
}

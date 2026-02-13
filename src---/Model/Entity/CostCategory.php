<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CostCategory extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'company_id' => true,
        'name' => true,
        'code' => true,
        'is_active' => true,
        'sort_order' => true,
        'created' => true,
        'modified' => true,
    ];
}

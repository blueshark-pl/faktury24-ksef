<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CostInvoiceNote extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

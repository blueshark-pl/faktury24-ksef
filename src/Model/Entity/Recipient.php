<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Recipient extends Entity
{
    protected array $_accessible = [
        'company_id' => true,
        'contractor_id' => true,
        'nip' => true,
        'name' => true,
        'email' => true,
        'phone' => true,
        'city' => true,
        'street' => true,
        'postal_code' => true,
        'created' => true,
        'modified' => true,
    ];
}

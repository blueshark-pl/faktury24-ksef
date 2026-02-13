<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CurrencyFavorite extends Entity
{
    protected array $_accessible = [
        'company_id' => true,
        'code' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
    ];
}

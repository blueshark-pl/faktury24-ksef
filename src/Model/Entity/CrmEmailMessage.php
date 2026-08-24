<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CrmEmailMessage extends Entity
{
    protected array $_accessible = ['*' => true, 'id' => false];
}

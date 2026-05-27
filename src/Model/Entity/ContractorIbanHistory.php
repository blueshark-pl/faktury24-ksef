<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ContractorIbanHistory extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'company_id' => true,
        'contractor_nip' => true,
        'contractor_name_snapshot' => true,
        'iban' => true,
        'confirmed_count' => true,
        'total_amount_pln' => true,
        'first_used' => true,
        'last_used' => true,
        'created' => true,
        'modified' => true,
    ];
}

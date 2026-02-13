<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class KsefBookingItem extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'company_id' => true,
        'environment' => true,
        'ksef_number' => true,
        'line_index' => true,
        'line_id' => true,
        'name' => true,
        'quantity' => true,
        'unit' => true,
        'unit_price' => true,
        'net_amount' => true,
        'vat_rate' => true,
        'vat_amount' => true,
        'gross_amount' => true,
        'currency' => true,
        'cost_type' => true,
        'note' => true,
        'source_json' => true,
        'created' => true,
        'modified' => true,
    ];
}

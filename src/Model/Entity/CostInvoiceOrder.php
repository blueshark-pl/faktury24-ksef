<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $cost_invoice_id
 * @property int $speed_order_id
 * @property \Cake\I18n\DateTime $created
 */
class CostInvoiceOrder extends Entity
{
    protected array $_accessible = ['*' => true, 'id' => false];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int                       $id
 * @property int                       $speed_order_id
 * @property string                    $invoice_id
 * @property \Cake\I18n\DateTime|null  $created
 */
class SpeedOrderInvoice extends Entity
{
    protected array $_accessible = [
        'speed_order_id' => true,
        'invoice_id'     => true,
        'created'        => true,
    ];
}

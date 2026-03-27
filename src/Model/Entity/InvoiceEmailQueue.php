<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $company_id
 * @property string $email
 * @property string $status  pending|sending|sent|failed
 * @property int    $attempts
 * @property string|null $last_error
 * @property \Cake\I18n\DateTime|null $sent_at
 * @property \Cake\I18n\DateTime $scheduled_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class InvoiceEmailQueue extends Entity
{
    protected array $_accessible = [
        'invoice_id'   => true,
        'company_id'   => true,
        'email'        => true,
        'status'       => true,
        'attempts'     => true,
        'last_error'   => true,
        'sent_at'      => true,
        'scheduled_at' => true,
    ];
}

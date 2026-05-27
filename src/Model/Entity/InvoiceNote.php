<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class InvoiceNote extends Entity
{
    protected array $_accessible = [
        'company_id'        => true,
        'invoice_id'        => true,
        'legacy_invoice_id' => true,
        'user_id'           => true,
        'note_type'         => true,
        'body'              => true,
        'payload_json'      => true,
        'created'           => true,
        'modified'          => true,
        'invoice'           => true,
        'user'              => true,
    ];
}

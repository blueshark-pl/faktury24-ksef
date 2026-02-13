<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AccountingAuthorization extends Entity
{
    protected array $_accessible = [
        'id'            => true,
        'company_id'    => true,
        'provider'      => true,
        'environment'   => true,
        'status'        => true,
        'is_active'     => true,
        'valid_from'    => true,
        'expires_at'    => true,
        'token_cipher'  => true,
        'token_last4'   => true,
        'scopes'        => true,
        'last_synced_at'=> true,
        'created'       => true,
        'modified'      => true,
    ];

    protected array $_hidden = ['token_cipher'];
}

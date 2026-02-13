<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class KsefAuthorization extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'company_id' => true,
        'environment' => true,
        'status' => true,
        'is_active' => true,
        'auth_method' => true,
        'scopes' => true,
        'valid_from' => true,
        'expires_at' => true,
        'token_cipher' => true,
        'token_last4' => true,
        'last_verified_at' => true,
        'revoked_at' => true,
        'created_by' => true,
        'revoked_by' => true,
        'created' => true,
        'modified' => true,
    ];

    // nigdy nie serializuj token_cipher do JSON
    protected array $_hidden = ['token_cipher'];
}

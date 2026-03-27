<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ApiToken Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property \Cake\I18n\DateTime|null $last_used_at
 * @property \Cake\I18n\DateTime|null $expires_at
 * @property bool $is_active
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class ApiToken extends Entity
{
    protected array $_accessible = [
        'company_id'   => true,
        'user_id'      => true,
        'name'         => true,
        'token_hash'   => true,
        'token_prefix' => true,
        'last_used_at' => true,
        'expires_at'   => true,
        'is_active'    => true,
        'created'      => true,
        'modified'     => true,
    ];

    protected array $_hidden = ['token_hash'];
}

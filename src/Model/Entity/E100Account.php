<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property string $label
 * @property string $username
 * @property string $password_enc
 * @property string|null $client_code
 * @property string|null $fullname
 * @property string|null $defcur
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Cake\I18n\DateTime|null $token_expires_at
 * @property \Cake\I18n\DateTime|null $last_sync_at
 * @property string|null $last_sync_row_version
 * @property bool $is_active
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class E100Account extends Entity
{
    protected array $_accessible = [
        'company_id'             => true,
        'label'                  => true,
        'username'               => true,
        'password_enc'           => true,
        'client_code'            => true,
        'fullname'               => true,
        'defcur'                 => true,
        'access_token'           => true,
        'refresh_token'          => true,
        'token_expires_at'       => true,
        'last_sync_at'           => true,
        'last_sync_row_version'  => true,
        'is_active'              => true,
    ];

    protected array $_hidden = ['password_enc', 'access_token', 'refresh_token'];
}

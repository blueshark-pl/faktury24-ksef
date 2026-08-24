<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property string|null $user_id
 * @property string $label
 * @property string $imap_host
 * @property int $imap_port
 * @property bool $use_ssl
 * @property string $username
 * @property string $password_encrypted
 * @property string $folder
 * @property int|null $last_seen_uid
 * @property \Cake\I18n\DateTime|null $last_synced_at
 * @property string|null $last_error
 * @property int $messages_synced_total
 * @property int $activities_created_total
 * @property bool $is_active
 * @property int $sync_frequency_min
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class CrmEmailAccount extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
        'password_encrypted' => false, // Ustawiane przez setPasswordFromPlain
    ];

    protected array $_hidden = ['password_encrypted'];
}

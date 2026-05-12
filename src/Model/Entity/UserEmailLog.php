<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string                    $id
 * @property string                    $user_id
 * @property string                    $recipient_email
 * @property string                    $email_type
 * @property string                    $lang
 * @property string|null               $subject
 * @property string                    $status
 * @property string|null               $error_message
 * @property string|null               $sender_user_id
 * @property string|null               $sender_email
 * @property \Cake\I18n\DateTime       $created
 */
class UserEmailLog extends Entity
{
    protected array $_accessible = [
        'user_id'         => true,
        'recipient_email' => true,
        'email_type'      => true,
        'lang'            => true,
        'subject'         => true,
        'status'          => true,
        'error_message'   => true,
        'sender_user_id'  => true,
        'sender_email'    => true,
        'created'         => true,
    ];
}

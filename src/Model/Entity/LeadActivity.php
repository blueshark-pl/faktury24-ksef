<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property string      $lead_id
 * @property string|null $user_id
 * @property string      $activity_type
 * @property string|null $subject
 * @property string|null $body
 * @property int|null    $duration_min
 * @property \Cake\I18n\DateTime|null $happened_at
 * @property \Cake\I18n\DateTime|null $due_at
 * @property bool        $is_done
 * @property \Cake\I18n\DateTime|null $done_at
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $payload_json
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Lead|null $lead
 * @property \App\Model\Entity\User|null $user
 */
class LeadActivity extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];

    public function getIconClass(): string
    {
        return match ($this->activity_type) {
            'phone_call'   => 'ri-phone-line',
            'email_out'    => 'ri-mail-send-line',
            'email_in'     => 'ri-mail-download-line',
            'meeting'      => 'ri-calendar-event-line',
            'note'         => 'ri-sticky-note-line',
            'task'         => 'ri-checkbox-line',
            'file'         => 'ri-attachment-2',
            'stage_change' => 'ri-arrow-right-up-line',
            'assignment'   => 'ri-user-shared-line',
            'offer_sent'   => 'ri-file-paper-line',
            'order_won'    => 'ri-trophy-line',
            'order_lost'   => 'ri-close-circle-line',
            default        => 'ri-more-line',
        };
    }

    public function getToneColor(): string
    {
        return match ($this->activity_type) {
            'phone_call'   => 'green',
            'email_out',
            'email_in'     => 'purple',
            'meeting'      => 'blue',
            'note'         => 'yellow',
            'task'         => 'orange',
            'stage_change' => 'slate',
            'offer_sent'   => 'purple',
            'order_won'    => 'green',
            'order_lost'   => 'red',
            default        => 'slate',
        };
    }
}

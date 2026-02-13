<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Notification Entity
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $channel
 * @property string $type
 * @property string $severity
 * @property string $title
 * @property string|null $message
 * @property string|null $action_url
 * @property string|null $action_label
 * @property bool $is_read
 * @property \Cake\I18n\DateTime|null $read_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \CakeDC\Users\Model\Entity\User $user
 */
class Notification extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'channel' => true,
        'type' => true,
        'severity' => true,
        'title' => true,
        'message' => true,
        'action_url' => true,
        'action_label' => true,
        'is_read' => true,
        'read_at' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
    ];
}

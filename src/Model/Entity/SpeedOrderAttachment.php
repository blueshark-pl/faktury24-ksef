<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SpeedOrderAttachment Entity
 *
 * @property int         $id
 * @property int         $speed_order_id
 * @property int|null    $label_id
 * @property string      $file_path
 * @property string      $original_name
 * @property string|null $mime_type
 * @property int|null    $file_size
 * @property string|null $uploaded_by
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\SpeedOrderAttachmentLabel|null $speed_order_attachment_label
 */
class SpeedOrderAttachment extends Entity
{
    protected array $_accessible = [
        'speed_order_id' => true,
        'label_id'       => true,
        'file_path'      => true,
        'original_name'  => true,
        'mime_type'      => true,
        'file_size'      => true,
        'uploaded_by'    => true,
        'created'        => true,
    ];
}

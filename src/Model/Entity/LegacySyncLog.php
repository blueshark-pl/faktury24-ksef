<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property int $rejestr
 * @property int $rok
 * @property int|null $mc
 * @property string|null $synced_by_user_id
 * @property string|null $synced_by_name
 * @property string $status
 * @property int $records_fetched
 * @property int $records_upserted
 * @property int $records_changed
 * @property string|null $changes_detail
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime $synced_at
 */
class LegacySyncLog extends Entity
{
    protected array $_accessible = [
        'company_id'        => true,
        'rejestr'           => true,
        'rok'               => true,
        'mc'                => true,
        'synced_by_user_id' => true,
        'synced_by_name'    => true,
        'status'            => true,
        'records_fetched'   => true,
        'records_upserted'  => true,
        'records_changed'   => true,
        'changes_detail'    => true,
        'error_message'     => true,
        'synced_at'         => true,
    ];
}

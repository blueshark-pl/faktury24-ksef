<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property string|null $contractor_id
 * @property string      $contractor_nip
 * @property float       $credit_limit_pln
 * @property int         $warning_threshold_pct
 * @property bool        $is_blocked
 * @property string|null $block_reason
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class ContractorCreditLimit extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

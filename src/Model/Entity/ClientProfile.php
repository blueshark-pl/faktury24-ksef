<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string                       $id
 * @property string                       $user_id
 * @property string                       $nip
 * @property string|null                  $company_name
 * @property string|null                  $contractor_id
 * @property string                       $locale
 * @property \Cake\I18n\DateTime|null     $created
 * @property \Cake\I18n\DateTime|null     $modified
 */
class ClientProfile extends Entity
{
    protected array $_accessible = [
        'user_id'       => true,
        'nip'           => true,
        'company_name'  => true,
        'contractor_id' => true,
        'locale'        => true,
        'created'       => true,
        'modified'      => true,
    ];
}

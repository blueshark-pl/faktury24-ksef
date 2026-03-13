<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * CompanyRegister Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $name
 * @property string|null $krs
 * @property string|null $regon
 * @property string|null $bdo
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 */
class CompanyRegister extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'company_id' => true,
        'name' => true,
        'krs' => true,
        'regon' => true,
        'bdo' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class CrmDocumentTrack extends Entity
{
    protected array $_accessible = ['*' => true, 'id' => false];

    public function getOpensLog(): array
    {
        if (empty($this->opens_json)) return [];
        $arr = json_decode((string)$this->opens_json, true);
        return is_array($arr) ? $arr : [];
    }
}

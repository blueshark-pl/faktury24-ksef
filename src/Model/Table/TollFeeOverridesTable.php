<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TollFeeOverridesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('toll_fee_overrides');
        $this->setPrimaryKey('id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->scalar('company_id')->notEmptyString('company_id')
            ->scalar('fare_signature')->maxLength('fare_signature', 40)->notEmptyString('fare_signature')
            ->scalar('country')->maxLength('country', 3)->notEmptyString('country')
            ->scalar('action')->inList('action', ['ignore', 'corrected', 'flagged']);
        return $validator;
    }

    /**
     * Generuje deterministyczny hash dla fare bazując na country + system + name.
     * Te same opłaty (np. "A2 AUTOSTRADA WIELKOPOLSKA" w PL) mają ten sam hash
     * niezależnie od trasy.
     */
    public static function fareSignature(string $country, string $system, string $name): string
    {
        $norm = function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = preg_replace('/\s+/u', ' ', $s);
            return $s;
        };
        return sha1($country . '|' . $norm($system) . '|' . $norm($name));
    }

    /**
     * Zwraca mapę overrides per fare_signature dla danej firmy.
     * @return array<string, array>  signature → override row
     */
    public function getActiveOverridesByCompany(string $companyId): array
    {
        $rows = $this->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->all();
        $map = [];
        foreach ($rows as $r) $map[(string)$r->fare_signature] = $r->toArray();
        return $map;
    }
}

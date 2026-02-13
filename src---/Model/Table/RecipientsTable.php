<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RecipientsTable extends Table
{
    private static function isValidNip(?string $nip): bool
    {
        $s = preg_replace('/\D+/', '', (string)$nip);
        if (strlen($s) !== 10) { return false; }
        $weights = [6,5,7,2,3,4,5,6,7];
        $digits = array_map('intval', str_split($s));
        $sum = 0;
        foreach ($weights as $i => $w) { $sum += $w * $digits[$i]; }
        return ($sum % 11) === $digits[9];
    }
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('recipients');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Contractors', [
            'foreignKey' => 'contractor_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        // Accept numeric contractor_id (int) or UUID depending on parent table type
        $validator->add('contractor_id', 'validId', [
            'rule' => function ($value) {
                $v = (string)$value;
                if ($v === '') return false;
                // UUID v4 pattern or all digits (integer id)
                return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v)
                    || ctype_digit($v);
            },
            'message' => 'Nieprawidłowe ID kontrahenta.'
        ])->notEmptyString('contractor_id');
        $validator
            ->scalar('name')->maxLength('name', 200)->notEmptyString('name');
        $validator
            ->email('email')->allowEmptyString('email');
        $validator
            ->scalar('phone')->maxLength('phone', 40)->allowEmptyString('phone');
        $validator
            ->scalar('city')->maxLength('city', 120)->allowEmptyString('city');
        $validator
            ->scalar('street')->maxLength('street', 160)->allowEmptyString('street');
        $validator
            ->scalar('postal_code')->maxLength('postal_code', 16)->allowEmptyString('postal_code');
        $validator
            ->scalar('nip')->maxLength('nip', 20)->allowEmptyString('nip');

        // Validate NIP checksum if provided
        $validator->add('nip', 'nipChecksum', [
            'rule' => function ($value) {
                $v = preg_replace('/\D+/', '', (string)$value);
                if ($v === '') return true; // optional
                return self::isValidNip($v) ? true : 'Nieprawidłowy NIP odbiorcy (suma kontrolna).';
            }
        ]);
        return $validator;
    }
}

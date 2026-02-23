<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Contractors Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\ContractorBankAccountsTable&\Cake\ORM\Association\HasMany $ContractorBankAccounts
 *
 * @method \App\Model\Entity\Contractor newEmptyEntity()
 * @method \App\Model\Entity\Contractor newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Contractor> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Contractor get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Contractor findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Contractor patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Contractor> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Contractor|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Contractor saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Contractor>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Contractor>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Contractor>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Contractor> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Contractor>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Contractor>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Contractor>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Contractor> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ContractorsTable extends Table
{
    /**
     * Validate Polish NIP checksum.
     */
    private static function isValidNip(?string $nip): bool
    {
        $s = preg_replace('/\D+/', '', (string)$nip);
        if (strlen($s) !== 10) {
            return false;
        }
        $weights = [6,5,7,2,3,4,5,6,7];
        $digits = array_map('intval', str_split($s));
        $sum = 0;
        foreach ($weights as $i => $w) { $sum += $w * $digits[$i]; }
        $control = $sum % 11;
        return $control === $digits[9];
    }

    /**
     * Validate Polish PESEL checksum.
     */
    private static function isValidPesel(?string $pesel): bool
    {
        $s = preg_replace('/\D+/', '', (string)$pesel);
        if (strlen($s) !== 11) {
            return false;
        }
        $weights = [1,3,7,9,1,3,7,9,1,3];
        $digits = array_map('intval', str_split($s));
        $sum = 0;
        foreach ($weights as $i => $w) { $sum += $w * $digits[$i]; }
        $ctrl = (10 - ($sum % 10)) % 10;
        return $ctrl === $digits[10];
    }
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('contractors');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('ContractorBankAccounts', [
            'foreignKey' => 'contractor_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 200)
            ->allowEmptyString('name');

        // Person name fields
        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 120)
            ->allowEmptyString('first_name');
        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 120)
            ->allowEmptyString('last_name');

        $validator
            ->scalar('altname')
            ->maxLength('altname', 160)
            ->allowEmptyString('altname');

        $validator
            ->scalar('nip')
            ->maxLength('nip', 20)
            ->allowEmptyString('nip');

        // PESEL
        $validator
            ->scalar('pesel')
            ->maxLength('pesel', 11)
            ->allowEmptyString('pesel');

        // Consent and legal basis
        $validator
            ->boolean('privacy_consent')
            ->allowEmptyString('privacy_consent');
        $validator
            ->scalar('privacy_basis')
            ->maxLength('privacy_basis', 16)
            ->allowEmptyString('privacy_basis');

        // Custom: identifiers logic
        // Company: NIP required; Person: PESEL/NIP optional but validated when provided
        $validator->add('nip', 'nipOrPesel', [
            'rule' => function ($value, array $context) {
                $data = (array)($context['data'] ?? []);
                $nip   = preg_replace('/\D+/', '', (string)($data['nip'] ?? ''));
                $pesel = preg_replace('/\D+/', '', (string)($data['pesel'] ?? ''));
                $name  = trim((string)($data['name'] ?? ''));
                $first = trim((string)($data['first_name'] ?? ''));
                $last  = trim((string)($data['last_name'] ?? ''));

                $isPerson = ($first !== '' || $last !== '') && $name === '';
                $isCompany = $name !== '' && ($first === '' && $last === '');

                // For companies, require NIP
                if ($isCompany) {
                    if ($nip === '') {
                        return 'NIP jest wymagany dla firmy.';
                    }
                }
                // For persons, identifiers optional

                if ($nip !== '') {
                    return self::isValidNip($nip) ? true : 'Nieprawidłowy NIP (suma kontrolna).';
                }

                if ($pesel !== '') {
                    if (!self::isValidPesel($pesel)) {
                        return 'Nieprawidłowy PESEL (suma kontrolna).';
                    }
                }
                return true;
            },
            'message' => 'Nieprawidłowe dane identyfikacyjne kontrahenta.'
        ]);

        // Custom: require company name OR first+last when pesel mode
        $validator->add('name', 'nameOrFirstLast', [
            'rule' => function ($value, array $context) {
                $data = (array)($context['data'] ?? []);
                $nip   = preg_replace('/\D+/', '', (string)($data['nip'] ?? ''));
                $pesel = preg_replace('/\D+/', '', (string)($data['pesel'] ?? ''));
                $name  = trim((string)($data['name'] ?? ''));
                $first = trim((string)($data['first_name'] ?? ''));
                $last  = trim((string)($data['last_name'] ?? ''));

                if (($first !== '' || $last !== '') && $name === '') {
                    // person mode: require first+last
                    if ($first === '' || $last === '') {
                        return 'Podaj imię i nazwisko dla osoby fizycznej.';
                    }
                    return true;
                }
                // company mode: require name
                if ($name === '') {
                    return 'Nazwa firmy jest wymagana.';
                }
                return true;
            },
            'message' => 'Uzupełnij dane nazwy kontrahenta.'
        ]);

        $validator
            ->scalar('regon')
            ->maxLength('regon', 14)
            ->allowEmptyString('regon');

        $validator
            ->boolean('eu_vat')
            ->notEmptyString('eu_vat');

        $validator
            ->scalar('country')
            ->maxLength('country', 2)
            ->allowEmptyString('country');

        $validator
            ->scalar('postal_code')
            ->maxLength('postal_code', 16)
            ->allowEmptyString('postal_code');

        $validator
            ->scalar('city')
            ->maxLength('city', 120)
            ->allowEmptyString('city');

        $validator
            ->scalar('street')
            ->maxLength('street', 160)
            ->allowEmptyString('street');

        $validator
            ->scalar('correspondence_street')
            ->maxLength('correspondence_street', 160)
            ->allowEmptyString('correspondence_street');

        $validator
            ->scalar('correspondence_postal_code')
            ->maxLength('correspondence_postal_code', 16)
            ->allowEmptyString('correspondence_postal_code');

        $validator
            ->scalar('correspondence_city')
            ->maxLength('correspondence_city', 120)
            ->allowEmptyString('correspondence_city');

        $validator
            ->scalar('correspondence_country')
            ->maxLength('correspondence_country', 2)
            ->allowEmptyString('correspondence_country');

        $validator
            ->scalar('local_number')
            ->maxLength('local_number', 32)
            ->allowEmptyString('local_number');

        // Conditional requirements for PL address
        foreach (['postal_code' => 'Kod pocztowy', 'city' => 'Miejscowość', 'street' => 'Ulica'] as $field => $label) {
            $validator->add($field, 'requiredForPL', [
                'rule' => function ($value, array $context) {
                    $country = strtoupper((string)($context['data']['country'] ?? ''));
                    if ($country !== 'PL') {
                        return true; // not required for non-PL
                    }
                    return trim((string)$value) !== '';
                },
                'message' => $label . ' jest wymagana dla kraju PL.'
            ]);
        }

        $validator
            ->scalar('phone')
            ->maxLength('phone', 40)
            ->allowEmptyString('phone');

        $validator
            ->email('email')
            ->allowEmptyString('email');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        // Flag: person vs company
        $validator
            ->boolean('is_person')
            ->allowEmptyString('is_person');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['company_id'], 'Companies'), ['errorField' => 'company_id']);

        return $rules;
    }
}

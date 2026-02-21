<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Companies Model
 *
 * @property \App\Model\Table\CompanyBankAccountsTable&\Cake\ORM\Association\HasMany $CompanyBankAccounts
 * @property \App\Model\Table\ContractorsTable&\Cake\ORM\Association\HasMany $Contractors
 * @property \App\Model\Table\InvoiceSeriesTable&\Cake\ORM\Association\HasMany $InvoiceSeries
 * @property \App\Model\Table\InvoicesTable&\Cake\ORM\Association\HasMany $Invoices
 * @property \App\Model\Table\ServicesTable&\Cake\ORM\Association\HasMany $Services
 * @property \CakeDC\Users\Model\Table\UsersTable&\Cake\ORM\Association\HasMany $Users
 *
 * @method \App\Model\Entity\Company newEmptyEntity()
 * @method \App\Model\Entity\Company newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Company> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Company get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Company findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Company patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Company> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Company|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Company saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Company>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Company>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Company>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Company> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Company>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Company>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Company>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Company> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class CompaniesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('companies');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('CompanyBankAccounts', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('Contractors', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('InvoiceSeries', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('Invoices', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('Services', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('Users', [
            'foreignKey' => 'company_id',
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
            ->scalar('name')
            ->maxLength('name', 160)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('altname')
            ->maxLength('altname', 160)
            ->allowEmptyString('altname');

        $validator
            ->scalar('nip')
            ->maxLength('nip', 10)
            ->allowEmptyString('nip');

        $validator
            ->add('nip', 'digitsIfProvided', [
                'rule' => function ($value): bool {
                    $v = preg_replace('/\D+/', '', (string)$value);
                    return $v === '' || strlen($v) === 10;
                },
                'message' => 'NIP powinien mieć 10 cyfr.',
            ]);

        $validator
            ->add('nip', 'requiredForBusinessVat', [
                'rule' => function ($value, $context): bool {
                    $data = (array)($context['data'] ?? []);
                    $profileMode = (string)($data['profile_mode'] ?? 'business');
                    $vatPayer = (bool)($data['vat_payer'] ?? false);

                    if ($profileMode !== 'business' || $vatPayer !== true) {
                        return true;
                    }

                    $v = preg_replace('/\D+/', '', (string)$value);
                    return strlen($v) === 10;
                },
                'message' => 'Dla firmy (VAT) podaj poprawny NIP (10 cyfr).',
            ]);

        $validator
            ->scalar('regon')
            ->maxLength('regon', 14)
            ->allowEmptyString('regon');

        $validator
            ->scalar('country')
            ->maxLength('country', 2)
            ->allowEmptyString('country');

        $validator
            ->scalar('postal_code')
            ->maxLength('postal_code', 6)
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
            ->scalar('local_number')
            ->maxLength('local_number', 32)
            ->allowEmptyString('local_number');

        $validator
            ->scalar('phone')
            ->maxLength('phone', 32)
            ->allowEmptyString('phone');

        $validator
            ->scalar('bank_name')
            ->maxLength('bank_name', 120)
            ->allowEmptyString('bank_name');

        $validator
            ->scalar('bank_account')
            ->maxLength('bank_account', 34)
            ->allowEmptyString('bank_account');

        $validator
            ->scalar('logo_url')
            ->maxLength('logo_url', 255)
            ->allowEmptyString('logo_url');

        $validator
            ->scalar('issuer')
            ->maxLength('issuer', 160)
            ->allowEmptyString('issuer');

        $validator
            ->boolean('vat_payer')
            ->notEmptyString('vat_payer');

        $validator
            ->boolean('ksef_mode_enabled')
            ->notEmptyString('ksef_mode_enabled');

        $validator
            ->date('register_date')
            ->allowEmptyDate('register_date');

        $validator
            ->date('subscription_end')
            ->allowEmptyDate('subscription_end');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->scalar('invoice_template')
            ->maxLength('invoice_template', 64)
            ->allowEmptyString('invoice_template');

        $validator
            ->scalar('profile_mode')
            ->maxLength('profile_mode', 32)
            ->inList('profile_mode', ['business', 'private_rental'])
            ->notEmptyString('profile_mode');

        return $validator;
    }
}

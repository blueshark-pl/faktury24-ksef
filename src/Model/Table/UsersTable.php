<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;
use CakeDC\Users\Model\Table\UsersTable as BaseUsersTable;

class UsersTable extends BaseUsersTable
{
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        // afterSave() dostaje encję już jako persisted (isNew=false),
        // dlatego zapamiętujemy tutaj czy to był CREATE.
        if (!isset($options['__isCreate'])) {
            $options['__isCreate'] = $entity->isNew();
        }

        // Główna ścieżka: przy CREATE od razu utwórz/podepnij firmę,
        // aby user zapisał się już z company_id.
        $isCreate = (bool)($options['__isCreate'] ?? false);
        if ($isCreate && empty($entity->get('company_id'))) {
            $resolved = $this->resolveCompanyFromPrefill($entity);
            if (!empty($resolved['company_id'])) {
                $entity->set('company_id', (string)$resolved['company_id']);
                if (array_key_exists('additional_data', $resolved)) {
                    $entity->set('additional_data', $resolved['additional_data']);
                }
            }
        }
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     * @return array{company_id?:string, additional_data?:array<string,mixed>}
     */
    private function resolveCompanyFromPrefill(EntityInterface $entity): array
    {
        $additionalData = (array)($entity->get('additional_data') ?? []);
        $prefill = (array)($additionalData['onboarding_prefill'] ?? []);
        if (empty($prefill)) {
            return [];
        }

        $nip = preg_replace('/\D+/', '', (string)($prefill['nip'] ?? ''));
        $name = trim((string)($prefill['name'] ?? ''));
        $street = trim((string)($prefill['street'] ?? ''));
        $postalCode = trim((string)($prefill['postal_code'] ?? ''));
        $city = trim((string)($prefill['city'] ?? ''));
        $country = trim((string)($prefill['country'] ?? ''));

        if ($name === '' || $street === '' || $postalCode === '' || $city === '') {
            return [];
        }

        /** @var \App\Model\Table\CompaniesTable $Companies */
        $Companies = $this->getTableLocator()->get('Companies');
        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
        $InvoiceSeries = $this->getTableLocator()->get('InvoiceSeries');
        /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
        $CompanyBankAccounts = $this->getTableLocator()->get('CompanyBankAccounts');

        $companyId = null;

        try {
            if (strlen($nip) === 10) {
                $existingCompany = $Companies->find()
                    ->select(['id'])
                    ->where(function ($exp, $q) use ($nip) {
                        return $exp->eq(
                            $q->newExpr("REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '')"),
                            $nip
                        );
                    })
                    ->first();
                if ($existingCompany) {
                    $companyId = (string)$existingCompany->id;
                }
            }

            if ($companyId === null) {
                $company = $Companies->newEntity([
                    'name' => $name,
                    'nip' => strlen($nip) === 10 ? $nip : null,
                    'street' => $street,
                    'local_number' => trim((string)($prefill['local_number'] ?? '')),
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'country' => $country !== '' ? $country : 'PL',
                    'vat_payer' => true,
                    'ksef_mode_enabled' => true,
                    'is_active' => true,
                    'profile_mode' => 'business',
                ]);

                if (!$Companies->save($company)) {
                    Log::error('Rejestracja: nie udało się utworzyć firmy dla usera ' . (string)$entity->get('id') . ': ' . json_encode($company->getErrors()), ['register_company']);
                    return [];
                }
                $companyId = (string)$company->id;

                // Opcjonalnie: bank accounts z prefilla (np. MF)
                $prefillBankAccounts = (array)($prefill['bank_accounts'] ?? []);
                $uniqueIbans = [];
                foreach ($prefillBankAccounts as $rawIban) {
                    $iban = strtoupper(preg_replace('/\s+/', '', (string)$rawIban));
                    if ($iban === '') {
                        continue;
                    }
                    if (preg_match('/^\d{26}$/', $iban)) {
                        $iban = 'PL' . $iban;
                    }
                    $uniqueIbans[$iban] = true;
                }

                $isFirst = true;
                foreach (array_keys($uniqueIbans) as $iban) {
                    $bankEntity = $CompanyBankAccounts->newEntity([
                        'company_id' => $companyId,
                        'iban' => $iban,
                        'currency' => 'PLN',
                        'is_default' => $isFirst,
                    ]);
                    if ($CompanyBankAccounts->save($bankEntity)) {
                        $isFirst = false;
                    }
                }

                $InvoiceSeries->copySystemSeriesForCompany($companyId);
            }

            unset($additionalData['onboarding_prefill']);

            return [
                'company_id' => $companyId,
                'additional_data' => $additionalData,
            ];
        } catch (\Throwable $e) {
            Log::error('Rejestracja: wyjątek przy tworzeniu/przypinaniu firmy dla usera ' . (string)$entity->get('id') . ': ' . $e->getMessage(), ['register_company']);

            return [];
        }
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Wymuszamy unikalność e-mail na poziomie reguł aplikacyjnych.
        $this->isValidateEmail = true;
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator = parent::validationDefault($validator);

        $validator
            ->requirePresence('email', 'create')
            ->notEmptyString('email', 'E-mail jest wymagany.')
            ->email('email', false, 'Podaj poprawny adres e-mail.');

        return $validator;
    }

    public function validationRegister(Validator $validator)
    {
        $validator = parent::validationRegister($validator);

        $validator
            ->requirePresence('email', 'create')
            ->notEmptyString('email', 'E-mail jest wymagany.')
            ->email('email', false, 'Podaj poprawny adres e-mail.');

        $validator
            ->add('additional_data', 'registrationCompanyPrefillRequired', [
                'rule' => function ($value): bool {
                    $data = (array)$value;
                    $prefill = (array)($data['onboarding_prefill'] ?? []);

                    $nip = preg_replace('/\D+/', '', (string)($prefill['nip'] ?? ''));
                    $name = trim((string)($prefill['name'] ?? ''));
                    $street = trim((string)($prefill['street'] ?? ''));
                    $postalCode = trim((string)($prefill['postal_code'] ?? ''));
                    $city = trim((string)($prefill['city'] ?? ''));
                    $country = trim((string)($prefill['country'] ?? ''));

                    $postalOk = (bool)preg_match('/^\d{2}-\d{3}$/', $postalCode)
                        || (bool)preg_match('/^\d{5}$/', $postalCode);

                    return strlen($nip) === 10
                        && $name !== ''
                        && $street !== ''
                        && $postalOk
                        && $city !== ''
                        && $country !== '';
                },
                'message' => 'NIP i dane firmy są wymagane przy rejestracji.',
            ]);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules = parent::buildRules($rules);

        // Dodatkowe bezpieczeństwo: e-mail nie może być pusty przy zapisie usera.
        $rules->add(function ($entity) {
            $email = trim((string)($entity->get('email') ?? ''));

            return $email !== '';
        }, 'emailRequired', [
            'errorField' => 'email',
            'message' => 'E-mail jest wymagany.',
        ]);

        return $rules;
    }

    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        // Reaguj tylko po utworzeniu konta i tylko gdy user nie ma jeszcze firmy.
        $isCreate = (bool)($options['__isCreate'] ?? false);
        if (!$isCreate || !empty($entity->get('company_id'))) {
            return;
        }

        // Fallback: jeśli z jakiegoś powodu beforeSave nie ustawił firmy,
        // spróbuj jeszcze raz po zapisie użytkownika.
        $resolved = $this->resolveCompanyFromPrefill($entity);
        if (empty($resolved['company_id'])) {
            return;
        }

        $user = $this->get((string)$entity->get('id'));
        $user->set('company_id', (string)$resolved['company_id']);
        if (array_key_exists('additional_data', $resolved)) {
            $user->set('additional_data', $resolved['additional_data']);
        }
        $this->save($user, ['checkRules' => false, 'validate' => false]);
    }
}

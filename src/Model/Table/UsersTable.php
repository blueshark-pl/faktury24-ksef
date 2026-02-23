<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;
use CakeDC\Users\Model\Table\UsersTable as BaseUsersTable;

class UsersTable extends BaseUsersTable
{
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
}

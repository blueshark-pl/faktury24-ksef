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

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ApiTokensTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('api_tokens');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('company_id')
            ->notEmptyString('user_id')
            ->maxLength('name', 128)
            ->notEmptyString('token_hash')
            ->notEmptyString('token_prefix');

        return $validator;
    }

    /**
     * Look up a token by raw value. Updates last_used_at on hit.
     * Returns the token entity (with company_id) or null.
     */
    public function findByRawToken(string $rawToken): ?\App\Model\Entity\ApiToken
    {
        $hash = hash('sha256', $rawToken);

        $token = $this->find()
            ->where(['token_hash' => $hash, 'is_active' => true])
            ->first();

        if ($token === null) {
            return null;
        }

        // Check expiry
        if ($token->expires_at !== null) {
            $now = new \DateTimeImmutable();
            $exp = $token->expires_at instanceof \DateTimeInterface
                ? $token->expires_at
                : new \DateTimeImmutable((string)$token->expires_at);
            if ($now > $exp) {
                return null;
            }
        }

        // Update last_used_at (best-effort, don't fail the request)
        try {
            $token->set('last_used_at', new \DateTimeImmutable());
            $this->save($token);
        } catch (\Throwable) {}

        return $token;
    }
}

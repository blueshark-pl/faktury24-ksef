<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Manages API tokens for the authenticated user/company.
 *
 * GET  /api-tokens          — list tokens
 * POST /api-tokens/generate — generate a new token (returns raw value once)
 * POST /api-tokens/revoke/:id — deactivate a token
 */
class ApiTokensController extends AppController
{
    public function index(): void
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        /** @var \App\Model\Table\ApiTokensTable $ApiTokens */
        $ApiTokens = $this->fetchTable('ApiTokens');

        $tokens = $ApiTokens->find()
            ->select(['id', 'name', 'token_prefix', 'last_used_at', 'expires_at', 'is_active', 'created'])
            ->where(['company_id' => $companyId])
            ->orderBy(['created' => 'DESC'])
            ->all();

        $this->set(compact('tokens'));
    }

    public function generate(): Response
    {
        $this->request->allowMethod(['post']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $userId    = (string)($identity?->get('id') ?? '');

        $name      = trim((string)($this->request->getData('name') ?? 'Token API'));
        if ($name === '') {
            $name = 'Token API';
        }
        $expiresAt = trim((string)($this->request->getData('expires_at') ?? ''));

        // Generate a cryptographically random token: prefix_<random>
        $rawToken = 'fv_' . bin2hex(random_bytes(24)); // 51 chars total
        $hash     = hash('sha256', $rawToken);
        $prefix   = substr($rawToken, 0, 8);

        /** @var \App\Model\Table\ApiTokensTable $ApiTokens */
        $ApiTokens = $this->fetchTable('ApiTokens');
        $entity = $ApiTokens->newEmptyEntity();
        $entity = $ApiTokens->patchEntity($entity, [
            'id'           => Text::uuid(),
            'company_id'   => $companyId,
            'user_id'      => $userId,
            'name'         => $name,
            'token_hash'   => $hash,
            'token_prefix' => $prefix,
            'is_active'    => true,
            'expires_at'   => $expiresAt !== '' ? $expiresAt : null,
        ]);

        if (!$ApiTokens->save($entity)) {
            $this->Flash->error('Nie udało się wygenerować tokenu.');
            return $this->redirect(['action' => 'index']);
        }

        // Pass raw token to view ONCE — it will never be shown again
        $this->set(['newToken' => $rawToken, 'tokenName' => $name]);
        $this->render('token_generated');
        return $this->response;
    }

    public function revoke(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        /** @var \App\Model\Table\ApiTokensTable $ApiTokens */
        $ApiTokens = $this->fetchTable('ApiTokens');
        $token = $ApiTokens->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if ($token === null) {
            $this->Flash->error('Token nie istnieje.');
            return $this->redirect(['action' => 'index']);
        }

        $token->set('is_active', false);
        if ($ApiTokens->save($token)) {
            $this->Flash->success('Token został unieważniony.');
        } else {
            $this->Flash->error('Nie udało się unieważnić tokenu.');
        }

        return $this->redirect(['action' => 'index']);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\View\JsonView;

class CompanyBankAccountsController extends AppController
{
    public function search()
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->request->getAttribute('identity')?->get('company_id');
        $q = trim((string)$this->request->getQuery('q'));
        $limit = min(50, max(1, (int)$this->request->getQuery('limit', 20)));

        $Table = $this->fetchTable('CompanyBankAccounts');
        $query = $Table->find()
            ->select(['id', 'iban', 'bank_name', 'currency', 'is_default', 'label'])
            ->where(['company_id' => $companyId])
            ->order(['is_default' => 'DESC', 'created' => 'DESC'])
            ->limit($limit);

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
            $query->andWhere([
                'OR' => [
                    'iban LIKE' => $like,
                    'bank_name LIKE' => $like,
                    'label LIKE' => $like,
                    'currency LIKE' => $like,
                ]
            ]);
        }

        $results = [];
        foreach ($query as $row) {
            $text = $row->label
                ?: trim(($row->bank_name ? $row->bank_name . ' ' : '') . ($row->iban ?? ''));
            if (!$text) {
                $text = 'Rachunek';
            }
            $results[] = [
                'id' => $row->id,
                'text' => $text,
                'iban' => (string)$row->iban,
                'bank_name' => (string)($row->bank_name ?? ''),
                'currency' => (string)($row->currency ?? ''),
                'is_default' => (int)($row->is_default ?? 0),
                'label' => (string)($row->label ?? ''),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'results' => $results]));
    }

    public function add()
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        if ($this->request->is(['post'])) {
            $Table = $this->fetchTable('CompanyBankAccounts');
            $data = $this->request->getData();
            $entity = $Table->newEmptyEntity();
            $entity = $Table->patchEntity($entity, [
                'company_id' => $companyId,
                'iban'       => trim((string)($data['iban'] ?? '')),
                'bank_name'  => trim((string)($data['bank_name'] ?? '')),
                'currency'   => trim((string)($data['currency'] ?? 'PLN')),
                'label'      => trim((string)($data['label'] ?? '')),
                'is_default' => (int)($data['is_default'] ?? 0),
            ]);

            if (!$entity->iban) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'IBAN jest wymagany.']));
            }

            if ($Table->save($entity)) {
                $text = $entity->label ?: trim(($entity->bank_name ? $entity->bank_name . ' ' : '') . ($entity->iban ?? ''));
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'account' => [
                            'id' => $entity->id,
                            'text' => $text,
                            'iban' => (string)$entity->iban,
                            'bank_name' => (string)($entity->bank_name ?? ''),
                            'currency' => (string)($entity->currency ?? ''),
                            'label' => (string)($entity->label ?? ''),
                            'is_default' => (int)($entity->is_default ?? 0),
                        ],
                        'message' => 'Rachunek dodany.'
                    ]));
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nie udało się zapisać rachunku.', 'errors' => $entity->getErrors()]));
        }

        // Fallback non-AJAX add (not used from UI)
        $this->Flash->error('Niedozwolona metoda.');
        return $this->redirect(['controller' => 'Invoices', 'action' => 'add']);
    }
}

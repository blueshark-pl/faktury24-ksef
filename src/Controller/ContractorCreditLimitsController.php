<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * CRUD limitow kredytowych per kontrahent.
 */
class ContractorCreditLimitsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $rows = $this->fetchTable('ContractorCreditLimits')->find()
            ->contain(['Contractors' => function ($q) {
                return $q->select(['id', 'name', 'nip']);
            }])
            ->where(['ContractorCreditLimits.company_id' => $companyId])
            ->orderByAsc('ContractorCreditLimits.contractor_nip')
            ->all();

        $this->set(compact('rows'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CCL = $this->fetchTable('ContractorCreditLimits');
        $limit = $CCL->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['company_id'] = $companyId;
            $data['contractor_nip'] = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($data['contractor_nip'] ?? '')));
            $limit = $CCL->patchEntity($limit, $data);
            if ($CCL->save($limit)) {
                $this->Flash->success(__('Limit kredytowy zapisany.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu limitu.'));
        }

        $this->set(compact('limit'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CCL = $this->fetchTable('ContractorCreditLimits');
        $limit = $CCL->get($id);
        if ((string)$limit->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            unset($data['company_id']);
            $data['contractor_nip'] = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($data['contractor_nip'] ?? '')));
            $limit = $CCL->patchEntity($limit, $data);
            if ($CCL->save($limit)) {
                $this->Flash->success(__('Limit zaktualizowany.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('limit'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CCL = $this->fetchTable('ContractorCreditLimits');
        $limit = $CCL->get($id);
        if ((string)$limit->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $CCL->delete($limit);
        $this->Flash->success(__('Limit usunięty.'));
        $this->redirect(['action' => 'index']);
    }
}

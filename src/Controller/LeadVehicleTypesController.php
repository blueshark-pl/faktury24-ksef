<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

class LeadVehicleTypesController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('lead_vehicle_types', $conn->getSchemaCollection()->listTables(), true)) {
                $this->Flash->error(__('Rodzaje taboru wymagaja migracji CreateLeadVehicleTypes. Uruchom /crm/admin/tools -> Migracje.'));
                $this->redirect(['controller' => 'CrmAdmin', 'action' => 'tools']);
                return;
            }
        } catch (\Throwable $e) {}

        $V = $this->fetchTable('LeadVehicleTypes');
        $items = $V->find()->where(['company_id' => $companyId])
            ->orderByAsc('sort_order')->orderByAsc('name')->all()->toArray();
        $this->set(compact('items'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $V = $this->fetchTable('LeadVehicleTypes');
        $item = $V->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            $data['id'] = \Cake\Utility\Text::uuid();
            $data['company_id'] = $companyId;
            $item = $V->patchEntity($item, $data);
            if ($V->save($item)) { $this->Flash->success(__('Dodano.')); $this->redirect(['action' => 'index']); return; }
            $this->Flash->error(__('Blad zapisu.'));
        }
        $this->set(compact('item'));
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $V = $this->fetchTable('LeadVehicleTypes');
        $item = $V->get($id);
        if ((string)$item->company_id !== (string)$companyId) throw new NotFoundException();
        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            unset($data['id'], $data['company_id']);
            $item = $V->patchEntity($item, $data);
            if ($V->save($item)) { $this->Flash->success(__('Zapisano.')); $this->redirect(['action' => 'index']); return; }
            $this->Flash->error(__('Blad zapisu.'));
        }
        $this->set(compact('item'));
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $V = $this->fetchTable('LeadVehicleTypes');
        $item = $V->get($id);
        if ((string)$item->company_id !== (string)$companyId) throw new NotFoundException();
        $V->delete($item);
        $this->Flash->success(__('Usunieto.'));
        $this->redirect(['action' => 'index']);
    }
}

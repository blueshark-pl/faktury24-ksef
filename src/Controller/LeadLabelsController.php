<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * FALA extras: CRUD katalogu etykiet (Trello-style labels) per firma.
 */
class LeadLabelsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Labels = $this->fetchTable('LeadLabels');
        $labels = $Labels->find()
            ->where(['company_id' => $companyId])
            ->orderByAsc('sort_order')
            ->orderByAsc('name')
            ->all()->toArray();
        $this->set(compact('labels'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Labels = $this->fetchTable('LeadLabels');
        $label = $Labels->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            $data['id'] = \Cake\Utility\Text::uuid();
            $label = $Labels->patchEntity($label, $data);
            if ($Labels->save($label)) {
                $this->Flash->success(__('Etykieta dodana.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }
        $this->set(compact('label'));
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Labels = $this->fetchTable('LeadLabels');
        $label = $Labels->get($id);
        if ((string)$label->company_id !== (string)$companyId) throw new NotFoundException();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            unset($data['id'], $data['company_id']);
            $label = $Labels->patchEntity($label, $data);
            if ($Labels->save($label)) {
                $this->Flash->success(__('Zapisano.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }
        $this->set(compact('label'));
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Labels = $this->fetchTable('LeadLabels');
        $label = $Labels->get($id);
        if ((string)$label->company_id !== (string)$companyId) throw new NotFoundException();
        if ($Labels->delete($label)) {
            $this->Flash->success(__('Etykieta usunięta.'));
        } else {
            $this->Flash->error(__('Błąd usunięcia.'));
        }
        $this->redirect(['action' => 'index']);
    }
}

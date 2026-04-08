<?php
declare(strict_types=1);

namespace App\Controller;

class CostCategoriesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->CostCategories = $this->fetchTable('CostCategories');
    }

    // requireAdmin() jest w AppController

    public function index()
    {
        if (($resp = $this->requireAdmin()) instanceof \Cake\Http\Response) {
            return $resp;
        }
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        if ($this->request->is('post')) {
            $name = trim((string)$this->request->getData('name'));
            $code = trim((string)$this->request->getData('code'));
            if ($name === '') {
                $this->Flash->error('Nazwa nie może być pusta.');
            } else {
                $entity = $this->CostCategories->newEntity([
                    'id' => \Cake\Utility\Text::uuid(),
                    'company_id' => $companyId,
                    'name' => $name,
                    'code' => $code !== '' ? $code : $name,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
                if ($this->CostCategories->save($entity)) {
                    $this->Flash->success('Kategoria dodana.');
                    return $this->redirect(['action' => 'index']);
                }
                $this->Flash->error('Nie udało się zapisać kategorii.');
            }
        }

        $cats = $this->CostCategories->find()
            ->where(['company_id' => $companyId])
            ->orderAsc('sort_order')
            ->orderAsc('name');

        $this->set('categories', $this->paginate($cats));
    }

    public function edit(string $id)
    {
        if (($resp = $this->requireAdmin()) instanceof \Cake\Http\Response) {
            return $resp;
        }
        $cat = $this->CostCategories->get($id);
        if ($this->request->is(['post', 'patch', 'put'])) {
            $cat = $this->CostCategories->patchEntity($cat, [
                'name' => (string)$this->request->getData('name', $cat->name),
                'code' => (string)$this->request->getData('code', $cat->code),
                'is_active' => (bool)$this->request->getData('is_active', $cat->is_active),
                'sort_order' => (int)$this->request->getData('sort_order', (int)$cat->sort_order),
            ]);
            if ($this->CostCategories->save($cat)) {
                $this->Flash->success('Zapisano zmiany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Nie udało się zapisać zmian.');
        }
        $this->set(compact('cat'));
    }

    public function delete(string $id)
    {
        if (($resp = $this->requireAdmin()) instanceof \Cake\Http\Response) {
            return $resp;
        }
        $this->request->allowMethod(['post', 'delete']);
        $cat = $this->CostCategories->get($id);
        if ($this->CostCategories->delete($cat)) {
            $this->Flash->success('Kategoria usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć kategorii.');
        }
        return $this->redirect(['action' => 'index']);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;

/**
 * CRUD mapowań: typ zestawu → kategoria w konkretnym systemie mytniczym.
 * Plus AJAX endpoint dla planera tras.
 *
 * Akcje:
 *   index  GET  /admin/vehicle-type-categories
 *   add    GET  /admin/vehicle-type-categories/add
 *          POST /admin/vehicle-type-categories/add
 *   edit   GET  /admin/vehicle-type-categories/edit/{id}
 *          POST /admin/vehicle-type-categories/edit/{id}
 *   delete POST /admin/vehicle-type-categories/delete/{id}
 *   forType GET /admin/vehicle-type-categories/for-type/{type}  — AJAX dla planera
 */
class VehicleTypeCategoriesController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $VTC = $this->fetchTable('VehicleTypeCategories');
        $categories = $VTC->find()
            ->where(['company_id' => $companyId])
            ->orderByAsc('vehicle_type_code')
            ->orderByAsc('country_code')
            ->orderByAsc('system_name')
            ->all();

        // Grupowanie po vehicle_type_code dla lepszej czytelności
        $grouped = [];
        foreach ($categories as $c) {
            $grouped[(string)$c->vehicle_type_code][] = $c;
        }

        $this->set(compact('categories', 'grouped'));
        $this->set('title', 'Kategorie typów pojazdu — klasyfikacja autostrad');
    }

    public function add(): ?\Cake\Http\Response
    {
        $companyId = $this->companyId();
        $VTC = $this->fetchTable('VehicleTypeCategories');
        $entity = $VTC->newEmptyEntity();
        $entity->set('is_active', true);
        $entity->set('company_id', $companyId);
        $entity->set('id', Text::uuid());

        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            $entity = $VTC->patchEntity($entity, $data);
            if ($VTC->save($entity)) {
                $this->Flash->success('Kategoria zapisana.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź pola.');
        }

        $this->set(compact('entity'));
        $this->set('title', 'Nowa kategoria typu pojazdu');
        $this->render('form');
        return null;
    }

    public function edit(string $id): ?\Cake\Http\Response
    {
        $companyId = $this->companyId();
        $VTC = $this->fetchTable('VehicleTypeCategories');
        $entity = $VTC->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            $entity = $VTC->patchEntity($entity, $data);
            if ($VTC->save($entity)) {
                $this->Flash->success('Zaktualizowano.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu.');
        }

        $this->set(compact('entity'));
        $this->set('title', 'Edytuj kategorię typu pojazdu');
        $this->render('form');
        return null;
    }

    public function delete(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $VTC = $this->fetchTable('VehicleTypeCategories');
        $entity = $VTC->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($VTC->delete($entity)) {
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: zwraca kategorie tolls dla typu pojazdu (dla planera tras).
     * GET /admin/vehicle-type-categories/for-type/{type}
     * Zwraca JSON: [{country_code, system_name, category_label, notes}, ...]
     */
    public function forType(string $type): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $VTC = $this->fetchTable('VehicleTypeCategories');
        $rows = $VTC->find()
            ->where(['company_id' => $companyId, 'vehicle_type_code' => $type, 'is_active' => true])
            ->orderByAsc('country_code')
            ->orderByAsc('system_name')
            ->all();

        $out = [];
        foreach ($rows as $c) {
            $out[] = [
                'country_code'   => (string)$c->country_code,
                'system_name'    => (string)$c->system_name,
                'category_label' => (string)$c->category_label,
                'notes'          => (string)($c->notes ?? ''),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'type' => $type, 'categories' => $out], JSON_UNESCAPED_UNICODE));
    }
}

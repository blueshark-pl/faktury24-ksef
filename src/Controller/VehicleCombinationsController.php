<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;

/**
 * CRUD zestawów: ciągnik + naczepa + kierowca (nazwane, klikalne).
 *
 * Akcje:
 *   index  GET  /zestawy
 *   add    GET  /zestawy/dodaj
 *          POST /zestawy/dodaj
 *   edit   GET  /zestawy/edytuj/{id}
 *          POST /zestawy/edytuj/{id}
 *   delete POST /zestawy/usun/{id}
 *   list   GET  /zestawy/lista.json     — AJAX dla planera (aktywne zestawy)
 *   view   GET  /zestawy/dane/{id}.json — AJAX pełne dane zestawu (do auto-fill)
 */
class VehicleCombinationsController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $VC = $this->fetchTable('VehicleCombinations');
        $combinations = $VC->find()
            ->where(['VehicleCombinations.company_id' => $companyId])
            ->contain([
                'Vehicles' => function ($q) {
                    return $q->select(['id', 'name', 'plate', 'combination_type', 'axle_count']);
                },
                'Trailers' => function ($q) {
                    return $q->select(['id', 'name', 'plate', 'type', 'axle_count']);
                },
                'Drivers' => function ($q) {
                    return $q->select(['id', 'full_name']);
                },
            ])
            ->orderByDesc('VehicleCombinations.is_default')
            ->orderByAsc('VehicleCombinations.name')
            ->all();

        $this->set(compact('combinations'));
        $this->set('title', 'Zestawy pojazd + naczepa + kierowca');
    }

    public function add(): ?\Cake\Http\Response
    {
        return $this->_upsert(null);
    }

    public function edit(string $id): ?\Cake\Http\Response
    {
        return $this->_upsert($id);
    }

    private function _upsert(?string $id): ?\Cake\Http\Response
    {
        $companyId = $this->companyId();
        $VC = $this->fetchTable('VehicleCombinations');

        if ($id) {
            $entity = $VC->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        } else {
            $entity = $VC->newEmptyEntity();
            $entity->set('is_active', true);
            $entity->set('company_id', $companyId);
            $entity->set('id', Text::uuid());
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            foreach (['vehicle_id', 'trailer_id', 'driver_id'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') {
                    $data[$f] = null;
                }
            }
            $entity = $VC->patchEntity($entity, $data);
            if ($VC->save($entity)) {
                // Jeśli oznaczono jako default → wyłącz default na innych
                if (!empty($entity->is_default)) {
                    $VC->updateAll(
                        ['is_default' => false],
                        ['company_id' => $companyId, 'id !=' => $entity->id]
                    );
                }
                $this->Flash->success('Zestaw zapisany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź pola.');
        }

        // Listy do selectów
        $Vehicles = $this->fetchTable('Vehicles');
        $vehicleOptions = $Vehicles->find('list', [
            'keyField' => 'id',
            'valueField' => function ($v) {
                return trim(($v->name ?? '') . ($v->plate ? ' (' . $v->plate . ')' : ''));
            },
        ])
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByAsc('name')
            ->toArray();

        $Trailers = $this->fetchTable('Trailers');
        $trailerOptions = $Trailers->find('list', [
            'keyField' => 'id',
            'valueField' => function ($t) {
                return trim(($t->name ?? '') . ($t->plate ? ' (' . $t->plate . ')' : ''));
            },
        ])
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByAsc('name')
            ->toArray();

        $Drivers = $this->fetchTable('Drivers');
        $driverOptions = $Drivers->find('list', [
            'keyField' => 'id',
            'valueField' => function ($d) {
                return (string)($d->full_name ?? '');
            },
        ])
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByAsc('full_name')
            ->toArray();

        $this->set(compact('entity', 'vehicleOptions', 'trailerOptions', 'driverOptions'));
        $this->set('title', $id ? 'Edytuj zestaw' : 'Nowy zestaw');
        $this->render('form');
        return null;
    }

    public function delete(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $VC = $this->fetchTable('VehicleCombinations');
        $entity = $VC->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($VC->delete($entity)) {
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: lista aktywnych zestawów dla planera.
     * GET /zestawy/lista.json
     * Zwraca: [{id, name, vehicle_id, trailer_id, driver_id, is_default}, ...]
     */
    public function listJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $VC = $this->fetchTable('VehicleCombinations');
        $rows = $VC->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByDesc('is_default')
            ->orderByAsc('name')
            ->all();

        $out = [];
        foreach ($rows as $c) {
            $out[] = [
                'id'         => (string)$c->id,
                'name'       => (string)$c->name,
                'vehicle_id' => $c->vehicle_id ? (string)$c->vehicle_id : null,
                'trailer_id' => $c->trailer_id ? (string)$c->trailer_id : null,
                'driver_id'  => $c->driver_id ? (string)$c->driver_id : null,
                'is_default' => (bool)$c->is_default,
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'combinations' => $out], JSON_UNESCAPED_UNICODE));
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Pojazdy floty firmy — używane w kalkulatorze trasy HERE Routing v8.
 * Per-firmowe (scope: company_id z identity).
 */
class VehiclesController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $event->setResult($this->redirect('/users/login'));
        }
    }

    private function getCompanyId(): string
    {
        $companyId = (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
        if ($companyId === '') {
            throw new NotFoundException(__('Brak przypisanej firmy.'));
        }
        return $companyId;
    }

    public function index(): void
    {
        $companyId = $this->getCompanyId();
        $q = trim((string)$this->request->getQuery('q', ''));
        $type = trim((string)$this->request->getQuery('type', ''));

        $Vehicles = $this->fetchTable('Vehicles');
        $query = $Vehicles->find()
            ->where(['company_id' => $companyId])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByAsc('name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'name LIKE'  => $like,
                'plate LIKE' => $like,
                'vin LIKE'   => $like,
            ]]);
        }
        if (in_array($type, ['truck', 'tractor', 'van', 'trailer'], true)) {
            $query->where(['type' => $type]);
        }

        $rows = $query->all();
        $this->set(compact('rows', 'q', 'type'));
    }

    public function add(): ?Response
    {
        $companyId = $this->getCompanyId();
        $Vehicles = $this->fetchTable('Vehicles');
        $entity = $Vehicles->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->convertMetersToCm($this->request->getData());
            $entity = $Vehicles->patchEntity($entity, $data);
            $entity->id = Text::uuid();
            $entity->company_id = $companyId;
            if (!empty($data['is_default'])) {
                $Vehicles->updateAll(['is_default' => false], ['company_id' => $companyId]);
            }
            if ($Vehicles->save($entity)) {
                $this->Flash->success(__('Pojazd dodany.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Nie udało się zapisać pojazdu.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function edit(string $id): ?Response
    {
        $companyId = $this->getCompanyId();
        $Vehicles = $this->fetchTable('Vehicles');
        $entity = $Vehicles->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();
        if (!$entity) {
            throw new NotFoundException(__('Pojazd nie istnieje.'));
        }
        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->convertMetersToCm($this->request->getData());
            $entity = $Vehicles->patchEntity($entity, $data);
            if (!empty($data['is_default'])) {
                $Vehicles->updateAll(
                    ['is_default' => false],
                    ['company_id' => $companyId, 'id !=' => $id]
                );
            }
            if ($Vehicles->save($entity)) {
                $this->Flash->success(__('Zapisano zmiany.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Nie udało się zapisać.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $companyId = $this->getCompanyId();
        $Vehicles = $this->fetchTable('Vehicles');
        $entity = $Vehicles->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();
        if (!$entity) {
            throw new NotFoundException(__('Pojazd nie istnieje.'));
        }
        if ($Vehicles->delete($entity)) {
            $this->Flash->success(__('Pojazd usunięty.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć.'));
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * Konwersja pól {width,height,length}_m (metry, decimal) na {width,height,length}_cm (cm, int).
     * Form wysyła w metrach dla wygody usera (np. 2.55), DB trzyma cm int (255).
     */
    private function convertMetersToCm(array $data): array
    {
        foreach (['width', 'height', 'length'] as $dim) {
            $mKey = $dim . '_m';
            $cmKey = $dim . '_cm';
            if (isset($data[$mKey]) && $data[$mKey] !== '') {
                $data[$cmKey] = (int)round(((float)$data[$mKey]) * 100);
            }
            unset($data[$mKey]);
        }
        return $data;
    }
}

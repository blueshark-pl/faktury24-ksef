<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Naczepy / przyczepy — osobna encja od pojazdów (Hogis-style).
 * W planerze trasy łączone z ciągnikiem (vehicles) i kierowcą (drivers).
 */
class TrailersController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if (!$this->request->getAttribute('identity')) {
            $event->setResult($this->redirect('/users/login'));
        }
    }

    private function getCompanyId(): string
    {
        $companyId = (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
        if ($companyId === '') throw new NotFoundException(__('Brak przypisanej firmy.'));
        return $companyId;
    }

    public function index(): void
    {
        $companyId = $this->getCompanyId();
        $q = trim((string)$this->request->getQuery('q', ''));
        $type = trim((string)$this->request->getQuery('type', ''));

        $Trailers = $this->fetchTable('Trailers');
        $query = $Trailers->find()
            ->where(['company_id' => $companyId])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByAsc('name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => ['name LIKE' => $like, 'plate LIKE' => $like, 'vin LIKE' => $like]]);
        }
        if (in_array($type, ['curtain','box','fridge','tanker','flatbed','drawbar','mega','silo','container','tipper'], true)) {
            $query->where(['type' => $type]);
        }

        $rows = $query->all();
        $this->set(compact('rows', 'q', 'type'));
    }

    public function add(): ?Response
    {
        $companyId = $this->getCompanyId();
        $Trailers = $this->fetchTable('Trailers');
        $entity = $Trailers->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->convertMetersToCm($this->request->getData());
            $entity = $Trailers->patchEntity($entity, $data);
            $entity->id = Text::uuid();
            $entity->company_id = $companyId;
            if (!empty($data['is_default'])) {
                $Trailers->updateAll(['is_default' => false], ['company_id' => $companyId]);
            }
            if ($Trailers->save($entity)) {
                $this->Flash->success(__('Naczepa dodana.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Nie udało się zapisać.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function edit(string $id): ?Response
    {
        $companyId = $this->getCompanyId();
        $Trailers = $this->fetchTable('Trailers');
        $entity = $Trailers->find()->where(['id' => $id, 'company_id' => $companyId])->first();
        if (!$entity) throw new NotFoundException(__('Naczepa nie istnieje.'));

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->convertMetersToCm($this->request->getData());
            $entity = $Trailers->patchEntity($entity, $data);
            if (!empty($data['is_default'])) {
                $Trailers->updateAll(['is_default' => false], ['company_id' => $companyId, 'id !=' => $id]);
            }
            if ($Trailers->save($entity)) {
                $this->Flash->success(__('Zapisano zmiany.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Nie udało się zapisać.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function delete(string $id): ?Response
    {
        $companyId = $this->getCompanyId();
        $this->request->allowMethod(['post', 'delete']);
        $Trailers = $this->fetchTable('Trailers');
        $entity = $Trailers->find()->where(['id' => $id, 'company_id' => $companyId])->first();
        if (!$entity) throw new NotFoundException(__('Naczepa nie istnieje.'));
        if ($Trailers->delete($entity)) {
            $this->Flash->success(__('Naczepa usunięta.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć.'));
        }
        return $this->redirect(['action' => 'index']);
    }

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

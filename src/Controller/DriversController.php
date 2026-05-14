<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Kierowcy — osobna encja z parametrami osobowymi i stawkami.
 * W planerze trasy ich stawka godzinowa wchodzi do kalkulacji kosztu.
 */
class DriversController extends AppController
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

        $Drivers = $this->fetchTable('Drivers');
        $query = $Drivers->find()
            ->where(['company_id' => $companyId])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByAsc('full_name');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => ['full_name LIKE' => $like, 'phone LIKE' => $like, 'email LIKE' => $like]]);
        }

        $rows = $query->all();
        $this->set(compact('rows', 'q'));
    }

    public function add(): ?Response
    {
        $companyId = $this->getCompanyId();
        $Drivers = $this->fetchTable('Drivers');
        $entity = $Drivers->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $entity = $Drivers->patchEntity($entity, $data);
            $entity->id = Text::uuid();
            $entity->company_id = $companyId;
            if (!empty($data['is_default'])) {
                $Drivers->updateAll(['is_default' => false], ['company_id' => $companyId]);
            }
            if ($Drivers->save($entity)) {
                $this->Flash->success(__('Kierowca dodany.'));
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
        $Drivers = $this->fetchTable('Drivers');
        $entity = $Drivers->find()->where(['id' => $id, 'company_id' => $companyId])->first();
        if (!$entity) throw new NotFoundException(__('Kierowca nie istnieje.'));

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $entity = $Drivers->patchEntity($entity, $data);
            if (!empty($data['is_default'])) {
                $Drivers->updateAll(['is_default' => false], ['company_id' => $companyId, 'id !=' => $id]);
            }
            if ($Drivers->save($entity)) {
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
        $Drivers = $this->fetchTable('Drivers');
        $entity = $Drivers->find()->where(['id' => $id, 'company_id' => $companyId])->first();
        if (!$entity) throw new NotFoundException(__('Kierowca nie istnieje.'));
        if ($Drivers->delete($entity)) {
            $this->Flash->success(__('Kierowca usunięty.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}

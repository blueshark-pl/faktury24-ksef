<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Log czasu pracy kierowcy (tachograf/manual).
 *
 * Akcje:
 *   index            GET  /czas-pracy               — lista + filtr per driver / week
 *   add              GET  /czas-pracy/dodaj
 *                    POST
 *   edit             GET  /czas-pracy/edytuj/{id}
 *                    POST
 *   delete           POST /czas-pracy/usun/{id}
 *   weeklyStatusJson GET  /czas-pracy/status/{driverId}.json?week_iso=YYYY-Wnn
 *                          — AJAX: status tygodniowy dla planera
 */
class DriverTimeLogsController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    private function userId(): ?string
    {
        return $this->request->getAttribute('identity')?->getIdentifier();
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $driverId = (string)$this->request->getQuery('driver_id', '');
        $week     = (string)$this->request->getQuery('week', '');

        $DTL = $this->fetchTable('DriverTimeLogs');
        $query = $DTL->find()
            ->where(['DriverTimeLogs.company_id' => $companyId])
            ->contain(['Drivers' => function ($q) { return $q->select(['id', 'full_name']); }])
            ->orderByDesc('DriverTimeLogs.log_date');

        if ($driverId !== '') {
            $query->where(['DriverTimeLogs.driver_id' => $driverId]);
        }
        if ($week !== '') {
            $query->where(['DriverTimeLogs.week_iso' => $week]);
        }

        $logs = $this->paginate($query, ['limit' => 30]);

        $drivers = $this->fetchTable('Drivers')->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->select(['id', 'full_name'])
            ->orderByAsc('full_name')
            ->all();

        $this->set(compact('logs', 'drivers', 'driverId', 'week'));
        $this->set('title', 'Czas pracy kierowców');
    }

    public function add(): ?Response
    {
        return $this->_upsert(null);
    }

    public function edit(string $id): ?Response
    {
        return $this->_upsert($id);
    }

    private function _upsert(?string $id): ?Response
    {
        $companyId = $this->companyId();
        $DTL = $this->fetchTable('DriverTimeLogs');

        if ($id) {
            $entity = $DTL->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        } else {
            $entity = $DTL->newEmptyEntity();
            $entity->set('id', Text::uuid());
            $entity->set('company_id', $companyId);
            $entity->set('created_by_user_id', $this->userId());
            $entity->set('source', 'manual');
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            $entity = $DTL->patchEntity($entity, $data);
            if ($DTL->save($entity)) {
                $this->_logEvent($id ? 'updated' : 'created', $entity->id);
                $this->Flash->success('Wpis zapisany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu.');
        }

        $driverOptions = $this->fetchTable('Drivers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'full_name',
        ])->where(['company_id' => $companyId, 'is_active' => true])->orderByAsc('full_name')->toArray();

        $this->set(compact('entity', 'driverOptions'));
        $this->set('title', $id ? 'Edytuj wpis czasu pracy' : 'Nowy wpis czasu pracy');
        $this->render('form');
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $DTL = $this->fetchTable('DriverTimeLogs');
        $entity = $DTL->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($DTL->delete($entity)) {
            $this->_logEvent('deleted', $id);
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: GET /czas-pracy/status/{driverId}.json?week_iso=2026-W29
     * Zwraca weekly status kierowcy dla planera (używa DriverTimeLogsTable::weeklyStatus).
     */
    public function weeklyStatusJson(string $driverId): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $weekIso = (string)$this->request->getQuery('week_iso', '');
        if ($weekIso === '') {
            $weekIso = (new \DateTimeImmutable('today'))->format('o-\WW');
        }

        // Weryfikuj że driver należy do firmy
        $Drivers = $this->fetchTable('Drivers');
        $driver = $Drivers->find()->where(['id' => $driverId, 'company_id' => $companyId])->first();
        if (!$driver) {
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode(['ok' => false, 'error' => 'Kierowca nie znaleziony.']));
        }

        $DTL = $this->fetchTable('DriverTimeLogs');
        $status = $DTL->weeklyStatus($driverId, $weekIso);
        $status['driver'] = [
            'id' => (string)$driver->id,
            'full_name' => (string)$driver->full_name,
        ];

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['ok' => true, 'status' => $status], JSON_UNESCAPED_UNICODE));
    }

    private function _logEvent(string $eventName, string $entityId, array $payload = []): void
    {
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log($this->companyId(), 'driver_time_log', $entityId, $eventName, $this->userId(), $payload);
    }
}

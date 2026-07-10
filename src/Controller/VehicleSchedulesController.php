<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Grafik pojazdow i naczep (blokada zajetosci).
 *
 * Akcje CRUD + AJAX endpoints dla planera:
 *   availableVehiclesJson — pojazdy wolne w oknie
 *   availableTrailersJson — naczepy wolne w oknie
 */
class VehicleSchedulesController extends AppController
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

        $fromParam = (string)$this->request->getQuery('from', '');
        $from = $fromParam !== '' ? new \DateTime($fromParam) : new \DateTime('monday this week');
        $to = (clone $from)->modify('+13 days');

        $VS = $this->fetchTable('VehicleSchedules');
        $schedules = $VS->find()
            ->where([
                'VehicleSchedules.company_id' => $companyId,
                'VehicleSchedules.starts_at <' => $to->format('Y-m-d 23:59:59'),
                'VehicleSchedules.ends_at >'   => $from->format('Y-m-d 00:00:00'),
            ])
            ->contain([
                'Vehicles' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'Trailers' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'SpeedOrders' => function ($q) { return $q->select(['id', 'symbol', 'title1']); },
                'RoutePlans'  => function ($q) { return $q->select(['id', 'name']); },
            ])
            ->orderByAsc('VehicleSchedules.starts_at')
            ->all();

        $this->set(compact('schedules', 'from', 'to'));
        $this->set('title', 'Grafik pojazdów i naczep');
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
        $VS = $this->fetchTable('VehicleSchedules');

        if ($id) {
            $entity = $VS->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        } else {
            $entity = $VS->newEmptyEntity();
            $entity->set('id', Text::uuid());
            $entity->set('company_id', $companyId);
            $entity->set('created_by_user_id', $this->userId());
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            foreach (['vehicle_id', 'trailer_id', 'speed_order_id', 'route_plan_id'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') $data[$f] = null;
            }
            $entity = $VS->patchEntity($entity, $data);
            if ($VS->save($entity)) {
                $this->_logEvent($id ? 'updated' : 'created', $entity->id, ['entry_type' => $entity->entry_type]);
                $this->Flash->success('Wpis grafiku zapisany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź pola (podaj TYLKO pojazd LUB naczepę).');
        }

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

        $this->set(compact('entity', 'vehicleOptions', 'trailerOptions'));
        $this->set('title', $id ? 'Edytuj wpis grafiku' : 'Nowy wpis grafiku pojazdu/naczepy');
        $this->render('form');
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $VS = $this->fetchTable('VehicleSchedules');
        $entity = $VS->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($VS->delete($entity)) {
            $this->_logEvent('deleted', $id);
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /** AJAX: GET /grafik-pojazdow/wolne.json?start=...&end=... */
    public function availableVehiclesJson(): Response
    {
        return $this->_availableJson('vehicle');
    }

    /** AJAX: GET /grafik-naczep/wolne.json?start=...&end=... */
    public function availableTrailersJson(): Response
    {
        return $this->_availableJson('trailer');
    }

    private function _availableJson(string $kind): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $startStr = (string)$this->request->getQuery('start', '');
        $endStr   = (string)$this->request->getQuery('end', '');

        if ($startStr === '' || $endStr === '') {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak start lub end']));
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Niepoprawny format daty']));
        }

        $VS = $this->fetchTable('VehicleSchedules');
        $rows = $kind === 'vehicle'
            ? $VS->findAvailableVehiclesInWindow($companyId, $start, $end)->all()
            : $VS->findAvailableTrailersInWindow($companyId, $start, $end)->all();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (string)$r->id,
                'name' => (string)($r->name ?? ''),
                'plate' => (string)($r->plate ?? ''),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'window' => ['start' => $start->format('c'), 'end' => $end->format('c')],
                'kind'   => $kind,
                'items'  => $out,
            ], JSON_UNESCAPED_UNICODE));
    }

    private function _logEvent(string $eventName, string $entityId, array $payload = []): void
    {
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log(
            $this->companyId(),
            'vehicle_schedule',
            $entityId,
            $eventName,
            $this->userId(),
            $payload,
            ['ip' => $this->request->clientIp(), 'user_agent' => $this->request->getHeaderLine('User-Agent')]
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Historia serwisow, przegladow, ubezpieczen — z alertami o wygasajacych.
 *
 * Akcje:
 *   index         GET  /serwisy                    — lista + filtr wygasajacych
 *   add           GET  /serwisy/dodaj
 *                 POST
 *   edit          GET  /serwisy/edytuj/{id}
 *                 POST
 *   delete        POST /serwisy/usun/{id}
 *   expiringJson  GET  /serwisy/wygasajace.json    — AJAX: co wygasa w N dni
 */
class VehicleMaintenanceController extends AppController
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

        $filter = (string)$this->request->getQuery('filter', ''); // '' | 'expiring' | 'expired' | 'valid'
        $type   = (string)$this->request->getQuery('type', '');

        $VM = $this->fetchTable('VehicleMaintenance');
        $query = $VM->find()
            ->where(['VehicleMaintenance.company_id' => $companyId])
            ->contain([
                'Vehicles' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'Trailers' => function ($q) { return $q->select(['id', 'name', 'plate']); },
            ])
            ->orderByAsc('VehicleMaintenance.valid_until');

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $limit30 = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');

        if ($filter === 'expiring') {
            $query->where([
                'VehicleMaintenance.valid_until >='  => $today,
                'VehicleMaintenance.valid_until <='  => $limit30,
                'VehicleMaintenance.is_active'       => true,
            ]);
        } elseif ($filter === 'expired') {
            $query->where([
                'VehicleMaintenance.valid_until <' => $today,
                'VehicleMaintenance.is_active'    => true,
            ]);
        } elseif ($filter === 'valid') {
            $query->where([
                'VehicleMaintenance.valid_until >' => $limit30,
                'VehicleMaintenance.is_active'    => true,
            ]);
        }

        if ($type !== '') {
            $query->where(['VehicleMaintenance.maintenance_type' => $type]);
        }

        $records = $this->paginate($query, ['limit' => 30]);

        // Statystyki dla top bara
        $stats = [
            'expiring_soon' => $VM->find()
                ->where([
                    'company_id' => $companyId,
                    'is_active' => true,
                    'valid_until >='  => $today,
                    'valid_until <='  => $limit30,
                ])->count(),
            'expired' => $VM->find()
                ->where([
                    'company_id' => $companyId,
                    'is_active' => true,
                    'valid_until <' => $today,
                ])->count(),
        ];

        $this->set(compact('records', 'filter', 'type', 'stats'));
        $this->set('title', 'Serwisy, przeglądy i ubezpieczenia');
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
        $VM = $this->fetchTable('VehicleMaintenance');

        if ($id) {
            $entity = $VM->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        } else {
            $entity = $VM->newEmptyEntity();
            $entity->set('id', Text::uuid());
            $entity->set('company_id', $companyId);
            $entity->set('is_active', true);
            $entity->set('reminder_days', 30);
            $entity->set('created_by_user_id', $this->userId());
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            foreach (['vehicle_id', 'trailer_id', 'cost_invoice_id'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') $data[$f] = null;
            }
            $entity = $VM->patchEntity($entity, $data);
            if ($VM->save($entity)) {
                $this->_logEvent($id ? 'updated' : 'created', $entity->id, ['type' => $entity->maintenance_type]);
                $this->Flash->success('Wpis zapisany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź pola (podaj DOKŁADNIE jedno: pojazd LUB naczepę).');
        }

        $Vehicles = $this->fetchTable('Vehicles');
        $vehicleOptions = $Vehicles->find('list', [
            'keyField' => 'id',
            'valueField' => function ($v) { return trim(($v->name ?? '') . ($v->plate ? ' (' . $v->plate . ')' : '')); },
        ])->where(['company_id' => $companyId, 'is_active' => true])->orderByAsc('name')->toArray();

        $Trailers = $this->fetchTable('Trailers');
        $trailerOptions = $Trailers->find('list', [
            'keyField' => 'id',
            'valueField' => function ($t) { return trim(($t->name ?? '') . ($t->plate ? ' (' . $t->plate . ')' : '')); },
        ])->where(['company_id' => $companyId, 'is_active' => true])->orderByAsc('name')->toArray();

        $this->set(compact('entity', 'vehicleOptions', 'trailerOptions'));
        $this->set('title', $id ? 'Edytuj wpis serwisu' : 'Nowy wpis serwisu/przeglądu');
        $this->render('form');
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $VM = $this->fetchTable('VehicleMaintenance');
        $entity = $VM->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($VM->delete($entity)) {
            $this->_logEvent('deleted', $id);
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: GET /serwisy/wygasajace.json?days=30
     * Zwraca listę wpisów wygasających w podanej liczbie dni (dla planera/dashbordu).
     */
    public function expiringJson(): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();
        $days = (int)$this->request->getQuery('days', 30);
        if ($days < 1 || $days > 365) $days = 30;

        $VM = $this->fetchTable('VehicleMaintenance');
        $rows = $VM->findExpiringSoon($companyId, $days)->all();

        $out = [];
        foreach ($rows as $r) {
            $assetName = null; $assetPlate = null; $assetType = null;
            if (!empty($r->vehicle)) {
                $assetName = $r->vehicle->name; $assetPlate = $r->vehicle->plate; $assetType = 'vehicle';
            } elseif (!empty($r->trailer)) {
                $assetName = $r->trailer->name; $assetPlate = $r->trailer->plate; $assetType = 'trailer';
            }
            $out[] = [
                'id' => (string)$r->id,
                'asset_type' => $assetType,
                'asset_name' => (string)($assetName ?? ''),
                'asset_plate' => (string)($assetPlate ?? ''),
                'maintenance_type' => (string)$r->maintenance_type,
                'valid_until' => $r->valid_until instanceof \DateTimeInterface ? $r->valid_until->format('Y-m-d') : (string)$r->valid_until,
                'days_left' => $r->valid_until instanceof \DateTimeInterface
                    ? (new \DateTimeImmutable('today'))->diff($r->valid_until)->days * (($r->valid_until >= new \DateTimeImmutable('today')) ? 1 : -1)
                    : null,
                'is_expired' => $r->valid_until instanceof \DateTimeInterface && $r->valid_until < new \DateTimeImmutable('today'),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['ok' => true, 'days' => $days, 'records' => $out], JSON_UNESCAPED_UNICODE));
    }

    private function _logEvent(string $eventName, string $entityId, array $payload = []): void
    {
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log(
            $this->companyId(),
            'vehicle_maintenance',
            $entityId,
            $eventName,
            $this->userId(),
            $payload,
            ['ip' => $this->request->clientIp(), 'user_agent' => $this->request->getHeaderLine('User-Agent')]
        );
    }
}

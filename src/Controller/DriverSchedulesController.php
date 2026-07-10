<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Grafik kierowcow.
 *
 * Akcje:
 *   index      GET  /grafik-kierowcow                        — kalendarz (widok tygodniowy)
 *   add        GET  /grafik-kierowcow/dodaj                  — formularz nowego wpisu
 *              POST                                          — zapis
 *   edit       GET  /grafik-kierowcow/edytuj/{id}
 *              POST
 *   delete     POST /grafik-kierowcow/usun/{id}
 *   availableJson GET /grafik-kierowcow/wolni.json           — AJAX: kierowcy wolni w oknie
 *   forDriverJson GET /grafik-kierowcow/dla-kierowcy/{id}.json — grafik konkretnego kierowcy
 */
class DriverSchedulesController extends AppController
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

        // Domyslnie: tydzien od poniedzialku
        $fromParam = (string)$this->request->getQuery('from', '');
        $from = $fromParam !== ''
            ? new \DateTime($fromParam)
            : new \DateTime('monday this week');
        $to = (clone $from)->modify('+13 days'); // 2 tygodnie widoku

        $DS = $this->fetchTable('DriverSchedules');
        $schedules = $DS->find()
            ->where([
                'DriverSchedules.company_id' => $companyId,
                'DriverSchedules.starts_at <' => $to->format('Y-m-d 23:59:59'),
                'DriverSchedules.ends_at >'   => $from->format('Y-m-d 00:00:00'),
            ])
            ->contain([
                'Drivers' => function ($q) { return $q->select(['id', 'full_name']); },
                'SpeedOrders' => function ($q) { return $q->select(['id', 'symbol', 'title1']); },
                'RoutePlans' => function ($q) { return $q->select(['id', 'name']); },
            ])
            ->orderByAsc('DriverSchedules.starts_at')
            ->all();

        $Drivers = $this->fetchTable('Drivers');
        $drivers = $Drivers->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->select(['id', 'full_name'])
            ->orderByAsc('full_name')
            ->all();

        $this->set(compact('schedules', 'drivers', 'from', 'to'));
        $this->set('title', 'Grafik kierowców');
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
        $DS = $this->fetchTable('DriverSchedules');

        if ($id) {
            $entity = $DS->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        } else {
            $entity = $DS->newEmptyEntity();
            $entity->set('id', Text::uuid());
            $entity->set('company_id', $companyId);
            $entity->set('created_by_user_id', $this->userId());
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();
            $data['company_id'] = $companyId;
            // Odblokuj nullable FKs
            foreach (['speed_order_id', 'route_plan_id', 'vehicle_id', 'trailer_id'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') $data[$f] = null;
            }
            $entity = $DS->patchEntity($entity, $data);
            if ($DS->save($entity)) {
                $this->_logEvent($id ? 'updated' : 'created', $entity->id, ['entry_type' => $entity->entry_type]);
                $this->Flash->success('Wpis grafiku zapisany.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź pola.');
        }

        $Drivers = $this->fetchTable('Drivers');
        $driverOptions = $Drivers->find('list', [
            'keyField' => 'id',
            'valueField' => 'full_name',
        ])
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByAsc('full_name')
            ->toArray();

        $this->set(compact('entity', 'driverOptions'));
        $this->set('title', $id ? 'Edytuj wpis grafiku' : 'Nowy wpis grafiku');
        $this->render('form');
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $DS = $this->fetchTable('DriverSchedules');
        $entity = $DS->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        if ($DS->delete($entity)) {
            $this->_logEvent('deleted', $id);
            $this->Flash->success('Usunięto.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: lista kierowcow wolnych w podanym oknie.
     * GET /grafik-kierowcow/wolni.json?start=2026-07-15T08:00&end=2026-07-16T18:00&override_time_off=1
     */
    public function availableJson(): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $startStr = (string)$this->request->getQuery('start', '');
        $endStr   = (string)$this->request->getQuery('end', '');
        $override = filter_var($this->request->getQuery('override_time_off'), FILTER_VALIDATE_BOOL);

        if ($startStr === '' || $endStr === '') {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak parametru start lub end']));
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable $e) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Niepoprawny format daty (uzyj ISO 8601)']));
        }

        $DS = $this->fetchTable('DriverSchedules');
        $available = $DS->findAvailableInWindow($companyId, $start, $end, $override)->all();

        $out = [];
        foreach ($available as $d) {
            $out[] = [
                'id' => (string)$d->id,
                'full_name' => (string)$d->full_name,
                'hourly_rate_pln' => $d->hourly_rate_pln !== null ? (float)$d->hourly_rate_pln : null,
                'adr_certified' => (bool)($d->adr_certified ?? false),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'window' => ['start' => $start->format('c'), 'end' => $end->format('c')],
                'drivers' => $out,
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * AJAX: grafik konkretnego kierowcy w oknie.
     * GET /grafik-kierowcow/dla-kierowcy/{id}.json?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function forDriverJson(string $driverId): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $fromParam = (string)$this->request->getQuery('from', '');
        $toParam   = (string)$this->request->getQuery('to', '');
        $from = $fromParam !== '' ? new \DateTime($fromParam) : new \DateTime('-7 days');
        $to   = $toParam !== ''   ? new \DateTime($toParam)   : new \DateTime('+30 days');

        $DS = $this->fetchTable('DriverSchedules');
        $rows = $DS->find()
            ->where([
                'company_id' => $companyId,
                'driver_id'  => $driverId,
                'starts_at <' => $to->format('Y-m-d 23:59:59'),
                'ends_at >'   => $from->format('Y-m-d 00:00:00'),
            ])
            ->orderByAsc('starts_at')
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (string)$r->id,
                'starts_at' => $r->starts_at instanceof \DateTimeInterface ? $r->starts_at->format('c') : (string)$r->starts_at,
                'ends_at'   => $r->ends_at   instanceof \DateTimeInterface ? $r->ends_at->format('c')   : (string)$r->ends_at,
                'entry_type' => (string)$r->entry_type,
                'speed_order_id' => $r->speed_order_id,
                'route_plan_id'  => $r->route_plan_id,
                'notes' => (string)($r->notes ?? ''),
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'schedule' => $out], JSON_UNESCAPED_UNICODE));
    }

    private function _logEvent(string $eventName, string $entityId, array $payload = []): void
    {
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log(
            $this->companyId(),
            'driver_schedule',
            $entityId,
            $eventName,
            $this->userId(),
            $payload,
            ['ip' => $this->request->clientIp(), 'user_agent' => $this->request->getHeaderLine('User-Agent')]
        );
    }
}

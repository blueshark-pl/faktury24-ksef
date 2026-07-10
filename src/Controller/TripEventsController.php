<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Timeline zdarzen w trakcie zlecenia.
 * Operator dodaje z UI, kierowca z telefonu przez publiczny link tokenowy.
 *
 * Akcje operatora:
 *   forOrder     GET  /trip-events/zlecenie/{orderId}  — timeline zlecenia
 *   addEvent     POST /trip-events/dodaj                — nowy event
 *   delete       POST /trip-events/usun/{id}
 *
 * Akcje publiczne (bez logowania, przez token na speed_orders):
 *   driverView   GET  /kierowca/{token}                 — mobile view zlecenia
 *   driverPost   POST /kierowca/{token}/event           — kierowca zglasza event/POD
 */
class TripEventsController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated(['driverView', 'driverPost']);
        }
    }

    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    private function userId(): ?string
    {
        return $this->request->getAttribute('identity')?->getIdentifier();
    }

    /**
     * WAZNE (CLAUDE.md #4a): speed_orders nie ma company_id, ma company_nip.
     * Ta metoda zwraca liste NIP-ow firmy (z 'PL' i bez) do filtrowania.
     */
    private function companyNipList(): array
    {
        $companyId = $this->companyId();
        if ($companyId === '') return [];
        try {
            $company = $this->fetchTable('Companies')->find()
                ->select(['nip'])->where(['id' => $companyId])->first();
            if ($company && !empty($company->nip)) {
                $nip = preg_replace('/\D+/', '', (string)$company->nip);
                if ($nip !== '') return [$nip, 'PL' . $nip];
            }
        } catch (\Throwable) {}
        return [];
    }

    /**
     * Timeline zlecenia dla operatora.
     */
    public function forOrder(int $orderId): void
    {
        $this->request->allowMethod(['get']);
        $companyNipList = $this->companyNipList();
        if (empty($companyNipList)) {
            throw new \Cake\Http\Exception\ForbiddenException('Brak NIP-u firmy.');
        }

        $SO = $this->fetchTable('SpeedOrders');
        $order = $SO->find()
            ->where(['SpeedOrders.id' => $orderId, 'SpeedOrders.company_nip IN' => $companyNipList])
            ->firstOrFail();

        $TE = $this->fetchTable('TripEvents');
        $timeline = $TE->timelineForOrder($orderId);

        // Wygeneruj driver-view URL (share z kierowca)
        $driverUrl = $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost()
                   . '/kierowca/' . $this->_orderToken($order);

        $this->set(compact('order', 'timeline', 'driverUrl'));
        $this->set('title', 'Timeline: ' . ($order->symbol ?? '#' . $orderId));
    }

    public function addEvent(): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $data = (array)$this->request->getData();

        $orderId = (int)($data['speed_order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->Flash->error('Brak zlecenia.');
            return $this->redirect($this->referer());
        }

        $companyNipList = $this->companyNipList();
        if (empty($companyNipList)) {
            throw new \Cake\Http\Exception\ForbiddenException('Brak NIP-u firmy.');
        }
        $SO = $this->fetchTable('SpeedOrders');
        $order = $SO->find()->where(['id' => $orderId, 'company_nip IN' => $companyNipList])->firstOrFail();

        $TE = $this->fetchTable('TripEvents');
        $entity = $TE->newEntity([
            'id'                  => Text::uuid(),
            'company_id'          => $companyId,
            'speed_order_id'      => $orderId,
            'event_type'          => (string)($data['event_type'] ?? 'note'),
            'happened_at'         => !empty($data['happened_at']) ? $data['happened_at'] : (new \DateTime())->format('Y-m-d H:i:s'),
            'notes'               => (string)($data['notes'] ?? ''),
            'delay_minutes'       => !empty($data['delay_minutes']) ? (int)$data['delay_minutes'] : null,
            'delay_reason'        => (string)($data['delay_reason'] ?? '') ?: null,
            'source'              => 'operator',
            'reported_by_user_id' => $this->userId(),
        ]);

        if ($TE->save($entity)) {
            $this->_logEvent('created', $entity->id, ['event_type' => $entity->event_type, 'speed_order_id' => $orderId]);
            $this->Flash->success('Event dodany.');
        } else {
            $this->Flash->error('Błąd zapisu eventu.');
        }
        return $this->redirect(['action' => 'forOrder', $orderId]);
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $TE = $this->fetchTable('TripEvents');
        $entity = $TE->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        $orderId = (int)$entity->speed_order_id;

        if ($TE->delete($entity)) {
            $this->_logEvent('deleted', $id);
            $this->Flash->success('Usunięto.');
        }
        return $this->redirect(['action' => 'forOrder', $orderId]);
    }

    // ─── PUBLICZNE — mobile view kierowcy ────────────────────────

    public function driverView(string $token): void
    {
        $this->request->allowMethod(['get']);
        $order = $this->_orderFromToken($token);
        if (!$order) {
            $this->set(['error' => 'Nieprawidłowy link.']);
            $this->render('driver_error');
            return;
        }

        $TE = $this->fetchTable('TripEvents');
        $timeline = $TE->timelineForOrder((int)$order->id);

        $this->set(compact('order', 'timeline', 'token'));
        $this->set('title', 'Zlecenie ' . ($order->symbol ?? ''));
        $this->viewBuilder()->setLayout('ajax');
    }

    public function driverPost(string $token): Response
    {
        $this->request->allowMethod(['post']);
        $order = $this->_orderFromToken($token);
        if (!$order) {
            return $this->redirect(['action' => 'driverView', $token]);
        }

        $data = (array)$this->request->getData();
        $eventType = (string)($data['event_type'] ?? 'note');
        $driverName = trim((string)($data['driver_name'] ?? ''));

        $TE = $this->fetchTable('TripEvents');
        $entity = $TE->newEmptyEntity();

        // Handle POD photo upload
        $photoPath = null;
        $uploadedFile = $this->request->getUploadedFile('photo');
        if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
            $mime = $uploadedFile->getClientMediaType();
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/heic'], true)
                && $uploadedFile->getSize() < 10 * 1024 * 1024) {
                $dir = WWW_ROOT . 'uploads' . DS . 'trip_pod';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = strtolower(pathinfo((string)$uploadedFile->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg');
                $fname = 'pod_' . $order->id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $uploadedFile->moveTo($dir . DS . $fname);
                $photoPath = 'uploads/trip_pod/' . $fname;
            }
        }

        $entity = $TE->patchEntity($entity, [
            'id'                => Text::uuid(),
            'company_id'        => $order->company_id,
            'speed_order_id'    => (int)$order->id,
            'event_type'        => $eventType,
            'happened_at'       => (new \DateTime())->format('Y-m-d H:i:s'),
            'notes'             => (string)($data['notes'] ?? ''),
            'delay_minutes'     => !empty($data['delay_minutes']) ? (int)$data['delay_minutes'] : null,
            'delay_reason'      => (string)($data['delay_reason'] ?? '') ?: null,
            'photo_path'        => $photoPath,
            'source'            => 'driver_mobile',
            'reported_by_name'  => $driverName ?: null,
            'location_lat'      => !empty($data['location_lat']) ? (float)$data['location_lat'] : null,
            'location_lng'      => !empty($data['location_lng']) ? (float)$data['location_lng'] : null,
        ]);

        if ($TE->save($entity)) {
            // Log do operational_events (bez usera — kierowca)
            try {
                $OE = $this->fetchTable('OperationalEvents');
                $OE->log(
                    (string)$order->company_id,
                    'trip_event',
                    $entity->id,
                    'driver_reported',
                    null,
                    ['event_type' => $eventType, 'order_id' => (int)$order->id, 'driver_name' => $driverName],
                    ['ip' => $this->request->clientIp()]
                );
            } catch (\Throwable) {}

            $this->Flash->success('Zgłoszenie zapisane. Dziękujemy!');
        } else {
            $this->Flash->error('Błąd zapisu.');
        }
        return $this->redirect(['action' => 'driverView', $token]);
    }

    /**
     * Deterministyczny token dla zlecenia: sha256(company_id + speed_orders.id + config_salt),
     * skrocony do 48 chars. Nie zapisujemy w DB — reconstructable.
     */
    private function _orderToken(object $order): string
    {
        $salt = (string)\Cake\Core\Configure::read('Security.salt');
        return substr(hash('sha256', $salt . '|trip|' . $order->company_id . '|' . $order->id), 0, 48);
    }

    private function _orderFromToken(string $token): ?object
    {
        if (strlen($token) !== 48) return null;
        $SO = $this->fetchTable('SpeedOrders');
        // Iteracja po wszystkich zleceniach jest niepraktyczna — trzeba by keyfetch.
        // Prostsze: skanuj otwarte + niedawne zlecenia i szukaj match.
        // Aby zoptymalizowac: dodac tabele link tokenow. Na razie ograniczamy do
        // zlecen z ostatnich 90 dni i status != anulowane.
        $recent = $SO->find()
            ->select(['id', 'company_id', 'symbol', 'title1', 'title2',
                      'place_from_name', 'place_to_name', 'date_load', 'date_delivery',
                      'load_country', 'unload_country', 'driver', 'vehicle_reg'])
            ->where([
                'SpeedOrders.date_doc >=' => (new \DateTimeImmutable('-90 days'))->format('Y-m-d'),
            ])
            ->limit(2000)
            ->all();
        foreach ($recent as $o) {
            if ($this->_orderToken($o) === $token) {
                return $o;
            }
        }
        return null;
    }

    private function _logEvent(string $eventName, string $entityId, array $payload = []): void
    {
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log($this->companyId(), 'trip_event', $entityId, $eventName, $this->userId(), $payload);
    }
}

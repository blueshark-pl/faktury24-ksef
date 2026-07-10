<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Oferty cenowe wysylane klientowi z planera tras.
 *
 * Akcje:
 *   index       GET  /oferty
 *   view        GET  /oferty/{id}
 *   send        POST /oferty/wyslij/{id}
 *   delete      POST /oferty/usun/{id}
 *   accessByToken GET /oferty/wglad/{token}  — PUBLICZNE (bez logowania)
 *   accept      POST /oferty/wglad/{token}/akceptuj — PUBLICZNE
 *   reject      POST /oferty/wglad/{token}/odrzuc  — PUBLICZNE
 */
class RouteOffersController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Publiczny wglad klienta bez logowania (walidacja przez token)
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated([
                'accessByToken', 'accept', 'reject'
            ]);
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

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $status = trim((string)$this->request->getQuery('status', ''));

        $RO = $this->fetchTable('RouteOffers');
        $query = $RO->find()
            ->where(['RouteOffers.company_id' => $companyId])
            ->contain([
                'RoutePlans' => function ($q) { return $q->select(['id', 'name', 'distance_km', 'duration_min']); },
                'Contractors' => function ($q) { return $q->select(['id', 'name', 'nip']); },
            ])
            ->orderByDesc('RouteOffers.created');

        if ($status !== '') {
            $query->where(['RouteOffers.status' => $status]);
        }

        $offers = $this->paginate($query, ['limit' => 20]);

        $this->set(compact('offers', 'status'));
        $this->set('title', 'Oferty cenowe');
    }

    public function view(string $id): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()
            ->where(['RouteOffers.id' => $id, 'RouteOffers.company_id' => $companyId])
            ->contain(['RoutePlans', 'Contractors'])
            ->firstOrFail();

        $this->set(compact('offer'));
        $this->set('title', 'Oferta ' . ($offer->subject ?? substr((string)$offer->id, 0, 8)));
    }

    /**
     * Utworzenie oferty z planera tras.
     * POST /oferty/utworz
     * Body: {
     *   route_plan_id?: uuid (jesli plan juz istnieje)
     *   plan_data?: {name, waypoints_json, calc_cost_json, distance_km, ...}  — dane do stworzenia planu na fly
     *   contractor_id, sent_to_email, sent_to_name?, subject?, message_body?
     *   price, currency, vat_rate?, payment_days?, valid_until?
     * }
     */
    public function create(): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $userId = $this->userId();
        $data = (array)$this->request->getData();

        $RP = $this->fetchTable('RoutePlans');
        $RO = $this->fetchTable('RouteOffers');

        // 1. Zdobadz lub utworz plan
        $planId = (string)($data['route_plan_id'] ?? '');
        if ($planId === '' && !empty($data['plan_data'])) {
            $planData = (array)$data['plan_data'];
            $plan = $RP->newEntity([
                'id'              => Text::uuid(),
                'company_id'      => $companyId,
                'author_user_id'  => $userId,
                'contractor_id'   => $data['contractor_id'] ?? null,
                'name'            => (string)($planData['name'] ?? 'Plan bez nazwy'),
                'status'          => 'offered',
                'waypoints_json'  => is_array($planData['waypoints_json'] ?? null) ? json_encode($planData['waypoints_json'], JSON_UNESCAPED_UNICODE) : ($planData['waypoints_json'] ?? null),
                'pickup_json'     => is_array($planData['pickup_json'] ?? null) ? json_encode($planData['pickup_json'], JSON_UNESCAPED_UNICODE) : ($planData['pickup_json'] ?? null),
                'calc_cost_json'  => is_array($planData['calc_cost_json'] ?? null) ? json_encode($planData['calc_cost_json'], JSON_UNESCAPED_UNICODE) : ($planData['calc_cost_json'] ?? null),
                'distance_km'     => $planData['distance_km'] ?? null,
                'duration_min'    => $planData['duration_min'] ?? null,
                'co2_kg'          => $planData['co2_kg'] ?? null,
                'currency'        => (string)($data['currency'] ?? 'PLN'),
                'suggested_price' => $data['price'] ?? null,
            ]);
            if (!$RP->save($plan)) {
                return $this->_jsonError('Nie udało się utworzyć planu.', 400);
            }
            $planId = $plan->id;
            $this->_logEvent('route_plan', $planId, 'created', ['from' => 'offer_create']);
        }

        if ($planId === '') {
            return $this->_jsonError('Brak route_plan_id lub plan_data.', 400);
        }

        // 2. Utworz oferte
        $offer = $RO->newEntity([
            'id'                 => Text::uuid(),
            'company_id'         => $companyId,
            'route_plan_id'      => $planId,
            'contractor_id'      => $data['contractor_id'] ?? null,
            'sent_to_email'      => (string)($data['sent_to_email'] ?? ''),
            'sent_to_name'       => (string)($data['sent_to_name'] ?? ''),
            'subject'            => (string)($data['subject'] ?? 'Oferta transportowa'),
            'message_body'       => (string)($data['message_body'] ?? ''),
            'price'              => $data['price'] ?? 0,
            'currency'           => strtoupper((string)($data['currency'] ?? 'PLN')),
            'vat_rate'           => $data['vat_rate'] ?? null,
            'payment_days'       => $data['payment_days'] ?? 30,
            'valid_until'        => $data['valid_until'] ?? null,
            'access_token'       => $this->_generateToken(),
            'status'             => 'draft',
            'created_by_user_id' => $userId,
        ]);

        if (!$RO->save($offer)) {
            return $this->_jsonError('Nie udało się zapisać oferty: ' . json_encode($offer->getErrors()), 400);
        }

        $this->_logEvent('route_offer', $offer->id, 'created', ['route_plan_id' => $planId]);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'ok' => true,
                'offer_id' => $offer->id,
                'access_url' => $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . '/oferty/wglad/' . $offer->access_token,
                'redirect' => \Cake\Routing\Router::url(['action' => 'view', $offer->id]),
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * POST /oferty/wyslij/{id} — wyslij oferte na email klienta i zmien status na 'sent'.
     */
    public function send(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();

        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->firstOrFail();

        if (empty($offer->sent_to_email)) {
            $this->Flash->error(__('Brak adresu email odbiorcy.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Wyslij email (best-effort)
        try {
            $mailer = new \Cake\Mailer\Mailer('default');
            $mailer->setTo($offer->sent_to_email)
                ->setSubject((string)$offer->subject ?: 'Oferta transportowa')
                ->setEmailFormat('html')
                ->viewBuilder()->setLayout('default')->setTemplate('route_offer');
            $mailer->setViewVars([
                'offer' => $offer,
                'accessUrl' => $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . '/oferty/wglad/' . $offer->access_token,
            ]);
            $mailer->deliver();

            $offer->status = 'sent';
            $offer->sent_at = new \DateTime();
            $RO->save($offer);

            $this->_logEvent('route_offer', $offer->id, 'sent', ['email' => $offer->sent_to_email]);
            $this->Flash->success(__('Oferta wysłana na :email', [':email' => $offer->sent_to_email]));
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('[RouteOffers] Wysylka email nieudana: ' . $e->getMessage());
            $this->Flash->error(__('Wysyłka nieudana: :err', [':err' => $e->getMessage()]));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();

        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();

        if ($RO->delete($offer)) {
            $this->_logEvent('route_offer', $id, 'deleted');
            $this->Flash->success(__('Oferta usunięta.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć oferty.'));
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * PUBLICZNE: GET /oferty/wglad/{token} — klient oglada oferte bez logowania.
     */
    public function accessByToken(string $token): void
    {
        $this->request->allowMethod(['get']);
        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()
            ->where(['access_token' => $token])
            ->contain(['RoutePlans', 'Contractors'])
            ->firstOrFail();

        // Sprawdz waznosc
        $isExpired = $offer->valid_until instanceof \DateTimeInterface
            ? $offer->valid_until < new \DateTime('today')
            : false;

        // Oznacz jako 'viewed' gdy klient otworzy po raz pierwszy
        if ($offer->status === 'sent' && !$isExpired) {
            $offer->status = 'viewed';
            $offer->viewed_at = new \DateTime();
            $RO->save($offer);
            $this->_logEvent('route_offer', $offer->id, 'viewed', [], (string)$offer->company_id);
        }

        $this->set(compact('offer', 'isExpired'));
        $this->set('title', 'Oferta ' . ($offer->subject ?? ''));
        $this->viewBuilder()->setLayout('ajax'); // standalone bez layout admin
    }

    /**
     * PUBLICZNE: POST /oferty/wglad/{token}/akceptuj
     */
    public function accept(string $token): Response
    {
        $this->request->allowMethod(['post']);
        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()->where(['access_token' => $token])->firstOrFail();

        if ($offer->status === 'accepted') {
            $this->Flash->info(__('Oferta jest już zaakceptowana.'));
            return $this->redirect(['action' => 'accessByToken', $token]);
        }
        if (in_array($offer->status, ['rejected', 'expired'], true)) {
            $this->Flash->error(__('Ta oferta nie może być już zaakceptowana.'));
            return $this->redirect(['action' => 'accessByToken', $token]);
        }

        $offer->status = 'accepted';
        $offer->decided_at = new \DateTime();
        $RO->save($offer);

        // Update plan
        $RP = $this->fetchTable('RoutePlans');
        $plan = $RP->get($offer->route_plan_id);
        $plan->status = 'accepted';
        $plan->accepted_price = $offer->price;
        $RP->save($plan);

        $this->_logEvent('route_offer', $offer->id, 'accepted', [], (string)$offer->company_id);
        $this->_logEvent('route_plan', $plan->id, 'accepted', ['offer_id' => $offer->id], (string)$offer->company_id);

        $this->Flash->success(__('Dziękujemy! Oferta została zaakceptowana. Skontaktujemy się wkrótce.'));
        return $this->redirect(['action' => 'accessByToken', $token]);
    }

    /**
     * PUBLICZNE: POST /oferty/wglad/{token}/odrzuc
     */
    public function reject(string $token): Response
    {
        $this->request->allowMethod(['post']);
        $RO = $this->fetchTable('RouteOffers');
        $offer = $RO->find()->where(['access_token' => $token])->firstOrFail();

        if (in_array($offer->status, ['accepted', 'rejected', 'expired'], true)) {
            return $this->redirect(['action' => 'accessByToken', $token]);
        }

        $offer->status = 'rejected';
        $offer->decided_at = new \DateTime();
        $offer->decision_reason = (string)$this->request->getData('reason', '');
        $RO->save($offer);

        $RP = $this->fetchTable('RoutePlans');
        $plan = $RP->get($offer->route_plan_id);
        $plan->status = 'rejected';
        $RP->save($plan);

        $this->_logEvent('route_offer', $offer->id, 'rejected', ['reason' => $offer->decision_reason], (string)$offer->company_id);

        $this->Flash->success(__('Oferta została odrzucona.'));
        return $this->redirect(['action' => 'accessByToken', $token]);
    }

    private function _generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function _logEvent(string $entityType, string $entityId, string $eventName, array $payload = [], ?string $companyIdOverride = null): void
    {
        $companyId = $companyIdOverride ?? $this->companyId();
        if ($companyId === '') return;
        $OE = $this->fetchTable('OperationalEvents');
        $OE->log(
            $companyId,
            $entityType,
            $entityId,
            $eventName,
            $this->userId(),
            $payload,
            ['ip' => $this->request->clientIp(), 'user_agent' => $this->request->getHeaderLine('User-Agent')]
        );
    }

    private function _jsonError(string $msg, int $status = 400): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
    }
}

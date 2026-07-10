<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\ReturnLoadCandidatesTable;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Ladunki powrotne — matching engine dla planera.
 *
 * Akcje:
 *   forPlan  GET  /powroty/{planId}       — lista kandydatow dla planu
 *   suggest  POST /powroty/{planId}/szukaj — wywolaj matching engine
 *   dismiss  POST /powroty/odrzuc/{id}
 */
class ReturnLoadsController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    public function forPlan(string $planId): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $RP = $this->fetchTable('RoutePlans');
        $plan = $RP->find()->where(['id' => $planId, 'company_id' => $companyId])->firstOrFail();

        $RLC = $this->fetchTable('ReturnLoadCandidates');
        $candidates = $RLC->find()
            ->where([
                'ReturnLoadCandidates.route_plan_id' => $planId,
                'ReturnLoadCandidates.status !='     => 'dismissed',
            ])
            ->contain(['SpeedOrders'])
            ->orderByDesc('ReturnLoadCandidates.match_score')
            ->all();

        $this->set(compact('plan', 'candidates'));
        $this->set('title', 'Powroty dla planu: ' . ($plan->name ?? ''));
    }

    /**
     * Uruchom matching engine: znajdz otwarte speed_orders w promieniu N km
     * od punktu koncowego planu, w oknie czasowym ±X dni od zakonczenia.
     */
    public function suggest(string $planId): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();

        $RP = $this->fetchTable('RoutePlans');
        $plan = $RP->find()->where(['id' => $planId, 'company_id' => $companyId])->firstOrFail();

        // Wyciagnij ostatni waypoint z waypoints_json (punkt koncowy trasy)
        $endPoint = $this->_extractEndPoint($plan);
        if (!$endPoint) {
            $this->Flash->error(__('Brak punktu końcowego w planie.'));
            return $this->redirect(['action' => 'forPlan', $planId]);
        }

        $endTime = $plan->planned_end_at ?: new \DateTimeImmutable('+1 day');
        $windowStart = (clone $endTime instanceof \DateTimeImmutable ? $endTime : new \DateTimeImmutable((string)$endTime))->modify('-1 day');
        $windowEnd   = (clone $endTime instanceof \DateTimeImmutable ? $endTime : new \DateTimeImmutable((string)$endTime))->modify('+5 days');
        $maxDeadheadKm = (float)($this->request->getData('max_deadhead_km', 150));

        // WAZNE (regula CLAUDE.md #4a): speed_orders nie ma company_id, ma company_nip.
        // Musimy pobrac NIP firmy z Companies.
        $companyNipDigits = null;
        try {
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()->select(['nip'])->where(['id' => $companyId])->first();
            if ($company && !empty($company->nip)) {
                $companyNipDigits = preg_replace('/\D+/', '', (string)$company->nip);
            }
        } catch (\Throwable) {}
        if (empty($companyNipDigits)) {
            $this->Flash->error(__('Brak NIP-u firmy — nie mogę wyszukać zleceń.'));
            return $this->redirect(['action' => 'forPlan', $planId]);
        }
        $companyNipList = [$companyNipDigits, 'PL' . $companyNipDigits];

        // Szukaj otwartych speed_orders — filtruj sensownie: musi miec datę zaladunku w oknie
        $SO = $this->fetchTable('SpeedOrders');
        $orders = $SO->find()
            ->where([
                'SpeedOrders.company_nip IN' => $companyNipList,
                'SpeedOrders.invoice_id IS' => null, // jeszcze niezafakturowane
                'SpeedOrders.date_load >=' => $windowStart->format('Y-m-d'),
                'SpeedOrders.date_load <=' => $windowEnd->format('Y-m-d'),
            ])
            ->select(['id', 'symbol', 'title1', 'place_from_name', 'place_to_name',
                     'load_country', 'unload_country', 'date_load', 'date_delivery',
                     'currency', 'buyer_nip'])
            ->limit(200)
            ->all();

        // Usun poprzednie sugestie dla tego planu (nie odrzucone recznie)
        $RLC = $this->fetchTable('ReturnLoadCandidates');
        $RLC->deleteAll([
            'route_plan_id' => $planId,
            'candidate_type' => 'internal',
            'status'         => 'suggested',
        ]);

        $added = 0;
        foreach ($orders as $o) {
            // Prosta heurystyka odleglosci: brak wspolrzednych zlecen → uzyj tylko nazwy miasta LIKE
            // Praktycznie: sprawdz czy miasto pickupu jest zbiezne z endPointem
            $matchesCity = false;
            $fromCity = strtolower((string)($o->place_from_name ?? ''));
            $endCity  = strtolower($endPoint['city'] ?? '');
            if ($endCity !== '' && $fromCity !== '' && str_contains($fromCity, $endCity)) {
                $matchesCity = true;
            }
            // Zblizone kraje?
            $sameCountry = !empty($endPoint['country'])
                && !empty($o->load_country)
                && strtoupper((string)$o->load_country) === strtoupper($endPoint['country']);

            if (!$matchesCity && !$sameCountry) continue;

            // Deadhead km — na razie 0 dla same city, 100 for same country
            $deadhead = $matchesCity ? 0.0 : ($sameCountry ? 100.0 : 200.0);

            // Time gap
            $orderLoad = $o->date_load instanceof \DateTimeInterface
                ? $o->date_load
                : new \DateTimeImmutable((string)$o->date_load);
            $endTimeAsImm = $endTime instanceof \DateTimeImmutable ? $endTime : new \DateTimeImmutable((string)$endTime);
            $gapH = abs(($orderLoad->getTimestamp() - $endTimeAsImm->getTimestamp()) / 3600);

            $score = ReturnLoadCandidatesTable::calcMatchScore($deadhead, $gapH, null);

            $entity = $RLC->newEntity([
                'id'                     => Text::uuid(),
                'company_id'             => $companyId,
                'route_plan_id'          => $planId,
                'candidate_type'         => 'internal',
                'speed_order_id'         => (int)$o->id,
                'from_city'              => (string)($o->place_from_name ?? ''),
                'from_country'           => (string)($o->load_country ?? ''),
                'to_city'                => (string)($o->place_to_name ?? ''),
                'to_country'             => (string)($o->unload_country ?? ''),
                'pickup_from'            => $orderLoad->format('Y-m-d H:i:s'),
                'distance_from_route_km' => $deadhead,
                'time_gap_hours'         => $gapH,
                'match_score'            => $score,
                'status'                 => 'suggested',
            ]);
            if ($RLC->save($entity)) $added++;
        }

        $this->Flash->success(__('Znaleziono :n kandydatów.', [':n' => $added]));
        return $this->redirect(['action' => 'forPlan', $planId]);
    }

    public function dismiss(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();
        $RLC = $this->fetchTable('ReturnLoadCandidates');
        $entity = $RLC->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();
        $planId = (string)$entity->route_plan_id;

        $entity->status = 'dismissed';
        $entity->dismissed_reason = (string)$this->request->getData('reason', '');
        $RLC->save($entity);
        $this->Flash->success('Odrzucono.');
        return $this->redirect(['action' => 'forPlan', $planId]);
    }

    private function _extractEndPoint(object $plan): ?array
    {
        if (empty($plan->waypoints_json)) return null;
        $wp = json_decode((string)$plan->waypoints_json, true);
        if (!is_array($wp) || empty($wp)) return null;
        $last = end($wp);
        if (!is_array($last)) return null;
        // Try to extract city/country
        $address = (string)($last['address'] ?? $last['label'] ?? '');
        // Prosto: pierwszy segment = miasto
        $city = trim(explode(',', $address)[0] ?? '');
        // Usun kod pocztowy z przodu
        $city = preg_replace('/^\d{2}[-\s]?\d{3}\s+/', '', $city);
        $city = preg_replace('/^\d{5}\s+/', '', $city);
        return [
            'city'    => $city,
            'country' => (string)($last['country'] ?? ''),
            'lat'     => $last['lat'] ?? null,
            'lng'     => $last['lng'] ?? null,
        ];
    }
}

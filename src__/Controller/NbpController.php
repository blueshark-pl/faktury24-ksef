<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Client;
use Cake\Http\Response;
use Cake\Datasource\Exception\RecordNotFoundException;

class NbpController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setLayout('default');
    }

    /**
     * Simple page with currency selector and date range to browse NBP average rates.
     */
    public function dictionary(): ?Response
    {
        $this->set('title', 'Słownik Walutowy NBP');
        // Defaults for date inputs (last 30 days)
        $to   = new \DateTimeImmutable('today');
        $from = $to->modify('-30 days');
        $this->set(compact('from', 'to'));
        return null;
    }

    /**
     * GET /nbp/rates?code=EUR&from=2025-09-28&to=2025-10-28
     * Returns array of daily mid rates for date range. Tries table A then B.
     */
    public function rates(): Response
    {
        $this->request->allowMethod(['get']);

        $code = strtoupper((string)$this->request->getQuery('code', ''));
        $from = (string)$this->request->getQuery('from', '');
        $to   = (string)$this->request->getQuery('to', '');
        if ($code === '' || $from === '' || $to === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Missing code/from/to']));
        }

        // Normalize dates
        try { $fromDt = new \DateTimeImmutable($from); } catch (\Throwable) { $fromDt = null; }
        try { $toDt   = new \DateTimeImmutable($to); }   catch (\Throwable) { $toDt = null; }
        if (!$fromDt || !$toDt || $fromDt > $toDt) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid date range']));
        }

        // PLN – base currency: synthesize constant 1.00 for working days (Mon–Fri)
        if ($code === 'PLN') {
            $rates = [];
            $cur = $fromDt;
            while ($cur <= $toDt) {
                $dow = (int)$cur->format('N'); // 1..7
                if ($dow <= 5) {
                    $rates[] = [
                        'effectiveDate' => $cur->format('Y-m-d'),
                        'mid' => 1.0,
                        'table' => '—'
                    ];
                }
                $cur = $cur->modify('+1 day');
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'currency' => 'PLN',
                    'name' => 'Złoty polski',
                    'table' => '—',
                    'from' => $fromDt->format('Y-m-d'),
                    'to'   => $toDt->format('Y-m-d'),
                    'rates' => $rates,
                    'note' => 'PLN is base currency – rate 1.00'
                ]));
        }

        $client = new Client(['timeout' => 8]);
        $fmt = fn($d) => $d->format('Y-m-d');
        $name = '';
        foreach (['A','B'] as $table) {
            try {
                $url = sprintf('https://api.nbp.pl/api/exchangerates/rates/%s/%s/%s/%s/?format=json',
                    $table, urlencode($code), $fmt($fromDt), $fmt($toDt));
                $resp = $client->get($url);
                if ($resp->isOk()) {
                    $json = (array)$resp->getJson();
                    $name = (string)($json['currency'] ?? $name);
                    $rows = isset($json['rates']) && is_array($json['rates']) ? $json['rates'] : [];
                    if (!empty($rows)) {
                        // Normalize structure
                        $rates = array_map(function($r){
                            return [
                                'effectiveDate' => (string)($r['effectiveDate'] ?? ''),
                                'mid' => (float)($r['mid'] ?? 0),
                            ];
                        }, $rows);
                        return $this->response->withType('application/json')
                            ->withStringBody(json_encode([
                                'success' => true,
                                'currency' => $code,
                                'name' => $name,
                                'table' => $table,
                                'from' => $fmt($fromDt),
                                'to'   => $fmt($toDt),
                                'rates' => $rates,
                            ]));
                    }
                }
            } catch (\Throwable $e) {
                // continue to next table
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'currency' => $code,
                'message' => 'Brak danych NBP dla wybranego zakresu',
                'from' => $fmt($fromDt),
                'to' => $fmt($toDt),
            ]));
    }

    /**
     * GET /nbp/favorites.json
     */
    public function favorites(): Response
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->getCompanyId();
        if (!$companyId) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Brak kontekstu firmy']));
        }
        $favorites = $this->fetchTable('CurrencyFavorites');
        $list = $favorites->find()
            ->select(['code'])
            ->where(['company_id' => $companyId])
            ->orderAsc('code')
            ->enableHydration(false)
            ->all()
            ->toList();
        $codes = array_values(array_unique(array_map(fn($r) => strtoupper((string)$r['code']), $list)));
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'favorites' => $codes]));
    }

    /**
     * POST /nbp/favorites-add.json { code: "EUR" }
     */
    public function favoritesAdd(): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->getCompanyId();
        if (!$companyId) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Brak kontekstu firmy']));
        }
        $code = strtoupper((string)($this->request->getData('code') ?? ''));
        if ($code === '' || strlen($code) !== 3) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nieprawidłowy kod waluty']));
        }
        $favorites = $this->fetchTable('CurrencyFavorites');
        $exists = $favorites->find()->where(['company_id' => $companyId, 'code' => $code])->first();
        if ($exists) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'message' => 'Już na liście']));
        }
        try{
            $entity = $favorites->newEntity(['company_id' => $companyId, 'code' => $code]);
            if ($favorites->save($entity)) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => true]));
            }
            $errs = $entity->getErrors();
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nie udało się dodać', 'errors' => $errs]));
        }catch(\Throwable $e){
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Wyjątek: '.$e->getMessage()]));
        }
    }

    /**
     * POST /nbp/favorites-remove.json { code: "EUR" }
     */
    public function favoritesRemove(): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->getCompanyId();
        if (!$companyId) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Brak kontekstu firmy']));
        }
        $code = strtoupper((string)($this->request->getData('code') ?? ''));
        if ($code === '' || strlen($code) !== 3) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Nieprawidłowy kod waluty']));
        }
        $favorites = $this->fetchTable('CurrencyFavorites');
        $pkNames = (array)$favorites->getPrimaryKey();
        $pkName = $pkNames[0] ?? 'id';
        $row = $favorites->find()
            ->select([$pkName])
            ->where(['company_id' => $companyId, 'code' => $code])
            ->first();
        try{
            if ($row) {
                $entity = $favorites->get($row->get($pkName));
                $favorites->delete($entity);
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => true]));
        }catch(\Throwable $e){
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Wyjątek: '.$e->getMessage()]));
        }
    }

    /**
     * Try to extract company id from authenticated identity.
     */
    protected function getCompanyId(): ?string
    {
        $id = null;
        $identity = $this->request->getAttribute('identity');
        if (!$identity && property_exists($this, 'Authentication')) {
            $identity = $this->Authentication->getIdentity();
        }
        if ($identity) {
            // common keys
            $candidates = ['company_id','companyId','company','active_company_id'];
            foreach ($candidates as $key) {
                if (is_array($identity) && !empty($identity[$key])) { $id = (string)$identity[$key]; break; }
                if (is_object($identity) && isset($identity->{$key})) { $id = (string)$identity->{$key}; break; }
                if (is_object($identity) && method_exists($identity, 'get') && $identity->get($key)) { $id = (string)$identity->get($key); break; }
            }
        }
        return $id ?: null;
    }
}

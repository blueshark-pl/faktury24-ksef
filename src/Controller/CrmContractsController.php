<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * CRUD kontraktow ramowych CRM/TSL.
 */
class CrmContractsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $q = trim((string)$this->request->getQuery('q', ''));
        $active = $this->request->getQuery('active');

        $CC = $this->fetchTable('CrmContracts');
        $query = $CC->find()
            ->contain(['Contractors' => function ($q) {
                return $q->select(['id', 'name', 'nip']);
            }])
            ->where(['CrmContracts.company_id' => $companyId])
            ->orderByDesc('CrmContracts.is_active')
            ->orderByAsc('CrmContracts.valid_to');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'CrmContracts.name LIKE'            => $like,
                'CrmContracts.contractor_name LIKE' => $like,
                'CrmContracts.contractor_nip LIKE'  => $like,
                'CrmContracts.from_city LIKE'       => $like,
                'CrmContracts.to_city LIKE'         => $like,
            ]]);
        }
        if ($active === '1') $query->where(['CrmContracts.is_active' => true]);
        if ($active === '0') $query->where(['CrmContracts.is_active' => false]);

        $rows = $query->limit(500)->all();
        $expiring = $CC->findExpiringSoon($companyId, 30);

        $this->set(compact('rows', 'q', 'active', 'expiring'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CC = $this->fetchTable('CrmContracts');
        $entity = $CC->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->prepareData($this->request->getData(), $companyId);
            $entity = $CC->patchEntity($entity, $data);
            if ($CC->save($entity)) {
                $this->Flash->success(__('Kontrakt zapisany.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu kontraktu.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CC = $this->fetchTable('CrmContracts');
        $entity = $CC->get($id);
        if ((string)$entity->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        if ($this->request->is('post')) {
            $data = $this->prepareData($this->request->getData(), $companyId);
            unset($data['company_id']);
            $entity = $CC->patchEntity($entity, $data);
            if ($CC->save($entity)) {
                $this->Flash->success(__('Kontrakt zaktualizowany.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $CC = $this->fetchTable('CrmContracts');
        $entity = $CC->get($id);
        if ((string)$entity->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $CC->delete($entity);
        $this->Flash->success(__('Kontrakt usunięty.'));
        $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: znajdz kontrakt dla danej trasy + NIP.
     * GET /kontrakty/match?nip=X&from_country=PL&from_city=Warszawa&to_country=DE&to_city=Berlin
     */
    public function matchJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $nip = trim((string)$this->request->getQuery('nip', ''));
        $route = [
            'from_country' => trim((string)$this->request->getQuery('from_country', '')),
            'from_city'    => trim((string)$this->request->getQuery('from_city', '')),
            'to_country'   => trim((string)$this->request->getQuery('to_country', '')),
            'to_city'      => trim((string)$this->request->getQuery('to_city', '')),
        ];

        $CC = $this->fetchTable('CrmContracts');
        $match = $CC->findBestMatch($companyId, $nip, $route);

        if (!$match) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'match' => null]));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'ok' => true,
                'match' => [
                    'id'            => $match->id,
                    'name'          => $match->name,
                    'price_netto'   => (float)$match->price_netto,
                    'currency'      => $match->currency,
                    'vat_rate'      => $match->vat_rate,
                    'payment_days'  => $match->payment_days,
                    'route_label'   => $match->getRouteLabel(),
                    'valid_to'      => $match->valid_to ? $match->valid_to->format('Y-m-d') : null,
                    'volume_used_pct' => $match->getVolumeUsedPct(),
                ],
            ]));
    }

    private function prepareData(array $data, string $companyId): array
    {
        $data['company_id'] = $companyId;
        if (isset($data['contractor_nip'])) {
            $data['contractor_nip'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$data['contractor_nip'])) ?: null;
        }
        foreach (['from_country', 'to_country'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = strtoupper(substr(trim((string)$data[$f]), 0, 2)) ?: null;
            }
        }
        foreach (['valid_from', 'valid_to'] as $f) {
            if (empty($data[$f])) $data[$f] = null;
        }
        $data['is_active'] = !empty($data['is_active']);
        return $data;
    }
}

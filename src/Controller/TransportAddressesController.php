<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Słownik adresów transportowych — załadunek/rozładunek.
 * Zalecane uzycie: autocomplete w formularzu zlecenia.
 */
class TransportAddressesController extends AppController
{
    public function index(): void
    {
        $q       = trim((string)$this->request->getQuery('q', ''));
        $type    = trim((string)$this->request->getQuery('type', ''));
        $country = strtoupper(trim((string)$this->request->getQuery('country', '')));
        $page    = max(1, (int)$this->request->getQuery('page', 1));
        $limit   = 50;

        // Sort whitelist
        $sortable = [
            'name'         => 'TransportAddresses.name',
            'city'         => 'TransportAddresses.city',
            'postal_code'  => 'TransportAddresses.postal_code',
            'country'      => 'TransportAddresses.country',
            'address_type' => 'TransportAddresses.address_type',
            'times_used'   => 'TransportAddresses.times_used',
        ];
        $sortKey = (string)$this->request->getQuery('sort', 'times_used');
        $sortDir = strtolower((string)$this->request->getQuery('direction', 'desc'));
        if (!isset($sortable[$sortKey]))         $sortKey = 'times_used';
        if (!in_array($sortDir, ['asc','desc'])) $sortDir = 'desc';

        $TransportAddresses = $this->fetchTable('TransportAddresses');
        $query = $TransportAddresses->find();

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'TransportAddresses.name LIKE'        => $like,
                'TransportAddresses.city LIKE'        => $like,
                'TransportAddresses.postal_code LIKE' => $like,
            ]]);
        }
        if (in_array($type, ['loading', 'unloading', 'both'], true)) {
            $query->where(['TransportAddresses.address_type' => $type]);
        }
        if ($country !== '') {
            $query->where(['TransportAddresses.country' => $country]);
        }

        if ($sortDir === 'asc') {
            $query->orderByAsc($sortable[$sortKey]);
        } else {
            $query->orderByDesc($sortable[$sortKey]);
        }

        $total = (clone $query)->count();
        $pages = max(1, (int)ceil($total / $limit));
        $page  = min($page, $pages);
        $rows  = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Lista krajów do filtra
        $countriesList = $TransportAddresses->find()
            ->select(['country'])
            ->where(['country IS NOT' => null, 'country !=' => ''])
            ->groupBy('country')
            ->orderBy(['country' => 'ASC'])
            ->disableHydration()
            ->all()
            ->extract('country')
            ->toArray();

        $this->set(compact('rows', 'total', 'page', 'pages', 'limit', 'q', 'type', 'country', 'countriesList', 'sortKey', 'sortDir'));
    }

    public function add(): ?Response
    {
        $TransportAddresses = $this->fetchTable('TransportAddresses');
        $entity = $TransportAddresses->newEmptyEntity();

        if ($this->request->is('post')) {
            $entity = $TransportAddresses->patchEntity($entity, $this->request->getData());
            $entity->set('country', strtoupper((string)($entity->country ?? 'PL')));
            if ($TransportAddresses->save($entity)) {
                $this->Flash->success(__('Adres dodany.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('Nie udało się zapisać adresu.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function edit(string $id): ?Response
    {
        $TransportAddresses = $this->fetchTable('TransportAddresses');
        $entity = $TransportAddresses->find()->where(['id' => $id])->first();
        if (!$entity) {
            throw new NotFoundException(__('Adres nie istnieje.'));
        }
        if ($this->request->is(['post', 'put', 'patch'])) {
            $entity = $TransportAddresses->patchEntity($entity, $this->request->getData());
            $entity->set('country', strtoupper((string)($entity->country ?? 'PL')));
            if ($TransportAddresses->save($entity)) {
                $this->Flash->success(__('Zapisano zmiany.'));
                return $this->redirect(['action' => 'edit', $entity->id]);
            }
            $this->Flash->error(__('Nie udało się zapisać.'));
        }
        $this->set(compact('entity'));
        return null;
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $TransportAddresses = $this->fetchTable('TransportAddresses');
        $entity = $TransportAddresses->find()->where(['id' => $id])->first();
        if (!$entity) {
            throw new NotFoundException(__('Adres nie istnieje.'));
        }
        if ($TransportAddresses->delete($entity)) {
            $this->Flash->success(__('Adres usunięty.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć.'));
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX endpoint dla autocomplete (Select2).
     * GET /slownik-adresow/search?q=gdansk&type=loading
     */
    public function search(): void
    {
        $this->request->allowMethod(['get']);
        $this->disableAutoRender();

        $q    = trim((string)$this->request->getQuery('q', ''));
        $type = trim((string)$this->request->getQuery('type', ''));

        $TransportAddresses = $this->fetchTable('TransportAddresses');
        $query = $TransportAddresses->find()
            ->select(['id', 'name', 'city', 'postal_code', 'country', 'address_type'])
            ->where(['is_active' => true]);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'name LIKE'        => $like,
                'city LIKE'        => $like,
                'postal_code LIKE' => $like,
            ]]);
        }
        if (in_array($type, ['loading', 'unloading'], true)) {
            // 'both' też pasuje
            $query->where(['address_type IN' => [$type, 'both']]);
        }

        $items = [];
        foreach ($query->orderByDesc('times_used')->limit(50)->all() as $r) {
            $items[] = [
                'id'   => $r->id,
                'text' => $r->full_label,
            ];
        }

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['results' => $items], JSON_UNESCAPED_UNICODE));
    }
}

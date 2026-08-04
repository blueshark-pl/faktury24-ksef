<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * CRUD katalogu palet (globalne + custom firmy).
 * Widoczne dla wszystkich: /palety
 * Custom firmy: /palety/dodaj (company_id = current)
 * Globalne (company_id=NULL): edytowalne tylko przez admin
 */
class PalletTypesController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $search    = trim((string)$this->request->getQuery('q', ''));
        $mfr       = trim((string)$this->request->getQuery('manufacturer', ''));

        $q = $this->fetchTable('PalletTypes')->findForCompany($companyId);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(['OR' => [
                'PalletTypes.code LIKE'         => $like,
                'PalletTypes.name LIKE'         => $like,
                'PalletTypes.description LIKE'  => $like,
            ]]);
        }
        if ($mfr !== '') $q->where(['PalletTypes.manufacturer' => $mfr]);

        $pallets = $q->all();

        $mfrs = $this->fetchTable('PalletTypes')->find()
            ->select(['manufacturer'])
            ->where(['manufacturer IS NOT' => null])
            ->group('manufacturer')
            ->orderByAsc('manufacturer')
            ->disableHydration()
            ->all()
            ->extract('manufacturer')
            ->toList();

        $this->set(compact('pallets', 'search', 'mfr', 'mfrs'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $PT = $this->fetchTable('PalletTypes');
        $pallet = $PT->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['company_id'] = $companyId; // custom per firma
            $pallet = $PT->patchEntity($pallet, $data);
            if ($PT->save($pallet)) {
                $this->Flash->success(__('Paleta zapisana.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('pallet'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $isAdmin   = (bool)($identity?->get('is_admin') ?? false);

        $PT = $this->fetchTable('PalletTypes');
        $pallet = $PT->get($id);
        // Global palety edytowalne tylko przez admin
        if ($pallet->company_id === null && !$isAdmin) {
            $this->Flash->error(__('Globalne palety może edytować tylko administrator.'));
            $this->redirect(['action' => 'index']);
            return;
        }
        if ($pallet->company_id !== null && (string)$pallet->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            unset($data['company_id']);
            $pallet = $PT->patchEntity($pallet, $data);
            if ($PT->save($pallet)) {
                $this->Flash->success(__('Paleta zaktualizowana.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('pallet'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $isAdmin   = (bool)($identity?->get('is_admin') ?? false);

        $PT = $this->fetchTable('PalletTypes');
        $pallet = $PT->get($id);
        if ($pallet->company_id === null && !$isAdmin) {
            $this->Flash->error(__('Globalnej palety nie można usunąć (tylko admin).'));
            $this->redirect(['action' => 'index']);
            return;
        }
        if ($pallet->company_id !== null && (string)$pallet->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $PT->delete($pallet);
        $this->Flash->success(__('Paleta usunięta.'));
        $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: lista palet dla dropdown w cargo items.
     * GET /palety/lista.json?q=EUR
     */
    public function listJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $search    = trim((string)$this->request->getQuery('q', ''));

        $q = $this->fetchTable('PalletTypes')->findForCompany($companyId)->limit(50);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(['OR' => [
                'PalletTypes.code LIKE' => $like,
                'PalletTypes.name LIKE' => $like,
            ]]);
        }
        $rows = $q->disableHydration()->all()->toList();
        $out = array_map(function ($r) {
            $dim = '';
            if (!empty($r['length_mm']) && !empty($r['width_mm'])) {
                $dim = $r['length_mm'] . 'x' . $r['width_mm'];
                if (!empty($r['height_mm'])) $dim .= 'x' . $r['height_mm'];
                $dim .= ' mm';
            }
            return [
                'id'   => $r['id'],
                'code' => $r['code'],
                'name' => $r['name'],
                'manufacturer' => $r['manufacturer'],
                'dimensions' => $dim,
            ];
        }, $rows);

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE));
        $this->autoRender = false;
    }
}

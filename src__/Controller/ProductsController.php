<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use Cake\Utility\Text;

class ProductsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Cake\View\View');
    }

    public function index(): ?Response
{
    $identity  = $this->getRequest()->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    $q       = trim((string)$this->request->getQuery('q', ''));
    $active  = $this->request->getQuery('active');
    $service = $this->request->getQuery('service');
    $unitId  = $this->request->getQuery('unit_id');
    $vatId   = $this->request->getQuery('vat_id');
    $limit   = (int)($this->request->getQuery('limit') ?? 25);
    $limit   = $limit > 0 ? $limit : 25;

    $Products = $this->fetchTable('Products');
    $query = $Products->find()
        ->where(['Products.company_id' => $companyId, 'Products.deleted' => 0]);

    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
        $query->andWhere(fn($exp) => $exp->or([
            'Products.name LIKE'     => $like,
            'Products.code LIKE'     => $like,
            'Products.pkwiu LIKE'    => $like,
            'Products.gtu_code LIKE' => $like,
            'Products.barcode LIKE'  => $like,
        ]));
    }
    if ($active  !== null && $active  !== '') $query->andWhere(['Products.is_active'  => (int)$active]);
    if ($service !== null && $service !== '') $query->andWhere(['Products.is_service' => (int)$service]);
    if ($unitId  !== null && $unitId  !== '') $query->andWhere(['Products.unit_id'   => (int)$unitId]);
    if ($vatId   !== null && $vatId   !== '') $query->andWhere(['Products.vat_id'    => (string)$vatId]);

    $query->order(['Products.name' => 'ASC', 'Products.code' => 'ASC', 'Products.created' => 'DESC']);

    // ✅ tu jest zmiana
    $products = $this->paginate($query, ['limit' => $limit]);

    $Units = $this->fetchTable('Units');
    $Vats  = $this->fetchTable('Vats');

    $units = $Units->find('list', keyField: 'id', valueField: 'name')
        ->where(['deleted' => 0])->orderAsc('name')->toArray();

    $vats = $Vats->find()->all()->combine('id', fn($v) => sprintf('%s (%.2f%%)', (string)$v->name, (float)$v->rate))->toArray();

    $this->set(compact('products', 'units', 'vats'));
    return null;
}


    public function view(string $id = null): ?Response
    {
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Products = $this->fetchTable('Products');
        $Units    = $this->fetchTable('Units');
        $Vats     = $this->fetchTable('Vats');

        $product = $Products->find()
            ->where(['Products.id' => $id, 'Products.company_id' => $companyId, 'Products.deleted' => 0])
            ->firstOrFail();

        $unitName = $Units->find()->where(['id' => $product->unit_id])->select(['name'])->first()?->name;
        $vat      = $Vats->find()->where(['id' => $product->vat_id])->first();

        $this->set(compact('product', 'unitName', 'vat'));
        return null;
    }

    public function viewJson(string $id): Response
    {
        $this->request->allowMethod(['get']);
        $this->response = $this->response->withType('application/json');

        try {
            $identity  = $this->getRequest()->getAttribute('identity');
            $companyId = $identity?->get('company_id');

            $Products = $this->fetchTable('Products');
            $p = $Products->find()
                ->where(['Products.id' => $id, 'Products.company_id' => $companyId, 'Products.deleted' => 0])
                ->firstOrFail();

            return $this->response->withStringBody(json_encode(['success' => true, 'product' => $p]));
        } catch (RecordNotFoundException) {
            return $this->response->withStringBody(json_encode([
                'success' => false,
                'message' => 'Nie znaleziono produktu.',
            ]));
        }
    }

    public function add(): Response|string|null
    {
        $this->request->allowMethod(['get', 'post']);
        
        $Products = $this->fetchTable('Products');
        $product  = $Products->newEmptyEntity();

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        if (!$companyId) {
            if ($this->request->is('ajax')) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Brak company_id w tożsamości.'
                    ]));
            }
            $this->Flash->error('Błąd autoryzacji.');
            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            
            // Validate required fields for AJAX requests
            if ($this->request->is('ajax')) {
                if (empty(trim((string)($data['name'] ?? '')))) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Nazwa produktu jest wymagana.'
                        ]));
                }
            }

            $data['company_id'] = $companyId;
            if (empty($data['id'])) $data['id'] = Text::uuid();
            
            // Set defaults for products created from invoice
            if ($this->request->is('ajax')) {
                $data['is_active'] = $data['is_active'] ?? 1;
                $data['deleted'] = 0;
                $data['currency'] = $data['currency'] ?? 'PLN';
                
                // Handle unit - if unit_name is provided, try to find or create unit
                if (!empty($data['unit_name']) && empty($data['unit_id'])) {
                    $Units = $this->fetchTable('Units');
                    $unit = $Units->find()
                        ->where(['name' => $data['unit_name'], 'deleted' => 0])
                        ->first();
                    
                    if (!$unit) {
                        // Create new unit if it doesn't exist
                        $unit = $Units->newEntity([
                            'name' => $data['unit_name'],
                            'deleted' => 0
                        ]);
                        if ($Units->save($unit)) {
                            $data['unit_id'] = $unit->id;
                        } else {
                            $data['unit_id'] = 1; // Fallback to default
                        }
                    } else {
                        $data['unit_id'] = $unit->id;
                    }
                } else if (empty($data['unit_id'])) {
                    $data['unit_id'] = 1; // Default to first unit
                }
                
                // Ensure we have a code
                if (empty($data['code']) && !empty($data['name'])) {
                    $data['code'] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($data['name'], 0, 10)));
                }
            }

            $product = $Products->patchEntity($product, $data);

            if ($Products->save($product)) {
                if ($this->request->is('ajax')) {
                    // Fetch the unit name for the response
                    $unitName = 'szt.'; // default
                    if ($product->unit_id) {
                        $Units = $this->fetchTable('Units');
                        $unit = $Units->find()->where(['id' => $product->unit_id])->first();
                        if ($unit) {
                            $unitName = $unit->name;
                        }
                    }
                    
                    // Return the product data in format expected by the invoice form
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'product' => [
                                'id'        => $product->id,
                                'name'      => $product->name,
                                'code'      => $product->code,
                                'net_price' => (float)$product->net_price,
                                'unit'      => $unitName,
                                'vat_id'    => $product->vat_id,
                                'pkwiu'     => $product->pkwiu,
                                'gtu_code'  => $product->gtu_code,
                                'is_service'=> (bool)$product->is_service,
                                'barcode'   => $product->barcode,
                                'description' => $product->description,
                            ],
                            'message' => ($product->is_service ? 'Usługa została dodana.' : 'Produkt został dodany.')
                        ]));
                }
                $this->Flash->success('Dodano produkt/usługę.');
                return $this->redirect(['action' => 'index']);
            }

            if ($this->request->is('ajax')) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Nie udało się zapisać produktu.',
                        'errors'  => $product->getErrors(),
                    ]));
            }
            $this->Flash->error('Nie udało się zapisać.');
        }

        // For non-AJAX requests, redirect to index
        if (!$this->request->is('ajax')) {
            return $this->redirect(['action' => 'index']);
        }

        return null;
    }

    public function edit(string $id): Response|string|null
    {
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Products = $this->fetchTable('Products');
        $product  = $Products->find()
            ->where(['Products.id' => $id, 'Products.company_id' => $companyId, 'Products.deleted' => 0])
            ->firstOrFail();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            unset($data['company_id'], $data['id']); // nie zmieniamy

            $Products->patchEntity($product, $data);

            if ($Products->save($product)) {
                if ($this->request->is('ajax')) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode(['success' => true, 'product' => $product]));
                }
                $this->Flash->success('Zapisano zmiany.');
                return $this->redirect(['action' => 'index']);
            }

            if ($this->request->is('ajax')) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Nie udało się zapisać.',
                        'errors'  => $product->getErrors(),
                    ]));
            }
            $this->Flash->error('Nie udało się zapisać zmian.');
        }

        return $this->redirect(['action' => 'index']);
    }

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Products = $this->fetchTable('Products');
        $product  = $Products->find()
            ->where(['Products.id' => $id, 'Products.company_id' => $companyId, 'Products.deleted' => 0])
            ->first();

        if ($product) {
            $product->deleted   = 1;
            $product->is_active = 0;
            $Products->save($product);
            $this->Flash->success('Usunięto produkt/usługę.');
        } else {
            $this->Flash->error('Nie znaleziono produktu lub już usunięty.');
        }

        return $this->redirect($this->referer(['action' => 'index'], true));
    }

    public function export(): Response
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $q       = trim((string)$this->request->getQuery('q', ''));
        $active  = $this->request->getQuery('active');
        $service = $this->request->getQuery('service');
        $unitId  = $this->request->getQuery('unit_id');
        $vatId   = $this->request->getQuery('vat_id');

        $Products = $this->fetchTable('Products');
        /** @var SelectQuery $query */
        $query = $Products->find()
            ->where(['Products.company_id' => $companyId, 'Products.deleted' => 0]);

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
            $query->andWhere(fn($exp) => $exp->or([
                'Products.name LIKE'     => $like,
                'Products.code LIKE'     => $like,
                'Products.pkwiu LIKE'    => $like,
                'Products.gtu_code LIKE' => $like,
                'Products.barcode LIKE'  => $like,
            ]));
        }
        if ($active !== null && $active !== '')   $query->andWhere(['Products.is_active' => (int)$active]);
        if ($service !== null && $service !== '') $query->andWhere(['Products.is_service' => (int)$service]);
        if ($unitId !== null && $unitId !== '')   $query->andWhere(['Products.unit_id' => (int)$unitId]);
        if ($vatId  !== null && $vatId  !== '')   $query->andWhere(['Products.vat_id'  => (string)$vatId]);

        $query->orderAsc('Products.name');

        $Units = $this->fetchTable('Units');
        $Vats  = $this->fetchTable('Vats');

        $units = $Units->find('list', keyField: 'id', valueField: 'name')->where(['deleted' => 0])->toArray();
        $vats  = $Vats->find()->all()->combine('id', fn($v) => sprintf('%s (%.2f%%)', (string)$v->name, (float)$v->rate))->toArray();

        $sep = ';'; $eol = "\r\n"; $bom = "\xEF\xBB\xBF";
        $rows = [];
        $rows[] = ['ID','Kod','Nazwa','Typ','Jm','VAT','Cena netto','Waluta','PKWiU','GTU','Kreskowy','Aktywne','Utworzono'];

        foreach ($query as $p) {
            $rows[] = [
                $p->id,
                $p->code ?? '',
                $p->name ?? '',
                (int)$p->is_service === 1 ? 'Usługa' : 'Produkt',
                $units[$p->unit_id] ?? (string)$p->unit_id,
                $vats[$p->vat_id] ?? '',
                number_format((float)$p->net_price, 2, '.', ''),
                $p->currency ?? 'PLN',
                $p->pkwiu ?? '',
                $p->gtu_code ?? '',
                $p->barcode ?? '',
                (int)$p->is_active === 1 ? '1' : '0',
                $p->created?->i18nFormat('yyyy-MM-dd HH:mm:ss') ?? '',
            ];
        }

        $escape = static function (string $v) use ($sep): string {
            $need = str_contains($v, $sep) || str_contains($v, '"') || str_contains($v, "\n") || str_contains($v, "\r");
            $v = str_replace('"', '""', $v);
            return $need ? "\"{$v}\"" : $v;
        };

        $csv = $bom;
        foreach ($rows as $r) {
            $csv .= implode($sep, array_map(fn($x) => $escape((string)$x), $r)) . $eol;
        }

        $filename = 'produkty_' . (new FrozenTime())->i18nFormat('yyyyMMdd_HHmmss') . '.csv';

        return $this->response
            ->withType('csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($csv))
            ->withStringBody($csv);
    }

    /**
     * Search method for AJAX requests from invoice forms
     * Returns JSON response with products matching the search term
     */
    public function search(): Response
    {
        $this->request->allowMethod(['get']);
        $this->response = $this->response->withType('application/json');

        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        if (!$companyId) {
            return $this->response->withStringBody(json_encode([
                'success' => false,
                'message' => 'Brak company_id w tożsamości.',
                'results' => []
            ]));
        }

        $q = trim((string)$this->request->getQuery('q', ''));
        $limit = min(50, max(1, (int)$this->request->getQuery('limit', 20))); // max 50 wyników

        $Products = $this->fetchTable('Products');
        $Units    = $this->fetchTable('Units');
        $Vats     = $this->fetchTable('Vats');

        $query = $Products->find()
            ->where([
                'Products.company_id' => $companyId,
                'Products.deleted'    => 0,
                'Products.is_active'  => 1
            ])
            ->limit($limit)
            ->order(['Products.name' => 'ASC']);

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
            $query->andWhere(function($exp) use ($like) {
                return $exp->or([
                    'Products.name LIKE'     => $like,
                    'Products.code LIKE'     => $like,
                    'Products.pkwiu LIKE'    => $like,
                    'Products.barcode LIKE'  => $like,
                ]);
            });
        }

        $products = $query->toArray();

        // Pobierz jednostki i VAT-y do mapowania
        $unitIds = array_unique(array_filter(array_column($products, 'unit_id')));
        $vatIds = array_unique(array_filter(array_column($products, 'vat_id')));

        $units = [];
        if (!empty($unitIds)) {
            $units = $Units->find()
                ->where(['id IN' => $unitIds, 'deleted' => 0])
                ->all()
                ->combine('id', 'name')
                ->toArray();
        }

        $vats = [];
        if (!empty($vatIds)) {
            $vats = $Vats->find()
                ->where(['id IN' => $vatIds])
                ->all()
                ->combine('id', 'rate')
                ->toArray();
        }

        // Formatuj wyniki dla JavaScript
        $results = [];
        foreach ($products as $p) {
            $results[] = [
                'id'        => $p->id,
                'text'      => sprintf('%s - %s', $p->code ?? $p->id, $p->name),
                'name'      => $p->name,
                'code'      => $p->code ?? '',
                'price'     => (float)$p->net_price,
                'unit'      => $units[$p->unit_id] ?? 'szt.',
                'vat_id'    => $p->vat_id,
                'vat_rate'  => (float)($vats[$p->vat_id] ?? 23.0),
                'pkwiu'     => $p->pkwiu ?? '',
                'gtu_code'  => $p->gtu_code ?? '',
                'is_service'=> (bool)$p->is_service,
            ];
        }

        return $this->response->withStringBody(json_encode([
            'success' => true,
            'results' => $results,
            'count'   => count($results)
        ]));
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
    }
}

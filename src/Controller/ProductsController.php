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


    public function view(?string $id = null): ?Response
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

            $unitName = $this->fetchTable('Units')->find()
                ->select(['name'])->where(['id' => $p->unit_id])->first()?->name ?? '';
            $vat = $this->fetchTable('Vats')->find()
                ->select(['name', 'rate'])->where(['id' => $p->vat_id])->first();
            $vatLabel = $vat ? sprintf('%s (%.2f%%)', $vat->name, (float)$vat->rate) : '';

            return $this->response->withStringBody(json_encode([
                'success'   => true,
                'product'   => $p,
                'unit_name' => $unitName,
                'vat_label' => $vatLabel,
            ]));
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
                
                // Jeśli użytkownik nie podał kodu — zostaw null (allowMultipleNulls w regule unikalności)
                if (empty($data['code'])) {
                    $data['code'] = null;
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
                                'id'               => $product->id,
                                'name'             => $product->name,
                                'code'             => $product->code,
                                'net_price'        => (float)$product->net_price,
                                'unit'             => $unitName,
                                'vat_id'           => $product->vat_id,
                                'pkwiu'            => $product->pkwiu,
                                'gtu_code'         => $product->gtu_code,
                                'is_service'       => (bool)$product->is_service,
                                'barcode'          => $product->barcode,
                                'description'      => $product->description,
                                'gtin'             => $product->gtin,
                                'cn_code'          => $product->cn_code,
                                'pkob'             => $product->pkob,
                                'excise_amount'    => $product->excise_amount !== null ? (float)$product->excise_amount : null,
                                'procedure_marking'=> $product->procedure_marking,
                                'is_attachment15'  => (bool)$product->is_attachment15,
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
                'pkwiu'             => $p->pkwiu ?? '',
                'gtu_code'          => $p->gtu_code ?? '',
                'gtin'              => $p->gtin ?? '',
                'cn_code'           => $p->cn_code ?? '',
                'pkob'              => $p->pkob ?? '',
                'excise_amount'     => $p->excise_amount !== null ? (float)$p->excise_amount : null,
                'procedure_marking' => $p->procedure_marking ?? '',
                'is_attachment15'   => (bool)$p->is_attachment15,
                'is_service'        => (bool)$p->is_service,
            ];
        }

        return $this->response->withStringBody(json_encode([
            'success' => true,
            'results' => $results,
            'count'   => count($results)
        ]));
    }

    /**
     * Pobiera listę towarów/usług ze starego systemu faktury24.com dla NIP bieżącej firmy.
     */
    public function importFetch(): Response
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $Companies = $this->fetchTable('Companies');
        $company = $Companies->find()
            ->select(['id', 'nip'])
            ->where(['id' => $companyId])
            ->first();

        $nip = preg_replace('/\D+/', '', (string)($company->nip ?? ''));
        if ($nip === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => 'Brak NIP-u firmy. Uzupełnij dane firmy przed importem.',
                ]));
        }

        try {
            $client = new \Cake\Http\Client();
            $resp = $client->get(
                'https://archiwum.faktury24.com/ajax/get_gns_by_nip',
                ['nip' => $nip],
                ['timeout' => 15]
            );
            $json = json_decode($resp->getStringBody(), true);
            if (!is_array($json) || ($json['status'] ?? 0) !== 200) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error'   => 'Serwer archiwum.faktury24.com nie zwrócił wyników (status: ' . ($json['status'] ?? '?') . ').',
                    ]));
            }

            $payload = $json['payload'] ?? [];

            // Pobierz już istniejące nazwy lokalnie (do oznaczenia duplikatów)
            $existingNames = [];
            if (!empty($payload)) {
                $remoteNames = array_filter(array_map(
                    fn($r) => trim((string)($r['gs_name'] ?? '')),
                    $payload
                ));
                if (!empty($remoteNames)) {
                    $found = $this->fetchTable('Products')->find()
                        ->select(['name'])
                        ->where(['company_id' => $companyId, 'deleted' => 0, 'name IN' => array_values($remoteNames)])
                        ->all();
                    foreach ($found as $p) {
                        $existingNames[mb_strtolower((string)$p->name)] = true;
                    }
                }
            }

            $rows = [];
            foreach ($payload as $r) {
                $name = trim((string)($r['gs_name'] ?? ''));
                if ($name === '') continue;
                // vat_vvat = "0.23" → mnożymy przez 100 → "23"
                $vatRaw = (float)($r['vat_vvat'] ?? 0.23);
                $vatRate = (string)round($vatRaw * 100);
                $rows[] = [
                    'gs_uuid'          => (string)($r['gs_uuid'] ?? ''),
                    'name'             => $name,
                    'net_price'        => (string)($r['gs_net_price'] ?? '0'),
                    'vat_rate'         => $vatRate,
                    'vat_label'        => (string)($r['vat_svat'] ?? $vatRate . '%'),
                    'unit_name'        => (string)($r['gs_unit'] ?? 'szt.'),
                    'already_imported' => isset($existingNames[mb_strtolower($name)]),
                ];
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'count'   => count($rows),
                    'rows'    => $rows,
                ]));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => 'Błąd połączenia z archiwum.faktury24.com: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Importuje wskazane towary/usługi z danych przekazanych w POST (JSON body).
     */
    public function importBatch(): Response
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $rows = (array)($this->request->getData('rows') ?? []);
        if (empty($rows)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak danych do importu.']));
        }

        $Products = $this->fetchTable('Products');
        $Units    = $this->fetchTable('Units');
        $Vats     = $this->fetchTable('Vats');

        // Zbuduj mapę stawka VAT (float) → vat_id
        $vatMap = [];
        foreach ($Vats->find()->all() as $v) {
            $vatMap[(string)(float)$v->rate] = (string)$v->id;
        }

        // Domyślna stawka 23%
        $defaultVatId = $vatMap['23'] ?? $vatMap['23.0'] ?? array_values($vatMap)[0] ?? null;

        // Jednostki – cache nazwa → id
        $unitCache = [];
        $getOrCreateUnit = function(string $name) use ($Units, &$unitCache): int {
            $name = trim($name) ?: 'szt.';
            if (isset($unitCache[$name])) return $unitCache[$name];
            $unit = $Units->find()->where(['name' => $name, 'deleted' => 0])->first();
            if (!$unit) {
                $unit = $Units->newEntity(['name' => $name, 'deleted' => 0]);
                $Units->save($unit);
            }
            $unitCache[$name] = (int)$unit->id;
            return (int)$unit->id;
        };

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $r) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') { $skipped++; continue; }

            // Duplikat po nazwie w tej samej firmie
            if ($Products->exists(['company_id' => $companyId, 'name' => $name, 'deleted' => 0])) {
                $skipped++;
                continue;
            }

            $vatRateKey = (string)(float)($r['vat_rate'] ?? 23);
            $vatId = $vatMap[$vatRateKey] ?? $defaultVatId;

            $unitId = $getOrCreateUnit((string)($r['unit_name'] ?? 'szt.'));

            $entity = $Products->newEntity([
                'company_id'  => $companyId,
                'name'        => $name,
                'code'        => (string)($r['code'] ?? '') ?: null,
                'description' => (string)($r['description'] ?? '') ?: null,
                'is_service'  => (bool)($r['is_service'] ?? false),
                'net_price'   => (float)($r['net_price'] ?? 0),
                'unit_id'     => $unitId,
                'vat_id'      => $vatId,
                'pkwiu'       => (string)($r['pkwiu'] ?? '') ?: null,
                'gtu_code'    => (string)($r['gtu_code'] ?? '') ?: null,
                'currency'    => (string)($r['currency'] ?? 'PLN') ?: 'PLN',
                'is_active'   => true,
                'deleted'     => false,
            ]);

            if ($Products->save($entity)) {
                $imported++;
            } else {
                $errors[] = 'Nie udało się zapisać: ' . $name;
                $skipped++;
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success'  => true,
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ]));
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
    }
}

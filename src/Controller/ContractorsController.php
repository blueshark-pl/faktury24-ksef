<?php
declare(strict_types=1);

namespace App\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Utility\Text;
use Cake\Http\Client;
use GusApi\GusApi;
use GusApi\Exception\InvalidUserKeyException;
use GusApi\Exception\NotFoundException;
use Cake\Database\Expression\QueryExpression;
use Cake\View\JsonView;

/**
 * Contractors Controller
 *
 * @property \App\Model\Table\ContractorsTable $Contractors
 */
class ContractorsController extends AppController
{

    public function vatStatusLookup()
    {
        $this->request->allowMethod(['post']);

        $nip = preg_replace('/\D+/', '', (string)$this->request->getData('nip'));
        if (!$nip || strlen($nip) !== 10) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Brak lub błędny NIP',
                ]));
        }

        try {
            $vat = [
                'statusVat' => null,
                'requestId' => null,
                'requestDateTime' => null,
                'accountNumbers' => [],
            ];

            $wlBase = rtrim((string)Configure::read('WlApi.baseUrl', 'https://wl-api.mf.gov.pl'), '/');
            $wlDate = date('Y-m-d');
            $client = new Client(['timeout' => 8]);
            $wlResp = $client->get($wlBase . '/api/search/nip/' . $nip, ['date' => $wlDate], [
                'headers' => ['Accept' => 'application/json'],
            ]);

            if ($wlResp->isOk()) {
                $wlData = (array)$wlResp->getJson();
                $result = (array)($wlData['result'] ?? []);
                $subject = (array)($result['subject'] ?? []);
                $vat['statusVat'] = (string)($subject['statusVat'] ?? '') ?: null;
                $vat['requestId'] = (string)($result['requestId'] ?? '') ?: null;
                $vat['requestDateTime'] = (string)($result['requestDateTime'] ?? '') ?: null;
                $vat['accountNumbers'] = array_values(array_filter((array)($subject['accountNumbers'] ?? []), function ($v) {
                    return trim((string)$v) !== '';
                }));
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'vat' => $vat,
                ]));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

public function gusLookup()
    {
        $this->request->allowMethod(['post']);

        // JSON body -> BodyParserMiddleware musi być dodany w Application::middleware()
        $nip = preg_replace('/\D+/', '', (string)$this->request->getData('nip'));

        if (!$nip || strlen($nip) !== 10) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Brak lub błędny NIP'
                ]));
        }

        try {
            $apiKey = (string)Configure::read('Gus.apiKey', '');
            if ($apiKey === '') {
                throw new \RuntimeException('Brak GUS_API_KEY (Configure.Gus.apiKey)');
            }

            // opcjonalnie: tryb dev z BIR testowym
            $isDev = (bool)Configure::read('Gus.dev', false);
            $gus = $isDev ? new GusApi($apiKey, 'dev') : new GusApi($apiKey);

            // wymagane przez lib
            $gus->login();

            // Uwaga: to ZWRACA TABLICĘ raportów
            $reports = $gus->getByNip($nip);

            if (empty($reports)) {
                // Zachowujemy styl komunikatów jak w README
                $msg = "No data found";
                // Jeśli chcesz, możesz dorzucić z biblioteki status/komunikaty:
                $msg .= sprintf(
                    " | StatusSesji:%s KomunikatKod:%s KomunikatTresc:%s",
                    (string)$gus->getSessionStatus(),
                    (string)$gus->getMessageCode(),
                    (string)$gus->getMessage()
                );
                throw new NotFoundException($msg);
            }

            // Strategia wyboru rekordu:
            // 1) bierzemy pierwszy z listy (najczęściej to jednostka główna),
            // ewentualnie możesz tu wstawić logikę wyboru po typie.
            $r = $reports[0];

            // Adres budowany jako: "ulica  numer/lokal" — gdy GUS zwrócił nr lokalu,
            // dokleja się go do street po "/". local_number zostaje pusty.
            $streetBase = trim(implode(' ', array_filter([
                (string)$r->getStreet(),
                (string)$r->getPropertyNumber(),
            ])));
            $apartmentNumberRaw = trim((string)$r->getApartmentNumber());
            $street = $streetBase;
            if ($streetBase !== '' && $apartmentNumberRaw !== '') {
                $street = $streetBase . '/' . $apartmentNumberRaw;
            }

            $contractor = [
                'name'   => trim((string)$r->getName()),
                'nip'    => $nip,
                'street' => $street,
                'local_number' => '',
                'zip'    => (string)$r->getZipCode(),
                'city'   => (string)$r->getCity(),
                'country'=> 'PL',
            ];

            // Weryfikacja statusu VAT (Biała lista MF) - best effort.
            $vat = [
                'statusVat' => null,
                'requestId' => null,
                'requestDateTime' => null,
                'accountNumbers' => [],
            ];

            try {
                $wlBase = rtrim((string)Configure::read('WlApi.baseUrl', 'https://wl-api.mf.gov.pl'), '/');
                $wlDate = date('Y-m-d');
                $client = new Client(['timeout' => 8]);
                $wlResp = $client->get($wlBase . '/api/search/nip/' . $nip, ['date' => $wlDate], [
                    'headers' => ['Accept' => 'application/json'],
                ]);

                if ($wlResp->isOk()) {
                    $wlData = (array)$wlResp->getJson();
                    $result = (array)($wlData['result'] ?? []);
                    $subject = (array)($result['subject'] ?? []);
                    $vat['statusVat'] = (string)($subject['statusVat'] ?? '') ?: null;
                    $vat['requestId'] = (string)($result['requestId'] ?? '') ?: null;
                    $vat['requestDateTime'] = (string)($result['requestDateTime'] ?? '') ?: null;
                    $vat['accountNumbers'] = array_values(array_filter((array)($subject['accountNumbers'] ?? []), function ($v) {
                        return trim((string)$v) !== '';
                    }));
                }
            } catch (\Throwable) {
                // bez blokowania GUS lookup
            }

            // (opcjonalnie) normalizacja kodu pocztowego 00-000
            if (preg_match('/^(\d{2})(\d{3})$/', $contractor['zip'], $m)) {
                $contractor['zip'] = $m[1] . '-' . $m[2];
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success'    => true,
                    'contractor' => $contractor,
                    'vat'        => $vat,
                ]));

        } catch (InvalidUserKeyException $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Nieprawidłowy klucz GUS_API_KEY',
                ]));
        } catch (NotFoundException $e) {
            // zwróćmy też komunikaty z serwera BIR, jeśli są dostępne
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Brak danych w GUS',
                ]));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
       public function search()
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $q = trim((string)$this->request->getQuery('q'));
        $limit = min(1000, max(1, (int)$this->request->getQuery('limit', 100))); // max 1000 wyników
        $all = $this->request->getQuery('all') === 'true' || $this->request->getQuery('all') === '1'; // dla katalogu
        
        $query = $this->Contractors->find()
            ->select(['id','name','altname','nip','street','city','postal_code','country','email','phone','vat_prefix','vat_eu','eori','tax_id_other','tax_id_other_country'])
            ->where([
                'company_id' => $companyId
            ])
            ->order(['name' => 'ASC'])
            ->limit($limit);

        // Only apply search filter if not requesting all contractors for catalog
        if ($q !== '' && !$all) {
            $like = "%{$q}%";
            $query->where(function (QueryExpression $exp) use ($like) {
                return $exp->or([
                    'name LIKE'    => $like,
                    'altname LIKE' => $like,
                    'nip LIKE'     => $like,
                    'email LIKE'   => $like,
                    'city LIKE'    => $like,
                    'street LIKE'  => $like,
                ]);
            });
        }

        $out = $query->all()->map(function($c){
            return [
                'id'      => $c->id,
                'label'   => $c->name . ($c->nip ? " (NIP {$c->nip})" : ''),
                'name'    => $c->name,
                'nip'     => $c->nip,
                'street'  => $c->street,
                'zip'     => $c->postal_code,
                'city'    => $c->city,
                'country' => $c->country ?: 'PL',
                'email'   => $c->email,
                'phone'   => $c->phone,
                'vat_prefix'           => $c->vat_prefix,
                'vat_eu'               => $c->vat_eu,
                'eori'                 => $c->eori,
                'tax_id_other'         => $c->tax_id_other,
                'tax_id_other_country' => $c->tax_id_other_country,
            ];
        })->toList();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($out));
    }

    public function index()
    {
        $q = trim((string)$this->request->getQuery('q'));
$active = $this->request->getQuery('active');

 $query = $this->Contractors->find()
     ->where([
         'company_id' => $this->request->getAttribute('identity')->get('company_id')
     ]);

if ($q !== '') {
    $query->where([
        'OR' => [
            'Contractors.name LIKE' => "%$q%",
            'Contractors.altname LIKE' => "%$q%",
            'Contractors.nip LIKE' => "%$q%",
            'Contractors.email LIKE' => "%$q%",
            'Contractors.city LIKE' => "%$q%",
        ]
    ]);
}
if ($active !== null && $active !== '') {
    $query->where(['Contractors.is_active' => (int)$active]);
}

$this->paginate = ['order' => ['Contractors.created' => 'DESC']];
$contractors = $this->paginate($query);

// === Saldo per kontrahent (suma 'remaining' z aktywnych faktur) ===
// Map: contractor_id => ['amount' => float, 'currency' => string]
// Liczymy tylko dla widocznej strony. Match: contractor_id LUB snapshot identyfikatorów
// (nip, vat_eu, tax_id_other) — żeby legacy/zaimportowane faktury bez contractor_id też trafiały.
$companyId = (string)$this->request->getAttribute('identity')->get('company_id');
$pageIds = [];
$contractorIdsByNip      = []; // nip => contractor_id
$contractorIdsByVatEu    = []; // vat_eu (z prefiksem i bez) => contractor_id
$contractorIdsByTaxOther = []; // tax_id_other => contractor_id
$allNips = []; $allVatEus = []; $allTaxOthers = [];
foreach ($contractors as $c) {
    $cid = (string)$c->id;
    $pageIds[] = $cid;
    if (!empty($c->nip)) {
        $nip = trim((string)$c->nip);
        $contractorIdsByNip[$nip] = $cid;
        $allNips[] = $nip;
    }
    if (!empty($c->vat_eu)) {
        $ve = trim((string)$c->vat_eu);
        $contractorIdsByVatEu[$ve] = $cid;
        $allVatEus[] = $ve;
        if (!empty($c->vat_prefix) && $c->vat_prefix !== 'NONE') {
            $full = $c->vat_prefix . $ve;
            $contractorIdsByVatEu[$full] = $cid;
            $allVatEus[] = $full;
        }
    }
    if (!empty($c->tax_id_other)) {
        $to = trim((string)$c->tax_id_other);
        $contractorIdsByTaxOther[$to] = $cid;
        $allTaxOthers[] = $to;
    }
}
$balances = [];

$applyBalance = function (string $cid, string $cur, float $amt) use (&$balances) {
    if ($cid === '' || $amt <= 0) return;
    if (!isset($balances[$cid])) {
        $balances[$cid] = ['amount' => $amt, 'currency' => $cur];
    } else {
        if ($balances[$cid]['currency'] === $cur) {
            $balances[$cid]['amount'] += $amt;
        } else {
            $balances[$cid]['multi'] = true;
        }
    }
};

if (!empty($pageIds)) {
    try {
        $Invoices = $this->fetchTable('Invoices');

        // 1) bezpośrednie matche po contractor_id
        $rows = $Invoices->find()
            ->select([
                'cid'   => 'Invoices.contractor_id',
                'cur'   => 'Invoices.currency',
                'total' => 'SUM(Invoices.remaining)',
            ])
            ->where([
                'Invoices.company_id'     => $companyId,
                'Invoices.contractor_id IN' => $pageIds,
                'Invoices.remaining >'    => 0,
                'OR' => [
                    ['Invoices.workflow_status IS' => null],
                    ['Invoices.workflow_status !=' => 'draft'],
                ],
            ])
            ->group(['Invoices.contractor_id', 'Invoices.currency'])
            ->disableHydration()
            ->all();
        $directInvoiceIds = $Invoices->find()
            ->select(['id'])
            ->where([
                'Invoices.company_id' => $companyId,
                'Invoices.contractor_id IN' => $pageIds,
                'Invoices.remaining >' => 0,
            ])
            ->disableHydration()
            ->all()
            ->extract('id')
            ->toList();
        foreach ($rows as $r) {
            $applyBalance((string)($r['cid'] ?? ''), (string)($r['cur'] ?? 'PLN'), (float)($r['total'] ?? 0));
        }

        // 2) snapshot matche — faktury bez contractor_id (lub z innym) ale ze zgodnym
        //    invoice_contractors.nip / .vat_eu / .tax_id_other
        if (!empty($allNips) || !empty($allVatEus) || !empty($allTaxOthers)) {
            $InvoiceContractors = $this->fetchTable('InvoiceContractors');
            $snapQ = $InvoiceContractors->find()
                ->innerJoinWith('Invoices')
                ->select([
                    'invoice_id' => 'Invoices.id',
                    'nip'        => 'InvoiceContractors.nip',
                    'vat_eu'     => 'InvoiceContractors.vat_eu',
                    'tax_id_other' => 'InvoiceContractors.tax_id_other',
                    'cur'        => 'Invoices.currency',
                    'rem'        => 'Invoices.remaining',
                ])
                ->where([
                    'Invoices.company_id' => $companyId,
                    'Invoices.remaining >' => 0,
                    'OR' => [
                        ['Invoices.workflow_status IS' => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ])
                ->andWhere(function ($exp) use ($allNips, $allVatEus, $allTaxOthers) {
                    $or = [];
                    if (!empty($allNips))      $or[] = ['InvoiceContractors.nip IN' => array_values(array_unique($allNips))];
                    if (!empty($allVatEus))    $or[] = ['InvoiceContractors.vat_eu IN' => array_values(array_unique($allVatEus))];
                    if (!empty($allTaxOthers)) $or[] = ['InvoiceContractors.tax_id_other IN' => array_values(array_unique($allTaxOthers))];
                    return $exp->or($or);
                })
                ->disableHydration()
                ->all();
            // Dedup po invoice_id (faktura już mogła być doliczona przez direct match)
            $directSet = array_flip(array_map('strval', $directInvoiceIds));
            foreach ($snapQ as $r) {
                $invId = (string)($r['invoice_id'] ?? '');
                if ($invId === '' || isset($directSet[$invId])) continue;
                // Znajdź kontrahenta — po pierwszym pasującym identyfikatorze
                $cid = null;
                $nip = trim((string)($r['nip'] ?? ''));
                $ve  = trim((string)($r['vat_eu'] ?? ''));
                $to  = trim((string)($r['tax_id_other'] ?? ''));
                if ($nip !== '' && isset($contractorIdsByNip[$nip])) $cid = $contractorIdsByNip[$nip];
                elseif ($ve !== '' && isset($contractorIdsByVatEu[$ve])) $cid = $contractorIdsByVatEu[$ve];
                elseif ($to !== '' && isset($contractorIdsByTaxOther[$to])) $cid = $contractorIdsByTaxOther[$to];
                if ($cid !== null) {
                    $applyBalance($cid, (string)($r['cur'] ?? 'PLN'), (float)($r['rem'] ?? 0));
                    $directSet[$invId] = true; // ochrona przed re-doliczeniem tej samej faktury
                }
            }
        }
    } catch (\Throwable) {
        $balances = [];
    }
}

$this->set(compact('contractors', 'balances'));

    }

    /**
     * View method
     *
     * @param string|null $id Contractor id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $contractor = $this->Contractors->get($id, contain: ['Companies', 'ContractorBankAccounts']);
        $this->set(compact('contractor'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $identity  = $this->getRequest()->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        
        $contractor = $this->Contractors->newEmptyEntity();
        
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['company_id'] = $companyId;
            $notificationsEnabled = !empty($data['notify_invoice_email']);
            // normalize NIP — trim whitespace, keep alphanumeric (supports foreign VAT numbers)
            if (isset($data['nip'])) {
                $data['nip'] = strtoupper(trim((string)$data['nip']));
            }
            
            // Detekcja typu kontrahenta przed sklejeniem name:
            // 1) Preferuj jawną flagę z formularza (chip-picker "Firma/Osoba" → ukryty `is_person`)
            // 2) Fallback heurystyka po polach (zgodność z API/legacy)
            $__first = trim((string)($data['first_name'] ?? ''));
            $__last  = trim((string)($data['last_name']  ?? ''));
            $__nameOriginal = trim((string)($data['name'] ?? ''));
            if (array_key_exists('is_person', $data)) {
                $data['is_person'] = (int)$data['is_person'] === 1 ? 1 : 0;
            } else {
                $data['is_person'] = (($__first !== '' || $__last !== '') && $__nameOriginal === '') ? 1 : 0;
            }

            // Osoba fizyczna: sklejamy first_name + last_name → name
            if ($__nameOriginal === '') {
                if ($__first !== '' || $__last !== '') {
                    $data['name'] = trim($__first . ' ' . $__last);
                }
            }

            // Validate required fields for AJAX requests
            if ($this->request->is('ajax')) {
                if (empty(trim((string)($data['name'] ?? '')))) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Podaj imię i nazwisko lub nazwę kontrahenta.'
                        ]));
                }
                if ($notificationsEnabled && empty(trim((string)($data['email'] ?? '')))) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Aby włączyć powiadomienie e-mail, podaj adres e-mail kontrahenta.'
                        ]));
                }
            }

            if ($notificationsEnabled && empty(trim((string)($data['email'] ?? '')))) {
                if ($this->request->is('ajax')) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Aby włączyć powiadomienie e-mail, podaj adres e-mail kontrahenta.'
                        ]));
                }
                $this->Flash->error(__('Aby włączyć powiadomienie e-mail, podaj adres e-mail kontrahenta.'));

                return $this->redirect(['action' => 'index']);
            }

            // Prevent duplicate contractor by NIP within same company
            $nip = strtoupper(trim((string)($data['nip'] ?? '')));
            if ($nip !== '') {
                $exists = $this->Contractors->exists([
                    'company_id' => $companyId,
                    'nip' => $nip,
                ]);
                if ($exists) {
                    if ($this->request->is('ajax')) {
                        return $this->response->withType('application/json')
                            ->withStringBody(json_encode([
                                'success' => false,
                                'duplicate' => true,
                                'message' => 'Kontrahent z tym NIP już istnieje w tej firmie.'
                            ]));
                    }
                    $this->Flash->error(__('Kontrahent z tym NIP już istnieje w tej firmie.'));
                    return $this->redirect(['action' => 'index']);
                }
            }
            
            $contractor = $this->Contractors->patchEntity($contractor, $data);
            
            if ($this->Contractors->save($contractor)) {
                $settingsError = $this->saveContractorNotificationSettings($companyId, (string)$contractor->id, $data);
                if ($settingsError !== null) {
                    $this->Contractors->delete($contractor);
                    if ($this->request->is('ajax')) {
                        return $this->response->withType('application/json')
                            ->withStringBody(json_encode([
                                'success' => false,
                                'message' => $settingsError,
                            ]));
                    }
                    $this->Flash->error(__($settingsError));

                    return $this->redirect(['action' => 'index']);
                }

                if ($this->request->is('ajax')) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'contractor' => [
                                'id'      => $contractor->id,
                                'label'   => $contractor->name . ($contractor->nip ? " (NIP {$contractor->nip})" : ''),
                                'name'    => $contractor->name,
                                'nip'     => $contractor->nip,
                                'street'  => $contractor->street,
                                'zip'     => $contractor->zip ?? $contractor->postal_code,
                                'city'    => $contractor->city,
                                'country' => $contractor->country ?: 'PL',
                                'email'   => $contractor->email,
                                'phone'   => $contractor->phone,
                                'vat_prefix'          => $contractor->vat_prefix,
                                'vat_eu'              => $contractor->vat_eu,
                                'eori'                => $contractor->eori,
                                'tax_id_other'        => $contractor->tax_id_other,
                                'tax_id_other_country' => $contractor->tax_id_other_country,
                            ],
                            'message' => 'Kontrahent został dodany.'
                        ]));
                }
                
                $this->Flash->success(__('The contractor has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            
            if ($this->request->is('ajax')) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Nie udało się zapisać kontrahenta.',
                        'errors'  => $contractor->getErrors(),
                    ]));
            }
            
            $this->Flash->error(__('The contractor could not be saved. Please, try again.'));
        }
        
        // For non-AJAX requests
        if (!$this->request->is('ajax')) {
            $companies = $this->Contractors->Companies->find('list', limit: 200)->all();
            $this->set(compact('contractor', 'companies'));
        }
    }
// ContractorsController.php
public function viewJson($id)
{
    $this->request->allowMethod(['get']);
    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    $c = $this->Contractors->find()
        ->where(['Contractors.id' => $id, 'Contractors.company_id' => $companyId])
        ->select(['id','name','altname','first_name','last_name','is_person','nip','pesel','email','phone','country','postal_code','city','street','local_number','correspondence_street','correspondence_postal_code','correspondence_city','correspondence_country','notes','privacy_consent','privacy_basis','is_active','vat_prefix','vat_eu','eori','tax_id_other','tax_id_other_country'])
        ->firstOrFail();

    $this->trackRecentlyViewed(
        'contractors',
        (string)$c->id,
        (string)$c->name,
        '/contractors?q=' . rawurlencode((string)$c->name),
        trim((string)($c->nip ?? '') . (($c->nip && $c->city) ? ' · ' : '') . (string)($c->city ?? '')) ?: null
    );

    return $this->response->withType('application/json')
        ->withStringBody(json_encode([
            'success' => true,
            'contractor' => $c,
        ]));
}

private function saveContractorNotificationSettings(string $companyId, string $contractorId, array $data): ?string
{
    if (empty($data['contractor_settings_enabled'])) {
        return null;
    }

    $notifyEmail = !empty($data['notify_invoice_email']);
    $email = trim((string)($data['email'] ?? ''));
    if ($notifyEmail && $email === '') {
        return 'Aby włączyć powiadomienie e-mail, podaj adres e-mail kontrahenta.';
    }

    $defaultMessage = "Dzień dobry,\n\n"
        . "informujemy, że została wystawiona faktura nr [NUMER] z dnia [DATA] na kwotę [KWOTA] [WALUTA].\n"
        . "Termin płatności: [TERMIN].\n"
        . "Forma płatności: [FORMA].\n\n"
        . "Faktura została wystawiona w Faktury24.com — bezpłatnym programie do wystawiania faktur i obsługi KSeF.";

    $message = trim((string)($data['notify_invoice_message'] ?? ''));
    if ($message === '') {
        $message = $defaultMessage;
    }

    $ContractorsSettings = $this->fetchTable('ContractorsSettings');
    $settings = $ContractorsSettings->find()
        ->where(['company_id' => $companyId, 'contractor_id' => $contractorId])
        ->first() ?? $ContractorsSettings->newEmptyEntity();

    $patch = [
        'company_id' => $companyId,
        'contractor_id' => $contractorId,
        'notify_email' => $notifyEmail,
        'notify_invoice_message' => $message,
        'attach_invoice_pdf_mode' => !empty($data['attach_invoice_pdf']) ? 'yes' : 'no',
    ];
    $settings = $ContractorsSettings->patchEntity($settings, $patch);
    if (!$ContractorsSettings->save($settings)) {
        return 'Nie udało się zapisać ustawień e-mailowych kontrahenta.';
    }

    return null;
}

    /**
     * Edit method
     *
     * @param string|null $id Contractor id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $contractor = $this->Contractors->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // Detekcja typu kontrahenta przed sklejeniem name:
            // 1) Preferuj jawną flagę z formularza (chip-picker "Firma/Osoba")
            // 2) Fallback: heurystyka po polach lub poprzedni stan w DB (legacy/API)
            $__first = trim((string)($data['first_name'] ?? ''));
            $__last  = trim((string)($data['last_name']  ?? ''));
            $__nameOriginal = trim((string)($data['name'] ?? ''));
            $__wasPerson = ((int)($contractor->is_person ?? 0) === 1);
            if (array_key_exists('is_person', $data)) {
                $data['is_person'] = (int)$data['is_person'] === 1 ? 1 : 0;
            } else {
                $data['is_person'] = (
                    (($__first !== '' || $__last !== '') && $__nameOriginal === '')
                    || ($__wasPerson && ($__first !== '' || $__last !== ''))
                ) ? 1 : 0;
            }

            // Osoba fizyczna: sklejamy first_name + last_name → name
            if ($__nameOriginal === '') {
                if ($__first !== '' || $__last !== '') {
                    $data['name'] = trim($__first . ' ' . $__last);
                }
            }

            // Guard: do not allow switching a person to company during edit
            $incomingName  = trim((string)($data['name'] ?? ''));
            $incomingFirst = trim((string)($data['first_name'] ?? ''));
            $incomingLast  = trim((string)($data['last_name'] ?? ''));
            $isTryingToBecomeCompany = ($incomingName !== '') && ($incomingFirst === '' && $incomingLast === '');
            if ((int)$contractor->is_person === 1 && $isTryingToBecomeCompany) {
                if ($this->request->is('ajax')) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Nie można zmienić osoby fizycznej na firmę. Utwórz nową firmę.',
                        ]));
                }
                $this->Flash->error(__('Nie można zmienić osoby fizycznej na firmę. Utwórz nową firmę.'));
                return $this->redirect(['action' => 'index']);
            }

            $contractor = $this->Contractors->patchEntity($contractor, $data);
            if ($this->Contractors->save($contractor)) {
                if ($this->request->is('ajax')) {
                    return $this->response->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'contractor' => [
                                'id'          => $contractor->id,
                                'name'        => (string)$contractor->name,
                                'altname'     => (string)$contractor->altname,
                                'nip'         => (string)$contractor->nip,
                                'email'       => (string)$contractor->email,
                                'phone'       => (string)$contractor->phone,
                                'city'        => (string)$contractor->city,
                                'country'     => (string)$contractor->country,
                                'street'      => (string)$contractor->street,
                                'postal_code' => (string)$contractor->postal_code,
                                'is_active'   => (int)$contractor->is_active,
                                'vat_prefix'           => (string)$contractor->vat_prefix,
                                'vat_eu'               => (string)$contractor->vat_eu,
                                'eori'                 => (string)$contractor->eori,
                                'tax_id_other'         => (string)$contractor->tax_id_other,
                                'tax_id_other_country' => (string)$contractor->tax_id_other_country,
                            ]
                        ]));
                }
                $this->Flash->success(__('The contractor has been saved.'));
                return $this->redirect(['action' => 'index']);
            }

            if ($this->request->is('ajax')) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'errors'  => $contractor->getErrors(),
                        'message' => 'Nie udało się zapisać zmian.'
                    ]));
            }

            $this->Flash->error(__('The contractor could not be saved. Please, try again.'));
        }
        $companies = $this->Contractors->Companies->find('list', limit: 200)->all();
        $this->set(compact('contractor', 'companies'));
    }


    /**
     * Delete method
     *
     * @param string|null $id Contractor id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $contractor = $this->Contractors->get($id);
        if ($this->Contractors->delete($contractor)) {
            $this->Flash->success(__('The contractor has been deleted.'));
        } else {
            $this->Flash->error(__('The contractor could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Eksport kontrahentów do pliku CSV.
     *
     * Metoda generuje plik CSV zawierający listę kontrahentów przypisanych do aktualnej firmy użytkownika.
     * Uwzględnia filtry przekazane w zapytaniu (parametry `q` – wyszukiwanie tekstowe oraz `active` – filtr aktywności).
     *
     * Format CSV jest przyjazny dla programu Microsoft Excel:
     * - Separator pól: średnik `;`
     * - Kodowanie: UTF-8 z nagłówkiem BOM
     * - Zawiera kolumny: ID, nazwa, nazwa alternatywna, NIP, adres, e-mail, telefon, status aktywności, data utworzenia.
     *
     * Przykładowe wywołanie:
     * ```
     * GET /contractors/export?q=Jan&active=1
     * ```
     * Zwraca gotowy do pobrania plik `contractors_YYYY-MM-DD_HH-mm.csv`.
     *
     * @return \Cake\Http\Response Plik CSV gotowy do pobrania przez przeglądarkę.
     * @throws \Cake\Http\Exception\MethodNotAllowedException Jeśli metoda HTTP jest inna niż GET.
     */
public function export()
{
    $this->request->allowMethod(['get']);

    // Bezpiecznik
    @set_time_limit(120);

    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    $q      = trim((string)$this->request->getQuery('q'));
    $active = $this->request->getQuery('active');

    $query = $this->Contractors->find()
        ->select([
            'id','name','altname','nip','street','postal_code','city',
            'country','email','phone','is_active','created'
        ])
        ->where(['company_id' => $companyId])
        ->order(['name' => 'ASC']);

    // Eksport zaznaczonych — gdy podano ids[]=..., ignoruj filtry q/active
    $ids = (array)$this->request->getQuery('ids');
    $ids = array_values(array_filter(array_map('strval', $ids), fn($v) => $v !== ''));
    if (!empty($ids)) {
        $query->where(['Contractors.id IN' => $ids]);
        // Pomiń pozostałe filtry — user zaznaczył konkretne wpisy
        $this->set('exportContext', 'selected');
        $q = '';
        $active = null;
    }

    if ($q !== '') {
        $like = '%' . str_replace(['%', '_'], ['\%','\_'], $q) . '%';
        $query->where([
            'OR' => [
                'Contractors.name LIKE'    => $like,
                'Contractors.altname LIKE' => $like,
                'Contractors.nip LIKE'     => $like,
                'Contractors.email LIKE'   => $like,
                'Contractors.city LIKE'    => $like,
            ]
        ]);
    }
    if ($active !== null && $active !== '') {
        $query->where(['Contractors.is_active' => (int)$active]);
    }

    // Helper: hardening dla Excela (ochrona przed =, +, -, @ na początku)
    $safe = static function (?string $v): string {
        $s = (string)$v;
        if ($s === '') return '';
        return preg_match('/^[=\+\-@]/u', $s) ? "'".$s : $s;
    };

    // CSV w pamięci (php://temp trzyma w RAM do pewnego progu, potem dysk)
    $fh = fopen('php://temp', 'r+');

    // BOM UTF-8 dla Excela
    fwrite($fh, "\xEF\xBB\xBF");

    // Nagłówki kolumn
    fputcsv($fh, [
        'ID','Nazwa','Nazwa alternatywna','NIP','Ulica',
        'Kod pocztowy','Miasto','Kraj','E-mail','Telefon',
        'Aktywny','Utworzono'
    ], ';');

    // Dane
    foreach ($query as $c) {
        fputcsv($fh, [
            $c->id,
            $safe($c->name),
            $safe($c->altname),
            $safe($c->nip),
            $safe($c->street),
            $safe($c->postal_code),
            $safe($c->city),
            $safe($c->country ?: 'PL'),
            $safe($c->email),
            $safe($c->phone),
            $c->is_active ? 1 : 0,
            $c->created?->i18nFormat('yyyy-MM-dd HH:mm'),
        ], ';');
    }

    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    $filename = 'contractors_' . (new \DateTime('now', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d_H-i') . '.csv';

    // Uwaga: withStringBody -> Cake doda Content-Length (idealne dla paska postępu)
    return $this->response
        ->withType('csv')
        ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->withDownload($filename)
        ->withStringBody($csv);
}

    /**
     * Pobiera listę kontrahentów ze starego systemu faktury24.com dla NIP bieżącej firmy.
     * Działa jako PHP proxy (unikamy CORS w przeglądarce).
     */
    public function importFetch()
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        // Pobierz NIP bieżącej firmy
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
            $client = new Client();
            $resp = $client->get(
                'https://archiwum.faktury24.com/ajax/get_contractors_by_nip',
                ['nip' => $nip],
                ['timeout' => 15]
            );
            $body = $resp->getStringBody();
            $json = json_decode($body, true);
            if (!is_array($json) || ($json['status'] ?? 0) !== 200) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error'   => 'Serwer archiwum.faktury24.com nie zwrócił wyników (status: ' . ($json['status'] ?? '?') . ').',

                    ]));
            }

            $payload = $json['payload'] ?? [];

            // Oznacz które NIP-y już istnieją lokalnie
            $existingNips = [];
            if (!empty($payload)) {
                $remoteNips = array_map(
                    fn($r) => preg_replace('/\D+/', '', (string)($r['bd_tin'] ?? '')),
                    $payload
                );
                $remoteNips = array_filter($remoteNips);
                if (!empty($remoteNips)) {
                    $found = $this->Contractors->find()
                        ->select(['nip'])
                        ->where(['company_id' => $companyId, 'nip IN' => array_values($remoteNips)])
                        ->all();
                    foreach ($found as $c) {
                        $existingNips[$c->nip] = true;
                    }
                }
            }

            $rows = [];
            foreach ($payload as $r) {
                $nipClean = preg_replace('/\D+/', '', (string)($r['bd_tin'] ?? ''));
                $rows[] = [
                    'bu_uuid'      => (string)($r['bu_uuid'] ?? ''),
                    'nip'          => (string)($r['bd_tin'] ?? ''),
                    'nip_clean'    => $nipClean,
                    'name'         => strtok((string)($r['bd_fna'] ?? ''), "\n"),
                    'street'       => (string)($r['bd_street'] ?? ''),
                    'postal_code'  => (string)($r['bd_postal_code'] ?? ''),
                    'city'         => (string)($r['bd_city'] ?? ''),
                    'email'        => (string)($r['bd_email'] ?? ''),
                    'already_imported' => isset($existingNips[$nipClean]),
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
     * Importuje wskazane (przez bu_uuid) rekordy z danych przekazanych w POST.
     * Oczekuje: { "rows": [ { bu_uuid, nip_clean, name, street, postal_code, city, email }, … ] }
     */
    public function importBatch()
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $rows = (array)($this->request->getData('rows') ?? []);
        if (empty($rows)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak danych do importu.']));
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $r) {
            $nipClean = preg_replace('/\D+/', '', (string)($r['nip_clean'] ?? $r['nip'] ?? ''));
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'Pominięto rekord bez nazwy (NIP: ' . ($r['nip'] ?? '?') . ')';
                $skipped++;
                continue;
            }

            // Sprawdź duplikat po NIP
            if ($nipClean !== '' && $this->Contractors->exists(['company_id' => $companyId, 'nip' => $nipClean])) {
                $skipped++;
                continue;
            }

            $entity = $this->Contractors->newEntity([
                'company_id'  => $companyId,
                'name'        => $name,
                'nip'         => $nipClean !== '' ? $nipClean : null,
                'street'      => (string)($r['street'] ?? ''),
                'postal_code' => (string)($r['postal_code'] ?? ''),
                'city'        => (string)($r['city'] ?? ''),
                'email'       => (string)($r['email'] ?? ''),
                'country'     => 'PL',
                'is_active'   => true,
            ]);

            if ($this->Contractors->save($entity)) {
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

public function invoices($contractorId)
{
    $this->request->allowMethod(['get']);

    // Powiedz Cake’owi, że chcemy JSON:
    $this->viewBuilder()->setClassName(JsonView::class);
    $this->viewBuilder()->setOption('serialize', ['success','invoices','message']);

    $companyId = $this->request->getAttribute('identity')?->get('company_id');

    // Pobierz kontrahenta — potrzebujemy też wszystkich identyfikatorów do fallback-match po snapshocie
    $contractor = $this->Contractors->find()
        ->select(['id', 'nip', 'vat_prefix', 'vat_eu', 'tax_id_other', 'tax_id_other_country'])
        ->where(['id' => $contractorId, 'company_id' => $companyId])
        ->first();
    if (!$contractor) {
        $this->set(['success'=>false, 'message'=>'Not found', 'invoices'=>[]]);
        return;
    }
    $contractorNip      = trim((string)($contractor->nip ?? ''));
    $contractorVatEu    = trim((string)($contractor->vat_eu ?? ''));
    $contractorVatPfx   = trim((string)($contractor->vat_prefix ?? ''));
    $contractorTaxOther = trim((string)($contractor->tax_id_other ?? ''));

    $onlyUnsettled = (int)$this->request->getQuery('unsettled') === 1;

    $Invoices = $this->fetchTable('Invoices');
    $q = $Invoices->find()
        ->select([
            'id','hash','company_id','parent_id','type','correction_type',
            'simplified_invoice','paymentmethod','paymentdate','paymentstate',
            'date','total','netto','tax','alreadypaid','remaining','fullnumber',
            'currency','currency_date','currency_exchange','description',
            'is_print','is_sent','is_api','workflow_status','created','modified'
        ])
        // company_id ZAWSZE filtruje (multitenancy — nie pokazujemy faktur innych firm!)
        ->where(['Invoices.company_id' => $companyId]);

    // Match: contractor_id LUB snapshot (invoice_contractors.nip / .vat_eu / .tax_id_other)
    // Faktury legacy/zaimportowane mogą nie mieć contractor_id, ale mają snapshot identyfikatorów.
    $hasSnapshotIds = $contractorNip !== '' || $contractorVatEu !== '' || $contractorTaxOther !== '';
    if ($hasSnapshotIds) {
        $q->leftJoinWith('InvoiceContractors')
          ->andWhere(function ($exp) use ($contractorId, $contractorNip, $contractorVatEu, $contractorVatPfx, $contractorTaxOther) {
              $or = ['Invoices.contractor_id' => (string)$contractorId];
              if ($contractorNip !== '') {
                  $or[] = ['InvoiceContractors.nip' => $contractorNip];
              }
              if ($contractorVatEu !== '') {
                  // Match po samym numerze VAT-UE, oraz po pełnym (prefix+number) gdy oba ustawione
                  $or[] = ['InvoiceContractors.vat_eu' => $contractorVatEu];
                  if ($contractorVatPfx !== '' && $contractorVatPfx !== 'NONE') {
                      $or[] = ['InvoiceContractors.vat_eu' => $contractorVatPfx . $contractorVatEu];
                  }
              }
              if ($contractorTaxOther !== '') {
                  $or[] = ['InvoiceContractors.tax_id_other' => $contractorTaxOther];
              }
              return $exp->or($or);
          })
          ->group(['Invoices.id']);
    } else {
        $q->andWhere(['Invoices.contractor_id' => (string)$contractorId]);
    }
    $q->orderDesc('Invoices.date');

    if ($onlyUnsettled) {
        $q->andWhere([
            'OR' => [
                'Invoices.remaining >' => 0,
                ['Invoices.paymentstate IS NOT' => null, 'Invoices.paymentstate <>' => 'paid'],
            ]
        ]);
    }

    $invoices = $q->limit(200)->all();

    $this->set([
        'success'  => true,
        'invoices' => $invoices,
        'message'  => null,
    ]);
}

/**
 * Statystyki sprzedaży per kontrahent.
 * GET /contractors/stats/{id}
 * Zwraca: agregaty kwot, średni czas płatności, faktury YTD, najpopularniejsze produkty.
 */
public function stats(string $contractorId)
{
    $this->request->allowMethod(['get']);
    $this->viewBuilder()->setClassName(JsonView::class);
    $this->viewBuilder()->setOption('serialize', ['success','stats','message']);

    $identity  = $this->request->getAttribute('identity');
    $companyId = (string)($identity?->get('company_id') ?? '');
    if ($companyId === '') {
        $this->set(['success'=>false, 'stats'=>null, 'message'=>'Brak kontekstu firmy.']);
        return;
    }

    $contractor = $this->Contractors->find()
        ->select(['id', 'nip', 'name'])
        ->where(['id' => $contractorId, 'company_id' => $companyId])
        ->first();
    if (!$contractor) {
        $this->set(['success'=>false, 'stats'=>null, 'message'=>'Nie znaleziono kontrahenta.']);
        return;
    }

    $Invoices = $this->fetchTable('Invoices');
    $nip = trim((string)($contractor->nip ?? ''));

    // Subselect WHERE — match po contractor_id LUB po snapshot NIP (jak w invoices() action)
    $baseWhere = function ($q) use ($companyId, $contractorId, $nip) {
        $q->where(['Invoices.company_id' => $companyId]);
        if ($nip !== '') {
            $q->leftJoinWith('InvoiceContractors')
              ->andWhere(function ($exp) use ($contractorId, $nip) {
                  return $exp->or([
                      'Invoices.contractor_id'  => $contractorId,
                      'InvoiceContractors.nip'  => $nip,
                  ]);
              });
        } else {
            $q->andWhere(['Invoices.contractor_id' => $contractorId]);
        }
        // Ignoruj szkice
        $q->andWhere([
            'OR' => [
                ['Invoices.workflow_status IS' => null],
                ['Invoices.workflow_status !=' => 'draft'],
            ],
        ]);
        return $q;
    };

    // Agregaty kwotowe (PLN — uproszczenie, multi-currency w tooltip)
    $aggQ = $Invoices->find();
    $baseWhere($aggQ);
    $agg = $aggQ
        ->select([
            'count'    => $aggQ->func()->count('DISTINCT Invoices.id'),
            'total'    => 'SUM(Invoices.total)',
            'paid'     => 'SUM(Invoices.alreadypaid)',
            'remaining'=> 'SUM(Invoices.remaining)',
        ])
        ->disableHydration()
        ->first();

    // Faktury w bieżącym roku
    $yearStart = (new \DateTime())->modify('first day of January this year')->format('Y-m-d');
    $ytdQ = $Invoices->find();
    $baseWhere($ytdQ);
    $ytdQ->andWhere(['Invoices.date >=' => $yearStart]);
    $ytd = $ytdQ
        ->select([
            'count' => $ytdQ->func()->count('DISTINCT Invoices.id'),
            'total' => 'SUM(Invoices.total)',
        ])
        ->disableHydration()
        ->first();

    // Średni czas płatności (dni między datą faktury a datą zapłaty, dla opłaconych)
    $avgDays = null;
    try {
        $paidQ = $Invoices->find();
        $baseWhere($paidQ);
        $paidQ->andWhere([
            'Invoices.paymentdate IS NOT' => null,
            'Invoices.date IS NOT' => null,
            'Invoices.remaining <=' => 0,
        ]);
        $delays = $paidQ
            ->select(['date' => 'Invoices.date', 'paid' => 'Invoices.paymentdate'])
            ->disableHydration()
            ->limit(500)
            ->all();
        $sum = 0; $n = 0;
        foreach ($delays as $r) {
            try {
                $d1 = new \DateTime((string)$r['date']);
                $d2 = new \DateTime((string)$r['paid']);
                $diff = (int)$d1->diff($d2)->format('%r%a');
                if ($diff >= 0 && $diff < 365) { $sum += $diff; $n++; }
            } catch (\Throwable) {}
        }
        $avgDays = $n > 0 ? round($sum / $n, 1) : null;
    } catch (\Throwable) { $avgDays = null; }

    // Po miesiącach (12 ostatnich miesięcy)
    $byMonth = [];
    try {
        $monthlyQ = $Invoices->find();
        $baseWhere($monthlyQ);
        $monthsAgo = (new \DateTime())->modify('-11 months')->modify('first day of this month')->format('Y-m-d');
        $monthlyQ->andWhere(['Invoices.date >=' => $monthsAgo]);
        $rows = $monthlyQ
            ->select([
                'mo' => 'DATE_FORMAT(Invoices.date, "%Y-%m")',
                'total' => 'SUM(Invoices.total)',
                'cnt' => $monthlyQ->func()->count('DISTINCT Invoices.id'),
            ])
            ->group(['mo'])
            ->order(['mo' => 'ASC'])
            ->disableHydration()
            ->all();
        foreach ($rows as $r) {
            $byMonth[] = [
                'month' => (string)$r['mo'],
                'total' => (float)$r['total'],
                'count' => (int)$r['cnt'],
            ];
        }
    } catch (\Throwable) {}

    // Top 3 produkty (z invoice_contents na fakturach kontrahenta)
    $topProducts = [];
    try {
        $InvoiceContents = $this->fetchTable('InvoiceContents');
        $tpQ = $InvoiceContents->find();
        $tpQ
            ->innerJoinWith('Invoices')
            ->select([
                'name' => 'InvoiceContents.name',
                'qty'  => 'SUM(InvoiceContents.quantity)',
                'rev'  => 'SUM(InvoiceContents.quantity * InvoiceContents.price)',
            ])
            ->where(['Invoices.company_id' => $companyId])
            ->group(['InvoiceContents.name'])
            ->order(['rev' => 'DESC'])
            ->limit(3);
        if ($nip !== '') {
            $tpQ->leftJoinWith('Invoices.InvoiceContractors')
                ->andWhere(function ($exp) use ($contractorId, $nip) {
                    return $exp->or([
                        'Invoices.contractor_id'  => $contractorId,
                        'InvoiceContractors.nip'  => $nip,
                    ]);
                });
        } else {
            $tpQ->andWhere(['Invoices.contractor_id' => $contractorId]);
        }
        $rows = $tpQ->disableHydration()->all();
        foreach ($rows as $r) {
            $topProducts[] = [
                'name' => (string)$r['name'],
                'qty'  => (float)$r['qty'],
                'revenue' => (float)$r['rev'],
            ];
        }
    } catch (\Throwable) {}

    $stats = [
        'totalInvoices' => (int)($agg['count'] ?? 0),
        'totalAmount'   => (float)($agg['total'] ?? 0),
        'totalPaid'     => (float)($agg['paid'] ?? 0),
        'totalRemaining'=> (float)($agg['remaining'] ?? 0),
        'ytdCount'      => (int)($ytd['count'] ?? 0),
        'ytdAmount'     => (float)($ytd['total'] ?? 0),
        'avgPaymentDays'=> $avgDays,
        'byMonth'       => $byMonth,
        'topProducts'   => $topProducts,
    ];

    $this->set(['success'=>true, 'stats'=>$stats, 'message'=>null]);
}

/**
 * Weryfikacja numeru VAT-UE w bazie VIES (Komisja Europejska).
 * POST /contractors/vies-check { prefix: 'DE', number: '123456789' }
 * Zwraca: { success, valid, name, address, source: 'vies' }
 */
public function viesCheck()
{
    $this->request->allowMethod(['post']);
    $this->viewBuilder()->setClassName(JsonView::class);
    $this->viewBuilder()->setOption('serialize', ['success','valid','name','address','message']);

    $prefix = strtoupper(preg_replace('/[^A-Z]/i', '', (string)$this->request->getData('prefix')));
    $number = preg_replace('/[^0-9A-Z]/i', '', strtoupper((string)$this->request->getData('number')));
    if ($prefix === '' || $number === '') {
        $this->set(['success'=>false, 'valid'=>false, 'message'=>'Podaj prefiks i numer VAT-UE.']);
        return;
    }

    // Lista 27 krajów członkowskich UE (VIES nie obsługuje innych)
    $eu = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','EL','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
    // VIES używa 'EL' dla Grecji (nie 'GR')
    if ($prefix === 'GR') $prefix = 'EL';
    if (!in_array($prefix, $eu, true)) {
        $this->set(['success'=>false, 'valid'=>false, 'message'=>'Prefiks „'.$prefix.'" nie należy do UE.']);
        return;
    }

    $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">'
        . '<soapenv:Body><urn:checkVat>'
        . '<urn:countryCode>' . htmlspecialchars($prefix, ENT_XML1) . '</urn:countryCode>'
        . '<urn:vatNumber>'   . htmlspecialchars($number, ENT_XML1) . '</urn:vatNumber>'
        . '</urn:checkVat></soapenv:Body></soapenv:Envelope>';

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"\"\r\n",
            'content' => $envelope,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $url = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService';
    $resp = @file_get_contents($url, false, $ctx);

    if ($resp === false) {
        $this->set(['success'=>false, 'valid'=>false, 'message'=>'Serwis VIES jest chwilowo niedostępny — spróbuj ponownie później.']);
        return;
    }

    // Parser regex — VIES używa prefiksu namespace (np. <ns2:valid>true</ns2:valid>).
    // Dopuszczamy opcjonalny prefiks `[\w-]+:` przed nazwą elementu.

    // SOAP fault (np. MS_UNAVAILABLE, GLOBAL_MAX_CONCURRENT_REQ, TIMEOUT, SERVICE_UNAVAILABLE)
    if (preg_match('#<(?:[\w-]+:)?Fault>.*?<faultstring>(.*?)</faultstring>#is', $resp, $m)) {
        $fault = trim($m[1]);
        $this->set([
            'success' => false,
            'valid'   => false,
            'message' => 'VIES odpowiedział błędem: ' . $fault . ' — spróbuj ponownie za chwilę.',
        ]);
        return;
    }

    $hasValidElement = preg_match('#<(?:[\w-]+:)?valid>\s*(true|false)\s*</(?:[\w-]+:)?valid>#i', $resp, $vMatch);
    if (!$hasValidElement) {
        // Niespodziewany format odpowiedzi — log do debugowania
        $this->set([
            'success' => false,
            'valid'   => false,
            'message' => 'Nieoczekiwana odpowiedź z VIES — sprawdź połączenie lub spróbuj ponownie.',
        ]);
        return;
    }
    $valid = strtolower($vMatch[1]) === 'true';

    $name = '';
    $addr = '';
    if (preg_match('#<(?:[\w-]+:)?name>(.*?)</(?:[\w-]+:)?name>#s', $resp, $m)) {
        $name = trim(html_entity_decode($m[1], ENT_QUOTES|ENT_XML1, 'UTF-8'));
    }
    if (preg_match('#<(?:[\w-]+:)?address>(.*?)</(?:[\w-]+:)?address>#s', $resp, $m)) {
        $addr = trim(html_entity_decode($m[1], ENT_QUOTES|ENT_XML1, 'UTF-8'));
    }

    $this->set([
        'success' => true,
        'valid'   => $valid,
        'name'    => $name !== '' && $name !== '---' ? $name : null,
        'address' => $addr !== '' && $addr !== '---' ? $addr : null,
        'message' => $valid ? 'Numer aktywny w VIES.' : 'Numer niezarejestrowany w VIES.',
    ]);
}

/**
 * Bulk update kontrahentów — toggle is_active.
 * POST /contractors/bulk-set-active { ids: [...uuid], active: 0|1 }
 * Zwraca JSON { success, updated, message }
 */
public function bulkSetActive()
{
    $this->request->allowMethod(['post']);
    $this->viewBuilder()->setClassName(JsonView::class);
    $this->viewBuilder()->setOption('serialize', ['success','updated','message']);

    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');
    if (!$companyId) {
        $this->set(['success'=>false, 'updated'=>0, 'message'=>'Brak kontekstu firmy.']);
        return;
    }

    $data = $this->request->getData();
    $ids = (array)($data['ids'] ?? []);
    $ids = array_values(array_filter(array_map('strval', $ids), fn($v) => $v !== ''));
    $active = (int)($data['active'] ?? 0) === 1 ? 1 : 0;

    if (empty($ids)) {
        $this->set(['success'=>false, 'updated'=>0, 'message'=>'Nie wybrano kontrahentów.']);
        return;
    }

    // UPDATE WHERE company_id = X AND id IN (...) — multitenancy safe
    $updated = $this->Contractors->updateAll(
        ['is_active' => $active],
        ['company_id' => $companyId, 'id IN' => $ids]
    );

    $this->set([
        'success' => true,
        'updated' => (int)$updated,
        'message' => null,
    ]);
}


}

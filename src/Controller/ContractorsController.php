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

            $apartmentNumberRaw = trim((string)$r->getApartmentNumber());
            $localNumber = $apartmentNumberRaw;
            if ($apartmentNumberRaw !== '' && preg_match('/^(\d+)/', $apartmentNumberRaw, $m)) {
                $localNumber = (string)$m[1];
            }

            $contractor = [
                'name'   => trim((string)$r->getName()),
                'nip'    => $nip,
                'street' => trim(implode(' ', array_filter([
                    (string)$r->getStreet(),
                    (string)$r->getPropertyNumber(),
                ]))),
                'local_number' => $localNumber,
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
$this->set(compact('contractors'));

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
            
            // Osoba fizyczna: sklejamy first_name + last_name → name
            if (empty(trim((string)($data['name'] ?? '')))) {
                $first = trim((string)($data['first_name'] ?? ''));
                $last  = trim((string)($data['last_name']  ?? ''));
                if ($first !== '' || $last !== '') {
                    $data['name'] = trim($first . ' ' . $last);
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
        ->select(['id','name','altname','nip','pesel','email','phone','country','postal_code','city','street','local_number','correspondence_street','correspondence_postal_code','correspondence_city','correspondence_country','notes','privacy_consent','privacy_basis','is_active','vat_prefix','vat_eu','eori','tax_id_other','tax_id_other_country'])
        ->firstOrFail();

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

            // Osoba fizyczna: sklejamy first_name + last_name → name
            if (empty(trim((string)($data['name'] ?? '')))) {
                $first = trim((string)($data['first_name'] ?? ''));
                $last  = trim((string)($data['last_name']  ?? ''));
                if ($first !== '' || $last !== '') {
                    $data['name'] = trim($first . ' ' . $last);
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

    /**
     * Pobiera kontrahentów z systemu Speed (API paginowane).
     * Wymaga SPEED_API_URL i SPEED_API_TOKEN w zmiennych środowiskowych.
     * GET /contractors/import-speed-fetch?page=1
     */
    public function importSpeedFetch(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $apiUrl   = rtrim((string)(getenv('SPEED_API_URL') ?: Configure::read('Speed.apiUrl') ?: ''), '/');
        $apiToken = (string)(getenv('SPEED_API_TOKEN') ?: Configure::read('Speed.apiToken') ?: '');

        if ($apiUrl === '') {
            $this->jsonError('Brak konfiguracji SPEED_API_URL. Skontaktuj się z administratorem.');
            return;
        }

        $page  = max(1, (int)$this->request->getQuery('page', 1));
        $limit = 100; // pobieramy po 100 rekordów

        try {
            $client = new Client();
            $headers = $apiToken !== '' ? ['Authorization' => 'Bearer ' . $apiToken] : [];
            $resp = $client->get(
                $apiUrl . '/kontrahenci',
                ['page' => $page, 'limit' => $limit],
                ['headers' => $headers, 'timeout' => 20]
            );

            if ($resp->getStatusCode() !== 200) {
                $this->jsonError('Speed API zwróciło HTTP ' . $resp->getStatusCode() . '. Sprawdź konfigurację.');
                return;
            }

            $json = $resp->getJson();
            if (!is_array($json) || !isset($json['data'])) {
                $this->jsonError('Nieoczekiwana odpowiedź z Speed API (brak klucza "data").');
                return;
            }

            $payload    = (array)$json['data'];
            $total      = (int)($json['total'] ?? 0);
            $totalPages = (int)($json['totalPages'] ?? 1);

            // Oznacz NIP-y, które już istnieją lokalnie
            $existingNips = [];
            $remoteNips = array_filter(array_map(
                fn($r) => preg_replace('/\D+/', '', trim((string)($r['KON_NIP'] ?? ''))),
                $payload
            ));
            if (!empty($remoteNips)) {
                $found = $this->Contractors->find()
                    ->select(['nip'])
                    ->where(['company_id' => $companyId, 'nip IN' => array_values($remoteNips)])
                    ->all();
                foreach ($found as $c) {
                    $existingNips[$c->nip] = true;
                }
            }

            $rows = [];
            foreach ($payload as $r) {
                $nipRaw   = trim((string)($r['KON_NIP'] ?? ''));
                $nipClean = preg_replace('/\D+/', '', $nipRaw);

                // Adres: ulica + numer domu
                $ulica  = trim((string)($r['KON_ULICA'] ?? ''));
                $nrDom  = trim((string)($r['KON_NRDOM'] ?? ''));
                $street = trim($ulica . ($nrDom !== '' ? ' ' . $nrDom : ''));

                // Kraj: Speed przechowuje 2-znakowy kod lub spacje → domyślnie PL
                $krajRaw = trim((string)($r['KON_KRAJ'] ?? ''));
                $country = (strlen($krajRaw) === 2 && ctype_alpha($krajRaw)) ? strtoupper($krajRaw) : 'PL';

                // Pola niemapowalne → JSON w notes
                $extra = [];
                $unmapped = [
                    'speed_id'         => $r['KON_ID'] ?? null,
                    'konto'            => trim((string)($r['KON_KONTO'] ?? '')),
                    'ean'              => trim((string)($r['KON_EAN'] ?? '')),
                    'bank'             => trim((string)($r['KON_BANK'] ?? '')),
                    'bank_adres'       => trim((string)($r['KON_BANK_ADRES'] ?? '')),
                    'numer_rachunku'   => trim((string)($r['KON_NUMER'] ?? '')),
                    'waluta'           => trim((string)($r['KON_WALUTA'] ?? '')),
                    'limit_kredytowy'  => $r['KON_LIMIT'] ?? null,
                    'upust'            => $r['KON_UPUST'] ?? null,
                    'fax'              => trim((string)($r['KON_FAX'] ?? '')),
                    'www'              => trim((string)($r['KON_WWW'] ?? '')),
                    'pesel'            => trim((string)($r['KON_PESEL'] ?? '')),
                    'krs'              => trim((string)($r['KON_KRS'] ?? '')),
                    'wojewodztwo'      => trim((string)($r['KON_WOJ'] ?? '')),
                    'powiat'           => trim((string)($r['KON_POWIAT'] ?? '')),
                    'gmina'            => trim((string)($r['KON_GMINA'] ?? '')),
                    'poczta'           => trim((string)($r['KON_POCZTA'] ?? '')),
                    'uwagi'            => trim((string)($r['KON_UWAGI'] ?? '')),
                    'prog'             => trim((string)($r['KON_PROG'] ?? '')),
                    'status'           => trim((string)($r['KON_STATUS'] ?? '')),
                    'osoba_kontakt'    => trim((string)($r['KON_OSOBA'] ?? '')),
                    'przedstawiciel'   => trim((string)($r['KON_PRZEDSTAW'] ?? '')),
                ];
                foreach ($unmapped as $k => $v) {
                    if ($v !== null && $v !== '' && $v !== 0 && $v !== 0.0) {
                        $extra[$k] = $v;
                    }
                }

                $rows[] = [
                    'speed_id'         => (int)($r['KON_ID'] ?? 0),
                    'nip'              => $nipRaw,
                    'nip_clean'        => $nipClean,
                    'name'             => trim((string)($r['KON_NAZWA1'] ?? '')),
                    'altname'          => trim((string)($r['KON_SKROT'] ?? '')),
                    'regon'            => trim((string)($r['KON_REGON'] ?? '')),
                    'country'          => $country,
                    'postal_code'      => trim((string)($r['KON_KOD'] ?? '')),
                    'city'             => trim((string)($r['KON_MIEJSC'] ?? '')),
                    'street'           => $street,
                    'local_number'     => trim((string)($r['KON_NRLOKAL'] ?? '')),
                    'phone'            => trim((string)($r['KON_TEL'] ?? '')),
                    'email'            => trim((string)($r['KON_EMAIL'] ?? '')),
                    'extra_json'       => empty($extra) ? null : $extra,
                    'already_imported' => isset($existingNips[$nipClean]),
                ];
            }

            $this->response = $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success'    => true,
                    'page'       => $page,
                    'totalPages' => $totalPages,
                    'total'      => $total,
                    'count'      => count($rows),
                    'rows'       => $rows,
                ]));
        } catch (\Throwable $e) {
            $this->jsonError('Błąd połączenia z Speed API: ' . $e->getMessage());
        }
    }

    /**
     * Importuje zaznaczonych kontrahentów z Speed do lokalnej bazy.
     * POST /contractors/import-speed-batch
     * Body: { "rows": [ { nip_clean, name, altname, regon, country, postal_code, city, street, local_number, phone, email, extra_json } ] }
     */
    public function importSpeedBatch(): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $rows = (array)($this->request->getData('rows') ?? []);
        if (empty($rows)) {
            $this->response = $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => 'Brak danych do importu.']));
            return;
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $r) {
            $nipClean = preg_replace('/\D+/', '', (string)($r['nip_clean'] ?? $r['nip'] ?? ''));
            $name     = trim((string)($r['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'Pominięto rekord bez nazwy (NIP: ' . ($r['nip'] ?? '?') . ')';
                $skipped++;
                continue;
            }

            // Duplikat po NIP
            if ($nipClean !== '' && $this->Contractors->exists(['company_id' => $companyId, 'nip' => $nipClean])) {
                $skipped++;
                continue;
            }

            // Pola niemapowalne → notes jako JSON
            $notesData = null;
            if (!empty($r['extra_json']) && is_array($r['extra_json'])) {
                $notesData = json_encode($r['extra_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $entity = $this->Contractors->newEntity([
                'company_id'   => $companyId,
                'name'         => $name,
                'altname'      => (string)($r['altname'] ?? '') ?: null,
                'nip'          => $nipClean !== '' ? $nipClean : null,
                'regon'        => (string)($r['regon'] ?? '') ?: null,
                'country'      => (string)($r['country'] ?? 'PL') ?: 'PL',
                'postal_code'  => (string)($r['postal_code'] ?? '') ?: null,
                'city'         => (string)($r['city'] ?? '') ?: null,
                'street'       => (string)($r['street'] ?? '') ?: null,
                'local_number' => (string)($r['local_number'] ?? '') ?: null,
                'phone'        => (string)($r['phone'] ?? '') ?: null,
                'email'        => (string)($r['email'] ?? '') ?: null,
                'notes'        => $notesData,
                'is_active'    => true,
            ]);

            if ($this->Contractors->save($entity)) {
                $imported++;
            } else {
                $errors[] = 'Nie udało się zapisać: ' . $name . ' (' . implode(', ', array_map(
                    fn($errs) => implode(', ', $errs),
                    $entity->getErrors()
                )) . ')';
                $skipped++;
            }
        }

        $this->response = $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success'  => true,
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ]));
    }

    /** Helper: zwraca JSON z błędem */
    private function jsonError(string $message): void
    {
        $this->response = $this->response->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'error' => $message]));
    }

public function invoices($contractorId)
{
    $this->request->allowMethod(['get']);

    // Powiedz Cake’owi, że chcemy JSON:
    $this->viewBuilder()->setClassName(JsonView::class);
    $this->viewBuilder()->setOption('serialize', ['success','invoices','message']);

    $companyId = $this->request->getAttribute('identity')?->get('company_id');

    $exists = $this->Contractors->exists(['id' => $contractorId, 'company_id' => $companyId]);
    if (!$exists) {
        $this->set(['success'=>false, 'message'=>'Not found', 'invoices'=>[]]);
        return;
    }

    $onlyUnsettled = (int)$this->request->getQuery('unsettled') === 1;

    $Invoices = $this->fetchTable('Invoices');
    $q = $Invoices->find()
        ->select([
            'id','hash','company_id','parent_id','type','correction_type',
            'simplified_invoice','paymentmethod','paymentdate','paymentstate',
            'date','total','netto','tax','alreadypaid','remaining','fullnumber',
            'currency','currency_date','currency_exchange','description',
            'is_print','is_sent','is_api','created','modified'
        ])
        ->where([
            'Invoices.company_id' => $companyId,
            'Invoices.contractor_id' => (int)$contractorId,
        ])
        ->orderDesc('Invoices.date');

    if ($onlyUnsettled) {
        $q->where([
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


}

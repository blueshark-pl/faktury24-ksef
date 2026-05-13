<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Log\Log;

/**
 * Companies Controller
 *
 * @property \App\Model\Table\CompaniesTable $Companies
 */
class CompaniesController extends AppController
{

    public function nipExists()
    {
        $this->request->allowMethod(['get', 'post']);

        $nip = preg_replace('/\D+/', '', (string)($this->request->getData('nip') ?: $this->request->getQuery('nip')));
        if ($nip === '' || strlen($nip) !== 10) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'exists' => false,
                    'message' => null,
                ]));
        }

        /** @var \App\Model\Table\CompaniesTable $Companies */
        $Companies = $this->fetchTable('Companies');

        $existing = $Companies->find()
            ->select(['id', 'name'])
            ->where(function ($exp, $q) use ($nip) {
                return $exp->eq(
                    $q->newExpr("REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '')"),
                    $nip
                );
            })
            ->first();

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'exists' => (bool)$existing,
                'message' => $existing ? 'Firma z podanym NIP już istnieje w systemie.' : null,
            ]));
    }

    public function onboarding()
    {
        $this->request->allowMethod(['get']);

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            // jeśli nie zalogowany — przekieruj na login
            return $this->redirect([
                'plugin' => 'CakeDC/Users',
                'controller' => 'Users',
                'action' => 'login',
            ]);
        }

        // Jeśli identity nie jest odświeżone, a w DB user ma już firmę, nie pokazuj onboardingu.
        try {
            /** @var \App\Model\Table\UsersTable $Users */
            $Users = $this->fetchTable('Users');
            $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id']]);
            if (!empty($dbUser->company_id)) {
                try {
                    if (!$this->components()->has('Authentication')) {
                        $this->loadComponent('Authentication.Authentication');
                    }
                    $this->Authentication->setIdentity($dbUser);
                } catch (\Throwable) {
                    // best-effort
                }
                return $this->redirect(['action' => 'edit', (string)$dbUser->company_id]);
            }
        } catch (\Throwable) {
            // best-effort
        }

        // jeśli user ma już firmę — nie pokazuj onboardingu
        if (!empty($identity->get('company_id'))) {
            return $this->redirect(['action' => 'edit', (string)$identity->get('company_id')]);
        }

        /** @var \Cake\ORM\Table $Companies */
        $Companies = $this->fetchTable('Companies');
        /** @var \Cake\ORM\Table $CompanyBankAccounts */
        $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');

        $company = $Companies->newEmptyEntity();
        $bankAccount = $CompanyBankAccounts->newEmptyEntity([
            'currency'   => 'PLN',
            'is_default' => true,
        ]);

        // Prefill from registration (additional_data.onboarding_prefill), if available.
        try {
            $Users = $this->fetchTable('Users');
            $user = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'additional_data']]);
            $additionalData = (array)($user->additional_data ?? []);
            $prefill = (array)($additionalData['onboarding_prefill'] ?? []);

            if (!empty($prefill)) {
                $map = [
                    'name' => 'name',
                    'nip' => 'nip',
                    'street' => 'street',
                    'local_number' => 'local_number',
                    'postal_code' => 'postal_code',
                    'city' => 'city',
                    'country' => 'country',
                ];

                foreach ($map as $src => $dst) {
                    $val = trim((string)($prefill[$src] ?? ''));
                    if ($val !== '') {
                        $company->set($dst, $val);
                    }
                }
            }
        } catch (\Throwable) {
            // best-effort
        }

        $this->set(compact('company', 'bankAccount'));
    }
    /**
     * POST /companies/save-onboarding
     */
   public function saveOnboarding()
{
    $this->request->allowMethod(['post']);

    $identity = $this->request->getAttribute('identity');
    if (!$identity) {
        throw new \Cake\Http\Exception\UnauthorizedException('Musisz być zalogowany.');
    }

    /** @var \App\Model\Table\CompaniesTable $Companies */
    $Companies = $this->fetchTable('Companies');
    /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
    $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');
    /** @var \App\Model\Table\UsersTable $Users */
    $Users = $this->fetchTable('Users');

    // jeśli user ma już firmę (nawet jeśli identity nie jest odświeżone) — nie pozwól wykonać onboardingu drugi raz
    $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id']]);
    if (!empty($dbUser->company_id)) {
        $this->Flash->info('Masz już przypisaną firmę. Zmień dane w edycji firmy.');
        return $this->redirect(['action' => 'edit', (string)$dbUser->company_id]);
    }

    $companyData = (array)($this->request->getData('company') ?? []);
    if (empty($companyData['profile_mode'])) {
        $companyData['profile_mode'] = 'business';
    }

    $normalizedNip = preg_replace('/\D+/', '', (string)($companyData['nip'] ?? ''));
    if (strlen($normalizedNip) === 10) {
        $existingCompanyByNip = $Companies->find()
            ->select(['id'])
            ->where(function ($exp, $q) use ($normalizedNip) {
                return $exp->eq(
                    $q->newExpr("REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '')"),
                    $normalizedNip
                );
            })
            ->first();
        if ($existingCompanyByNip) {
            $this->Flash->error('Firma z podanym NIP już istnieje w systemie.');
            $company = $Companies->newEntity($companyData);
            $bankAccount = $CompanyBankAccounts->newEmptyEntity([
                'currency' => 'PLN',
                'is_default' => true,
            ]);
            $this->set(compact('company', 'bankAccount'));

            return $this->render('onboarding');
        }
    }

    $banksInput  = (array)($this->request->getData('banks') ?? []);
    $defaultIdx  = (int)($this->request->getData('banks_default') ?? 0);

    // helper: IBAN bez spacji, upper
    $normalizeIban = static function (?string $v): string {
        return strtoupper(preg_replace('/\s+/', '', (string)$v));
    };
    $normalizeImportedIban = static function (?string $v) use ($normalizeIban): string {
        $iban = $normalizeIban($v);
        if ($iban !== '' && preg_match('/^\d{26}$/', $iban)) {
            $iban = 'PL' . $iban;
        }

        return $iban;
    };

    // Jeśli użytkownik nie podał ręcznie rachunków, spróbuj użyć rachunków z prefilla (MF WL z rejestracji).
    $hasManualBankInput = false;
    foreach ($banksInput as $row) {
        if ($normalizeIban((string)($row['iban'] ?? '')) !== '') {
            $hasManualBankInput = true;

            break;
        }
    }

    if (!$hasManualBankInput) {
        try {
            $userForPrefill = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'additional_data']]);
            $additionalData = (array)($userForPrefill->get('additional_data') ?? []);
            $prefill = (array)($additionalData['onboarding_prefill'] ?? []);
            $prefillBankAccounts = (array)($prefill['bank_accounts'] ?? []);

            $autofillBanks = [];
            foreach ($prefillBankAccounts as $rawAccount) {
                $iban = $normalizeImportedIban((string)$rawAccount);
                if ($iban === '') {
                    continue;
                }
                if (isset($autofillBanks[$iban])) {
                    continue;
                }
                $autofillBanks[$iban] = [
                    'iban' => $iban,
                    'currency' => 'PLN',
                    'bank_name' => null,
                    'label' => null,
                ];
            }

            if (!empty($autofillBanks)) {
                $banksInput = array_values($autofillBanks);
                $defaultIdx = 0;
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    // 1) Zbuduj encję firmy (onboarding tworzy nową firmę)
    $company = $Companies->newEmptyEntity();
    $company = $Companies->patchEntity($company, $companyData);

    $conn = $Companies->getConnection();

        try {
            $self = $this; // capture controller instance safely
            $conn->transactional(function () use (
                $Companies,
                $CompanyBankAccounts,
                $Users,
                $self,
                &$company,
                $banksInput,
                $defaultIdx,
                $normalizeIban,
                $identity
            ): void {
            // 1) Firma
            if (!$Companies->save($company)) {
                $firstError = current(current($company->getErrors() ?: [['__all__' => ['Nie udało się zapisać firmy.']]]));
                throw new \RuntimeException(is_array($firstError) ? (string)current($firstError) : (string)$firstError);
            }

            // 2) Rachunki bankowe (wiele, opcjonalnie)
            //    - filtrujemy puste
            //    - normalizujemy IBAN
            //    - ustawiamy is_default zgodnie z $defaultIdx (jeśli cokolwiek jest)
            $toSave = [];
            $seenIbans = [];

            foreach ($banksInput as $idx => $row) {
                $iban = $normalizeIban($row['iban'] ?? '');
                if ($iban === '') {
                    continue; // pomiń puste wiersze
                }
                // unikaj duplikatów w jednym submitcie
                if (isset($seenIbans[$iban])) {
                    throw new \RuntimeException('Duplikat rachunku IBAN w formularzu: ' . $iban);
                }
                $seenIbans[$iban] = true;

                $entity = $CompanyBankAccounts->newEntity([
                    'company_id' => $company->id,
                    'iban'       => $iban,
                    'bank_name'  => $row['bank_name'] ?? null,
                    'currency'   => strtoupper((string)($row['currency'] ?? 'PLN')),
                    'label'      => $row['label'] ?? null,
                    'is_default' => false, // ustawimy niżej
                ]);
                $toSave[$idx] = $entity;
            }

            if (!empty($toSave)) {
                // ustaw domyślny – jeśli defaultIdx poza zakresem, ustaw pierwszy
                if (!array_key_exists($defaultIdx, $toSave)) {
                    $firstKey = array_key_first($toSave);
                    $defaultIdx = $firstKey ?? 0;
                }
                foreach ($toSave as $idx => $ent) {
                    $ent->set('is_default', $idx === $defaultIdx);
                }

                // Zapisz każdy (chcemy precyzyjne błędy; saveMany też ok, ale trudniej wyjąć błąd konkretnej encji)
                foreach ($toSave as $idx => $ent) {
                    if (!$CompanyBankAccounts->save($ent)) {
                        $firstError = current(current($ent->getErrors() ?: [['__all__' => ['Nie udało się zapisać rachunku bankowego.']]]));
                        $fieldInfo  = ' (wiersz #' . ($idx + 1) . ')';
                        throw new \RuntimeException((is_array($firstError) ? (string)current($firstError) : (string)$firstError) . $fieldInfo);
                    }
                }
            }

            // 3) Przypnij usera do firmy (tylko jeśli jeszcze nie ma)
            $user = $Users->get($identity->getIdentifier());
            if (empty($user->company_id)) {
                $user->set('company_id', $company->id);
                $additionalData = (array)($user->get('additional_data') ?? []);
                if (isset($additionalData['onboarding_prefill'])) {
                    unset($additionalData['onboarding_prefill']);
                    $user->set('additional_data', $additionalData);
                }
                if (!$Users->save($user)) {
                    $firstError = current(current($user->getErrors() ?: [['__all__' => ['Nie udało się przypisać użytkownika do firmy.']]]));
                    throw new \RuntimeException(is_array($firstError) ? (string)current($firstError) : (string)$firstError);
                }
            }

            // 4) Skopiuj domyślne (systemowe) serie faktur dla nowej firmy
            /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
            $InvoiceSeries = $self->fetchTable('InvoiceSeries');
            $copied = $InvoiceSeries->copySystemSeriesForCompany($company->id);
            if ($copied === 0) {
                // brak systemowych – nie traktuj jako błąd, tylko info
                \Cake\Log\Log::info('Brak systemowych serii do skopiowania dla nowej firmy '.$company->id, ['onboarding']);
            }
        });

        // 4) Odśwież identity (żeby company_id było od razu w sesji)
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        try {
            if (!$this->components()->has('Authentication')) {
                $this->loadComponent('Authentication.Authentication');
            }
            $this->Authentication->setIdentity($user);
        } catch (\Throwable) {
            // Jeśli nie uda się odświeżyć sesji (brak middleware/service), onboarding i tak jest zapisany w DB.
            // W razie potrzeby user może odświeżyć stronę / zalogować się ponownie.
        }

        $this->Flash->success('Dane firmy zapisane. Domyślne serie zostały skonfigurowane. Witamy w Faktury24!');
        return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
    } catch (\Throwable $e) {
        $this->Flash->error('Ups! ' . $e->getMessage());

        // Przy błędzie wróć do formularza z tym, co już wpisał użytkownik
        $bankAccount = $this->fetchTable('CompanyBankAccounts')->newEmptyEntity();
        $this->set(compact('company', 'bankAccount'));
        return $this->render('onboarding');
    }
}

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Companies->find();
        $companies = $this->paginate($query);

        $this->set(compact('companies'));
    }

    /**
     * View method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $company = $this->Companies->get($id, contain: ['CompanyBankAccounts', 'Contractors', 'InvoiceSeries', 'Invoices', 'Services', 'Users']);
        $this->set(compact('company'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $company = $this->Companies->newEmptyEntity();
        if ($this->request->is('post')) {
            $company = $this->Companies->patchEntity($company, $this->request->getData());
            if ($this->Companies->save($company)) {
                $this->Flash->success(__('The company has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The company could not be saved. Please, try again.'));
        }
        $this->set(compact('company'));
    }

    /**
     * GET /firma/serie/sprawdz?series_id=UUID
     * Sprawdza stan numeracji dla danej serii — zwraca count faktur w bieżącym okresie,
     * ostatni użyty numer oraz sugerowany numer początkowy.
     */
    public function checkSeriesStart(): \Cake\Http\Response
    {
        $this->request->allowMethod('get');
        $this->disableAutoRender();

        $ctxCompanyId = (string)($this->request->getSession()->read('Auth.company_id') ?? '');
        $seriesId = trim((string)($this->request->getQuery('series_id') ?? ''));

        if ($ctxCompanyId === '' || $seriesId === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak parametrów.']));
        }

        try {
            /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
            $InvoiceSeries = $this->fetchTable('InvoiceSeries');
            $series = $InvoiceSeries->find()
                ->where(['InvoiceSeries.id' => $seriesId, 'InvoiceSeries.company_id' => $ctxCompanyId])
                ->contain(['InvoiceSeriesPeriods'])
                ->first();

            if (!$series) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Seria nie znaleziona.']));
            }

            /** @var \App\Model\Table\InvoicesTable $InvoicesTbl */
            $InvoicesTbl = $this->fetchTable('Invoices');

            $now = new \DateTimeImmutable();
            $year  = (int)$now->format('Y');
            $month = (int)$now->format('m');

            $periodName = (string)($series->invoice_series_period->name ?? '');

            $where = [
                'company_id'        => $ctxCompanyId,
                'invoice_series_id' => $seriesId,
                'fullnumber IS NOT' => null,
                'workflow_status !=' => 'draft',
            ];

            if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
                $where['year']  = $year;
                $where['month'] = $month;
                $periodLabel = $month . '/' . $year;
            } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
                $where['year'] = $year;
                $periodLabel = (string)$year;
            } else {
                $periodLabel = 'wszystkie okresy';
            }

            $lastInvoice = $InvoicesTbl->find()
                ->where($where)
                ->order(['number' => 'DESC', 'id' => 'DESC'])
                ->first();

            $count = $InvoicesTbl->find()->where($where)->count();

            if ($lastInvoice) {
                $maxNumber = !empty($lastInvoice->number)
                    ? (int)$lastInvoice->number
                    : (new \App\Service\Invoice\InvoiceNumberingService())
                        ->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
                $suggested = $maxNumber + 1;
            } else {
                $maxNumber   = 0;
                $suggested   = (int)($series->starting_number ?: 1);
            }

            return $this->response->withType('application/json')->withStringBody(json_encode([
                'count'       => $count,
                'max_number'  => $maxNumber,
                'suggested'   => $suggested,
                'period'      => $periodLabel,
                'series_name' => (string)($series->name ?? ''),
            ]));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => $e->getMessage()]));
        }
    }

    /**
     * Edit method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        // wymuś kontekst firmy zalogowanego użytkownika
        $ctxCompanyId = (string)($this->currentCompanyId ?? '');
        if ($ctxCompanyId === '') {
            $this->Flash->error('Brak powiązania użytkownika z firmą.');
            return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
        }
        // jeśli w URL jest inne id niż kontekst, przekieruj na właściwe
        if ($id !== null && (string)$id !== $ctxCompanyId) {
            return $this->redirect(['action' => 'edit', $ctxCompanyId]);
        }

        // Załaduj firmę wyłącznie po ctxCompanyId, razem z relacjami potrzebnymi w edycji
        $company = $this->Companies->find()
            ->where(['Companies.id' => $ctxCompanyId])
            ->contain(['CompanyBankAccounts', 'CompanyRegisters'])
            ->firstOrFail();
        // Dostosowanie widoku (przeniesiony z onboardingu) do edycji:
        // zapewnij kontekst rachunku bankowego i ewentualnej listy rachunków
        /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
        $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');
        $bankAccount = $CompanyBankAccounts->newEmptyEntity([
            'currency'   => 'PLN',
            'is_default' => false,
        ]);
        $existingAccounts = $CompanyBankAccounts->find()
            ->where(['company_id' => $ctxCompanyId])
            ->orderAsc('is_default')
            ->orderAsc('created')
            ->all();

        /** @var \App\Model\Table\CompanyRegistersTable $CompanyRegisters */
        $CompanyRegisters = $this->fetchTable('CompanyRegisters');
        $existingRegisters = $CompanyRegisters->find()
            ->where(['company_id' => $ctxCompanyId])
            ->orderAsc('created')
            ->all()
            ->toList();

        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
        $invoiceSeriesRows = $InvoiceSeries->find()
            ->where(['company_id' => $ctxCompanyId])
            ->orderDesc('is_default')
            ->orderAsc('name')
            ->all()
            ->toList();

        Log::info('Companies.edit: initial invoice series count=' . count($invoiceSeriesRows) . ' for company=' . $ctxCompanyId, ['series_debug']);

        if (count($invoiceSeriesRows) === 0) {
            try {
                $copied = $InvoiceSeries->copySystemSeriesForCompany((string)$ctxCompanyId);
                Log::info('Companies.edit: copySystemSeriesForCompany copied=' . (int)$copied . ' for company=' . $ctxCompanyId, ['series_debug']);
                $invoiceSeriesRows = $InvoiceSeries->find()
                    ->where(['company_id' => $ctxCompanyId])
                    ->orderDesc('is_default')
                    ->orderAsc('name')
                    ->all()
                    ->toList();
                Log::info('Companies.edit: post-copy invoice series count=' . count($invoiceSeriesRows) . ' for company=' . $ctxCompanyId, ['series_debug']);
            } catch (\Throwable) {
                Log::error('Companies.edit: copySystemSeriesForCompany failed for company=' . $ctxCompanyId, ['series_debug']);
                // best-effort; view still renders without rows
            }
        }

        $copiedSeriesCount = 0;
        foreach ($invoiceSeriesRows as $seriesRow) {
            if (!empty($seriesRow->parent_id)) {
                $copiedSeriesCount++;
            }
        }

        // Zlicz faktury (nie-draft) per typ dokumentu w bieżącym roku — do ostrzeżenia przy zmianie numeru początkowego
        $currentYear = (int)date('Y');
        $invoiceCountsByType = [];
        try {
            $InvoicesTbl = $this->fetchTable('Invoices');
            $rows = $InvoicesTbl->find()
                ->select(['type', 'cnt' => $InvoicesTbl->query()->func()->count('*')])
                ->where([
                    'company_id'        => $ctxCompanyId,
                    'workflow_status !=' => 'draft',
                    'issue_date >='     => $currentYear . '-01-01',
                    'issue_date <'      => ($currentYear + 1) . '-01-01',
                ])
                ->groupBy('type')
                ->all();
            foreach ($rows as $row) {
                $invoiceCountsByType[(string)$row->type] = (int)$row->cnt;
            }
        } catch (\Throwable) {}

        // Sprawdź czy firma wysłała kiedykolwiek fakturę do KSeF — jeśli tak, nie można wyłączyć trybu KSeF
        $hasKsefSentInvoice = false;
        try {
            $InvoicesTbl = $InvoicesTbl ?? $this->fetchTable('Invoices');
            $hasKsefSentInvoice = $InvoicesTbl->exists([
                'company_id'        => $ctxCompanyId,
                'OR' => [
                    ['workflow_status' => 'sent'],
                    ['ksef_number IS NOT' => null],
                ],
            ]);
        } catch (\Throwable) {}

        $invoiceSeriesTypeOptions = [];
        try {
            $InvoiceSeriesTypes = $this->fetchTable('InvoiceSeriesTypes');
            $invoiceSeriesTypeOptions = $InvoiceSeriesTypes->find('list', keyField: 'id', valueField: 'name')->toArray();
        } catch (\Throwable) {
            $invoiceSeriesTypeOptions = [];
        }

        $invoiceSeriesPeriodOptions = [];
        try {
            $InvoiceSeriesPeriods = $this->fetchTable('InvoiceSeriesPeriods');
            $invoiceSeriesPeriodOptions = $InvoiceSeriesPeriods->find('list', keyField: 'id', valueField: 'name')->toArray();
        } catch (\Throwable) {
            $invoiceSeriesPeriodOptions = [];
        }

        $invoiceTypeOptions = [
            'vat' => 'Faktura VAT',
            'novat' => 'Rachunek',
            'currency' => 'Faktura walutowa',
            'margin' => 'Faktura marża',
            'proforma' => 'Faktura proforma',
            'credit_note' => 'Nota uznaniowa',
            'advance' => 'Faktura zaliczkowa',
            'correction' => 'Faktura korygująca',
            'internal' => 'Dowód wewnętrzny',
            'internal_evidence' => 'Dowód wewnętrzny (ewidencja)',
            'oss' => 'Faktura OSS',
            'rental' => 'Faktura najmu prywatnego',
        ];

        if ($this->request->is(['patch', 'post', 'put']) && $this->request->is('ajax')) {
            $section = trim((string)($this->request->getData('_section') ?? 'all'));
            $csrf    = (string)($this->request->getCookie('csrfToken') ?? '');
            $normalizeIban = static function (?string $v): string {
                return strtoupper(preg_replace('/\s+/', '', (string)$v));
            };

            $sections = ($section === 'all')
                ? ['dane_firmy', 'banki', 'serie', 'rejestry']
                : [$section];

            $results = [];

            foreach ($sections as $sec) {
                try {
                    $conn = $this->Companies->getConnection();
                    $conn->transactional(function () use ($sec, $ctxCompanyId, $company, $normalizeIban, $invoiceTypeOptions) {
                        /** @var \App\Model\Table\CompaniesTable $Companies */
                        $Companies = $this->fetchTable('Companies');
                        /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
                        $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');
                        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                        /** @var \App\Model\Table\CompanyRegistersTable $CompanyRegisters */
                        $CompanyRegisters = $this->fetchTable('CompanyRegisters');

                        if ($sec === 'dane_firmy') {
                            $dataCompany = (array)$this->request->getData();
                            if (empty($dataCompany['profile_mode'])) $dataCompany['profile_mode'] = 'business';
                            // checkbox — gdy odznaczony, przeglądarka nie wysyła wartości
                            if (!isset($dataCompany['rental_enabled'])) $dataCompany['rental_enabled'] = 0;
                            $dataCompany['id'] = $ctxCompanyId;
                            $ent = $Companies->patchEntity($company, $dataCompany);
                            if (!$Companies->save($ent)) {
                                $errs = $ent->getErrors();
                                $first = '';
                                foreach ($errs as $field => $msgs) {
                                    foreach ((array)$msgs as $msg) { $first = $field . ': ' . (is_array($msg) ? implode(', ', $msg) : $msg); break; }
                                    if ($first) break;
                                }
                                throw new \RuntimeException($first ?: 'Nie udało się zapisać danych firmy.');
                            }
                        }

                        if ($sec === 'banki') {
                            $banksInput = (array)($this->request->getData('banks') ?? []);
                            $defaultIdx = (int)($this->request->getData('banks_default') ?? 0);
                            $toSave = [];
                            $seenIbans = [];
                            foreach ($banksInput as $idx => $row) {
                                $iban = $normalizeIban($row['iban'] ?? '');
                                if ($iban === '') continue;
                                if (isset($seenIbans[$iban])) throw new \RuntimeException('Duplikat rachunku IBAN: ' . $iban);
                                $seenIbans[$iban] = true;
                                $ent = $CompanyBankAccounts->newEmptyEntity();
                                $ent = $CompanyBankAccounts->patchEntity($ent, [
                                    'company_id'       => $ctxCompanyId, 'iban' => $iban,
                                    'bank_name'        => $row['bank_name'] ?? null,
                                    'currency'         => strtoupper((string)($row['currency'] ?? 'PLN')),
                                    'label'            => $row['label'] ?? null, 'is_default' => false,
                                    'swift'            => !empty($row['swift']) ? strtoupper(trim((string)$row['swift'])) : null,
                                    'bank_desc'        => !empty($row['bank_desc']) ? trim((string)$row['bank_desc']) : null,
                                    'bank_correspondent' => !empty($row['bank_correspondent']) ? trim((string)$row['bank_correspondent']) : null,
                                ]);
                                $toSave[$idx] = $ent;
                            }
                            if (!empty($toSave)) {
                                if (!array_key_exists($defaultIdx, $toSave)) $defaultIdx = array_key_first($toSave);
                                foreach ($toSave as $idx => $ent) $ent->set('is_default', $idx === $defaultIdx);
                                $CompanyBankAccounts->deleteAll(['company_id' => $ctxCompanyId]);
                                foreach ($toSave as $ent) {
                                    if (!$CompanyBankAccounts->save($ent)) throw new \RuntimeException('Nie udało się zapisać rachunku bankowego.');
                                }
                            }
                        }

                        if ($sec === 'serie') {
                            $invoiceSeriesInput = (array)($this->request->getData('invoice_series_rows') ?? []);
                            // invoice_series_default_key is now an array [type => rowKey] (one per doc type)
                            $invoiceSeriesDefaultKeys = (array)($this->request->getData('invoice_series_default_key') ?? []);
                            $existingSeries = $InvoiceSeries->find()->where(['company_id' => $ctxCompanyId])->all()->indexBy('id')->toArray();

                            // usuwanie
                            foreach ($invoiceSeriesInput as $row) {
                                $row = (array)$row;
                                $rowId = trim((string)($row['id'] ?? ''));
                                if (!empty($row['_delete']) && $rowId !== '') {
                                    if (!isset($existingSeries[$rowId])) continue;
                                    $toDelete = $existingSeries[$rowId];
                                    if (!empty($toDelete->parent_id) || (int)($toDelete->is_blocked ?? 0) === 1)
                                        throw new \RuntimeException('Nie można usunąć serii systemowej: ' . (string)$toDelete->name);
                                    if (!$InvoiceSeries->delete($toDelete)) throw new \RuntimeException('Nie udało się usunąć serii: ' . (string)$toDelete->name);
                                    unset($existingSeries[$rowId]);
                                }
                            }

                            // zapis
                            $savedSeriesByKey = [];
                            foreach ($invoiceSeriesInput as $key => $row) {
                                $row = (array)$row;
                                if (!empty($row['_delete'])) continue;
                                $rowId    = trim((string)($row['id'] ?? ''));
                                $name     = trim((string)($row['name'] ?? ''));
                                $template = trim((string)($row['series_template'] ?? ''));
                                $startingNumber = max(1, (int)($row['starting_number'] ?? 1));
                                $overrideNextRaw = trim((string)($row['override_next_number'] ?? ''));
                                $overrideNext = $overrideNextRaw !== '' && (int)$overrideNextRaw > 0 ? (int)$overrideNextRaw : null;
                                $type = trim((string)($row['type'] ?? 'vat'));
                                if ($name === '' && $template === '') continue;
                                if ($name === '') throw new \RuntimeException('Nazwa serii jest wymagana.');
                                if ($template === '') throw new \RuntimeException('Wzorzec numeracji jest wymagany dla serii: ' . $name);
                                if (!array_key_exists($type, $invoiceTypeOptions)) $type = 'vat';
                                $payload = [
                                    'company_id' => $ctxCompanyId, 'name' => $name,
                                    'series_template' => $template, 'starting_number' => $startingNumber,
                                    'override_next_number' => $overrideNext,
                                    'type' => $type,
                                    'invoice_series_type_id'   => trim((string)($row['invoice_series_type_id']   ?? '')) ?: null,
                                    'invoice_series_period_id' => trim((string)($row['invoice_series_period_id'] ?? '')) ?: null,
                                    'is_system' => 0, 'is_default' => false,
                                ];
                                if ($rowId !== '' && isset($existingSeries[$rowId])) {
                                    $seriesEntity = $existingSeries[$rowId];
                                    if (!empty($seriesEntity->parent_id) || (int)($seriesEntity->is_blocked ?? 0) === 1) {
                                        // seria systemowa — bezpośredni update starting_number i override_next_number
                                        $InvoiceSeries->updateAll(['starting_number' => $startingNumber, 'override_next_number' => $overrideNext], ['id' => $rowId]);
                                        $savedSeriesByKey[(string)$key] = ['id' => (string)$seriesEntity->id, 'type' => (string)($seriesEntity->type ?? 'vat')];
                                        continue;
                                    }
                                    $seriesEntity = $InvoiceSeries->patchEntity($seriesEntity, $payload);
                                } else {
                                    $seriesEntity = $InvoiceSeries->newEntity($payload);
                                }
                                if (!$InvoiceSeries->save($seriesEntity)) {
                                    $firstErr = current(current($seriesEntity->getErrors() ?: [['__all__' => ['Nie udało się zapisać serii.']]]));
                                    throw new \RuntimeException(is_array($firstErr) ? (string)current($firstErr) : (string)$firstErr);
                                }
                                $savedSeriesByKey[(string)$key] = ['id' => (string)$seriesEntity->id, 'type' => (string)($seriesEntity->type ?? 'vat')];
                            }

                            // ustaw domyślną per typ dokumentu
                            // $invoiceSeriesDefaultKeys = [docType => rowKey] — jeden per typ
                            if (!empty($savedSeriesByKey) && !empty($invoiceSeriesDefaultKeys)) {
                                foreach ($invoiceSeriesDefaultKeys as $docType => $rowKey) {
                                    $rowKey = (string)$rowKey;
                                    if (!isset($savedSeriesByKey[$rowKey])) continue;
                                    $selectedSeries = $savedSeriesByKey[$rowKey];
                                    if (empty($selectedSeries['id']) || empty($selectedSeries['type'])) continue;
                                    $InvoiceSeries->updateAll(['is_default' => 0], ['company_id' => $ctxCompanyId, 'type' => $selectedSeries['type']]);
                                    $InvoiceSeries->updateAll(['is_default' => 1], ['company_id' => $ctxCompanyId, 'id' => $selectedSeries['id']]);
                                }
                            }
                        }

                        if ($sec === 'rejestry') {
                            $registersInput = (array)($this->request->getData('company_registers') ?? []);
                            $CompanyRegisters->deleteAll(['company_id' => $ctxCompanyId]);
                            foreach ($registersInput as $idx => $row) {
                                $row = (array)$row;
                                $rName  = trim((string)($row['name'] ?? ''));
                                $rKrs   = trim((string)($row['krs'] ?? ''));
                                $rRegon = trim((string)($row['regon'] ?? ''));
                                $rBdo   = trim((string)($row['bdo'] ?? ''));
                                if ($rName === '' && $rKrs === '' && $rRegon === '' && $rBdo === '') continue;
                                $regEntity = $CompanyRegisters->newEntity([
                                    'company_id' => $ctxCompanyId,
                                    'name'       => $rName !== '' ? $rName : null,
                                    'krs'        => $rKrs !== '' ? $rKrs : null,
                                    'regon'      => $rRegon !== '' ? $rRegon : null,
                                    'bdo'        => $rBdo !== '' ? $rBdo : null,
                                ]);
                                if (!$CompanyRegisters->save($regEntity)) throw new \RuntimeException('Nie udało się zapisać rejestru: ' . ($rName ?: '#' . ($idx + 1)));
                            }
                        }
                    });
                    $results[$sec] = ['success' => true, 'message' => 'Zapisano.'];
                } catch (\Throwable $e) {
                    $results[$sec] = ['success' => false, 'message' => $e->getMessage()];
                }
            }

            $allOk = array_reduce($results, fn($c, $r) => $c && $r['success'], true);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => $allOk, 'sections' => $results]));
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            // patchuj bez namespacu: pola formularza są płaskie (name, street, itd.)
            $dataCompany = (array)$this->request->getData();
            if (empty($dataCompany['profile_mode'])) {
                $dataCompany['profile_mode'] = 'business';
            }
            if (!isset($dataCompany['rental_enabled'])) $dataCompany['rental_enabled'] = 0;
            $dataCompany['id'] = $ctxCompanyId; // wymuś właściwe ID
            $company = $this->Companies->patchEntity($company, $dataCompany);

            // Banki z formularza (podobnie jak w onboardingu)
            $banksInput = (array)($this->request->getData('banks') ?? []);
            $defaultIdx = (int)($this->request->getData('banks_default') ?? 0);
            $invoiceSeriesInput = (array)($this->request->getData('invoice_series_rows') ?? []);
            $registersInput = (array)($this->request->getData('company_registers') ?? []);
            $invoiceSeriesDefaultKey = (string)($this->request->getData('invoice_series_default_key') ?? '');
            $invoiceSeriesDeleteIds = [];
            foreach ($invoiceSeriesInput as $seriesRow) {
                $seriesRow = (array)$seriesRow;
                $rowId = trim((string)($seriesRow['id'] ?? ''));
                $isDelete = !empty($seriesRow['_delete']);
                if ($isDelete && $rowId !== '') {
                    $invoiceSeriesDeleteIds[] = $rowId;
                }
            }

            $normalizeIban = static function (?string $v): string {
                return strtoupper(preg_replace('/\s+/', '', (string)$v));
            };

            $conn = $this->Companies->getConnection();
            try {
                $conn->transactional(function () use (
                    $ctxCompanyId,
                    $company,
                    $banksInput,
                    $defaultIdx,
                    $normalizeIban,
                    $invoiceSeriesInput,
                    $invoiceSeriesDefaultKey,
                    $invoiceSeriesDeleteIds,
                    $invoiceTypeOptions,
                    $registersInput
                ) {
                    /** @var \App\Model\Table\CompaniesTable $Companies */
                    $Companies = $this->fetchTable('Companies');
                    /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
                    $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');
                    /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                    $InvoiceSeries = $this->fetchTable('InvoiceSeries');

                    // 1) Zapisz dane firmy
                    if (!$Companies->saveOrFail($company)) {
                        throw new \RuntimeException('Nie udało się zapisać danych firmy.');
                    }

                    // 2) Zbuduj listę rachunków do zapisu (pomijaj puste IBAN)
                    $toSave = [];
                    $seenIbans = [];
                    foreach ($banksInput as $idx => $row) {
                        $iban = $normalizeIban($row['iban'] ?? '');
                        if ($iban === '') { continue; }
                        if (isset($seenIbans[$iban])) {
                            throw new \RuntimeException('Duplikat rachunku IBAN w formularzu: ' . $iban);
                        }
                        $seenIbans[$iban] = true;

                        $ent = $CompanyBankAccounts->newEmptyEntity();
                        $ent = $CompanyBankAccounts->patchEntity($ent, [
                            'company_id' => $ctxCompanyId,
                            'iban'       => $iban,
                            'bank_name'  => $row['bank_name'] ?? null,
                            'currency'   => strtoupper((string)($row['currency'] ?? 'PLN')),
                            'label'      => $row['label'] ?? null,
                            'is_default' => false,
                        ]);
                        $toSave[$idx] = $ent;
                    }

                    // 3) Jeśli coś podano — nadpisz listę rachunków (replace)
                    if (!empty($toSave)) {
                        // Ustal domyślny indeks
                        if (!array_key_exists($defaultIdx, $toSave)) {
                            $firstKey = array_key_first($toSave);
                            $defaultIdx = $firstKey ?? 0;
                        }
                        foreach ($toSave as $idx => $ent) {
                            $ent->set('is_default', $idx === $defaultIdx);
                        }

                        // Usuń dotychczasowe rachunki i wstaw nowe
                        $CompanyBankAccounts->deleteAll(['company_id' => $ctxCompanyId]);
                        foreach ($toSave as $idx => $ent) {
                            if (!$CompanyBankAccounts->save($ent)) {
                                $firstError = current(current($ent->getErrors() ?: [['__all__' => ['Nie udało się zapisać rachunku bankowego.']]]));
                                $fieldInfo  = ' (wiersz #' . ($idx + 1) . ')';
                                throw new \RuntimeException((is_array($firstError) ? (string)current($firstError) : (string)$firstError) . $fieldInfo);
                            }
                        }
                    }

                    // 3a) Rejestry firmy (replace strategy — usuń stare, wstaw nowe)
                    /** @var \App\Model\Table\CompanyRegistersTable $CompanyRegisters */
                    $CompanyRegisters = $this->fetchTable('CompanyRegisters');
                    $CompanyRegisters->deleteAll(['company_id' => $ctxCompanyId]);
                    foreach ($registersInput as $idx => $regRow) {
                        $regRow = (array)$regRow;
                        $rName  = trim((string)($regRow['name'] ?? ''));
                        $rKrs   = trim((string)($regRow['krs'] ?? ''));
                        $rRegon = trim((string)($regRow['regon'] ?? ''));
                        $rBdo   = trim((string)($regRow['bdo'] ?? ''));

                        // pomijaj całkowicie puste wiersze
                        if ($rName === '' && $rKrs === '' && $rRegon === '' && $rBdo === '') {
                            continue;
                        }

                        $regEntity = $CompanyRegisters->newEntity([
                            'company_id' => $ctxCompanyId,
                            'name'       => $rName !== '' ? $rName : null,
                            'krs'        => $rKrs !== '' ? $rKrs : null,
                            'regon'      => $rRegon !== '' ? $rRegon : null,
                            'bdo'        => $rBdo !== '' ? $rBdo : null,
                        ]);
                        if (!$CompanyRegisters->save($regEntity)) {
                            $firstError = current(current($regEntity->getErrors() ?: [['__all__' => ['Nie udało się zapisać rejestru.']]]));
                            throw new \RuntimeException((is_array($firstError) ? (string)current($firstError) : (string)$firstError) . ' (rejestr #' . ($idx + 1) . ')');
                        }
                    }

                    // 4) Serie numeracji (tworzenie/edycja/usuwanie + domyślna)
                    $existingSeries = $InvoiceSeries->find()
                        ->where(['company_id' => $ctxCompanyId])
                        ->all()
                        ->indexBy('id')
                        ->toArray();

                    if (!empty($invoiceSeriesDeleteIds)) {
                        Log::info('Companies.edit: requested invoice series deletes=' . json_encode($invoiceSeriesDeleteIds), ['series_debug']);
                        foreach ($invoiceSeriesDeleteIds as $deleteId) {
                            $deleteId = (string)$deleteId;
                            if ($deleteId === '' || !isset($existingSeries[$deleteId])) {
                                Log::warning('Companies.edit: delete skipped, series not found id=' . $deleteId . ' company=' . $ctxCompanyId, ['series_debug']);
                                continue;
                            }
                            $toDelete = $existingSeries[$deleteId];
                            if (!empty($toDelete->parent_id) || (int)($toDelete->is_blocked ?? 0) === 1) {
                                Log::warning('Companies.edit: delete blocked for system series id=' . $deleteId . ' name=' . (string)$toDelete->name, ['series_debug']);
                                throw new \RuntimeException('Nie można usunąć zablokowanej serii: ' . (string)$toDelete->name);
                            }
                            if (!$InvoiceSeries->delete($toDelete)) {
                                Log::error('Companies.edit: delete failed for series id=' . $deleteId . ' company=' . $ctxCompanyId, ['series_debug']);
                                throw new \RuntimeException('Nie udało się usunąć serii: ' . (string)$toDelete->name);
                            }
                            unset($existingSeries[$deleteId]);
                        }
                    }

                    $savedSeriesByKey = [];
                    foreach ($invoiceSeriesInput as $key => $row) {
                        $row = (array)$row;
                        if (!empty($row['_delete'])) {
                            continue;
                        }
                        $rowId = trim((string)($row['id'] ?? ''));
                        $name = trim((string)($row['name'] ?? ''));
                        $template = trim((string)($row['series_template'] ?? ''));
                        $startingNumber = (int)($row['starting_number'] ?? 1);
                        $overrideNextRaw = trim((string)($row['override_next_number'] ?? ''));
                        $overrideNext = $overrideNextRaw !== '' && (int)$overrideNextRaw > 0 ? (int)$overrideNextRaw : null;
                        $type = trim((string)($row['type'] ?? 'vat'));

                        if ($name === '' && $template === '') {
                            continue;
                        }
                        if ($name === '') {
                            throw new \RuntimeException('Nazwa serii jest wymagana.');
                        }
                        if ($template === '') {
                            throw new \RuntimeException('Wzorzec numeracji jest wymagany dla serii: ' . $name);
                        }
                        if ($startingNumber < 1) {
                            $startingNumber = 1;
                        }
                        if (!array_key_exists($type, $invoiceTypeOptions)) {
                            $type = 'vat';
                        }

                        $payload = [
                            'company_id' => $ctxCompanyId,
                            'name' => $name,
                            'series_template' => $template,
                            'starting_number' => $startingNumber,
                            'override_next_number' => $overrideNext,
                            'type' => $type,
                            'invoice_series_type_id' => trim((string)($row['invoice_series_type_id'] ?? '')) ?: null,
                            'invoice_series_period_id' => trim((string)($row['invoice_series_period_id'] ?? '')) ?: null,
                            'is_system' => 0,
                            'is_default' => false,
                        ];

                        if ($rowId !== '' && isset($existingSeries[$rowId])) {
                            $seriesEntity = $existingSeries[$rowId];
                            if (!empty($seriesEntity->parent_id) || (int)($seriesEntity->is_blocked ?? 0) === 1) {
                                Log::info('Companies.edit: system series – direct updateAll starting_number=' . $startingNumber . ' override_next_number=' . var_export($overrideNext, true) . ' id=' . $rowId, ['series_debug']);
                                // seria systemowa — bezpośredni update starting_number i override_next_number
                                $InvoiceSeries->updateAll(['starting_number' => $startingNumber, 'override_next_number' => $overrideNext], ['id' => $rowId]);
                                $savedSeriesByKey[(string)$key] = [
                                    'id' => (string)$seriesEntity->id,
                                    'type' => (string)($seriesEntity->type ?? 'vat'),
                                ];
                                continue;
                            }
                            $seriesEntity = $InvoiceSeries->patchEntity($seriesEntity, $payload);
                            Log::info('Companies.edit: updating series id=' . $rowId . ' payload=' . json_encode($payload), ['series_debug']);
                        } else {
                            $seriesEntity = $InvoiceSeries->newEntity($payload);
                            Log::info('Companies.edit: creating series payload=' . json_encode($payload), ['series_debug']);
                        }

                        if (!$InvoiceSeries->save($seriesEntity)) {
                            Log::error('Companies.edit: save series failed errors=' . json_encode($seriesEntity->getErrors()) . ' payload=' . json_encode($payload), ['series_debug']);
                            $firstError = current(current($seriesEntity->getErrors() ?: [['__all__' => ['Nie udało się zapisać serii numeracji.']]]));
                            throw new \RuntimeException(is_array($firstError) ? (string)current($firstError) : (string)$firstError);
                        }
                        Log::info('Companies.edit: series saved id=' . (string)$seriesEntity->id . ' key=' . (string)$key, ['series_debug']);
                        $savedSeriesByKey[(string)$key] = [
                            'id' => (string)$seriesEntity->id,
                            'type' => (string)($seriesEntity->type ?? 'vat'),
                        ];
                    }

                    if (!empty($savedSeriesByKey)) {
                        $selectedSeries = null;
                        if ($invoiceSeriesDefaultKey !== '' && isset($savedSeriesByKey[$invoiceSeriesDefaultKey])) {
                            $selectedSeries = $savedSeriesByKey[$invoiceSeriesDefaultKey];
                        } else {
                            $selectedSeries = reset($savedSeriesByKey) ?: null;
                        }

                        if (!empty($selectedSeries['id']) && !empty($selectedSeries['type'])) {
                            $selectedSeriesId = (string)$selectedSeries['id'];
                            $selectedType = (string)$selectedSeries['type'];
                            $InvoiceSeries->updateAll(['is_default' => 0], ['company_id' => $ctxCompanyId, 'type' => $selectedType]);
                            $InvoiceSeries->updateAll(['is_default' => 1], ['company_id' => $ctxCompanyId, 'id' => $selectedSeriesId]);
                            Log::info('Companies.edit: default series set id=' . $selectedSeriesId . ' type=' . $selectedType . ' company=' . $ctxCompanyId, ['series_debug']);
                        }
                    }
                });

                $this->Flash->success('Dane firmy zapisane.');
                return $this->redirect(['action' => 'edit', $ctxCompanyId]);
            } catch (\Throwable $e) {
                Log::error('Companies.edit: save transaction failed company=' . $ctxCompanyId . ' message=' . $e->getMessage(), ['series_debug']);
                $this->Flash->error('Nie udało się zapisać zmian: ' . $e->getMessage());
            }
        }

        $this->set(compact(
            'company',
            'bankAccount',
            'existingAccounts',
            'existingRegisters',
            'invoiceSeriesRows',
            'copiedSeriesCount',
            'invoiceSeriesTypeOptions',
            'invoiceSeriesPeriodOptions',
            'invoiceTypeOptions',
            'invoiceCountsByType',
            'currentYear',
            'hasKsefSentInvoice',
        ));
        // $this->render('edit');
    }

    /**
     * Delete method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $company = $this->Companies->get($id);
        if ($this->Companies->delete($company)) {
            $this->Flash->success(__('The company has been deleted.'));
        } else {
            $this->Flash->error(__('The company could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

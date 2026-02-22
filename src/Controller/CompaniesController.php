<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Companies Controller
 *
 * @property \App\Model\Table\CompaniesTable $Companies
 */
class CompaniesController extends AppController
{

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
    $banksInput  = (array)($this->request->getData('banks') ?? []);
    $defaultIdx  = (int)($this->request->getData('banks_default') ?? 0);

    // helper: IBAN bez spacji, upper
    $normalizeIban = static function (?string $v): string {
        return strtoupper(preg_replace('/\s+/', '', (string)$v));
    };

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
            ->contain(['CompanyBankAccounts'])
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

        if ($this->request->is(['patch', 'post', 'put'])) {
            // patchuj bez namespacu: pola formularza są płaskie (name, street, itd.)
            $dataCompany = (array)$this->request->getData();
            if (empty($dataCompany['profile_mode'])) {
                $dataCompany['profile_mode'] = 'business';
            }
            $dataCompany['id'] = $ctxCompanyId; // wymuś właściwe ID
            $company = $this->Companies->patchEntity($company, $dataCompany);

            // Banki z formularza (podobnie jak w onboardingu)
            $banksInput = (array)($this->request->getData('banks') ?? []);
            $defaultIdx = (int)($this->request->getData('banks_default') ?? 0);

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
                    $normalizeIban
                ) {
                    /** @var \App\Model\Table\CompaniesTable $Companies */
                    $Companies = $this->fetchTable('Companies');
                    /** @var \App\Model\Table\CompanyBankAccountsTable $CompanyBankAccounts */
                    $CompanyBankAccounts = $this->fetchTable('CompanyBankAccounts');

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
                });

                $this->Flash->success('Dane firmy zapisane.');
                return $this->redirect(['action' => 'edit', $ctxCompanyId]);
            } catch (\Throwable $e) {
                $this->Flash->error('Nie udało się zapisać zmian: ' . $e->getMessage());
            }
        }

        $this->set(compact('company', 'bankAccount', 'existingAccounts'));
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

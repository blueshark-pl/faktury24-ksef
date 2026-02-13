<?php
declare(strict_types=1);

namespace App\Controller;
use Cake\Database\Expression\QueryExpression;

/**
 * InvoiceSeries Controller
 *        // Znajdź serię dla firmy
        $seriesEntity = $this->InvoiceSeries->find()
            ->contain(['InvoiceSeriesPeriods', 'InvoiceSeriesTypes'])
            ->where([
                'company_id' => $companyId,
                'name' => $series
            ])
            ->first();operty \App\Model\Table\InvoiceSeriesTable $InvoiceSeries
 */
class InvoiceSeriesController extends AppController
{
    /**
     * GET /invoice-series/search.json?q=...&limit=20
     * Zwraca listę serii dla Select2: [{id,text,pattern,next_no}, ...]
     */
public function search()
{
    $this->request->allowMethod(['get']);

    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    $q = trim((string)$this->request->getQuery('q'));
    $type = trim((string)$this->request->getQuery('type'));
    $limit = (int)($this->request->getQuery('limit') ?? 20) ?: 20;

    $query = $this->InvoiceSeries->find()
        ->select(['id','name','series_template','starting_number','invoice_series_type_id','invoice_series_period_id','is_default', 'type'])
        ->where(['InvoiceSeries.company_id' => $companyId])
        ->order(['InvoiceSeries.is_default' => 'DESC', 'InvoiceSeries.name' => 'ASC'])
        ->limit($limit);

    // Jeśli podano typ (np. type=margin), filtruj po typie serii
    if ($type !== '') {
        $query->where(['InvoiceSeries.type' => $type]);
    }

    if ($q !== '') {
        $like = "%{$q}%";
        $query->where(function (QueryExpression $exp) use ($like) {
            return $exp->or([
                'InvoiceSeries.type LIKE'            => $like,
                'InvoiceSeries.series_template LIKE' => $like,
            ]);
        });
    }

    // Format pod Select2 (value = nazwa serii, ale zwracamy też row_id itd.)
    $out = $query->all()->map(function($s){
        return [
            'id'        => $s->name,             // wartość do select2 (wpisze się w pole "series")
            'text'      => $s->name,             // label wyświetlany w Select2
            'row_id'    => $s->id,               // ID rekordu (jeśli chcesz użyć)
            'name'      => $s->name,
            'template'  => $s->series_template,  // zamiast "pattern"
            'start_no'  => $s->starting_number,  // zamiast "next_no"
            'type_id'   => $s->invoice_series_type_id,
            'type'   => $s->type,
            'period_id' => $s->invoice_series_period_id,
            'is_default'=> (bool)$s->is_default,
        ];
    })->toList();

    return $this->response
        ->withType('application/json')
        ->withStringBody(json_encode(['results' => $out]));
}

public function add()
{
    $this->request->allowMethod(['post']);

    $identity  = $this->request->getAttribute('identity');
    $companyId = $identity?->get('company_id');

    $data = $this->request->getData();
    $payload = [
        'company_id' => $companyId,
        'name'       => trim((string)($data['name'] ?? '')),
        'series_template' => trim((string)($data['series_template'] ?? $data['pattern'] ?? '')), // Support both field names
        'starting_number' => $data['starting_number'] !== '' ? (int)($data['starting_number'] ?? $data['next_no'] ?? 1) : 1, // Support both field names
        'invoice_series_type_id' => !empty($data['invoice_series_type_id']) ? (int)$data['invoice_series_type_id'] : null,
        'invoice_series_period_id' => !empty($data['invoice_series_period_id']) ? (int)$data['invoice_series_period_id'] : null,
        'is_default' => !empty($data['is_default']) ? 1 : 0,
    ];

    $entity = $this->InvoiceSeries->newEmptyEntity();
    $entity = $this->InvoiceSeries->patchEntity($entity, $payload);

    if ($entity->getErrors()) {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => __('Niepoprawne dane serii.'),
                'errors'  => $entity->getErrors(),
            ]));
    }

    if ($this->InvoiceSeries->save($entity)) {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'id'      => (string)$entity->name, // value do select2
                'text'    => (string)$entity->name, // label
                'row_id'  => (int)$entity->id,      // ID rekordu
                'template' => $entity->series_template,
                'start_no' => $entity->starting_number,
                'type_id' => $entity->invoice_series_type_id,
                'period_id' => $entity->invoice_series_period_id,
                'is_default' => (bool)$entity->is_default,
            ]));
    }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => __('Nie udało się zapisać serii.'),
            ]));
    }

    /**
     * GET /invoice-series/next-number.json?series=...&period=...
     * Zwraca następny numer faktury dla danej serii i okresu
     */
    public function nextNumber()
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $series = trim((string)$this->request->getQuery('series'));
        $periodId = trim((string)$this->request->getQuery('period'));
        
        if (!$series) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Brak nazwy serii'
                ]));
        }

        // Znajdź serię wraz z informacją o okresie
        $seriesEntity = $this->InvoiceSeries->find()
            ->contain(['InvoiceSeriesPeriods'])
            ->where([
                'InvoiceSeries.company_id' => $companyId,
                'InvoiceSeries.name' => $series
            ])
            ->first();

        if (!$seriesEntity) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Seria nie istnieje'
                ]));
        }

        // Sprawdź jaki jest ostatni numer w tej serii
        $InvoicesTable = $this->getTableLocator()->get('Invoices');
        
        $issueDate = $this->request->getQuery('date') ?: date('Y-m-d');
        $year = date('Y', strtotime($issueDate));
        $month = date('m', strtotime($issueDate));
        
        // Określ warunki wyszukiwania w zależności od typu okresu
        $whereConditions = [
            'company_id' => $companyId,
            'invoice_series_id' => $seriesEntity->id
        ];
        
        $periodName = $seriesEntity->invoice_series_period->name ?? '';
        $periodType = 'continuous';
        
        error_log("InvoiceSeriesController nextNumber - Period name: " . $periodName);
        
        $query = $InvoicesTable->find()->where($whereConditions);
        
        if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
            // Miesięczne - dodaj warunki na rok i miesiąc
            $periodType = 'monthly';
            $query = $query->where(function($exp) use ($year, $month) {
                return $exp
                    ->eq('YEAR(date)', $year)
                    ->eq('MONTH(date)', $month);
            });
            error_log("Using monthly period for year: $year, month: $month");
        } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
            // Roczne - dodaj warunek na rok
            $periodType = 'yearly';
            $query = $query->where(function($exp) use ($year) {
                return $exp->eq('YEAR(date)', $year);
            });
            error_log("Using yearly period for year: $year");
        } else {
            error_log("Using continuous period");
        }
        
        $lastInvoice = $query
            ->order(['number' => 'DESC', 'id' => 'DESC'])
            ->first();

        // Wyciągnij numer z ostatniej faktury lub użyj startowego
        if ($lastInvoice) {
            // Znaleziono fakturę w bieżącym okresie - kontynuuj numerację
            if (isset($lastInvoice->number) && $lastInvoice->number > 0) {
                $extractedNumber = $lastInvoice->number;
            } else {
                $extractedNumber = $this->extractNumberFromFullnumber($lastInvoice->fullnumber);
            }
            $nextNumber = $extractedNumber + 1;
            error_log("Found last invoice in period: ID=" . $lastInvoice->id . ", fullnumber=" . $lastInvoice->fullnumber . ", extracted=" . $extractedNumber . ", next=" . $nextNumber);
        } else {
            // Brak faktur w bieżącym okresie - rozpocznij od numeru startowego
            $nextNumber = $seriesEntity->starting_number ?: 1;
            
            if ($periodType === 'monthly') {
                error_log("No invoice found in current month ($year-$month), starting from: $nextNumber");
            } elseif ($periodType === 'yearly') {
                error_log("No invoice found in current year ($year), starting from: $nextNumber");
            } else {
                error_log("No previous invoice found (continuous), using starting number: $nextNumber");
            }
        }
        
        // Sformatuj sugestię numeru z uwzględnieniem daty wystawienia
        $template = $seriesEntity->series_template ?: '[numer]';
        $formatted = $this->formatInvoicePattern($template, $nextNumber, $issueDate);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'next_number' => $nextNumber,
                'formatted' => $formatted,
                'template' => $template
            ]));
    }

    /**
     * Wyciąga numer z pełnego numeru faktury (np. "VAT/10/2025" -> 10)
     */
    private function extractNumberFromFullnumber($fullnumber)
    {
        // Szukaj liczby w fullnumber - zazwyczaj jest to liczba w środku lub na końcu
        if (preg_match('/\/(\d+)\//', $fullnumber, $matches)) {
            return (int)$matches[1];
        }
        // Jeśli nie ma slashów, spróbuj znaleźć ostatnią liczbę
        if (preg_match('/(\d+)$/', $fullnumber, $matches)) {
            return (int)$matches[1];
        }
        // Jeśli nie można wyciągnąć numeru, zwróć 0
        return 0;
    }

    /**
     * Formatuje wzorzec numeru faktury z nowymi znacznikami
     */
    private function formatInvoicePattern($pattern, $number, $date)
    {
        $timestamp = strtotime($date);
        $day = date('j', $timestamp);
        $month = date('n', $timestamp);
        $year = date('Y', $timestamp);
        $year2 = date('y', $timestamp);
        $quarter = ceil($month / 3);
        $dayOfYear = date('z', $timestamp) + 1;
        
        // Debug log
        error_log("formatInvoicePattern: pattern={$pattern}, number={$number}, date={$date}");
        error_log("Variables: day={$day}, month={$month}, year={$year}, quarter={$quarter}");
        
        // Obsługa starych znaczników (dla kompatybilności)
        $pattern = str_replace(['{NUMER}', '{MIESIAC}', '{ROK}', '{numer}', '{miesiac}', '{rok}', '{mm}', '{yyyy}'], 
                              ['[numer]', '[miesiąc]', '[rok]', '[numer]', '[miesiąc]', '[rok]', '[miesiąc]', '[rok]'], 
                              $pattern);
        
        error_log("After old tags conversion: {$pattern}");
        
        // Obsługa nowych znaczników z zerami wiodącymi
        $pattern = preg_replace_callback('/\[([^:\]]+)(?::zera_wiodące=(\d+))?\]/u', function($matches) use ($number, $day, $month, $year, $year2, $quarter, $dayOfYear) {
            $field = $matches[1];
            $padding = isset($matches[2]) ? (int)$matches[2] : 0;
            
            error_log("Processing field: {$field}, padding: {$padding}");
            
            switch ($field) {
                case 'numer':
                    $value = (string)$number;
                    break;
                case 'dzień':
                    $value = (string)$day;
                    break;
                case 'miesiąc':
                    $value = (string)$month;
                    break;
                case 'kwartał':
                    $value = (string)$quarter;
                    break;
                case 'rok':
                    $value = (string)$year;
                    break;
                case 'dzień_roku':
                    $value = (string)$dayOfYear;
                    break;
                default:
                    error_log("Unknown field: {$field}");
                    return $matches[0]; // Pozostaw niezmienione jeśli nieznany znacznik
            }
            
            $result = $padding > 0 ? str_pad($value, $padding, '0', STR_PAD_LEFT) : $value;
            error_log("Field {$field} = {$value} -> {$result}");
            return $result;
        }, $pattern);
        
        // Obsługa specjalnych formatów roku
        $pattern = str_replace('[rok:format_dwucyfrowy]', $year2, $pattern);
        
        error_log("Final formatted pattern: {$pattern}");
        return $pattern;
    }    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->InvoiceSeries->find()
            ->contain(['Companies', 'InvoiceSeriesTypes', 'InvoiceSeriesPeriods']);
        $invoiceSeries = $this->paginate($query);

        $this->set(compact('invoiceSeries'));
    }

    /**
     * View method
     *
     * @param string|null $id Invoice Series id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoiceSeries = $this->InvoiceSeries->get($id, contain: ['Companies', 'InvoiceSeriesTypes', 'InvoiceSeriesPeriods']);
        $this->set(compact('invoiceSeries'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Invoice Series id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $invoiceSeries = $this->InvoiceSeries->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceSeries = $this->InvoiceSeries->patchEntity($invoiceSeries, $this->request->getData());
            if ($this->InvoiceSeries->save($invoiceSeries)) {
                $this->Flash->success(__('The invoice series has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice series could not be saved. Please, try again.'));
        }
        $companies = $this->InvoiceSeries->Companies->find('list', limit: 200)->all();
        $invoiceSeriesTypes = $this->InvoiceSeries->InvoiceSeriesTypes->find('list', limit: 200)->all();
        $invoiceSeriesPeriods = $this->InvoiceSeries->InvoiceSeriesPeriods->find('list', limit: 200)->all();
        $this->set(compact('invoiceSeries', 'companies', 'invoiceSeriesTypes', 'invoiceSeriesPeriods'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice Series id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceSeries = $this->InvoiceSeries->get($id);
        if ($this->InvoiceSeries->delete($invoiceSeries)) {
            $this->Flash->success(__('The invoice series has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice series could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

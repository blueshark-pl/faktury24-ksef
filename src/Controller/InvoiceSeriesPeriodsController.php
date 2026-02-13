<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceSeriesPeriods Controller
 *
 * @property \App\Model\Table\InvoiceSeriesPeriodsTable $InvoiceSeriesPeriods
 */
class InvoiceSeriesPeriodsController extends AppController
{
    /**
     * GET /invoice-series-periods/search.json
     * Zwraca listę okresów serii dla Select2
     */
    public function search()
    {
        $this->request->allowMethod(['get']);

        $query = $this->InvoiceSeriesPeriods->find()
            ->select(['id', 'name'])
            ->order(['name' => 'ASC']);

        $out = $query->all()->map(function($period){
            return [
                'id'   => $period->id,
                'text' => $period->name,
            ];
        })->toList();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['results' => $out]));
    }

    /**
     * Index method
     */
    public function index()
    {
        $invoiceSeriesPeriods = $this->paginate($this->InvoiceSeriesPeriods);
        $this->set(compact('invoiceSeriesPeriods'));
    }

    /**
     * View method
     */
    public function view($id = null)
    {
        $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->get($id, contain: []);
        $this->set(compact('invoiceSeriesPeriod'));
    }

    /**
     * Add method - handles both regular form and AJAX requests
     */
    public function add()
    {
        $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->newEmptyEntity();
        
        if ($this->request->is('post')) {
            // Handle AJAX requests for JSON response
            if ($this->request->accepts('application/json')) {
                $data = $this->request->getData();
                $payload = [
                    'name' => trim((string)($data['name'] ?? '')),
                ];

                $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->patchEntity($invoiceSeriesPeriod, $payload);

                if ($invoiceSeriesPeriod->getErrors()) {
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => __('Niepoprawne dane okresu serii.'),
                            'errors'  => $invoiceSeriesPeriod->getErrors(),
                        ]));
                }

                if ($this->InvoiceSeriesPeriods->save($invoiceSeriesPeriod)) {
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'id'      => (int)$invoiceSeriesPeriod->id,
                            'text'    => (string)$invoiceSeriesPeriod->name,
                            'name'    => (string)$invoiceSeriesPeriod->name,
                        ]));
                }

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => __('Nie udało się zapisać okresu serii.'),
                    ]));
            }
            
            // Handle regular form submission
            $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->patchEntity($invoiceSeriesPeriod, $this->request->getData());
            if ($this->InvoiceSeriesPeriods->save($invoiceSeriesPeriod)) {
                $this->Flash->success(__('The invoice series period has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice series period could not be saved. Please, try again.'));
        }
        $this->set(compact('invoiceSeriesPeriod'));
    }

    /**
     * Edit method
     */
    public function edit($id = null)
    {
        $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->patchEntity($invoiceSeriesPeriod, $this->request->getData());
            if ($this->InvoiceSeriesPeriods->save($invoiceSeriesPeriod)) {
                $this->Flash->success(__('The invoice series period has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice series period could not be saved. Please, try again.'));
        }
        $this->set(compact('invoiceSeriesPeriod'));
    }

    /**
     * Delete method
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceSeriesPeriod = $this->InvoiceSeriesPeriods->get($id);
        if ($this->InvoiceSeriesPeriods->delete($invoiceSeriesPeriod)) {
            $this->Flash->success(__('The invoice series period has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice series period could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
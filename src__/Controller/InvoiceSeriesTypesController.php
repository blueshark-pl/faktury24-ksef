<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceSeriesTypes Controller
 *
 * @property \App\Model\Table\InvoiceSeriesTypesTable $InvoiceSeriesTypes
 */
class InvoiceSeriesTypesController extends AppController
{
    /**
     * GET /invoice-series-types/search.json
     * Zwraca listę typów serii dla Select2
     */
    public function search()
    {
        $this->request->allowMethod(['get']);

        $query = $this->InvoiceSeriesTypes->find()
            ->select(['id', 'name'])
            ->order(['name' => 'ASC']);

        $out = $query->all()->map(function($type){
            return [
                'id'   => $type->id,
                'text' => $type->name,
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
        $invoiceSeriesTypes = $this->paginate($this->InvoiceSeriesTypes);
        $this->set(compact('invoiceSeriesTypes'));
    }

    /**
     * View method
     */
    public function view($id = null)
    {
        $invoiceSeriesType = $this->InvoiceSeriesTypes->get($id, contain: []);
        $this->set(compact('invoiceSeriesType'));
    }

    /**
     * Add method - handles both regular form and AJAX requests
     */
    public function add()
    {
        $invoiceSeriesType = $this->InvoiceSeriesTypes->newEmptyEntity();
        
        if ($this->request->is('post')) {
            // Handle AJAX requests for JSON response
            if ($this->request->accepts('application/json')) {
                $data = $this->request->getData();
                $payload = [
                    'name' => trim((string)($data['name'] ?? '')),
                    'invoice_series_period_id' => !empty($data['invoice_series_period_id']) ? (int)$data['invoice_series_period_id'] : null,
                    'series_template' => trim((string)($data['series_template'] ?? '')),
                    'starting_number' => isset($data['starting_number']) && $data['starting_number'] !== '' ? (int)$data['starting_number'] : null,
                ];

                $invoiceSeriesType = $this->InvoiceSeriesTypes->patchEntity($invoiceSeriesType, $payload);

                if ($invoiceSeriesType->getErrors()) {
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => __('Niepoprawne dane typu serii.'),
                            'errors'  => $invoiceSeriesType->getErrors(),
                        ]));
                }

                if ($this->InvoiceSeriesTypes->save($invoiceSeriesType)) {
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'id'      => (int)$invoiceSeriesType->id,
                            'text'    => (string)$invoiceSeriesType->name,
                            'name'    => (string)$invoiceSeriesType->name,
                            'series_template' => (string)$invoiceSeriesType->series_template,
                            'starting_number' => (int)$invoiceSeriesType->starting_number,
                            'period_id' => $invoiceSeriesType->invoice_series_period_id,
                        ]));
                }

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => __('Nie udało się zapisać typu serii.'),
                    ]));
            }
            
            // Handle regular form submission
            $invoiceSeriesType = $this->InvoiceSeriesTypes->patchEntity($invoiceSeriesType, $this->request->getData());
            if ($this->InvoiceSeriesTypes->save($invoiceSeriesType)) {
                $this->Flash->success(__('The invoice series type has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice series type could not be saved. Please, try again.'));
        }
        $this->set(compact('invoiceSeriesType'));
    }

    /**
     * Edit method
     */
    public function edit($id = null)
    {
        $invoiceSeriesType = $this->InvoiceSeriesTypes->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceSeriesType = $this->InvoiceSeriesTypes->patchEntity($invoiceSeriesType, $this->request->getData());
            if ($this->InvoiceSeriesTypes->save($invoiceSeriesType)) {
                $this->Flash->success(__('The invoice series type has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice series type could not be saved. Please, try again.'));
        }
        $this->set(compact('invoiceSeriesType'));
    }

    /**
     * Delete method
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceSeriesType = $this->InvoiceSeriesTypes->get($id);
        if ($this->InvoiceSeriesTypes->delete($invoiceSeriesType)) {
            $this->Flash->success(__('The invoice series type has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice series type could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
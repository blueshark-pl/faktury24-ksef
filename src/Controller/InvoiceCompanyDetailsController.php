<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceCompanyDetails Controller
 *
 * @property \App\Model\Table\InvoiceCompanyDetailsTable $InvoiceCompanyDetails
 */
class InvoiceCompanyDetailsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->InvoiceCompanyDetails->find()
            ->contain(['Invoices']);
        $invoiceCompanyDetails = $this->paginate($query);

        $this->set(compact('invoiceCompanyDetails'));
    }

    /**
     * View method
     *
     * @param string|null $id Invoice Company Detail id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoiceCompanyDetail = $this->InvoiceCompanyDetails->get($id, contain: ['Invoices']);
        $this->set(compact('invoiceCompanyDetail'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $invoiceCompanyDetail = $this->InvoiceCompanyDetails->newEmptyEntity();
        if ($this->request->is('post')) {
            $invoiceCompanyDetail = $this->InvoiceCompanyDetails->patchEntity($invoiceCompanyDetail, $this->request->getData());
            if ($this->InvoiceCompanyDetails->save($invoiceCompanyDetail)) {
                $this->Flash->success(__('The invoice company detail has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice company detail could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceCompanyDetails->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceCompanyDetail', 'invoices'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Invoice Company Detail id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $invoiceCompanyDetail = $this->InvoiceCompanyDetails->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceCompanyDetail = $this->InvoiceCompanyDetails->patchEntity($invoiceCompanyDetail, $this->request->getData());
            if ($this->InvoiceCompanyDetails->save($invoiceCompanyDetail)) {
                $this->Flash->success(__('The invoice company detail has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice company detail could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceCompanyDetails->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceCompanyDetail', 'invoices'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice Company Detail id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceCompanyDetail = $this->InvoiceCompanyDetails->get($id);
        if ($this->InvoiceCompanyDetails->delete($invoiceCompanyDetail)) {
            $this->Flash->success(__('The invoice company detail has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice company detail could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

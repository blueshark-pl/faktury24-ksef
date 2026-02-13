<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceContractors Controller
 *
 * @property \App\Model\Table\InvoiceContractorsTable $InvoiceContractors
 */
class InvoiceContractorsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->InvoiceContractors->find()
            ->contain(['Invoices']);
        $invoiceContractors = $this->paginate($query);

        $this->set(compact('invoiceContractors'));
    }

    /**
     * View method
     *
     * @param string|null $id Invoice Contractor id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoiceContractor = $this->InvoiceContractors->get($id, contain: ['Invoices']);
        $this->set(compact('invoiceContractor'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $invoiceContractor = $this->InvoiceContractors->newEmptyEntity();
        if ($this->request->is('post')) {
            $invoiceContractor = $this->InvoiceContractors->patchEntity($invoiceContractor, $this->request->getData());
            if ($this->InvoiceContractors->save($invoiceContractor)) {
                $this->Flash->success(__('The invoice contractor has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice contractor could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceContractors->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceContractor', 'invoices'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Invoice Contractor id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $invoiceContractor = $this->InvoiceContractors->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceContractor = $this->InvoiceContractors->patchEntity($invoiceContractor, $this->request->getData());
            if ($this->InvoiceContractors->save($invoiceContractor)) {
                $this->Flash->success(__('The invoice contractor has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice contractor could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceContractors->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceContractor', 'invoices'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice Contractor id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceContractor = $this->InvoiceContractors->get($id);
        if ($this->InvoiceContractors->delete($invoiceContractor)) {
            $this->Flash->success(__('The invoice contractor has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice contractor could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

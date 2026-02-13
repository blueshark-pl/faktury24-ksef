<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceVatContents Controller
 *
 * @property \App\Model\Table\InvoiceVatContentsTable $InvoiceVatContents
 */
class InvoiceVatContentsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->InvoiceVatContents->find()
            ->contain(['Invoices']);
        $invoiceVatContents = $this->paginate($query);

        $this->set(compact('invoiceVatContents'));
    }

    /**
     * View method
     *
     * @param string|null $id Invoice Vat Content id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoiceVatContent = $this->InvoiceVatContents->get($id, contain: ['Invoices']);
        $this->set(compact('invoiceVatContent'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $invoiceVatContent = $this->InvoiceVatContents->newEmptyEntity();
        if ($this->request->is('post')) {
            $invoiceVatContent = $this->InvoiceVatContents->patchEntity($invoiceVatContent, $this->request->getData());
            if ($this->InvoiceVatContents->save($invoiceVatContent)) {
                $this->Flash->success(__('The invoice vat content has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice vat content could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceVatContents->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceVatContent', 'invoices'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Invoice Vat Content id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $invoiceVatContent = $this->InvoiceVatContents->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceVatContent = $this->InvoiceVatContents->patchEntity($invoiceVatContent, $this->request->getData());
            if ($this->InvoiceVatContents->save($invoiceVatContent)) {
                $this->Flash->success(__('The invoice vat content has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice vat content could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceVatContents->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceVatContent', 'invoices'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice Vat Content id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceVatContent = $this->InvoiceVatContents->get($id);
        if ($this->InvoiceVatContents->delete($invoiceVatContent)) {
            $this->Flash->success(__('The invoice vat content has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice vat content could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

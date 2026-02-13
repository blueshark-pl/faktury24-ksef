<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoiceContents Controller
 *
 * @property \App\Model\Table\InvoiceContentsTable $InvoiceContents
 */
class InvoiceContentsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->InvoiceContents->find()
            ->contain(['Invoices']);
        $invoiceContents = $this->paginate($query);

        $this->set(compact('invoiceContents'));
    }

    /**
     * View method
     *
     * @param string|null $id Invoice Content id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $invoiceContent = $this->InvoiceContents->get($id, contain: ['Invoices']);
        $this->set(compact('invoiceContent'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $invoiceContent = $this->InvoiceContents->newEmptyEntity();
        if ($this->request->is('post')) {
            $invoiceContent = $this->InvoiceContents->patchEntity($invoiceContent, $this->request->getData());
            if ($this->InvoiceContents->save($invoiceContent)) {
                $this->Flash->success(__('The invoice content has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice content could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceContents->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceContent', 'invoices'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Invoice Content id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $invoiceContent = $this->InvoiceContents->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $invoiceContent = $this->InvoiceContents->patchEntity($invoiceContent, $this->request->getData());
            if ($this->InvoiceContents->save($invoiceContent)) {
                $this->Flash->success(__('The invoice content has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The invoice content could not be saved. Please, try again.'));
        }
        $invoices = $this->InvoiceContents->Invoices->find('list', limit: 200)->all();
        $this->set(compact('invoiceContent', 'invoices'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Invoice Content id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $invoiceContent = $this->InvoiceContents->get($id);
        if ($this->InvoiceContents->delete($invoiceContent)) {
            $this->Flash->success(__('The invoice content has been deleted.'));
        } else {
            $this->Flash->error(__('The invoice content could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

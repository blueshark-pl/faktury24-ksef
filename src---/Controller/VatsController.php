<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Vats Controller
 *
 * @property \App\Model\Table\VatsTable $Vats
 */
class VatsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Vats->find();
        $vats = $this->paginate($query);

        $this->set(compact('vats'));
    }

    /**
     * View method
     *
     * @param string|null $id Vat id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $vat = $this->Vats->get($id, contain: ['Services']);
        $this->set(compact('vat'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $vat = $this->Vats->newEmptyEntity();
        if ($this->request->is('post')) {
            $vat = $this->Vats->patchEntity($vat, $this->request->getData());
            if ($this->Vats->save($vat)) {
                $this->Flash->success(__('The vat has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The vat could not be saved. Please, try again.'));
        }
        $this->set(compact('vat'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Vat id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $vat = $this->Vats->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $vat = $this->Vats->patchEntity($vat, $this->request->getData());
            if ($this->Vats->save($vat)) {
                $this->Flash->success(__('The vat has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The vat could not be saved. Please, try again.'));
        }
        $this->set(compact('vat'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Vat id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $vat = $this->Vats->get($id);
        if ($this->Vats->delete($vat)) {
            $this->Flash->success(__('The vat has been deleted.'));
        } else {
            $this->Flash->error(__('The vat could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

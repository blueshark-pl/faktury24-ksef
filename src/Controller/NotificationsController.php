<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Notifications Controller
 *
 * @property \App\Model\Table\NotificationsTable $Notifications
 */
class NotificationsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
public function index(): ?Response
    {
        $this->request->allowMethod(['get']);

        $q        = trim((string)$this->request->getQuery('q'));
        $channel  = $this->request->getQuery('channel');   // email|push|sms|null
        $severity = $this->request->getQuery('severity');  // info|success|warning|danger|null
        $read     = $this->request->getQuery('read');      // 0|1|null
        $type     = $this->request->getQuery('type');      // dowolny string

        /** @var \App\Model\Table\NotificationsTable $Notifications */
        $Notifications = $this->fetchTable('Notifications');

        $query = $Notifications->find()
            ->orderDesc('Notifications.created');

        if ($q !== '') {
            $query->where(function ($exp, $qry) use ($q) {
                return $exp->or_([
                    'Notifications.title LIKE' => "%$q%",
                    'Notifications.message LIKE' => "%$q%",
                    'Notifications.type LIKE' => "%$q%",
                ]);
            });
        }
        if ($channel !== null && $channel !== '') {
            $query->where(['Notifications.channel' => $channel]);
        }
        if ($severity !== null && $severity !== '') {
            $query->where(['Notifications.severity' => $severity]);
        }
        if ($type !== null && $type !== '') {
            $query->where(['Notifications.type' => $type]);
        }
        if ($read !== null && $read !== '') {
            $query->where(['Notifications.is_read' => (bool)$read]);
        }

        $this->paginate = [
            'limit' => 25,
        ];
        $notifications = $this->paginate($query);

        // liczniki do UI (np. zakładki)
        $unreadCount = $Notifications->find()->where(['is_read' => false])->count();

        $this->set(compact('notifications', 'unreadCount', 'q', 'channel', 'severity', 'read', 'type'));
        $this->viewBuilder()->setLayout('default'); // dopasuj do Zynix
        return null;
    }

    /**
     * View method
     *
     * @param string|null $id Notification id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $notification = $this->Notifications->get($id, contain: ['Users']);
        $this->set(compact('notification'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $notification = $this->Notifications->newEmptyEntity();
        if ($this->request->is('post')) {
            $notification = $this->Notifications->patchEntity($notification, $this->request->getData());
            if ($this->Notifications->save($notification)) {
                $this->Flash->success(__('The notification has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The notification could not be saved. Please, try again.'));
        }
        $users = $this->Notifications->Users->find('list', limit: 200)->all();
        $this->set(compact('notification', 'users'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Notification id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $notification = $this->Notifications->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $notification = $this->Notifications->patchEntity($notification, $this->request->getData());
            if ($this->Notifications->save($notification)) {
                $this->Flash->success(__('The notification has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The notification could not be saved. Please, try again.'));
        }
        $users = $this->Notifications->Users->find('list', limit: 200)->all();
        $this->set(compact('notification', 'users'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Notification id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $notification = $this->Notifications->get($id);
        if ($this->Notifications->delete($notification)) {
            $this->Flash->success(__('The notification has been deleted.'));
        } else {
            $this->Flash->error(__('The notification could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}

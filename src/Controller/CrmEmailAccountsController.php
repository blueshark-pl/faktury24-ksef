<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

class CrmEmailAccountsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $rows = $this->fetchTable('CrmEmailAccounts')->find()
            ->contain(['Users' => function ($q) { return $q->select(['id', 'first_name', 'last_name']); }])
            ->where(['CrmEmailAccounts.company_id' => $companyId])
            ->orderByDesc('CrmEmailAccounts.is_active')
            ->all();
        $this->set(compact('rows'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['company_id'] = $companyId;
            $data['is_active']  = !empty($data['is_active']);
            $data['use_ssl']    = !empty($data['use_ssl']);
            $plain = trim((string)($data['password'] ?? ''));
            unset($data['password']);

            $entity = $EA->patchEntity($entity, $data);
            if ($plain !== '') {
                $entity->password_encrypted = $EA->encryptPassword($plain);
            } else {
                $this->Flash->error(__('Podaj hasło do skrzynki.'));
                $this->set(compact('entity'));
                $this->set('isEdit', false);
                $this->render('add');
                return;
            }

            if ($EA->save($entity)) {
                $this->Flash->success(__('Konto email dodane. Uruchom „bin/cake crm_email_poll" żeby zsynchronizować.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu konta.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            unset($data['company_id']);
            $data['is_active'] = !empty($data['is_active']);
            $data['use_ssl']   = !empty($data['use_ssl']);
            $plain = trim((string)($data['password'] ?? ''));
            unset($data['password']);

            $entity = $EA->patchEntity($entity, $data);
            if ($plain !== '') {
                $entity->password_encrypted = $EA->encryptPassword($plain);
            }

            if ($EA->save($entity)) {
                $this->Flash->success(__('Konto zaktualizowane.'));
                $this->redirect(['action' => 'index']);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $this->set(compact('entity'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        $EA->delete($entity);
        $this->Flash->success(__('Konto usunięte.'));
        $this->redirect(['action' => 'index']);
    }

    /**
     * Test polaczenia IMAP - bez pobierania nowych, tylko login+status folderu.
     */
    public function test(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $EA = $this->fetchTable('CrmEmailAccounts');
        $entity = $EA->get($id);
        if ((string)$entity->company_id !== (string)$companyId) throw new NotFoundException();

        if (!function_exists('imap_open')) {
            $this->Flash->error(__('PHP IMAP extension nie jest zainstalowane na serwerze.'));
            $this->redirect(['action' => 'index']);
            return;
        }

        $password = $EA->decryptPassword($entity->password_encrypted);
        $ssl = $entity->use_ssl ? '/ssl' : '';
        $mailbox = sprintf('{%s:%d/imap%s}%s', $entity->imap_host, $entity->imap_port, $ssl, $entity->folder);

        try {
            $conn = @imap_open($mailbox, $entity->username, $password, OP_READONLY);
            if (!$conn) {
                $err = imap_last_error() ?: 'nieznany błąd';
                $entity->last_error = 'TEST: ' . $err;
                $EA->save($entity);
                $this->Flash->error(sprintf(__('Test IMAP nieudany: %s'), $err));
            } else {
                $status = imap_status($conn, $mailbox, SA_MESSAGES | SA_UIDNEXT);
                imap_close($conn);
                $entity->last_error = null;
                $EA->save($entity);
                $this->Flash->success(sprintf(__('Połączenie OK. Wiadomości w folderze: %d, następny UID: %d'),
                    $status->messages ?? 0, $status->uidnext ?? 0));
            }
        } catch (\Throwable $e) {
            $this->Flash->error(sprintf(__('Wyjątek IMAP: %s'), $e->getMessage()));
        }
        $this->redirect(['action' => 'index']);
    }
}

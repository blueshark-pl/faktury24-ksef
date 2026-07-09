<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\SupportTicketsTable;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;

class SupportTicketsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function index(): void
    {
        $identity  = $this->request->getAttribute('identity');
        $userId    = (string)($identity?->getIdentifier() ?? '');

        $query = $this->fetchTable('SupportTickets')->find()
            ->where(['SupportTickets.user_id' => $userId])
            ->orderDesc('SupportTickets.created');

        $tickets = $this->paginate($query, ['limit' => 20]);
        $this->set(compact('tickets'));
    }

    public function add(): ?\Cake\Http\Response
    {
        $identity  = $this->request->getAttribute('identity');
        $userId    = (string)($identity?->getIdentifier() ?? '');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $SupportTickets = $this->fetchTable('SupportTickets');
        $ticket = $SupportTickets->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            unset($data['attachment']); // plik obsługiwany osobno przez getUploadedFile()
            $ticket = $SupportTickets->patchEntity($ticket, $data);
            $ticket->user_id   = $userId;
            $ticket->company_id = $companyId ?: null;
            $ticket->status    = 'nowe';

            // Obsługa załącznika
            $uploadedFile = $this->request->getUploadedFile('attachment');
            if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $mime    = $uploadedFile->getClientMediaType();
                $size    = $uploadedFile->getSize();

                if (!in_array($mime, $allowed, true)) {
                    $this->Flash->error('Niedozwolony typ pliku. Akceptowane: JPG, PNG, GIF, PDF.');
                } elseif ($size > 5 * 1024 * 1024) {
                    $this->Flash->error('Plik jest za duży. Maksymalny rozmiar to 5 MB.');
                } else {
                    $origName = $uploadedFile->getClientFilename();
                    $ext      = strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION));
                    $dir      = WWW_ROOT . 'uploads' . DS . 'support';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = 'ticket_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $uploadedFile->moveTo($dir . DS . $filename);
                    $ticket->attachment      = $filename;
                    $ticket->attachment_name = $origName;
                }
            }

            if (!isset($ticket->attachment)) {
                $ticket->attachment = null;
            }

            if ($SupportTickets->save($ticket)) {
                // Email do admina
                try {
                    $this->_sendNewTicketEmail($ticket, (string)($identity?->get('email') ?? ''));
                } catch (\Throwable $e) {
                    \Cake\Log\Log::error('[SupportTickets] Email do admina nieudany: ' . $e->getMessage());
                }
                $this->Flash->success('Zgłoszenie zostało wysłane. Odpowiemy najszybciej jak to możliwe.');
                return $this->redirect(['action' => 'view', $ticket->id]);
            }
            $this->Flash->error('Nie udało się zapisać zgłoszenia. Sprawdź formularz.');
        }

        $categories = SupportTicketsTable::CATEGORIES;
        $this->set(compact('ticket', 'categories'));
        return null;
    }

    public function view(int $id): ?\Cake\Http\Response
    {
        $identity = $this->request->getAttribute('identity');
        $userId   = (string)($identity?->getIdentifier() ?? '');

        $SupportTickets = $this->fetchTable('SupportTickets');
        $ticket = $SupportTickets->find()
            ->where(['SupportTickets.id' => $id])
            ->contain(['SupportTicketReplies'])
            ->first();

        if (!$ticket) {
            throw new NotFoundException('Zgłoszenie nie istnieje.');
        }
        if ((string)$ticket->user_id !== $userId) {
            throw new ForbiddenException('Brak dostępu do tego zgłoszenia.');
        }

        if ($this->request->is('post') && $ticket->status !== 'zamkniete') {
            $Replies = $this->fetchTable('SupportTicketReplies');
            $reply   = $Replies->newEmptyEntity();
            $reply   = $Replies->patchEntity($reply, ['message' => $this->request->getData('message')]);
            $reply->support_ticket_id = $ticket->id;
            $reply->user_id           = $userId;
            $reply->author_name       = (string)($identity?->get('email') ?? 'Użytkownik');
            $reply->is_admin_reply    = false;

            if ($Replies->save($reply)) {
                // Powiadom admina o odpowiedzi użytkownika
                try {
                    $this->_sendUserReplyEmail($ticket, $reply, (string)($identity?->get('email') ?? ''));
                } catch (\Throwable $e) {
                    \Cake\Log\Log::error('[SupportTickets] Email admina (user reply): ' . $e->getMessage());
                }
                $this->Flash->success('Odpowiedź została dodana.');
            } else {
                $this->Flash->error('Nie udało się dodać odpowiedzi.');
            }
            return $this->redirect(['action' => 'view', $id]);
        }

        $taskUpdates = $this->fetchTable('TaskUpdates')->find()
            ->where(['support_ticket_id' => $id])
            ->orderAsc('created')
            ->all();

        $this->set(compact('ticket', 'taskUpdates'));
        return null;
    }

    public function download(int $id): \Cake\Http\Response
    {
        $identity = $this->request->getAttribute('identity');
        $userId   = (string)($identity?->getIdentifier() ?? '');
        $isAdmin  = (bool)($identity?->get('is_admin') ?? false)
            || strtolower((string)($identity?->get('role') ?? '')) === 'admin';

        $ticket = $this->fetchTable('SupportTickets')->get($id);
        if (!$isAdmin && (string)$ticket->user_id !== $userId) {
            throw new ForbiddenException();
        }
        if (empty($ticket->attachment)) {
            throw new NotFoundException('Brak załącznika.');
        }

        $path = WWW_ROOT . 'uploads' . DS . 'support' . DS . $ticket->attachment;
        if (!is_file($path)) {
            throw new NotFoundException('Plik nie istnieje.');
        }

        return $this->response->withFile($path, [
            'download' => false,
            'name'     => $ticket->attachment_name ?: $ticket->attachment,
        ]);
    }

    // ── Emaile ──────────────────────────────────────────────────────────────

    private function _sendNewTicketEmail(\App\Model\Entity\SupportTicket $ticket, string $userEmail): void
    {
        $adminEmail = \Cake\Core\Configure::read('App.adminEmail') ?: 'kontakt@faktury24.com';
        $mailer = new \Cake\Mailer\Mailer('default');
        $mailer->setTo($adminEmail)
            ->setSubject('Nowe zgłoszenie #' . $ticket->id . ' – ' . $ticket->title)
            ->setEmailFormat('html')
            ->viewBuilder()->setLayout('default')->setTemplate('support_new_ticket');
        $mailer->setViewVars([
            'ticket'    => $ticket,
            'userEmail' => $userEmail,
        ]);
        $mailer->deliver();
    }

    private function _sendUserReplyEmail(\App\Model\Entity\SupportTicket $ticket, \App\Model\Entity\SupportTicketReply $reply, string $userEmail): void
    {
        $adminEmail = \Cake\Core\Configure::read('App.adminEmail') ?: 'kontakt@faktury24.com';
        $mailer = new \Cake\Mailer\Mailer('default');
        $mailer->setTo($adminEmail)
            ->setSubject('Odpowiedź użytkownika do zgłoszenia #' . $ticket->id . ' – ' . $ticket->title)
            ->setEmailFormat('html')
            ->viewBuilder()->setLayout('default')->setTemplate('support_user_reply');
        $mailer->setViewVars([
            'ticket'    => $ticket,
            'reply'     => $reply,
            'userEmail' => $userEmail,
        ]);
        $mailer->deliver();
    }
}

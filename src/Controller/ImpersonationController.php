<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\View\JsonView;

/**
 * ImpersonationController
 *
 * Admin-only: pozwala zalogować się jako dowolny użytkownik bez znajomości jego hasła.
 * - GET  /admin/impersonate/search?q=... — JSON lista użytkowników (autocomplete)
 * - POST /admin/impersonate/start/{id}   — wcielenie się w użytkownika
 * - POST /admin/impersonate/stop          — powrót do oryginalnej tożsamości
 *
 * W sesji trzymamy `Impersonation.original_user_id` — gdy ustawione, w layoucie
 * pojawia się sticky banner z przyciskiem powrotu.
 */
class ImpersonationController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        if (!$this->components()->has('Authentication')) {
            $this->loadComponent('Authentication.Authentication');
        }
    }

    public function beforeFilter(EventInterface $event)
    {
        // Wykonaj logikę AppController (m.in. wymóg posiadania firmy) tylko dla `start`.
        // Dla `search` i `stop` chcemy działać niezależnie od stanu firmy aktualnej tożsamości.
        $action = $this->request->getParam('action');
        if (in_array($action, ['search', 'stop'], true)) {
            return; // pomijamy beforeFilter AppController (whitelista poniżej)
        }
        return parent::beforeFilter($event);
    }

    /**
     * Wymaga uprawnień admina LUB aktywnej sesji impersonacji (dla `stop`).
     */
    private function ensureAccess(bool $allowImpersonatedStop = false): void
    {
        $identity = $this->request->getAttribute('identity');
        $isAdmin  = (bool)($identity?->get('is_admin') ?? false);
        $role     = strtolower((string)($identity?->get('role') ?? ''));
        if ($isAdmin || $role === 'admin') {
            return;
        }
        if ($allowImpersonatedStop && $this->request->getSession()->check('Impersonation.original_user_id')) {
            return;
        }
        throw new ForbiddenException('Dostęp tylko dla administratora.');
    }

    /**
     * GET /admin/impersonate/search?q=...
     * Zwraca JSON: [{id, email, name, company, is_admin}]
     */
    public function search()
    {
        $this->ensureAccess(false);
        $this->request->allowMethod(['get']);

        $q = trim((string)$this->request->getQuery('q', ''));
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['items']);

        if (mb_strlen($q) < 2) {
            $this->set('items', []);
            return;
        }

        /** @var \Cake\ORM\Table $Users */
        $Users = $this->fetchTable('Users');
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        // NIP — tylko cyfry z zapytania (akceptujemy wpis z myślnikami/spacjami/"PL")
        $nipDigits = preg_replace('/\D+/', '', $q);

        $query = $Users->find()
            ->select([
                'Users.id', 'Users.email', 'Users.first_name', 'Users.last_name', 'Users.role', 'Users.company_id',
                'company_name' => 'Companies.name',
            ])
            // Jawny LEFT JOIN do companies (bez polegania na asocjacji ORM) — pozwala
            // filtrować po Companies.name/nip i zwraca nazwę firmy bez N+1.
            ->leftJoin(['Companies' => 'companies'], ['Companies.id = Users.company_id'])
            ->where(function ($exp, $q) use ($like, $nipDigits) {
                $or = $exp->or([
                    'Users.email LIKE'      => $like,
                    'Users.first_name LIKE' => $like,
                    'Users.last_name LIKE'  => $like,
                    'Companies.name LIKE'   => $like,
                ]);
                // NIP: porównanie po znormalizowanym numerze (usuwamy -, spacje, kropki z zapisanej wartości).
                // $nipDigits to wyłącznie cyfry → bezpieczne w surowym wyrażeniu.
                if (strlen($nipDigits) >= 3) {
                    $or->add($q->newExpr(
                        "REPLACE(REPLACE(REPLACE(Companies.nip, '-', ''), ' ', ''), '.', '') LIKE '%" . $nipDigits . "%'"
                    ));
                }
                return $or;
            })
            ->order(['Users.email' => 'ASC'])
            ->limit(20);

        $items = [];
        foreach ($query as $user) {
            $first = trim((string)($user->first_name ?? ''));
            $last  = trim((string)($user->last_name ?? ''));
            $full  = trim($first . ' ' . $last);

            $items[] = [
                'id'       => (string)$user->id,
                'email'    => (string)$user->email,
                'name'     => $full,
                'company'  => $user->company_name ?? null,
                'is_admin' => strtolower((string)($user->role ?? '')) === 'admin',
            ];
        }

        $this->set('items', $items);
    }

    /**
     * POST /admin/impersonate/start/{userId}
     */
    public function start(?string $userId = null)
    {
        $this->ensureAccess(false);
        $this->request->allowMethod(['post']);

        if (!$userId) {
            throw new BadRequestException('Brak userId.');
        }

        $identity = $this->request->getAttribute('identity');
        $originalId = (string)($identity?->getIdentifier() ?? '');
        if ($originalId === '') {
            throw new ForbiddenException('Brak tożsamości.');
        }
        if ($originalId === $userId) {
            $this->Flash->info('To Twoje konto — nie ma w co się wcielać.');
            return $this->redirect($this->referer(['controller' => 'Invoices', 'action' => 'index'], true));
        }

        /** @var \Cake\ORM\Table $Users */
        $Users = $this->fetchTable('Users');
        $target = $Users->find()->where(['Users.id' => $userId])->first();
        if (!$target) {
            throw new NotFoundException('Użytkownik nie istnieje.');
        }

        $session = $this->request->getSession();

        // Jeżeli już jesteśmy w impersonacji, nie nadpisuj `original_user_id`.
        if (!$session->check('Impersonation.original_user_id')) {
            $session->write('Impersonation.original_user_id', $originalId);
        }
        $session->write('Impersonation.impersonated_user_id', (string)$target->id);

        try {
            $this->Authentication->setIdentity($target);
        } catch (\Throwable $e) {
            Log::error('Impersonate setIdentity error: ' . $e->getMessage(), ['impersonation']);
            throw new ForbiddenException('Nie udało się przełączyć tożsamości.');
        }

        Log::info("Admin {$originalId} impersonates user {$target->id}", ['impersonation']);

        return $this->redirect(['controller' => 'Invoices', 'action' => 'index']);
    }

    /**
     * POST /admin/impersonate/stop
     */
    public function stop()
    {
        $this->ensureAccess(true);
        $this->request->allowMethod(['post']);

        $session = $this->request->getSession();
        $originalId = (string)$session->read('Impersonation.original_user_id', '');
        if ($originalId === '') {
            $this->Flash->info('Nie jesteś w trybie wcielenia.');
            return $this->redirect(['controller' => 'Invoices', 'action' => 'index']);
        }

        /** @var \Cake\ORM\Table $Users */
        $Users = $this->fetchTable('Users');
        $original = $Users->find()->where(['Users.id' => $originalId])->first();
        if (!$original) {
            $session->delete('Impersonation');
            throw new NotFoundException('Nie znaleziono oryginalnego konta.');
        }

        try {
            $this->Authentication->setIdentity($original);
        } catch (\Throwable $e) {
            Log::error('Impersonate stop setIdentity error: ' . $e->getMessage(), ['impersonation']);
            throw new ForbiddenException('Nie udało się przywrócić tożsamości.');
        }

        $session->delete('Impersonation');

        Log::info("Admin {$originalId} stopped impersonation", ['impersonation']);

        return $this->redirect(['controller' => 'Invoices', 'action' => 'index']);
    }
}

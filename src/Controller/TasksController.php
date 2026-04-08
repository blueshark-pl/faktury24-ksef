<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\TasksTable;
use Cake\Http\Exception\NotFoundException;

class TasksController extends AppController
{
    // ── Kanban board ─────────────────────────────────────────────────────────

    public function index(): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $Tasks = $this->fetchTable('Tasks');

        // Wszystkie taski pogrupowane po kolumnach, posortowane po pozycji
        $tasks = $Tasks->find()
            ->contain(['TaskLabels', 'TaskTimeEntries'])
            ->orderAsc('Tasks.kanban_position')
            ->orderAsc('Tasks.id')
            ->all();

        // Grupuj po kolumnie
        $board = [];
        foreach (array_keys(TasksTable::COLUMNS) as $col) {
            $board[$col] = [];
        }
        foreach ($tasks as $task) {
            $col = $task->kanban_column;
            if (!isset($board[$col])) $board[$col] = [];
            // Policz minuty
            $task->total_minutes = array_sum(array_filter(
                array_map(fn($e) => $e->minutes, $task->task_time_entries ?? [])
            ));
            $board[$col][] = $task;
        }

        $columns   = TasksTable::COLUMNS;
        $labels    = $this->fetchTable('TaskLabels')->find()->orderAsc('name')->all();

        $this->set(compact('board', 'columns', 'labels'));
        return null;
    }

    // ── Dodaj task ───────────────────────────────────────────────────────────

    public function add(): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $identity = $this->request->getAttribute('identity');
        $Tasks    = $this->fetchTable('Tasks');
        $task     = $Tasks->newEmptyEntity();

        // Pre-fill z support ticket
        $ticketId = (int)$this->request->getQuery('ticket_id');
        $ticket   = null;
        if ($ticketId) {
            $ticket = $this->fetchTable('SupportTickets')->find()
                ->where(['id' => $ticketId])->first();
            if ($ticket) {
                $task->support_ticket_id = $ticketId;
                $task->title = $ticket->title;
                $task->description = $ticket->description;
            }
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $task = $Tasks->patchEntity($task, $data, ['associated' => ['TaskLabels']]);
            $task->created_by = (string)($identity?->getIdentifier() ?? null);

            if ($Tasks->save($task, ['associated' => ['TaskLabels']])) {
                // Jeśli jest powiązany ticket — dodaj update widoczny dla usera
                if (!empty($task->support_ticket_id)) {
                    $this->_addTaskUpdate($task->id, $task->support_ticket_id,
                        'Zgłoszenie zostało przyjęte do realizacji jako task ' . $task->number_formatted . '.');
                }
                $this->Flash->success('Task ' . $task->number_formatted . ' utworzony.');
                return $this->redirect(['action' => 'view', $task->id]);
            }
            $this->Flash->error('Nie udało się zapisać taska. Sprawdź formularz.');
        }

        $columns   = TasksTable::COLUMNS;
        $statuses  = TasksTable::STATUSES;
        $priorities= TasksTable::PRIORITIES;
        $labels    = $this->fetchTable('TaskLabels')->find()->orderAsc('name')->all();

        $this->set(compact('task', 'ticket', 'columns', 'statuses', 'priorities', 'labels'));
        return null;
    }

    // ── Szczegóły taska ──────────────────────────────────────────────────────

    public function view(int $id): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $identity = $this->request->getAttribute('identity');
        $userId   = (string)($identity?->getIdentifier() ?? '');
        $Tasks    = $this->fetchTable('Tasks');

        $task = $Tasks->find()
            ->where(['Tasks.id' => $id])
            ->contain([
                'TaskLabels',
                'TaskTimeEntries' => fn($q) => $q->orderDesc('started_at'),
                'TaskUpdates'     => fn($q) => $q->orderDesc('created'),
            ])
            ->first();

        if (!$task) throw new NotFoundException('Task nie istnieje.');

        // Obsługa formularza edycji / dodania wpisu czasu / update
        if ($this->request->is('post')) {
            $action = $this->request->getData('_action');

            if ($action === 'edit') {
                $task = $Tasks->patchEntity($task, $this->request->getData(), ['associated' => ['TaskLabels']]);
                if ($Tasks->save($task, ['associated' => ['TaskLabels']])) {
                    $this->Flash->success('Task zaktualizowany.');
                } else {
                    $this->Flash->error('Błąd zapisu.');
                }

            } elseif ($action === 'add_update') {
                $msg = trim((string)$this->request->getData('message'));
                if ($msg !== '') {
                    $this->_addTaskUpdate($task->id, $task->support_ticket_id ?? null, $msg);
                    $this->Flash->success('Aktualizacja dodana.');
                }

            } elseif ($action === 'delete_time') {
                $entryId = (int)$this->request->getData('entry_id');
                $entry   = $this->fetchTable('TaskTimeEntries')->get($entryId);
                $this->fetchTable('TaskTimeEntries')->delete($entry);
                $this->Flash->success('Wpis czasu usunięty.');
            }

            return $this->redirect(['action' => 'view', $id]);
        }

        $totalMinutes = $Tasks->totalMinutes($id);
        $activeTimer  = $this->fetchTable('TaskTimeEntries')->find()
            ->where(['task_id' => $id, 'user_id' => $userId, 'stopped_at IS' => null])
            ->first();

        $columns    = TasksTable::COLUMNS;
        $statuses   = TasksTable::STATUSES;
        $priorities = TasksTable::PRIORITIES;
        $allLabels  = $this->fetchTable('TaskLabels')->find()->orderAsc('name')->all();

        $this->set(compact('task', 'totalMinutes', 'activeTimer', 'columns', 'statuses', 'priorities', 'allLabels'));
        return null;
    }

    // ── AJAX: przesuń kartę na tablicy ───────────────────────────────────────

    public function move(): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $this->request->allowMethod(['post']);
        $taskId  = (int)$this->request->getData('task_id');
        $column  = (string)$this->request->getData('column');
        $order   = (array)$this->request->getData('order'); // array of task ids in new order

        $Tasks = $this->fetchTable('Tasks');

        if ($taskId && isset(TasksTable::COLUMNS[$column])) {
            $task = $Tasks->get($taskId);
            $task->kanban_column = $column;
            $Tasks->save($task);
        }

        // Zapisz nową kolejność
        foreach ($order as $pos => $tid) {
            $t = $Tasks->find()->where(['id' => (int)$tid])->first();
            if ($t) {
                $t->kanban_position = (int)$pos;
                $Tasks->save($t);
            }
        }

        return $this->response->withType('json')->withStringBody(json_encode(['ok' => true]));
    }

    // ── AJAX: start timer ─────────────────────────────────────────────────────

    public function startTimer(int $id): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $userId   = (string)($identity?->getIdentifier() ?? '');

        $Entries = $this->fetchTable('TaskTimeEntries');

        // Zatrzymaj ewentualny aktywny timer tego usera dla tego taska
        $active = $Entries->find()->where(['task_id' => $id, 'user_id' => $userId, 'stopped_at IS' => null])->first();
        if ($active) {
            $this->_stopEntry($active);
        }

        $entry = $Entries->newEntity([
            'task_id'    => $id,
            'user_id'    => $userId,
            'started_at' => new \DateTime(),
        ]);
        $Entries->save($entry);

        return $this->response->withType('json')->withStringBody(json_encode([
            'ok'         => true,
            'entry_id'   => $entry->id,
            'started_at' => $entry->started_at->format('Y-m-d H:i:s'),
        ]));
    }

    // ── AJAX: stop timer ──────────────────────────────────────────────────────

    public function stopTimer(int $id): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $userId   = (string)($identity?->getIdentifier() ?? '');
        $note     = trim((string)$this->request->getData('note'));

        $Entries = $this->fetchTable('TaskTimeEntries');
        $active  = $Entries->find()->where(['task_id' => $id, 'user_id' => $userId, 'stopped_at IS' => null])->first();

        if (!$active) {
            return $this->response->withType('json')->withStringBody(json_encode(['ok' => false, 'error' => 'Brak aktywnego timera.']));
        }

        $minutes = $this->_stopEntry($active, $note);

        return $this->response->withType('json')->withStringBody(json_encode([
            'ok'      => true,
            'minutes' => $minutes,
        ]));
    }

    // ── Zarządzanie etykietami ────────────────────────────────────────────────

    public function labels(): ?\Cake\Http\Response
    {
        if (($r = $this->requireAdmin()) instanceof \Cake\Http\Response) return $r;

        $TaskLabels = $this->fetchTable('TaskLabels');

        if ($this->request->is('post')) {
            $action = $this->request->getData('_action');

            if ($action === 'add') {
                $label = $TaskLabels->newEntity($this->request->getData());
                if ($TaskLabels->save($label)) {
                    $this->Flash->success('Etykieta dodana.');
                } else {
                    $this->Flash->error('Błąd zapisu etykiety.');
                }
            } elseif ($action === 'delete') {
                $labelId = (int)$this->request->getData('id');
                // Usuń powiązania w tabeli pivot przed usunięciem etykiety
                $this->fetchTable('TaskLabelAssignments')->deleteAll(['task_label_id' => $labelId]);
                $label = $TaskLabels->get($labelId);
                $TaskLabels->delete($label);
                $this->Flash->success('Etykieta usunięta.');
            }

            return $this->redirect(['action' => 'labels']);
        }

        $labels = $TaskLabels->find()->orderAsc('name')->all();
        $this->set(compact('labels'));
        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function _stopEntry(\App\Model\Entity\TaskTimeEntry $entry, string $note = ''): int
    {
        $now     = new \DateTime();
        $minutes = (int)round(($now->getTimestamp() - $entry->started_at->getTimestamp()) / 60);
        $entry->stopped_at = $now;
        $entry->minutes    = max(1, $minutes);
        if ($note !== '') $entry->note = $note;
        $this->fetchTable('TaskTimeEntries')->save($entry);
        return $entry->minutes;
    }

    private function _addTaskUpdate(int $taskId, ?int $ticketId, string $message): void
    {
        $update = $this->fetchTable('TaskUpdates')->newEntity([
            'task_id'           => $taskId,
            'support_ticket_id' => $ticketId,
            'message'           => $message,
        ]);
        $this->fetchTable('TaskUpdates')->save($update);
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;
use Cake\I18n\DateTime;

/**
 * CRM Leads - CRUD + Kanban + timeline aktywnosci.
 */
class LeadsController extends AppController
{
    /**
     * Lista tabelaryczna (jak Excel) z filtrami.
     */
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException(__('Brak firmy w sesji.'));
        }

        $q       = trim((string)$this->request->getQuery('q', ''));
        $stage   = trim((string)$this->request->getQuery('stage', ''));
        $branch  = trim((string)$this->request->getQuery('branch', ''));
        $country = trim((string)$this->request->getQuery('country', ''));
        $mine    = $this->request->getQuery('mine') === '1';
        $userId  = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $query = $Leads->find()
            ->contain([
                'AssignedUser' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'email']);
                },
            ])
            ->where(['Leads.company_id' => $companyId])
            ->orderByDesc('Leads.modified');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'Leads.company_name LIKE'   => $like,
                'Leads.nip LIKE'            => $like,
                'Leads.contact_person LIKE' => $like,
                'Leads.email LIKE'          => $like,
                'Leads.city LIKE'           => $like,
            ]]);
        }
        if ($stage !== '' && in_array($stage, \App\Model\Table\LeadsTable::STAGES, true)) {
            $query->where(['Leads.stage' => $stage]);
        }
        if ($branch !== '') {
            $query->where(['Leads.branch_type' => $branch]);
        }
        if ($country !== '') {
            $query->where(['Leads.country_code' => strtoupper($country)]);
        }
        if ($mine && $userId) {
            $query->where(['Leads.assigned_to_user_id' => $userId]);
        }

        $leads = $query->limit(500)->all();

        // Statystyki nagłówka
        $stats = $Leads->pipelineStats($companyId);
        $totalCount = array_sum(array_map(fn($s) => $s['count'], $stats));
        $avgProb = 0;
        if ($totalCount > 0) {
            $probSum = $Leads->find()
                ->select(['s' => $Leads->find()->func()->sum('probability')])
                ->where(['company_id' => $companyId])
                ->disableHydration()->first();
            $avgProb = (int)round(((float)($probSum['s'] ?? 0)) / $totalCount);
        }

        $this->set(compact('leads', 'stats', 'q', 'stage', 'branch', 'country', 'mine', 'totalCount', 'avgProb'));
    }

    /**
     * Kanban 5 kolumn.
     */
    public function kanban(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $rows = $Leads->find()
            ->contain(['AssignedUser' => function ($q) {
                return $q->select(['id', 'first_name', 'last_name']);
            }])
            ->where(['Leads.company_id' => $companyId, 'Leads.stage !=' => 'lost'])
            ->orderByDesc('Leads.kanban_pinned')
            ->orderByDesc('Leads.modified')
            ->limit(500)
            ->all();

        $columns = ['new' => [], 'contact' => [], 'inquiry' => [], 'offer' => [], 'order' => []];
        foreach ($rows as $lead) {
            if (isset($columns[$lead->stage])) {
                $columns[$lead->stage][] = $lead;
            }
        }
        $stats = $Leads->pipelineStats($companyId);

        $this->set(compact('columns', 'stats'));
    }

    /**
     * Drag&drop w Kanban - zmiana etapu.
     */
    public function kanbanMove(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            return $this->jsonResp(['ok' => false, 'error' => 'not_owned'], 403);
        }
        $newStage = (string)$this->request->getData('stage');
        if (!in_array($newStage, \App\Model\Table\LeadsTable::STAGES, true)) {
            return $this->jsonResp(['ok' => false, 'error' => 'invalid_stage'], 400);
        }
        $oldStage = $lead->stage;
        if ($oldStage === $newStage) {
            return $this->jsonResp(['ok' => true, 'unchanged' => true]);
        }
        $lead->stage = $newStage;
        if ($Leads->save($lead)) {
            $this->fetchTable('LeadActivities')->logSystem(
                $companyId, $lead->id, 'stage_change',
                sprintf('%s → %s', $oldStage, $newStage),
                null,
                ['old' => $oldStage, 'new' => $newStage],
                $userId
            );
            return $this->jsonResp(['ok' => true, 'stage' => $newStage, 'probability' => $lead->probability]);
        }
        return $this->jsonResp(['ok' => false, 'error' => 'save_failed'], 500);
    }

    /**
     * Detal leada + timeline.
     */
    public function view(string $id): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id, contain: [
            'AssignedUser' => function ($q) {
                return $q->select(['id', 'first_name', 'last_name', 'email']);
            },
            'Contractors' => function ($q) {
                return $q->select(['id', 'name', 'nip']);
            },
            'LeadActivities' => function ($q) {
                return $q->contain(['Users' => function ($u) {
                    return $u->select(['id', 'first_name', 'last_name']);
                }])->limit(200);
            },
        ]);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $this->set(compact('lead'));
    }

    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->prepareLeadData($this->request->getData(), $companyId, $userId);
            $lead = $Leads->patchEntity($lead, $data);
            if ($Leads->save($lead)) {
                $this->fetchTable('LeadActivities')->logSystem(
                    $companyId, $lead->id, 'note', __('Lead utworzony'),
                    sprintf('Firma: %s', $lead->company_name),
                    ['source' => $lead->source ?? 'manual'],
                    $userId
                );
                $this->Flash->success(__('Lead dodany.'));
                $this->redirect(['action' => 'view', $lead->id]);
                return;
            }
            $this->Flash->error(__('Błąd zapisu leada.'));
        }

        $users = $this->fetchTable('Users')->find()
            ->where(['company_id' => $companyId])
            ->orderByAsc('last_name')->all();

        $this->set(compact('lead', 'users'));
        $this->set('isEdit', false);
        $this->render('add');
    }

    public function edit(string $id): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        if ($this->request->is('post')) {
            $data = $this->prepareLeadData($this->request->getData(), $companyId, $userId, true);
            unset($data['company_id']);
            $lead = $Leads->patchEntity($lead, $data);
            if ($Leads->save($lead)) {
                $this->Flash->success(__('Lead zaktualizowany.'));
                $this->redirect(['action' => 'view', $lead->id]);
                return;
            }
            $this->Flash->error(__('Błąd zapisu.'));
        }

        $users = $this->fetchTable('Users')->find()
            ->where(['company_id' => $companyId])
            ->orderByAsc('last_name')->all();

        $this->set(compact('lead', 'users'));
        $this->set('isEdit', true);
        $this->render('add');
    }

    public function delete(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $Leads->delete($lead);
        $this->Flash->success(__('Lead usunięty.'));
        $this->redirect(['action' => 'index']);
    }

    /**
     * Dodanie aktywnosci (call/email/note/task) z formularza w view.
     */
    public function activityAdd(string $leadId): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($leadId);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        $Acts = $this->fetchTable('LeadActivities');
        $data = $this->request->getData();
        $type = (string)($data['activity_type'] ?? 'note');
        if (!in_array($type, \App\Model\Table\LeadActivitiesTable::TYPES, true)) {
            $type = 'note';
        }
        $happenedAt = trim((string)($data['happened_at'] ?? ''));
        $dueAt      = trim((string)($data['due_at'] ?? ''));

        $activity = $Acts->newEntity([
            'company_id'    => $companyId,
            'lead_id'       => $lead->id,
            'user_id'       => $userId,
            'activity_type' => $type,
            'subject'       => (string)($data['subject'] ?? '') ?: null,
            'body'          => (string)($data['body'] ?? '') ?: null,
            'duration_min'  => isset($data['duration_min']) && $data['duration_min'] !== ''
                ? (int)$data['duration_min'] : null,
            'happened_at'   => $happenedAt !== '' ? new DateTime($happenedAt) : new DateTime(),
            'due_at'        => $dueAt !== '' ? new DateTime($dueAt) : null,
        ]);
        if ($Acts->save($activity)) {
            $lead->last_contacted_at = $activity->happened_at;
            // Task z terminem => aktualizuj next_action
            if ($type === 'task' && $activity->due_at) {
                $lead->next_action_at = $activity->due_at;
                $lead->next_action_description = $activity->subject ?: $activity->body;
            }
            $Leads->save($lead);
            $this->Flash->success(__('Aktywność dodana.'));
        } else {
            $this->Flash->error(__('Błąd zapisu aktywności.'));
        }
        $this->redirect(['action' => 'view', $lead->id]);
    }

    public function activityDelete(string $activityId): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');
        $isAdmin   = in_array($identity?->get('role'), ['admin', 'manager'], true);

        $Acts = $this->fetchTable('LeadActivities');
        $activity = $Acts->get($activityId);
        if ((string)$activity->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        // Autor lub admin/manager moze usunac
        if (!$isAdmin && (string)$activity->user_id !== (string)$userId) {
            $this->Flash->error(__('Brak uprawnień do usunięcia.'));
            $this->redirect(['action' => 'view', $activity->lead_id]);
            return;
        }
        $leadId = $activity->lead_id;
        $Acts->delete($activity);
        $this->Flash->success(__('Aktywność usunięta.'));
        $this->redirect(['action' => 'view', $leadId]);
    }

    /**
     * Konwersja leada na kontrahenta (contractors) - jesli jeszcze niepodpiety.
     */
    public function convertToContractor(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        if ($lead->contractor_id) {
            $this->Flash->warning(__('Lead już podpięty do kontrahenta.'));
            $this->redirect(['action' => 'view', $lead->id]);
            return;
        }

        $Contractors = $this->fetchTable('Contractors');
        $contractor = $Contractors->newEntity([
            'company_id' => $companyId,
            'name'       => $lead->company_name,
            'nip'        => $lead->nip,
            'country'    => $lead->country_code,
            'zipcode'    => $lead->postal_code,
            'city'       => $lead->city,
            'street'     => $lead->street,
            'phone'      => $lead->phone,
            'email'      => $lead->email,
        ]);
        if ($Contractors->save($contractor)) {
            $lead->contractor_id = $contractor->id;
            $Leads->save($lead);
            $this->Flash->success(__('Kontrahent utworzony i podpięty do leada.'));
        } else {
            $this->Flash->error(__('Błąd tworzenia kontrahenta.'));
        }
        $this->redirect(['action' => 'view', $lead->id]);
    }

    // ================= HELPERS =================

    private function prepareLeadData(array $data, string $companyId, ?string $userId, bool $isEdit = false): array
    {
        $data['company_id'] = $companyId;

        // Normalizacja
        if (isset($data['nip'])) {
            $data['nip'] = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$data['nip'])) ?: null;
        }
        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper(substr((string)$data['country_code'], 0, 2)) ?: null;
        }

        // Checkboxy jako bool
        foreach (['flag_contact', 'flag_inquiry', 'flag_offer', 'flag_order', 'kanban_pinned'] as $b) {
            $data[$b] = !empty($data[$b]);
        }

        // Snooze - date input
        if (empty($data['snooze_until'])) {
            $data['snooze_until'] = null;
        }

        // Assigned to - domyslnie sam sobie przy tworzeniu
        if (!$isEdit && empty($data['assigned_to_user_id'])) {
            $data['assigned_to_user_id'] = $userId;
        }

        // Value_pln - number
        if (isset($data['value_pln']) && $data['value_pln'] === '') {
            $data['value_pln'] = null;
        }

        // Source default
        if (!$isEdit && empty($data['source'])) {
            $data['source'] = 'manual';
        }

        return $data;
    }

    private function jsonResp(array $body, int $status = 200): \Cake\Http\Response
    {
        $this->autoRender = false;
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($body, JSON_UNESCAPED_UNICODE));
    }
}

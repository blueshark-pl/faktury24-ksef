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

        // Sortowanie z whitelist (chroni przed SQL injection)
        $sortMap = [
            'company_name' => 'Leads.company_name',
            'city'         => 'Leads.city',
            'stage'        => 'Leads.stage',
            'probability'  => 'Leads.probability',
            'value_pln'    => 'Leads.value_pln',
            'modified'     => 'Leads.modified',
            'created'      => 'Leads.created',
            'next_action_at' => 'Leads.next_action_at',
        ];
        $sortCol = (string)$this->request->getQuery('sort', 'modified');
        $sortDir = strtolower((string)$this->request->getQuery('dir', 'desc'));
        if (!isset($sortMap[$sortCol])) $sortCol = 'modified';
        if (!in_array($sortDir, ['asc', 'desc'], true)) $sortDir = 'desc';

        $query = $Leads->find()
            ->contain([
                'AssignedUser' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'email']);
                },
            ])
            ->where(['Leads.company_id' => $companyId])
            ->orderBy([$sortMap[$sortCol] => $sortDir]);

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

        // Lista userow do bulk assign
        $users = $this->fetchTable('Users')->find()
            ->where(['company_id' => $companyId])
            ->orderByAsc('last_name')->all();

        $this->set(compact('leads', 'stats', 'q', 'stage', 'branch', 'country', 'mine',
            'totalCount', 'avgProb', 'sortCol', 'sortDir', 'users'));
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

    /**
     * Bulk actions - zaznacz X leadow w tabeli → zmien etap / przypisz / usun.
     * POST /crm/bulk
     * Body: action (change_stage|assign|delete|snooze), ids[], stage?, user_id?, snooze_until?
     */
    public function bulk(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $data = (array)$this->request->getData();
        $action = (string)($data['bulk_action'] ?? '');
        $ids = (array)($data['ids'] ?? []);
        $ids = array_values(array_filter(array_map('strval', $ids), fn($x) => $x !== ''));

        if (empty($ids)) {
            $this->Flash->warning(__('Nie zaznaczono żadnego leada.'));
            $this->redirect(['action' => 'index']);
            return $this->response;
        }
        if (count($ids) > 500) {
            $this->Flash->error(__('Zbyt wiele leadów w jednej operacji (max 500).'));
            $this->redirect(['action' => 'index']);
            return $this->response;
        }

        $Leads = $this->fetchTable('Leads');
        $leads = $Leads->find()
            ->where(['Leads.company_id' => $companyId, 'Leads.id IN' => $ids])
            ->all();

        $count = 0;
        $Acts = $this->fetchTable('LeadActivities');

        switch ($action) {
            case 'change_stage':
                $newStage = (string)($data['stage'] ?? '');
                if (!in_array($newStage, \App\Model\Table\LeadsTable::STAGES, true)) {
                    $this->Flash->error(__('Nieprawidłowy etap.'));
                    $this->redirect(['action' => 'index']);
                    return $this->response;
                }
                foreach ($leads as $lead) {
                    if ($lead->stage === $newStage) continue;
                    $old = $lead->stage;
                    $lead->stage = $newStage;
                    if ($Leads->save($lead)) {
                        $count++;
                        $Acts->logSystem($companyId, $lead->id, 'stage_change',
                            sprintf('%s → %s (bulk)', $old, $newStage),
                            null, ['old' => $old, 'new' => $newStage, 'source' => 'bulk'], $userId);
                    }
                }
                $this->Flash->success(sprintf(__('Zmieniono etap dla %d leadów.'), $count));
                break;

            case 'assign':
                $newUserId = trim((string)($data['assigned_to_user_id'] ?? '')) ?: null;
                foreach ($leads as $lead) {
                    if ((string)$lead->assigned_to_user_id === (string)$newUserId) continue;
                    $lead->assigned_to_user_id = $newUserId;
                    if ($Leads->save($lead)) {
                        $count++;
                        $Acts->logSystem($companyId, $lead->id, 'assignment',
                            $newUserId ? __('Przypisano do usera (bulk)') : __('Odpięto opiekuna (bulk)'),
                            null, ['user_id' => $newUserId, 'source' => 'bulk'], $userId);
                    }
                }
                $this->Flash->success(sprintf(__('Zmieniono opiekuna dla %d leadów.'), $count));
                break;

            case 'snooze':
                $until = trim((string)($data['snooze_until'] ?? ''));
                $date = $until !== '' ? new \Cake\I18n\Date($until) : null;
                foreach ($leads as $lead) {
                    $lead->snooze_until = $date;
                    if ($Leads->save($lead)) $count++;
                }
                $this->Flash->success(sprintf(__('Ustawiono snooze dla %d leadów.'), $count));
                break;

            case 'delete':
                foreach ($leads as $lead) {
                    if ($Leads->delete($lead)) $count++;
                }
                $this->Flash->success(sprintf(__('Usunięto %d leadów.'), $count));
                break;

            default:
                $this->Flash->error(__('Nieznana akcja bulk.'));
        }

        $this->redirect(['action' => 'index']);
        return $this->response;
    }

    /**
     * Dashboard KPI CRM per handlowiec + wykresy.
     */
    public function dashboard(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            throw new BadRequestException();
        }

        $days = (int)$this->request->getQuery('days', '90');
        if (!in_array($days, [30, 90, 180, 365], true)) $days = 90;
        $from = new \Cake\I18n\DateTime("-{$days} days");

        $Leads = $this->fetchTable('Leads');
        $Acts  = $this->fetchTable('LeadActivities');
        $Users = $this->fetchTable('Users');

        // Global KPI
        $stats = $Leads->pipelineStats($companyId);
        $totalActive = 0; $totalValue = 0.0;
        foreach ($stats as $s => $v) {
            if ($s !== 'lost') { $totalActive += $v['count']; $totalValue += $v['value_pln']; }
        }
        $wonCount = (int)($stats['order']['count'] ?? 0);
        $lostCount = (int)($stats['lost']['count'] ?? 0);
        $conversion = ($wonCount + $lostCount) > 0
            ? round($wonCount / ($wonCount + $lostCount) * 100, 1) : 0;

        // Per user - ranking handlowcow
        $rows = $Leads->find()
            ->select([
                'user_id' => 'Leads.assigned_to_user_id',
                'cnt'     => $Leads->find()->func()->count('*'),
                'value'   => $Leads->find()->func()->sum('value_pln'),
            ])
            ->where(['Leads.company_id' => $companyId])
            ->groupBy('Leads.assigned_to_user_id')
            ->disableHydration()
            ->toArray();
        $byUser = [];
        foreach ($rows as $r) {
            if (empty($r['user_id'])) continue;
            $byUser[$r['user_id']] = ['count' => (int)$r['cnt'], 'value' => (float)$r['value']];
        }
        // Won/lost per user
        $wonLost = $Leads->find()
            ->select([
                'user_id' => 'Leads.assigned_to_user_id',
                'stage'   => 'Leads.stage',
                'cnt'     => $Leads->find()->func()->count('*'),
            ])
            ->where(['Leads.company_id' => $companyId, 'Leads.stage IN' => ['order', 'lost']])
            ->groupBy(['Leads.assigned_to_user_id', 'Leads.stage'])
            ->disableHydration()
            ->toArray();
        foreach ($wonLost as $r) {
            $uid = (string)($r['user_id'] ?? '');
            if (!$uid) continue;
            if (!isset($byUser[$uid])) $byUser[$uid] = ['count' => 0, 'value' => 0];
            $byUser[$uid][$r['stage']] = (int)$r['cnt'];
        }

        // Fetch user details + assemble ranking
        $userIds = array_keys($byUser);
        $usersData = [];
        if (!empty($userIds)) {
            $usersData = $Users->find()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where(['Users.id IN' => $userIds])
                ->indexBy('id')
                ->toArray();
        }
        $ranking = [];
        foreach ($byUser as $uid => $s) {
            $u = $usersData[$uid] ?? null;
            $name = $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) : '?';
            $won = (int)($s['order'] ?? 0);
            $lost = (int)($s['lost'] ?? 0);
            $conv = ($won + $lost) > 0 ? round($won / ($won + $lost) * 100, 1) : 0;
            $ranking[] = [
                'user_id' => $uid,
                'name'    => $name ?: ($u->email ?? '—'),
                'total'   => $s['count'],
                'value'   => $s['value'],
                'won'     => $won,
                'lost'    => $lost,
                'conversion' => $conv,
            ];
        }
        usort($ranking, fn($a, $b) => $b['value'] <=> $a['value']);

        // Activity heatmap (ostatnie X dni)
        $activityRows = $Acts->find()
            ->select([
                'day' => 'DATE(LeadActivities.created)',
                'cnt' => $Acts->find()->func()->count('*'),
            ])
            ->where([
                'LeadActivities.company_id' => $companyId,
                'LeadActivities.created >=' => $from,
            ])
            ->groupBy('day')
            ->orderByAsc('day')
            ->disableHydration()
            ->toArray();
        $activityByDay = [];
        foreach ($activityRows as $r) {
            $activityByDay[(string)$r['day']] = (int)$r['cnt'];
        }

        // Nowe leady w okresie (source breakdown)
        $sourceRows = $Leads->find()
            ->select([
                'source' => 'Leads.source',
                'cnt'    => $Leads->find()->func()->count('*'),
            ])
            ->where(['Leads.company_id' => $companyId, 'Leads.created >=' => $from])
            ->groupBy('Leads.source')
            ->disableHydration()
            ->toArray();

        $this->set(compact(
            'stats', 'totalActive', 'totalValue', 'wonCount', 'lostCount', 'conversion',
            'ranking', 'activityByDay', 'sourceRows', 'days'
        ));
    }

    /**
     * Utworz oferte cenowa (route_offer) na podstawie leada.
     * POST /crm/{id}/utworz-oferte
     * Body: price, currency, vat_rate?, payment_days?, valid_until?, subject?, message_body?
     * Tworzy pusty route_plan (bez trasy) + route_offer w statusie 'draft'.
     * Ustawia lead.stage = 'offer' + loguje activity 'offer_sent'.
     */
    public function createOfferFromLead(string $leadId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($leadId);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        $data = (array)$this->request->getData();
        $price = (float)($data['price'] ?? $lead->value_pln ?? 0);
        if ($price <= 0) {
            $this->Flash->error(__('Podaj cenę oferty.'));
            $this->redirect(['action' => 'view', $lead->id]);
            return $this->response;
        }
        $currency = strtoupper((string)($data['currency'] ?? $lead->currency ?? 'PLN'));
        $vatRate  = isset($data['vat_rate']) && $data['vat_rate'] !== '' ? (int)$data['vat_rate'] : 23;
        $paymentDays = isset($data['payment_days']) && $data['payment_days'] !== '' ? (int)$data['payment_days'] : 30;
        $validUntil = trim((string)($data['valid_until'] ?? ''));
        $subject = trim((string)($data['subject'] ?? '')) ?: __('Oferta transportowa dla {0}', $lead->company_name);
        $message = trim((string)($data['message_body'] ?? ''));
        $sentToEmail = trim((string)($data['sent_to_email'] ?? $lead->email ?? ''));
        $sentToName  = trim((string)($data['sent_to_name']  ?? $lead->contact_person ?? ''));

        if ($sentToEmail === '' || !filter_var($sentToEmail, FILTER_VALIDATE_EMAIL)) {
            $this->Flash->error(__('Brak lub nieprawidłowy adres email odbiorcy.'));
            $this->redirect(['action' => 'view', $lead->id]);
            return $this->response;
        }

        try {
            $RP = $this->fetchTable('RoutePlans');
            $plan = $RP->newEntity([
                'id'             => \Cake\Utility\Text::uuid(),
                'company_id'     => $companyId,
                'author_user_id' => $userId,
                'contractor_id'  => $lead->contractor_id,
                'name'           => sprintf(__('Oferta dla %s'), $lead->company_name),
                'status'         => 'offered',
                'currency'       => $currency,
                'suggested_price' => $price,
            ]);
            if (!$RP->save($plan)) {
                $this->Flash->error(__('Błąd tworzenia planu trasy.'));
                $this->redirect(['action' => 'view', $lead->id]);
                return $this->response;
            }

            $RO = $this->fetchTable('RouteOffers');
            $offer = $RO->newEntity([
                'id'              => \Cake\Utility\Text::uuid(),
                'company_id'      => $companyId,
                'route_plan_id'   => $plan->id,
                'contractor_id'   => $lead->contractor_id,
                'sent_to_email'   => $sentToEmail,
                'sent_to_name'    => $sentToName,
                'subject'         => $subject,
                'message_body'    => $message,
                'price'           => $price,
                'currency'        => $currency,
                'vat_rate'        => $vatRate,
                'payment_days'    => $paymentDays,
                'valid_until'     => $validUntil !== '' ? $validUntil : null,
                'access_token'    => bin2hex(random_bytes(24)),
                'status'          => 'draft',
                'created_by_user_id' => $userId,
            ]);
            if (!$RO->save($offer)) {
                $this->Flash->error(__('Błąd zapisu oferty.'));
                $this->redirect(['action' => 'view', $lead->id]);
                return $this->response;
            }

            // Zmien stage leada na 'offer' (jesli nie jest juz dalej)
            if (in_array($lead->stage, ['new', 'contact', 'inquiry'], true)) {
                $lead->stage = 'offer';
                $lead->value_pln = $price;
                $lead->currency = $currency;
                $Leads->save($lead);
            }

            // Log activity
            $this->fetchTable('LeadActivities')->logSystem(
                $companyId, $lead->id, 'offer_sent',
                sprintf(__('Oferta cenowa: %s %s'), number_format($price, 2, ',', ' '), $currency),
                sprintf(__('Utworzono ofertę #%s dla %s (%s)'), substr($offer->id, 0, 8), $sentToName, $sentToEmail),
                ['route_offer_id' => $offer->id, 'price' => $price, 'currency' => $currency],
                $userId
            );

            $this->Flash->success(__('Oferta utworzona. Przejdź do „Oferty" żeby wysłać ją do klienta.'));
            $this->redirect(['controller' => 'RouteOffers', 'action' => 'view', $offer->id]);
            return $this->response;
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('LeadsController::createOfferFromLead failed: ' . $e->getMessage());
            $this->Flash->error(__('Błąd: {0}', $e->getMessage()));
            $this->redirect(['action' => 'view', $lead->id]);
            return $this->response;
        }
    }

    /**
     * Widok "Moje zadania" - lista task-activities przypisanych do usera
     * (activity_type=task, is_done=false, due_at IN ostatnie 30 dni / kolejne 30 dni).
     */
    public function myTasks(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $onlyMine = $this->request->getQuery('all') !== '1';
        $range    = (int)$this->request->getQuery('days', '14');
        if (!in_array($range, [7, 14, 30, 60, 90], true)) $range = 14;

        $Acts = $this->fetchTable('LeadActivities');
        $from = new \Cake\I18n\DateTime('-30 days');
        $to   = new \Cake\I18n\DateTime('+' . $range . ' days');

        $query = $Acts->find()
            ->contain([
                'Leads' => function ($q) {
                    return $q->select(['id', 'company_name', 'stage', 'probability', 'value_pln',
                        'nip', 'city', 'country_code', 'assigned_to_user_id']);
                },
                'Users' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name']);
                },
            ])
            ->where([
                'LeadActivities.company_id'    => $companyId,
                'LeadActivities.activity_type' => 'task',
                'LeadActivities.is_done'       => false,
                'LeadActivities.due_at IS NOT' => null,
                'LeadActivities.due_at >='     => $from,
                'LeadActivities.due_at <='     => $to,
            ])
            ->orderByAsc('LeadActivities.due_at');

        if ($onlyMine && $userId) {
            $query->where(['OR' => [
                'LeadActivities.user_id' => $userId,
                'Leads.assigned_to_user_id' => $userId,
            ]]);
        }

        $tasks = $query->limit(500)->all();

        // Rowniez leady z next_action_at (jesli nie ma dedykowanego task)
        $Leads = $this->fetchTable('Leads');
        $nextActionQuery = $Leads->find()
            ->contain(['AssignedUser' => function ($q) { return $q->select(['id', 'first_name', 'last_name']); }])
            ->where([
                'Leads.company_id' => $companyId,
                'Leads.next_action_at IS NOT' => null,
                'Leads.next_action_at >=' => $from,
                'Leads.next_action_at <=' => $to,
            ])
            ->orderByAsc('Leads.next_action_at');
        if ($onlyMine && $userId) {
            $nextActionQuery->where(['Leads.assigned_to_user_id' => $userId]);
        }
        $nextActions = $nextActionQuery->limit(200)->all();

        // Statystyki
        $today = new \Cake\I18n\DateTime('today');
        $tomorrow = new \Cake\I18n\DateTime('+1 day');
        $overdueCnt = 0;
        $todayCnt = 0;
        $upcomingCnt = 0;
        foreach ($tasks as $t) {
            if ($t->due_at < $today) $overdueCnt++;
            elseif ($t->due_at < $tomorrow) $todayCnt++;
            else $upcomingCnt++;
        }

        $this->set(compact('tasks', 'nextActions', 'onlyMine', 'range', 'overdueCnt', 'todayCnt', 'upcomingCnt'));
    }

    /**
     * Oznacz task jako wykonany.
     */
    public function taskDone(string $activityId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Acts = $this->fetchTable('LeadActivities');
        $act = $Acts->get($activityId);
        if ((string)$act->company_id !== (string)$companyId) {
            return $this->jsonResp(['ok' => false, 'error' => 'not_owned'], 403);
        }
        $act->is_done = true;
        $act->done_at = new \Cake\I18n\DateTime();
        if ($Acts->save($act)) {
            return $this->jsonResp(['ok' => true]);
        }
        return $this->jsonResp(['ok' => false, 'error' => 'save_failed'], 500);
    }

    /**
     * Import CSV lead-ow z arkusza klienta.
     * Kolumny (case-insensitive): company_name (lub 'nazwa firmy'), nip, country_code,
     * postal_code, city, street, contact_person, phone, email, branch_type, note,
     * flag_contact/inquiry/offer/order (checkboxy: 1/yes/tak/x/true = true), probability, stage, value_pln.
     */
    public function importCsv(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $preview = null;
        $errors = [];
        $importedCount = 0;
        $errorRows = [];
        $content = '';
        $csvText = '';

        if ($this->request->is('post')) {
            $upload = $this->request->getUploadedFile('csv');
            $isConfirm = (bool)$this->request->getData('confirm');
            $csvText = (string)$this->request->getData('csv_text');

            $rows = [];
            if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
                if ($upload->getSize() > 5 * 1024 * 1024) {
                    $errors[] = __('Plik za duży (max 5 MB)');
                } else {
                    $content = (string)$upload->getStream()->getContents();
                    $rows = $this->parseCsv($content);
                }
            } elseif ($isConfirm && $csvText !== '') {
                $rows = $this->parseCsv($csvText);
            }

            if (empty($errors) && empty($rows)) {
                $errors[] = __('CSV jest pusty lub niepoprawny');
            }

            if (!empty($rows)) {
                $Leads = $this->fetchTable('Leads');
                foreach ($rows as $idx => $r) {
                    $mapped = $this->mapCsvRowToLead($r);
                    if (empty(trim((string)($mapped['company_name'] ?? '')))) {
                        $errorRows[] = ['row' => $idx + 2, 'error' => __('Brak nazwy firmy'), 'data' => $r];
                        continue;
                    }
                    if (!$isConfirm) continue;

                    try {
                        $data = $this->prepareLeadData($mapped, $companyId, $userId);
                        $data['source'] = 'import_csv';
                        // Dedup po NIP (opcjonalnie - tylko warning w preview)
                        if (!empty($data['nip'])) {
                            $exists = $Leads->find()
                                ->where(['company_id' => $companyId, 'nip' => $data['nip']])
                                ->count();
                            if ($exists > 0) {
                                $errorRows[] = ['row' => $idx + 2, 'error' => __('Duplikat NIP: ') . $data['nip'], 'data' => $r];
                                continue;
                            }
                        }
                        $lead = $Leads->newEntity($data);
                        if ($Leads->save($lead)) {
                            $importedCount++;
                        } else {
                            $errorRows[] = ['row' => $idx + 2, 'error' => __('Walidacja: ') . json_encode($lead->getErrors(), JSON_UNESCAPED_UNICODE), 'data' => $r];
                        }
                    } catch (\Throwable $e) {
                        $errorRows[] = ['row' => $idx + 2, 'error' => $e->getMessage(), 'data' => $r];
                    }
                }

                if ($isConfirm) {
                    $msg = sprintf(__('%d leadów zaimportowanych'), $importedCount);
                    if (!empty($errorRows)) $msg .= ', ' . count($errorRows) . ' ' . __('błędów');
                    $this->Flash->success($msg);
                    if ($importedCount > 0) {
                        $this->redirect(['action' => 'index']);
                        return;
                    }
                } else {
                    $preview = array_map([$this, 'mapCsvRowToLead'], $rows);
                    $this->set('csvText', $content ?: $csvText);
                }
            }
        }

        $this->set(compact('preview', 'errors', 'errorRows', 'importedCount'));
    }

    /**
     * Szablon CSV do pobrania.
     */
    public function importCsvTemplate(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;

        $header = [
            'company_name', 'nip', 'country_code', 'postal_code', 'city', 'street',
            'contact_person', 'contact_role', 'phone', 'email', 'contact_channel',
            'branch_type', 'stage', 'probability', 'value_pln',
            'flag_contact', 'flag_inquiry', 'flag_offer', 'flag_order',
            'note', 'next_action_description',
        ];
        $example = [
            'SILESIAN FLOUR Sp. z o.o.', '8971834912', 'PL', '57-220', 'Ziębice', 'Przemysłowa 34',
            'Daniel Wachowicz', 'Dyrektor sprzedaży', '+48 663 877 760', 'daniel.wachowicz@sgrain.pl', 'phone',
            'road', 'offer', '75', '24500',
            '1', '1', '1', '0',
            'Klient B2B, silny partner na trasie PL→DE', 'Follow-up oferty w piątek 09:00',
        ];
        $csv = implode(';', $header) . "\n" . implode(';', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $example)) . "\n";
        return $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="crm-leads-template.csv"')
            ->withStringBody("\xEF\xBB\xBF" . $csv);
    }

    /**
     * GUS lookup - proxy do ContractorsController::gusLookup.
     * Zwraca danymi w formacie leada (dopasowane pola).
     */
    public function gusLookupJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $nip = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$this->request->getData('nip', '')));
        if (strlen($nip) < 10) {
            return $this->jsonResp(['ok' => false, 'error' => __('Nieprawidłowy NIP')], 400);
        }

        // Dedup check
        $Leads = $this->fetchTable('Leads');
        $existing = $Leads->find()
            ->select(['id', 'company_name', 'stage'])
            ->where(['company_id' => $companyId, 'nip' => $nip])
            ->first();
        $duplicate = null;
        if ($existing) {
            $duplicate = [
                'id' => $existing->id,
                'company_name' => $existing->company_name,
                'stage' => $existing->stage,
                'view_url' => $this->request->getAttribute('webroot') . 'crm/view/' . $existing->id,
            ];
        }

        // GUS query - reuse istniejacego GUS service (jesli dostepny)
        $gusData = null;
        try {
            if (class_exists('\App\Service\GusService')) {
                $svc = new \App\Service\GusService();
                if (method_exists($svc, 'lookupByNip')) {
                    $gusData = $svc->lookupByNip($nip);
                }
            }
            // Fallback: try Contractors table
            if (!$gusData) {
                $Contractors = $this->fetchTable('Contractors');
                $existing_c = $Contractors->find()
                    ->where(['nip' => $nip])
                    ->first();
                if ($existing_c) {
                    $gusData = [
                        'name'     => $existing_c->name,
                        'street'   => $existing_c->street ?? null,
                        'zipcode'  => $existing_c->zipcode ?? null,
                        'city'     => $existing_c->city ?? null,
                        'country'  => $existing_c->country ?? null,
                        'phone'    => $existing_c->phone ?? null,
                        'email'    => $existing_c->email ?? null,
                        'source'   => 'contractors_table',
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('CRM GUS lookup failed: ' . $e->getMessage());
        }

        return $this->jsonResp([
            'ok'        => true,
            'nip'       => $nip,
            'duplicate' => $duplicate,
            'company'   => $gusData ? [
                'company_name' => $gusData['name'] ?? null,
                'country_code' => strtoupper((string)($gusData['country'] ?? 'PL')),
                'postal_code'  => $gusData['zipcode'] ?? null,
                'city'         => $gusData['city'] ?? null,
                'street'       => $gusData['street'] ?? null,
                'phone'        => $gusData['phone'] ?? null,
                'email'        => $gusData['email'] ?? null,
            ] : null,
        ]);
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

    /**
     * Parser CSV - obsluguje separatory , ; \t; encoding UTF-8/Win-1250; BOM strip.
     */
    private function parseCsv(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        $content = ltrim($content, "\xEF\xBB\xBF");
        // Encoding detection best-effort - niektore prod PHP-e maja okrojony mbstring
        // gdzie CP1250/Windows-1250 rzucaja ValueError. Sprawdzamy UTF-8 przez regex
        // (preg z /u zwraca false na zlym UTF-8, nie krashuje), potem iconv.
        if (preg_match('//u', $content) !== 1) {
            if (function_exists('iconv')) {
                $converted = @iconv('CP1250', 'UTF-8//IGNORE', $content);
                if ($converted !== false && $converted !== '') {
                    $content = $converted;
                }
            }
        }
        $lines = explode("\n", $content);
        if (count($lines) < 2) return [];

        $firstLine = $lines[0];
        $sep = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) $sep = ';';
        elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) $sep = "\t";

        $header = str_getcsv($firstLine, $sep);
        $header = array_map(function ($h) { return trim(strtolower(trim($h)), '"'); }, $header);

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            $cells = str_getcsv($line, $sep);
            if (count($cells) < count($header)) {
                $cells = array_pad($cells, count($header), '');
            }
            $row = [];
            foreach ($header as $j => $col) {
                $row[$col] = isset($cells[$j]) ? trim((string)$cells[$j]) : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Mapa naglowkow CSV -> pola leada.
     * Akceptuje polskie i angielskie naglowki (case-insensitive).
     */
    private function mapCsvRowToLead(array $r): array
    {
        // Aliasy naglowkow (klucz = pole w bazie, wartosc = mozliwe naglowki w CSV)
        $aliases = [
            'company_name'   => ['company_name', 'nazwa', 'nazwa firmy', 'firma'],
            'nip'            => ['nip', 'vat', 'vat_id'],
            'country_code'   => ['country_code', 'kraj', 'country'],
            'postal_code'    => ['postal_code', 'kod', 'kod pocztowy', 'zip'],
            'city'           => ['city', 'miasto'],
            'street'         => ['street', 'ulica', 'adres'],
            'contact_person' => ['contact_person', 'osoba kontaktowa', 'kontakt', 'osoba'],
            'contact_role'   => ['contact_role', 'stanowisko', 'rola'],
            'phone'          => ['phone', 'telefon', 'tel', 'numer tel.', 'numer telefonu'],
            'email'          => ['email', 'e-mail', 'adres mailowy', 'mail'],
            'contact_channel'=> ['contact_channel', 'rodzaj kontaktu', 'kanal'],
            'branch_type'    => ['branch_type', 'galaz', 'gałąź', 'galaz transportu', 'gałąź transportu'],
            'stage'          => ['stage', 'etap'],
            'probability'    => ['probability', 'skutecznosc', 'skuteczność', 'skutecznosc %', 'skuteczność %'],
            'value_pln'      => ['value_pln', 'wartosc', 'wartość', 'kwota'],
            'flag_contact'   => ['flag_contact', 'kontakt (checkbox)', 'k'],
            'flag_inquiry'   => ['flag_inquiry', 'zapytanie (checkbox)', 'z'],
            'flag_offer'     => ['flag_offer', 'oferta (checkbox)', 'o'],
            'flag_order'     => ['flag_order', 'zlecenie (checkbox)', 'zl'],
            'note'           => ['note', 'notatka', 'uwagi'],
            'next_action_description' => ['next_action_description', 'nastepna akcja', 'następna akcja'],
        ];

        $out = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $n) {
                $key = strtolower($n);
                if (isset($r[$key]) && $r[$key] !== '') {
                    $out[$field] = $r[$key];
                    break;
                }
            }
        }

        // Normalizacja checkboxow
        foreach (['flag_contact', 'flag_inquiry', 'flag_offer', 'flag_order'] as $b) {
            if (isset($out[$b])) {
                $v = strtolower(trim((string)$out[$b]));
                $out[$b] = in_array($v, ['1', 'true', 'tak', 'yes', 'x', '✓', 'v'], true);
            }
        }

        // Probability - clamp 0-100
        if (isset($out['probability'])) {
            $out['probability'] = max(0, min(100, (int)$out['probability']));
        }
        if (isset($out['value_pln'])) {
            $out['value_pln'] = str_replace([' ', ','], ['', '.'], (string)$out['value_pln']);
        }

        return $out;
    }
}

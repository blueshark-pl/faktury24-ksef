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
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Publiczny formularz kontaktowy dostepny bez logowania
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated(['publicForm', 'publicFormThanks']);
        }
    }

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
            'postal_code'  => 'Leads.postal_code',
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
                    return $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']);
                },
            ])
            ->where(['Leads.company_id' => $companyId])
            ->orderBy([$sortMap[$sortCol] => $sortDir]);

        // FALA extras: filter archived (?archived=hide|show|only, default hide)
        $archivedFilter = (string)$this->request->getQuery('archived', 'hide');
        try {
            $schema = $Leads->getSchema();
            if (in_array('archived_at', $schema->columns(), true)) {
                if ($archivedFilter === 'only') {
                    $query->where(['Leads.archived_at IS NOT' => null]);
                } elseif ($archivedFilter === 'show') {
                    // pokaz wszystko - bez filtra
                } else {
                    $query->where(['Leads.archived_at IS' => null]);
                }
            }
        } catch (\Throwable $e) {}
        $this->set('archivedFilter', $archivedFilter);

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
        // FALA extras: postal_code, industry, vehicle_type filtry
        $postal = trim((string)$this->request->getQuery('postal', ''));
        if ($postal !== '') {
            $query->where(['Leads.postal_code LIKE' => $postal . '%']);
        }
        $industryId = trim((string)$this->request->getQuery('industry', ''));
        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            $tables = $conn->getSchemaCollection()->listTables();
            $hasIndustry = in_array('lead_industries', $tables, true);
            $hasVehicleType = in_array('lead_vehicle_types', $tables, true);
        } catch (\Throwable $e) { $hasIndustry = $hasVehicleType = false; }

        if ($industryId !== '' && $hasIndustry) {
            $query->where(['Leads.industry_id' => $industryId]);
        }
        $vehicleTypeId = trim((string)$this->request->getQuery('vehicle_type', ''));
        if ($vehicleTypeId !== '' && $hasVehicleType) {
            $query->innerJoinWith('LeadVehicleTypes', function ($q) use ($vehicleTypeId) {
                return $q->where(['LeadVehicleTypes.id' => $vehicleTypeId]);
            });
        }

        // Contain dla wyswietlania kolumn
        $indexContain = [];
        if ($hasIndustry) $indexContain['LeadIndustries'] = [];
        if ($hasVehicleType) $indexContain['LeadVehicleTypes'] = [];
        if (!empty($indexContain)) $query->contain(array_merge(['AssignedUser' => function ($q) {
            return $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']);
        }], $indexContain));

        $leads = $query->limit(500)->all();

        // Dla filter dropdownow
        $industriesForFilter = [];
        $vehicleTypesForFilter = [];
        if ($hasIndustry) {
            try {
                $industriesForFilter = $this->fetchTable('LeadIndustries')->find()
                    ->where(['company_id' => $companyId])->orderByAsc('sort_order')->orderByAsc('name')
                    ->all()->toArray();
            } catch (\Throwable $e) {}
        }
        if ($hasVehicleType) {
            try {
                $vehicleTypesForFilter = $this->fetchTable('LeadVehicleTypes')->find()
                    ->where(['company_id' => $companyId])->orderByAsc('sort_order')->orderByAsc('name')
                    ->all()->toArray();
            } catch (\Throwable $e) {}
        }
        $this->set(compact('industriesForFilter', 'vehicleTypesForFilter', 'postal', 'industryId', 'vehicleTypeId'));

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
        $userId    = $identity?->get('id');

        // Filter tylko moje (?mine=1)
        $onlyMine = $this->request->getQuery('mine') === '1';
        // FALA extras: filter tylko wolne (?free=1) - leady bez opiekuna
        $onlyFree = $this->request->getQuery('free') === '1';

        // FALA 21: Multi-pipeline - default 'spot' (backward compat z URL bez ?pipeline)
        $pipelineType = trim((string)$this->request->getQuery('pipeline', 'spot'));
        if (!in_array($pipelineType, \App\Model\Table\LeadsTable::PIPELINE_TYPES, true)) {
            $pipelineType = 'spot';
        }
        $stages = \App\Model\Table\LeadsTable::stagesForPipeline($pipelineType);
        // Wykluczamy 'lost'/'churned' z Kanban (widoczne w liscie index z filtrem)
        $displayStages = array_filter($stages, fn($s) => !in_array($s, ['lost', 'churned'], true));

        $Leads = $this->fetchTable('Leads');

        // FALA 21 fix: sprawdz czy pipeline_type kolumna istnieje - fallback do
        // starego zachowania (jeden pipeline dla wszystkich) gdy migracja nie odpalona
        $hasPipelineColumn = false;
        try {
            $schema = $Leads->getSchema();
            $hasPipelineColumn = in_array('pipeline_type', $schema->columns(), true);
        } catch (\Throwable $e) {}

        // FALA extras: 'disqualified' widoczne w Kanban jako ostatnia kolumna
        // (uzytkownik moze tam przeciagac zdyskwalifikowanych).
        // 'lost'/'churned' NIE wyswietlane (historia, poza pipeline).
        $baseWhere = [
            'Leads.company_id' => $companyId,
            'Leads.stage NOT IN' => ['lost', 'churned'],
        ];
        if ($hasPipelineColumn) {
            $baseWhere['Leads.pipeline_type'] = $pipelineType;
        }
        if ($onlyMine && $userId) {
            $baseWhere['Leads.assigned_to_user_id'] = $userId;
        }
        if ($onlyFree) {
            // Leady bez opiekuna (assigned_to_user_id IS NULL) - "wolne do wziecia"
            $baseWhere['Leads.assigned_to_user_id IS'] = null;
        }
        // FALA extras: domyslnie chowamy archived (chyba ze ?archived=1)
        try {
            $schema = $Leads->getSchema();
            if (in_array('archived_at', $schema->columns(), true)) {
                if ($this->request->getQuery('archived') !== '1') {
                    $baseWhere['Leads.archived_at IS'] = null;
                }
            }
        } catch (\Throwable $e) {}

        $containKanban = ['AssignedUser' => function ($q) {
            return $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']);
        }];
        // FALA extras: user labels na kartach Kanban - jesli tabela lead_labels ISTNIEJE w bazie
        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            $tables = $conn->getSchemaCollection()->listTables();
            if (in_array('lead_labels', $tables, true) && in_array('leads_lead_labels', $tables, true)) {
                $containKanban['LeadLabels'] = [];
            }
        } catch (\Throwable $e) {}

        $rows = $Leads->find()
            ->contain($containKanban)
            ->where($baseWhere)
            ->orderByDesc('Leads.kanban_pinned')
            ->orderByDesc('Leads.modified')
            ->limit(500)
            ->all();

        $columns = [];
        foreach ($displayStages as $s) $columns[$s] = [];
        foreach ($rows as $lead) {
            if (isset($columns[$lead->stage])) {
                $columns[$lead->stage][] = $lead;
            }
        }

        // Liczniki per pipeline dla tabs (tylko gdy kolumna istnieje)
        $pipelineCounts = [];
        if ($hasPipelineColumn) {
            foreach (\App\Model\Table\LeadsTable::PIPELINE_TYPES as $pt) {
                $pipelineCounts[$pt] = $Leads->find()->where([
                    'company_id' => $companyId,
                    'pipeline_type' => $pt,
                    'stage NOT IN' => ['lost', 'churned'],
                ])->count();
            }
        } else {
            // Fallback: pokaz tylko tab 'spot' z pelnym countem
            foreach (\App\Model\Table\LeadsTable::PIPELINE_TYPES as $pt) {
                $pipelineCounts[$pt] = $pt === 'spot' ? $Leads->find()->where([
                    'company_id' => $companyId,
                    'stage NOT IN' => ['lost', 'churned'],
                ])->count() : 0;
            }
        }

        $stats = $Leads->pipelineStats($companyId);
        $stageLabels = $this->stageLabelsForPipeline($pipelineType);

        $this->set(compact('columns', 'stats', 'pipelineType', 'pipelineCounts',
            'displayStages', 'stageLabels', 'onlyMine', 'onlyFree'));
    }

    /**
     * FALA 21: Ludzkie labelki dla stages danego pipeline (do wyswietlania w naglowkach Kanban).
     */
    private function stageLabelsForPipeline(string $pipelineType): array
    {
        $labels = [
            // spot (legacy)
            'new' => 'Nowy', 'contact' => 'Kontakt', 'inquiry' => 'Zapytanie',
            'offer' => 'Oferta', 'order' => 'Zlecenie', 'lost' => 'Utracone',
            // long_term
            'qualification' => 'Kwalifikacja', 'proposal' => 'Propozycja',
            'negotiation' => 'Negocjacje', 'contract' => 'Kontrakt', 'active' => 'Aktywny',
            // recurring
            'prospect' => 'Prospekt', 'trial' => 'Trial', 'churned' => 'Churned',
            // FALA extras
            'disqualified' => 'Zdyskwalifikowany',
        ];
        return $labels;
    }

    /**
     * Drag&drop w Kanban - zmiana etapu.
     */
    /**
     * FALA extras: Trello-style peek modal - lekki JSON dla popup w Kanban.
     * GET /crm/peek/{id}.json
     */
    /**
     * FALA extras: Lista wszystkich etykiet firmy dla picker'a (dropdown w modal).
     * GET /crm/labels-all.json
     */
    /**
     * FALA extras: Inline create nowej etykiety z modal peek (JSON).
     * POST /crm/labels/create-inline - body: name, color
     * Zwraca JSON z nowa etykieta - klient auto-assign do leada.
     */
    /**
     * FALA extras: Kolejka @mentions dla zalogowanego usera.
     * GET /crm/wspomniano-mnie
     */
    public function myMentions(): void
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $userId = $identity?->get('id');
        $companyId = $identity?->get('company_id');

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('lead_activity_mentions', $conn->getSchemaCollection()->listTables(), true)) {
                $this->Flash->error(__('Wspomnienia wymagaja migracji CreateLeadActivityMentions.'));
                $this->redirect(['controller' => 'CrmAdmin', 'action' => 'tools']);
                return;
            }
        } catch (\Throwable $e) {}

        $M = $this->fetchTable('LeadActivityMentions');
        $showRead = $this->request->getQuery('all') === '1';
        $where = [
            'LeadActivityMentions.mentioned_user_id' => $userId,
            'LeadActivityMentions.company_id' => $companyId,
        ];
        if (!$showRead) $where['LeadActivityMentions.seen_at IS'] = null;

        $mentions = $M->find()
            ->where($where)
            ->contain([
                'LeadActivities',
                'Leads' => function ($q) { return $q->select(['id', 'company_name', 'stage', 'nip']); },
                'ByUser' => function ($q) { return $q->select(['id', 'first_name', 'last_name', 'avatar']); },
            ])
            ->orderByDesc('LeadActivityMentions.created')
            ->limit(200)
            ->all()->toArray();

        // Auto mark as seen (opcjonalnie na click - tu na render, prostsze)
        if (!$showRead && !empty($mentions)) {
            $ids = array_map(fn($m) => (string)$m->id, $mentions);
            try {
                $M->updateAll(['seen_at' => new \Cake\I18n\DateTime()], ['id IN' => $ids]);
            } catch (\Throwable $e) {}
        }

        $this->set(compact('mentions', 'showRead'));
    }

    public function labelCreateInlineJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $this->response = $this->response->withType('application/json');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withStatus($code)->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('lead_labels', $conn->getSchemaCollection()->listTables(), true)) {
                return $json(['ok' => false, 'error' => 'Migracja lead_labels nie odpalona'], 400);
            }
        } catch (\Throwable $e) {}

        $name = trim((string)$this->request->getData('name'));
        $color = trim((string)$this->request->getData('color', '#94C81F'));
        if ($name === '') return $json(['ok' => false, 'error' => 'Nazwa wymagana'], 400);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#94C81F';

        try {
            $L = $this->fetchTable('LeadLabels');
            $entity = $L->newEntity([
                'id' => \Cake\Utility\Text::uuid(),
                'company_id' => $companyId,
                'name' => mb_substr($name, 0, 60),
                'color' => strtoupper($color),
                'sort_order' => 100,
            ]);
            if (!$L->save($entity)) {
                return $json(['ok' => false, 'error' => 'Zapis fail', 'details' => $entity->getErrors()], 500);
            }
            return $json(['ok' => true, 'label' => [
                'id' => (string)$entity->id, 'name' => $entity->name, 'color' => $entity->color,
            ]]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function labelsAllJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $this->response = $this->response->withType('application/json');

        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            if (!in_array('lead_labels', $conn->getSchemaCollection()->listTables(), true)) {
                return $this->response->withStringBody(json_encode(['ok' => true, 'labels' => []]));
            }
            $L = $this->fetchTable('LeadLabels');
            $labels = $L->find()
                ->where(['company_id' => $companyId])
                ->orderByAsc('sort_order')->orderByAsc('name')
                ->all()->toArray();
            $out = array_map(fn($l) => ['id' => (string)$l->id, 'name' => $l->name, 'color' => $l->color], $labels);
            return $this->response->withStringBody(json_encode(['ok' => true, 'labels' => $out], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->response->withStringBody(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        }
    }

    public function peekJson(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $Leads = $this->fetchTable('Leads');
            $containSpec = [
                'AssignedUser' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']);
                },
                'LeadActivities' => function ($q) {
                    return $q->orderByDesc('happened_at')->orderByDesc('created')->limit(10);
                },
            ];
            // Opcjonalnie: labels + attachments - sprawdz czy tabele w DB (nie tylko Table klasa)
            try {
                $conn = \Cake\Datasource\ConnectionManager::get('default');
                $tables = $conn->getSchemaCollection()->listTables();
                if (in_array('lead_labels', $tables, true) && in_array('leads_lead_labels', $tables, true)) {
                    $containSpec['LeadLabels'] = [];
                }
                if (in_array('lead_attachments', $tables, true)) {
                    $containSpec['LeadAttachments'] = [];
                }
            } catch (\Throwable $e) {}

            $lead = $Leads->get($id, ['contain' => $containSpec]);
            if ((string)$lead->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
            }

            $activities = $lead->lead_activities ?? [];
            $lastAct = $activities[0] ?? null;
            $u = $lead->assigned_user ?? null;
            $userName = $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) : null;

            return $json(['ok' => true, 'lead' => [
                'id' => (string)$lead->id,
                'company_name' => $lead->company_name,
                'nip' => $lead->nip,
                'country_code' => $lead->country_code,
                'postal_code' => $lead->postal_code,
                'city' => $lead->city,
                'street' => $lead->street,
                'branch_type' => $lead->branch_type,
                'stage' => $lead->stage,
                'probability' => (int)$lead->probability,
                'value_pln' => $lead->value_pln,
                'contact_person' => $lead->contact_person,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'note' => $lead->note,
                'days_in_stage' => $lead->getDaysInStage(),
                'assigned_user' => $u ? [
                    'name' => $userName,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                ] : null,
                'last_activity' => $lastAct ? [
                    'type' => $lastAct->activity_type,
                    'subject' => $lastAct->subject,
                    'date' => ($lastAct->happened_at ?? $lastAct->created)?->format('d.m.Y H:i'),
                ] : null,
                'activities' => array_map(fn($a) => [
                    'id' => (string)$a->id,
                    'type' => $a->activity_type,
                    'subject' => $a->subject,
                    'body' => $a->body ? mb_substr((string)$a->body, 0, 200) : null,
                    'date' => ($a->happened_at ?? $a->created)?->format('d.m.Y H:i'),
                ], $activities),
                'labels' => array_map(fn($l) => [
                    'id' => (string)$l->id, 'name' => $l->name, 'color' => $l->color,
                ], $lead->lead_labels ?? []),
                'attachments' => array_map(fn($a) => [
                    'id' => (string)$a->id, 'filename' => $a->filename, 'mime' => $a->mime,
                    'size' => (int)$a->size, 'url' => '/' . $a->path,
                ], $lead->lead_attachments ?? []),
            ]]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

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
        // FALA 21+extras: waliduj po UNII wszystkich pipeline stages (nie tylko legacy STAGES)
        $allStages = array_unique(array_merge(...array_values(\App\Model\Table\LeadsTable::PIPELINE_STAGES)));
        if (!in_array($newStage, $allStages, true)) {
            return $this->jsonResp(['ok' => false, 'error' => 'invalid_stage', 'allowed' => $allStages], 400);
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
        $viewContain = [
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
        ];
        // FALA extras: LeadLabels + LeadAttachments jesli tabele istnieja
        try {
            $conn = \Cake\Datasource\ConnectionManager::get('default');
            $tables = $conn->getSchemaCollection()->listTables();
            if (in_array('lead_labels', $tables, true)) $viewContain['LeadLabels'] = [];
            if (in_array('lead_attachments', $tables, true)) $viewContain['LeadAttachments'] = [];
        } catch (\Throwable $e) {}
        $lead = $Leads->get($id, contain: $viewContain);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        // FALA 15+16: agregat wykrytych zlecen z quote_request activities
        // + pobierz peine wiadomosci email z crm_email_messages (dla rozbudowanego widoku)
        $allShipments = [];
        $quoteRequests = [];
        $totalOrdersCreated = 0;
        foreach ($lead->lead_activities as $a) {
            if ($a->activity_type === 'quote_request' && !empty($a->payload_json)) {
                $p = json_decode($a->payload_json, true);
                if (!empty($p['shipments']) && is_array($p['shipments'])) {
                    $quoteRequests[] = [
                        'activity_id' => $a->id,
                        'created' => $a->created,
                        'happened_at' => $a->happened_at,
                        'from_email' => $p['from_email'] ?? '',
                        'customer_name' => $p['customer_name'] ?? '',
                        'shipments' => $p['shipments'],
                        'shipments_count' => count($p['shipments']),
                        'orders_created_count' => (int)($p['orders_created_count'] ?? 0),
                        'orders_created_at' => $p['orders_created_at'] ?? null,
                        'message_id' => $p['message_id'] ?? null,
                    ];
                    foreach ($p['shipments'] as $s) {
                        $allShipments[] = $s + ['_activity_id' => $a->id];
                    }
                    $totalOrdersCreated += (int)($p['orders_created_count'] ?? 0);
                }
            }
        }

        // Fetch emailowe wiadomosci z crm_email_messages dla tego leada (attachments)
        $emailMessages = [];
        try {
            $Msg = $this->fetchTable('CrmEmailMessages');
            $rows = $Msg->find()
                ->where(['lead_id' => $lead->id])
                ->orderByDesc('received_at')
                ->limit(50)
                ->all()->toArray();
            foreach ($rows as $m) {
                $emailMessages[(string)($m->message_id ?: $m->id)] = $m;
            }
        } catch (\Throwable $e) {
            // ignoruj jesli tabela nieodstepna
        }

        $this->set(compact('lead', 'quoteRequests', 'allShipments', 'totalOrdersCreated', 'emailMessages'));
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
            $lead = $Leads->patchEntity($lead, $data, ['associated' => ['LeadVehicleTypes']]);
            if ($Leads->save($lead, ['associated' => ['LeadVehicleTypes']])) {
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

        // Fetch lead z vehicle_types dla replace-strategy w patchEntity
        try {
            $lead = $Leads->get($id, ['contain' => ['LeadVehicleTypes']]);
        } catch (\Throwable $e) {}

        if ($this->request->is('post')) {
            $data = $this->prepareLeadData($this->request->getData(), $companyId, $userId, true);
            unset($data['company_id']);
            $lead = $Leads->patchEntity($lead, $data, ['associated' => ['LeadVehicleTypes']]);
            if ($Leads->save($lead, ['associated' => ['LeadVehicleTypes']])) {
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
            // W CakePHP 5 indexBy() jest na ResultSet, nie na SelectQuery -
            // trzeba najpierw ->all() i dopiero potem indeksowac.
            $rows = $Users->find()
                ->select(['id', 'first_name', 'last_name', 'email'])
                ->where(['Users.id IN' => $userIds])
                ->all();
            foreach ($rows as $u) {
                $usersData[(string)$u->id] = $u;
            }
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

        // Top 10 do dzwonienia dzis (rules-based scoring)
        $onlyMineTop = $this->request->getQuery('top_mine') === '1';
        $topPriority = $Leads->topPriority($companyId, $onlyMineTop ? $identity?->get('id') : null, 10);

        $this->set(compact(
            'stats', 'totalActive', 'totalValue', 'wonCount', 'lostCount', 'conversion',
            'ranking', 'activityByDay', 'sourceRows', 'days',
            'topPriority', 'onlyMineTop'
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
    /**
     * FALA 18: Kolejka pilnych maili - wszystkie email_in gdzie AI klasyfikator
     * wykryl urgency>=4 lub action_required=true, jeszcze nie oznaczone jako "zalatwione".
     * Grupowane po lead, sortowane najnowsze pierwsze.
     * GET /crm/pilne
     */
    /**
     * FALA 22: Executive dashboard dla managera - weighted forecast, sales velocity,
     * cohort analysis, revenue attribution. Chart.js dla wizualizacji.
     * GET /crm/manager
     */
    public function managerDashboard(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');

        // FALA 22 fix: sprawdz czy pipeline_type kolumna istnieje (migracja odpalona)
        $hasPipelineColumn = false;
        try {
            $schema = $Leads->getSchema();
            $hasPipelineColumn = in_array('pipeline_type', $schema->columns(), true);
        } catch (\Throwable $e) {}
        if (!$hasPipelineColumn) {
            $this->Flash->error(__('Executive Dashboard wymaga migracji AddPipelineTypeToLeads. Uruchom: /crm/admin/tools → "Uruchom pending migracje" a potem odśwież tę stronę.'));
            $this->redirect(['controller' => 'CrmAdmin', 'action' => 'tools']);
            return;
        }

        // 1. WEIGHTED FORECAST - SUM(value_pln * probability/100) per pipeline
        //    Wszystkie leady aktywne (nie lost/churned)
        $activeStages = ['lost', 'churned'];
        $forecastRows = $Leads->find()
            ->select([
                'pipeline_type', 'stage', 'assigned_to_user_id',
                'total_val' => 'SUM(value_pln)',
                'weighted' => 'SUM(value_pln * probability / 100)',
                'cnt' => 'COUNT(*)',
            ])
            ->where(['company_id' => $companyId, 'stage NOT IN' => $activeStages])
            ->groupBy(['pipeline_type', 'stage', 'assigned_to_user_id'])
            ->disableHydration()
            ->all()->toArray();

        // Agreguj do KPI
        $forecast = ['total' => 0, 'weighted' => 0, 'count' => 0, 'per_pipeline' => []];
        $forecastPerRep = []; // [userId => [total, weighted, count]]
        foreach ($forecastRows as $r) {
            $forecast['total'] += (float)$r['total_val'];
            $forecast['weighted'] += (float)$r['weighted'];
            $forecast['count'] += (int)$r['cnt'];
            $pt = $r['pipeline_type'] ?: 'spot';
            if (!isset($forecast['per_pipeline'][$pt])) {
                $forecast['per_pipeline'][$pt] = ['total' => 0, 'weighted' => 0, 'count' => 0];
            }
            $forecast['per_pipeline'][$pt]['total'] += (float)$r['total_val'];
            $forecast['per_pipeline'][$pt]['weighted'] += (float)$r['weighted'];
            $forecast['per_pipeline'][$pt]['count'] += (int)$r['cnt'];

            $uid = (string)($r['assigned_to_user_id'] ?? '');
            if ($uid !== '') {
                if (!isset($forecastPerRep[$uid])) {
                    $forecastPerRep[$uid] = ['total' => 0, 'weighted' => 0, 'count' => 0];
                }
                $forecastPerRep[$uid]['total'] += (float)$r['total_val'];
                $forecastPerRep[$uid]['weighted'] += (float)$r['weighted'];
                $forecastPerRep[$uid]['count'] += (int)$r['cnt'];
            }
        }

        // Fetch user names dla forecastPerRep
        $Users = $this->fetchTable('Users');
        $userMap = [];
        if (!empty($forecastPerRep)) {
            $u = $Users->find()
                ->select(['id', 'first_name', 'last_name'])
                ->where(['id IN' => array_keys($forecastPerRep)])
                ->all();
            foreach ($u as $usr) {
                $userMap[(string)$usr->id] = trim($usr->first_name . ' ' . $usr->last_name);
            }
        }

        // 2. SALES VELOCITY - dni contact/inquiry -> offer -> order
        //    Fetch leady zamkniete (stage=order/active/contract) z ost 6 mies
        $velocitySince = (new \DateTimeImmutable('-6 months'))->format('Y-m-d H:i:s');
        $Acts = $this->fetchTable('LeadActivities');
        // Dla velocity: dla kazdego wygranego leada, znajdz timestamp pierwszej activity + timestamp order_won
        $wonLeads = $Leads->find()
            ->select(['id', 'stage_changed_at', 'created', 'assigned_to_user_id', 'pipeline_type'])
            ->where([
                'company_id' => $companyId,
                'stage IN' => ['order', 'active', 'contract'],
                'stage_changed_at >=' => $velocitySince,
            ])
            ->limit(500)
            ->all()->toArray();
        $velocityDays = []; // [pipelineType => [days...]]
        $velocityPerRep = []; // [userId => [days...]]
        foreach ($wonLeads as $wl) {
            if (!$wl->stage_changed_at || !$wl->created) continue;
            $daysToWin = (int)$wl->created->diffInDays($wl->stage_changed_at);
            if ($daysToWin < 0 || $daysToWin > 365) continue; // outliers
            $pt = $wl->pipeline_type ?: 'spot';
            $velocityDays[$pt][] = $daysToWin;
            $uid = (string)($wl->assigned_to_user_id ?? '');
            if ($uid !== '') $velocityPerRep[$uid][] = $daysToWin;
        }
        $velocityMedian = function ($arr) {
            if (empty($arr)) return null;
            sort($arr);
            return $arr[intdiv(count($arr), 2)];
        };
        $velocityStats = [];
        foreach ($velocityDays as $pt => $days) {
            $velocityStats[$pt] = ['median' => $velocityMedian($days), 'count' => count($days)];
        }
        $velocityRepStats = [];
        foreach ($velocityPerRep as $uid => $days) {
            $velocityRepStats[$uid] = [
                'name' => $userMap[$uid] ?? 'Unknown',
                'median_days' => $velocityMedian($days),
                'won_count' => count($days),
            ];
        }
        uasort($velocityRepStats, fn($a, $b) => (int)$a['median_days'] <=> (int)$b['median_days']);

        // 3. COHORT ANALYSIS - leady z miesiaca X -> aktualny status
        //    Fetch leady z ost 6 mies grupowane po YYYY-MM created
        $cohortSince = (new \DateTimeImmutable('-6 months'))->format('Y-m-d');
        $cohortRows = $Leads->find()
            ->select([
                'cohort_month' => "DATE_FORMAT(created, '%Y-%m')",
                'stage',
                'cnt' => 'COUNT(*)',
            ])
            ->where(['company_id' => $companyId, 'created >=' => $cohortSince])
            ->groupBy(["DATE_FORMAT(created, '%Y-%m')", 'stage'])
            ->disableHydration()
            ->all()->toArray();
        $cohorts = []; // [YYYY-MM => [stage => count, total => X, won => Y, lost => Z]]
        foreach ($cohortRows as $r) {
            $m = $r['cohort_month'];
            if (!isset($cohorts[$m])) $cohorts[$m] = ['stages' => [], 'total' => 0, 'won' => 0, 'lost' => 0];
            $cohorts[$m]['stages'][$r['stage']] = (int)$r['cnt'];
            $cohorts[$m]['total'] += (int)$r['cnt'];
            if (in_array($r['stage'], ['order', 'active', 'contract'], true)) $cohorts[$m]['won'] += (int)$r['cnt'];
            if (in_array($r['stage'], ['lost', 'churned'], true)) $cohorts[$m]['lost'] += (int)$r['cnt'];
        }
        krsort($cohorts);

        // 4. REVENUE ATTRIBUTION - source (website/linkedin/csv/manual) -> sum wygranych
        $attrRows = $Leads->find()
            ->select([
                'source',
                'won_val' => 'SUM(value_pln)',
                'won_cnt' => 'COUNT(*)',
            ])
            ->where([
                'company_id' => $companyId,
                'stage IN' => ['order', 'active', 'contract'],
            ])
            ->groupBy('source')
            ->disableHydration()
            ->all()->toArray();
        $revenueAttribution = [];
        foreach ($attrRows as $r) {
            $src = $r['source'] ?: 'manual';
            $revenueAttribution[$src] = [
                'value' => (float)$r['won_val'],
                'count' => (int)$r['won_cnt'],
            ];
        }
        arsort($revenueAttribution);

        // 5. MONTHLY FORECAST TIMELINE - suma weighted per miesiac (na podstawie next_action_at
        //    lub stage_changed_at + prognoza zamkniecia 30 dni)
        // Uproszczone: fake miesieczna prognoza na podst. modified date + weighted
        $timelineRows = $Leads->find()
            ->select([
                'month' => "DATE_FORMAT(modified, '%Y-%m')",
                'weighted' => 'SUM(value_pln * probability / 100)',
                'cnt' => 'COUNT(*)',
            ])
            ->where(['company_id' => $companyId, 'stage NOT IN' => $activeStages,
                'modified >=' => $cohortSince])
            ->groupBy(["DATE_FORMAT(modified, '%Y-%m')"])
            ->orderByAsc('month')
            ->disableHydration()
            ->all()->toArray();
        $monthlyForecast = [];
        foreach ($timelineRows as $r) {
            $monthlyForecast[$r['month']] = [
                'weighted' => round((float)$r['weighted'], 2),
                'count' => (int)$r['cnt'],
            ];
        }

        $this->set(compact('forecast', 'forecastPerRep', 'userMap',
            'velocityStats', 'velocityRepStats', 'cohorts', 'revenueAttribution',
            'monthlyForecast'));
    }

    public function urgentEmails(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $onlyMine = $this->request->getQuery('mine') === '1';
        $onlyUrgent = $this->request->getQuery('urgent') === '1';

        $Acts = $this->fetchTable('LeadActivities');

        // Fetch email_in z payload_json - filter po AI klasyfikatorze
        $query = $Acts->find()
            ->where([
                'LeadActivities.company_id' => $companyId,
                'LeadActivities.activity_type' => 'email_in',
                'LeadActivities.payload_json LIKE' => '%"classification"%',
            ])
            ->contain([
                'Leads' => function ($q) {
                    return $q->select(['id', 'company_name', 'stage', 'nip', 'city', 'country_code',
                        'assigned_to_user_id', 'email', 'phone', 'contact_person']);
                },
            ])
            ->orderByDesc('LeadActivities.created')
            ->limit(200);

        $rows = $query->all()->toArray();

        // Filter po klasyfikacji + assigned user (na PHP level bo JSON w MySQL)
        $filtered = [];
        $stats = ['total' => 0, 'urgent' => 0, 'action' => 0, 'complaint' => 0];
        foreach ($rows as $act) {
            $p = json_decode((string)$act->payload_json, true) ?: [];
            $cls = $p['classification'] ?? null;
            if (!$cls) continue;
            $urgency = (int)($cls['urgency'] ?? 0);
            $action = !empty($cls['action_required']);
            $sentiment = $cls['sentiment'] ?? '';
            $intent = $cls['intent'] ?? '';

            $isPriority = ($urgency >= 4) || $action || ($sentiment === 'urgent') || ($intent === 'complaint');
            if (!$isPriority) continue;

            if ($onlyMine && (string)$act->lead?->assigned_to_user_id !== (string)$userId) continue;
            if ($onlyUrgent && $urgency < 5) continue;

            $stats['total']++;
            if ($urgency >= 4) $stats['urgent']++;
            if ($action) $stats['action']++;
            if ($intent === 'complaint') $stats['complaint']++;

            $act->set('_classification', $cls, ['guard' => false]);
            $filtered[] = $act;
        }

        // Grupuj po lead
        $byLead = [];
        foreach ($filtered as $act) {
            $lid = (string)$act->lead_id;
            if (!isset($byLead[$lid])) {
                $byLead[$lid] = ['lead' => $act->lead, 'activities' => []];
            }
            $byLead[$lid]['activities'][] = $act;
        }

        $this->set(compact('byLead', 'stats', 'onlyMine', 'onlyUrgent'));
    }

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
                    \Cake\Log\Log::info(sprintf('CRM importCsv upload: name=%s, size=%d, len=%d, first120=%s',
                        (string)$upload->getClientFilename(),
                        (int)$upload->getSize(),
                        strlen($content),
                        substr(str_replace(["\r", "\n"], ['<CR>', '<LF>'], $content), 0, 120)
                    ));
                    $rows = $this->parseCsv($content);
                }
            } elseif ($upload && $upload->getError() !== UPLOAD_ERR_NO_FILE) {
                $errors[] = sprintf(__('Błąd uploadu pliku (kod PHP: %d)'), $upload->getError());
            } elseif ($isConfirm && $csvText !== '') {
                $rows = $this->parseCsv($csvText);
            }

            if (empty($errors) && empty($rows)) {
                $errors[] = __('CSV jest pusty lub niepoprawny. Sprawdź: separator (;), kodowanie (UTF-8), pierwsza linia = nagłówek, dane od 2. linii.');
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

    /**
     * GPT AI: draft odpowiedzi email na podstawie pelnej historii korespondencji.
     *
     * POST /crm/ai/draft-response
     * Body: {lead_id, message_id?, tone?, context?}
     * Return: {ok, draft_subject, draft_body, tokens_used}
     */
    public function aiDraftResponseJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $leadId = trim((string)$this->request->getData('lead_id', ''));
        $msgId  = trim((string)$this->request->getData('message_id', ''));
        $tone   = trim((string)$this->request->getData('tone', 'professional'));
        $extraContext = trim((string)$this->request->getData('context', ''));

        $Leads = $this->fetchTable('Leads');
        try {
            $lead = $Leads->get($leadId, contain: [
                'AssignedUser' => fn($q) => $q->select(['id', 'first_name', 'last_name', 'email']),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResp(['ok' => false, 'error' => 'lead_not_found'], 404);
        }
        if ((string)$lead->company_id !== (string)$companyId) {
            return $this->jsonResp(['ok' => false, 'error' => 'not_owned'], 403);
        }

        // Pobierz ostatnia wiadomosc lub konkretna
        $Messages = $this->fetchTable('CrmEmailMessages');
        $msgQuery = $Messages->find()->where(['lead_id' => $lead->id]);
        if ($msgId !== '') {
            $msgQuery->where(['id' => $msgId]);
        } else {
            $msgQuery->orderByDesc('received_at');
        }
        $lastMsg = $msgQuery->first();
        if (!$lastMsg) {
            return $this->jsonResp(['ok' => false, 'error' => 'no_email',
                'hint' => __('Brak zapisanych emaili od tego leada. Uruchom cron IMAP zeby pobrac.')], 400);
        }

        // Pobierz cala history w tym watku (do 10 ostatnich)
        $threadMsgs = $Messages->find()
            ->where(['lead_id' => $lead->id, 'thread_id' => $lastMsg->thread_id])
            ->orderByAsc('received_at')
            ->limit(10)
            ->all();

        // Build context dla GPT
        $threadText = '';
        foreach ($threadMsgs as $m) {
            $threadText .= sprintf("=== [%s] %s <%s> -> %s ===\nTemat: %s\n\n%s\n\n",
                $m->received_at ? $m->received_at->format('Y-m-d H:i') : '?',
                $m->from_name ?: '?',
                $m->from_email,
                $m->to_emails ?: '?',
                $m->subject ?: '(bez tematu)',
                mb_substr($m->body_text ?: '', 0, 3000)
            );
        }

        // Ostatnie 5 activities (poza email_in - to juz mamy)
        $Acts = $this->fetchTable('LeadActivities');
        $otherActs = $Acts->find()
            ->where(['lead_id' => $lead->id, 'activity_type NOT IN' => ['email_in']])
            ->orderByDesc('happened_at')
            ->limit(5)
            ->all();
        $actsText = '';
        foreach ($otherActs as $a) {
            $actsText .= sprintf("- [%s] %s: %s\n",
                ($a->happened_at ?? $a->created)->format('Y-m-d H:i'),
                $a->activity_type,
                mb_substr(($a->subject ?: '') . ' ' . ($a->body ?: ''), 0, 200)
            );
        }

        $handlerName = trim(($lead->assigned_user?->first_name ?? '') . ' ' . ($lead->assigned_user?->last_name ?? '')) ?: 'Zespol Booklio TMS';

        $toneMap = [
            'professional' => 'profesjonalnym, biznesowym',
            'friendly'     => 'ciepłym, przyjaznym',
            'urgent'       => 'zdecydowanym, wzywającym do akcji',
            'formal'       => 'bardzo formalnym',
        ];
        $toneDesc = $toneMap[$tone] ?? $toneMap['professional'];

        $systemPrompt = "Jestes doswiadczonym handlowcem w polskiej firmie spedycyjnej (transport drogowy PL/EU). "
            . "Twoim zadaniem jest napisac odpowiedz emailem klientowi na podstawie historii korespondencji. "
            . "Piszesz w " . $toneDesc . " tonie, po polsku (chyba ze klient pisze w innym jezyku - wtedy dopasuj). "
            . "Bądz konkretny, oferuj rozwiazania. Podpisz jako: " . $handlerName . ". "
            . "Zwroc wynik w formacie JSON: {\"subject\": \"...\", \"body\": \"...\"}. "
            . "Zwykle 'subject' to 'Re: ' + oryginalny temat.";

        $userPrompt = "=== KONTEKST LEADA ===\n"
            . "Firma: " . $lead->company_name . "\n"
            . "Osoba kontaktowa: " . ($lead->contact_person ?: '?') . "\n"
            . "Etap: " . $lead->stage . "\n"
            . "Wartosc: " . ($lead->value_pln ? number_format((float)$lead->value_pln, 0, ',', ' ') . ' PLN' : '?') . "\n"
            . "Galaz: " . ($lead->branch_type ?: '?') . "\n"
            . "Notatka wewnetrzna: " . ($lead->note ?: '(brak)') . "\n\n"
            . "=== HISTORIA EMAILI (watku) ===\n" . $threadText . "\n"
            . "=== OSTATNIE AKTYWNOSCI ===\n" . ($actsText ?: '(brak)') . "\n"
            . ($extraContext !== '' ? "\n=== DODATKOWY KONTEKST OD HANDLOWCA ===\n" . $extraContext . "\n" : '')
            . "\n=== ZADANIE ===\n"
            . "Napisz odpowiedz na ostatnia wiadomosc od klienta. "
            . "Odpowiadaj konkretnie na jego pytania/oferty/prosby. "
            . "Zwroc wynik w formacie JSON.";

        try {
            $service = new \App\Service\Ai\OpenAiService();
            $result = $service->chatJson($systemPrompt, $userPrompt, 2000);
            $subject = trim((string)($result['subject'] ?? ('Re: ' . $lastMsg->subject)));
            $body    = trim((string)($result['body'] ?? ''));

            return $this->jsonResp([
                'ok'            => true,
                'draft_subject' => $subject,
                'draft_body'    => $body,
                'thread_count'  => count($threadMsgs),
                'last_msg_from' => $lastMsg->from_email,
                'last_msg_date' => $lastMsg->received_at ? $lastMsg->received_at->format('d.m.Y H:i') : null,
            ]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('AI draft response failed: ' . $e->getMessage());
            return $this->jsonResp(['ok' => false, 'error' => 'ai_failed', 'hint' => $e->getMessage()], 500);
        }
    }

    /**
     * GPT AI: summarize timeline leada + rekomendacja next step.
     * POST /crm/ai/summarize {lead_id}
     */
    public function aiSummarizeJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $leadId = trim((string)$this->request->getData('lead_id', ''));
        $Leads = $this->fetchTable('Leads');
        try {
            $lead = $Leads->get($leadId, contain: [
                'LeadActivities' => fn($q) => $q->orderByDesc('happened_at')->limit(30),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResp(['ok' => false, 'error' => 'lead_not_found'], 404);
        }
        if ((string)$lead->company_id !== (string)$companyId) {
            return $this->jsonResp(['ok' => false, 'error' => 'not_owned'], 403);
        }

        $Messages = $this->fetchTable('CrmEmailMessages');
        $emails = $Messages->find()
            ->where(['lead_id' => $lead->id])
            ->orderByDesc('received_at')
            ->limit(10)
            ->all();

        $emailsText = '';
        foreach ($emails as $m) {
            $emailsText .= sprintf("[%s] %s: %s\n%s\n\n",
                $m->received_at ? $m->received_at->format('Y-m-d') : '?',
                $m->from_email, $m->subject ?: '',
                mb_substr($m->body_text ?: '', 0, 500)
            );
        }
        $actsText = '';
        foreach (($lead->lead_activities ?? []) as $a) {
            $actsText .= sprintf("[%s] %s: %s\n",
                ($a->happened_at ?? $a->created)->format('Y-m-d'),
                $a->activity_type,
                mb_substr(($a->subject ?: '') . ' ' . ($a->body ?: ''), 0, 200)
            );
        }

        $system = "Jestes sales managerem analizujacym historie leada w CRM. "
            . "Wygeneruj krotkie (max 200 slow) podsumowanie po polsku + 3-5 konkretnych rekomendacji nastepnych krokow. "
            . "Zwroc JSON: {\"summary\": \"...\", \"next_steps\": [\"krok1\", \"krok2\", ...], \"sentiment\": \"positive|neutral|negative|urgent\", \"probability_hint\": 0-100}";
        $user = sprintf("=== LEAD ===\nFirma: %s\nStage: %s\nProb: %d%%\nWartosc: %s\nGalaz: %s\nOsoba: %s\n\n=== EMAILE ===\n%s\n=== ACTIVITIES ===\n%s",
            $lead->company_name, $lead->stage, (int)$lead->probability,
            $lead->value_pln ?: '?', $lead->branch_type ?: '?',
            $lead->contact_person ?: '?',
            $emailsText ?: '(brak)',
            $actsText ?: '(brak)'
        );

        try {
            $result = (new \App\Service\Ai\OpenAiService())->chatJson($system, $user, 1500);
            return $this->jsonResp([
                'ok'         => true,
                'summary'    => $result['summary'] ?? '',
                'next_steps' => $result['next_steps'] ?? [],
                'sentiment'  => $result['sentiment'] ?? 'neutral',
                'probability_hint' => (int)($result['probability_hint'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('AI summarize failed: ' . $e->getMessage());
            return $this->jsonResp(['ok' => false, 'error' => 'ai_failed', 'hint' => $e->getMessage()], 500);
        }
    }

    /**
     * LinkedIn search - znajdz publiczny URL profilu osoby/firmy przez
     * zewnetrzne Search API (Serper.dev/Brave/Google CSE).
     *
     * POST /crm/linkedin-search
     * Body: {lead_id, mode: 'person'|'company', save: 1?}
     * Return: {ok, results:[{url,title,snippet}], saved: bool, provider}
     */
    public function linkedinSearchJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $leadId = trim((string)$this->request->getData('lead_id', ''));
        $mode   = trim((string)$this->request->getData('mode', 'person'));
        $save   = (bool)$this->request->getData('save');

        if (!in_array($mode, ['person', 'company'], true)) {
            return $this->jsonResp(['ok' => false, 'error' => 'invalid_mode'], 400);
        }

        $Leads = $this->fetchTable('Leads');
        try {
            $lead = $Leads->get($leadId);
        } catch (\Throwable $e) {
            return $this->jsonResp(['ok' => false, 'error' => 'lead_not_found'], 404);
        }
        if ((string)$lead->company_id !== (string)$companyId) {
            return $this->jsonResp(['ok' => false, 'error' => 'not_owned'], 403);
        }

        $service = new \App\Service\LinkedinSearchService();
        if (!$service->isConfigured()) {
            return $this->jsonResp([
                'ok' => false,
                'error' => 'search_not_configured',
                'hint' => __('Search API nie skonfigurowany. Dodaj klucz w config/app_local.php: Search.provider + Search.serperApiKey (lub braveApiKey/googleCseApiKey).'),
            ], 400);
        }

        try {
            if ($mode === 'person') {
                $name = trim((string)$lead->contact_person);
                if ($name === '') {
                    return $this->jsonResp(['ok' => false, 'error' => 'no_contact_person',
                        'hint' => __('Uzupelnij osobe kontaktowa w leadzie zeby moc wyszukac profil.')], 400);
                }
                $results = $service->findPerson($name, (string)$lead->company_name);
            } else {
                $results = $service->findCompany((string)$lead->company_name);
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('LinkedIn search failed: ' . $e->getMessage());
            return $this->jsonResp(['ok' => false, 'error' => 'search_failed', 'hint' => $e->getMessage()], 500);
        }

        $saved = false;
        if ($save && !empty($results)) {
            $topUrl = $results[0]['url'];
            $field = $mode === 'person' ? 'linkedin_url' : 'linkedin_company_url';
            if ((string)($lead->{$field} ?? '') === '') {
                $lead->{$field} = $topUrl;
                if ($Leads->save($lead)) {
                    $saved = true;
                    $this->fetchTable('LeadActivities')->logSystem(
                        $companyId, $lead->id, 'note',
                        __('Znaleziono LinkedIn URL'),
                        sprintf(__('%s: %s (provider: %s)'), $field, $topUrl, $service->getProvider()),
                        ['field' => $field, 'url' => $topUrl, 'provider' => $service->getProvider()],
                        $userId
                    );
                }
            }
        }

        return $this->jsonResp([
            'ok'       => true,
            'results'  => $results,
            'saved'    => $saved,
            'provider' => $service->getProvider(),
        ]);
    }

    /**
     * KRS lookup - pobiera pelny wypis MS-KRS API dla podanego numeru KRS lub NIP.
     * Auto-apply do leada + zwraca panel z dodatkowymi info (kapital, PKD, wspolnicy).
     *
     * POST /crm/krs-lookup
     * Body: { lead_id, krs? , nip?, apply? }  - apply=1 zapisuje pola do leada
     */
    public function krsLookupJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $leadId = trim((string)$this->request->getData('lead_id', ''));
        $krs    = trim((string)$this->request->getData('krs', ''));
        $nip    = trim((string)$this->request->getData('nip', ''));
        $apply  = (bool)$this->request->getData('apply');

        $krs = str_pad(preg_replace('/[^0-9]/', '', $krs), 10, '0', STR_PAD_LEFT);
        $nip = preg_replace('/[^0-9]/', '', $nip);

        $service = new \App\Service\KrsService();
        $data = null;

        if (strlen($krs) === 10 && $krs !== '0000000000') {
            $data = $service->fetchByKrs($krs);
        } elseif (strlen($nip) === 10) {
            $data = $service->fetchByNipFromCache($nip);
            if (!$data) {
                return $this->jsonResp([
                    'ok' => true,
                    'data' => null,
                    'hint' => __('Podaj KRS - MS API nie oferuje wyszukiwania po NIP. Znajdz KRS przez wyszukiwarka-krs.ms.gov.pl'),
                ]);
            }
        } else {
            return $this->jsonResp(['ok' => false, 'error' => __('Podaj KRS (10 cyfr) lub NIP.')], 400);
        }

        if (!$data) {
            return $this->jsonResp(['ok' => true, 'data' => null,
                'hint' => __('Nie znaleziono w rejestrze P (przedsiebiorcy) ani S (stowarzyszenia).')]);
        }

        // Opcjonalnie apply do leada
        $applied = [];
        if ($apply && $leadId !== '') {
            try {
                $Leads = $this->fetchTable('Leads');
                $lead = $Leads->get($leadId);
                if ((string)$lead->company_id === (string)$companyId) {
                    $updates = [];
                    if (empty($lead->company_name) && $data['nazwa']) {
                        $lead->company_name = $data['nazwa']; $updates[] = 'company_name';
                    }
                    if (empty($lead->nip) && $data['nip']) {
                        $lead->nip = $data['nip']; $updates[] = 'nip';
                    }
                    if (empty($lead->country_code)) {
                        $lead->country_code = 'PL'; $updates[] = 'country_code';
                    }
                    if (empty($lead->postal_code) && $data['kod_pocztowy']) {
                        $lead->postal_code = $data['kod_pocztowy']; $updates[] = 'postal_code';
                    }
                    if (empty($lead->city) && $data['miejscowosc']) {
                        $lead->city = $data['miejscowosc']; $updates[] = 'city';
                    }
                    if (empty($lead->street) && ($data['ulica'] || $data['nr_domu'])) {
                        $street = trim(($data['ulica'] ?? '') . ' ' . ($data['nr_domu'] ?? '') .
                            ($data['nr_lokalu'] ? '/' . $data['nr_lokalu'] : ''));
                        $lead->street = $street; $updates[] = 'street';
                    }
                    if (!empty($updates)) {
                        $Leads->save($lead);
                        // Log activity
                        $this->fetchTable('LeadActivities')->logSystem(
                            $companyId, $lead->id, 'note',
                            __('Auto-fill z KRS'),
                            sprintf(__('Uzupelnione pola z KRS %s: %s'), $data['krs'], implode(', ', $updates)),
                            ['krs' => $data['krs'], 'fields' => $updates],
                            $userId
                        );
                        $applied = $updates;
                    }
                }
            } catch (\Throwable $e) {
                \Cake\Log\Log::warning('KRS auto-apply failed: ' . $e->getMessage());
            }
        }

        return $this->jsonResp([
            'ok'      => true,
            'data'    => $data,
            'applied' => $applied,
        ]);
    }

    /**
     * Widok duplikatow: pary lead-ow do potencjalnej konsolidacji.
     * Wykrywa: same NIP / same email / same phone / podobna nazwa (Levenshtein).
     */
    public function duplicates(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $all = $Leads->find()
            ->select(['id', 'company_name', 'nip', 'email', 'phone', 'city', 'country_code',
                      'stage', 'probability', 'value_pln', 'contact_person', 'last_contacted_at',
                      'modified'])
            ->where(['company_id' => $companyId])
            ->orderByDesc('modified')
            ->all();

        $byNip   = [];
        $byEmail = [];
        $byPhone = [];
        $byName  = [];
        $leadsIdx = [];

        foreach ($all as $l) {
            $leadsIdx[(string)$l->id] = $l;
            if (!empty($l->nip))   $byNip[strtoupper(trim($l->nip))][] = $l->id;
            if (!empty($l->email)) $byEmail[strtolower(trim($l->email))][] = $l->id;
            $normPhone = preg_replace('/[^0-9]/', '', (string)$l->phone);
            if (strlen($normPhone) >= 6) $byPhone[$normPhone][] = $l->id;
            $normName = strtolower(preg_replace('/\s+/', ' ', trim((string)$l->company_name)));
            $normName = preg_replace('/\s+(sp\.?\s*z\s*o\.?\s*o\.?|s\.a\.|sa|gmbh|kg|b\.v\.|bv|ag|s\.r\.o\.?|srl|kft)$/i', '', $normName);
            if ($normName !== '') $byName[$normName][] = $l->id;
        }

        // Zbierz pary z powodem
        $pairs = [];
        $seen = []; // klucz "id1|id2" (sorted)
        $addPair = function ($a, $b, $reason) use (&$pairs, &$seen, $leadsIdx) {
            if ($a === $b) return;
            $k = min($a, $b) . '|' . max($a, $b);
            if (isset($seen[$k])) {
                $pairs[$k]['reasons'][] = $reason;
                return;
            }
            if (!isset($leadsIdx[$a]) || !isset($leadsIdx[$b])) return;
            $seen[$k] = true;
            $pairs[$k] = [
                'a' => $leadsIdx[$a],
                'b' => $leadsIdx[$b],
                'reasons' => [$reason],
            ];
        };
        foreach ($byNip as $ids)   foreach ($ids as $x) foreach ($ids as $y) if ($x < $y) $addPair($x, $y, 'ten sam NIP');
        foreach ($byEmail as $ids) foreach ($ids as $x) foreach ($ids as $y) if ($x < $y) $addPair($x, $y, 'ten sam email');
        foreach ($byPhone as $ids) foreach ($ids as $x) foreach ($ids as $y) if ($x < $y) $addPair($x, $y, 'ten sam tel');
        foreach ($byName as $ids)  foreach ($ids as $x) foreach ($ids as $y) if ($x < $y) $addPair($x, $y, 'ta sama nazwa');

        // Fuzzy match nazw (Levenshtein <= 3, tylko jesli obie >= 5 znakow) - drozsze, tylko dla small subset
        // Pomijamy dla dyzych zbiorow zeby nie zamulic
        if (count($all) < 500) {
            $names = [];
            foreach ($all as $l) {
                $n = strtolower(preg_replace('/[^a-z0-9]/', '', (string)$l->company_name));
                if (strlen($n) >= 5) $names[(string)$l->id] = $n;
            }
            $keys = array_keys($names);
            $vals = array_values($names);
            $n = count($keys);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (abs(strlen($vals[$i]) - strlen($vals[$j])) > 3) continue;
                    $d = levenshtein($vals[$i], $vals[$j]);
                    if ($d > 0 && $d <= 2) $addPair($keys[$i], $keys[$j], 'podobna nazwa (typo)');
                }
            }
        }

        // Sortuj po liczbie powodow desc + ograniczenie
        usort($pairs, fn($p1, $p2) => count($p2['reasons']) <=> count($p1['reasons']));
        $pairs = array_slice($pairs, 0, 200);

        $this->set(compact('pairs'));
    }

    /**
     * UI: podglad przed merge - wybierz ktora wartosc zachowac per pole.
     * GET /crm/merge?a=uuid&b=uuid
     */
    public function mergeReview(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $aId = trim((string)$this->request->getQuery('a', ''));
        $bId = trim((string)$this->request->getQuery('b', ''));
        if ($aId === '' || $bId === '' || $aId === $bId) {
            throw new BadRequestException('Podaj dwa rozne lead id.');
        }
        $a = $Leads->get($aId, contain: ['LeadActivities']);
        $b = $Leads->get($bId, contain: ['LeadActivities']);
        if ((string)$a->company_id !== (string)$companyId || (string)$b->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $this->set(compact('a', 'b'));
    }

    /**
     * POST /crm/merge - scala 2 leady w 1.
     * Wybieramy wartosci per pole (default lead A), a lead B jest usuwany.
     * Wszystkie activities z B sa przenoszone do A.
     */
    public function merge(): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $data = $this->request->getData();
        $aId = trim((string)($data['a'] ?? ''));
        $bId = trim((string)($data['b'] ?? ''));
        if ($aId === '' || $bId === '' || $aId === $bId) {
            $this->Flash->error(__('Podaj dwa różne lead ID.'));
            $this->redirect(['action' => 'duplicates']);
            return;
        }

        $Leads = $this->fetchTable('Leads');
        $Acts  = $this->fetchTable('LeadActivities');
        $a = $Leads->get($aId);
        $b = $Leads->get($bId);
        if ((string)$a->company_id !== (string)$companyId || (string)$b->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }

        // Fields to merge - dla kazdego pola $data['field_source_X'] = 'a'|'b'
        $mergeFields = ['company_name', 'nip', 'country_code', 'postal_code', 'city', 'street',
            'contact_person', 'contact_role', 'phone', 'email', 'contact_channel', 'branch_type',
            'value_pln', 'currency', 'assigned_to_user_id', 'note', 'next_action_description'];
        foreach ($mergeFields as $f) {
            $source = (string)($data['field_source_' . $f] ?? 'a');
            $a->{$f} = ($source === 'b') ? $b->{$f} : $a->{$f};
        }

        // Wyzszy stage/probability z obu
        $stageOrder = ['new' => 0, 'contact' => 1, 'inquiry' => 2, 'offer' => 3, 'order' => 4, 'lost' => -1];
        if (($stageOrder[$b->stage] ?? 0) > ($stageOrder[$a->stage] ?? 0)) $a->stage = $b->stage;
        $a->probability = max((int)$a->probability, (int)$b->probability);

        // Auto-flagi OR
        foreach (['flag_contact', 'flag_inquiry', 'flag_offer', 'flag_order'] as $flag) {
            $a->{$flag} = ((bool)$a->{$flag}) || ((bool)$b->{$flag});
        }

        // Latest last_contacted
        if ($b->last_contacted_at && (!$a->last_contacted_at || $b->last_contacted_at > $a->last_contacted_at)) {
            $a->last_contacted_at = $b->last_contacted_at;
        }

        $connection = $Leads->getConnection();
        $connection->begin();
        try {
            // Zapisz zaktualizowanego A
            if (!$Leads->save($a)) {
                throw new \RuntimeException('Nie mozna zapisac leada A: ' . json_encode($a->getErrors()));
            }

            // Przenies activities z B do A
            $Acts->updateAll(['lead_id' => $a->id], ['lead_id' => $b->id]);

            // Log merge
            $Acts->logSystem(
                $companyId, $a->id, 'note',
                sprintf(__('Scalono z leadem: %s'), $b->company_name),
                sprintf(__('ID scalonego leada: %s. Wszystkie activities przeniesione.'), $b->id),
                ['merged_from' => $b->id, 'merged_from_name' => $b->company_name],
                $userId
            );

            // Usun B
            $Leads->delete($b);

            $connection->commit();
            $this->Flash->success(sprintf(__('Scalono %s z %s.'), $b->company_name, $a->company_name));
            $this->redirect(['action' => 'view', $a->id]);
        } catch (\Throwable $e) {
            $connection->rollback();
            \Cake\Log\Log::error('CRM merge failed: ' . $e->getMessage());
            $this->Flash->error(sprintf(__('Błąd scalenia: %s'), $e->getMessage()));
            $this->redirect(['action' => 'duplicates']);
        }
    }

    /**
     * PUBLICZNY: formularz kontaktowy dla klientow z www.
     * GET/POST /kontakt/{companyId}
     * Auto-tworzy lead z source='website', stage='new'.
     * Anti-spam: honeypot pole + rate limit po IP (5/h) + minimum czas formularza 3s.
     */
    public function publicForm(string $companyId = ''): void
    {
        $this->request->allowMethod(['get', 'post']);
        $this->viewBuilder()->setLayout('ajax');

        // Walidacja companyId - musi istniec
        $companyId = trim($companyId);
        if ($companyId === '') {
            $companyId = (string)\Cake\Core\Configure::read('App.defaultCompanyId', '');
        }
        if ($companyId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $companyId)) {
            throw new NotFoundException(__('Nieprawidlowy adres formularza kontaktowego.'));
        }
        try {
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()->where(['id' => $companyId])->first();
            if (!$company) {
                throw new NotFoundException(__('Firma nie istnieje.'));
            }
        } catch (\Throwable $e) {
            throw new NotFoundException(__('Firma nie istnieje.'));
        }

        $errors = [];
        $submitted = false;

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Honeypot: pole 'website_url' (ukryte) musi byc puste - boty je wypelnia
            if (!empty($data['website_url'])) {
                // Silent success dla botow (nie chcemy im dawac sygnalu)
                $this->redirect(['action' => 'publicFormThanks', $companyId]);
                return;
            }

            // Minimum 3 sekundy od otwarcia formularza (t = timestamp)
            $t = (int)($data['t'] ?? 0);
            if ($t > 0 && (time() - $t) < 3) {
                $this->redirect(['action' => 'publicFormThanks', $companyId]);
                return;
            }

            // Rate limit - max 5 formularzy z jednego IP na godzine
            $ip = (string)$this->request->clientIp();
            $rateKey = 'crm_public_form:' . md5($ip);
            $count = (int)($this->request->getSession()->read($rateKey) ?? 0);
            if ($count >= 5) {
                $errors[] = __('Zbyt wiele formularzy wyslanych z tego adresu. Sprobuj za godzine.');
            } else {
                $this->request->getSession()->write($rateKey, $count + 1);
            }

            // Walidacja
            $companyName = trim((string)($data['company_name'] ?? ''));
            $email       = trim((string)($data['email'] ?? ''));
            $person      = trim((string)($data['contact_person'] ?? ''));
            $phone       = trim((string)($data['phone'] ?? ''));
            $message     = trim((string)($data['message'] ?? ''));

            if ($companyName === '') $errors[] = __('Nazwa firmy jest wymagana.');
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('Podaj prawidlowy adres email.');
            }
            if ($message === '' && $phone === '') {
                $errors[] = __('Podaj tresc zapytania lub numer telefonu.');
            }
            if (mb_strlen($message) > 2000) {
                $errors[] = __('Wiadomosc jest za dluga (max 2000 znakow).');
            }

            if (empty($errors)) {
                try {
                    $Leads = $this->fetchTable('Leads');
                    $lead = $Leads->newEntity([
                        'company_id'      => $companyId,
                        'company_name'    => $companyName,
                        'nip'             => strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)($data['nip'] ?? ''))) ?: null,
                        'country_code'    => strtoupper(substr(trim((string)($data['country_code'] ?? '')), 0, 2)) ?: null,
                        'city'            => trim((string)($data['city'] ?? '')) ?: null,
                        'contact_person'  => $person ?: null,
                        'phone'           => $phone ?: null,
                        'email'           => $email,
                        'contact_channel' => 'email',
                        'branch_type'     => trim((string)($data['branch_type'] ?? '')) ?: null,
                        'source'          => 'website',
                        'stage'           => 'new',
                        'note'            => $message,
                        'next_action_at'  => new DateTime('+1 day'),
                        'next_action_description' => __('Odpowiedz na zapytanie z www'),
                    ]);
                    if ($Leads->save($lead)) {
                        // Log activity
                        $this->fetchTable('LeadActivities')->logSystem(
                            $companyId, $lead->id, 'email_in',
                            __('Zapytanie z formularza kontaktowego'),
                            $message ?: __('(brak tresci)'),
                            ['ip' => $ip, 'user_agent' => (string)$this->request->getHeaderLine('User-Agent')],
                            null
                        );

                        // Powiadom admina firmy (best-effort)
                        try {
                            $Users = $this->fetchTable('Users');
                            $admin = $Users->find()
                                ->where(['company_id' => $companyId])
                                ->orderByAsc('created')
                                ->first();
                            if ($admin && !empty($admin->email)) {
                                $mailer = new \Cake\Mailer\Mailer('default');
                                $mailer->setTo((string)$admin->email)
                                    ->setSubject(sprintf('[CRM] Nowe zapytanie z www: %s', $companyName))
                                    ->deliver(
                                        sprintf("Nowe zapytanie z www:\n\nFirma: %s\nOsoba: %s\nEmail: %s\nTel: %s\n\nWiadomosc:\n%s\n\nZobacz lead: %s/crm/view/%s",
                                            $companyName, $person, $email, $phone, $message,
                                            rtrim((string)\Cake\Core\Configure::read('App.fullBaseUrl'), '/'),
                                            $lead->id
                                        )
                                    );
                            }
                        } catch (\Throwable $e) {
                            \Cake\Log\Log::warning('publicForm admin notify failed: ' . $e->getMessage());
                        }

                        $submitted = true;
                        $this->redirect(['action' => 'publicFormThanks', $companyId]);
                        return;
                    }
                    $errors[] = __('Blad zapisu zapytania. Sprobuj ponownie.');
                    \Cake\Log\Log::warning('publicForm save failed: ' . json_encode($lead->getErrors()));
                } catch (\Throwable $e) {
                    $errors[] = __('Wystapil blad techniczny.');
                    \Cake\Log\Log::error('publicForm exception: ' . $e->getMessage());
                }
            }
        }

        $this->set(compact('company', 'errors', 'submitted'));
    }

    /**
     * PUBLICZNE: podziekowanie po submicie formularza.
     */
    public function publicFormThanks(string $companyId): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        try {
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()->where(['id' => $companyId])->first();
        } catch (\Throwable $e) {
            $company = null;
        }
        $this->set(compact('company'));
    }

    // ================= HELPERS =================

    private function prepareLeadData(array $data, string $companyId, ?string $userId, bool $isEdit = false): array
    {
        $data['company_id'] = $companyId;

        // FALA extras: industry_id (single FK) - waliduj czy nalezy do firmy
        if (!empty($data['industry_id'])) {
            try {
                $I = $this->fetchTable('LeadIndustries');
                $ok = $I->find()->where(['id' => $data['industry_id'], 'company_id' => $companyId])->count() > 0;
                if (!$ok) $data['industry_id'] = null;
            } catch (\Throwable $e) { $data['industry_id'] = null; }
        } else {
            $data['industry_id'] = null;
        }

        // FALA extras: vehicle_type_ids (multi) - buduj lead_vehicle_types entities do belongsToMany replace
        $vtIds = (array)($data['vehicle_type_ids'] ?? []);
        unset($data['vehicle_type_ids']);
        try {
            if (!empty($vtIds)) {
                $V = $this->fetchTable('LeadVehicleTypes');
                $entities = $V->find()->where(['company_id' => $companyId, 'id IN' => $vtIds])->all()->toArray();
                $data['lead_vehicle_types'] = $entities;
            } else {
                $data['lead_vehicle_types'] = [];
            }
        } catch (\Throwable $e) {}

        // FALA 21: Multi-pipeline - walidacja + default
        if (empty($data['pipeline_type']) || !in_array($data['pipeline_type'], \App\Model\Table\LeadsTable::PIPELINE_TYPES, true)) {
            $data['pipeline_type'] = 'spot';
        }
        // Walidacja stage vs pipeline (jesli user manualnie postnie zly stage)
        $allowedStages = \App\Model\Table\LeadsTable::stagesForPipeline($data['pipeline_type']);
        if (!empty($data['stage']) && !in_array($data['stage'], $allowedStages, true)) {
            $data['stage'] = $allowedStages[0]; // fallback do pierwszego stage tego pipeline
        }

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
        // Normalizacja newlinow: Windows (CRLF), Mac Classic (CR) i Unix (LF) -> LF.
        // Kolejnosc wazna: najpierw CRLF, potem samo CR (zeby nie zamienic \r\n na \n\n).
        $content = str_replace(["\r\n", "\r"], "\n", $content);
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
        // Split na linie + wywal puste (moze byc wiele trailing \n)
        $lines = array_values(array_filter(explode("\n", $content), fn($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            \Cake\Log\Log::warning('CRM parseCsv: mniej niz 2 linie po parsowaniu. Content len: '
                . strlen($content) . ', lines: ' . count($lines)
                . ', first 200 chars: ' . substr($content, 0, 200));
            return [];
        }

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

    /**
     * FALA 15: Utworz manualne zlecenia (speed_orders) z listy shipments wyekstraktowanej z emaila
     * przez GPT (activity_type='quote_request').
     * POST /crm/{leadId}/utworz-zlecenia-z-quote/{activityId}
     */
    /**
     * FALA 19: Wyslij odpowiedz przez Gmail API (POST messages/send).
     * Wymaga: Gmail OAuth z scope gmail.send + activityId email_in do ktorego odpowiadamy.
     *
     * POST /crm/reply/{activityId}
     * Body: subject, body_text, body_html?, to?
     *
     * Response JSON: ok, gmail_id, thread_id, error?
     */
    /**
     * FALA 20: Sugeruj cene dla jednego shipment (AJAX).
     * POST /crm/suggest-price?activity_id=X&shipment_index=N
     */
    public function suggestPriceJson(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post', 'get']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $companyNip = $identity?->get('company_nip');

        $activityId = trim((string)($this->request->getQuery('activity_id') ?: $this->request->getData('activity_id')));
        $shipIndex = (int)($this->request->getQuery('shipment_index') ?: $this->request->getData('shipment_index'));

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $Acts = $this->fetchTable('LeadActivities');
            $act = $Acts->get($activityId, ['contain' => ['Leads']]);
            if ((string)$act->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
            }
            $payload = json_decode((string)$act->payload_json, true) ?: [];
            $ships = $payload['shipments'] ?? [];
            if (!isset($ships[$shipIndex])) {
                return $json(['ok' => false, 'error' => 'shipment_index poza zakresem'], 400);
            }
            $shipment = $ships[$shipIndex];

            $Contracts = $this->fetchTable('CrmContracts');
            $suggestion = $Contracts->suggestPrice(
                (string)$companyId,
                $act->lead?->nip,
                $shipment,
                $companyNip
            );
            return $json(['ok' => true, 'suggestion' => $suggestion, 'shipment' => $shipment]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * FALA 20: Zapisz ceny dla wszystkich shipments w payload_json.
     * POST /crm/quote/{activityId}/save-prices
     * Body: prices[] = [{shipment_index: N, price: X, currency: Y}, ...]
     */
    public function savePricesJson(string $activityId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $Acts = $this->fetchTable('LeadActivities');
            $act = $Acts->get($activityId);
            if ((string)$act->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
            }
            $payload = json_decode((string)$act->payload_json, true) ?: [];
            $prices = (array)$this->request->getData('prices');
            foreach ($prices as $p) {
                $idx = (int)($p['shipment_index'] ?? -1);
                if (!isset($payload['shipments'][$idx])) continue;
                $payload['shipments'][$idx]['_quote_price'] = (float)($p['price'] ?? 0);
                $payload['shipments'][$idx]['_quote_currency'] = strtoupper((string)($p['currency'] ?? 'EUR'));
            }
            $payload['prices_saved_at'] = date('Y-m-d H:i:s');
            $act->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $Acts->save($act);
            return $json(['ok' => true, 'saved' => count($prices)]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * FALA 20: Wygeneruj PDF wyceny z quote_request.
     * GET /crm/quote/{activityId}/pdf?download=1
     */
    public function quotePdf(string $activityId): void
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Acts = $this->fetchTable('LeadActivities');
        $act = $Acts->get($activityId, ['contain' => ['Leads']]);
        if ((string)$act->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $payload = json_decode((string)$act->payload_json, true) ?: [];
        $shipments = $payload['shipments'] ?? [];
        $lead = $act->lead;

        // Total
        $total = 0.0;
        $currency = 'EUR';
        foreach ($shipments as $s) {
            $p = (float)($s['_quote_price'] ?? 0);
            if ($p > 0) {
                $total += $p;
                if (!empty($s['_quote_currency'])) $currency = $s['_quote_currency'];
            }
        }

        $issueDate = new \Cake\I18n\Date();
        $validUntil = $issueDate->modify('+7 days');
        $quoteNumber = 'WYC/' . $issueDate->format('Y/m') . '/' . strtoupper(substr($activityId, 0, 6));

        $download = (bool)$this->request->getQuery('download', 0);
        // Cake 5: setLayout(false) rzuca TypeError - trzeba disableAutoLayout()
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setTemplate('quote_response')
            ->setOptions([
                'pdfConfig' => [
                    'filename'    => 'Wycena-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$lead->company_name) . '-' . $issueDate->format('Y-m-d') . '.pdf',
                    'download'    => $download,
                    'orientation' => 'portrait',
                    'paper'       => 'A4',
                    'engine'      => 'CakePdf.DomPdf',
                ],
            ]);

        $this->set(compact('act', 'lead', 'shipments', 'total', 'currency',
            'quoteNumber', 'issueDate', 'validUntil', 'payload'));
    }

    /**
     * FALA 20: Wyslij PDF wyceny przez Gmail (reuse GmailApiService::sendMessage z attach).
     * POST /crm/quote/{activityId}/send
     * Body: to, subject?, body_text?
     */
    public function sendQuoteJson(string $activityId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId = $identity?->get('id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $Acts = $this->fetchTable('LeadActivities');
            $act = $Acts->get($activityId, ['contain' => ['Leads']]);
            if ((string)$act->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
            }
            $lead = $act->lead;
            $data = (array)$this->request->getData();
            $to = trim((string)($data['to'] ?? ($lead->email ?? '')));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $json(['ok' => false, 'error' => 'brak/zly adres odbiorcy'], 400);
            }
            $subject = trim((string)($data['subject'] ?? 'Wycena transportu')) ?: 'Wycena transportu';
            $bodyText = trim((string)($data['body_text'] ?? ''));
            if ($bodyText === '') {
                $bodyText = "Dzień dobry,\n\nw załączniku przesyłam wycenę transportu.\n\nW razie pytań pozostaję do dyspozycji.\n\nPozdrawiam";
            }

            // TEST MODE: przekierowanie na Crm.testEmailOverride
            $override = trim((string)\Cake\Core\Configure::read('Crm.testEmailOverride'));
            $originalTo = $to;
            if ($override !== '') {
                $to = $override;
                $subject = '[TEST → ' . $originalTo . '] ' . $subject;
                $bodyText = "!!! TRYB TESTOWY - mial isc do: {$originalTo} !!!\n\n" . $bodyText;
            }

            // Wygeneruj PDF do stringa
            $pdfContent = $this->renderQuotePdfString($act);
            if (!$pdfContent) {
                return $json(['ok' => false, 'error' => 'PDF generation failed'], 500);
            }

            // Znajdz Gmail account
            $EA = $this->fetchTable('CrmEmailAccounts');
            $acc = $EA->find()->where([
                'company_id' => $companyId,
                'auth_type' => 'gmail_oauth',
                'is_active' => true,
            ])->first();
            if (!$acc) {
                return $json(['ok' => false, 'error' => 'brak aktywnego Gmail OAuth'], 400);
            }

            $svc = new \App\Service\GmailApiService();
            $accessToken = $EA->decryptPassword($acc->oauth_access_token);
            if (!$acc->oauth_expires_at || $acc->oauth_expires_at->isPast()) {
                $tokens = $svc->refreshAccessToken($EA->decryptPassword($acc->oauth_refresh_token));
                $accessToken = $tokens['access_token'];
                $acc->oauth_access_token = $EA->encryptPassword($accessToken);
                $acc->oauth_expires_at = new \Cake\I18n\DateTime('+' . (int)($tokens['expires_in'] ?? 3600) . ' seconds');
                $EA->save($acc);
            }

            $fromName = trim(($identity?->get('first_name') ?? '') . ' ' . ($identity?->get('last_name') ?? '')) ?: 'CRM';
            $pdfFilename = 'Wycena-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$lead->company_name) . '.pdf';

            $result = $svc->sendMessageWithAttachment(
                $accessToken, $acc->username, $fromName, $to, $subject, $bodyText,
                $pdfContent, $pdfFilename, 'application/pdf'
            );
            if (!$result || empty($result['id'])) {
                return $json(['ok' => false, 'error' => 'Gmail sendMessage failed - moze brak scope gmail.send, re-authoryzuj konto'], 500);
            }

            // Log w timeline
            $payload = json_decode((string)$act->payload_json, true) ?: [];
            $payload['quote_sent_at'] = date('Y-m-d H:i:s');
            $payload['quote_sent_to'] = $to;
            $payload['quote_gmail_id'] = $result['id'];
            $act->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $Acts->save($act);

            try {
                $Acts->logSystem((string)$companyId, (string)$lead->id, 'offer_sent',
                    $subject,
                    sprintf('Wysłano wycenę PDF do %s (Gmail ID: %s)', $to, $result['id']),
                    ['source' => 'quote_pdf', 'gmail_id' => $result['id'], 'to' => $to,
                     'from_activity' => $activityId, 'shipments_count' => count($payload['shipments'] ?? [])],
                    $userId);
            } catch (\Throwable $e) {}

            return $json(['ok' => true, 'gmail_id' => $result['id']]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('sendQuoteJson exception: ' . $e->getMessage());
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper: renderuje quotePdf template do stringa (dla attach do email).
     */
    private function renderQuotePdfString($act): ?string
    {
        try {
            $payload = json_decode((string)$act->payload_json, true) ?: [];
            $shipments = $payload['shipments'] ?? [];
            $lead = $act->lead;
            $total = 0.0;
            $currency = 'EUR';
            foreach ($shipments as $s) {
                $p = (float)($s['_quote_price'] ?? 0);
                if ($p > 0) {
                    $total += $p;
                    if (!empty($s['_quote_currency'])) $currency = $s['_quote_currency'];
                }
            }
            $issueDate = new \Cake\I18n\Date();
            $validUntil = $issueDate->modify('+7 days');
            $quoteNumber = 'WYC/' . $issueDate->format('Y/m') . '/' . strtoupper(substr((string)$act->id, 0, 6));

            // Cake 5: setLayout(false) rzuca TypeError - disableAutoLayout() zamiast
            $viewBuilder = new \Cake\View\ViewBuilder();
            $viewBuilder->disableAutoLayout();
            $viewBuilder
                ->setClassName('CakePdf.Pdf')
                ->setTemplate('quote_response')
                ->setTemplatePath('Leads')
                ->setOptions([
                    'pdfConfig' => [
                        'orientation' => 'portrait',
                        'paper' => 'A4',
                        'engine' => 'CakePdf.DomPdf',
                    ],
                ]);
            $view = $viewBuilder->build([
                'act' => $act, 'lead' => $lead, 'shipments' => $shipments,
                'total' => $total, 'currency' => $currency,
                'quoteNumber' => $quoteNumber, 'issueDate' => $issueDate,
                'validUntil' => $validUntil, 'payload' => $payload,
            ]);
            return $view->render();
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('renderQuotePdfString failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * FALA 23: Download / inline preview zalacznika z email_in.
     * GET /crm/attachment/{activityId}/{index}?download=1
     *
     * Fetch Gmail attachment po attachment_id z crm_email_messages.attachments_json[index].
     * Streamuje binary do przegladarki z Content-Disposition inline (default) lub attachment.
     */
    /**
     * FALA extras: Archiwum leadow.
     * POST /crm/{id}/archive - ustawia archived_at = now
     * POST /crm/{id}/unarchive - ustawia archived_at = null
     * Zachowuje activities i wszystkie inne pola - tylko widocznosc w Kanban/default liscie.
     */
    /**
     * FALA extras: Upload zalacznika do leada.
     * POST /crm/{id}/attachments/upload (multipart form: file, note?)
     */
    public function attachmentUpload(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId = $identity?->get('id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->response;
        };

        try {
            $Leads = $this->fetchTable('Leads');
            $lead = $Leads->get($id);
            if ((string)$lead->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
            }

            $file = $this->request->getUploadedFile('file');
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                return $json(['ok' => false, 'error' => 'brak pliku lub blad uploadu'], 400);
            }
            $size = $file->getSize();
            if ($size > 20 * 1024 * 1024) {
                return $json(['ok' => false, 'error' => 'plik za duzy (max 20MB)'], 400);
            }

            $origName = $file->getClientFilename() ?: 'plik';
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            $ext = mb_strtolower($ext);
            if (strlen($ext) > 10) $ext = substr($ext, 0, 10);

            $uuid = \Cake\Utility\Text::uuid();
            $relDir = 'files/lead_attachments/' . $id;
            $absDir = WWW_ROOT . $relDir;
            if (!is_dir($absDir)) {
                @mkdir($absDir, 0755, true);
            }
            $filename = $uuid . ($ext ? '.' . $ext : '');
            $absPath = $absDir . DIRECTORY_SEPARATOR . $filename;
            $relPath = $relDir . '/' . $filename;

            $file->moveTo($absPath);

            $Att = $this->fetchTable('LeadAttachments');
            $entity = $Att->newEntity([
                'id' => $uuid,
                'company_id' => $companyId,
                'lead_id' => $id,
                'uploaded_by_user_id' => $userId,
                'filename' => mb_substr($origName, 0, 255),
                'path' => $relPath,
                'mime' => $file->getClientMediaType() ?: 'application/octet-stream',
                'size' => (int)$size,
                'note' => trim((string)($this->request->getData('note') ?? '')) ?: null,
            ]);
            if (!$Att->save($entity)) {
                @unlink($absPath);
                return $json(['ok' => false, 'error' => 'blad zapisu', 'details' => $entity->getErrors()], 500);
            }

            // Log activity - best effort
            try {
                $this->fetchTable('LeadActivities')->logSystem(
                    (string)$companyId, (string)$id, 'file',
                    __('Załącznik: {0}', $entity->filename),
                    $entity->note,
                    ['attachment_id' => $entity->id, 'size' => $entity->size, 'mime' => $entity->mime],
                    $userId
                );
            } catch (\Throwable $e) {}

            return $json([
                'ok' => true,
                'attachment' => [
                    'id' => $entity->id,
                    'filename' => $entity->filename,
                    'size' => $entity->size,
                    'mime' => $entity->mime,
                    'url' => '/' . $entity->path,
                ],
            ]);
        } catch (\Throwable $e) {
            return $json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * FALA extras: Download zalacznika.
     * GET /crm/attachment-file/{attachmentId}?download=1
     */
    public function attachmentFile(string $attachmentId): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Att = $this->fetchTable('LeadAttachments');
        $att = $Att->get($attachmentId);
        if ((string)$att->company_id !== (string)$companyId) throw new NotFoundException();

        $absPath = WWW_ROOT . $att->path;
        if (!is_file($absPath)) throw new NotFoundException('plik nie istnieje na dysku');

        $disposition = $this->request->getQuery('download') === '1' ? 'attachment' : 'inline';
        return $this->response
            ->withType($att->mime)
            ->withHeader('Content-Disposition', $disposition . '; filename="' . str_replace('"', '', $att->filename) . '"')
            ->withHeader('Content-Length', (string)filesize($absPath))
            ->withStringBody(file_get_contents($absPath));
    }

    /**
     * FALA extras: Usun zalacznik.
     */
    public function attachmentDelete(string $attachmentId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Att = $this->fetchTable('LeadAttachments');
        $att = $Att->get($attachmentId);
        if ((string)$att->company_id !== (string)$companyId) throw new NotFoundException();

        $leadId = $att->lead_id;
        $absPath = WWW_ROOT . $att->path;
        if (is_file($absPath)) @unlink($absPath);
        $Att->delete($att);
        $this->Flash->success(__('Załącznik usunięty.'));
        $this->redirect($this->request->referer() ?: ['action' => 'view', $leadId]);
        return $this->response;
    }

    /**
     * FALA extras: Przypisz/zamien etykiety dla leada (multi-assign).
     * POST /crm/{id}/labels z body: label_ids[] (array UUID)
     */
    public function assignLabels(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data));
            return $this->response;
        };

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id, ['contain' => ['LeadLabels']]);
        if ((string)$lead->company_id !== (string)$companyId) {
            return $json(['ok' => false, 'error' => 'brak dostepu'], 403);
        }
        $labelIds = (array)($this->request->getData('label_ids') ?? []);
        $labelIds = array_values(array_unique(array_filter($labelIds, 'is_string')));

        // Zbuduj entities z tych ID (belongsToMany replace strategy)
        $LeadLabels = $this->fetchTable('LeadLabels');
        $labels = $labelIds ? $LeadLabels->find()
            ->where(['company_id' => $companyId, 'id IN' => $labelIds])
            ->all()->toArray() : [];

        $lead = $Leads->patchEntity($lead, ['lead_labels' => $labels], ['associated' => ['LeadLabels']]);
        if ($Leads->save($lead, ['associated' => ['LeadLabels']])) {
            return $json(['ok' => true, 'count' => count($labels)]);
        }
        return $json(['ok' => false, 'error' => 'save fail'], 500);
    }

    public function archive(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $lead->archived_at = new \Cake\I18n\DateTime();
        if ($Leads->save($lead)) {
            $this->Flash->success(__('Lead zarchiwizowany. Widoczny tylko z filtrem "Pokaż archiwum".'));
            // Log activity - best effort
            try {
                $this->fetchTable('LeadActivities')->logSystem(
                    (string)$companyId, (string)$lead->id, 'note',
                    __('Zarchiwizowany'),
                    __('Lead schowany z Kanban i domyślnej listy. Aby przywrócić - filter "Pokaż archiwum" + button Przywróć.'),
                    ['action' => 'archive'], $identity?->get('id')
                );
            } catch (\Throwable $e) {}
        } else {
            $this->Flash->error(__('Błąd archiwizacji.'));
        }
        $this->redirect($this->request->referer() ?: ['action' => 'index']);
        return $this->response;
    }

    public function unarchive(string $id): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Leads = $this->fetchTable('Leads');
        $lead = $Leads->get($id);
        if ((string)$lead->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        $lead->archived_at = null;
        if ($Leads->save($lead)) {
            $this->Flash->success(__('Lead przywrócony z archiwum.'));
            try {
                $this->fetchTable('LeadActivities')->logSystem(
                    (string)$companyId, (string)$lead->id, 'note',
                    __('Przywrócony z archiwum'), null,
                    ['action' => 'unarchive'], $identity?->get('id')
                );
            } catch (\Throwable $e) {}
        } else {
            $this->Flash->error(__('Błąd przywracania.'));
        }
        $this->redirect($this->request->referer() ?: ['action' => 'view', $lead->id]);
        return $this->response;
    }

    public function attachmentDownload(string $activityId, string $index): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        @set_time_limit(120);
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        try {
            $Acts = $this->fetchTable('LeadActivities');
            $act = $Acts->get($activityId);
            if ((string)$act->company_id !== (string)$companyId) {
                throw new NotFoundException();
            }
            $payload = json_decode((string)$act->payload_json, true) ?: [];
            $gmailId = $payload['gmail_id'] ?? null;
            $accountId = $payload['account_id'] ?? null;
            if (!$gmailId || !$accountId) {
                throw new NotFoundException(__('Brak gmail_id lub account_id w activity - re-poll wiadomosc.'));
            }

            // Match crm_email_messages po lead_id + subject + from (najlepszy match)
            $Msg = $this->fetchTable('CrmEmailMessages');
            $msg = $Msg->find()
                ->where(['lead_id' => $act->lead_id, 'account_id' => $accountId])
                ->orderByDesc('received_at')
                ->all()->toArray();
            // Znajdz message z pasujacym subject
            $matched = null;
            foreach ($msg as $m) {
                if ((string)$m->subject === (string)$act->subject) { $matched = $m; break; }
            }
            if (!$matched) {
                throw new NotFoundException(__('Nie znaleziono crm_email_messages dla tej activity.'));
            }
            $atts = json_decode((string)$matched->attachments_json, true) ?: [];
            $idx = (int)$index;
            if (!isset($atts[$idx])) {
                throw new NotFoundException(__('Zalacznik o indeksie {0} nie istnieje ({1} zalacznikow).', $idx, count($atts)));
            }
            $att = $atts[$idx];
            $attId = $att['attachment_id'] ?? null;
            if (!$attId) {
                throw new NotFoundException(__('Zalacznik nie ma attachment_id - byl zapisany przed FALA 16. Reset Gmail history + Poll.'));
            }

            // Fetch Gmail account + refresh token
            $EA = $this->fetchTable('CrmEmailAccounts');
            $acc = $EA->get($accountId);
            $svc = new \App\Service\GmailApiService();
            $accessToken = $EA->decryptPassword($acc->oauth_access_token);
            if (!$acc->oauth_expires_at || $acc->oauth_expires_at->isPast()) {
                $tokens = $svc->refreshAccessToken($EA->decryptPassword($acc->oauth_refresh_token));
                $accessToken = $tokens['access_token'];
                $acc->oauth_access_token = $EA->encryptPassword($accessToken);
                $acc->oauth_expires_at = new \Cake\I18n\DateTime('+' . (int)($tokens['expires_in'] ?? 3600) . ' seconds');
                $EA->save($acc);
            }

            $binary = $svc->getAttachment($accessToken, $gmailId, $attId, 15 * 1024 * 1024);
            if ($binary === null) {
                throw new NotFoundException(__('Gmail API zwrocilo null - attachment prawdopodobnie usuniety lub token invalid.'));
            }

            $mime = (string)($att['mime'] ?? 'application/octet-stream');
            $filename = (string)($att['filename'] ?? 'attachment-' . $idx);
            $download = $this->request->getQuery('download') === '1';
            $disposition = $download ? 'attachment' : 'inline';

            $response = $this->response
                ->withType($mime)
                ->withHeader('Content-Disposition', $disposition . '; filename="' . str_replace('"', '', $filename) . '"')
                ->withHeader('Content-Length', (string)strlen($binary))
                ->withHeader('Cache-Control', 'private, max-age=3600')
                ->withStringBody($binary);
            return $response;
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('attachmentDownload exception: ' . $e->getMessage());
            throw new NotFoundException('Attachment fetch failed: ' . $e->getMessage());
        }
    }

    public function replyByGmail(string $activityId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId = $identity?->get('id');

        $json = function (array $data, int $code = 200) {
            $this->response = $this->response->withType('application/json')->withStatus($code);
            $this->response = $this->response->withStringBody(json_encode($data));
            return $this->response;
        };

        try {
            // Fetch original email_in activity
            $Acts = $this->fetchTable('LeadActivities');
            $orig = $Acts->get($activityId, ['contain' => ['Leads']]);
            if ((string)$orig->company_id !== (string)$companyId) {
                return $json(['ok' => false, 'error' => 'Brak dostepu do tego leada'], 403);
            }
            if ($orig->activity_type !== 'email_in') {
                return $json(['ok' => false, 'error' => 'Mozna odpowiadac tylko na email_in'], 400);
            }

            $lead = $orig->lead;
            $data = (array)$this->request->getData();
            $subject = trim((string)($data['subject'] ?? ''));
            $bodyText = trim((string)($data['body_text'] ?? ''));
            $bodyHtml = trim((string)($data['body_html'] ?? ''));
            $to = trim((string)($data['to'] ?? ''));

            if ($bodyText === '') {
                return $json(['ok' => false, 'error' => 'body_text jest wymagany'], 400);
            }

            // Znajdz original crm_email_message dla message_id (do threadingu)
            $origMsg = null;
            try {
                $Msg = $this->fetchTable('CrmEmailMessages');
                $origMsg = $Msg->find()
                    ->where(['lead_id' => $lead->id])
                    ->orderByDesc('received_at')
                    ->first();
            } catch (\Throwable $e) {}

            // Fallback: to = from oryginalnego maila lub lead.email
            $origPayload = json_decode((string)$orig->payload_json, true) ?: [];
            if ($to === '') {
                $to = $origPayload['from'] ?? $lead->email ?? '';
            }
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $json(['ok' => false, 'error' => 'Brak lub nieprawidlowy adres odbiorcy'], 400);
            }

            // Subject: dodaj 'Re: ' jesli brak
            if ($subject === '') {
                $subject = 'Re: ' . ($orig->subject ?: '(bez tematu)');
            } elseif (stripos($subject, 're:') !== 0 && stripos($subject, 'odp:') !== 0) {
                $subject = 'Re: ' . $subject;
            }

            // TEST MODE: przekierowanie na Crm.testEmailOverride
            $override = trim((string)\Cake\Core\Configure::read('Crm.testEmailOverride'));
            $originalTo = $to;
            if ($override !== '') {
                $to = $override;
                $subject = '[TEST → ' . $originalTo . '] ' . $subject;
                $bodyText = "!!! TRYB TESTOWY - mial isc do: {$originalTo} !!!\n\n" . $bodyText;
                if ($bodyHtml !== '') {
                    $bodyHtml = '<div style="background:#fff7ed;border:2px solid #ea580c;padding:10px;margin-bottom:12px;color:#7c2d12;font-family:sans-serif;font-size:12px;">'
                        . '⚠ TRYB TESTOWY - mial isc do: <strong>' . htmlspecialchars($originalTo) . '</strong></div>' . $bodyHtml;
                }
            }

            // Znajdz Gmail account firmy
            $EA = $this->fetchTable('CrmEmailAccounts');
            $acc = $EA->find()->where([
                'company_id' => $companyId,
                'auth_type' => 'gmail_oauth',
                'is_active' => true,
            ])->first();
            if (!$acc) {
                return $json(['ok' => false, 'error' => 'Brak aktywnego konta Gmail OAuth w tej firmie. Skonfiguruj w /crm/email-accounts'], 400);
            }

            $svc = new \App\Service\GmailApiService();
            $accessToken = $EA->decryptPassword($acc->oauth_access_token);
            $needsRefresh = !$acc->oauth_expires_at || $acc->oauth_expires_at->isPast();
            if ($needsRefresh) {
                $refreshToken = $EA->decryptPassword($acc->oauth_refresh_token);
                $tokens = $svc->refreshAccessToken($refreshToken);
                if (empty($tokens['access_token'])) {
                    return $json(['ok' => false, 'error' => 'Nie mozna odswiezyc access token - re-authoryzuj konto'], 500);
                }
                $accessToken = $tokens['access_token'];
                $acc->oauth_access_token = $EA->encryptPassword($accessToken);
                $acc->oauth_expires_at = new \Cake\I18n\DateTime('+' . (int)($tokens['expires_in'] ?? 3600) . ' seconds');
                $EA->save($acc);
            }

            $fromEmail = $acc->username;
            $fromName = trim(($identity?->get('first_name') ?? '') . ' ' . ($identity?->get('last_name') ?? '')) ?: 'CRM';

            $inReplyTo = $origMsg?->message_id ?: '';
            $references = $origMsg?->in_reply_to ? trim($origMsg->in_reply_to, '<>') . ' ' : '';
            $references .= $inReplyTo;
            $threadId = $origMsg?->thread_id ?: '';

            $result = $svc->sendMessage(
                $accessToken, $fromEmail, $fromName, $to,
                $subject, $bodyText, $bodyHtml,
                $inReplyTo, trim($references), $threadId
            );

            if (!$result || empty($result['id'])) {
                return $json(['ok' => false, 'error' => 'Gmail API sendMessage zwrocilo blad - sprawdz logi. Byc moze scope gmail.send jeszcze nie dodany - re-authoryzuj konto Gmail.'], 500);
            }

            // Log w timeline jako email_out
            try {
                $Acts->logSystem(
                    (string)$companyId, (string)$lead->id, 'email_out',
                    $subject,
                    mb_substr($bodyText, 0, 500),
                    [
                        'gmail_id' => $result['id'],
                        'thread_id' => $result['threadId'],
                        'to' => $to,
                        'from' => $fromEmail,
                        'reply_to_activity' => $activityId,
                        'sent_via' => 'gmail_api',
                    ],
                    $userId
                );
            } catch (\Throwable $e) {}

            // Update last_contacted_at
            try {
                $Leads = $this->fetchTable('Leads');
                $lead->last_contacted_at = new \Cake\I18n\DateTime();
                $Leads->save($lead);
            } catch (\Throwable $e) {}

            return $json(['ok' => true, 'gmail_id' => $result['id'], 'thread_id' => $result['threadId']]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('replyByGmail exception: ' . $e->getMessage());
            return $json(['ok' => false, 'error' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    public function createOrdersFromQuote(string $activityId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->get('id');

        $Acts = $this->fetchTable('LeadActivities');
        $act = $Acts->get($activityId, ['contain' => ['Leads']]);
        if ((string)$act->company_id !== (string)$companyId) {
            throw new NotFoundException();
        }
        if ($act->activity_type !== 'quote_request') {
            $this->Flash->error(__('Aktywność nie jest zapytaniem o wycenę.'));
            $this->redirect(['action' => 'view', $act->lead_id]);
            return $this->response;
        }

        $payload = !empty($act->payload_json) ? json_decode($act->payload_json, true) : [];
        $shipments = $payload['shipments'] ?? [];
        if (empty($shipments) || !is_array($shipments)) {
            $this->Flash->error(__('Brak zleceń w danych aktywności.'));
            $this->redirect(['action' => 'view', $act->lead_id]);
            return $this->response;
        }

        $lead = $act->lead;
        $SO = $this->fetchTable('SpeedOrders');
        $Notes = null;
        try { $Notes = $this->fetchTable('SpeedOrderNotes'); } catch (\Throwable $e) {}

        // Wygeneruj numer manualny dla kazdego zlecenia (przez ostatni manual_seq per rok+mc)
        $companyNip = (string)$identity?->get('company_nip');
        $today = new \Cake\I18n\Date();
        $rok = (int)$today->format('Y');
        $mc  = (int)$today->format('m');

        $created = 0;
        $errors = 0;
        foreach ($shipments as $s) {
            try {
                // Kolejny manual_seq per (company_nip, source=manual, rok, mc)
                $lastSeq = $SO->find()
                    ->where([
                        'company_nip' => $companyNip,
                        'source' => 'manual',
                        'rok' => $rok,
                        'mc'  => $mc,
                    ])
                    ->orderByDesc('manual_seq')
                    ->first();
                $newSeq = ($lastSeq && $lastSeq->manual_seq) ? ((int)$lastSeq->manual_seq + 1) : 1;
                $symbol = sprintf('M-%04d/%02d/%d', $newSeq, $mc, $rok);

                $order = $SO->newEntity([
                    'source' => 'manual',
                    'manual_seq' => $newSeq,
                    'company_nip' => $companyNip,
                    'rok' => $rok,
                    'mc'  => $mc,
                    'symbol' => $symbol,
                    'date_doc' => $today,
                    'buyer_name' => $lead->company_name,
                    'buyer_nip'  => $lead->nip,
                    'buyer_city' => $lead->city,
                    'buyer_country' => $lead->country_code,
                    'buyer_postal_code' => $lead->postal_code,
                    'buyer_street' => $lead->street,
                    'load_country' => $s['from_country'] ?? '',
                    'load_postal'  => $s['from_postal'] ?? '',
                    'load_city'    => $s['from_city'] ?? '',
                    'load_company' => $s['from_company'] ?? '',
                    'load_date'    => !empty($s['load_date']) ? $s['load_date'] : null,
                    'unload_country' => $s['to_country'] ?? '',
                    'unload_postal'  => $s['to_postal'] ?? '',
                    'unload_city'    => $s['to_city'] ?? '',
                    'unload_company' => $s['to_company'] ?? '',
                    'unload_date'    => !empty($s['unload_date']) ? $s['unload_date'] : null,
                    'cargo_weight_kg' => !empty($s['weight_kg']) ? (int)$s['weight_kg'] : null,
                    'cargo_pallets'   => !empty($s['pallets']) ? (int)$s['pallets'] : null,
                    'cargo_pallet_type' => $s['pallet_type'] ?? null,
                    'required_vehicle_type' => $s['vehicle_type'] ?? null,
                    'notes' => trim(($s['customer_order_ref'] ? 'Kundenbestellnummer/Ref: ' . $s['customer_order_ref'] . "\n" : '')
                        . ($s['cargo_type'] ? 'Ładunek: ' . $s['cargo_type'] . "\n" : '')
                        . ($s['notes'] ?? '')),
                    'currency' => 'EUR',
                    'vat_rate' => '0',
                    'netto' => 0,
                    'vat'   => 0,
                    'brutto' => 0,
                ]);
                if ($SO->save($order)) {
                    $created++;
                    if ($Notes) {
                        try {
                            $Notes->logSystem((string)$companyId, (string)$order->id,
                                sprintf('Utworzone automatycznie z zapytania o wycenę (lead #%s, aktywność %s).',
                                    $lead->company_name, $act->id),
                                ['source' => 'quote_request', 'lead_id' => $lead->id, 'activity_id' => $act->id]);
                        } catch (\Throwable $ee) {}
                    }
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        if ($created > 0) {
            // Zaktualizuj payload_json - dopisz flage created_orders + count
            $payload['orders_created_at'] = date('Y-m-d H:i:s');
            $payload['orders_created_count'] = $created;
            $payload['orders_created_by_user_id'] = $userId;
            $act->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $Acts->save($act);

            // Log w timeline
            try {
                $Acts->logSystem((string)$companyId, (string)$lead->id,
                    'note',
                    sprintf(__('Utworzono %d zleceń manualnych z zapytania o wycenę'), $created),
                    sprintf(__('Aktywność #%s zawierała %d zleceń, utworzono %d w bazie (%d błędów).'),
                        $act->id, count($shipments), $created, $errors),
                    ['source' => 'quote_extract_apply', 'activity_id' => $act->id],
                    $userId);
            } catch (\Throwable $e) {}
        }

        if ($errors > 0) {
            $this->Flash->warning(__('Utworzono {0} zleceń, {1} błędów.', $created, $errors));
        } else {
            $this->Flash->success(__('Utworzono {0} zleceń manualnych.', $created));
        }
        $this->redirect(['action' => 'view', $lead->id]);
        return $this->response;
    }
}

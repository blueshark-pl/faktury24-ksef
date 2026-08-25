<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use ArrayObject;

/**
 * CRM Leads (potencjalni klienci).
 *
 * @method \App\Model\Entity\Lead newEmptyEntity()
 * @method \App\Model\Entity\Lead get($id, array|string $finder = 'all', ?\Psr\SimpleCache\CacheInterface|string $cache = null, ?string $cacheKey = null, array $cacheOptions = [])
 */
class LeadsTable extends Table
{
    // LEGACY (spot pipeline default) - zachowane dla kompatybilnosci starych zapytan
    public const STAGES = ['new', 'contact', 'inquiry', 'offer', 'order', 'lost'];
    public const STAGE_PROBABILITY = [
        'new'     => 10,
        'contact' => 25,
        'inquiry' => 50,
        'offer'   => 75,
        'order'   => 100,
        'lost'    => 0,
    ];

    // FALA 21: Multi-pipeline dla spedycji - 3 rozne cykle sprzedazowe
    public const PIPELINE_TYPES = ['long_term', 'spot', 'recurring'];

    /**
     * Stages per pipeline_type. 'lost' zawsze na koncu jako fail state.
     * SPOT (default) uzywa starych stages new/contact/inquiry/offer/order/lost - backward compat.
     */
    public const PIPELINE_STAGES = [
        'long_term' => [
            'new',            // Kontakt zainicjowany
            'qualification',  // Weryfikacja potrzeb, budzetu, decyzyjnosci
            'proposal',       // Wysłana propozycja handlowa
            'negotiation',    // Rozmowa o warunkach kontraktu
            'contract',       // Podpisany kontrakt ramowy
            'active',         // Kontrakt czynny, generuje zlecenia
            'lost',
        ],
        'spot' => self::STAGES,  // legacy: new/contact/inquiry/offer/order/lost
        'recurring' => [
            'prospect',       // Potencjalny stały klient
            'trial',          // Pierwsze zlecenia probne (0-3 msc)
            'active',         // Regularne zlecenia (>3 msc)
            'churned',        // Odszedl / brak zlecen >60 dni
        ],
    ];

    /**
     * Skutecznosc per (pipeline, stage). Preset przy zmianie stage.
     * Long-term: dluzsze etapy = wolniejsze rosnace probability.
     * Spot: szybki cycle = strome krzywe.
     * Recurring: prospect/trial na dolnym plateau, active = 100.
     */
    public const PIPELINE_PROBABILITY = [
        'long_term' => [
            'new' => 5, 'qualification' => 20, 'proposal' => 40,
            'negotiation' => 65, 'contract' => 90, 'active' => 100, 'lost' => 0,
        ],
        'spot' => self::STAGE_PROBABILITY,
        'recurring' => [
            'prospect' => 15, 'trial' => 50, 'active' => 100, 'churned' => 0,
        ],
    ];

    // Ludzkie labelki dla dropdown pipeline_type
    public const PIPELINE_LABELS = [
        'long_term' => 'Kontrakt długoterminowy',
        'spot' => 'Zlecenie jednorazowe (spot)',
        'recurring' => 'Klient regularny (recurring)',
    ];

    /**
     * Pomocnicza: zwraca stages dla podanego pipeline_type (fallback do spot).
     */
    public static function stagesForPipeline(?string $pipelineType): array
    {
        $pt = $pipelineType ?: 'spot';
        return self::PIPELINE_STAGES[$pt] ?? self::PIPELINE_STAGES['spot'];
    }

    /**
     * Pomocnicza: zwraca probability preset dla (pipeline, stage). Null jesli nieznane.
     */
    public static function probabilityForStage(?string $pipelineType, string $stage): ?int
    {
        $pt = $pipelineType ?: 'spot';
        return self::PIPELINE_PROBABILITY[$pt][$stage] ?? null;
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('leads');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Contractors', ['foreignKey' => 'contractor_id', 'joinType' => 'LEFT']);
        $this->belongsTo('AssignedUser', [
            'className'  => 'Users',
            'foreignKey' => 'assigned_to_user_id',
            'joinType'   => 'LEFT',
        ]);
        $this->hasMany('LeadActivities', [
            'foreignKey' => 'lead_id',
            'sort'       => ['LeadActivities.happened_at' => 'DESC', 'LeadActivities.created' => 'DESC'],
            'dependent'  => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('company_name')->notEmptyString('company_name')
            ->maxLength('company_name', 255)
            ->allowEmptyString('nip')->maxLength('nip', 30)
            ->allowEmptyString('country_code')->maxLength('country_code', 2)
            ->allowEmptyString('city')->maxLength('city', 100)
            ->allowEmptyString('street')->maxLength('street', 255)
            ->allowEmptyString('email')->email('email', false, 'Nieprawidlowy email')
            ->allowEmptyString('phone')->maxLength('phone', 30)
            // FALA 21: walidacja stage uwzglednia pipeline_type (post-validation w beforeSave)
            // W validator: akceptujemy dowolny stage z UNII wszystkich pipelines, ostra walidacja w beforeSave
            ->inList('stage', array_unique(array_merge(...array_values(self::PIPELINE_STAGES))), 'Nieprawidlowy etap')
            ->range('probability', [0, 100])
            ->allowEmptyString('value_pln')->decimal('value_pln')
            ->inList('pipeline_type', self::PIPELINE_TYPES, 'Nieprawidlowy pipeline');

        return $validator;
    }

    /**
     * Preset skutecznosci + timestamp zmiany etapu + auto-flagi.
     */
    public function beforeSave(EventInterface $event, $entity, ArrayObject $options): void
    {
        // Zapamietaj poprzedni stage - dla afterSave (auto-thanks email)
        if (!$entity->isNew() && $entity->isDirty('stage')) {
            $entity->_previous_stage = (string)$entity->getOriginal('stage');
        }

        // FALA 21: default pipeline_type dla nowych rekordow
        if ($entity->isNew() && empty($entity->pipeline_type)) {
            $entity->pipeline_type = 'spot';
        }

        // Auto-preset probability przy pierwszym zapisie lub gdy zmienil sie etap
        if ($entity->isNew() || $entity->isDirty('stage')) {
            if ($entity->isDirty('stage')) {
                $entity->stage_changed_at = new DateTime();
            }
            // Ustawiaj probability tylko jesli user nie ustawil recznie
            // FALA 21: preset per (pipeline_type, stage) - fallback do legacy STAGE_PROBABILITY
            if (!$entity->isDirty('probability')) {
                $preset = self::probabilityForStage($entity->pipeline_type ?? 'spot', (string)$entity->stage);
                if ($preset !== null) {
                    $entity->probability = $preset;
                }
            }
        }

        // Auto-flagi z historii etapow (raz zaznaczone - zostaja)
        $stage = $entity->stage ?? 'new';
        if (in_array($stage, ['contact', 'inquiry', 'offer', 'order'], true)) {
            $entity->flag_contact = true;
        }
        if (in_array($stage, ['inquiry', 'offer', 'order'], true)) {
            $entity->flag_inquiry = true;
        }
        if (in_array($stage, ['offer', 'order'], true)) {
            $entity->flag_offer = true;
        }
        if ($stage === 'order') {
            $entity->flag_order = true;
        }
    }

    /**
     * Po zapisie - jesli lead trafil na stage=order i ma email -> wyslij thank-you.
     * Best-effort: try/catch, nie moze wywrocic save().
     * Idempotent: nie wysyla ponownie jesli poprzedni stage tez byl 'order'.
     * Kontrolowane przez Configure key 'Crm.autoThanksEnabled' (default true).
     */
    public function afterSave(EventInterface $event, $entity, ArrayObject $options): void
    {
        try {
            $enabled = (bool)\Cake\Core\Configure::read('Crm.autoThanksEnabled', true);
            if (!$enabled) return;

            $prev = (string)($entity->_previous_stage ?? '');
            if ($entity->stage !== 'order') return;
            if ($prev === 'order') return; // nie duplikujemy
            if (empty($entity->email)) return;
            if (!filter_var($entity->email, FILTER_VALIDATE_EMAIL)) return;

            $this->sendThankYouEmail($entity);
        } catch (\Throwable $e) {
            Log::warning('LeadsTable::afterSave thanks email failed: ' . $e->getMessage());
        }
    }

    /**
     * Wysyla email thank-you do kontaktu leada. Publiczna zeby mozna bylo
     * ewentualnie wywolac recznie z kontrolera (np. "wyslij ponownie").
     */
    public function sendThankYouEmail($lead): bool
    {
        try {
            // TEST MODE: Configure Crm.testEmailOverride redirecting wszystkie CRM maile
            // na jeden adres (dla testow przed uruchomieniem produkcji)
            $originalTo = (string)$lead->email;
            $override = trim((string)\Cake\Core\Configure::read('Crm.testEmailOverride'));
            $realTo = $override !== '' ? $override : $originalTo;
            $subject = sprintf(__d('crm', 'Dziękujemy za zaufanie – %s'), $lead->company_name);
            if ($override !== '') {
                $subject = '[TEST → ' . $originalTo . '] ' . $subject;
            }

            $mailer = new Mailer('default');
            $mailer->setTo($realTo, (string)($lead->contact_person ?? $lead->company_name))
                ->setSubject($subject)
                ->setEmailFormat('html')
                ->viewBuilder()->setLayout('default')->setTemplate('crm_lead_thanks');
            $mailer->setViewVars([
                'lead' => $lead,
                'testMode' => $override !== '',
                'originalTo' => $originalTo,
            ]);
            $mailer->deliver();

            // Log activity - best effort
            try {
                $Acts = $this->getAssociation('LeadActivities')->getTarget();
                $Acts->logSystem(
                    (string)$lead->company_id, (string)$lead->id, 'email_out',
                    __d('crm', 'Auto-thanks (stage=order)'),
                    $override !== ''
                        ? sprintf(__d('crm', 'TEST MODE: przekierowano do %s (miał iść do %s)'), $realTo, $originalTo)
                        : sprintf(__d('crm', 'Wysłano do %s'), $originalTo),
                    ['auto' => true, 'trigger' => 'stage_change_to_order',
                     'test_mode' => $override !== '', 'original_to' => $originalTo, 'sent_to' => $realTo],
                    null
                );
            } catch (\Throwable $e) {
                Log::warning('CRM auto-thanks activity log failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('CRM sendThankYouEmail failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Finder - aktywne leady dla firmy (pomija snoozed).
     */
    public function findActiveForCompany(SelectQuery $query, string $companyId): SelectQuery
    {
        return $query->where([
            'Leads.company_id' => $companyId,
            'OR' => [
                'Leads.snooze_until IS' => null,
                'Leads.snooze_until <=' => new DateTime(),
            ],
        ]);
    }

    /**
     * Top X leadow do dzwonienia dzis - rules-based scoring.
     *
     * Reguly (im wyzszy score, tym pilniejszy):
     *  - next_action_at przeterminowane        +50
     *  - next_action_at do 24h                 +30
     *  - stage: offer=25 / inquiry=20 / contact=15 / new=5 / lost=0
     *  - last_contacted_at NULL                +15
     *  - last_contacted_at > 14 dni temu       +20
     *  - last_contacted_at > 7 dni temu        +10
     *  - value_pln / 1000, max +30             (10k = +10, 30k = +30)
     *  - probability / 5, max +20              (100% = +20)
     *
     * @return array Lista rekordow z wyliczonym score, posortowana desc.
     */
    public function topPriority(string $companyId, ?string $userId = null, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();
        $in24h = $now->modify('+1 day');
        $days7 = $now->modify('-7 days');
        $days14 = $now->modify('-14 days');

        $query = $this->find()
            ->contain(['AssignedUser' => function ($q) {
                return $q->select(['id', 'first_name', 'last_name']);
            }])
            ->where([
                'Leads.company_id' => $companyId,
                'Leads.stage NOT IN' => ['order', 'lost'],
                // Pomin snoozed
                'OR' => [
                    'Leads.snooze_until IS' => null,
                    'Leads.snooze_until <=' => new DateTime(),
                ],
            ])
            ->limit(200); // Bierzemy 200 do wyliczenia, sortujemy w PHP

        if ($userId) {
            $query->where(['Leads.assigned_to_user_id' => $userId]);
        }
        $leads = $query->all();

        $stagePriority = ['offer' => 25, 'inquiry' => 20, 'contact' => 15, 'new' => 5, 'lost' => 0];
        $scored = [];
        foreach ($leads as $lead) {
            $score = 0;
            $reasons = [];

            // Next action urgency
            if ($lead->next_action_at) {
                $na = new \DateTimeImmutable($lead->next_action_at->format('c'));
                if ($na < $now) {
                    $score += 50;
                    $reasons[] = 'Przeterminowana akcja';
                } elseif ($na < $in24h) {
                    $score += 30;
                    $reasons[] = 'Akcja dzis';
                }
            }

            // Stage priority
            $sp = $stagePriority[$lead->stage] ?? 0;
            $score += $sp;
            if ($sp >= 20) $reasons[] = ucfirst($lead->stage);

            // Last contacted
            if (!$lead->last_contacted_at) {
                $score += 15;
                $reasons[] = 'Nigdy niekontaktowany';
            } else {
                $lc = new \DateTimeImmutable($lead->last_contacted_at->format('c'));
                if ($lc < $days14) {
                    $score += 20;
                    $reasons[] = '14+ dni bez kontaktu';
                } elseif ($lc < $days7) {
                    $score += 10;
                    $reasons[] = '7+ dni bez kontaktu';
                }
            }

            // Value
            $val = (float)($lead->value_pln ?? 0);
            $vs = (int)min(30, $val / 1000);
            $score += $vs;
            if ($vs >= 20) $reasons[] = 'Duza wartosc';

            // Probability
            $score += (int)(($lead->probability ?? 0) / 5);

            // KRS enrichment bonus: wielkosc firmy wg kapitalu (jesli w cache)
            if (!empty($lead->nip)) {
                try {
                    $krs = $this->getConnection()->execute(
                        'SELECT kapital_zakladowy FROM crm_krs_cache WHERE nip = :nip LIMIT 1',
                        ['nip' => $lead->nip]
                    )->fetch('assoc');
                    if ($krs && !empty($krs['kapital_zakladowy'])) {
                        $kap = (float)$krs['kapital_zakladowy'];
                        // 100k+ = +10, 1M+ = +20, 10M+ = +30 (log scaling)
                        if ($kap >= 10000000) {
                            $score += 30; $reasons[] = 'Duza spolka (kapital 10M+)';
                        } elseif ($kap >= 1000000) {
                            $score += 20; $reasons[] = 'Sredniej wielkosci (kapital 1M+)';
                        } elseif ($kap >= 100000) {
                            $score += 10;
                        }
                    }
                } catch (\Throwable $e) {}
            }

            $scored[] = [
                'lead'    => $lead,
                'score'   => $score,
                'reasons' => $reasons,
            ];
        }

        // Sort desc + limit
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * Statystyki pipeline dla dashboardu.
     * Zwraca array [stage => ['count' => X, 'value_pln' => Y]].
     */
    public function pipelineStats(string $companyId): array
    {
        $rows = $this->find()
            ->select([
                'stage',
                'cnt' => $this->find()->func()->count('*'),
                'sum' => $this->find()->func()->sum('value_pln'),
            ])
            ->where(['company_id' => $companyId])
            ->groupBy('stage')
            ->disableHydration()
            ->toArray();

        $stats = [];
        foreach (self::STAGES as $s) {
            $stats[$s] = ['count' => 0, 'value_pln' => 0.0];
        }
        foreach ($rows as $r) {
            $stats[$r['stage']] = [
                'count'     => (int)$r['cnt'],
                'value_pln' => (float)($r['sum'] ?? 0),
            ];
        }
        return $stats;
    }
}

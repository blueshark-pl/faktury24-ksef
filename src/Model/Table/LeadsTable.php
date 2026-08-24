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
    public const STAGES = ['new', 'contact', 'inquiry', 'offer', 'order', 'lost'];

    // Domyslne skutecznosci per etap - preset przy zmianie stage
    public const STAGE_PROBABILITY = [
        'new'     => 10,
        'contact' => 25,
        'inquiry' => 50,
        'offer'   => 75,
        'order'   => 100,
        'lost'    => 0,
    ];

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
            ->inList('stage', self::STAGES, 'Nieprawidlowy etap')
            ->range('probability', [0, 100])
            ->allowEmptyString('value_pln')->decimal('value_pln');

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

        // Auto-preset probability przy pierwszym zapisie lub gdy zmienil sie etap
        if ($entity->isNew() || $entity->isDirty('stage')) {
            if ($entity->isDirty('stage')) {
                $entity->stage_changed_at = new DateTime();
            }
            // Ustawiaj probability tylko jesli user nie ustawil recznie
            if (!$entity->isDirty('probability') && isset(self::STAGE_PROBABILITY[$entity->stage])) {
                $entity->probability = self::STAGE_PROBABILITY[$entity->stage];
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
            $mailer = new Mailer('default');
            $mailer->setTo((string)$lead->email, (string)($lead->contact_person ?? $lead->company_name))
                ->setSubject(sprintf(__d('crm', 'Dziękujemy za zaufanie – %s'), $lead->company_name))
                ->setEmailFormat('html')
                ->viewBuilder()->setLayout('default')->setTemplate('crm_lead_thanks');
            $mailer->setViewVars([
                'lead' => $lead,
            ]);
            $mailer->deliver();

            // Log activity - best effort
            try {
                $Acts = $this->getAssociation('LeadActivities')->getTarget();
                $Acts->logSystem(
                    (string)$lead->company_id, (string)$lead->id, 'email_out',
                    __d('crm', 'Auto-thanks (stage=order)'),
                    sprintf(__d('crm', 'Wysłano do %s'), $lead->email),
                    ['auto' => true, 'trigger' => 'stage_change_to_order'],
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

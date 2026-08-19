<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
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

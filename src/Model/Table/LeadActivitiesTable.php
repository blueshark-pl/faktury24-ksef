<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CRM Timeline aktywnosci per lead.
 *
 * @method \App\Model\Entity\LeadActivity newEmptyEntity()
 * @method \App\Model\Entity\LeadActivity get($id, array|string $finder = 'all', ?\Psr\SimpleCache\CacheInterface|string $cache = null, ?string $cacheKey = null, array $cacheOptions = [])
 */
class LeadActivitiesTable extends Table
{
    public const TYPES = [
        'phone_call', 'email_out', 'email_in', 'meeting', 'note', 'task',
        'file', 'stage_change', 'assignment', 'offer_sent', 'order_won', 'order_lost',
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('lead_activities');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Leads', ['foreignKey' => 'lead_id', 'joinType' => 'INNER']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->uuid('lead_id')->notEmptyString('lead_id')
            ->inList('activity_type', self::TYPES, 'Nieprawidlowy typ aktywnosci')
            ->allowEmptyString('subject')->maxLength('subject', 255)
            ->allowEmptyString('body');
        return $validator;
    }

    /**
     * Best-effort log systemowy (nie moze wywrocic glownego flow).
     * Uzywac zamiast recznego save w kontrolerze dla eventow typu stage_change.
     */
    public function logSystem(
        string $companyId,
        string $leadId,
        string $type,
        ?string $subject = null,
        ?string $body = null,
        array $payload = [],
        ?string $userId = null
    ): void {
        try {
            $entity = $this->newEntity([
                'company_id'    => $companyId,
                'lead_id'       => $leadId,
                'user_id'       => $userId,
                'activity_type' => $type,
                'subject'       => $subject,
                'body'          => $body,
                'happened_at'   => new \Cake\I18n\DateTime(),
                'payload_json'  => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $this->save($entity);
        } catch (\Throwable $e) {
            Log::warning('LeadActivities::logSystem failed: ' . $e->getMessage());
        }
    }
}

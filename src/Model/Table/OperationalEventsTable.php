<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

class OperationalEventsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('operational_events');
        $this->setPrimaryKey('id');
        // Nie dodajemy Timestamp behavior — 'created' ustawiamy domyslnie z DB, brak 'modified' (append-only)
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->scalar('entity_type')->maxLength('entity_type', 40)->notEmptyString('entity_type');
        $validator
            ->scalar('entity_id')->maxLength('entity_id', 40)->notEmptyString('entity_id');
        $validator
            ->scalar('event_name')->maxLength('event_name', 40)->notEmptyString('event_name');

        return $validator;
    }

    /**
     * Convenience helper: log event z automatycznym uuidem i timestampem.
     *
     * @param string $companyId
     * @param string $entityType route_plan|route_offer|speed_order|...
     * @param string|int $entityId
     * @param string $eventName created|updated|status_changed|...
     * @param string|null $userId
     * @param array $payload  Metadane
     * @param array $context  Opcjonalne: ip, user_agent, impersonated_by
     */
    public function log(
        string $companyId,
        string $entityType,
        string|int $entityId,
        string $eventName,
        ?string $userId = null,
        array $payload = [],
        array $context = []
    ): void {
        $entity = $this->newEntity([
            'id'                       => Text::uuid(),
            'company_id'               => $companyId,
            'entity_type'              => $entityType,
            'entity_id'                => (string)$entityId,
            'event_name'               => $eventName,
            'user_id'                  => $userId,
            'impersonated_by_user_id'  => $context['impersonated_by'] ?? null,
            'payload_json'             => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address'               => $context['ip'] ?? null,
            'user_agent'               => isset($context['user_agent']) ? substr((string)$context['user_agent'], 0, 255) : null,
        ]);
        // Best-effort — nie rzucamy exception, bo log nie moze psuc glownego flow
        try {
            $this->save($entity);
        } catch (\Throwable) {
            // ignore
        }
    }
}

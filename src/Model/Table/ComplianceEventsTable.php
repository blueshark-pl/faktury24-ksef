<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

class ComplianceEventsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('compliance_events');
        $this->setPrimaryKey('id');
        // Log-only, brak modified

        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id', 'joinType' => 'LEFT']);
        $this->belongsTo('RouteOffers', ['foreignKey' => 'route_offer_id', 'joinType' => 'LEFT']);
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Drivers', ['foreignKey' => 'driver_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Vehicles', ['foreignKey' => 'vehicle_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Trailers', ['foreignKey' => 'trailer_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->scalar('event_type')->notEmptyString('event_type');
        $validator
            ->scalar('severity')->inList('severity', ['info', 'warning', 'error']);

        return $validator;
    }

    /**
     * Helper: dodaj wpis compliance jednym wywolaniem.
     * Best-effort — try/catch wewnatrz, log nie moze psuc glownego flow.
     */
    public function record(
        string $companyId,
        string $eventType,
        string $description,
        string $severity = 'warning',
        array $context = [],
        array $links = []
    ): void {
        $entity = $this->newEntity([
            'id'             => Text::uuid(),
            'company_id'     => $companyId,
            'event_type'     => $eventType,
            'severity'       => $severity,
            'description'    => $description,
            'context_json'   => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'route_plan_id'  => $links['route_plan_id']  ?? null,
            'route_offer_id' => $links['route_offer_id'] ?? null,
            'speed_order_id' => $links['speed_order_id'] ?? null,
            'driver_id'      => $links['driver_id']      ?? null,
            'vehicle_id'     => $links['vehicle_id']     ?? null,
            'trailer_id'     => $links['trailer_id']     ?? null,
        ]);
        try {
            $this->save($entity);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Aktywne (nie-dismissed) ostrzezenia dla dashboardu.
     */
    public function findActiveForCompany(string $companyId, int $days = 30): \Cake\ORM\Query\SelectQuery
    {
        return $this->find()
            ->where([
                'ComplianceEvents.company_id'   => $companyId,
                'ComplianceEvents.is_dismissed' => false,
                'ComplianceEvents.detected_at >=' => (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s'),
            ])
            ->contain([
                'Drivers'  => function ($q) { return $q->select(['id', 'full_name']); },
                'Vehicles' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'Trailers' => function ($q) { return $q->select(['id', 'name', 'plate']); },
            ])
            ->orderByDesc('ComplianceEvents.detected_at');
    }
}

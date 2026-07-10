<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TripEventsTable extends Table
{
    public const EVENT_TYPES = [
        'departure'            => 'Wyjazd z bazy',
        'arrival'              => 'Przybycie na miejsce',
        'loading_started'      => 'Rozpoczęcie załadunku',
        'loading_completed'    => 'Zakończenie załadunku',
        'unloading_started'    => 'Rozpoczęcie rozładunku',
        'unloading_completed'  => 'Zakończenie rozładunku',
        'border_crossed'       => 'Przekroczenie granicy',
        'delay_reported'       => 'Opóźnienie',
        'pod_uploaded'         => 'POD załadowany',
        'cmr_signed'           => 'CMR podpisany',
        'incident'             => 'Incydent',
        'note'                 => 'Notatka',
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('trip_events');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id']);
        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Drivers', ['foreignKey' => 'driver_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->integer('speed_order_id')->notEmptyString('speed_order_id');
        $validator
            ->scalar('event_type')->inList('event_type', array_keys(self::EVENT_TYPES));
        $validator
            ->scalar('source')->inList('source', ['operator', 'driver_mobile', 'gps_track', 'api_webhook', 'system']);

        return $validator;
    }

    /**
     * Timeline zdarzen dla konkretnego zlecenia (od najstarszego).
     */
    public function timelineForOrder(int $speedOrderId): array
    {
        return $this->find()
            ->where(['speed_order_id' => $speedOrderId])
            ->contain([
                'Drivers' => function ($q) { return $q->select(['id', 'full_name']); },
            ])
            ->orderByAsc('happened_at')
            ->all()
            ->toArray();
    }
}

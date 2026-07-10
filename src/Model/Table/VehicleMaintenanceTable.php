<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class VehicleMaintenanceTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('vehicle_maintenance');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Vehicles', ['foreignKey' => 'vehicle_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Trailers', ['foreignKey' => 'trailer_id', 'joinType' => 'LEFT']);
        $this->belongsTo('CostInvoices', ['foreignKey' => 'cost_invoice_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->scalar('maintenance_type')
            ->inList('maintenance_type', [
                'technical_inspection', 'service', 'tacho_calibration',
                'adr_cert', 'insurance', 'oc', 'ac',
                'extinguisher', 'first_aid', 'other'
            ]);

        // XOR
        $validator->add('vehicle_id', 'xor', [
            'rule' => function ($value, $context) {
                $v = !empty($value);
                $t = !empty($context['data']['trailer_id'] ?? null);
                return ($v && !$t) || (!$v && $t) ? true : 'Podaj DOKŁADNIE jedno: pojazd LUB naczepę';
            },
        ]);

        return $validator;
    }

    /**
     * Rekordy wygasajace w ciagu N dni (dla dashboarda i cron alertow).
     */
    public function findExpiringSoon(string $companyId, int $days = 30): \Cake\ORM\Query\SelectQuery
    {
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify("+{$days} days");
        return $this->find()
            ->where([
                'VehicleMaintenance.company_id' => $companyId,
                'VehicleMaintenance.is_active'  => true,
                'VehicleMaintenance.valid_until IS NOT' => null,
                'VehicleMaintenance.valid_until <=' => $limit->format('Y-m-d'),
            ])
            ->contain([
                'Vehicles' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'Trailers' => function ($q) { return $q->select(['id', 'name', 'plate']); },
            ])
            ->orderByAsc('VehicleMaintenance.valid_until');
    }

    /**
     * Sprawdz czy pojazd/naczepa maja WSZYSTKIE aktualne dokumenty
     * na dany dzien (dla compliance check w planerze).
     * Zwraca array<string maintenance_type> braku (empty = wszystko OK).
     */
    public function findMissingForDate(string $companyId, string $assetType, string $assetId, \DateTimeInterface $date, array $requiredTypes = ['technical_inspection', 'insurance']): array
    {
        $foreignKey = $assetType === 'vehicle' ? 'vehicle_id' : 'trailer_id';
        $dateStr = $date->format('Y-m-d');

        $found = $this->find()
            ->select(['maintenance_type'])
            ->where([
                'company_id'    => $companyId,
                $foreignKey     => $assetId,
                'is_active'     => true,
                'valid_until >=' => $dateStr,
                'maintenance_type IN' => $requiredTypes,
            ])
            ->all()
            ->extract('maintenance_type')
            ->toArray();

        return array_values(array_diff($requiredTypes, $found));
    }
}

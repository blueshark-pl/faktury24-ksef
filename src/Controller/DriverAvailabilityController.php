<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Wzorce dostepnosci kierowcy — 7 dni na 1 kierowce.
 *
 * Akcje:
 *   index       GET  /dostepnosc-kierowcow            — lista kierowcow z podsumowaniem
 *   edit        GET  /dostepnosc-kierowcow/{driverId} — edycja 7 dni na jednej stronie
 *               POST                                  — zapis wszystkich 7 dni razem
 */
class DriverAvailabilityController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $Drivers = $this->fetchTable('Drivers');
        $drivers = $Drivers->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->select(['id', 'full_name', 'adr_certified'])
            ->orderByAsc('full_name')
            ->all();

        // Dla kazdego kierowcy — ile dni ma zdefiniowanych + preferencje
        $DA = $this->fetchTable('DriverAvailability');
        $summary = [];
        foreach ($drivers as $d) {
            $rows = $DA->find()
                ->where(['driver_id' => $d->id])
                ->orderByAsc('day_of_week')
                ->all()
                ->toArray();
            $activeDays = count(array_filter($rows, fn ($r) => !empty($r->shift_start)));
            $summary[(string)$d->id] = [
                'defined_days' => count($rows),
                'active_days'  => $activeDays,
                'accepts_adr'  => !empty($rows) && !empty($rows[0]->accepts_adr),
                'accepts_intl' => !empty($rows) && !empty($rows[0]->accepts_international),
            ];
        }

        $this->set(compact('drivers', 'summary'));
        $this->set('title', 'Dostępność kierowców');
    }

    public function edit(string $driverId): ?Response
    {
        $companyId = $this->companyId();

        $Drivers = $this->fetchTable('Drivers');
        $driver = $Drivers->find()
            ->where(['id' => $driverId, 'company_id' => $companyId])
            ->firstOrFail();

        $DA = $this->fetchTable('DriverAvailability');

        if ($this->request->is(['post', 'put', 'patch'])) {
            $conn = $DA->getConnection();
            $conn->begin();
            try {
                $DA->deleteAll(['driver_id' => $driverId]);
                $days = (array)$this->request->getData('days', []);
                foreach ($days as $dow => $vals) {
                    $dow = (int)$dow;
                    if ($dow < 1 || $dow > 7) continue;
                    $entity = $DA->newEntity([
                        'id'                     => Text::uuid(),
                        'company_id'             => $companyId,
                        'driver_id'              => $driverId,
                        'day_of_week'            => $dow,
                        'shift_start'            => !empty($vals['shift_start']) ? $vals['shift_start'] : null,
                        'shift_end'              => !empty($vals['shift_end']) ? $vals['shift_end'] : null,
                        'max_hours_this_day'     => !empty($vals['max_hours_this_day']) ? (int)$vals['max_hours_this_day'] : null,
                        'accepts_international'  => !empty($vals['accepts_international']),
                        'accepts_adr'            => !empty($vals['accepts_adr']),
                        'accepts_night'          => !empty($vals['accepts_night']),
                        'accepts_weekend'        => !empty($vals['accepts_weekend']),
                    ]);
                    if (!$DA->save($entity)) {
                        throw new \RuntimeException('Bład zapisu dnia ' . $dow);
                    }
                }
                $conn->commit();
                $this->Flash->success('Wzorzec dostępności zapisany.');
                return $this->redirect(['action' => 'index']);
            } catch (\Throwable $e) {
                $conn->rollback();
                $this->Flash->error('Błąd zapisu: ' . $e->getMessage());
            }
        }

        $rows = $DA->find()->where(['driver_id' => $driverId])->orderByAsc('day_of_week')->all()->toArray();
        $byDow = [];
        foreach ($rows as $r) { $byDow[(int)$r->day_of_week] = $r; }

        $this->set(compact('driver', 'byDow'));
        $this->set('title', 'Dostępność: ' . ($driver->full_name ?? ''));
        return null;
    }
}

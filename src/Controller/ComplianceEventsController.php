<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Dashboard ryzyk compliance. Read-only (wpisy sa dodawane automatycznie z
 * innych modulow poprzez ComplianceEventsTable::record). Operator moze tylko
 * "dismiss" ostrzezenie z uzasadnieniem do audytu.
 *
 * Akcje:
 *   index    GET  /ryzyko           — lista aktywnych ostrzezen z filtrami
 *   dismiss  POST /ryzyko/akceptuj/{id}  — akceptuj ryzyko z uzasadnieniem
 */
class ComplianceEventsController extends AppController
{
    private function companyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
    }

    private function userId(): ?string
    {
        return $this->request->getAttribute('identity')?->getIdentifier();
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $companyId = $this->companyId();

        $severity = (string)$this->request->getQuery('severity', '');
        $showDismissed = (string)$this->request->getQuery('dismissed', '') === '1';
        $days = (int)$this->request->getQuery('days', 30);

        $CE = $this->fetchTable('ComplianceEvents');
        $query = $CE->find()
            ->where(['ComplianceEvents.company_id' => $companyId])
            ->contain([
                'Drivers'  => function ($q) { return $q->select(['id', 'full_name']); },
                'Vehicles' => function ($q) { return $q->select(['id', 'name', 'plate']); },
                'Trailers' => function ($q) { return $q->select(['id', 'name', 'plate']); },
            ])
            ->orderByDesc('ComplianceEvents.detected_at');

        if (!$showDismissed) {
            $query->where(['ComplianceEvents.is_dismissed' => false]);
        }
        if ($severity !== '') {
            $query->where(['ComplianceEvents.severity' => $severity]);
        }
        if ($days > 0) {
            $query->where(['ComplianceEvents.detected_at >=' => (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s')]);
        }

        $events = $this->paginate($query, ['limit' => 40]);

        // Statystyki
        $stats = [
            'total_active' => $CE->find()
                ->where(['company_id' => $companyId, 'is_dismissed' => false])->count(),
            'errors' => $CE->find()
                ->where(['company_id' => $companyId, 'is_dismissed' => false, 'severity' => 'error'])->count(),
            'warnings' => $CE->find()
                ->where(['company_id' => $companyId, 'is_dismissed' => false, 'severity' => 'warning'])->count(),
        ];

        $this->set(compact('events', 'stats', 'severity', 'showDismissed', 'days'));
        $this->set('title', 'Ryzyko / Compliance');
    }

    public function dismiss(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->companyId();

        $CE = $this->fetchTable('ComplianceEvents');
        $event = $CE->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();

        $event->is_dismissed = true;
        $event->dismissed_by_user_id = $this->userId();
        $event->dismissed_at = new \DateTime();
        $event->dismissal_reason = (string)$this->request->getData('reason', '');

        if ($CE->save($event)) {
            $this->Flash->success(__('Ryzyko zaakceptowane. Wpis zachowany do audytu.'));
        } else {
            $this->Flash->error(__('Nie udało się zapisać.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}

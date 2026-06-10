<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\MaintenanceService;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;

/**
 * Panel sterowania trybem przerwy technicznej (maintenance).
 * Dostęp wyłącznie dla administratorów.
 *
 * GET  /admin/maintenance  — formularz
 * POST /admin/maintenance  — zapis stanu do pliku-flagi
 */
class MaintenanceController extends AppController
{
    private MaintenanceService $maint;

    public function initialize(): void
    {
        parent::initialize();
        $this->maint = new MaintenanceService();
    }

    public function beforeFilter(EventInterface $event)
    {
        $result = parent::beforeFilter($event);
        if ($result !== null) {
            return $result;
        }

        // Twarda blokada: tylko administrator (defense-in-depth obok RBAC).
        $identity = $this->request->getAttribute('identity');
        $isAdmin = (bool)($identity?->get('is_admin') ?? false)
            || strtolower((string)($identity?->get('role') ?? '')) === 'admin';
        if (!$isAdmin) {
            throw new ForbiddenException('Brak dostępu.');
        }

        return null;
    }

    public function index()
    {
        $this->set('state', $this->maint->state());
        $this->set('active', $this->maint->isActive());
        $this->set('flagPath', $this->maint->flagPath());
    }

    public function save()
    {
        $this->request->allowMethod(['post']);
        $d = (array)$this->request->getData();

        $norm = static function ($v): ?string {
            $v = trim((string)($v ?? ''));

            return $v !== '' ? $v : null;
        };

        $state = [
            'enabled'        => !empty($d['enabled']),
            'message'        => trim((string)($d['message'] ?? '')),
            'notice_message' => trim((string)($d['notice_message'] ?? '')),
            'from'        => $norm($d['from'] ?? null),
            'to'          => $norm($d['to'] ?? null),
            'notice_from' => $norm($d['notice_from'] ?? null),
            'allow_cron'  => !empty($d['allow_cron']),
        ];

        if ($this->maint->save($state)) {
            $this->Flash->success($state['enabled']
                ? 'Zapisano. Tryb przerwy technicznej jest WŁĄCZONY (wg ustawionego okna).'
                : 'Zapisano. Tryb przerwy technicznej jest WYŁĄCZONY.');
        } else {
            $this->Flash->error('Nie udało się zapisać pliku ' . h($this->maint->flagPath()) . ' — sprawdź uprawnienia katalogu config/.');
        }

        return $this->redirect(['action' => 'index']);
    }
}

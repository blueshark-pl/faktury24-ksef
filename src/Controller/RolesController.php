<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Admin — zarządzanie rolami i ich uprawnieniami.
 *
 * Role systemowe (is_system=1) mają zablokowaną zmianę kodu i kasowanie.
 * Admin może zmieniać im uprawnienia, nazwę i opis.
 */
class RolesController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($r = $this->requireAdmin()) {
            $event->setResult($r);
        }
    }

    public function index(): void
    {
        $Roles = $this->fetchTable('Roles');
        $roles = $Roles->find()
            ->contain(['Permissions' => function (\Cake\ORM\Query\SelectQuery $q) {
                return $q->select(['Permissions.id']);
            }])
            ->orderByDesc('Roles.is_system')
            ->orderBy(['Roles.name' => 'ASC'])
            ->all();

        // Liczba userów per rola (po users.role = roles.code)
        $userCounts = $this->fetchTable('Users')->find()
            ->select(['role', 'cnt' => $this->fetchTable('Users')->find()->func()->count('*')])
            ->groupBy('role')
            ->disableHydration()
            ->all()
            ->toArray();
        $countByCode = [];
        foreach ($userCounts as $row) {
            $countByCode[(string)($row['role'] ?? '')] = (int)$row['cnt'];
        }

        $this->set(compact('roles', 'countByCode'));
    }

    public function add(): ?Response
    {
        $Roles = $this->fetchTable('Roles');
        $role  = $Roles->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $code = $this->normalizeCode((string)($data['code'] ?? ''));

            if ($code === '') {
                $this->Flash->error(__('Podaj kod roli.'));
            } elseif ($Roles->exists(['code' => $code])) {
                $this->Flash->error(__('Rola o tym kodzie już istnieje.'));
            } else {
                $role = $Roles->newEntity([
                    'code'        => $code,
                    'name'        => trim((string)($data['name'] ?? $code)),
                    'description' => trim((string)($data['description'] ?? '')) ?: null,
                    'is_system'   => false,
                    'is_active'   => !empty($data['is_active']),
                ]);
                if ($Roles->save($role)) {
                    $this->Flash->success(__('Rola dodana. Możesz teraz przypisać uprawnienia.'));
                    return $this->redirect(['action' => 'edit', $role->id]);
                }
                $this->Flash->error(__('Nie udało się zapisać roli.'));
            }
        }

        $this->set(compact('role'));
        return null;
    }

    public function edit(int $id): ?Response
    {
        $Roles       = $this->fetchTable('Roles');
        $Permissions = $this->fetchTable('Permissions');

        $role = $Roles->find()
            ->where(['Roles.id' => $id])
            ->contain(['Permissions' => function (\Cake\ORM\Query\SelectQuery $q) {
                return $q->select(['Permissions.id']);
            }])
            ->first();
        if (!$role) {
            throw new NotFoundException(__('Rola nie istnieje.'));
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();

            // Zmiana danych podstawowych — kodu nie ruszamy dla ról systemowych
            $patch = [
                'name'        => trim((string)($data['name'] ?? $role->name)),
                'description' => trim((string)($data['description'] ?? '')) ?: null,
                'is_active'   => !empty($data['is_active']),
            ];
            if (!$role->is_system) {
                $newCode = $this->normalizeCode((string)($data['code'] ?? $role->code));
                if ($newCode !== '' && $newCode !== $role->code) {
                    $conflict = $Roles->exists(['code' => $newCode, 'id !=' => $role->id]);
                    if ($conflict) {
                        $this->Flash->error(__('Inna rola ma już ten kod.'));
                        $this->set(compact('role'));
                        $this->set('permissionsByCategory', $this->groupPermissions($Permissions));
                        $this->set('selectedPermissionIds', $this->extractIds($role->permissions ?? []));
                        return null;
                    }
                    $patch['code'] = $newCode;
                }
            }

            // Uprawnienia — checkboxy
            $selected = (array)($data['permissions'] ?? []);
            $selected = array_values(array_unique(array_map('intval', $selected)));
            $patch['permissions'] = $Permissions->find()
                ->where(['Permissions.id IN' => $selected ?: [-1]])
                ->all()
                ->toArray();

            $role = $Roles->patchEntity($role, $patch, [
                'associated' => ['Permissions' => ['onlyIds' => false]],
            ]);

            if ($Roles->save($role)) {
                $this->Flash->success(__('Zapisano rolę i jej uprawnienia.'));
                return $this->redirect(['action' => 'edit', $role->id]);
            }
            $this->Flash->error(__('Nie udało się zapisać.'));
        }

        $this->set(compact('role'));
        $this->set('permissionsByCategory', $this->groupPermissions($Permissions));
        $this->set('selectedPermissionIds', $this->extractIds($role->permissions ?? []));
        return null;
    }

    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $Roles = $this->fetchTable('Roles');
        $role  = $Roles->find()->where(['id' => $id])->first();
        if (!$role) {
            throw new NotFoundException(__('Rola nie istnieje.'));
        }
        if ($role->is_system) {
            $this->Flash->error(__('Nie można usunąć roli systemowej.'));
            return $this->redirect(['action' => 'index']);
        }

        // Czy ktokolwiek ma tę rolę?
        $usersCount = $this->fetchTable('Users')->find()->where(['role' => $role->code])->count();
        if ($usersCount > 0) {
            $this->Flash->error(__('Nie można usunąć — {0} użytkowników ma tę rolę. Najpierw zmień im rolę.', $usersCount));
            return $this->redirect(['action' => 'index']);
        }

        if ($Roles->delete($role)) {
            $this->Flash->success(__('Rola usunięta.'));
        } else {
            $this->Flash->error(__('Nie udało się usunąć roli.'));
        }
        return $this->redirect(['action' => 'index']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
        $code = trim((string)$code, '_');
        return $code;
    }

    /** @return array<string, \Cake\Datasource\ResultSetInterface> */
    private function groupPermissions($PermissionsTable): array
    {
        $all = $PermissionsTable->find()
            ->orderBy(['Permissions.category' => 'ASC', 'Permissions.name' => 'ASC'])
            ->all();

        $grouped = [];
        foreach ($all as $p) {
            $cat = (string)$p->category;
            if (!isset($grouped[$cat])) { $grouped[$cat] = []; }
            $grouped[$cat][] = $p;
        }
        return $grouped;
    }

    /** @return int[] */
    private function extractIds(iterable $entities): array
    {
        $ids = [];
        foreach ($entities as $e) {
            $ids[] = (int)$e->id;
        }
        return $ids;
    }
}

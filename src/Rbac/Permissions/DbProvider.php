<?php
declare(strict_types=1);

namespace App\Rbac\Permissions;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use CakeDC\Auth\Rbac\Permissions\ConfigProvider;

/**
 * DbProvider - RBAC provider ktory czyta uprawnienia z tabeli roles_permissions
 * i uzupelnia je regulami statycznymi z config/permissions.php (jako fallback).
 *
 * ZASADA DZIALANIA:
 *
 * 1. Feature flag `Auth.dbRbacEnabled` (default false) decyduje czy dodajemy
 *    reguly DB. Gdy false - zachowanie identyczne z ConfigProvider.
 *
 * 2. Gdy enabled:
 *    a) Query DB: JOIN roles + roles_permissions + permissions,
 *       zwraca [role_code => [permission_code, ...]]
 *    b) Dla kazdego rekordu, za pomoca `config/permissions_map.php` mapujemy
 *       code -> [controller, action[]] i EMITUJEMY reguly CakeDC-owe.
 *    c) DOKLADAMY reguly z config/permissions.php jako fallback - kolejnosc
 *       ma znaczenie (pierwsza dopasowana wygrywa), wiec reguly DB idą przed
 *       config, aby mogly nadpisac (allow lub deny).
 *
 * 3. Cache 5 minut. Klucz zawiera hash zestawu permissions map + DB, aby
 *    invalidacja odbywala sie automatycznie po zmianie w /admin/role
 *    (clearCache w RolesController po save() + Cache::clear).
 *
 * 4. Reguly z DB maja format:
 *      ['role' => '<code>', 'controller' => '<Ctrl>', 'action' => ['a', 'b']]
 *
 * 5. Wildcardy: admin i pracownik_administracyjny maja w permissions.php
 *    wildcard controller=* action=* - te reguly zostaja z config providera
 *    (nie duplikujemy w DB).
 */
class DbProvider extends ConfigProvider
{
    private const CACHE_KEY = 'db_rbac_permissions';
    private const CACHE_CONFIG = 'default';
    private const CACHE_TTL = 300; // 5 min

    public function getPermissions(): array
    {
        // 1. Reguly z config/permissions.php (fallback + wildcard admin)
        $configPermissions = parent::getPermissions();

        // 2. Feature flag - domyslnie false (backward compat)
        if (!Configure::read('Auth.dbRbacEnabled', false)) {
            return $configPermissions;
        }

        // 3. Reguly z DB
        try {
            $dbPermissions = $this->getCachedDbPermissions();
        } catch (\Throwable $e) {
            Log::warning('DbProvider: falling back to config permissions - ' . $e->getMessage(), ['db_rbac']);
            return $configPermissions;
        }

        // Reguly DB idą PRZED reglami z config zeby moc nadpisac
        // (CakeDC/Auth - first match wins).
        return array_merge($dbPermissions, $configPermissions);
    }

    /**
     * Wrapper na cache - klucz zawiera max(permissions.modified, roles_permissions.created)
     * aby automatycznie inwalidowac po zmianach w /admin/role.
     */
    private function getCachedDbPermissions(): array
    {
        $stamp = $this->getPermissionsStamp();
        $cacheKey = self::CACHE_KEY . '_' . $stamp;

        try {
            $cached = Cache::read($cacheKey, self::CACHE_CONFIG);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable) {
            // cache nie skonfigurowane - policz na swiezo
        }

        $rules = $this->buildDbPermissions();

        try {
            Cache::write($cacheKey, $rules, self::CACHE_CONFIG);
        } catch (\Throwable) {
            // best-effort
        }

        return $rules;
    }

    /**
     * Znacznik czasu - max modified permissions + roles_permissions.
     * Zmiana w /admin/role automatycznie inwaliduje cache bez explicit clear.
     */
    private function getPermissionsStamp(): string
    {
        try {
            $conn = ConnectionManager::get('default');
            $row = $conn->execute(
                "SELECT
                    UNIX_TIMESTAMP(GREATEST(
                        IFNULL((SELECT MAX(modified) FROM permissions), '2000-01-01'),
                        IFNULL((SELECT MAX(created) FROM roles_permissions), '2000-01-01')
                    )) as stamp"
            )->fetch('assoc');
            return (string)((int)($row['stamp'] ?? 0));
        } catch (\Throwable) {
            return 'na';
        }
    }

    /**
     * Buduje tablice regul CakeDC z DB + config/permissions_map.php.
     */
    private function buildDbPermissions(): array
    {
        $map = $this->loadPermissionsMap();
        if (empty($map)) {
            return [];
        }

        // Query: rola -> lista kodow permissions
        $conn = ConnectionManager::get('default');
        $rows = $conn->execute(
            "SELECT r.code AS role_code, p.code AS perm_code
             FROM roles r
             INNER JOIN roles_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE r.is_active = 1"
        )->fetchAll('assoc') ?: [];

        // Grupuj: role -> [perm_codes]
        $rolePerms = [];
        foreach ($rows as $r) {
            $roleCode = (string)$r['role_code'];
            $permCode = (string)$r['perm_code'];
            $rolePerms[$roleCode][] = $permCode;
        }

        // Konwertuj na format CakeDC
        $rules = [];
        foreach ($rolePerms as $roleCode => $codes) {
            $ctrlActionsGrouped = [];
            foreach ($codes as $code) {
                if (!isset($map[$code])) continue;
                foreach ($map[$code] as $entry) {
                    $ctrl = (string)($entry['controller'] ?? '');
                    $act = $entry['action'] ?? [];
                    if ($ctrl === '' || $act === []) continue;
                    if (!isset($ctrlActionsGrouped[$ctrl])) {
                        $ctrlActionsGrouped[$ctrl] = [];
                    }
                    if ($act === '*') {
                        $ctrlActionsGrouped[$ctrl] = '*';
                        continue;
                    }
                    if ($ctrlActionsGrouped[$ctrl] !== '*') {
                        foreach ((array)$act as $a) {
                            $ctrlActionsGrouped[$ctrl][] = $a;
                        }
                    }
                }
            }

            foreach ($ctrlActionsGrouped as $ctrl => $actions) {
                if ($actions === '*') {
                    $rules[] = [
                        'role' => $roleCode,
                        'controller' => $ctrl,
                        'action' => '*',
                    ];
                    continue;
                }
                $rules[] = [
                    'role' => $roleCode,
                    'controller' => $ctrl,
                    'action' => array_values(array_unique((array)$actions)),
                ];
            }
        }

        return $rules;
    }

    private function loadPermissionsMap(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $path = CONFIG . 'permissions_map.php';
        if (!is_file($path)) {
            return $cached = [];
        }
        $data = include $path;
        return $cached = is_array($data) ? $data : [];
    }
}

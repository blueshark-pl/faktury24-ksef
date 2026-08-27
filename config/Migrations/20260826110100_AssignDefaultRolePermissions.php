<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Domyslne przypisania role -> permissions.
 *
 * WAZNE: admin i pracownik_administracyjny NIE dostaja wpisow w DB
 * bo maja wildcard w config/permissions.php (CakeDC/Auth backend enforcement).
 * Panel /admin/role dla nich pokazuje puste checkboxy - to celowe.
 *
 * DB permissions (roles_permissions) sa uzywane przez PermissionCheckerComponent
 * (jesli istnieje) do granularnej kontroli w UI (`$this->Identity->hasPermission()`).
 *
 * Ta migracja tylko UZUPELNIA brakujace przypisania - nie usuwa istniejacych.
 */
class AssignDefaultRolePermissions extends BaseMigration
{
    private function quote(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }

    public function up(): void
    {
        // Mapa: rola -> [kody permissions]
        $assignments = [
            // ========================================================
            // user (Pracownik/spedytor) - pelny zakres operacyjny
            // Historycznie w seedzie mial ograniczony zestaw (14 kodow).
            // Wg audytu: pelen dostep - poza admin.* i client_portal.access.
            // Uwaga: menu sidebara dla 'user' jest zawezone przez $_isMinimalUser
            // (tylko Kontrahenci/Zlecenia/CRM/Planer) - to warstwa UI, ale backend
            // dopuszcza dostep do wszystkich innych modulow po URL.
            // ========================================================
            'user' => [
                'invoices.view', 'invoices.add', 'invoices.edit', 'invoices.delete',
                'invoices.ksef', 'invoices.correction', 'invoices.drafts',
                'invoices.export', 'invoices.print', 'invoices.email',
                'invoice_series.manage', 'products.manage', 'units.manage',
                'vats.view', 'company_bank_accounts.manage', 'recipients.manage',
                'nbp.view', 'legacy_invoices.view', 'api_tokens.manage',

                'contractors.view', 'contractors.add', 'contractors.edit',
                'contractors.delete', 'contractors.approve', 'contractors.export',
                'contractors.import', 'credit_limits.manage', 'credit_checks.view',

                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit',
                'speed_orders.delete', 'speed_orders.docs_upload', 'speed_orders.carrier',
                'speed_orders.costs', 'speed_orders.finance', 'speed_orders.approve',
                'speed_orders.export', 'speed_orders.tracking', 'speed_orders.sync',
                'speed_orders.templates', 'pallet_types.manage', 'transport_addresses.manage',

                'crm.leads.view', 'crm.leads.add', 'crm.leads.edit', 'crm.leads.delete',
                'crm.leads.import', 'crm.leads.convert', 'crm.leads.bulk',
                'crm.leads.merge', 'crm.leads.ai', 'crm.dashboard', 'crm.dictionaries',
                'crm.contracts.manage', 'crm.email_accounts.manage',
                'crm.workflows.manage', 'crm.doc_tracks.manage', 'crm.admin_tools',

                'cost_invoices.view', 'cost_invoices.add', 'cost_invoices.edit',
                'cost_invoices.delete', 'cost_invoices.import', 'cost_invoices.assign',
                'cost_invoices.payments', 'cost_invoices.manage', 'cost_categories.manage',

                'reconciliations.view', 'reconciliations.pay', 'reconciliations.kanban',
                'bank.view', 'bank.import', 'bank.allocate',
                'ksef.view', 'ksef.manage',

                'fleet.vehicles.manage', 'fleet.trailers.manage', 'fleet.drivers.manage',
                'fleet.combinations.manage', 'fleet.type_categories.manage',
                'fleet.maintenance.view', 'fleet.maintenance.manage',
                'fleet.schedules.view', 'fleet.schedules.manage',
                'fleet.time_logs.view', 'fleet.time_logs.manage', 'fleet.availability.manage',

                'route.planner.use', 'route.planner.templates', 'route.planner.tolls',
                'route.offers.view', 'route.offers.create', 'route.offers.delete',
                'route.trip_events.view', 'route.trip_events.add', 'route.return_loads.view',

                'reports.global', 'reports.analytics', 'reports.compliance',
                'reports.compliance.accept',

                'fuel_cards.view', 'fuel_cards.manage',

                'support.tickets.use', 'support.notifications.own',
            ],

            // ========================================================
            // spedycja_manager - user + approve + manager dashboard
            // ========================================================
            'spedycja_manager' => [
                'contractors.view', 'contractors.add', 'contractors.edit',
                'contractors.approve', 'contractors.export', 'contractors.import',
                'credit_limits.manage', 'credit_checks.view',

                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit',
                'speed_orders.docs_upload', 'speed_orders.carrier', 'speed_orders.costs',
                'speed_orders.finance', 'speed_orders.approve', 'speed_orders.export',
                'speed_orders.tracking', 'speed_orders.sync', 'speed_orders.templates',
                'pallet_types.manage', 'transport_addresses.manage',

                'crm.leads.view', 'crm.leads.add', 'crm.leads.edit', 'crm.leads.bulk',
                'crm.dashboard', 'crm.manager_dashboard', 'crm.dictionaries',
                'crm.contracts.manage',

                'cost_invoices.view', 'cost_invoices.manage', 'cost_invoices.assign',
                'cost_invoices.payments', 'cost_categories.manage',

                'reconciliations.view', 'reconciliations.pay', 'reconciliations.kanban',
                'bank.view', 'bank.import',
                'ksef.view',

                'fleet.vehicles.manage', 'fleet.trailers.manage', 'fleet.drivers.manage',
                'fleet.combinations.manage', 'fleet.maintenance.view',
                'fleet.maintenance.manage', 'fleet.schedules.view', 'fleet.schedules.manage',
                'fleet.time_logs.view', 'fleet.availability.manage',

                'route.planner.use', 'route.offers.view', 'route.offers.create',
                'route.trip_events.view', 'route.return_loads.view',

                'reports.global', 'reports.analytics', 'reports.compliance',
                'reports.compliance.accept',
                'admin.performance',

                'support.tickets.use', 'support.notifications.own',
            ],

            // ========================================================
            // sales_manager - CRM + kontrahenci + oferty + raporty
            // ========================================================
            'sales_manager' => [
                'contractors.view', 'contractors.add', 'contractors.edit',
                'contractors.approve', 'contractors.export', 'credit_limits.manage',
                'credit_checks.view',

                'speed_orders.view', 'speed_orders.export', 'speed_orders.tracking',

                'crm.leads.view', 'crm.leads.add', 'crm.leads.edit', 'crm.leads.delete',
                'crm.leads.import', 'crm.leads.convert', 'crm.leads.bulk', 'crm.leads.merge',
                'crm.leads.ai', 'crm.dashboard', 'crm.manager_dashboard', 'crm.dictionaries',
                'crm.contracts.manage', 'crm.email_accounts.manage', 'crm.workflows.manage',
                'crm.doc_tracks.manage', 'crm.admin_tools',

                'route.offers.view', 'route.offers.create', 'route.offers.delete',

                'reports.global', 'reports.analytics',

                'support.tickets.use', 'support.notifications.own',
            ],

            // ========================================================
            // mlodszy_spedytor - asystent + finanse zlecen + flota
            // ========================================================
            'mlodszy_spedytor' => [
                'contractors.view', 'contractors.add',

                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit',
                'speed_orders.docs_upload', 'speed_orders.carrier', 'speed_orders.costs',
                'speed_orders.finance', 'speed_orders.export', 'speed_orders.tracking',
                'speed_orders.sync', 'speed_orders.templates',
                'pallet_types.manage', 'transport_addresses.manage',

                'cost_invoices.view', 'cost_invoices.assign', 'cost_invoices.payments',

                'route.planner.use', 'route.offers.view', 'route.offers.create',
                'route.trip_events.view', 'route.trip_events.add', 'route.return_loads.view',

                'fleet.vehicles.manage', 'fleet.trailers.manage', 'fleet.drivers.manage',
                'fleet.schedules.view', 'fleet.schedules.manage', 'fleet.maintenance.view',

                'crm.leads.view', 'crm.leads.add', 'crm.leads.edit',
                'crm.dashboard', 'crm.contracts.manage',

                'support.tickets.use', 'support.notifications.own',
            ],

            // ========================================================
            // asystent_spedytora - operacyjne minimum
            // ========================================================
            'asystent_spedytora' => [
                'contractors.view', 'contractors.add',

                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit',
                'speed_orders.docs_upload', 'speed_orders.tracking',
                'speed_orders.templates',
                'pallet_types.manage', 'transport_addresses.manage',

                'route.planner.use', 'route.trip_events.view', 'route.trip_events.add',

                'fleet.drivers.manage', 'fleet.schedules.view', 'fleet.maintenance.view',

                'crm.leads.view', 'crm.leads.add', 'crm.leads.edit',
                'crm.dictionaries', 'crm.doc_tracks.manage',

                'support.tickets.use', 'support.notifications.own',
            ],

            // ========================================================
            // client - tylko portal
            // ========================================================
            'client' => [
                'client_portal.access',
            ],

            // pracownik_administracyjny i admin - wildcard w permissions.php,
            // NIE dodajemy wpisow DB (celowo puste checkboxy w /admin/role).
        ];

        foreach ($assignments as $roleCode => $permCodes) {
            $role = $this->fetchRow(
                "SELECT id FROM roles WHERE code = " . $this->quote($roleCode) . " LIMIT 1"
            );
            if (!$role) { continue; }
            $roleId = (int)$role['id'];

            foreach ($permCodes as $permCode) {
                $perm = $this->fetchRow(
                    "SELECT id FROM permissions WHERE code = " . $this->quote($permCode) . " LIMIT 1"
                );
                if (!$perm) { continue; }
                $permId = (int)$perm['id'];

                $exists = $this->fetchRow(
                    "SELECT id FROM roles_permissions
                     WHERE role_id = $roleId AND permission_id = $permId LIMIT 1"
                );
                if ($exists) { continue; }

                $this->execute(
                    "INSERT INTO roles_permissions (role_id, permission_id)
                     VALUES ($roleId, $permId)"
                );
            }
        }
    }

    public function down(): void
    {
        // Nie robimy rollbacku - migracja idempotent, ryzyko utraty przypisan.
    }
}

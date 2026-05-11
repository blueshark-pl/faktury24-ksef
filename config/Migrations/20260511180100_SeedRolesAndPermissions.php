<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Seed startowy:
 *   - 7 ról systemowych
 *   - bazowy katalog uprawnień (per moduł)
 *   - przypisania per rola wg specyfikacji asystenta/młodszego spedytora
 *
 * Idempotentne: jeśli rekord o danym `code` istnieje, nie nadpisuje.
 * Admin ma w permissions.php wildcard '*' (nie potrzebuje wpisów w DB).
 */
class SeedRolesAndPermissions extends BaseMigration
{
    public function up(): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // ── Role systemowe ──────────────────────────────────────────────────
        $roles = [
            ['code' => 'admin',              'name' => 'Administrator',           'description' => 'Pełny dostęp do systemu'],
            ['code' => 'user',               'name' => 'Pracownik (spedytor)',    'description' => 'Standardowy użytkownik biurowy — pełen zakres operacyjny'],
            ['code' => 'client',             'name' => 'Klient portalu',          'description' => 'Klient zewnętrzny — dostęp do portalu z własnymi zleceniami'],
            ['code' => 'spedycja_manager',   'name' => 'Kierownik Spedycji',      'description' => 'Kierownik działu spedycji — akceptacja kontrahentów + raporty'],
            ['code' => 'sales_manager',      'name' => 'Kierownik Działu Handlowego', 'description' => 'Kierownik handlu — akceptacja kontrahentów + raporty'],
            ['code' => 'mlodszy_spedytor',   'name' => 'Młodszy spedytor',        'description' => 'Spedytor — zlecenia, przewoźnicy, koszty, wynik finansowy zlecenia'],
            ['code' => 'asystent_spedytora', 'name' => 'Asystent spedytora',      'description' => 'Asystent — zlecenia + dokumenty POL/POD, kontrahenci do akceptacji'],
        ];

        foreach ($roles as $r) {
            $exists = $this->fetchRow("SELECT id FROM roles WHERE code = '" . $r['code'] . "' LIMIT 1");
            if ($exists) { continue; }
            $this->execute(sprintf(
                "INSERT INTO roles (code, name, description, is_system, is_active, created, modified)
                 VALUES (%s, %s, %s, 1, 1, %s, %s)",
                $this->quote($r['code']),
                $this->quote($r['name']),
                $this->quote($r['description']),
                $this->quote($now),
                $this->quote($now)
            ));
        }

        // ── Katalog uprawnień ───────────────────────────────────────────────
        // Format: [code, name, category]
        $perms = [
            // Faktury sprzedaży
            ['invoices.view',    'Faktury — podgląd listy i szczegółów',     'invoices'],
            ['invoices.add',     'Faktury — wystawianie nowych',             'invoices'],
            ['invoices.edit',    'Faktury — edycja',                          'invoices'],
            ['invoices.delete',  'Faktury — usuwanie',                        'invoices'],
            ['invoices.ksef',    'Faktury — wysyłka do KSeF',                 'invoices'],

            // Kontrahenci
            ['contractors.view',    'Kontrahenci — podgląd',                  'contractors'],
            ['contractors.add',     'Kontrahenci — dodawanie (status pending)','contractors'],
            ['contractors.edit',    'Kontrahenci — edycja',                   'contractors'],
            ['contractors.delete',  'Kontrahenci — usuwanie',                 'contractors'],
            ['contractors.approve', 'Kontrahenci — akceptacja oczekujących',  'contractors'],

            // Zlecenia transportowe (Speed)
            ['speed_orders.view',       'Zlecenia — podgląd',                 'speed_orders'],
            ['speed_orders.add',        'Zlecenia — tworzenie',               'speed_orders'],
            ['speed_orders.edit',       'Zlecenia — edycja operacyjna',       'speed_orders'],
            ['speed_orders.delete',     'Zlecenia — usuwanie',                'speed_orders'],
            ['speed_orders.docs_upload','Zlecenia — wgrywanie POL/POD',       'speed_orders'],
            ['speed_orders.carrier',    'Zlecenia — zlecenia spedycyjne / przewoźnicy / pojazdy', 'speed_orders'],
            ['speed_orders.costs',      'Zlecenia — koszty i faktury kosztowe','speed_orders'],
            ['speed_orders.finance',    'Zlecenia — wynik finansowy (przychód/koszt/marża)', 'speed_orders'],

            // Faktury kosztowe
            ['cost_invoices.view',   'Faktury kosztowe — podgląd',            'cost_invoices'],
            ['cost_invoices.manage', 'Faktury kosztowe — zarządzanie',        'cost_invoices'],

            // Rozliczenia / bank
            ['reconciliations.view', 'Rozliczenia — podgląd',                 'finance'],
            ['reconciliations.pay',  'Rozliczenia — dodawanie wpłat',         'finance'],
            ['bank.view',            'Wyciągi bankowe — podgląd',             'finance'],
            ['bank.import',          'Wyciągi bankowe — import MT940',        'finance'],

            // Raporty
            ['reports.global', 'Raporty zarządcze (globalne)',                'reports'],

            // Administracja
            ['admin.users',       'Administracja — zarządzanie użytkownikami','admin'],
            ['admin.roles',       'Administracja — zarządzanie rolami',      'admin'],
            ['admin.permissions', 'Administracja — przypisywanie uprawnień', 'admin'],
            ['admin.settings',    'Administracja — ustawienia systemowe',    'admin'],

            // Portal klienta
            ['client_portal.access', 'Portal klienta — dostęp',               'portal'],
        ];

        foreach ($perms as [$code, $name, $cat]) {
            $exists = $this->fetchRow("SELECT id FROM permissions WHERE code = " . $this->quote($code) . " LIMIT 1");
            if ($exists) { continue; }
            $this->execute(sprintf(
                "INSERT INTO permissions (code, name, category, created, modified)
                 VALUES (%s, %s, %s, %s, %s)",
                $this->quote($code),
                $this->quote($name),
                $this->quote($cat),
                $this->quote($now),
                $this->quote($now)
            ));
        }

        // ── Przypisania ról → uprawnień ─────────────────────────────────────
        // Admin: ma wildcard w permissions.php — nie potrzebuje rekordów w DB.
        // user (spedytor): pełny zakres operacyjny, BEZ administracji.
        // client: tylko portal.

        $assignments = [
            'client' => ['client_portal.access'],

            'user' => [
                'invoices.view', 'invoices.add', 'invoices.edit', 'invoices.delete', 'invoices.ksef',
                'contractors.view', 'contractors.add', 'contractors.edit', 'contractors.delete', 'contractors.approve',
                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit', 'speed_orders.delete',
                'speed_orders.docs_upload', 'speed_orders.carrier', 'speed_orders.costs', 'speed_orders.finance',
                'cost_invoices.view', 'cost_invoices.manage',
                'reconciliations.view', 'reconciliations.pay',
                'bank.view', 'bank.import',
                'reports.global',
            ],

            // Asystent spedytora — wg specyfikacji:
            // ✓ kontrahenci (tylko dodawanie, akceptacja przez kierownika/admina)
            // ✓ zlecenia (tworzenie, edycja danych operacyjnych, statusy, załadunek/rozładunek)
            // ✓ dokumenty POL/POD
            // ✗ koszty, marża, finanse, przewoźnicy
            'asystent_spedytora' => [
                'contractors.view', 'contractors.add',
                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit', 'speed_orders.docs_upload',
            ],

            // Młodszy spedytor — asystent + przewoźnicy + koszty + finanse zlecenia
            'mlodszy_spedytor' => [
                'contractors.view', 'contractors.add',
                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit', 'speed_orders.docs_upload',
                'speed_orders.carrier', 'speed_orders.costs', 'speed_orders.finance',
                'cost_invoices.view',
            ],

            // Kierownik Spedycji — akceptacja kontrahentów + pełen wgląd operacyjny + raporty
            'spedycja_manager' => [
                'contractors.view', 'contractors.add', 'contractors.edit', 'contractors.approve',
                'speed_orders.view', 'speed_orders.add', 'speed_orders.edit', 'speed_orders.docs_upload',
                'speed_orders.carrier', 'speed_orders.costs', 'speed_orders.finance',
                'cost_invoices.view', 'cost_invoices.manage',
                'reports.global',
            ],

            // Kierownik Działu Handlowego — akceptacja kontrahentów + raporty (bez operacji)
            'sales_manager' => [
                'contractors.view', 'contractors.add', 'contractors.edit', 'contractors.approve',
                'speed_orders.view',
                'reports.global',
            ],
        ];

        foreach ($assignments as $roleCode => $permCodes) {
            $role = $this->fetchRow("SELECT id FROM roles WHERE code = " . $this->quote($roleCode) . " LIMIT 1");
            if (!$role) { continue; }
            $roleId = (int)$role['id'];

            foreach ($permCodes as $permCode) {
                $perm = $this->fetchRow("SELECT id FROM permissions WHERE code = " . $this->quote($permCode) . " LIMIT 1");
                if (!$perm) { continue; }
                $permId = (int)$perm['id'];

                $exists = $this->fetchRow(
                    "SELECT id FROM roles_permissions
                     WHERE role_id = $roleId AND permission_id = $permId LIMIT 1"
                );
                if ($exists) { continue; }

                $this->execute(sprintf(
                    "INSERT INTO roles_permissions (role_id, permission_id, created)
                     VALUES (%d, %d, %s)",
                    $roleId, $permId, $this->quote($now)
                ));
            }
        }
    }

    public function down(): void
    {
        // Cofnięcie seedu — usuwamy tylko system role i im przypisane uprawnienia.
        // Nie ruszamy permissions (są w katalogu), bo nadal mogą być przypisane do custom-ról.
        $this->execute("DELETE FROM roles_permissions WHERE role_id IN (SELECT id FROM roles WHERE is_system = 1)");
        $this->execute("DELETE FROM roles WHERE is_system = 1");
    }

    /**
     * MySQL-safe string quoting (proste — wartości pochodzą wyłącznie z hardcoded array powyżej).
     */
    private function quote(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }
}

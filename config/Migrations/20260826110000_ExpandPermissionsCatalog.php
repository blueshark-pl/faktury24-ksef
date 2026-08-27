<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Rozbudowa katalogu permissions do 110 kodow w 12 kategoriach.
 * Cel: /admin/role - kompletny wybor per rola z UI.
 *
 * Kategorie:
 *  - invoices        - fakturowanie sprzedazy (11 + 9 pomocniczych = 20)
 *  - contractors     - kontrahenci + kredyt (9)
 *  - speed_orders    - zlecenia TSL (15)
 *  - crm             - CRM Leady + kontrakty + email + workflows (17)
 *  - cost_invoices   - faktury kosztowe (8)
 *  - finance         - rozliczenia + bank + KSeF (9)
 *  - fleet           - flota + grafiki + compliance (12)
 *  - route           - planer + oferty + tracking (9)
 *  - reports         - analityka + compliance (4)
 *  - fuel_cards      - E100 (3)
 *  - support         - ticketing + tasks + notifications (3)
 *  - admin           - administracja techniczna (9)
 *  - portal          - portal klienta (1)
 *
 * Domyslne przypisania per rola sa robione w osobnej migracji
 * (AssignDefaultRolePermissions - odpalana zaraz po tej).
 */
class ExpandPermissionsCatalog extends BaseMigration
{
    private function quote(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $perms = [
            // ================ invoices ================
            ['invoices.view',            'Faktury — podgląd listy i szczegółów',            'invoices'],
            ['invoices.add',             'Faktury — wystawianie nowych',                     'invoices'],
            ['invoices.edit',            'Faktury — edycja',                                  'invoices'],
            ['invoices.delete',          'Faktury — usuwanie',                                'invoices'],
            ['invoices.ksef',            'Faktury — wysyłka do KSeF',                         'invoices'],
            ['invoices.correction',      'Faktury — wystawianie korekt',                      'invoices'],
            ['invoices.drafts',          'Faktury — szkice i drafty planowane',               'invoices'],
            ['invoices.export',          'Faktury — eksport (CSV/XLSX)',                      'invoices'],
            ['invoices.print',           'Faktury — druk PDF i etykiety',                     'invoices'],
            ['invoices.email',           'Faktury — wysyłka e-mailem do kontrahenta',         'invoices'],
            ['invoices.admin_all',       'Faktury — panel administratora (wszystkie firmy)',  'invoices'],
            ['invoice_series.manage',    'Serie numeracji faktur — CRUD',                     'invoices'],
            ['products.manage',          'Towary/usługi — CRUD słownika',                     'invoices'],
            ['units.manage',             'Jednostki miary — CRUD słownika',                   'invoices'],
            ['vats.view',                'Stawki VAT — podgląd',                              'invoices'],
            ['company_bank_accounts.manage','Rachunki bankowe firmy — CRUD',                  'invoices'],
            ['recipients.manage',        'Odbiorcy e-mail kontrahenta — CRUD',                'invoices'],
            ['nbp.view',                 'Kursy NBP — słownik',                               'invoices'],
            ['legacy_invoices.view',     'Archiwum faktur (import ze starego systemu)',       'invoices'],
            ['api_tokens.manage',        'Tokeny API (integracje) — generowanie/rewokacja',   'invoices'],

            // ================ contractors ================
            ['contractors.view',         'Kontrahenci — podgląd',                             'contractors'],
            ['contractors.add',          'Kontrahenci — dodawanie (status pending)',          'contractors'],
            ['contractors.edit',         'Kontrahenci — edycja',                              'contractors'],
            ['contractors.delete',       'Kontrahenci — usuwanie',                            'contractors'],
            ['contractors.approve',      'Kontrahenci — akceptacja oczekujących',             'contractors'],
            ['contractors.export',       'Kontrahenci — eksport + lookup GUS/VIES',           'contractors'],
            ['contractors.import',       'Kontrahenci — import batch (GUS/Speed)',            'contractors'],
            ['credit_limits.manage',     'Limity kredytowe kontrahentów — CRUD',              'contractors'],
            ['credit_checks.view',       'Kredyt kupiecki (Allianz/Syntesys) — sprawdzanie',  'contractors'],

            // ================ speed_orders ================
            ['speed_orders.view',        'Zlecenia — podgląd listy i szczegółów',             'speed_orders'],
            ['speed_orders.add',         'Zlecenia — tworzenie ręczne',                       'speed_orders'],
            ['speed_orders.edit',        'Zlecenia — edycja operacyjna',                      'speed_orders'],
            ['speed_orders.delete',      'Zlecenia — usuwanie',                               'speed_orders'],
            ['speed_orders.docs_upload', 'Zlecenia — wgrywanie POL/POD/CMR',                  'speed_orders'],
            ['speed_orders.carrier',     'Zlecenia — przewoźnicy i pojazdy',                  'speed_orders'],
            ['speed_orders.costs',       'Zlecenia — koszty i faktury kosztowe',              'speed_orders'],
            ['speed_orders.finance',     'Zlecenia — wynik finansowy (marża)',                'speed_orders'],
            ['speed_orders.approve',     'Zlecenia — zatwierdzanie/odrzucanie (manager)',     'speed_orders'],
            ['speed_orders.export',      'Zlecenia — eksport CSV/XLSX + batch import',        'speed_orders'],
            ['speed_orders.tracking',    'Zlecenia — dashboard tracking live',                'speed_orders'],
            ['speed_orders.sync',        'Zlecenia — sync z ERP Speed',                       'speed_orders'],
            ['speed_orders.templates',   'Zlecenia — szablony (CRUD)',                        'speed_orders'],
            ['pallet_types.manage',      'Katalog palet — CRUD',                              'speed_orders'],
            ['transport_addresses.manage','Słownik adresów transportowych — CRUD',            'speed_orders'],

            // ================ crm ================
            ['crm.leads.view',           'CRM Leady — podgląd',                               'crm'],
            ['crm.leads.add',            'CRM Leady — dodawanie',                             'crm'],
            ['crm.leads.edit',           'CRM Leady — edycja (Kanban, konwersja)',            'crm'],
            ['crm.leads.delete',         'CRM Leady — usuwanie',                              'crm'],
            ['crm.leads.import',         'CRM Leady — import CSV + GUS+KRS+LinkedIn',         'crm'],
            ['crm.leads.convert',        'CRM Leady — konwersja lead→kontrahent+zlecenie+oferta','crm'],
            ['crm.leads.bulk',           'CRM Leady — bulk actions (etap/przypisanie/usuwanie)','crm'],
            ['crm.leads.merge',          'CRM Leady — łączenie duplikatów',                   'crm'],
            ['crm.leads.ai',             'CRM Leady — funkcje AI (draft/summary/quote)',      'crm'],
            ['crm.dashboard',            'CRM — dashboard KPI zespołu',                       'crm'],
            ['crm.manager_dashboard',    'CRM — Executive Dashboard (managerski)',            'crm'],
            ['crm.dictionaries',         'CRM — słowniki (branże, tabor, etykiety)',          'crm'],
            ['crm.contracts.manage',     'CRM Kontrakty ramowe — CRUD',                       'crm'],
            ['crm.email_accounts.manage','CRM Konta e-mail (IMAP+Gmail OAuth) — CRUD',        'crm'],
            ['crm.workflows.manage',     'CRM Automatyzacje/Workflows — CRUD',                'crm'],
            ['crm.doc_tracks.manage',    'CRM Document Tracking — linki + statystyki',        'crm'],
            ['crm.admin_tools',          'CRM Narzędzia administracyjne (migracje/cron/git)', 'crm'],

            // ================ cost_invoices ================
            ['cost_invoices.view',       'Faktury kosztowe — podgląd',                        'cost_invoices'],
            ['cost_invoices.add',        'Faktury kosztowe — ręczne dodawanie',               'cost_invoices'],
            ['cost_invoices.edit',       'Faktury kosztowe — edycja + statusy',               'cost_invoices'],
            ['cost_invoices.delete',     'Faktury kosztowe — usuwanie',                       'cost_invoices'],
            ['cost_invoices.import',     'Faktury kosztowe — import z KSeF',                  'cost_invoices'],
            ['cost_invoices.assign',     'Faktury kosztowe — przypisanie do zleceń + dekretacja','cost_invoices'],
            ['cost_invoices.payments',   'Faktury kosztowe — wpłaty + notatki',               'cost_invoices'],
            ['cost_invoices.manage',     'Faktury kosztowe — zarządzanie',                    'cost_invoices'],
            ['cost_categories.manage',   'Kategorie kosztowe — CRUD',                         'cost_invoices'],

            // ================ finance ================
            ['reconciliations.view',     'Rozliczenia — podgląd',                             'finance'],
            ['reconciliations.pay',      'Rozliczenia — dodawanie/usuwanie wpłat',            'finance'],
            ['reconciliations.kanban',   'Rozliczenia — Kanban windykacji + reminder',        'finance'],
            ['reconciliations.admin',    'Rozliczenia — integrity check + naprawy',           'finance'],
            ['bank.view',                'Wyciągi bankowe — podgląd',                         'finance'],
            ['bank.import',              'Wyciągi bankowe — import MT940 + AI-parse',         'finance'],
            ['bank.allocate',            'Wyciągi bankowe — alokacje N:M przelew↔faktura',    'finance'],
            ['ksef.view',                'KSeF — lista otrzymanych/wystawionych',             'finance'],
            ['ksef.manage',              'KSeF — grants, cert, dekretacja bookingItems',      'finance'],

            // ================ fleet ================
            ['fleet.vehicles.manage',    'Pojazdy floty — CRUD',                              'fleet'],
            ['fleet.trailers.manage',    'Naczepy — CRUD',                                    'fleet'],
            ['fleet.drivers.manage',     'Kierowcy — CRUD',                                   'fleet'],
            ['fleet.combinations.manage','Zestawy (pojazd+naczepa+kierowca) — CRUD',          'fleet'],
            ['fleet.type_categories.manage','Mapa typ zestawu → kategoria tolls — CRUD',      'fleet'],
            ['fleet.maintenance.view',   'Serwisy/badania/ADR/OC — podgląd',                  'fleet'],
            ['fleet.maintenance.manage', 'Serwisy — dodawanie/edycja/usuwanie',               'fleet'],
            ['fleet.schedules.view',     'Grafiki kierowców i pojazdów — podgląd',            'fleet'],
            ['fleet.schedules.manage',   'Grafiki — CRUD (dyspozytor)',                       'fleet'],
            ['fleet.time_logs.view',     'Czas pracy kierowców — podgląd',                    'fleet'],
            ['fleet.time_logs.manage',   'Czas pracy — wpisy manualne + import DDD',          'fleet'],
            ['fleet.availability.manage','Wzorce dostępności kierowców — CRUD',               'fleet'],

            // ================ route ================
            ['route.planner.use',        'Planer tras — kalkulator HERE + AI helpers',        'route'],
            ['route.planner.templates',  'Planer tras — zapis szablonów tras',                'route'],
            ['route.planner.tolls',     'Planer tras — override stawek tolls',                'route'],
            ['route.offers.view',        'Oferty cenowe — lista i podgląd',                   'route'],
            ['route.offers.create',      'Oferty cenowe — tworzenie i wysyłka',               'route'],
            ['route.offers.delete',      'Oferty cenowe — usuwanie',                          'route'],
            ['route.trip_events.view',   'Trip events — timeline zleceń',                     'route'],
            ['route.trip_events.add',    'Trip events — dodawanie zdarzeń (operator)',        'route'],
            ['route.return_loads.view',  'Ładunki powrotne — dopasowanie',                    'route'],

            // ================ reports ================
            ['reports.global',           'Raporty zarządcze (globalne)',                      'reports'],
            ['reports.analytics',        'Analytics — KPI dashboard',                         'reports'],
            ['reports.compliance',       'Compliance events — dashboard ryzyk (ITD)',         'reports'],
            ['reports.compliance.accept','Compliance events — akceptacja ryzyk',              'reports'],

            // ================ fuel_cards ================
            ['fuel_cards.view',          'Karty paliwowe — podgląd sald i transakcji',        'fuel_cards'],
            ['fuel_cards.manage',        'Karty paliwowe — konta, karty, limity',             'fuel_cards'],
            ['fuel_cards.block',         'Karty paliwowe — blokowanie zdalne',                'fuel_cards'],

            // ================ support ================
            ['support.tickets.use',      'Zgłoszenia i uwagi — składanie i podgląd własnych', 'support'],
            ['support.tasks.use',        'Tablica Kanban zadań admin (tasks)',                'support'],
            ['support.notifications.own','Powiadomienia in-app — własne',                     'support'],

            // ================ portal ================
            ['client_portal.access',     'Portal klienta — dostęp (rola client)',             'portal'],

            // ================ admin ================
            ['admin.users',              'Administracja — zarządzanie użytkownikami',         'admin'],
            ['admin.roles',              'Administracja — zarządzanie rolami',                'admin'],
            ['admin.permissions',        'Administracja — przypisywanie uprawnień',           'admin'],
            ['admin.settings',           'Administracja — ustawienia systemowe (firma)',      'admin'],
            ['admin.login_logs',         'Administracja — historia logowań',                  'admin'],
            ['admin.security_events',    'Administracja — wydarzenia bezpieczeństwa',         'admin'],
            ['admin.action_logs',        'Administracja — audyt akcji CRUD',                  'admin'],
            ['admin.performance',        'Administracja — wydajność pracowników',             'admin'],
            ['admin.impersonate',        'Administracja — wcielanie się w użytkownika',       'admin'],
        ];

        foreach ($perms as [$code, $name, $cat]) {
            $exists = $this->fetchRow("SELECT id FROM permissions WHERE code = " . $this->quote($code) . " LIMIT 1");
            if ($exists) {
                // Aktualizuj nazwe/kategorie jesli sie zmienily (idempotent)
                $this->execute(sprintf(
                    "UPDATE permissions SET name = %s, category = %s, modified = %s WHERE code = %s",
                    $this->quote($name),
                    $this->quote($cat),
                    $this->quote($now),
                    $this->quote($code)
                ));
                continue;
            }
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
    }

    public function down(): void
    {
        // Nie usuwamy - migracja idempotent, przy rollbacku zostaja wpisy.
        // Rollback rzeczywistego usuniecia trzeba by przebiedzia rekordami roles_permissions
        // co spowodowaloby utrata przypisan. Bezpieczniej zostawic.
    }
}

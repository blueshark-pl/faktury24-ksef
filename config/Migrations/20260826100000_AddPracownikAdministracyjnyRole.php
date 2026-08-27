<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Nowa rola: pracownik_administracyjny.
 *
 * user (Pracownik/spedytor) - w sidebarze widzi TYLKO: Kontrahenci, Zlecenia,
 * CRM Leady, Planer tras. Reszta ukryta.
 *
 * pracownik_administracyjny - dostep do wszystkiego (odpowiednik dotychczasowego user).
 */
class AddPracownikAdministracyjnyRole extends BaseMigration
{
    private function quote(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = $this->fetchRow("SELECT id FROM roles WHERE code = 'pracownik_administracyjny' LIMIT 1");
        if (!$exists) {
            $this->execute(sprintf(
                "INSERT INTO roles (code, name, description, is_system, is_active, created, modified)
                 VALUES ('pracownik_administracyjny', 'Pracownik administracyjny',
                         'Pelen dostep operacyjny + biuro - fakturowanie, kontrahenci, zlecenia, CRM, floty, ksiegowosc',
                         0, 1, %s, %s)",
                $this->quote($now),
                $this->quote($now)
            ));
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM roles WHERE code = 'pracownik_administracyjny'");
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * FALA 9: Dodaj pole linkedin_url + linkedin_company_url do leads.
 *
 * Handlowiec recznie wkleja LinkedIn URL osoby kontaktowej i/lub firmy.
 * System pokazuje przyciski "Otworz profil" + auto-parsuje imie/nazwisko
 * z URL slug (linkedin.com/in/jan-kowalski-abc123 -> Jan Kowalski).
 */
class AddLinkedinUrlToLeads extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('leads');
        if (!$table->hasColumn('linkedin_url')) {
            $table->addColumn('linkedin_url', 'string', [
                'limit' => 500, 'null' => true, 'after' => 'email',
                'comment' => 'LinkedIn URL osoby kontaktowej (np. https://linkedin.com/in/jan-kowalski)',
            ]);
        }
        if (!$table->hasColumn('linkedin_company_url')) {
            $table->addColumn('linkedin_company_url', 'string', [
                'limit' => 500, 'null' => true, 'after' => 'linkedin_url',
                'comment' => 'LinkedIn URL profilu firmy (np. https://linkedin.com/company/silesian-flour)',
            ]);
        }
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('leads');
        if ($table->hasColumn('linkedin_url')) $table->removeColumn('linkedin_url');
        if ($table->hasColumn('linkedin_company_url')) $table->removeColumn('linkedin_company_url');
        $table->update();
    }
}

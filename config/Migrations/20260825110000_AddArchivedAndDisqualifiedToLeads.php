<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Archiwum leadow + stage 'disqualified'.
 *
 * Dwa niezalezne mechanizmy:
 *
 * 1. archived_at datetime - lead chowany z Kanban + z default listy.
 *    Widoczny tylko z filtrem 'Archiwum: pokaz'. Do raportow zostaje.
 *
 * 2. disqualified_reason - powod dyskwalifikacji (spam, wrong contact,
 *    duplikat, nie chce, nie w naszej branzy). Semantycznie inne od 'lost':
 *    - lost = wybral konkurencje PO ofercie
 *    - disqualified = nie chce/nie moze wspolpracowac PRZED oferta
 *    Nowy stage 'disqualified' w LeadsTable::PIPELINE_STAGES kazdego pipeline.
 */
class AddArchivedAndDisqualifiedToLeads extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('leads');

        if (!$t->hasColumn('archived_at')) {
            $t->addColumn('archived_at', 'datetime', [
                'null' => true, 'default' => null,
                'comment' => 'Kiedy zarchiwizowany (schowany z Kanban i default listy)',
            ])->update();
        }

        if (!$t->hasColumn('disqualified_reason')) {
            $t->addColumn('disqualified_reason', 'string', [
                'limit' => 500, 'null' => true, 'default' => null,
                'comment' => 'Powod dyskwalifikacji (dla stage=disqualified). Np. spam, wrong contact, nie chce.',
            ])->update();
        }

        if (!$t->hasIndexByName('BY_ARCHIVED')) {
            $t->addIndex(['company_id', 'archived_at'], ['name' => 'BY_ARCHIVED'])->update();
        }
    }

    public function down(): void
    {
        $t = $this->table('leads');
        if ($t->hasIndexByName('BY_ARCHIVED')) {
            $t->removeIndexByName('BY_ARCHIVED')->update();
        }
        if ($t->hasColumn('archived_at')) {
            $t->removeColumn('archived_at')->update();
        }
        if ($t->hasColumn('disqualified_reason')) {
            $t->removeColumn('disqualified_reason')->update();
        }
    }
}

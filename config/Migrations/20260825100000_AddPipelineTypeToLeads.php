<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA 21: Multi-pipeline dla spedycji.
 *
 * Rozne cykle sprzedazowe wymagaja roznych stages:
 *  - long_term: miesiace, high-value, 6 etapow (new/contact/qualification/proposal/negotiation/contract)
 *  - spot: dni, low-value, 3 etapy (new/quote/won)
 *  - recurring: regularne (prospect/trial/active)
 *
 * Kolumna leads.pipeline_type wskazuje ktorego uzywac.
 * Domyslnie 'spot' bo to najczestszy przypadek w spedycji.
 *
 * Istniejaca kolumna leads.stage zachowana - stages sa specyficzne per pipeline
 * ale wartosci przechowywane w tym samym polu (walidacja w LeadsTable).
 */
class AddPipelineTypeToLeads extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('leads');

        if (!$t->hasColumn('pipeline_type')) {
            $t->addColumn('pipeline_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'spot',
                'comment' => 'long_term | spot | recurring - FALA 21 multi-pipeline',
            ])->update();
        }

        if (!$t->hasIndexByName('BY_PIPELINE')) {
            $t->addIndex(['company_id', 'pipeline_type', 'stage'], ['name' => 'BY_PIPELINE'])->update();
        }

        // Backfill: wszystkie istniejace leady -> 'spot' (bo aktualny pipeline byl uniwersalny
        // z stages new/contact/inquiry/offer/order/lost, ktory najlepiej pasuje do spot)
        $this->execute("UPDATE leads SET pipeline_type = 'spot' WHERE pipeline_type IS NULL OR pipeline_type = ''");
    }

    public function down(): void
    {
        $t = $this->table('leads');
        if ($t->hasIndexByName('BY_PIPELINE')) {
            $t->removeIndexByName('BY_PIPELINE')->update();
        }
        if ($t->hasColumn('pipeline_type')) {
            $t->removeColumn('pipeline_type')->update();
        }
    }
}

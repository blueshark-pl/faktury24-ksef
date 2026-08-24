<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Document tracking - kto otworzyl PDF oferty/faktury, kiedy, ile razy.
 *
 * Flow:
 *   1. User wysyla oferte klientowi -> generujemy hash URL + wpis w crm_document_tracks
 *   2. Link w emailu: /oferta-tracked/{hash} lub /oferta-tracked/{hash}.pdf
 *   3. Kazde otwarcie -> zapisujemy w opens_json (IP, user agent, timestamp)
 *   4. Widget w widoku oferty pokazuje: "Otwarcia: 3x (15:20 pt, 09:15 pn)"
 *
 * Zamiennik komercyjnego Docsend / PandaDoc tracking (za free).
 */
class CreateCrmDocumentTracks extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_document_tracks')) return;

        $this->table('crm_document_tracks', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('hash', 'string', ['limit' => 64, 'null' => false,
                'comment' => 'Unikatowy hash URL (bin2hex random 32 bytes)'])

            // Powiazanie z dokumentem - polimorficzne
            ->addColumn('entity_type', 'string', ['limit' => 30, 'null' => false,
                'comment' => 'route_offer | invoice | speed_order | lead_document'])
            ->addColumn('entity_id', 'string', ['limit' => 40, 'null' => false,
                'comment' => 'ID dokumentu (uuid lub int - string dla polimorfizmu)'])

            // Referencje CRM
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do leads (auto-log activity email_out)'])
            ->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('sent_to_email', 'string', ['limit' => 200, 'null' => true])

            // Metadata dokumentu
            ->addColumn('document_name', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Oryginalna nazwa PDF np. "Oferta Silesian Flour PL-DE.pdf"'])
            ->addColumn('document_url', 'string', ['limit' => 500, 'null' => true,
                'comment' => 'Ścieżka do PDF na dysku (webroot) lub URL zewnetrzny'])
            ->addColumn('document_size', 'integer', ['null' => true])

            // Statystyki (aktualizowane po kazdym otwarciu)
            ->addColumn('total_opens', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('unique_ips', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('first_open_at', 'datetime', ['null' => true])
            ->addColumn('last_open_at', 'datetime', ['null' => true])
            ->addColumn('total_time_seconds', 'integer', ['null' => false, 'default' => 0,
                'comment' => 'Sumaryczny czas przegladania (heartbeat co 10s przy otwartym PDF)'])

            // Log otwarcac (JSON array)
            ->addColumn('opens_json', 'mediumtext', ['null' => true,
                'comment' => 'JSON [{ip, ua, city (geo), opened_at, duration_s}] max 200 wpisow'])

            // TTL - opcjonalny, ograniczenie dostepu po dacie
            ->addColumn('expires_at', 'datetime', ['null' => true,
                'comment' => 'Po tej dacie link zwraca 410 Gone'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Opcjonalne haslo dostepu (jesli user chce protect)'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['hash'], ['unique' => true, 'name' => 'UNQ_HASH'])
            ->addIndex(['company_id', 'entity_type', 'entity_id'], ['name' => 'BY_ENTITY'])
            ->addIndex(['lead_id'], ['name' => 'BY_LEAD'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_document_tracks')->drop()->save();
    }
}

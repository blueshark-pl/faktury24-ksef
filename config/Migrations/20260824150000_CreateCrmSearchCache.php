<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Cache wynikow Google Search (Serper.dev/Brave/Google Custom Search)
 * dla LinkedIn URL lookup.
 *
 * Query zapisywane jako md5(normalized) - unikamy palenia kredytow na
 * tych samych zapytaniach. TTL 90 dni - LinkedIn URLs rzadko sie zmieniaja.
 *
 * Provider: 'serper' | 'brave' | 'google_cse' - zapisany dla debug.
 */
class CreateCrmSearchCache extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_search_cache')) return;

        $this->table('crm_search_cache', [
            'id' => false, 'primary_key' => ['query_hash'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('query_hash', 'string', ['limit' => 32, 'null' => false,
                'comment' => 'md5 lowered normalized query - unique key'])
            ->addColumn('query_text', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('provider', 'string', ['limit' => 20, 'null' => false,
                'comment' => 'serper | brave | google_cse'])
            ->addColumn('results_json', 'text', ['null' => true,
                'comment' => 'JSON [{url, title, snippet}] max 10 wynikow'])
            ->addColumn('result_count', 'integer', ['limit' => 3, 'null' => false, 'default' => 0])
            ->addColumn('http_status', 'integer', ['limit' => 3, 'null' => true])
            ->addColumn('error', 'text', ['null' => true])
            ->addColumn('fetched_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['fetched_at'], ['name' => 'BY_FETCHED'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_search_cache')->drop()->save();
    }
}

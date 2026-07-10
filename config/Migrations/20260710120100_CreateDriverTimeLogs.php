<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Log czasu pracy kierowcy — dzienne wpisy jazdy, przerw, innej pracy.
 * Regulacja UE 561/2006:
 *  - max 9h jazdy dziennie (rozszerzenie do 10h dwa razy w tygodniu)
 *  - max 56h w tygodniu, max 90h w 2 tygodniach
 *  - odpoczynek dobowy 11h (redukcja do 9h trzy razy w tygodniu)
 *  - odpoczynek tygodniowy 45h (redukcja do 24h co drugi tydzien)
 *
 * Zrodlo danych: import z tachografu (CSV/DDD), rozliczenie kadry, lub manual entry.
 */
class CreateDriverTimeLogs extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('driver_time_logs', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => false]);

        $table->addColumn('log_date', 'date', ['null' => false,
            'comment' => 'Doba pracy (do agregacji miesiecznej/tygodniowej)',
        ]);
        $table->addColumn('week_iso', 'string', ['limit' => 8, 'null' => false,
            'comment' => 'ISO week np. 2026-W29 — do szybkiego GROUP BY',
        ]);

        // Skladowe czasu w minutach
        $table->addColumn('driving_min', 'integer', ['null' => false, 'default' => 0,
            'comment' => 'Faktyczna jazda',
        ]);
        $table->addColumn('rest_min', 'integer', ['null' => false, 'default' => 0,
            'comment' => 'Przerwy (przerwa 45min, odpoczynek dobowy 11h)',
        ]);
        $table->addColumn('other_work_min', 'integer', ['null' => false, 'default' => 0,
            'comment' => 'Zaladunek, dokumenty, oczekiwanie',
        ]);
        $table->addColumn('availability_min', 'integer', ['null' => false, 'default' => 0,
            'comment' => 'Dyzur/gotowosc (nie liczy sie do limitu jazdy)',
        ]);

        // Compliance flags
        $table->addColumn('daily_rest_ok', 'boolean', ['null' => true,
            'comment' => 'Czy odpoczynek 11h zachowany',
        ]);
        $table->addColumn('weekly_rest_ok', 'boolean', ['null' => true,
            'comment' => 'Czy odpoczynek tygodniowy 45h zachowany',
        ]);
        $table->addColumn('extended_driving_used', 'boolean', ['null' => false, 'default' => false,
            'comment' => 'Wykorzystanie rozszerzenia do 10h (max 2x w tyg)',
        ]);
        $table->addColumn('reduced_daily_rest_used', 'boolean', ['null' => false, 'default' => false,
            'comment' => 'Wykorzystanie redukcji odpoczynku do 9h (max 3x w tyg)',
        ]);

        $table->addColumn('source', 'string', ['limit' => 20, 'null' => false, 'default' => 'manual',
            'comment' => 'tachograph|manual|estimated|import_ddd|import_csv',
        ]);
        $table->addColumn('source_file_id', 'string', ['limit' => 100, 'null' => true,
            'comment' => 'ID/nazwa pliku importu (audit)',
        ]);

        $table->addColumn('notes', 'text', ['null' => true]);
        $table->addColumn('created_by_user_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['driver_id', 'log_date'], ['unique' => true, 'name' => 'UQ_DRIVER_DATE']);
        $table->addIndex(['driver_id', 'week_iso'], ['name' => 'BY_DRIVER_WEEK']);
        $table->addIndex(['company_id', 'log_date'], ['name' => 'BY_COMPANY_DATE']);

        $table->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CRM Email 2-way sync - konfiguracja skrzynek IMAP zespolu.
 *
 * Cron `bin/cake crm_email_poll` sprawdza wszystkie aktywne konta co 5 min,
 * pobiera nowe emaile (UID > last_seen_uid), matchuje po from-email z
 * leads/contractors i loguje activity_type=email_in.
 *
 * Haslo jest szyfrowane (openssl_encrypt z Configure Security.salt).
 * Rekomendujemy app-specific password dla Gmail/O365, nie zwykle haslo.
 */
class CreateCrmEmailAccounts extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_email_accounts')) return;

        $this->table('crm_email_accounts', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do users - opcjonalne, jesli konto osobiste konkretnego usera'])

            ->addColumn('label', 'string', ['limit' => 100, 'null' => false,
                'comment' => 'np. "Skrzynka sprzedaz@" lub "Konto Krzysztofa"'])
            ->addColumn('imap_host', 'string', ['limit' => 150, 'null' => false,
                'comment' => 'np. imap.gmail.com, outlook.office365.com'])
            ->addColumn('imap_port', 'integer', ['limit' => 5, 'null' => false, 'default' => 993])
            ->addColumn('use_ssl', 'boolean', ['default' => true])
            ->addColumn('username', 'string', ['limit' => 200, 'null' => false,
                'comment' => 'Login/adres email'])
            ->addColumn('password_encrypted', 'text', ['null' => false,
                'comment' => 'Haslo openssl_encrypt (aes-256-cbc) - patrz CrmEmailAccountsTable::encryptPassword'])
            ->addColumn('folder', 'string', ['limit' => 100, 'null' => false, 'default' => 'INBOX'])

            // Stan synchronizacji
            ->addColumn('last_seen_uid', 'integer', ['limit' => 11, 'null' => true,
                'comment' => 'Ostatni UID pobrany - kolejny cron zaczyna od UID > last'])
            ->addColumn('last_synced_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('messages_synced_total', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('activities_created_total', 'integer', ['null' => false, 'default' => 0])

            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('sync_frequency_min', 'integer', ['limit' => 4, 'null' => false, 'default' => 5,
                'comment' => 'Co ile minut cron sprawdza to konto (min 5)'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'is_active'], ['name' => 'BY_COMPANY_ACTIVE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_email_accounts')->drop()->save();
    }
}

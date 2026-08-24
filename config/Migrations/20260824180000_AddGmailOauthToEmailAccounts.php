<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Rozszerzenie crm_email_accounts o Gmail OAuth 2.0 auth.
 *
 * auth_type = 'imap' (default) uzywa IMAP (Fala 6)
 * auth_type = 'gmail_oauth' uzywa Gmail API v1 przez OAuth 2.0 (Fala 13)
 *
 * Dla gmail_oauth pola IMAP (imap_host, port, password_encrypted) sa unused;
 * uzywane sa oauth_access_token + oauth_refresh_token + oauth_expires_at.
 */
class AddGmailOauthToEmailAccounts extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('crm_email_accounts');

        if (!$table->hasColumn('auth_type')) {
            $table->addColumn('auth_type', 'string', [
                'limit' => 20, 'null' => false, 'default' => 'imap',
                'after' => 'label',
                'comment' => 'imap | gmail_oauth',
            ]);
        }
        if (!$table->hasColumn('oauth_access_token')) {
            $table->addColumn('oauth_access_token', 'text', [
                'null' => true, 'after' => 'password_encrypted',
                'comment' => 'Encrypted OAuth access token (aes-256-cbc)',
            ]);
        }
        if (!$table->hasColumn('oauth_refresh_token')) {
            $table->addColumn('oauth_refresh_token', 'text', [
                'null' => true, 'after' => 'oauth_access_token',
                'comment' => 'Encrypted OAuth refresh token',
            ]);
        }
        if (!$table->hasColumn('oauth_expires_at')) {
            $table->addColumn('oauth_expires_at', 'datetime', [
                'null' => true, 'after' => 'oauth_refresh_token',
                'comment' => 'Kiedy access_token wygasa - jesli <= now, refresh',
            ]);
        }
        if (!$table->hasColumn('oauth_history_id')) {
            $table->addColumn('oauth_history_id', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'oauth_expires_at',
                'comment' => 'Gmail historyId dla incremental sync (zamiast IMAP UID)',
            ]);
        }
        // IMAP fields NULL dla oauth wpisow
        $table->changeColumn('imap_host', 'string', ['limit' => 150, 'null' => true]);
        $table->changeColumn('imap_port', 'integer', ['limit' => 5, 'null' => true, 'default' => 993]);
        $table->changeColumn('password_encrypted', 'text', ['null' => true]);
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('crm_email_accounts');
        foreach (['auth_type', 'oauth_access_token', 'oauth_refresh_token',
                  'oauth_expires_at', 'oauth_history_id'] as $col) {
            if ($table->hasColumn($col)) $table->removeColumn($col);
        }
        $table->update();
    }
}

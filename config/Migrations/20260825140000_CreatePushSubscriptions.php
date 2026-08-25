<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Browser Push Notifications - Web Push API subscriptions.
 * Kazdy user + device (browser) ma osobna subscription (endpoint + keys).
 *
 * Web Push wymaga VAPID keys (Configure Push.vapidPublicKey/vapidPrivateKey)
 * i skryptu send po stronie serwera - albo prostszy hack: browser Notification API
 * bez push service (tylko gdy tab jest otwarty).
 *
 * Minimalny setup: zapisujemy subscription, serwer wysyla przez fetch do endpointa.
 */
class CreatePushSubscriptions extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('crm_push_subscriptions', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('endpoint', 'text', ['null' => false, 'comment' => 'Push service URL (Google/Mozilla)'])
            ->addColumn('p256dh_key', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Klucz szyfrowania z subscription'])
            ->addColumn('auth_key', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Auth secret z subscription'])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('last_used_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['user_id', 'is_active'], ['name' => 'BY_USER_ACTIVE'])
            ->addIndex(['company_id'], ['name' => 'BY_COMPANY'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_push_subscriptions')->drop()->save();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * user_email_logs — historia wszystkich e-maili wychodzących do użytkowników.
 * Zapisywana z MyUsersMailer (welcome, resetPassword, validation,
 * socialAccountValidation, sendToken) — niezależnie czy zainicjowane
 * z panelu admina, czy z flow CakeDC.
 */
class CreateUserEmailLogs extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('user_email_logs', ['id' => false, 'primary_key' => ['id']]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('user_id', 'uuid', ['null' => false])
            ->addColumn('recipient_email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email_type', 'string', [
                'limit' => 40, 'null' => false,
                'comment' => 'welcome|reset_password|validation|social_validation|onetime_token',
            ])
            ->addColumn('lang', 'string', ['limit' => 5, 'null' => false, 'default' => 'pl'])
            ->addColumn('subject', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('status', 'string', [
                'limit' => 20, 'null' => false, 'default' => 'sent',
                'comment' => 'sent|failed',
            ])
            ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
            ->addColumn('sender_user_id', 'uuid', ['null' => true, 'default' => null,
                'comment' => 'Kto wysłał — null gdy auto (np. reset z ekranu loginu)'])
            ->addColumn('sender_email', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['user_id'],   ['name' => 'BY_USER'])
            ->addIndex(['email_type'],['name' => 'BY_TYPE'])
            ->addIndex(['created'],   ['name' => 'BY_DATE'])
            ->create();
    }
}

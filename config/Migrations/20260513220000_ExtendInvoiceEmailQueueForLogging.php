<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Rozszerzenie invoice_email_queue do roli logu wysyłek e-mail.
 *
 * Dotychczas tabela trzymała tylko kolejkę (pending → sent/failed).
 * Dodajemy pola pozwalające traktować ją także jako audyt:
 *
 *  - sent_by_user_id  — kto kliknął 'Wyślij' (NULL dla queue worker'a / cron)
 *  - sent_by_username — snapshot login/email użytkownika (dla read-only audyt)
 *  - subject          — temat wiadomości (snapshot)
 *  - kind             — 'manual' (klik 'Wyślij maila' w UI) / 'queued' (cron) / 'auto' (np. po wystawieniu)
 */
class ExtendInvoiceEmailQueueForLogging extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoice_email_queue')
            ->addColumn('sent_by_user_id',  'uuid',   ['null' => true, 'default' => null, 'after' => 'sent_at'])
            ->addColumn('sent_by_username', 'string', ['limit' => 200, 'null' => true, 'default' => null, 'after' => 'sent_by_user_id'])
            ->addColumn('subject',          'string', ['limit' => 500, 'null' => true, 'default' => null, 'after' => 'sent_by_username'])
            ->addColumn('kind',             'string', ['limit' => 20,  'null' => false, 'default' => 'manual', 'comment' => 'manual|queued|auto', 'after' => 'subject'])
            ->addIndex(['sent_by_user_id'])
            ->addIndex(['kind'])
            ->update();
    }
}

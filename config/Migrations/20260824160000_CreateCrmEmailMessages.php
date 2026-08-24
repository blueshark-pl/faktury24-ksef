<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Pelne tresci wiadomosci IMAP dla GPT AI processing.
 *
 * CrmEmailPollCommand zapisuje kazda wiadomosc (z match do leada) tutaj
 * dodatkowo do activity_type=email_in w lead_activities.
 *
 * GPT-based features (draft response, summarize, sentiment analysis) czytaja
 * z tej tabeli - potrzebuja pelnej tresci, nie tylko snippet.
 *
 * Thread ID (In-Reply-To + References headers) pozwala grupowac watki.
 */
class CreateCrmEmailMessages extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_email_messages')) return;

        $this->table('crm_email_messages', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('account_id', 'char', ['limit' => 36, 'null' => false,
                'comment' => 'FK crm_email_accounts'])
            ->addColumn('lead_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK leads - null gdy nie zmatchowany'])

            // IMAP metadata
            ->addColumn('imap_uid', 'integer', ['limit' => 11, 'null' => false])
            ->addColumn('message_id', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Message-ID z headera - unique dla dedup'])
            ->addColumn('in_reply_to', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('thread_id', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Grupowanie watku - normalized subject or References chain'])

            // Envelope
            ->addColumn('direction', 'string', ['limit' => 3, 'null' => false, 'default' => 'in',
                'comment' => 'in = odebrany, out = wyslany (przez nasz IMAP folder Sent)'])
            ->addColumn('from_email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('from_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('to_emails', 'text', ['null' => true,
                'comment' => 'Comma-separated lista adresow'])
            ->addColumn('cc_emails', 'text', ['null' => true])
            ->addColumn('subject', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('received_at', 'datetime', ['null' => true])

            // Body
            ->addColumn('body_text', 'text', ['null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'Plain text body (MEDIUMTEXT do 16MB)'])
            ->addColumn('body_html', 'text', ['null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'HTML body raw (MEDIUMTEXT do 16MB)'])
            ->addColumn('body_length', 'integer', ['null' => true])

            // Attachments (tylko metadata, nie zawartosc - za duze)
            ->addColumn('attachments_json', 'text', ['null' => true,
                'comment' => 'JSON [{filename, mime, size}]'])
            ->addColumn('attachments_count', 'integer', ['null' => false, 'default' => 0])

            // AI processing state
            ->addColumn('ai_summary', 'text', ['null' => true,
                'comment' => 'GPT-generated summary tego maila (cache)'])
            ->addColumn('ai_summary_at', 'datetime', ['null' => true])
            ->addColumn('ai_sentiment', 'string', ['limit' => 20, 'null' => true,
                'comment' => 'positive/neutral/negative/urgent - GPT classification'])
            ->addColumn('ai_intent', 'string', ['limit' => 40, 'null' => true,
                'comment' => 'inquiry/complaint/order/followup/other'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'lead_id', 'received_at'], ['name' => 'BY_LEAD_TIME'])
            ->addIndex(['account_id', 'imap_uid'], ['unique' => true, 'name' => 'UNQ_ACCOUNT_UID'])
            ->addIndex(['message_id'], ['name' => 'BY_MSG_ID'])
            ->addIndex(['thread_id'], ['name' => 'BY_THREAD'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_email_messages')->drop()->save();
    }
}

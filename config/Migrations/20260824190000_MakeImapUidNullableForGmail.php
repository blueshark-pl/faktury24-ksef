<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA 15 fix: crm_email_messages.imap_uid nullable + zamiana UNIQUE(account_id,imap_uid)
 * na UNIQUE(account_id,message_id) zeby Gmail OAuth (bez UID) mogl zapisywac wiele wiadomosci.
 *
 * MySQL dopuszcza wiele NULL w unique index - dla Gmail zapisujemy imap_uid=NULL,
 * dla IMAP zapisujemy prawdziwy UID > 0.
 *
 * Dedup dla Gmail = po message_id (naturalne unikat).
 * Dedup dla IMAP = po (account_id, imap_uid) - nadal unikat gdy imap_uid > 0.
 */
class MakeImapUidNullableForGmail extends AbstractMigration
{
    public function up(): void
    {
        $t = $this->table('crm_email_messages');

        // Drop existing unique
        if ($t->hasIndexByName('UNQ_ACCOUNT_UID')) {
            $t->removeIndexByName('UNQ_ACCOUNT_UID')->update();
        }

        // Zmien imap_uid na nullable
        $t->changeColumn('imap_uid', 'integer', ['limit' => 11, 'null' => true])->update();

        // Backfill: istniejace wpisy z imap_uid=0 -> NULL (byly to marker Gmail)
        $this->execute("UPDATE crm_email_messages SET imap_uid = NULL WHERE imap_uid = 0");

        // Nowy unique: (account_id, imap_uid) dziala tylko dla NOT NULL (MySQL/InnoDB dopuszcza wiele NULL)
        $t->addIndex(['account_id', 'imap_uid'], ['unique' => true, 'name' => 'UNQ_ACCOUNT_UID'])
          ->update();

        // Dodatkowy unique per (account_id, message_id) dla dedup Gmail - tez dopuszcza NULL
        if (!$t->hasIndexByName('UNQ_ACCOUNT_MSGID')) {
            $t->addIndex(['account_id', 'message_id'], ['unique' => true, 'name' => 'UNQ_ACCOUNT_MSGID'])
              ->update();
        }
    }

    public function down(): void
    {
        $t = $this->table('crm_email_messages');
        if ($t->hasIndexByName('UNQ_ACCOUNT_MSGID')) {
            $t->removeIndexByName('UNQ_ACCOUNT_MSGID')->update();
        }
        if ($t->hasIndexByName('UNQ_ACCOUNT_UID')) {
            $t->removeIndexByName('UNQ_ACCOUNT_UID')->update();
        }
        $this->execute("UPDATE crm_email_messages SET imap_uid = 0 WHERE imap_uid IS NULL");
        $t->changeColumn('imap_uid', 'integer', ['limit' => 11, 'null' => false])->update();
        $t->addIndex(['account_id', 'imap_uid'], ['unique' => true, 'name' => 'UNQ_ACCOUNT_UID'])
          ->update();
    }
}

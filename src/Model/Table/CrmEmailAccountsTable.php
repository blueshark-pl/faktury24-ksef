<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class CrmEmailAccountsTable extends Table
{
    private const CIPHER = 'aes-256-cbc';

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_email_accounts');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('label')->notEmptyString('label')->maxLength('label', 100)
            ->scalar('imap_host')->notEmptyString('imap_host')->maxLength('imap_host', 150)
            ->integer('imap_port')->range('imap_port', [1, 65535])
            ->scalar('username')->notEmptyString('username')->email('username', false, 'Musi być prawidłowy email')
            ->integer('sync_frequency_min')->range('sync_frequency_min', [5, 1440]);
        return $validator;
    }

    /**
     * Szyfruje haslo przez openssl_encrypt (AES-256-CBC).
     * Klucz - Security.salt z app config (musi byc ustawione).
     */
    public function encryptPassword(string $plain): string
    {
        $key = $this->getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decryptPassword(string $encoded): ?string
    {
        try {
            $key = $this->getKey();
            $data = base64_decode($encoded, true);
            if ($data === false) return null;
            $ivLen = openssl_cipher_iv_length(self::CIPHER);
            $iv = substr($data, 0, $ivLen);
            $encrypted = substr($data, $ivLen);
            $plain = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            return $plain === false ? null : $plain;
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('CrmEmailAccounts decrypt failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getKey(): string
    {
        // FALA 13c: preferuj Crm.encryptionKey (nasz nowy klucz), fallback do Security.salt
        // Umozliwia szyfrowanie na hostingach gdzie Security.salt jest nadpisywany
        // na pusty string przez env('SECURITY_SALT', '') hosting-specific setup.
        $key = (string)Configure::read('Crm.encryptionKey');
        if ($key === '') {
            $key = (string)Configure::read('Security.salt');
        }
        if ($key === '') {
            throw new \RuntimeException(
                'Brak klucza szyfrowania. Ustaw jeden z:'
                . ' Configure::write(\'Crm.encryptionKey\', \'<64-hex-chars>\')'
                . ' lub Configure::write(\'Security.salt\', \'<64-hex-chars>\').'
                . ' Wygeneruj przez: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
        return hash('sha256', $key, true);
    }

    /**
     * Konta gotowe do sync (aktywne + nie synchronizowane niedawno).
     */
    public function findDueForSync(?string $companyFilter = null): array
    {
        $now = new \DateTimeImmutable();
        $q = $this->find()
            ->where(['is_active' => true])
            ->orderByAsc('last_synced_at');

        if ($companyFilter) $q->where(['company_id' => $companyFilter]);

        $due = [];
        foreach ($q->all() as $a) {
            $freq = max(5, (int)$a->sync_frequency_min);
            if (!$a->last_synced_at) {
                $due[] = $a;
                continue;
            }
            $lastSync = new \DateTimeImmutable($a->last_synced_at->format('c'));
            $mustSyncAfter = $lastSync->modify("+{$freq} minutes");
            if ($now >= $mustSyncAfter) $due[] = $a;
        }
        return $due;
    }
}

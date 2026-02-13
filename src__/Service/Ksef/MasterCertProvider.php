<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Core\Configure;

final class MasterCertProvider
{
    public function __construct(
        private readonly ?string $baseDir = null,
        private readonly ?PassphraseDecryptorInterface $decryptor = null,
        private readonly string $metaFileName = 'meta.json'
    ) {}

    /**
     * Zwraca: ['path' => '/abs/path/to/certificate.p12', 'passphrase' => '...']
     */
    public function getMasterCert(string $environment): array
    {
        $env = $environment === 'prod' ? 'prod' : 'test';

        $dir = $this->baseDir ?? (string)Configure::read('Ksef.masterCertDir');
        $dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $env;

        $metaPath = $dir . DIRECTORY_SEPARATOR . $this->metaFileName;
        if (!is_file($metaPath)) {
            throw new \RuntimeException("Brak {$this->metaFileName} w: {$metaPath}");
        }

        $metaRaw = file_get_contents($metaPath);
        $meta = json_decode((string)$metaRaw, true);
        if (!is_array($meta)) {
            throw new \RuntimeException("Nieprawidłowy JSON w: {$metaPath}");
        }

        $fileName = (string)($meta['file'] ?? 'certificate.p12');
        $certPath = $dir . DIRECTORY_SEPARATOR . $fileName;

        if (!is_file($certPath)) {
            throw new \RuntimeException("Brak pliku certyfikatu: {$certPath}");
        }

        // Passphrase: 1) env plaintext -> 2) meta pass_cipher (decryptor) -> 3) null
        $passphrase = $this->getPassphraseFromEnv($environment);

        if ($passphrase === null && !empty($meta['pass_cipher'])) {
            if ($this->decryptor === null) {
                throw new \RuntimeException('Meta zawiera pass_cipher, ale brak decryptora (PassphraseDecryptorInterface).');
            }
            $passphrase = $this->decryptor->decrypt((string)$meta['pass_cipher']);
        }

        // UWAGA: passphrase może być '' (puste) i to jest OK.
        return [
            'path' => $certPath,
            'passphrase' => $passphrase,
            'meta' => $meta,
        ];
    }

    private function getPassphraseFromEnv(string $environment): ?string
    {
        $env = $environment === 'prod' ? 'PROD' : 'TEST';

        $p = getenv("KSEF_MASTER_P12_PASSPHRASE_{$env}");
        if ($p !== false) return trim((string)$p);

        $p = getenv('KSEF_MASTER_P12_PASSPHRASE');
        if ($p !== false) return trim((string)$p);

        return null;
    }
}

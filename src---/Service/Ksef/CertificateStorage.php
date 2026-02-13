<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use App\Utility\TokenVault;
use Cake\Chronos\Chronos;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Przechowuje pliki certyfikatów KSeF per firma/środowisko w katalogu resources/ksef_certs.
 * Dla każdego kontekstu tworzy meta.json z zaszyfrowanym hasłem.
 *
 * Obsługiwane formaty plików:
 * - .p12 (rekomendowane przez klienta N1ebieski)
 * - .pem (opcjonalnie; jeśli zawiera klucz prywatny i certyfikat – może wymagać konwersji do .p12 poza aplikacją)
 */
final class CertificateStorage
{
    public function __construct(
        private readonly string $baseDir = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ksef_certs'
    ) {}

    public function saveUploadedCertificate(
        UploadedFileInterface $file,
        string $companyId,
        string $environment,
        ?string $passphrase
    ): array {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        $dir = $this->ensureDir($companyId, $env);

        $original = $file->getClientFilename() ?: ('cert_' . Chronos::now()->format('Ymd_His'));
        $sanitized = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $original);
    $targetPath = $dir . DIRECTORY_SEPARATOR . $sanitized;

        $file->moveTo($targetPath);

        $meta = [
            'file'        => $sanitized,
            'mime'        => $file->getClientMediaType(),
            'size'        => $file->getSize(),
            'uploaded_at' => Chronos::now()->toIso8601String(),
            'pass_cipher' => $passphrase !== null ? TokenVault::encrypt($passphrase) : null,
        ];
        $this->writeMeta($dir, $meta);

        return [
            'path' => $targetPath,
            'meta' => $meta,
        ];
    }

    /**
     * Zapis pary klucz prywatny (.key) + certyfikat publiczny (.crt) i tworzy z nich złączony PEM.
     * Zwraca ścieżkę do combined PEM oraz metadane.
     */
    public function saveKeyAndCertificate(
        UploadedFileInterface $privateKey,
        UploadedFileInterface $publicCrt,
        string $companyId,
        string $environment,
        ?string $privateKeyPassphrase = null
    ): array {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        $dir = $this->ensureDir($companyId, $env);

        $keyName = $privateKey->getClientFilename() ?: ('private_' . Chronos::now()->format('Ymd_His') . '.key');
        $crtName = $publicCrt->getClientFilename() ?: ('public_' . Chronos::now()->format('Ymd_His') . '.crt');
        $keySan  = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $keyName);
        $crtSan  = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $crtName);

    $keyPath = $dir . DIRECTORY_SEPARATOR . $keySan;
    $crtPath = $dir . DIRECTORY_SEPARATOR . $crtSan;
        $privateKey->moveTo($keyPath);
        $publicCrt->moveTo($crtPath);

        // Zbuduj combined PEM: najpierw klucz prywatny, potem certyfikat
    $combined = $dir . DIRECTORY_SEPARATOR . 'combined.pem';
    $pem = (string)file_get_contents($keyPath) . PHP_EOL . (string)file_get_contents($crtPath) . PHP_EOL;
    $this->writeFileAtomic($combined, $pem, 0600);

        // Spróbuj od razu wygenerować plik PKCS#12 z pary klucz+cert
        $p12File = null;
        $p12Pass = bin2hex(random_bytes(8)); // 16 znaków hex
        try {
            $this->generatePkcs12FromPaths($keyPath, $crtPath, $dir, $p12Pass, $privateKeyPassphrase);
            $p12File = 'certificate.p12';
        } catch (\Throwable $e) {
            // zostaw combined.pem jako fallback
        }

        $meta = [
            'key_file'      => $keySan,
            'crt_file'      => $crtSan,
            'combined_pem'  => 'combined.pem',
            'uploaded_at'   => Chronos::now()->toIso8601String(),
            'pass_cipher'   => $p12File ? TokenVault::encrypt($p12Pass) : null,
            'file'          => $p12File, // jeśli wygenerowano p12, wskazuj go jako główny plik
            'format'        => $p12File ? 'p12' : 'pem-combined',
        ];
        $this->writeMeta($dir, $meta);

        return [
            'path' => $p12File ? ($dir . DIRECTORY_SEPARATOR . $p12File) : $combined,
            'meta' => $meta,
        ];
    }

    /** Zwraca ['path'=>..., 'passphrase'=>...] lub null jeśli brak.
     *  Fallback: jeśli brak certyfikatu firmy, próbuje resources/ksef_certs/master/{env}.
     */
    public function getCertificateFor(string $companyId, string $environment): ?array
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        // 1) Ścieżka firmowa
        $dir = $this->dirPath($companyId, $env);
        $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';

        $useFallback = false;
        if (!is_file($metaPath)) {
            // 2) Fallback do master/{env}
            $fallbackDir = $this->baseDir . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . $env;
            $fallbackMeta = $fallbackDir . DIRECTORY_SEPARATOR . 'meta.json';
            if (is_file($fallbackMeta)) {
                $dir = $fallbackDir;
                $metaPath = $fallbackMeta;
                $useFallback = true;
            } else {
                return null;
            }
        }

        $meta = json_decode((string)file_get_contents($metaPath), true) ?: [];
        // Preferuj plik .p12 jeśli jest (i hasło jeśli zapisane)
        $file = isset($meta['file']) ? (string)$meta['file'] : '';
        if ($file !== '' && str_ends_with($file, '.p12')) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                $pass = !empty($meta['pass_cipher']) ? TokenVault::decrypt((string)$meta['pass_cipher']) : null;
                return ['path' => $path, 'passphrase' => $pass, 'source' => $useFallback ? 'master' : 'company'];
            }
        }
        // Następnie combined PEM
        if (!empty($meta['combined_pem'])) {
            $pemPath = $dir . DIRECTORY_SEPARATOR . (string)$meta['combined_pem'];
            if (is_file($pemPath)) {
                return ['path' => $pemPath, 'passphrase' => null, 'source' => $useFallback ? 'master' : 'company'];
            }
        }
        // Albo inny pojedynczy plik (np. .pem)
        if ($file !== '') {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                $pass = !empty($meta['pass_cipher']) ? TokenVault::decrypt((string)$meta['pass_cipher']) : null;
                return ['path' => $path, 'passphrase' => $pass, 'source' => $useFallback ? 'master' : 'company'];
            }
        }

        // Jeśli próba firmowa nieudana, spróbuj raz fallback (gdy jeszcze nie użyty)
        if (!$useFallback) {
            $fallbackDir = $this->baseDir . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . $env;
            $fallbackMeta = $fallbackDir . DIRECTORY_SEPARATOR . 'meta.json';
            if (is_file($fallbackMeta)) {
                $meta = json_decode((string)file_get_contents($fallbackMeta), true) ?: [];
                $file = isset($meta['file']) ? (string)$meta['file'] : '';
                if ($file !== '' && str_ends_with($file, '.p12')) {
                    $path = $fallbackDir . DIRECTORY_SEPARATOR . $file;
                    if (is_file($path)) {
                        $pass = !empty($meta['pass_cipher']) ? TokenVault::decrypt((string)$meta['pass_cipher']) : null;
                        return ['path' => $path, 'passphrase' => $pass, 'source' => 'master'];
                    }
                }
                if (!empty($meta['combined_pem'])) {
                    $pemPath = $fallbackDir . DIRECTORY_SEPARATOR . (string)$meta['combined_pem'];
                    if (is_file($pemPath)) {
                        return ['path' => $pemPath, 'passphrase' => null, 'source' => 'master'];
                    }
                }
                if ($file !== '') {
                    $path = $fallbackDir . DIRECTORY_SEPARATOR . $file;
                    if (is_file($path)) {
                        $pass = !empty($meta['pass_cipher']) ? TokenVault::decrypt((string)$meta['pass_cipher']) : null;
                        return ['path' => $path, 'passphrase' => $pass, 'source' => 'master'];
                    }
                }
            }
        }
        return null;
    }

    private function ensureDir(string $companyId, string $env): string
    {
        $dir = $this->dirPath($companyId, $env);
        if (!is_dir($dir)) {
            $oldUmask = umask(0);
            $ok = @mkdir($dir, 0700, true);
            umask($oldUmask);
            if (!$ok && !is_dir($dir)) {
                throw new \RuntimeException('Nie udało się utworzyć katalogu dla certyfikatu KSeF.');
            }
        }
        return $dir;
    }

    private function writeMeta(string $dir, array $meta): void
    {
    $path = $dir . DIRECTORY_SEPARATOR . 'meta.json';
    $this->writeFileAtomic($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0600);
    }

    private function dirPath(string $companyId, string $env): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . 'company_' . $companyId . DIRECTORY_SEPARATOR . $env;
    }

    /**
     * Jeśli mamy zapisane key_file + crt_file, wygeneruj certificate.p12 i zaktualizuj meta.
     * Zwraca ['path'=>p12path,'passphrase'=>pass] po sukcesie.
     */
    public function ensurePkcs12(string $companyId, string $environment): ?array
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        $dir = $this->dirPath($companyId, $env);
        $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
        if (!is_file($metaPath)) {
            return null;
        }
        $meta = json_decode((string)file_get_contents($metaPath), true) ?: [];
        // Jeśli już mamy p12
        if (!empty($meta['file']) && str_ends_with((string)$meta['file'], '.p12')) {
            $p12 = $dir . DIRECTORY_SEPARATOR . (string)$meta['file'];
            if (is_file($p12)) {
                $pass = !empty($meta['pass_cipher']) ? TokenVault::decrypt((string)$meta['pass_cipher']) : null;
                return ['path' => $p12, 'passphrase' => $pass];
            }
        }
        // Spróbuj wygenerować, jeśli mamy parę key/crt
        $key = isset($meta['key_file']) ? (string)$meta['key_file'] : '';
        $crt = isset($meta['crt_file']) ? (string)$meta['crt_file'] : '';
        if ($key === '' || $crt === '') {
            return null;
        }
        $keyPath = $dir . DIRECTORY_SEPARATOR . $key;
        $crtPath = $dir . DIRECTORY_SEPARATOR . $crt;
        if (!is_file($keyPath) || !is_file($crtPath)) {
            return null;
        }
        $p12Pass = bin2hex(random_bytes(8));
        $this->generatePkcs12FromPaths($keyPath, $crtPath, $dir, $p12Pass);

        $meta['file'] = 'certificate.p12';
        $meta['format'] = 'p12';
        $meta['pass_cipher'] = TokenVault::encrypt($p12Pass);
        $this->writeMeta($dir, $meta);

        return ['path' => $dir . DIRECTORY_SEPARATOR . 'certificate.p12', 'passphrase' => $p12Pass];
    }

    /**
     * Wytwarza plik certificate.p12 w podanym katalogu z pary key+crt (PEM). Używa PHP OpenSSL.
     * @throws \RuntimeException
     */
    private function generatePkcs12FromPaths(string $keyPath, string $crtPath, string $targetDir, ?string $p12Pass, ?string $privateKeyPassphrase = null): void
    {
        $keyPem = (string)file_get_contents($keyPath);
        $crtPem = (string)file_get_contents($crtPath);
        $priv = $privateKeyPassphrase !== null && $privateKeyPassphrase !== ''
            ? openssl_pkey_get_private($keyPem, $privateKeyPassphrase)
            : openssl_pkey_get_private($keyPem);
        if ($priv === false) {
            throw new \RuntimeException('Nie udało się wczytać klucza prywatnego (.key).');
        }
        $x509 = openssl_x509_read($crtPem);
        if ($x509 === false) {
            throw new \RuntimeException('Nie udało się wczytać certyfikatu (.crt).');
        }
        $p12Out = '';
        $ok = openssl_pkcs12_export($x509, $p12Out, $priv, (string)$p12Pass, [
            'friendly_name' => 'KSeF Certificate'
        ]);
        if (!$ok || $p12Out === '') {
            throw new \RuntimeException('Nie udało się wygenerować pliku PKCS#12 z klucza i certyfikatu.');
        }
        $p12Path = $targetDir . DIRECTORY_SEPARATOR . 'certificate.p12';
        $this->writeFileAtomic($p12Path, $p12Out, 0600);
    }

    private function writeFileAtomic(string $path, string $contents, int $mode = 0600): void
    {
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $contents) === false) {
            @unlink($tmp);
            throw new \RuntimeException('Nie udało się zapisać pliku tymczasowego: ' . $tmp);
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Nie udało się przenieść pliku do docelowej ścieżki: ' . $path);
        }
    }
}

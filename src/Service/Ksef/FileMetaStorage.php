<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Core\Configure;

final class FileMetaStorage
{
    public function __construct(
        private readonly ?string $metaDir = null
    ) {}

    public function getEncryptionKey(string $environment, string $nip): ?string
    {
        $meta = $this->read($environment, $nip);
        $key = $meta['encryptionKey'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function saveEncryptionKey(string $environment, string $nip, string $key): void
    {
        $meta = $this->read($environment, $nip);
        $meta['encryptionKey'] = $key;
        $meta['updatedAt'] = date('c');

        $this->write($environment, $nip, $meta);
    }

    private function read(string $environment, string $nip): array
    {
        $file = $this->metaPath($environment, $nip);
        if (!is_file($file)) {
            return [];
        }
        $json = @file_get_contents($file);
        if (!is_string($json) || $json === '') {
            return [];
        }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    private function write(string $environment, string $nip, array $meta): void
    {
        $file = $this->metaPath($environment, $nip);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // atomic write
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        @file_put_contents($tmp, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        @rename($tmp, $file);
    }

    private function metaPath(string $environment, string $nip): string
    {
        $env = $environment === 'prod' ? 'prod' : 'test';
        $nip = preg_replace('/\D+/', '', $nip ?? '');

        if ($nip === '') {
            throw new \InvalidArgumentException('Brak NIP (metaPath).');
        }

        $base = $this->metaDir ?? (string)Configure::read('Ksef.metaDir');
        $base = rtrim($base, DIRECTORY_SEPARATOR);

        return $base . DIRECTORY_SEPARATOR . $env . DIRECTORY_SEPARATOR . $nip . '.json';
    }
}

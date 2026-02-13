<?php
declare(strict_types=1);

namespace App\Utility;

/**
 * Bardzo prosty „sejf” na tokeny KSeF.
 * Szyfruje i odszyfrowuje przy pomocy openssl.
 */
final class TokenVault
{
    private static function key(): string
    {
        $key = env('KSEF_TOKEN_KEY');
        if (empty($key)) {
            throw new \RuntimeException('KSEF_TOKEN_KEY missing in .env');
        }
        // dociągamy / przycinamy klucz do 32 bajtów
        return substr(hash('sha256', $key, true), 0, 32);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 17) {
            return '';
        }
        $iv = substr($data, 0, 16);
        $cipher = substr($data, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain ?: '';
    }
}

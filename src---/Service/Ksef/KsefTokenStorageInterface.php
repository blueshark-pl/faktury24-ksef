<?php
declare(strict_types=1);

namespace App\Service\Ksef;

interface KsefTokenStorageInterface
{
    public function saveTokens(string $contextKey, string $accessToken, ?string $refreshToken, ?int $accessExp = null): void;
    public function getTokens(string $contextKey): ?array;
    public function clearTokens(string $contextKey): void;
}

<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use RuntimeException;

/**
 * @deprecated Zachowane na czas migracji. Docelowo korzystaj z N1KsefService::buildClient()
 * i zasobów klienta (auth(), invoices(), sessions(), itp.).
 */
final class KsefSessionService
{
    public function __construct(
        private readonly KsefClient $client,
        private readonly DbKsefTokenStorage $storage
    ) {}

    /** Zapewnia ważny accessToken. Gdy brak → loguje przez sysToken+NIP z ksef_authorizations. */
    public function ensureAccessToken(string $companyId, string $environment): string
    {
        $contextKey = $this->ctx($companyId, $environment);

        // 1) spróbuj użyć/odświeżyć istniejącą sesję
        try {
            return $this->client->ensureAccessToken($contextKey);
        } catch (\Throwable $e) {
            // brak lub nie da się odświeżyć — przechodzimy do pełnego loginu
        }

        // 2) weź sysToken + nip z NAJNOWSZEGO wpisu (aktywny lub nie)
        $creds = $this->storage->getSystemCreds($contextKey);
        if (!$creds) {
            throw new RuntimeException('Brak sysToken/nip w ksef_authorizations (seed wymagany).');
        }

        // 3) pełny login w tle
        $challenge = $this->client->getAuthChallenge();
        $auth = $this->client->authenticateByKsefToken(
            $challenge['challenge'],
            (string)$challenge['timestamp'],
            ['type' => 'nip', 'value' => $creds['nip']],
            $creds['sysToken']
        );

        // 4) krótki polling statusu (OCSP/CRL na prod może trwać; tutaj skrócony)
        $authenticationToken = $auth['authenticationToken'];
        $referenceNumber     = $auth['referenceNumber'];

        for ($i = 0; $i < 10; $i++) {
            $status = $this->client->getAuthStatus($referenceNumber, $authenticationToken);
            $code = (string)($status['code'] ?? $status['statusCode'] ?? '');
            if (in_array($code, ['SUCCESS','OK','COMPLETED','200'], true)) {
                break;
            }
            usleep(400_000); // 0.4s
        }

        // 5) redeem → zapisz nową sesję (storage przeniesie sysToken/nip z poprzedniego wpisu)
        $tokens = $this->client->redeemAccessAndRefreshTokens($authenticationToken);
        if (empty($tokens['accessToken'])) {
            throw new RuntimeException('Redeem access/refresh token nie powiódł się.');
        }

        $this->client->storeTokens($contextKey, $tokens['accessToken'], $tokens['refreshToken']);
        return $tokens['accessToken'];
    }

    private function ctx(string $companyId, string $environment): string
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        return "company:{$companyId}:{$env}";
    }
}

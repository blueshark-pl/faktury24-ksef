<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use App\Utility\TokenVault;
use Cake\Chronos\Chronos;
use Cake\Datasource\FactoryLocator;

/**
 * Storage oparty o tabelę `ksef_authorizations`.
 *
 * Uwaga (migracja): Klasa pozostaje aktualna – korzysta z niej adapter N1KsefService
 * do wczytania tokenów oraz sysToken/NIP.
 * contextKey: "company:{company_id}:{environment}"  (np. company:123:test|prod)
 *
 * token_cipher przechowuje zaszyfrowany JSON:
 *  v1: { "v":1, "accessToken","refreshToken","accessExp" }
 *  v2: { "v":2, "accessToken","refreshToken","accessExp","sysToken","nip" }
 */
final class DbKsefTokenStorage implements KsefTokenStorageInterface
{
    /** Zapisuje nową sesję (dezaktywuje poprzednie) i przenosi sysToken/nip z ostatniego wpisu. */
    public function saveTokens(string $contextKey, string $accessToken, ?string $refreshToken, ?int $accessExp = null): void
    {
        [$companyId, $environment] = $this->splitContext($contextKey);

        $now    = Chronos::now();
        $claims = $this->extractJwtPayload($accessToken);
        $iat    = isset($claims['iat']) ? (int)$claims['iat'] : $now->getTimestamp();
        $exp    = $accessExp ?? (isset($claims['exp']) ? (int)$claims['exp'] : ($now->getTimestamp() + 10 * 60));
        $scopes = $claims['scope'] ?? ($claims['scopes'] ?? null);
        if (is_array($scopes)) {
            $scopes = implode(' ', $scopes);
        }

        // carry-over sysToken/nip z ostatniego wpisu (aktywny lub najświeższy)
        $prev = $this->getLatestPayload($companyId, $environment);
        $sysToken = $prev['sysToken'] ?? null;
        $nip      = $prev['nip']      ?? null;

        $payloadArr = [
            'v'            => 2,
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'accessExp'    => $exp,
            'sysToken'     => $sysToken,
            'nip'          => $nip,
        ];
        $sealed = TokenVault::encrypt(json_encode($payloadArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        /** @var \App\Model\Table\KsefAuthorizationsTable $KsefAuths */
        $KsefAuths = FactoryLocator::get('Table')->get('KsefAuthorizations');

        // dezaktywuj stare aktywne
        $KsefAuths->updateAll(
            ['is_active' => 0, 'modified' => $now],
            ['company_id' => $companyId, 'environment' => $environment, 'is_active' => 1]
        );

        $entity = $KsefAuths->newEntity([
            'company_id'       => $companyId,
            'environment'      => $environment,
            'status'           => 'active',
            'is_active'        => 1,
            'auth_method'      => 'ksef_token', // albo 'xades' jeśli ścieżka XAdES
            'scopes'           => $scopes,
            'valid_from'       => Chronos::createFromTimestamp($iat),
            'expires_at'       => Chronos::createFromTimestamp($exp),
            'token_cipher'     => $sealed,
            'token_last4'      => substr($accessToken, -4),
            'last_verified_at' => $now,
        ], ['accessibleFields' => ['*' => true]]);

        if (!$KsefAuths->save($entity)) {
            throw new \RuntimeException('Nie udało się zapisać autoryzacji KSeF (ksef_authorizations).');
        }
    }

    /** Zwraca ['accessToken','refreshToken','accessExp'] lub null (tylko z aktywnego wpisu). */
    public function getTokens(string $contextKey): ?array
    {
        [$companyId, $environment] = $this->splitContext($contextKey);
        $row = $this->findActiveRow($companyId, $environment);
        if (!$row) {
            return null;
        }
        $data = $this->decryptPayload((string)$row->token_cipher);
        if (!$data) {
            return null;
        }

        // heartbeat
        /** @var \App\Model\Table\KsefAuthorizationsTable $KsefAuths */
        $KsefAuths = FactoryLocator::get('Table')->get('KsefAuthorizations');
        $KsefAuths->updateAll(
            ['last_verified_at' => Chronos::now(), 'modified' => Chronos::now()],
            ['id' => $row->id]
        );

        return [
            'accessToken'  => $data['accessToken']  ?? null,
            'refreshToken' => $data['refreshToken'] ?? null,
            'accessExp'    => $data['accessExp']    ?? null,
        ];
    }

    /** Dezaktywuje wszystkie aktywne dla kontekstu. */
    public function clearTokens(string $contextKey): void
    {
        [$companyId, $environment] = $this->splitContext($contextKey);

        /** @var \App\Model\Table\KsefAuthorizationsTable $KsefAuths */
        $KsefAuths = FactoryLocator::get('Table')->get('KsefAuthorizations');

        $KsefAuths->updateAll(
            ['is_active' => 0, 'revoked_at' => Chronos::now(), 'status' => 'revoked', 'modified' => Chronos::now()],
            ['company_id' => $companyId, 'environment' => $environment, 'is_active' => 1]
        );
    }

    /**
     * NOWE: odczyt sysToken + nip z NAJNOWSZEGO wpisu (aktywny lub nie).
     * Zwraca ['sysToken'=>..., 'nip'=>...] lub null.
     */
    public function getSystemCreds(string $contextKey): ?array
    {
        [$companyId, $environment] = $this->splitContext($contextKey);
        $payload = $this->getLatestPayload($companyId, $environment);
        if (!$payload) {
            return null;
        }
        $sys = $payload['sysToken'] ?? null;
        $nip = $payload['nip'] ?? null;
        if (!$sys || !$nip) {
            return null;
        }
        return ['sysToken' => $sys, 'nip' => $nip];
    }

    /* ===== helpers ===== */

    private function splitContext(string $contextKey): array
    {
        if (!str_starts_with($contextKey, 'company:')) {
            throw new \InvalidArgumentException('contextKey format: company:{company_id}:{environment}');
        }
        $parts = explode(':', $contextKey, 3);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid contextKey.');
        }
        return [$parts[1], $parts[2]];
    }

    private function findActiveRow(string $companyId, string $environment): ?\Cake\Datasource\EntityInterface
    {
        /** @var \App\Model\Table\KsefAuthorizationsTable $KsefAuths */
        $KsefAuths = FactoryLocator::get('Table')->get('KsefAuthorizations');

        return $KsefAuths->find()
            ->where(['company_id' => $companyId, 'environment' => $environment, 'is_active' => 1])
            ->orderDesc('created')
            ->first();
    }

    private function getLatestPayload(string $companyId, string $environment): ?array
    {
        /** @var \App\Model\Table\KsefAuthorizationsTable $KsefAuths */
        $KsefAuths = FactoryLocator::get('Table')->get('KsefAuthorizations');

        $row = $KsefAuths->find()
            ->where(['company_id' => $companyId, 'environment' => $environment])
            ->orderDesc('created')
            ->first();

        if (!$row) {
            return null;
        }
        return $this->decryptPayload((string)$row->token_cipher);
    }

    private function decryptPayload(string $sealed): ?array
    {
        $plain = TokenVault::decrypt($sealed);
        if ($plain === '') {
            return null;
        }
        $data = json_decode($plain, true);
        if (!is_array($data)) {
            return null;
        }
        // upgrade v1 -> v2 bez sysToken/nip
        if (!isset($data['v'])) {
            $data['v'] = 1;
        }
        return $data;
    }

    private function extractJwtPayload(string $jwt): array
    {
        $p = explode('.', $jwt);
        if (count($p) !== 3) {
            return [];
        }
        $payload = $this->b64url_decode($p[1]);
        if ($payload === false) {
            return [];
        }
        $arr = json_decode($payload, true);
        return is_array($arr) ? $arr : [];
    }

    private function b64url_decode(string $data): string|false
    {
        $rem = strlen($data) % 4;
        if ($rem) {
            $data .= str_repeat('=', 4 - $rem);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

<?php
declare(strict_types=1);

namespace App\Service\Ksef;

/**
 * @deprecated Nie używane — docelowo N1KsefService odczytuje dane z DbKsefTokenStorage.
 */
interface KsefCredentialsProviderInterface
{
    /**
     * Zwraca ['nip' => '...', 'ksefToken' => '...'] dla company_id i środowiska.
     * Rzuć wyjątek jeśli brak danych.
     */
    public function getCredentials(string $companyId, string $environment): array;
}

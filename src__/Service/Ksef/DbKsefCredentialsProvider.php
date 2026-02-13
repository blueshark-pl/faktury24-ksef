<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Datasource\FactoryLocator;
use RuntimeException;

/**
 * @deprecated Dane uwierzytelniające (NIP + sysToken) są odczytywane bezpośrednio z DbKsefTokenStorage (payload v2).
 */
final class DbKsefCredentialsProvider implements KsefCredentialsProviderInterface
{
    public function __construct(
        private readonly ?string $nipField = 'nip',
        private readonly ?string $tokenField = 'ksef_token'
    ) {}

    public function getCredentials(string $companyId, string $environment): array
    {
        /** @var \App\Model\Table\CompaniesTable $Companies */
        $Companies = FactoryLocator::get('Table')->get('Companies');

        $row = $Companies->find()
            ->select(['id', $this->nipField, $this->tokenField])
            ->where(['id' => $companyId])
            ->first();

        if (!$row) {
            throw new RuntimeException('Firma nie znaleziona.');
        }
        $nip = (string)($row->{$this->nipField} ?? '');
        $token = (string)($row->{$this->tokenField} ?? '');

        if ($nip === '' || $token === '') {
            throw new RuntimeException('Brak NIP lub tokenu KSeF w records firmy.');
        }
        return ['nip' => $nip, 'ksefToken' => $token];
    }
}

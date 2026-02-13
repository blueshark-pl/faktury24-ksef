<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Intermedia\Ksef\Apiv2\AuthTokenRequest;
use Intermedia\Ksef\Apiv2\Models\Components\TContextIdentifier;
use Intermedia\Ksef\Apiv2\Models\Components\TNip;
use Intermedia\Ksef\Apiv2\Models\Components\SubjectIdentifierTypeEnum;

final class KsefXadesService
{
    public function signAuthTokenRequestToXml(string $challenge, string $nip): string
    {
        $req = new AuthTokenRequest(
            $challenge,
            TContextIdentifier::fromNip(new TNip($nip)),
            SubjectIdentifierTypeEnum::CERTIFICATE_SUBJECT
        );

        $priv = (string)Configure::read('Ksef.certificate.privateKeyPath');
        $cert = (string)Configure::read('Ksef.certificate.certPath');

        if ($priv === '' || !is_file($priv)) {
            throw new \RuntimeException('Brak private key: ' . $priv);
        }
        if ($cert === '' || !is_file($cert)) {
            throw new \RuntimeException('Brak cert: ' . $cert);
        }

        // private.pem + cert.pem
        return $req->signWithXadesToString($priv, $cert);
    }
}

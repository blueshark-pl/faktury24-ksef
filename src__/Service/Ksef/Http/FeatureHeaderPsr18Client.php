<?php
declare(strict_types=1);

namespace App\Service\Ksef\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client decorator.
 *
 * KSeF production appears to be stricter for some endpoints.
 * In our legacy client we were sending the feature header for invoice metadata.
 * The n1ebieski/ksef-php-client does not add it by default.
 */
final class FeatureHeaderPsr18Client implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Works for both "/api/v2/invoices/query/metadata" and "invoices/query/metadata".
        if (str_ends_with($path, '/invoices/query/metadata')) {
            if (!$request->hasHeader('X-KSeF-Feature')) {
                $request = $request->withHeader('X-KSeF-Feature', 'include-metadata');
            }
        }

        return $this->inner->sendRequest($request);
    }
}

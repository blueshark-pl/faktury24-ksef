<?php
declare(strict_types=1);

namespace App\Service\Jpk;

/**
 * Walidator XML JPK_V7M(3) względem schematu XSD z MF (obowiązuje od 2026-02-01).
 *
 * Wymagane pliki XSD obok tego walidatora (src/Service/Jpk/):
 *   - Schemat_JPK_V7M(3)_v1-0E.xsd  (główny, opublikowany 2025-12-19)
 *   - StrukturyDanych_v12-0E.xsd
 *   - KodyKrajow_v13-0E.xsd
 *   - KodyUrzedowSkarbowych_v8-0E.xsd
 *
 * Pobierz z: http://crd.gov.pl/wzor/2025/12/19/14090/schemat.xsd
 * (rozpakuj zależności z atrybutów schemaLocation tego XSD).
 *
 * libxml_set_external_entity_loader() mapuje URL-e crd.gov.pl → lokalne pliki po basename,
 * więc walidacja działa offline (crd.gov.pl czasem zwraca HTML zamiast XSD).
 */
class JpkV7mValidator
{
    private const XSD_PATH = __DIR__ . '/Schemat_JPK_V7M(3)_v1-0E.xsd';

    /**
     * @return array{valid: bool, errors: array, xsd_exists: bool}
     */
    public function validate(string $xml, ?string $xsdPath = null): array
    {
        $xsdPath = $xsdPath ?? self::XSD_PATH;

        if (!is_file($xsdPath)) {
            return [
                'valid'      => false,
                'xsd_exists' => false,
                'errors'     => [
                    [
                        'level'   => 'WARNING',
                        'message' => 'Brak pliku XSD pod ścieżką: ' . $xsdPath
                            . '. Pobierz oficjalny "Schemat_JPK_V7M(3)_v1-0E.xsd" z '
                            . 'http://crd.gov.pl/wzor/2025/12/19/14090/schemat.xsd i umieść w tej lokalizacji.',
                    ],
                ],
            ];
        }

        $useErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        if (!$loaded) {
            $errors = $this->collectErrors();
            libxml_use_internal_errors($useErrors);
            return ['valid' => false, 'xsd_exists' => true, 'errors' => $errors];
        }

        # crd.gov.pl czasem zwraca HTML (404/gateway), libxml wtedy nie umie sparsować
        # importów. Rejestrujemy entity loader → wszystkie URL-e do crd.gov.pl/.../DefinicjeTypy/
        # mapujemy na lokalne pliki obok głównego XSD.
        $schemaDir = __DIR__;
        $previousLoader = libxml_set_external_entity_loader(
            function ($public, $system, $context) use ($schemaDir) {
                if (is_string($system) && $system !== '') {
                    $basename = basename(parse_url($system, PHP_URL_PATH) ?: $system);
                    $local    = $schemaDir . DIRECTORY_SEPARATOR . $basename;
                    if (is_file($local)) {
                        return $local;
                    }
                }
                return $system;
            }
        );

        try {
            $isValid = @$dom->schemaValidate($xsdPath);
            $errors  = $this->collectErrors();
        } finally {
            # PHP 8+ wymaga callable|null — jeśli wcześniejszy loader nie jest callable, resetujemy do null
            libxml_set_external_entity_loader(
                ($previousLoader !== null && is_callable($previousLoader)) ? $previousLoader : null
            );
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
        }

        return [
            'valid'      => $isValid,
            'xsd_exists' => true,
            'errors'     => $errors,
        ];
    }

    private function collectErrors(): array
    {
        $out = [];
        foreach (libxml_get_errors() as $err) {
            $level = match ($err->level) {
                LIBXML_ERR_WARNING => 'WARNING',
                LIBXML_ERR_ERROR   => 'ERROR',
                LIBXML_ERR_FATAL   => 'FATAL',
                default            => 'UNKNOWN',
            };
            $out[] = [
                'level'   => $level,
                'code'    => $err->code,
                'line'    => $err->line,
                'column'  => $err->column,
                'message' => trim($err->message),
            ];
        }
        return $out;
    }
}

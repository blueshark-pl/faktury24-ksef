<?php
declare(strict_types=1);

namespace App\Service\Jpk;

/**
 * Walidator XML JPK_V7M względem schematu XSD z MF.
 *
 * Aby działał, plik XSD JPK_V7M musi być umieszczony w:
 *   G:/2025/partnersc/faktury24/src/Service/Jpk/Schemat_JPK_V7M(2)_v1-0E.xsd
 *
 * Pobierz oficjalny XSD z:
 *   https://www.podatki.gov.pl/jpk/struktury-jpk/
 *   (sekcja: JPK_V7M wariant 2)
 */
class JpkV7mValidator
{
    private const XSD_PATH = __DIR__ . '/Schemat_JPK_V7M(2)_v1-0E.xsd';

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
                            . '. Pobierz oficjalny "Schemat_JPK_V7M(2)_v1-0E.xsd" ze strony '
                            . 'https://www.podatki.gov.pl/jpk/struktury-jpk/ i umieść w tej lokalizacji.',
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

        $isValid = @$dom->schemaValidate($xsdPath);
        $errors  = $this->collectErrors();
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

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

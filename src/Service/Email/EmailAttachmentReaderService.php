<?php
declare(strict_types=1);

namespace App\Service\Email;

use Cake\Log\Log;

/**
 * FALA 16: Ekstraktor tresci z zalacznikow emailowych.
 *
 * Obslugiwane typy:
 *  - image/* (png/jpg/gif/webp): zwraca data URI - do wyslania do GPT Vision
 *  - application/pdf: shell pdftotext (poppler) - jesli dostepny; fallback: smalot/pdfparser jesli zainstalowany
 *  - text/csv, text/plain: raw utf-8
 *  - application/vnd.ms-excel, .xlsx: TODO - wymaga PhpSpreadsheet composer require
 *
 * Best-effort: kazde zapis loguje warning zamiast rzucac wyjatek.
 */
class EmailAttachmentReaderService
{
    /**
     * @param string $binaryContent  Zdekodowana zawartosc pliku (binary blob)
     * @param string $mime           MIME type (np. 'application/pdf', 'image/png')
     * @param string $filename       Nazwa pliku (dla logow + dobor MIME z rozszerzenia jesli brakuje)
     * @return array{type: string, content: string|null, error: string|null}
     *   type: 'text' | 'image' | 'unsupported' | 'error'
     *   content: dla 'text' - utf-8 tekst; dla 'image' - data URI 'data:mime;base64,X'; null przy 'unsupported'/'error'
     *   error: opis bledu (dla type=error/unsupported)
     */
    public function read(string $binaryContent, string $mime, string $filename = ''): array
    {
        $mime = strtolower(trim($mime));
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Sprobuj domyslic MIME z rozszerzenia jesli MIME jest generyczny
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mimeMap = [
                'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
                'txt' => 'text/plain', 'csv' => 'text/csv',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'eml' => 'message/rfc822',
            ];
            $mime = $mimeMap[$ext] ?? $mime;
        }

        // Image -> data URI
        if (str_starts_with($mime, 'image/')) {
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binaryContent);
            return ['type' => 'image', 'content' => $dataUri, 'error' => null];
        }

        // PDF -> pdftotext (jesli dostepny) lub smalot/pdfparser
        if ($mime === 'application/pdf') {
            $text = $this->extractPdfText($binaryContent);
            if ($text === null) {
                return ['type' => 'error', 'content' => null,
                    'error' => 'PDF text extraction failed - brak pdftotext CLI i smalot/pdfparser'];
            }
            return ['type' => 'text', 'content' => $text, 'error' => null];
        }

        // Plain text / CSV
        if (str_starts_with($mime, 'text/')) {
            // Ensure UTF-8
            $enc = mb_detect_encoding($binaryContent, ['UTF-8', 'Windows-1250', 'ISO-8859-2', 'ISO-8859-1'], true);
            if ($enc && $enc !== 'UTF-8') {
                $binaryContent = @iconv($enc, 'UTF-8//IGNORE', $binaryContent) ?: $binaryContent;
            }
            return ['type' => 'text', 'content' => $binaryContent, 'error' => null];
        }

        // EML (forwarded email jako zalacznik)
        if ($mime === 'message/rfc822') {
            // Prosta ekstrakcja - wywal tylko headers i return body
            $text = $binaryContent;
            $bodyStart = strpos($text, "\r\n\r\n") ?: strpos($text, "\n\n");
            if ($bodyStart !== false) {
                $text = substr($text, $bodyStart);
            }
            return ['type' => 'text', 'content' => trim($text), 'error' => null];
        }

        // TODO: xlsx/docx wymaga composer require
        return ['type' => 'unsupported', 'content' => null,
            'error' => "MIME '{$mime}' (.{$ext}) niewspierany. Dla XLSX/DOCX wymaga phpoffice/phpspreadsheet + phpoffice/phpword."];
    }

    /**
     * Ekstraktuj text z PDF przez pdftotext (poppler CLI) lub smalot/pdfparser fallback.
     * @return string|null tekst lub null przy failure
     */
    private function extractPdfText(string $pdfContent): ?string
    {
        // Sprobuj pdftotext CLI - najszybszy i najlepszy
        $pdftotextBin = $this->findBinary('pdftotext');
        if ($pdftotextBin !== null) {
            try {
                // pdftotext - - reads from stdin, writes to stdout
                $descriptorSpec = [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w'], // stderr
                ];
                $proc = proc_open($pdftotextBin . ' -layout -enc UTF-8 - -', $descriptorSpec, $pipes);
                if (is_resource($proc)) {
                    fwrite($pipes[0], $pdfContent);
                    fclose($pipes[0]);
                    $out = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($proc);
                    if ($out !== false && $out !== '') {
                        return $out;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('pdftotext failed: ' . $e->getMessage());
            }
        }

        // Fallback: smalot/pdfparser (jesli composer require zrobiony)
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($pdfContent);
                return $pdf->getText();
            } catch (\Throwable $e) {
                Log::warning('smalot/pdfparser failed: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Znajdz binary CLI w systemowej sciezce - Linux/Mac/Windows compatible.
     */
    private function findBinary(string $name): ?string
    {
        // Windows uses .exe suffix
        if (DIRECTORY_SEPARATOR === '\\') {
            $name .= '.exe';
        }
        // Typowe lokalizacje na Linux (cyberfolks + inne shared hostings)
        $candidates = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/opt/local/bin/' . $name,
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) return $path;
        }
        // Fallback: which/where
        $whichCmd = DIRECTORY_SEPARATOR === '\\' ? 'where' : 'which';
        $found = @shell_exec($whichCmd . ' ' . escapeshellarg($name));
        if ($found && trim($found) !== '') {
            $first = strtok(trim($found), "\n");
            if ($first !== false && is_executable(trim($first))) return trim($first);
        }
        return null;
    }
}

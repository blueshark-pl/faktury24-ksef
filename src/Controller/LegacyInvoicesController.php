<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class LegacyInvoicesController extends AppController
{
    /**
     * Strona listy faktur ze starego systemu faktury24.com.
     * Sama strona – dane ładowane AJAX-em przez fetch().
     */
    public function index(): void
    {
        $this->set('title', 'Faktury z faktury24 (archiwum)');
    }

    /**
     * PHP proxy – pobiera PDF faktury ze starego systemu i strumieniuje go do przeglądarki.
     * Parametry GET: uuid (string), lang (string, domyślnie 'pl').
     */
    public function downloadPdf(): Response
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        $Companies = $this->fetchTable('Companies');
        $company = $Companies->find()
            ->select(['id', 'nip'])
            ->where(['id' => $companyId])
            ->first();

        $nip = preg_replace('/\D+/', '', (string)($company->nip ?? ''));
        if ($nip === '') {
            return $this->response->withStatus(400)->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak NIP-u firmy.']));
        }

        $uuid = trim((string)$this->request->getQuery('uuid', ''));
        if ($uuid === '' || !preg_match('/^[0-9a-f\-]{36}$/i', $uuid)) {
            return $this->response->withStatus(400)->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Nieprawidłowy UUID faktury.']));
        }

        $lang = in_array($this->request->getQuery('lang', 'pl'), ['pl', 'en'], true)
            ? $this->request->getQuery('lang', 'pl')
            : 'pl';

        try {
            $client = new \Cake\Http\Client();
            $resp = $client->get(
                'https://archiwum.faktury24.com/ajax/get_invoice_pdf_by_nip',
                ['uuid' => $uuid, 'nip' => $nip, 'lang' => $lang],
                ['timeout' => 30]
            );

            $contentType = $resp->getHeaderLine('Content-Type') ?: 'application/pdf';

            // Jeśli serwer zwrócił błąd (np. JSON z błędem zamiast PDF)
            if (!str_contains($contentType, 'pdf') && !str_contains($contentType, 'octet-stream')) {
                return $this->response->withStatus(502)->withType('application/json')
                    ->withStringBody(json_encode([
                        'error' => 'Serwer archiwum.faktury24.com nie zwrócił pliku PDF.',

                    ]));
            }

            $filename = 'faktura-' . preg_replace('/[^a-z0-9\-]/i', '-', $uuid) . '.pdf';

            return $this->response
                ->withType('application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withStringBody($resp->getStringBody());
        } catch (\Throwable $e) {
            return $this->response->withStatus(502)->withType('application/json')
                ->withStringBody(json_encode([
                    'error' => 'Błąd pobierania PDF: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * PHP proxy – pobiera faktury ze starego systemu dla NIP bieżącej firmy.
     * Parametry GET: rok (int), miesiac (int 1-12).
     * Nigdy nie ujawnia wywołania faktury24.com w przeglądarce klienta.
     */
    public function fetch(): Response
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        // Pobierz NIP bieżącej firmy
        $Companies = $this->fetchTable('Companies');
        $company = $Companies->find()
            ->select(['id', 'nip'])
            ->where(['id' => $companyId])
            ->first();

        $nip = preg_replace('/\D+/', '', (string)($company->nip ?? ''));
        if ($nip === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => 'Brak NIP-u firmy. Uzupełnij dane firmy przed użyciem archiwum.',
                ]));
        }

        // Walidacja i normalizacja parametrów
        $rok     = (int)($this->request->getQuery('rok', date('Y')));
        $miesiac = (int)($this->request->getQuery('miesiac', (int)date('n')));

        // Limity: max 2026/03
        $maxRok     = 2026;
        $maxMiesiac = 3;
        if ($rok > $maxRok) $rok = $maxRok;
        if ($rok < 2010)    $rok = 2010;
        if ($miesiac < 1)   $miesiac = 1;
        if ($miesiac > 12)  $miesiac = 12;
        if ($rok === $maxRok && $miesiac > $maxMiesiac) $miesiac = $maxMiesiac;

        try {
            $client = new \Cake\Http\Client();
            $resp = $client->get(
                'https://archiwum.faktury24.com/ajax/get_invoices_by_nip',
                ['nip' => $nip, 'rok' => $rok, 'miesiac' => $miesiac],
                ['timeout' => 20]
            );
            $json = json_decode($resp->getStringBody(), true);

            if (!is_array($json) || ($json['status'] ?? 0) !== 200) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'error'   => 'Serwer archiwum.faktury24.com nie zwrócił wyników (status: ' . ($json['status'] ?? '?') . ').',

                    ]));
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'rok'     => $rok,
                    'miesiac' => $miesiac,
                    'count'   => count($json['payload'] ?? []),
                    'payload' => $json['payload'] ?? [],
                ]));
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'error'   => 'Błąd połączenia z archiwum.faktury24.com: ' . $e->getMessage(),
                ]));
        }
    }
}

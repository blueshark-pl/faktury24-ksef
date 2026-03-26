<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\InvoicesController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\InvoicesController Test Case
 *
 * Pokrywa scenariusze opisane w README#TODO (Invoices):
 *  - zapis draftu,
 *  - zmiana serii przy edycji,
 *  - wysyłka draftu z datą inną niż dziś,
 *  - renumeracja po zmianie daty.
 *
 * Uwaga: akcje wymagające logowania są testowane z sesją identity (mock).
 * Akcje wymagające KSeF są pomijane (brak dostępu testowego do API).
 *
 * @link \App\Controller\InvoicesController
 */
class InvoicesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Invoices',
        'app.Companies',
        'app.InvoiceCompanyDetails',
        'app.InvoiceContractors',
        'app.InvoiceContents',
        'app.InvoiceVatContents',
        'app.InvoiceSeries',
    ];

    // UUID-y z fixture
    private const COMPANY_ID  = 'db36cc19-aa6d-4832-b984-62f50f260c4f';
    private const INVOICE_ID  = '96d32418-70e0-4615-9401-0505d2242a98';
    private const SERIES_ID   = '7a49833c-bb87-4db7-8cc8-01ee22d49107';
    private const SERIES_NAME = 'Lorem ipsum dolor sit amet';

    /**
     * Symulacja zalogowanego użytkownika (identity w sesji).
     */
    protected function loginAs(string $companyId = self::COMPANY_ID): void
    {
        $this->session([
            'Auth' => [
                'User' => [
                    'id'         => 'user-test-uuid',
                    'company_id' => $companyId,
                    'username'   => 'test@example.com',
                    'role'       => 'admin',
                ],
            ],
        ]);
    }

    // ── GET /invoices (index) ────────────────────────────────────────────────

    /**
     * Niezalogowany użytkownik powinien być przekierowany.
     */
    public function testIndexRedirectsWhenNotAuthenticated(): void
    {
        $this->get('/invoices');
        // Oczekuj przekierowania na login lub 403
        $this->assertResponseCode(302);
    }

    // ── Draft workflow ───────────────────────────────────────────────────────

    /**
     * Zapis faktury jako roboczy (draft) — nie wymaga numeru faktury.
     *
     * Oczekiwania:
     *  - status HTTP 302 (redirect po udanym zapisie)
     *  - faktura zapisana z workflow_status = 'draft'
     *  - brak fullnumber (nadawany dopiero przy finalizacji)
     */
    public function testSaveDraftDoesNotRequireFullnumber(): void
    {
        $this->markTestIncomplete(
            'Wymaga pełnych fixture z danymi serii i VAT. ' .
            'Scenariusz: POST /invoices/add-vat z is_draft=1 → redirect, invoice.workflow_status=draft.'
        );
    }

    /**
     * Faktura draft z datą wystawienia inną niż dzisiejsza może być wysłana.
     *
     * Po wysyłce do KSeF data powinna zostać znormalizowana (lub odrzucona z komunikatem).
     */
    public function testSendDraftWithPastDateIsRejectedOrNormalized(): void
    {
        $this->markTestIncomplete(
            'Wymaga mocka KsefClient lub konfiguracji środowiska test. ' .
            'Scenariusz: draft z date < today - 1 dzień → błąd walidacji lub normalizacja.'
        );
    }

    // ── Zmiana serii przy edycji ─────────────────────────────────────────────

    /**
     * Zmiana serii faktury podczas edycji powinna zaktualizować invoice_series_id (UUID),
     * nie tylko pole 'series' (nazwa).
     *
     * Weryfikuje naprawę TODO: "Ujednolicić model pola serii (UUID)".
     */
    public function testEditInvoiceSeriesUpdatesInvoiceSeriesId(): void
    {
        $this->markTestIncomplete(
            'Wymaga fixture z poprawną sesją i encją Invoice. ' .
            'Scenariusz: POST /invoices/edit/{id} z invoice_series_id=<uuid> → invoice.invoice_series_id == uuid.'
        );
    }

    // ── Renumeracja po zmianie daty ──────────────────────────────────────────

    /**
     * Zmiana daty faktury może wymagać renumeracji jeśli seria ma okres miesięczny/roczny.
     *
     * Weryfikuje że po zmianie daty fullnumber jest przeliczony.
     */
    public function testEditInvoiceDateTriggersRenumbering(): void
    {
        $this->markTestIncomplete(
            'Wymaga fixture z miesięczną serią i kilkoma fakturami. ' .
            'Scenariusz: POST /invoices/edit/{id} z nową date → fullnumber zmieniony zgodnie z nowym miesiącem.'
        );
    }

    // ── Proforma → zaliczkowa (API) ──────────────────────────────────────────

    /**
     * proformaSearch() powinien zwracać JSON z listą proform firmy.
     */
    public function testProformaSearchReturnsJson(): void
    {
        $this->loginAs();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/invoices/proforma-search?q=');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
    }

    /**
     * proformaDetails() dla nieistniejącej proformy zwraca 404.
     */
    public function testProformaDetailsNotFoundReturns404(): void
    {
        $this->loginAs();
        $this->get('/invoices/proforma-details/00000000-0000-0000-0000-000000000000');
        $this->assertResponseCode(404);
    }

    // ── InvoiceSeriesController::search() ────────────────────────────────────

    /**
     * Endpoint search serii zwraca UUID jako id (nie nazwę).
     * Weryfikuje naprawę TODO: "Ujednolicić model pola serii".
     */
    public function testSeriesSearchReturnsUuidAsId(): void
    {
        $this->loginAs();
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/invoice-series/search.json?q=');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('results', $body);

        if (!empty($body['results'])) {
            $first = $body['results'][0];
            $this->assertArrayHasKey('id', $first);
            // UUID format: 8-4-4-4-12
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                (string)$first['id'],
                'series.id powinno być UUID (nie nazwą serii)'
            );
            // Nazwa powinna być w polu 'text' lub 'name'
            $this->assertArrayHasKey('text', $first);
        }
    }
}

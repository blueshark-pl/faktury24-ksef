<?php
/**
 * Routes configuration.
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    // Używamy DashedRoute (np. /gus-lookup → gusLookup)
    $routes->setRouteClass(DashedRoute::class);

    // GŁÓWNY SCOPE APLIKACJI
    $routes->scope('/', function (RouteBuilder $builder): void {
        // Włącz obsługę rozszerzeń .json (i ewentualnie .xml gdybyś chciał)
        $builder->setExtensions(['json']);

        // Strona główna
        $builder->connect('/', ['controller' => 'Invoices', 'action' => 'index']);

        // Pages controller
        $builder->connect('/pages/*', 'Pages::display');

        // --- API/akcje AJAX, które chcesz wywoływać z JS ---

        // GUS lookup (POST): /contractors/gus-lookup oraz /contractors/gus-lookup.json
        $builder->post('/contractors/gus-lookup', 'Contractors::gusLookup');
        $builder->post('/contractors/search', 'Contractors::search');
        $builder->get('/contractors/import-fetch', 'Contractors::importFetch');
        $builder->post('/contractors/import-batch', 'Contractors::importBatch');
        $builder->get('/contractors/import-speed-fetch', 'Contractors::importSpeedFetch');
        $builder->post('/contractors/import-speed-batch', 'Contractors::importSpeedBatch');
        // Faktury kosztowe
        $builder->get('/koszty', 'CostInvoices::index');
        $builder->get('/koszty/import-ksef', 'CostInvoices::importKsef');
        $builder->post('/koszty/do-import-ksef', 'CostInvoices::doImportKsef');
        $builder->get('/koszty/search', 'CostInvoices::searchAjax');
        $builder->post('/koszty/assign-order', 'CostInvoices::assignOrder');
        $builder->post('/koszty/unassign-order', 'CostInvoices::unassignOrder');
        $builder->post('/koszty/set-status', 'CostInvoices::setStatus');
        $builder->connect('/koszty/add', ['controller' => 'CostInvoices', 'action' => 'add']);
        $builder->connect('/koszty/edit/{id}', ['controller' => 'CostInvoices', 'action' => 'edit'])->setPass(['id']);
        $builder->post('/koszty/delete/{id}', ['controller' => 'CostInvoices', 'action' => 'delete'])->setPass(['id']);
        $builder->get('/koszty/view/{id}', ['controller' => 'CostInvoices', 'action' => 'view'])->setPass(['id']);

        $builder->get('/zlecenia', 'SpeedOrders::index');
        $builder->get('/zlecenia/dashboard', 'SpeedOrders::dashboard');
        $builder->get('/zlecenia/export-csv', 'SpeedOrders::exportCsv');
        $builder->get('/zlecenia/view/{id}', 'SpeedOrders::view');
        $builder->get('/zlecenia/view-modal/{id}', 'SpeedOrders::viewModal');
        $builder->post('/zlecenia/sync', 'SpeedOrders::sync');
        $builder->post('/zlecenia/create-batch-invoices', 'SpeedOrders::createBatchInvoices');
        $builder->post('/zlecenia/update-status', 'SpeedOrders::updateStatus');
        $builder->post('/zlecenia/{id}/upload-attachment', ['controller' => 'SpeedOrders', 'action' => 'uploadAttachment'])
            ->setPass(['id']);
        $builder->post('/zlecenia/{orderId}/delete-attachment/{attachmentId}', ['controller' => 'SpeedOrders', 'action' => 'deleteAttachment'])
            ->setPass(['orderId', 'attachmentId']);
        $builder->get('/invoices/print-custom/{id}', ['controller' => 'Invoices', 'action' => 'printCustom'])->setPass(['id']);
        $builder->get('/invoices/{id}/label', ['controller' => 'Invoices', 'action' => 'getLabel'])->setPass(['id']);
        $builder->post('/invoices/{id}/label', ['controller' => 'Invoices', 'action' => 'generateLabel'])->setPass(['id']);
        $builder->post('/invoices/{id}/mark-sent', ['controller' => 'Invoices', 'action' => 'markSent'])->setPass(['id']);
        $builder->get('/invoices/scan/{id}/{token}', ['controller' => 'Invoices', 'action' => 'scanLabel'])->setPass(['id', 'token']);
        $builder->get('/products/import-fetch', 'Products::importFetch');
        $builder->post('/products/import-batch', 'Products::importBatch');
        $builder->get('/archiwum-faktur', ['controller' => 'LegacyInvoices', 'action' => 'index']);
        $builder->get('/archiwum-faktur/pobierz', ['controller' => 'LegacyInvoices', 'action' => 'fetch']);
        $builder->get('/archiwum-faktur/pdf', ['controller' => 'LegacyInvoices', 'action' => 'downloadPdf']);
        $builder->post('/invoice-series/search', 'InvoiceSeries::search');
        $builder->connect('/invoices/validate-ajax', ['controller' => 'Invoices', 'action' => 'validateAjax'], ['_method' => ['POST', 'PUT', 'PATCH']]);

        // config/routes.php
        $builder->connect('/firma/edycja', ['controller' => 'Companies', 'action' => 'edit']);
        $builder->get('/firma/serie/sprawdz', ['controller' => 'Companies', 'action' => 'checkSeriesStart']);
        $builder->connect('/uzytkownik/profil', ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']);
        $builder->get('/konto/2fa', ['controller' => 'TwoFactor', 'action' => 'index']);
        $builder->post('/konto/2fa/wlacz', ['controller' => 'TwoFactor', 'action' => 'enable']);
        $builder->post('/konto/2fa/weryfikuj', ['controller' => 'TwoFactor', 'action' => 'verify']);
        $builder->post('/konto/2fa/wylacz', ['controller' => 'TwoFactor', 'action' => 'disable']);
        $builder->post('/invoices/{id}/send-to-ksef', ['controller' => 'Invoices', 'action' => 'sendToKsef'])
            ->setPass(['id']);
        $builder->connect('/invoices/ksef-auth/active', ['controller' => 'Invoices', 'action' => 'ksefAuthActive']);
        $builder->connect('/invoices/ksef-auth/login',  ['controller' => 'Invoices', 'action' => 'ksefAuthLogin']);
        $builder->connect('/invoices/ksef-smoke',       ['controller' => 'Invoices', 'action' => 'ksefSmoke']);
// config/routes.php
$builder->connect('/invoices/ksef/download', ['controller' => 'Invoices', 'action' => 'downloadKsef']);
$builder->connect('/invoices/ksef/metadata', ['controller' => 'Invoices', 'action' => 'metadataKsef']);

        // Tokeny API — zarządzanie przez użytkownika
        $builder->get('/api-tokens', ['controller' => 'ApiTokens', 'action' => 'index']);
        $builder->post('/api-tokens/generate', ['controller' => 'ApiTokens', 'action' => 'generate']);
        $builder->post('/api-tokens/revoke/{id}', ['controller' => 'ApiTokens', 'action' => 'revoke'])
            ->setPass(['id']);

        // Wewnętrzny endpoint PDF dla crona kolejki e-mail
        $builder->get('/invoices/generate-pdf-internal/{id}', ['controller' => 'Invoices', 'action' => 'generatePdfInternal'])
            ->setPass(['id']);
        // Endpoint HTTP do przetworzenia kolejki e-mail (cron URL)
        $builder->connect('/invoices/process-email-queue', ['controller' => 'Invoices', 'action' => 'processEmailQueue']);

        // Import użytkowników ze starego systemu (jednorazowy, wywoływany z przeglądarki)
        $builder->get('/import-legacy-users', ['controller' => 'Import', 'action' => 'importLegacyUsers']);

        // SSO — logowanie z systemu księgowego (portal.partnersc.com)
        $builder->get('/sso/login', ['controller' => 'Sso', 'action' => 'login']);

        // (opcjonalnie) wyszukiwarka kontrahentów i produktów pod Select2:
        // $builder->get('/contractors/search', 'Contractors::search');
        // $builder->get('/products/search', 'Products::search');

        // Tasks — tablica Kanban (tylko admin)
        $builder->get('/tasks', ['controller' => 'Tasks', 'action' => 'index']);
        $builder->get('/tasks/add', ['controller' => 'Tasks', 'action' => 'add']);
        $builder->post('/tasks/add', ['controller' => 'Tasks', 'action' => 'add']);
        $builder->get('/tasks/labels', ['controller' => 'Tasks', 'action' => 'labels']);
        $builder->post('/tasks/labels', ['controller' => 'Tasks', 'action' => 'labels']);
        $builder->post('/tasks/move', ['controller' => 'Tasks', 'action' => 'move']);
        $builder->connect('/tasks/{id}', ['controller' => 'Tasks', 'action' => 'view'])
            ->setPass(['id']);
        $builder->post('/tasks/{id}', ['controller' => 'Tasks', 'action' => 'view'])
            ->setPass(['id']);
        $builder->post('/tasks/{id}/start-timer', ['controller' => 'Tasks', 'action' => 'startTimer'])
            ->setPass(['id']);
        $builder->post('/tasks/{id}/stop-timer', ['controller' => 'Tasks', 'action' => 'stopTimer'])
            ->setPass(['id']);

        // Admin — lista i usuwanie faktur wszystkich użytkowników
        $builder->post('/invoices/check-rates-batch', ['controller' => 'Invoices', 'action' => 'checkRatesBatch']);
        $builder->get('/admin/check-currency-rates', ['controller' => 'Invoices', 'action' => 'checkCurrencyRates']);
        $builder->get('/admin/faktury', ['controller' => 'Invoices', 'action' => 'adminInvoices']);
        $builder->get('/admin/szkice', ['controller' => 'Invoices', 'action' => 'adminDrafts']);
        $builder->get('/admin/logi-usuniec', ['controller' => 'Invoices', 'action' => 'adminDeletionLogs']);
        $builder->get('/admin/zgloszenia', ['controller' => 'Invoices', 'action' => 'adminSupport']);
        $builder->connect('/admin/zgloszenia/{id}', ['controller' => 'Invoices', 'action' => 'adminSupportView'])
            ->setPass(['id']);
        $builder->post('/admin/faktury/{id}/delete', ['controller' => 'Invoices', 'action' => 'adminDelete'])
            ->setPass(['id']);

        // Rozliczenia
        $builder->get('/rozliczenia', ['controller' => 'Reconciliations', 'action' => 'index']);
        $builder->post('/rozliczenia/add-payment', ['controller' => 'Reconciliations', 'action' => 'addPayment']);
        $builder->post('/rozliczenia/delete-payment/{id}', ['controller' => 'Reconciliations', 'action' => 'deletePayment'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/bank-transactions/{id}', ['controller' => 'Reconciliations', 'action' => 'bankTransactions'])
            ->setPass(['id']);

        // Wyciągi bankowe MT940
        $builder->get('/wyciagi', ['controller' => 'BankTransactions', 'action' => 'index']);
        $builder->get('/wyciagi/transakcje', ['controller' => 'BankTransactions', 'action' => 'transactions']);
        $builder->connect('/wyciagi/import', ['controller' => 'BankTransactions', 'action' => 'import']);
        $builder->connect('/wyciagi/view/{id}', ['controller' => 'BankTransactions', 'action' => 'view'])
            ->setPass(['id']);
        $builder->post('/wyciagi/delete/{id}', ['controller' => 'BankTransactions', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->post('/wyciagi/confirm-match/{id}', ['controller' => 'BankTransactions', 'action' => 'confirmMatch'])
            ->setPass(['id']);
        $builder->post('/wyciagi/ignore/{id}', ['controller' => 'BankTransactions', 'action' => 'ignoreTransaction'])
            ->setPass(['id']);

        // Fallbacks (na końcu)
        $builder->fallbacks();
    });

    // ── Zewnętrzne API v1 (uwierzytelnianie przez Bearer token) ──────────────
    $routes->prefix('Api', ['path' => '/api/v1'], function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        // GET  /api/v1/series         — lista serii numeracji
        $builder->get('/series', ['controller' => 'Invoices', 'action' => 'series']);
        // GET  /api/v1/bank-accounts  — lista rachunków bankowych
        $builder->get('/bank-accounts', ['controller' => 'Invoices', 'action' => 'bankAccounts']);
        // GET  /api/v1/invoices       — lista faktur
        $builder->get('/invoices', ['controller' => 'Invoices', 'action' => 'index']);
        // POST /api/v1/invoices       — wystaw fakturę VAT
        $builder->post('/invoices', ['controller' => 'Invoices', 'action' => 'create']);
        // GET  /api/v1/invoices/{id}  — szczegóły faktury
        $builder->get('/invoices/{id}', ['controller' => 'Invoices', 'action' => 'get'])
            ->setPass(['id']);
        // GET  /api/v1/invoices/{id}/pdf    — pobierz PDF faktury
        $builder->get('/invoices/{id}/pdf', ['controller' => 'Invoices', 'action' => 'pdf'])
            ->setPass(['id']);
        // GET  /api/v1/invoices/{id}/status — lekki status (workflow, ksef)
        $builder->get('/invoices/{id}/status', ['controller' => 'Invoices', 'action' => 'status'])
            ->setPass(['id']);
        // POST /api/v1/invoices/{id}/issue — szkic → wystawiona
        $builder->post('/invoices/{id}/issue', ['controller' => 'Invoices', 'action' => 'issue'])
            ->setPass(['id']);
        // POST /api/v1/invoices/{id}/send-ksef — wystawiona → wysyłka do KSeF
        $builder->post('/invoices/{id}/send-ksef', ['controller' => 'Invoices', 'action' => 'sendKsef'])
            ->setPass(['id']);
        // POST /api/v1/invoices/{id}/payments — dodaj rozliczenie
        $builder->post('/invoices/{id}/payments', ['controller' => 'Invoices', 'action' => 'addPayment'])
            ->setPass(['id']);
    });

    // (opcjonalnie) Możesz dorobić osobny scope dla API:
    $routes->scope('/api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        // KSeF: otrzymane – JSON API
        $builder->get('/ksef/received', ['controller' => 'KsefAuthorizations', 'action' => 'receivedApi']);
        // KSeF: lekki status check (AJAX)
        $builder->get('/ksef/status', ['controller' => 'KsefAuthorizations', 'action' => 'statusApi']);
        // KSeF: status workflow faktury kosztowej (FV DO POTWIERDZENIA, ZAAKCEPTOWANA, etc.)
        $builder->post('/ksef/invoice-status', ['controller' => 'KsefAuthorizations', 'action' => 'setInvoiceStatus']);
        // KSeF: wystawione – JSON API
        $builder->get('/ksef/issued', ['controller' => 'KsefAuthorizations', 'action' => 'issuedApi']);
        // KSeF: uprawnienia (personal grants) – JSON API
        $builder->get('/ksef/personal-grants', ['controller' => 'KsefAuthorizations', 'action' => 'personalGrantsApi']);
        // KSeF: check auth + uprawnienia (personal grants) – JSON API
        $builder->get('/ksef/personal-grants/check', ['controller' => 'KsefAuthorizations', 'action' => 'personalGrantsCheckApi']);
        // KSeF: uprawnienia podmiotowe (authorizations grants) – JSON API
        $builder->get('/ksef/authorizations-grants', ['controller' => 'KsefAuthorizations', 'action' => 'authorizationsGrantsApi']);
        // KSeF: pozycje (wiersze) faktury – JSON API
        $builder->get('/ksef/lines/{ksefNumber}', ['controller' => 'KsefAuthorizations', 'action' => 'linesApi'])
            ->setPass(['ksefNumber']);
        // KSeF: podgląd XML (download)
        $builder->get('/ksef/preview/{ksefNumber}', ['controller' => 'KsefAuthorizations', 'action' => 'previewApi'])
            ->setPass(['ksefNumber']);
        // KSeF: pobranie XML jako załącznik
        $builder->get('/ksef/download/{ksefNumber}', ['controller' => 'KsefAuthorizations', 'action' => 'downloadApi'])
            ->setPass(['ksefNumber']);

        // Invoices: generowanie PDF z przesłanego XML
        $builder->post('/invoices/print', ['controller' => 'Invoices', 'action' => 'printApi']);
        // przykłady istniejących akcji API
        $builder->post('/contractors/gus-lookup', 'Contractors::gusLookup');
        $builder->fallbacks();
    });
};

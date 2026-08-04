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
        $builder->post('/koszty/mark-paid', 'CostInvoices::markPaid');
        $builder->post('/koszty/unmark-paid', 'CostInvoices::unmarkPaid');
        $builder->post('/koszty/{id}/add-payment', ['controller' => 'CostInvoices', 'action' => 'addPayment'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/payment/{paymentId}/delete', ['controller' => 'CostInvoices', 'action' => 'deletePayment'])
            ->setPass(['paymentId']);
        $builder->get('/koszty/{id}/bank-transactions', ['controller' => 'CostInvoices', 'action' => 'bankTxForCost'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/sync-ksef-auto', 'CostInvoices::syncKsefAuto');
        $builder->post('/koszty/set-cost-status', 'CostInvoices::setCostStatus');
        $builder->get('/koszty/{id}/lines', ['controller' => 'CostInvoices', 'action' => 'getLines'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/{id}/lines/save', ['controller' => 'CostInvoices', 'action' => 'saveLines'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/{id}/lines/ai-suggest', ['controller' => 'CostInvoices', 'action' => 'aiSuggestLines'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->get('/koszty/{id}/orders-search', ['controller' => 'CostInvoices', 'action' => 'searchOrdersForCost'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->get('/koszty/{id}/notes', ['controller' => 'CostInvoices', 'action' => 'getNotes'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/{id}/notes/add', ['controller' => 'CostInvoices', 'action' => 'addNote'])
            ->setPass(['id'])->setPatterns(['id' => '\d+']);
        $builder->post('/koszty/notes/{noteId}/delete', ['controller' => 'CostInvoices', 'action' => 'deleteNote'])
            ->setPass(['noteId']);
        // Cron endpoint — bez sesji, autoryzacja przez token w query lub header X-Cron-Token
        $builder->get('/api/cron/cost-invoices/sync/{companyId}', ['controller' => 'CostInvoices', 'action' => 'cronSyncKsef'])
            ->setPass(['companyId'])->setPatterns(['companyId' => '[0-9a-f-]{36}']);
        $builder->post('/api/cron/cost-invoices/sync/{companyId}', ['controller' => 'CostInvoices', 'action' => 'cronSyncKsef'])
            ->setPass(['companyId'])->setPatterns(['companyId' => '[0-9a-f-]{36}']);
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
        // Reczne tworzenie zlecen (source='manual')
        $builder->connect('/zlecenia/dodaj',       ['controller' => 'SpeedOrders', 'action' => 'add']);
        $builder->connect('/zlecenia/edytuj/{id}', ['controller' => 'SpeedOrders', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/zlecenia/usun/{id}',      ['controller' => 'SpeedOrders', 'action' => 'delete'])
            ->setPass(['id']);
        // UWAGA: setExtensions(['json']) na scope stripuje .json z URL przed matchowaniem,
        // wiec trasy definiujemy BEZ .json w path. URL /zlecenia/drivers.json → matcher
        // szuka /zlecenia/drivers z ext=json.
        $builder->get('/zlecenia/drivers',              ['controller' => 'SpeedOrders', 'action' => 'driversJson']);
        $builder->get('/zlecenia/vehicles',             ['controller' => 'SpeedOrders', 'action' => 'vehiclesJson']);
        $builder->get('/zlecenia/ostatnie-dla-klienta', ['controller' => 'SpeedOrders', 'action' => 'lastForBuyerJson']);
        $builder->get('/zlecenia/cities',               ['controller' => 'SpeedOrders', 'action' => 'citiesJson']);
        $builder->post('/zlecenia/route-calc',          ['controller' => 'SpeedOrders', 'action' => 'routeCalcJson']);
        $builder->post('/zlecenia/ai-parse',            ['controller' => 'SpeedOrders', 'action' => 'aiParseOrderJson']);
        $builder->post('/zlecenia/conflict-check',      ['controller' => 'SpeedOrders', 'action' => 'conflictCheckJson']);
        $builder->get('/zlecenia/wolne-zasoby',         ['controller' => 'SpeedOrders', 'action' => 'freeResourcesJson']);
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
        $builder->get('/rozliczenia/ksef', ['controller' => 'Reconciliations', 'action' => 'indexKsef']);
        $builder->get('/rozliczenia/ksef/insights', ['controller' => 'Reconciliations', 'action' => 'insights']);
        $builder->get('/rozliczenia/ksef/kalendarz', ['controller' => 'Reconciliations', 'action' => 'calendar']);
        $builder->get('/rozliczenia/ksef/kalendarz/{yearMonth}', ['controller' => 'Reconciliations', 'action' => 'calendar'])
            ->setPass(['yearMonth'])
            ->setPatterns(['yearMonth' => '\d{4}-\d{2}']);
        $builder->get('/rozliczenia/ksef/insights/top-debtors', ['controller' => 'Reconciliations', 'action' => 'topDebtorsPage']);
        $builder->get('/rozliczenia/ksef/insights/contractor/{nip}', ['controller' => 'Reconciliations', 'action' => 'contractorProfile'])
            ->setPass(['nip']);

        // Kanban — pipeline rozliczeniowy
        $builder->get('/rozliczenia/kanban', ['controller' => 'Reconciliations', 'action' => 'kanban']);
        $builder->post('/rozliczenia/kanban/move/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanMove'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/note/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanNote'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/snooze/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanSnooze'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/dispute/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanDispute'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/assign/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanAssign'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/pin/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanPin'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/bulk-action', ['controller' => 'Reconciliations', 'action' => 'kanbanBulkAction']);
        $builder->post('/rozliczenia/kanban/ai-suggest/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanAiSuggest'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/kanban/notes/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanGetNotes'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/kanban/card-data/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanCardData'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/kanban/reminder-info/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanReminderInfo'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/kanban/send-reminder/{id}', ['controller' => 'Reconciliations', 'action' => 'kanbanSendReminder'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/speed', ['controller' => 'Reconciliations', 'action' => 'indexSpeed']);
        $builder->post('/rozliczenia/add-payment', ['controller' => 'Reconciliations', 'action' => 'addPayment']);
        $builder->post('/rozliczenia/delete-payment/{id}', ['controller' => 'Reconciliations', 'action' => 'deletePayment'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/bank-transactions/{id}', ['controller' => 'Reconciliations', 'action' => 'bankTransactions'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/contractor-info/{id}', ['controller' => 'Reconciliations', 'action' => 'contractorInfo'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/create-contractor/{id}', ['controller' => 'Reconciliations', 'action' => 'createContractorFromInvoice'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/sync-legacy', ['controller' => 'Reconciliations', 'action' => 'syncLegacy']);
        $builder->post('/rozliczenia/legacy-add-payment', ['controller' => 'Reconciliations', 'action' => 'addLegacyPayment']);
        $builder->post('/rozliczenia/legacy-delete-payment/{id}', ['controller' => 'Reconciliations', 'action' => 'deleteLegacyPayment'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/legacy-bank-transactions/{id}', ['controller' => 'Reconciliations', 'action' => 'legacyBankTransactions'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/alokacje/{id}', ['controller' => 'Reconciliations', 'action' => 'allocations'])
            ->setPass(['id']);
        $builder->post('/rozliczenia/add-allocation', ['controller' => 'Reconciliations', 'action' => 'addAllocation']);
        $builder->post('/rozliczenia/delete-allocation/{id}', ['controller' => 'Reconciliations', 'action' => 'deleteAllocation'])
            ->setPass(['id']);
        $builder->get('/rozliczenia/tx-allocated/{id}', ['controller' => 'Reconciliations', 'action' => 'transactionAllocatedSummary'])
            ->setPass(['id']);
        // Admin: sprawdzenie integralności bank_tx ↔ allocation ↔ invoice_payment
        $builder->get('/admin/rozliczenia/sprawdz-integralnosc', ['controller' => 'Reconciliations', 'action' => 'checkIntegrity']);
        $builder->post('/admin/rozliczenia/napraw-integralnosc', ['controller' => 'Reconciliations', 'action' => 'fixIntegrity']);
        $builder->post('/admin/rozliczenia/napraw-integralnosc/{type}/{id}', [
            'controller' => 'Reconciliations', 'action' => 'fixOneIntegrity',
        ])->setPass(['type', 'id']);
        $builder->post('/admin/rozliczenia/odepnij-kategorie/{category}', [
            'controller' => 'Reconciliations', 'action' => 'unlinkAllCategory',
        ])->setPass(['category']);
        $builder->post('/admin/rozliczenia/przelicz-wszystkie', [
            'controller' => 'Reconciliations', 'action' => 'refreshAllPaymentStates',
        ]);
        $builder->post('/admin/rozliczenia/backfill-iban-history', [
            'controller' => 'Reconciliations', 'action' => 'backfillIbanHistory',
        ]);

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
        $builder->get('/wyciagi/invoice-search', ['controller' => 'BankTransactions', 'action' => 'invoiceSearch']);
        $builder->get('/wyciagi/tx-allocations/{id}', ['controller' => 'BankTransactions', 'action' => 'txAllocations'])
            ->setPass(['id']);
        $builder->post('/wyciagi/ai-parse-title/{id}', ['controller' => 'BankTransactions', 'action' => 'aiParseTitle'])
            ->setPass(['id']);

        // Screen lock — odblokowanie po bezczynności + zarządzanie PIN-em + avatar
        $builder->post('/unlock',        ['controller' => 'Security', 'action' => 'unlock']);
        $builder->post('/set-pin',       ['controller' => 'Security', 'action' => 'setPin']);
        $builder->post('/delete-pin',    ['controller' => 'Security', 'action' => 'deletePin']);
        $builder->post('/upload-avatar', ['controller' => 'Security', 'action' => 'uploadAvatar']);
        $builder->post('/delete-avatar', ['controller' => 'Security', 'action' => 'deleteAvatar']);

        // Admin — zarządzanie użytkownikami (pracownicy + klienci, filtr po roli)
        $builder->get('/admin/uzytkownicy',                 ['controller' => 'AdminUsers', 'action' => 'index']);
        $builder->connect('/admin/uzytkownicy/dodaj',       ['controller' => 'AdminUsers', 'action' => 'add']);
        $builder->connect('/admin/uzytkownicy/edytuj/{id}', ['controller' => 'AdminUsers', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/admin/uzytkownicy/usun/{id}',      ['controller' => 'AdminUsers', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->post('/admin/uzytkownicy/powitanie/{id}', ['controller' => 'AdminUsers', 'action' => 'sendWelcome'])
            ->setPass(['id']);
        $builder->get('/admin/uzytkownicy/welcome-history/{id}', ['controller' => 'AdminUsers', 'action' => 'welcomeHistory'])
            ->setPass(['id']);
        $builder->post('/admin/uzytkownicy/upload-avatar/{id}', ['controller' => 'AdminUsers', 'action' => 'uploadAvatar'])
            ->setPass(['id']);

        // Admin — historia logowań
        $builder->get('/admin/logowania', ['controller' => 'AdminLoginLogs', 'action' => 'index']);

        // Admin — wydarzenia bezpieczeństwa
        $builder->get('/admin/bezpieczenstwo', ['controller' => 'AdminSecurityEvents', 'action' => 'index']);

        // Admin — audyt akcji CRUD
        $builder->get('/admin/akcje', ['controller' => 'AdminActionLogs', 'action' => 'index']);

        // Admin — wydajność pracowników
        $builder->get('/admin/wydajnosc', ['controller' => 'AdminPerformance', 'action' => 'index']);

        // Globalne wyszukiwanie (AJAX z headera)
        $builder->get('/szukaj', ['controller' => 'Search', 'action' => 'query']);

        // Planer tras (HERE Routing v8)
        $builder->get('/trasy',                      ['controller' => 'RoutePlanner', 'action' => 'index']);
        $builder->connect('/trasy/calculate',        ['controller' => 'RoutePlanner', 'action' => 'calculate']);
        $builder->get('/trasy/autosuggest',          ['controller' => 'RoutePlanner', 'action' => 'autosuggest']);
        $builder->get('/trasy/revgeocode',           ['controller' => 'RoutePlanner', 'action' => 'revgeocode']);
        $builder->post('/trasy/ai/parse-address',    ['controller' => 'RoutePlanner', 'action' => 'aiParseAddress']);
        $builder->post('/trasy/ai/cargo-wizard',     ['controller' => 'RoutePlanner', 'action' => 'aiCargoWizard']);
        $builder->post('/trasy/ai/pricing',          ['controller' => 'RoutePlanner', 'action' => 'aiPricing']);
        $builder->post('/trasy/ai/driver-brief',     ['controller' => 'RoutePlanner', 'action' => 'aiDriverBrief']);
        $builder->post('/trasy/ai/route-optimizer',  ['controller' => 'RoutePlanner', 'action' => 'aiRouteOptimizer']);
        $builder->post('/trasy/ai/email-reply',      ['controller' => 'RoutePlanner', 'action' => 'aiEmailReply']);
        $builder->post('/trasy/ai/delay-prediction', ['controller' => 'RoutePlanner', 'action' => 'aiDelayPrediction']);
        $builder->post('/trasy/optimize-multileg',   ['controller' => 'RoutePlanner', 'action' => 'optimizeMultileg']);
        $builder->post('/trasy/weather',             ['controller' => 'RoutePlanner', 'action' => 'weather']);
        $builder->post('/trasy/truck-pois',           ['controller' => 'RoutePlanner', 'action' => 'truckPois']);
        $builder->post('/trasy/toll-booths',           ['controller' => 'RoutePlanner', 'action' => 'tollBooths']);
        // Toll fee overrides — learning loop
        $builder->get('/trasy/toll-overrides',           ['controller' => 'RoutePlanner', 'action' => 'tollOverrideList']);
        $builder->post('/trasy/toll-overrides/save',     ['controller' => 'RoutePlanner', 'action' => 'tollOverrideSave']);
        $builder->post('/trasy/toll-overrides/{id}/delete', ['controller' => 'RoutePlanner', 'action' => 'tollOverrideDelete'])
            ->setPatterns(['id' => '[0-9a-f-]{36}']);

        // #7 Cabotage tracker
        $builder->get('/trasy/cabotage-status',        ['controller' => 'RoutePlanner', 'action' => 'cabotageStatus']);
        $builder->post('/trasy/cabotage-save',         ['controller' => 'RoutePlanner', 'action' => 'cabotageSave']);
        $builder->post('/trasy/cabotage/{id}/delete',  ['controller' => 'RoutePlanner', 'action' => 'cabotageDelete'])
            ->setPatterns(['id' => '[0-9a-f-]{36}']);
        // #14 Live tracking — publiczne, bez auth
        $builder->get('/trasy/track/{id}',             ['controller' => 'RoutePlanner', 'action' => 'trackView'])->setPatterns(['id' => '[0-9a-f-]{36}']);
        $builder->get('/trasy/track-api/{id}',         ['controller' => 'RoutePlanner', 'action' => 'track'])->setPatterns(['id' => '[0-9a-f-]{36}']);
        $builder->post('/trasy/historia/usun/{id}',  ['controller' => 'RoutePlanner', 'action' => 'deleteRecent'])
            ->setPass(['id']);
        $builder->post('/trasy/szablon/zapisz',       ['controller' => 'RoutePlanner', 'action' => 'saveTemplate']);
        $builder->get('/trasy/zlecenie/{orderId}',   ['controller' => 'RoutePlanner', 'action' => 'forOrder'])
            ->setPass(['orderId']);

        // Fala 2A: historia stawek klienta (cascade query po speed_orders + invoices)
        $builder->connect('/planer-tras/historia-stawek', ['controller' => 'RoutePlanner', 'action' => 'pricingHistory']);

        // Fala 2B: oferty cenowe wysylane z planera tras
        $builder->get('/oferty',                    ['controller' => 'RouteOffers', 'action' => 'index']);
        $builder->post('/oferty/utworz',            ['controller' => 'RouteOffers', 'action' => 'create']);
        $builder->get('/oferty/{id}',               ['controller' => 'RouteOffers', 'action' => 'view'])
            ->setPatterns(['id' => '[a-f0-9\-]{36}'])
            ->setPass(['id']);
        $builder->post('/oferty/wyslij/{id}',       ['controller' => 'RouteOffers', 'action' => 'send'])
            ->setPatterns(['id' => '[a-f0-9\-]{36}'])
            ->setPass(['id']);
        $builder->post('/oferty/usun/{id}',         ['controller' => 'RouteOffers', 'action' => 'delete'])
            ->setPatterns(['id' => '[a-f0-9\-]{36}'])
            ->setPass(['id']);
        // Publiczne — dostep klienta po tokenie (bez logowania, kontrola w kontrolerze)
        $builder->get('/oferty/wglad/{token}',            ['controller' => 'RouteOffers', 'action' => 'accessByToken'])
            ->setPatterns(['token' => '[a-f0-9]{48}'])
            ->setPass(['token']);
        $builder->post('/oferty/wglad/{token}/akceptuj',  ['controller' => 'RouteOffers', 'action' => 'accept'])
            ->setPatterns(['token' => '[a-f0-9]{48}'])
            ->setPass(['token']);
        $builder->post('/oferty/wglad/{token}/odrzuc',    ['controller' => 'RouteOffers', 'action' => 'reject'])
            ->setPatterns(['token' => '[a-f0-9]{48}'])
            ->setPass(['token']);

        // Fala 3: Serwisy pojazdow i naczep (badania, ADR, ubezpieczenia)
        $builder->get('/serwisy',                       ['controller' => 'VehicleMaintenance', 'action' => 'index']);
        $builder->connect('/serwisy/dodaj',             ['controller' => 'VehicleMaintenance', 'action' => 'add']);
        $builder->connect('/serwisy/edytuj/{id}',       ['controller' => 'VehicleMaintenance', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/serwisy/usun/{id}',            ['controller' => 'VehicleMaintenance', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/serwisy/wygasajace.json',       ['controller' => 'VehicleMaintenance', 'action' => 'expiringJson']);

        // Fala 3: Czas pracy kierowcow (tachograf/manual)
        $builder->get('/czas-pracy',                    ['controller' => 'DriverTimeLogs', 'action' => 'index']);
        $builder->connect('/czas-pracy/dodaj',          ['controller' => 'DriverTimeLogs', 'action' => 'add']);
        $builder->connect('/czas-pracy/edytuj/{id}',    ['controller' => 'DriverTimeLogs', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/czas-pracy/usun/{id}',         ['controller' => 'DriverTimeLogs', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/czas-pracy/status/{driverId}.json', ['controller' => 'DriverTimeLogs', 'action' => 'weeklyStatusJson'])
            ->setPass(['driverId']);

        // Fala 3: Wzorce dostepnosci kierowcow
        $builder->get('/dostepnosc-kierowcow',                  ['controller' => 'DriverAvailability', 'action' => 'index']);
        $builder->connect('/dostepnosc-kierowcow/{driverId}',   ['controller' => 'DriverAvailability', 'action' => 'edit'])
            ->setPass(['driverId']);

        // Fala 3: Dashboard ryzyk compliance
        $builder->get('/ryzyko',                        ['controller' => 'ComplianceEvents', 'action' => 'index']);
        $builder->post('/ryzyko/akceptuj/{id}',         ['controller' => 'ComplianceEvents', 'action' => 'dismiss'])
            ->setPass(['id']);

        // Fala 4A: Trip events (timeline zlecen)
        $builder->get('/trip-events/zlecenie/{orderId}',  ['controller' => 'TripEvents', 'action' => 'forOrder'])
            ->setPatterns(['orderId' => '\d+'])
            ->setPass(['orderId']);
        $builder->post('/trip-events/dodaj',              ['controller' => 'TripEvents', 'action' => 'addEvent']);
        $builder->post('/trip-events/usun/{id}',          ['controller' => 'TripEvents', 'action' => 'delete'])
            ->setPass(['id']);
        // Publiczne (bez auth) — mobile view kierowcy
        $builder->get('/kierowca/{token}',                ['controller' => 'TripEvents', 'action' => 'driverView'])
            ->setPatterns(['token' => '[a-f0-9]{48}'])
            ->setPass(['token']);
        $builder->post('/kierowca/{token}/event',         ['controller' => 'TripEvents', 'action' => 'driverPost'])
            ->setPatterns(['token' => '[a-f0-9]{48}'])
            ->setPass(['token']);

        // Fala 4B: Return loads (ladunki powrotne)
        $builder->get('/powroty/{planId}',                ['controller' => 'ReturnLoads', 'action' => 'forPlan'])
            ->setPatterns(['planId' => '[a-f0-9\-]{36}'])
            ->setPass(['planId']);
        $builder->post('/powroty/{planId}/szukaj',        ['controller' => 'ReturnLoads', 'action' => 'suggest'])
            ->setPatterns(['planId' => '[a-f0-9\-]{36}'])
            ->setPass(['planId']);
        $builder->post('/powroty/odrzuc/{id}',            ['controller' => 'ReturnLoads', 'action' => 'dismiss'])
            ->setPass(['id']);

        // Fala 4C: Analytics dashboard
        $builder->get('/analytics',                       ['controller' => 'Analytics', 'action' => 'index']);

        // Pojazdy floty
        $builder->get('/pojazdy',                ['controller' => 'Vehicles', 'action' => 'index']);
        $builder->connect('/pojazdy/dodaj',      ['controller' => 'Vehicles', 'action' => 'add']);
        $builder->connect('/pojazdy/edytuj/{id}', ['controller' => 'Vehicles', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/pojazdy/usun/{id}',     ['controller' => 'Vehicles', 'action' => 'delete'])
            ->setPass(['id']);

        // Naczepy / przyczepy
        $builder->get('/naczepy',                ['controller' => 'Trailers', 'action' => 'index']);
        $builder->connect('/naczepy/dodaj',      ['controller' => 'Trailers', 'action' => 'add']);
        $builder->connect('/naczepy/edytuj/{id}', ['controller' => 'Trailers', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/naczepy/usun/{id}',     ['controller' => 'Trailers', 'action' => 'delete'])
            ->setPass(['id']);

        // Kierowcy
        $builder->get('/kierowcy',                ['controller' => 'Drivers', 'action' => 'index']);
        $builder->connect('/kierowcy/dodaj',      ['controller' => 'Drivers', 'action' => 'add']);
        $builder->connect('/kierowcy/edytuj/{id}', ['controller' => 'Drivers', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/kierowcy/usun/{id}',     ['controller' => 'Drivers', 'action' => 'delete'])
            ->setPass(['id']);

        // Grafik kierowcow (Fala 1 planera operacyjnego)
        $builder->get('/grafik-kierowcow',                    ['controller' => 'DriverSchedules', 'action' => 'index']);
        $builder->connect('/grafik-kierowcow/dodaj',          ['controller' => 'DriverSchedules', 'action' => 'add']);
        $builder->connect('/grafik-kierowcow/edytuj/{id}',    ['controller' => 'DriverSchedules', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/grafik-kierowcow/usun/{id}',         ['controller' => 'DriverSchedules', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/grafik-kierowcow/wolni.json',         ['controller' => 'DriverSchedules', 'action' => 'availableJson']);
        $builder->get('/grafik-kierowcow/dla-kierowcy/{driverId}.json', ['controller' => 'DriverSchedules', 'action' => 'forDriverJson'])
            ->setPass(['driverId']);

        // Grafik pojazdow i naczep
        $builder->get('/grafik-pojazdow',                     ['controller' => 'VehicleSchedules', 'action' => 'index']);
        $builder->connect('/grafik-pojazdow/dodaj',           ['controller' => 'VehicleSchedules', 'action' => 'add']);
        $builder->connect('/grafik-pojazdow/edytuj/{id}',     ['controller' => 'VehicleSchedules', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/grafik-pojazdow/usun/{id}',          ['controller' => 'VehicleSchedules', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/grafik-pojazdow/wolne.json',          ['controller' => 'VehicleSchedules', 'action' => 'availableVehiclesJson']);
        $builder->get('/grafik-naczep/wolne.json',            ['controller' => 'VehicleSchedules', 'action' => 'availableTrailersJson']);

        // Zestawy pojazd+naczepa+kierowca (klikalne w planerze)
        $builder->get('/zestawy',                 ['controller' => 'VehicleCombinations', 'action' => 'index']);
        $builder->connect('/zestawy/dodaj',       ['controller' => 'VehicleCombinations', 'action' => 'add']);
        $builder->connect('/zestawy/edytuj/{id}', ['controller' => 'VehicleCombinations', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/zestawy/usun/{id}',      ['controller' => 'VehicleCombinations', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/zestawy/lista.json',      ['controller' => 'VehicleCombinations', 'action' => 'listJson']);

        // Kategorie typów pojazdu (mapowanie typ zestawu → kategoria tolls)
        $builder->get('/admin/vehicle-type-categories',            ['controller' => 'VehicleTypeCategories', 'action' => 'index']);
        $builder->connect('/admin/vehicle-type-categories/add',    ['controller' => 'VehicleTypeCategories', 'action' => 'add']);
        $builder->connect('/admin/vehicle-type-categories/edit/{id}', ['controller' => 'VehicleTypeCategories', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/admin/vehicle-type-categories/delete/{id}', ['controller' => 'VehicleTypeCategories', 'action' => 'delete'])
            ->setPass(['id']);
        $builder->get('/admin/vehicle-type-categories/for-type/{type}', ['controller' => 'VehicleTypeCategories', 'action' => 'forType'])
            ->setPass(['type']);

        // Powiadomienia (per user)
        $builder->get('/powiadomienia',                 ['controller' => 'Notifications', 'action' => 'index']);
        $builder->get('/powiadomienia/recent',          ['controller' => 'Notifications', 'action' => 'recent']);
        $builder->get('/powiadomienia/count',           ['controller' => 'Notifications', 'action' => 'count']);
        $builder->post('/powiadomienia/oznacz/{id}',    ['controller' => 'Notifications', 'action' => 'markRead'])
            ->setPass(['id']);
        $builder->post('/powiadomienia/oznacz-wszystkie', ['controller' => 'Notifications', 'action' => 'markAllRead']);
        $builder->post('/powiadomienia/usun/{id}',      ['controller' => 'Notifications', 'action' => 'delete'])
            ->setPass(['id']);

        // Admin — wcielanie się w użytkownika (impersonation)
        $builder->post('/admin/impersonate/start/{userId}', ['controller' => 'AdminImpersonate', 'action' => 'start'])
            ->setPass(['userId']);
        $builder->post('/admin/impersonate/stop',           ['controller' => 'AdminImpersonate', 'action' => 'stop']);
        $builder->get('/admin/impersonate/search',          ['controller' => 'AdminImpersonate', 'action' => 'search']);
        $builder->post('/admin/uzytkownicy/delete-avatar/{id}', ['controller' => 'AdminUsers', 'action' => 'deleteAvatar'])
            ->setPass(['id']);

        // Admin — role i uprawnienia
        $builder->get('/admin/role',                  ['controller' => 'Roles', 'action' => 'index']);
        $builder->connect('/admin/role/dodaj',        ['controller' => 'Roles', 'action' => 'add']);
        $builder->connect('/admin/role/edytuj/{id}',  ['controller' => 'Roles', 'action' => 'edit'])
            ->setPatterns(['id' => '\d+'])->setPass(['id']);
        $builder->post('/admin/role/usun/{id}',       ['controller' => 'Roles', 'action' => 'delete'])
            ->setPatterns(['id' => '\d+'])->setPass(['id']);

        // Wsteczna kompatybilność: stare URL /admin/klienci → nowe /admin/uzytkownicy?role=client
        $builder->get('/admin/klienci',                 ['controller' => 'AdminClients', 'action' => 'index']);
        $builder->connect('/admin/klienci/dodaj',       ['controller' => 'AdminClients', 'action' => 'add']);
        $builder->connect('/admin/klienci/edytuj/{id}', ['controller' => 'AdminClients', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/admin/klienci/usun/{id}',      ['controller' => 'AdminClients', 'action' => 'delete'])
            ->setPass(['id']);

        // Przełączanie języka UI — dostępne dla każdego (także przed logowaniem)
        $builder->get('/lang/{lang}', ['controller' => 'Locale', 'action' => 'change'])
            ->setPatterns(['lang' => 'pl|en'])
            ->setPass(['lang']);

        // Słownik adresów transportowych
        $builder->get('/slownik-adresow',                ['controller' => 'TransportAddresses', 'action' => 'index']);
        $builder->get('/slownik-adresow/search',         ['controller' => 'TransportAddresses', 'action' => 'search']);
        $builder->connect('/slownik-adresow/dodaj',      ['controller' => 'TransportAddresses', 'action' => 'add']);
        $builder->connect('/slownik-adresow/edytuj/{id}', ['controller' => 'TransportAddresses', 'action' => 'edit'])
            ->setPass(['id']);
        $builder->post('/slownik-adresow/usun/{id}',     ['controller' => 'TransportAddresses', 'action' => 'delete'])
            ->setPass(['id']);

        // Portal klienta (rola `client`) — zlecenia transportowe powiązane przez NIP
        $builder->get('/portal',                       ['controller' => 'ClientPortal', 'action' => 'index']);
        $builder->get('/portal/zlecenie/{id}',         ['controller' => 'ClientPortal', 'action' => 'view'])
            ->setPass(['id']);
        $builder->get('/portal/cmr/{attId}',           ['controller' => 'ClientPortal', 'action' => 'downloadAttachment'])
            ->setPass(['attId']);
        $builder->get('/portal/faktura/{invoiceId}',   ['controller' => 'ClientPortal', 'action' => 'downloadInvoice'])
            ->setPass(['invoiceId']);
        $builder->get('/portal/lang/{lang}',           ['controller' => 'ClientPortal', 'action' => 'setLocale'])
            ->setPass(['lang']);
        $builder->get('/portal/export.csv',            ['controller' => 'ClientPortal', 'action' => 'exportCsv']);

        // Kredyt kupiecki (Allianz Trade / Syntesys)
        $builder->get('/kredyt-kupiecki', ['controller' => 'CreditChecks', 'action' => 'index']);
        $builder->post('/kredyt-kupiecki/sync', ['controller' => 'CreditChecks', 'action' => 'sync']);
        $builder->post('/kredyt-kupiecki/sprawdz-opinie', ['controller' => 'CreditChecks', 'action' => 'checkOpinion']);
        $builder->post('/kredyt-kupiecki/szukaj-firme', ['controller' => 'CreditChecks', 'action' => 'foreignSearch']);
        $builder->post('/kredyt-kupiecki/usun/{id}', ['controller' => 'CreditChecks', 'action' => 'delete'])
            ->setPass(['id']);

        // Karty paliwowe E100
        $builder->get('/karty-paliwowe', ['controller' => 'FuelCards', 'action' => 'index']);
        $builder->get('/karty-paliwowe/export-csv', ['controller' => 'FuelCards', 'action' => 'exportCsv']);
        $builder->post('/karty-paliwowe/sync', ['controller' => 'FuelCards', 'action' => 'sync']);
        $builder->get('/karty-paliwowe/konta', ['controller' => 'FuelCards', 'action' => 'accounts']);
        $builder->connect('/karty-paliwowe/konta/dodaj', ['controller' => 'FuelCards', 'action' => 'addAccount']);
        $builder->connect('/karty-paliwowe/konta/edytuj/{id}', ['controller' => 'FuelCards', 'action' => 'editAccount'])
            ->setPass(['id']);
        $builder->post('/karty-paliwowe/konta/usun/{id}', ['controller' => 'FuelCards', 'action' => 'deleteAccount'])
            ->setPass(['id']);
        $builder->get('/karty-paliwowe/karty', ['controller' => 'FuelCards', 'action' => 'cards']);
        $builder->get('/karty-paliwowe/karty/info', ['controller' => 'FuelCards', 'action' => 'cardInfo']);
        $builder->post('/karty-paliwowe/karty/blokuj', ['controller' => 'FuelCards', 'action' => 'blockCard']);
        $builder->get('/karty-paliwowe/saldo', ['controller' => 'FuelCards', 'action' => 'balance']);
        $builder->get('/karty-paliwowe/limity', ['controller' => 'FuelCards', 'action' => 'limits']);
        $builder->get('/karty-paliwowe/limity/pobierz', ['controller' => 'FuelCards', 'action' => 'getLimit']);
        $builder->post('/karty-paliwowe/limity/ustaw', ['controller' => 'FuelCards', 'action' => 'setLimit']);
        $builder->connect('/karty-paliwowe/stacje', ['controller' => 'FuelCards', 'action' => 'stations']);

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

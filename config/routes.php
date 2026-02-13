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
        $builder->post('/invoice-series/search', 'InvoiceSeries::search');

        // config/routes.php
        $builder->connect('/firma/edycja', ['controller' => 'Companies', 'action' => 'edit']);
        $builder->connect('/uzytkownik/profil', ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']);
        $builder->get('/konto/2fa', ['controller' => 'TwoFactor', 'action' => 'index']);
        $builder->post('/konto/2fa/wlacz', ['controller' => 'TwoFactor', 'action' => 'enable']);
        $builder->post('/konto/2fa/weryfikuj', ['controller' => 'TwoFactor', 'action' => 'verify']);
        $builder->post('/konto/2fa/wylacz', ['controller' => 'TwoFactor', 'action' => 'disable']);
        $builder->connect('/invoices/ksef-auth/active', ['controller' => 'Invoices', 'action' => 'ksefAuthActive']);
        $builder->connect('/invoices/ksef-auth/login',  ['controller' => 'Invoices', 'action' => 'ksefAuthLogin']);
        $builder->connect('/invoices/ksef-smoke',       ['controller' => 'Invoices', 'action' => 'ksefSmoke']);
// config/routes.php
$builder->connect('/invoices/ksef/download', ['controller' => 'Invoices', 'action' => 'downloadKsef']);
$builder->connect('/invoices/ksef/metadata', ['controller' => 'Invoices', 'action' => 'metadataKsef']);

        // (opcjonalnie) wyszukiwarka kontrahentów i produktów pod Select2:
        // $builder->get('/contractors/search', 'Contractors::search');
        // $builder->get('/products/search', 'Products::search');

        // Fallbacks (na końcu)
        $builder->fallbacks();
    });

    // (opcjonalnie) Możesz dorobić osobny scope dla API:
    $routes->scope('/api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        // KSeF: otrzymane – JSON API
        $builder->get('/ksef/received', ['controller' => 'KsefAuthorizations', 'action' => 'receivedApi']);
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

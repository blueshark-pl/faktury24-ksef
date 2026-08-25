<?php

use function Cake\Core\env;

/*
 * Local configuration file to provide any overrides to your app.php configuration.
 * Copy and save this file as app_local.php and make changes as required.
 * Note: It is not recommended to commit files with credentials such as app_local.php
 * into source code version control.
 */
return [
    'App' => [
        // Branding for HTML system emails (optional)
        // Prefer URL/CID for best client compatibility; data URIs may be blocked by some mail clients.
        'emailLogoUrl' => env('APP_EMAIL_LOGO_URL', ''),
        'emailLogoDataUri' => env('APP_EMAIL_LOGO_DATA_URI', ''),
    ],
    /*
     * Debug Level:
     *
     * Production Mode:
     * false: No error messages, errors, or warnings shown.
     *
     * Development Mode:
     * true: Errors and warnings shown.
     */
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Security and encryption configuration
     *
     * - salt - A random string used in security hashing methods.
     *   The salt value is also used as the encryption key.
     *   You should treat it as extremely sensitive data.
     */
    'Security' => [
        'salt' => env('SECURITY_SALT', '__SALT__'),
    ],

    /*
     * Connection information used by the ORM to connect
     * to your application's datastores.
     *
     * See app.php for more configuration options.
     */
    'Datasources' => [
        'default' => [
            'host' => 'localhost',
            /*
             * CakePHP will use the default DB port based on the driver selected
             * MySQL on MAMP uses port 8889, MAMP users will want to uncomment
             * the following line and set the port accordingly
             */
            //'port' => 'non_standard_port_number',

            'username' => 'my_app',
            'password' => 'secret',

            'database' => 'my_app',
            /*
             * If not using the default 'public' schema with the PostgreSQL driver
             * set it here.
             */
            //'schema' => 'myapp',

            /*
             * You can use a DSN string to set the entire configuration
             */
            'url' => env('DATABASE_URL', null),
        ],

        /*
         * The test connection is used during the test suite.
         */
        'test' => [
            'host' => 'localhost',
            //'port' => 'non_standard_port_number',
            'username' => 'my_app',
            'password' => 'secret',
            'database' => 'test_myapp',
            //'schema' => 'myapp',
            'url' => env('DATABASE_TEST_URL', 'sqlite://127.0.0.1/tmp/tests.sqlite'),
        ],
    ],

    /*
     * Email configuration.
     *
     * Host and credential configuration in case you are using SmtpTransport
     *
     * See app.php for more configuration options.
     */
    'EmailTransport' => [
        'default' => [
            'className' => 'Smtp',
            'host' => env('SMTP_HOST', 'partnersc.home.pl'),
            'port' => (int)env('SMTP_PORT', 587),
            'username' => env('SMTP_USERNAME', 'no-reply@faktury24.com'),
            'password' => env('SMTP_PASSWORD', 'przykladowe haslo'),
            'client' => null,
            'timeout' => 30,
            'tls' => filter_var(env('SMTP_TLS', false), FILTER_VALIDATE_BOOLEAN),
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],

    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => ['no-reply@faktury24.com' => 'Faktury24'],
        ],
    ],

    // CRM Search API dla LinkedIn URL lookup (FALA 10).
    // Provider: 'serper' | 'brave' | 'google_cse' - wybierz jeden.
    // Serper.dev:  2500 free na start, potem $50/2500 - https://serper.dev
    // Brave:       2000/mies free na karte, potem $3/1000 - https://api.search.brave.com
    // Google CSE:  100/dzien free - https://developers.google.com/custom-search
    'Search' => [
        'provider' => env('SEARCH_PROVIDER', 'serper'),
        'serperApiKey'    => env('SERPER_API_KEY', ''),
        'braveApiKey'     => env('BRAVE_API_KEY', ''),
        'google_cseApiKey' => env('GOOGLE_CSE_API_KEY', ''),
        'googleCseCx'     => env('GOOGLE_CSE_CX', ''),
    ],

    // CRM (FALA 14) - encryption key + auto-lead z email
    'Crm' => [
        // AES-256 key do szyfrowania OAuth tokens + IMAP passwords w crm_email_accounts.
        // Musi byc 64 hex chars, wygeneruj przez: php -r "echo bin2hex(random_bytes(32));"
        // NIGDY nie zmieniaj po dodaniu pierwszego konta email!
        'encryptionKey' => env('CRM_ENCRYPTION_KEY', ''),
        // Auto-tworzenie leada z nowego emaila (nie od istniejacego kontaktu).
        // GPT-4o analizuje tresc + podpis, wyciaga dane firmy, tworzy lead source=email_inbound.
        // Wymaga OpenAI API key (Openai.apiKey).
        'autoCreateLeadsFromEmail' => env('CRM_AUTO_CREATE_LEADS', true),
        // Token do webhooka cronu (bez auth). Endpoint (standalone script):
        //   GET /cron.php?cmd={command}&token=X
        //   Commands: crm_email_poll, crm_workflow_run, crm_tasks_digest, alerts
        // Wygeneruj: php -r "echo bin2hex(random_bytes(24));"
        // Cyberfolks Cron Jobs (webroot/cron.php bypassuje middleware):
        //   */5 * * * *  curl -s "https://booklio.pl/cron.php?cmd=crm_email_poll&token=XXX"
        'cronToken' => env('CRM_CRON_TOKEN', ''),
        // TEST MODE: przekieruj WSZYSTKIE wychodzace maile CRM (auto-thanks, reply Gmail,
        // wysylka wyceny PDF) na TEN adres zamiast do klienta. Subject dostanie prefix
        // [TEST → oryginalny_odbiorca], body dostanie banner na gorze.
        // Zostaw pusty ('') dla produkcji - maile ida do klientow normalnie.
        // Przyklad: 'krzysztof@3ck.pl' - wszystko trafia do mnie zamiast do klientow
        'testEmailOverride' => env('CRM_TEST_EMAIL_OVERRIDE', ''),
        // Sidebar: role z ograniczonym menu (widza tylko Zlecenia + CRM Leady).
        // Domyslnie puste - dla backward compat starych klientow booklio.
        // Przyklad dla NordLogis: ['user', 'sales_manager']
        //   - user             = 'Pracownik (spedytor)'
        //   - sales_manager    = 'Kierownik Działu Handlowego'
        // (asystent_spedytora nadal ma osobny wlasny minimal menu z Kontrahentami)
        'restrictedMenuRoles' => [],
    ],

    // Gmail OAuth 2.0 (FALA 13) - alternatywa dla IMAP dla skrzynek Gmail/Workspace.
    // Setup: https://console.cloud.google.com/ -> nowy projekt -> Gmail API enable
    // -> OAuth consent screen -> Credentials -> OAuth 2.0 Client ID (Web application)
    // Authorized redirect URIs musi zawierac: <APP_URL>/crm/email-accounts/google-callback
    'Google' => [
        'clientId'     => env('GOOGLE_CLIENT_ID', ''),
        'clientSecret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirectUri'  => env('GOOGLE_REDIRECT_URI', 'https://booklio.pl/crm/email-accounts/google-callback'),
    ],
];

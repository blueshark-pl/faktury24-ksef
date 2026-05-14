<?php
/**
 * Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2018, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/*
 * IMPORTANT:
 * This is an example configuration file. Copy this file into your config directory and edit to
 * set up your app permissions.
 *
 * This is a quick roles-permissions implementation
 * Rules are evaluated top-down, first matching rule will apply
 * Each line define
 *      [
 *          'role' => 'role' | ['roles'] | '*'
 *          'prefix' => 'Prefix' | , (default = null)
 *          'plugin' => 'Plugin' | , (default = null)
 *          'controller' => 'Controller' | ['Controllers'] | '*',
 *          'action' => 'action' | ['actions'] | '*',
 *          'allowed' => true | false | callback (default = true)
 *      ]
 * You could use '*' to match anything
 * 'allowed' will be considered true if not defined. It allows a callable to manage complex
 * permissions, like this
 * 'allowed' => function (array $user, $role, Request $request) {}
 *
 * Example, using allowed callable to define permissions only for the owner of the Posts to edit/delete
 *
 * (remember to add the 'uses' at the top of the permissions.php file for Hash, TableRegistry and Request
   [
        'role' => ['user'],
        'controller' => ['Posts'],
        'action' => ['edit', 'delete'],
        'allowed' => function(array $user, $role, Request $request) {
            $postId = Hash::get($request->params, 'pass.0');
            $post = TableRegistry::getTableLocator()->get('Posts')->get($postId);
            $userId = Hash::get($user, 'id');
            if (!empty($post->user_id) && !empty($userId)) {
                return $post->user_id === $userId;
            }
            return false;
        }
    ],
 */

return [
    'CakeDC/Auth.permissions' => [
        //all bypass
        [
            'prefix' => false,
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => [
                // LoginTrait
                'socialLogin',
                'login',
                'logout',
                'socialEmail',
                'verify',
                // RegisterTrait
                'register',
                'validateEmail',
                // PasswordManagementTrait used in RegisterTrait
                'changePassword',
                'resetPassword',
                'requestResetPassword',
                // UserValidationTrait used in PasswordManagementTrait
                'resendTokenValidation',
                'linkSocial',
                //Webauthn2fa actions
                'webauthn2fa',
                'webauthn2faRegister',
                'webauthn2faRegisterOptions',
                'webauthn2faAuthenticate',
                'webauthn2faAuthenticateOptions',
                'requestLoginLink',
                'sendLoginLink',
                'singleTokenLogin',
            ],
            'bypassAuth' => true,
        ],
        [
            'prefix' => false,
            'plugin' => 'CakeDC/Users',
            'controller' => 'SocialAccounts',
            'action' => [
                'validateAccount',
                'resendValidation',
            ],
            'bypassAuth' => true,
        ],
        //admin role allowed to all the things
        [
            'role' => \CakeDC\Users\Model\Table\UsersTable::ROLE_ADMIN,
            'prefix' => '*',
            'extension' => '*',
            'plugin' => '*',
            'controller' => '*',
            'action' => '*',
        ],
        // Impersonation 'stop' — DOSTĘPNE dla każdej roli żeby admin
        // wcielony w klienta/pracownika mógł wrócić do siebie. Walidacja
        // wewnątrz akcji sprawdza session.Impersonation.original_user_id.
        [
            'role' => '*',
            'controller' => 'AdminImpersonate',
            'action' => 'stop',
        ],
        //specific actions allowed for the all roles in Users plugin
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => ['profile', 'logout', 'linkSocial', 'callbackLinkSocial'],
        ],
        // Screen lock + PIN + avatar — dla zalogowanych każdej roli
        [
            'role'       => '*',
            'plugin'     => false,
            'controller' => 'Security',
            'action'     => ['unlock', 'setPin', 'deletePin', 'uploadAvatar', 'deleteAvatar'],
        ],
        // 2FA — ustawienia tokenu uwierzytelniającego (dla każdej roli)
        [
            'role'       => '*',
            'plugin'     => false,
            'controller' => 'TwoFactor',
            'action'     => ['index', 'enable', 'verify', 'disable'],
        ],
        // Tokeny API — generowanie i unieważnianie własnych tokenów (dla każdej roli)
        [
            'role'       => '*',
            'plugin'     => false,
            'controller' => 'ApiTokens',
            'action'     => ['index', 'generate', 'revoke'],
        ],
        // Powiadomienia in-app — własne notyfikacje per user (dla każdej roli)
        [
            'role'       => '*',
            'plugin'     => false,
            'controller' => 'Notifications',
            'action'     => ['index', 'recent', 'count', 'markRead', 'markAllRead', 'delete'],
        ],
        // Globalne wyszukiwanie (faktury + kontrahenci + zlecenia) — bez klientów
        [
            'role'       => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'asystent_spedytora'],
            'plugin'     => false,
            'controller' => 'Search',
            'action'     => ['query'],
        ],
        // Pojazdy floty — zarządzanie przez spedycję i sales
        [
            'role'       => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'asystent_spedytora'],
            'plugin'     => false,
            'controller' => 'Vehicles',
            'action'     => ['index', 'add', 'edit', 'delete'],
        ],
        // Naczepy
        [
            'role'       => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'asystent_spedytora'],
            'plugin'     => false,
            'controller' => 'Trailers',
            'action'     => ['index', 'add', 'edit', 'delete'],
        ],
        // Kierowcy
        [
            'role'       => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'asystent_spedytora'],
            'plugin'     => false,
            'controller' => 'Drivers',
            'action'     => ['index', 'add', 'edit', 'delete'],
        ],
        // Planer tras (HERE Routing v8)
        [
            'role'       => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'asystent_spedytora'],
            'plugin'     => false,
            'controller' => 'RoutePlanner',
            'action'     => ['index', 'calculate', 'forOrder', 'autosuggest', 'revgeocode', 'deleteRecent', 'saveTemplate', 'aiParseAddress', 'aiCargoWizard', 'aiPricing', 'aiDriverBrief', 'aiRouteOptimizer', 'aiEmailReply', 'weather', 'truckPois', 'tollBooths', 'track', 'trackView', 'cabotageStatus', 'cabotageSave', 'cabotageDelete', 'aiDelayPrediction', 'optimizeMultileg', 'tollOverrideSave', 'tollOverrideDelete', 'tollOverrideList'],
        ],
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => 'resetOneTimePasswordAuthenticator',
            'allowed' => function ($user, $role, \Cake\Http\ServerRequest $request) {
                $userId = \Cake\Utility\Hash::get($request->getAttribute('params'), 'pass.0');
                $currentId = is_array($user)
                    ? ($user['id'] ?? null)
                    : ($user?->id ?? null);
                if (!empty($userId) && !empty($currentId)) {
                    return $userId === $currentId;
                }

                return false;
            }
        ],
        //all roles allowed to Pages/display
        [
            'role' => '*',
            'controller' => 'Pages',
            'action' => 'display',
        ],
        // rejestracja: pobranie danych firmy po NIP z GUS (bez zalogowania)
        [
            'role' => '*',
            'controller' => 'Contractors',
            'action' => ['gusLookup'],
            'bypassAuth' => true,
        ],
        // Przełączanie języka UI — dostępne także bez logowania (ekran loginu)
        [
            'role' => '*',
            'controller' => 'Locale',
            'action' => 'change',
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'controller' => 'Companies',
            'action' => ['nipExists'],
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'controller' => 'Invoices',
            'action' => ['runPlannedDrafts'],
        ],
        // jednorazowy import użytkowników ze starego systemu (chroniony kluczem w query)
        [
            'role' => '*',
            'controller' => 'Import',
            'action' => ['importLegacyUsers'],
            'bypassAuth' => true,
        ],
        // onboarding firmy (wymagane po rejestracji, zanim user ma company_id)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Companies',
            'action' => ['onboarding', 'saveOnboarding', 'edit', 'checkSeriesStart'],
        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => ['Invoices'],
            'action' => [
                'addRental',
                'index',
                'view',
                'add',
                'addVat',
                'addCurrency',
                'addNoVat',
                'addProforma',
                'addCreditNote',
                'addAdvance',
                'edit',
                'editVat',
                'editCurrency',
                'editNoVat',
                'editProforma',
                'editAdvance',
                'editCorrection',
                'editMargin',
                'editInternal',
                'editInternalEvidence',
                'editOss',
                'delete',
                'validateAjax',
                'nbpRate',
                'nbpCurrencies',
                'proformaSearch',
                'proformaDetails',
                'addCorrection',
                'addMargin',
                'addInternal',
                'addInternalEvidence',
                'addOss',
                'print',
                'export',
                'bulkAction',
                'sendToKsef',
                'refreshKsefStatus',
                'downloadKsef',
                'downloadFa3Xml',
                'downloadUpo',
                'downloadUpoByInvoice',
                'downloadUpoPdf',
                'upoHtml',
                'metadataKsef',
                'drafts',
                'sendDraftNow',
                'scheduleDraft',
                'promoteToIssued',
                'ksefSendLogs',
                'contractorEmailLookup',
                'emailInvoice',
                'ksefAuthActive',
                'ksefAuthLogin',
                'ksefSmoke',
                'generatePdfInternal',
                'processEmailQueue',
                'debugKsefXml',
                'checkRatesBatch',
                'getLabel',
                'generateLabel',
                'markSent',
                'printCustom',
            ],

        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Contractors',
            'action' => [
                'gusLookup',
                'vatStatusLookup',
                'search',
                'index',
                'view',
                'add',
                'viewJson',
                'edit',
                'delete',
                'export',
                'invoices',
                'importFetch',
                'importBatch',
            ],

        ],

        // ── Asystent spedytora ── tylko Kontrahenci (view+add — wg specyfikacji,
        // edit/delete robi kierownik/admin po akceptacji) + Zlecenia transportowe.
        // Granularne ograniczenia per akcja (np. zakaz delete) wymagają DB-driven
        // enforcement w osobnej iteracji — na razie pełen zakres tych dwóch modułów.
        [
            'role' => 'asystent_spedytora',
            'controller' => 'Contractors',
            'action' => [
                'gusLookup', 'vatStatusLookup', 'search',
                'index', 'view', 'viewJson', 'add', 'edit',
                'importFetch', 'importBatch',
            ],
        ],
        [
            'role' => ['asystent_spedytora', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager', 'user'],
            'controller' => 'SpeedOrders',
            'action' => [
                'index', 'dashboard', 'exportCsv', 'view', 'viewModal',
                'sync', 'updateStatus', 'createBatchInvoices',
                'uploadAttachment', 'deleteAttachment',
            ],
        ],

        // Słownik adresów transportowych — dostępny dla pracowniczych ról
        [
            'role' => ['user', 'asystent_spedytora', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'TransportAddresses',
            'action' => ['index', 'search', 'add', 'edit', 'delete'],
        ],

        // towary i usługi
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Products',
            'action' => [
                'index',
                'view',
                'viewJson',
                'add',
                'edit',
                'delete',
                'export',
                'search',
                'importFetch',
                'importBatch',
            ],
        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Units',
            'action' => [
                'index',
                'view',
                'add',
                'edit',
                'delete',
            ],
        ],

        // rachunki bankowe firmy (select na fakturze)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'CompanyBankAccounts',
            'action' => [
                'index',
                'search',
                'add',
                'edit',
                'delete',
            ],
        ],

        // serie numeracji faktur
        [
            'role' => ['user',  'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'InvoiceSeries',
            'action' => [
                'index',
                'view',
                'search',
                'add',
                'edit',
                'delete',
                'nextNumber',
            ],
        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'InvoiceSeriesTypes',
            'action' => [
                'index',
                'search',
                'add',
            ],
        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'InvoiceSeriesPeriods',
            'action' => [
                'index',
                'search',
                'add',
            ],
        ],

        // podstawowy landing po zalogowaniu
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Dashboard',
            'action' => ['index'],
        ],
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'KsefAuthorizations',
            'action' => [
                'received',
                'issued',
                'status',
                'statusAjax',
                'statusApi',
                'personalGrants',
                'personalGrantsCheck',
                'authorizationsGrants',
                'bookingSummary',
                'certDiagnostics',
                'costCategories',
                'download',
                'lines',
                'preview',
                'uploadCertificate',
                'view',
                'saveBookingItems',
            ],
        ],

        // 2FA jest opcjonalne, ale dostęp do ustawień musi mieć każdy zalogowany
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'TwoFactor',
            'action' => ['index', 'enable', 'verify', 'disable'],
        ],

        // rozliczenia/płatności do faktur (modal w liście faktur)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'InvoicePayments',
            'action' => ['index', 'add', 'delete'],
        ],

        // Rozliczenia — moduł + synchronizacja legacy
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Reconciliations',
            'action' => ['index', 'indexKsef', 'indexSpeed', 'addPayment', 'deletePayment', 'bankTransactions', 'contractorInfo', 'createContractorFromInvoice', 'syncLegacy', 'addLegacyPayment', 'deleteLegacyPayment', 'legacyBankTransactions', 'allocations', 'addAllocation', 'deleteAllocation', 'transactionAllocatedSummary'],
        ],

        // Wyciągi bankowe
        [
            'role'       => 'user',
            'controller' => 'BankTransactions',
            'action'     => ['index', 'transactions', 'view', 'import', 'confirmMatch', 'ignoreTransaction', 'delete', 'invoiceSearch', 'txAllocations'],
        ],

        // Kredyt kupiecki
        [
            'role'       => 'user',
            'controller' => 'CreditChecks',
            'action'     => ['index', 'sync', 'checkOpinion', 'foreignSearch', 'delete'],
        ],

        // Karty paliwowe E100
        [
            'role' => ['user',  'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'FuelCards',
            'action' => [
                'index', 'exportCsv', 'sync',
                'accounts', 'addAccount', 'editAccount', 'deleteAccount',
                'cards', 'cardInfo', 'blockCard',
                'balance',
                'limits', 'getLimit', 'setLimit',
                'stations',
            ],
        ],

        // odbiorcy e-mail faktur (panel kontrahenta)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Recipients',
            'action' => ['byContractor', 'view', 'add', 'edit', 'delete'],
        ],

        // ustawienia kontrahenta (panel kontrahenta)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'ContractorsSettings',
            'action' => ['view', 'save'],
        ],

        // stawki VAT (widok tylko do odczytu; edycja dla admina)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Vats',
            'action' => ['index', 'view'],
        ],

        // archiwum faktur (import z poprzedniego systemu)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'LegacyInvoices',
            'action' => ['index', 'fetch', 'downloadPdf'],
        ],

        // tokeny API (generowanie i unieważnianie własnych tokenów)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'ApiTokens',
            'action' => ['index', 'generate', 'revoke'],
        ],

        // słownik walutowy NBP
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'Nbp',
            'action' => ['dictionary', 'rates'],
        ],

        // tablica Kanban / taski (tylko admin — ale admin ma wildcard, tu na wszelki wypadek)
        // Admin wildcard (linia ~100) już to pokrywa. Brak wpisów dla 'user' — intencjonalne.

        // zgłoszenia i uwagi (support tickets)
        [
            'role' => ['user', 'mlodszy_spedytor', 'spedycja_manager', 'sales_manager'],
            'controller' => 'SupportTickets',
            'action' => ['index', 'add', 'view', 'download'],
        ],

        // ── Portal klienta (rola `client`) ────────────────────────────────────
        [
            'role'       => 'client',
            'plugin'     => 'CakeDC/Users',
            'controller' => 'Users',
            'action'     => ['profile', 'logout', 'changePassword', 'edit',
                             'webauthn2fa', 'webauthn2faRegister', 'webauthn2faRegisterOptions',
                             'webauthn2faAuthenticate', 'webauthn2faAuthenticateOptions'],
        ],
        [
            'role'       => 'client',
            'controller' => 'ClientPortal',
            'action'     => ['index', 'view', 'downloadAttachment', 'downloadInvoice', 'setLocale'],
        ],
        // Każdy zalogowany może przełączać język (PL/EN) — używamy ClientPortal::setLocale
        // jako wspólnego endpointu (zapisuje Config.locale w sesji)
        [
            'role'       => '*',
            'controller' => 'ClientPortal',
            'action'     => 'setLocale',
        ],
        // Klient może pobrać PDF faktury — ale tylko tej, która wisi przy jego zleceniu
        [
            'role'       => 'client',
            'controller' => 'Invoices',
            'action'     => ['print', 'printCustom'],
            'allowed'    => function ($user, $role, \Cake\Http\ServerRequest $request) {
                $invoiceId = \Cake\Utility\Hash::get($request->getAttribute('params'), 'pass.0');
                $userId    = is_array($user)
                    ? ($user['id'] ?? null)
                    : ($user?->id ?? null);
                if (!$invoiceId || !$userId) {
                    return false;
                }
                $profile = \Cake\ORM\TableRegistry::getTableLocator()
                    ->get('ClientProfiles')
                    ->find()
                    ->where(['user_id' => $userId])
                    ->first();
                if (!$profile) {
                    return false;
                }
                return \Cake\ORM\TableRegistry::getTableLocator()
                    ->get('SpeedOrders')
                    ->find()
                    ->where(['invoice_id' => $invoiceId, 'buyer_nip' => $profile->nip])
                    ->count() > 0;
            },
        ],
        [
            'role' => '*',
            'plugin' => 'DebugKit',
            'controller' => '*',
            'action' => '*',
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'plugin' => '*',
            'controller' => 'KsefAuthorizations',
            'action' => ['receivedApi', 'issuedApi', 'linesApi', 'previewApi', 'personalGrantsCheckApi', 'statusApi'],
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'prefix' => 'Api',
            'plugin' => '*',
            'controller' => '*',
            'action' => '*',
            'bypassAuth' => true,
        ],
        [
            'role' => '*',
            'controller' => 'Sso',
            'action' => ['login'],
            'bypassAuth' => true,
        ],
        // Skan QR z etykiety pocztowej — oznaczenie faktury jako wysłanej (bez logowania)
        [
            'role' => '*',
            'prefix' => false,
            'plugin' => false,
            'controller' => 'Invoices',
            'action' => ['scanLabel'],
            'bypassAuth' => true,
        ],
    ]
];

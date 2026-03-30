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
        //specific actions allowed for the all roles in Users plugin
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => ['profile', 'logout', 'linkSocial', 'callbackLinkSocial'],
        ],
        [
            'role' => '*',
            'plugin' => 'CakeDC/Users',
            'controller' => 'Users',
            'action' => 'resetOneTimePasswordAuthenticator',
            'allowed' => function (array $user, $role, \Cake\Http\ServerRequest $request) {
                $userId = \Cake\Utility\Hash::get($request->getAttribute('params'), 'pass.0');
                if (!empty($userId) && !empty($user)) {
                    return $userId === $user['id'];
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
            'role' => 'user',
            'controller' => 'Companies',
            'action' => ['onboarding', 'saveOnboarding', 'edit', 'checkSeriesStart'],
        ],
        [
            'role' => 'user',
            'controller' => ['Invoices'],
            'action' => [
                'index',
                'view',
                'add',
                'addVat',
                'addCurrency',
                'addNoVat',
                'addProforma',
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
                'ksefSendLogs',
                'contractorEmailLookup',
                'emailInvoice',
                'ksefAuthActive',
                'ksefAuthLogin',
                'ksefSmoke',
                'generatePdfInternal',
                'processEmailQueue',
                'debugKsefXml',
            ],

        ],
        [
            'role' => 'user',
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

        // towary i usługi
        [
            'role' => 'user',
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
            'role' => 'user',
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
            'role' => 'user',
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
            'role' => 'user',
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
            'role' => 'user',
            'controller' => 'InvoiceSeriesTypes',
            'action' => [
                'index',
                'search',
                'add',
            ],
        ],
        [
            'role' => 'user',
            'controller' => 'InvoiceSeriesPeriods',
            'action' => [
                'index',
                'search',
                'add',
            ],
        ],

        // podstawowy landing po zalogowaniu
        [
            'role' => 'user',
            'controller' => 'Dashboard',
            'action' => ['index'],
        ],
        [
            'role' => 'user',
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
            'role' => 'user',
            'controller' => 'TwoFactor',
            'action' => ['index', 'enable', 'verify', 'disable'],
        ],

        // rozliczenia/płatności do faktur (modal w liście faktur)
        [
            'role' => 'user',
            'controller' => 'InvoicePayments',
            'action' => ['index', 'add', 'delete'],
        ],

        // odbiorcy e-mail faktur (panel kontrahenta)
        [
            'role' => 'user',
            'controller' => 'Recipients',
            'action' => ['byContractor', 'view', 'add', 'edit', 'delete'],
        ],

        // ustawienia kontrahenta (panel kontrahenta)
        [
            'role' => 'user',
            'controller' => 'ContractorsSettings',
            'action' => ['view', 'save'],
        ],

        // stawki VAT (widok tylko do odczytu; edycja dla admina)
        [
            'role' => 'user',
            'controller' => 'Vats',
            'action' => ['index', 'view'],
        ],

        // archiwum faktur (import z poprzedniego systemu)
        [
            'role' => 'user',
            'controller' => 'LegacyInvoices',
            'action' => ['index', 'fetch', 'downloadPdf'],
        ],

        // tokeny API (generowanie i unieważnianie własnych tokenów)
        [
            'role' => 'user',
            'controller' => 'ApiTokens',
            'action' => ['index', 'generate', 'revoke'],
        ],

        // słownik walutowy NBP
        [
            'role' => 'user',
            'controller' => 'Nbp',
            'action' => ['dictionary', 'rates'],
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
    ]
];

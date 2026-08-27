<?php
/**
 * Mapa: kod uprawnienia (z tabeli `permissions`) -> akcje kontrolerow.
 *
 * Uzywana przez App\Rbac\Permissions\DbProvider ktory czyta zaznaczone
 * uprawnienia per rola z tabeli `roles_permissions` i emituje regule
 * CakeDC/Auth-owe {role, controller, action[]}.
 *
 * Zasady:
 *  - Jeden kod moze pokrywac wiele akcji tego samego kontrolera.
 *  - Ta sama akcja moze wymagac roznych kodow (np. 'index' Invoices
 *    daje sie zobaczyc tylko z 'invoices.view').
 *  - Kod z sufiksem '.view' zwykle obejmuje: index, view, print, export, listJson.
 *  - Kod z sufiksem '.add' obejmuje: add, dodaj (PL routes), duplicate.
 *  - Kod z sufiksem '.edit' obejmuje: edit, edytuj, patchEntity actions.
 *  - Kod z sufiksem '.delete' obejmuje: delete, usun.
 *  - Kod z sufiksem '.manage' = CRUD calosciowy (index, add, edit, delete).
 *
 * WAZNE: akcje nie wystepujace w mapie sa ALLOW dla wszystkich zalogowanych
 * z rola matching w permissions.php (fallback). DbProvider dodaje reguly
 * DB PRZED regułami z permissions.php, wiec jesli code jest zdefiniowany
 * w DB dla roli - DB decyduje.
 *
 * Wersja: 2026-08-26
 */

return [
    // ==================== invoices ====================
    'invoices.view' => [
        ['controller' => 'Invoices', 'action' => ['index', 'view', 'preview', 'print', 'exportCsv', 'checkCurrencyRates']],
        ['controller' => 'InvoicePayments', 'action' => ['index', 'view']],
        ['controller' => 'InvoiceSeries', 'action' => ['index', 'view']],
        ['controller' => 'InvoiceContractors', 'action' => ['index', 'view']],
        ['controller' => 'InvoiceCompanyDetails', 'action' => ['index', 'view']],
    ],
    'invoices.add' => [
        ['controller' => 'Invoices', 'action' => ['add', 'addVat', 'addNovat', 'addProforma', 'addCurrency', 'addAdvance', 'addFinal', 'addMargin', 'addRental', 'addOss', 'addInternal', 'addInternalEvidence', 'buildXml', 'buildLinesXml', 'buildVatSummaryXml']],
        ['controller' => 'InvoicePayments', 'action' => ['add']],
    ],
    'invoices.edit' => [
        ['controller' => 'Invoices', 'action' => ['edit', 'editVat', 'editNovat', 'editCurrency', 'update', 'workflowChange', 'assign']],
        ['controller' => 'InvoicePayments', 'action' => ['edit']],
    ],
    'invoices.delete' => [
        ['controller' => 'Invoices', 'action' => ['delete']],
        ['controller' => 'InvoicePayments', 'action' => ['delete']],
    ],
    'invoices.ksef' => [
        ['controller' => 'Invoices', 'action' => ['sendKsef', 'ksefStatus', 'ksefBatch']],
        ['controller' => 'KsefAuthorizations', 'action' => ['send', 'status', 'download']],
    ],
    'invoices.correction' => [
        ['controller' => 'Invoices', 'action' => ['addCorrection', 'addCorrectCurrency', 'addCreditNote']],
    ],
    'invoices.drafts' => [
        ['controller' => 'Invoices', 'action' => ['drafts', 'runPlannedDrafts', 'scheduleDraft']],
    ],
    'invoices.export' => [
        ['controller' => 'Invoices', 'action' => ['exportCsv', 'exportXlsx', 'exportJpk', 'checkRatesBatch']],
    ],
    'invoices.print' => [
        ['controller' => 'Invoices', 'action' => ['print', 'generatePdfInternal', 'labels', 'qrLabels']],
    ],
    'invoices.email' => [
        ['controller' => 'Invoices', 'action' => ['sendEmail', 'processEmailQueue']],
    ],
    'invoices.admin_all' => [
        ['controller' => 'Invoices', 'action' => ['adminAll', 'adminOverview']],
    ],
    'invoice_series.manage' => [
        ['controller' => 'InvoiceSeries', 'action' => ['index', 'add', 'edit', 'delete']],
        ['controller' => 'InvoiceSeriesTypes', 'action' => ['index', 'add', 'edit', 'delete']],
        ['controller' => 'InvoiceSeriesPeriods', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'products.manage' => [
        ['controller' => 'Products', 'action' => ['index', 'add', 'edit', 'delete', 'listJson', 'search']],
    ],
    'units.manage' => [
        ['controller' => 'Units', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'vats.view' => [
        ['controller' => 'Vats', 'action' => ['index', 'view']],
    ],
    'company_bank_accounts.manage' => [
        ['controller' => 'CompanyBankAccounts', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'recipients.manage' => [
        ['controller' => 'Recipients', 'action' => ['index', 'add', 'edit', 'delete']],
        ['controller' => 'ContractorsSettings', 'action' => ['index', 'edit']],
    ],
    'nbp.view' => [
        ['controller' => 'Nbp', 'action' => ['rates', 'index', 'view']],
    ],
    'legacy_invoices.view' => [
        ['controller' => 'LegacyInvoices', 'action' => ['index', 'view']],
    ],
    'api_tokens.manage' => [
        ['controller' => 'ApiTokens', 'action' => ['index', 'add', 'edit', 'delete', 'revoke']],
    ],

    // ==================== contractors ====================
    'contractors.view' => [
        ['controller' => 'Contractors', 'action' => ['index', 'view', 'search', 'searchJson', 'listJson']],
        ['controller' => 'ContractorBankAccounts', 'action' => ['index', 'view']],
    ],
    'contractors.add' => [
        ['controller' => 'Contractors', 'action' => ['add', 'gusLookup', 'viesLookup']],
        ['controller' => 'ContractorBankAccounts', 'action' => ['add']],
    ],
    'contractors.edit' => [
        ['controller' => 'Contractors', 'action' => ['edit', 'update']],
        ['controller' => 'ContractorBankAccounts', 'action' => ['edit']],
    ],
    'contractors.delete' => [
        ['controller' => 'Contractors', 'action' => ['delete']],
        ['controller' => 'ContractorBankAccounts', 'action' => ['delete']],
    ],
    'contractors.approve' => [
        ['controller' => 'Contractors', 'action' => ['approve', 'reject', 'pending']],
    ],
    'contractors.export' => [
        ['controller' => 'Contractors', 'action' => ['exportCsv', 'exportXlsx', 'gusLookup', 'viesLookup']],
    ],
    'contractors.import' => [
        ['controller' => 'Contractors', 'action' => ['import', 'batchImport']],
    ],
    'credit_limits.manage' => [
        ['controller' => 'ContractorCreditLimits', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'credit_checks.view' => [
        ['controller' => 'CreditChecks', 'action' => ['index', 'check', 'view']],
    ],

    // ==================== speed_orders ====================
    'speed_orders.view' => [
        ['controller' => 'SpeedOrders', 'action' => ['index', 'view', 'viewModal', 'dashboard']],
    ],
    'speed_orders.add' => [
        ['controller' => 'SpeedOrders', 'action' => ['add', 'duplicate', 'aiParseOrderJson', 'nextManualSymbol', 'buyerProfileJson', 'creditCheckJson', 'cabotageCheckJson', 'routeCalcJson', 'citiesJson', 'lastForBuyerJson', 'driversJson', 'vehiclesJson', 'routePlansJson', 'freeResourcesJson', 'conflictCheckJson']],
    ],
    'speed_orders.edit' => [
        ['controller' => 'SpeedOrders', 'action' => ['edit', 'update', 'updateStatus', 'assign', 'noteAdd', 'noteDelete']],
    ],
    'speed_orders.delete' => [
        ['controller' => 'SpeedOrders', 'action' => ['delete']],
    ],
    'speed_orders.docs_upload' => [
        ['controller' => 'SpeedOrders', 'action' => ['uploadAttachment', 'deleteAttachment']],
    ],
    'speed_orders.carrier' => [
        ['controller' => 'SpeedOrders', 'action' => ['assignCarrier', 'carrierList']],
    ],
    'speed_orders.costs' => [
        ['controller' => 'SpeedOrders', 'action' => ['costs', 'costSummary']],
        ['controller' => 'CostInvoices', 'action' => ['assignToOrder', 'unassignFromOrder']],
    ],
    'speed_orders.finance' => [
        ['controller' => 'SpeedOrders', 'action' => ['finance', 'financialResult']],
    ],
    'speed_orders.approve' => [
        ['controller' => 'SpeedOrders', 'action' => ['approve', 'reject']],
    ],
    'speed_orders.export' => [
        ['controller' => 'SpeedOrders', 'action' => ['exportCsv', 'exportXlsx', 'batchImport', 'batchImportTemplate']],
    ],
    'speed_orders.tracking' => [
        ['controller' => 'SpeedOrders', 'action' => ['tracking', 'kanban', 'kanbanMove']],
    ],
    'speed_orders.sync' => [
        ['controller' => 'SpeedOrders', 'action' => ['sync']],
    ],
    'speed_orders.templates' => [
        ['controller' => 'SpeedOrders', 'action' => ['templatesListJson', 'templateSaveJson', 'templateDeleteJson', 'templateUseJson', 'templateFavoriteJson']],
    ],
    'pallet_types.manage' => [
        ['controller' => 'PalletTypes', 'action' => ['index', 'add', 'edit', 'delete', 'listJson']],
    ],
    'transport_addresses.manage' => [
        ['controller' => 'TransportAddresses', 'action' => ['index', 'add', 'edit', 'delete']],
    ],

    // ==================== crm ====================
    'crm.leads.view' => [
        ['controller' => 'Leads', 'action' => ['index', 'view', 'kanban', 'peekJson', 'labelsAllJson', 'urgentEmails', 'myMentions', 'myTasks', 'faq', 'attachmentDownload', 'attachmentFile']],
    ],
    'crm.leads.add' => [
        ['controller' => 'Leads', 'action' => ['add', 'activityAdd', 'attachmentUpload', 'labelCreateInlineJson', 'gusLookupJson', 'krsLookupJson', 'linkedinSearchJson']],
    ],
    'crm.leads.edit' => [
        ['controller' => 'Leads', 'action' => ['edit', 'kanbanMove', 'assignLabels', 'archive', 'unarchive', 'taskDone', 'replyByGmail', 'activityDelete', 'attachmentDelete']],
    ],
    'crm.leads.delete' => [
        ['controller' => 'Leads', 'action' => ['delete']],
    ],
    'crm.leads.import' => [
        ['controller' => 'Leads', 'action' => ['importCsv', 'importCsvTemplate']],
    ],
    'crm.leads.convert' => [
        ['controller' => 'Leads', 'action' => ['convertToContractor', 'createOfferFromLead', 'createOrdersFromQuote']],
    ],
    'crm.leads.bulk' => [
        ['controller' => 'Leads', 'action' => ['bulk']],
    ],
    'crm.leads.merge' => [
        ['controller' => 'Leads', 'action' => ['duplicates', 'mergeReview', 'merge']],
    ],
    'crm.leads.ai' => [
        ['controller' => 'Leads', 'action' => ['aiDraftResponseJson', 'aiSummarizeJson', 'suggestPriceJson', 'savePricesJson', 'quotePdf', 'sendQuoteJson']],
    ],
    'crm.dashboard' => [
        ['controller' => 'Leads', 'action' => ['dashboard']],
    ],
    'crm.manager_dashboard' => [
        ['controller' => 'Leads', 'action' => ['managerDashboard']],
    ],
    'crm.dictionaries' => [
        ['controller' => 'LeadIndustries', 'action' => ['index', 'add', 'edit', 'delete']],
        ['controller' => 'LeadVehicleTypes', 'action' => ['index', 'add', 'edit', 'delete']],
        ['controller' => 'LeadLabels', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'crm.contracts.manage' => [
        ['controller' => 'CrmContracts', 'action' => ['index', 'add', 'edit', 'delete', 'matchJson']],
    ],
    'crm.email_accounts.manage' => [
        ['controller' => 'CrmEmailAccounts', 'action' => ['index', 'add', 'edit', 'delete', 'test', 'googleAuth', 'googleCallback']],
    ],
    'crm.workflows.manage' => [
        ['controller' => 'CrmWorkflows', 'action' => ['index', 'add', 'edit', 'delete']],
    ],
    'crm.doc_tracks.manage' => [
        ['controller' => 'CrmDocumentTracks', 'action' => ['create', 'stats', 'deactivate']],
    ],
    'crm.admin_tools' => [
        ['controller' => 'CrmAdmin', 'action' => ['tools', 'migrate', 'migrationStatus', 'clearCache', 'pollEmails', 'runCron', 'gitPull', 'fileCheck', 'nuclearClear', 'findLead', 'resetGmailHistory', 'analyzeLastEmail', 'clearLeadAssignments']],
    ],

    // ==================== cost_invoices ====================
    'cost_invoices.view' => [
        ['controller' => 'CostInvoices', 'action' => ['index', 'view', 'exportCsv']],
    ],
    'cost_invoices.add' => [
        ['controller' => 'CostInvoices', 'action' => ['add']],
    ],
    'cost_invoices.edit' => [
        ['controller' => 'CostInvoices', 'action' => ['edit', 'updateStatus', 'noteAdd', 'noteDelete']],
    ],
    'cost_invoices.delete' => [
        ['controller' => 'CostInvoices', 'action' => ['delete']],
    ],
    'cost_invoices.import' => [
        ['controller' => 'CostInvoices', 'action' => ['syncKsef', 'importFromKsef']],
    ],
    'cost_invoices.assign' => [
        ['controller' => 'CostInvoices', 'action' => ['assignOrder', 'unassignOrder', 'linesAiSuggest', 'linesUpdate']],
    ],
    'cost_invoices.payments' => [
        ['controller' => 'CostInvoices', 'action' => ['addPayment', 'deletePayment', 'markPaid']],
    ],
    'cost_invoices.manage' => [
        ['controller' => 'CostInvoices', 'action' => ['index', 'add', 'edit', 'delete', 'assignOrder', 'unassignOrder']],
    ],
    'cost_categories.manage' => [
        ['controller' => 'CostCategories', 'action' => ['index', 'add', 'edit', 'delete']],
    ],

    // ==================== finance ====================
    'reconciliations.view' => [
        ['controller' => 'Reconciliations', 'action' => ['index', 'view', 'calendar', 'insights', 'topDebtors', 'bankTransactions']],
    ],
    'reconciliations.pay' => [
        ['controller' => 'Reconciliations', 'action' => ['addPayment', 'deletePayment']],
    ],
    'reconciliations.kanban' => [
        ['controller' => 'Reconciliations', 'action' => ['kanban', 'kanbanMove', 'reminderSend', 'aiSuggestNextAction', 'noteAdd', 'noteDelete', 'assign', 'snooze', 'disputeToggle']],
    ],
    'reconciliations.admin' => [
        ['controller' => 'Reconciliations', 'action' => ['integrityCheck', 'repair']],
    ],
    'bank.view' => [
        ['controller' => 'BankTransactions', 'action' => ['index', 'view', 'transactions', 'listJson']],
    ],
    'bank.import' => [
        ['controller' => 'BankTransactions', 'action' => ['import', 'importPreview', 'aiParseTitle']],
    ],
    'bank.allocate' => [
        ['controller' => 'BankTransactions', 'action' => ['allocate', 'unallocate', 'allocateManual']],
    ],
    'ksef.view' => [
        ['controller' => 'KsefAuthorizations', 'action' => ['index', 'view', 'receivedApi', 'issuedApi', 'linesApi', 'previewApi', 'statusApi']],
    ],
    'ksef.manage' => [
        ['controller' => 'KsefAuthorizations', 'action' => ['add', 'edit', 'delete', 'grantAdd', 'grantRevoke', 'certUpload', 'certDiagnostics', 'personalGrantsCheck', 'personalGrantsCheckApi']],
        ['controller' => 'AccountingAuthorizations', 'action' => ['index', 'add', 'edit', 'delete', 'check']],
    ],

    // ==================== fleet ====================
    'fleet.vehicles.manage' => [
        ['controller' => 'Vehicles', 'action' => ['index', 'view', 'add', 'edit', 'delete']],
    ],
    'fleet.trailers.manage' => [
        ['controller' => 'Trailers', 'action' => ['index', 'view', 'add', 'edit', 'delete']],
    ],
    'fleet.drivers.manage' => [
        ['controller' => 'Drivers', 'action' => ['index', 'view', 'add', 'edit', 'delete']],
    ],
    'fleet.combinations.manage' => [
        ['controller' => 'VehicleCombinations', 'action' => ['index', 'view', 'add', 'edit', 'delete', 'listJson']],
    ],
    'fleet.type_categories.manage' => [
        ['controller' => 'VehicleTypeCategories', 'action' => ['index', 'add', 'edit', 'delete', 'forType']],
    ],
    'fleet.maintenance.view' => [
        ['controller' => 'VehicleMaintenance', 'action' => ['index', 'view', 'expiring', 'expiringJson']],
    ],
    'fleet.maintenance.manage' => [
        ['controller' => 'VehicleMaintenance', 'action' => ['add', 'edit', 'delete']],
    ],
    'fleet.schedules.view' => [
        ['controller' => 'DriverSchedules', 'action' => ['index', 'view', 'wolniJson', 'dlaKierowcyJson']],
        ['controller' => 'VehicleSchedules', 'action' => ['index', 'view', 'wolneJson']],
    ],
    'fleet.schedules.manage' => [
        ['controller' => 'DriverSchedules', 'action' => ['add', 'edit', 'delete']],
        ['controller' => 'VehicleSchedules', 'action' => ['add', 'edit', 'delete']],
    ],
    'fleet.time_logs.view' => [
        ['controller' => 'DriverTimeLogs', 'action' => ['index', 'view', 'statusJson']],
    ],
    'fleet.time_logs.manage' => [
        ['controller' => 'DriverTimeLogs', 'action' => ['add', 'edit', 'delete', 'import']],
    ],
    'fleet.availability.manage' => [
        ['controller' => 'DriverAvailability', 'action' => ['index', 'view', 'edit', 'delete']],
    ],

    // ==================== route ====================
    'route.planner.use' => [
        ['controller' => 'RoutePlanner', 'action' => ['index', 'calculate', 'pricingHistory', 'aiCargoWizard', 'aiPricing', 'aiDriverBrief', 'aiRouteOptimizer', 'aiEmailReply', 'aiDelayPrediction']],
    ],
    'route.planner.templates' => [
        ['controller' => 'RoutePlanner', 'action' => ['saveTemplate', 'listTemplates', 'deleteTemplate']],
    ],
    'route.planner.tolls' => [
        ['controller' => 'RoutePlanner', 'action' => ['tollOverride', 'tollLearning']],
    ],
    'route.offers.view' => [
        ['controller' => 'RouteOffers', 'action' => ['index', 'view']],
    ],
    'route.offers.create' => [
        ['controller' => 'RouteOffers', 'action' => ['create', 'send', 'edit']],
    ],
    'route.offers.delete' => [
        ['controller' => 'RouteOffers', 'action' => ['delete']],
    ],
    'route.trip_events.view' => [
        ['controller' => 'TripEvents', 'action' => ['forOrder', 'view']],
    ],
    'route.trip_events.add' => [
        ['controller' => 'TripEvents', 'action' => ['add', 'delete']],
    ],
    'route.return_loads.view' => [
        ['controller' => 'ReturnLoads', 'action' => ['forPlan', 'suggest', 'dismiss']],
    ],

    // ==================== reports ====================
    'reports.global' => [
        ['controller' => 'Reports', 'action' => ['index', 'global']],
    ],
    'reports.analytics' => [
        ['controller' => 'Analytics', 'action' => ['index']],
    ],
    'reports.compliance' => [
        ['controller' => 'ComplianceEvents', 'action' => ['index', 'view']],
    ],
    'reports.compliance.accept' => [
        ['controller' => 'ComplianceEvents', 'action' => ['accept', 'dismiss']],
    ],

    // ==================== fuel_cards ====================
    'fuel_cards.view' => [
        ['controller' => 'FuelCards', 'action' => ['index', 'view', 'accounts', 'balance', 'transactions', 'stations', 'exportCsv']],
    ],
    'fuel_cards.manage' => [
        ['controller' => 'FuelCards', 'action' => ['addCard', 'editCard', 'deleteCard', 'limits', 'sync']],
    ],
    'fuel_cards.block' => [
        ['controller' => 'FuelCards', 'action' => ['blockCard', 'unblockCard']],
    ],

    // ==================== support ====================
    'support.tickets.use' => [
        ['controller' => 'SupportTickets', 'action' => ['index', 'view', 'add', 'edit', 'delete']],
    ],
    'support.tasks.use' => [
        ['controller' => 'Tasks', 'action' => ['index', 'view', 'add', 'edit', 'delete', 'kanban', 'kanbanMove']],
    ],
    'support.notifications.own' => [
        ['controller' => 'Notifications', 'action' => ['index', 'view', 'markRead', 'delete', 'countJson']],
    ],

    // ==================== portal ====================
    'client_portal.access' => [
        ['controller' => 'ClientPortal', 'action' => '*'],
    ],

    // ==================== admin ====================
    'admin.users' => [
        ['controller' => 'AdminUsers', 'action' => '*'],
    ],
    'admin.roles' => [
        ['controller' => 'Roles', 'action' => '*'],
    ],
    'admin.permissions' => [
        ['controller' => 'Permissions', 'action' => '*'],
    ],
    'admin.settings' => [
        ['controller' => 'Companies', 'action' => ['edit', 'settings']],
    ],
    'admin.login_logs' => [
        ['controller' => 'AdminLoginLogs', 'action' => '*'],
    ],
    'admin.security_events' => [
        ['controller' => 'AdminSecurityEvents', 'action' => '*'],
    ],
    'admin.action_logs' => [
        ['controller' => 'AdminActionLogs', 'action' => '*'],
    ],
    'admin.performance' => [
        ['controller' => 'AdminPerformance', 'action' => '*'],
    ],
    'admin.impersonate' => [
        ['controller' => 'AdminImpersonate', 'action' => ['start', 'stop']],
    ],
];

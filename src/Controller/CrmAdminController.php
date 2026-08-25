<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Console\ConsoleIo;
use Cake\Console\Arguments;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Filesystem\Folder;
use Migrations\Migrations;

/**
 * CRM Admin Tools - webowe uruchamianie migracji / cron / cache clear.
 * Zabezpieczone auth (dostep tylko dla rol manager/admin).
 *
 * Endpointy:
 *   GET  /crm/admin/tools           - HTML page z buttonami
 *   POST /crm/admin/migrate         - uruchom migracje bazy
 *   POST /crm/admin/migration-status - lista migracji up/down
 *   POST /crm/admin/clear-cache     - wyczysc tmp/cache i tmp/sessions
 *   POST /crm/admin/poll-emails     - uruchom crm_email_poll manualnie
 *   POST /crm/admin/run-cron/{name} - uruchom dowolny cron command
 */
class CrmAdminController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Cron webhook = brak auth (token-secured w Configure Crm.cronToken)
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated(['cronWebhook']);
        }
    }

    public function tools(): void
    {
        $this->request->allowMethod(['get']);

        // Info o aktualnie zainstalowanym kodzie
        $gitInfo = $this->getGitInfo();
        $this->set('gitInfo', $gitInfo);
    }

    /**
     * POST /crm/admin/clear-lead-assignments - wyczyść assigned_to_user_id
     * dla WSZYSTKICH leadów tej firmy. Uzywac ostroznie - bez powrotu (chyba
     * ze robisz backup przed).
     * Zwraca ilosc dotknietych rekordow + krotki raport starych opiekunow.
     */
    public function clearLeadAssignments(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $out = "=== CLEAR LEAD ASSIGNMENTS ===\n\n";
        $out .= "Twoj company_id: {$companyId}\n\n";

        try {
            $Leads = $this->fetchTable('Leads');
            // Najpierw pokaz stan PRZED: kto ma leady przypisane
            $before = $Leads->find()
                ->select([
                    'assigned_to_user_id',
                    'cnt' => 'COUNT(*)',
                ])
                ->where([
                    'company_id' => $companyId,
                    'assigned_to_user_id IS NOT' => null,
                ])
                ->groupBy('assigned_to_user_id')
                ->disableHydration()
                ->all()->toArray();

            $out .= "=== STAN PRZED ===\n";
            $totalBefore = 0;
            $userIds = [];
            foreach ($before as $r) {
                $userIds[] = $r['assigned_to_user_id'];
                $totalBefore += (int)$r['cnt'];
            }
            // Fetch user names
            $userNames = [];
            if (!empty($userIds)) {
                try {
                    $Users = $this->fetchTable('Users');
                    $users = $Users->find()
                        ->select(['id', 'first_name', 'last_name', 'email'])
                        ->where(['id IN' => $userIds])
                        ->all();
                    foreach ($users as $u) {
                        $userNames[(string)$u->id] = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) . ' <' . ($u->email ?? '?') . '>';
                    }
                } catch (\Throwable $e) {}
            }
            foreach ($before as $r) {
                $uid = (string)$r['assigned_to_user_id'];
                $name = $userNames[$uid] ?? 'Unknown';
                $out .= sprintf("  %s (id=%s): %d leadow\n", $name, substr($uid, 0, 8), (int)$r['cnt']);
            }
            $out .= "\nRAZEM: {$totalBefore} leadow z przypisanym opiekunem.\n\n";

            if ($this->request->is('post')) {
                // Wykonanie UPDATE
                $affected = $Leads->updateAll(
                    ['assigned_to_user_id' => null],
                    ['company_id' => $companyId, 'assigned_to_user_id IS NOT' => null]
                );
                $out .= "=== EXECUTED ===\n";
                $out .= "✓ Zaktualizowano {$affected} leadow (assigned_to_user_id = NULL)\n\n";

                // Verify
                $stillAssigned = $Leads->find()->where([
                    'company_id' => $companyId,
                    'assigned_to_user_id IS NOT' => null,
                ])->count();
                $out .= "Weryfikacja: pozostalo {$stillAssigned} leadow z opiekunem.\n";

                // Log activity - dla audytu
                try {
                    $Acts = $this->fetchTable('LeadActivities');
                    // Note: nie mozemy logowac per lead (za drogo), robimy jeden systemowy note bez lead_id
                    // ale LeadActivities wymaga lead_id NOT NULL - wiec zostawiam Log::warning zamiast
                    \Cake\Log\Log::warning(sprintf(
                        'CRM: user %s wyczyscil assigned_to_user_id dla %d leadow firmy %s',
                        $identity?->get('email') ?? '?', $affected, $companyId
                    ));
                } catch (\Throwable $e) {}
            } else {
                $out .= "=== TRYB PODGLADU (GET) ===\n";
                $out .= "To jest tylko podglad. Aby WYKONAC UPDATE, wroc do /crm/admin/tools\n";
                $out .= "i kliknij czerwony button 'Wyczysc przypisania leadow' (POST z confirm).\n";
            }
        } catch (\Throwable $e) {
            $out .= "❌ EXCEPTION: " . $e->getMessage() . "\n";
        }

        $this->set('title', 'Clear lead assignments');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * GET /crm/admin/analyze-last-email?lead_id=X (opt)
     * Wez ostatni email z crm_email_messages i pokaz krok-po-kroku dlaczego
     * FALA 15 tryExtractQuoteRequest nie wykryl zapytania o wycene.
     */
    public function analyzeLastEmail(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        // FALA 16: Vision GPT 8000 tokens moze zajac 2-3 min
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $leadIdFilter = trim((string)$this->request->getQuery('lead_id', ''));
        $msgIdFilter = trim((string)$this->request->getQuery('message_id', ''));

        $out = "=== ANALYZE LAST EMAIL (FALA 15 debug) ===\n\n";
        try {
            $Msg = $this->fetchTable('CrmEmailMessages');
            $q = $Msg->find()->where(['company_id' => $companyId, 'direction' => 'in'])
                ->orderByDesc('received_at')->limit(1);
            if ($leadIdFilter !== '') $q->where(['lead_id' => $leadIdFilter]);
            if ($msgIdFilter !== '') $q->where(['message_id' => $msgIdFilter]);
            $msg = $q->first();
            if (!$msg) {
                $out .= "Brak wiadomosci w bazie dla tej firmy.\n";
                $out .= "Filter: lead_id={$leadIdFilter}, message_id={$msgIdFilter}\n";
                $this->set('title', 'Analyze Last Email');
                $this->set('output', $out);
                $this->render('output');
                return;
            }
            $out .= "Message: {$msg->id}\n";
            $out .= "Received: " . ($msg->received_at ? $msg->received_at->format('Y-m-d H:i:s') : '?') . "\n";
            $out .= "From: {$msg->from_email} ({$msg->from_name})\n";
            $out .= "Subject: {$msg->subject}\n";
            $out .= "Lead: {$msg->lead_id}\n";
            $out .= "Body length: " . strlen((string)$msg->body_text) . " chars\n";
            $out .= "Attachments: " . (int)$msg->attachments_count . "\n";

            // FALA 16: pokaz zalaczniki
            if ((int)$msg->attachments_count > 0) {
                $attList = json_decode((string)$msg->attachments_json, true);
                if (is_array($attList)) {
                    $out .= "  Zalaczniki:\n";
                    foreach ($attList as $i => $att) {
                        $sz = isset($att['size']) ? round($att['size'] / 1024, 1) . 'KB' : '?';
                        $hasId = !empty($att['attachment_id']) ? '(ID OK)' : '(ID BRAK - trzeba re-fetch)';
                        $out .= sprintf("    [%d] %s | %s | %s %s\n",
                            $i + 1,
                            $att['filename'] ?? '?',
                            $att['mime'] ?? '?',
                            $sz,
                            $hasId
                        );
                    }
                    // Sprawdz czy pdftotext dostepny
                    $reader = new \App\Service\Email\EmailAttachmentReaderService();
                    $ref = new \ReflectionClass($reader);
                    $m = $ref->getMethod('findBinary');
                    $m->setAccessible(true);
                    $pdftotext = $m->invoke($reader, 'pdftotext');
                    $out .= "  pdftotext CLI: " . ($pdftotext ?: '❌ BRAK - dla PDF text ekstract') . "\n";
                    $out .= "  smalot/pdfparser: " . (class_exists('\Smalot\PdfParser\Parser') ? 'OK (fallback)' : '❌ brak (composer require smalot/pdfparser)') . "\n";
                }
            }
            $out .= "\n";

            $bodyText = (string)$msg->body_text;
            $attachmentsFromDb = json_decode((string)$msg->attachments_json, true) ?: [];

            // FALA 16: LIVE re-fetch z Gmail API zeby dostac swiezy attachment_id
            // Sprawdz account i typ auth
            $liveAttachments = [];
            $liveFetchDone = false;
            try {
                $EA = $this->fetchTable('CrmEmailAccounts');
                $acc = $EA->get($msg->account_id);
                if ($acc && $acc->auth_type === 'gmail_oauth' && !empty($msg->message_id)) {
                    $out .= "\n=== LIVE FETCH z Gmail API (dla attachment_id) ===\n";
                    $svc = new \App\Service\GmailApiService();

                    // Refresh token jesli wygasl
                    $accessToken = $EA->decryptPassword($acc->oauth_access_token);
                    $needsRefresh = !$acc->oauth_expires_at || $acc->oauth_expires_at->isPast();
                    if ($needsRefresh) {
                        $refreshToken = $EA->decryptPassword($acc->oauth_refresh_token);
                        $tokens = $svc->refreshAccessToken($refreshToken);
                        $accessToken = $tokens['access_token'];
                        $out .= "  Access token refreshed\n";
                    }

                    // Znajdz gmail_id z payload_json wiadomosci (jest tam z syncGmailOauth)
                    // Fallback: search po message_id
                    $gmailId = null;
                    // Sprobuj wyciagnac gmail_id z activity payload
                    try {
                        $Acts = $this->fetchTable('LeadActivities');
                        $act = $Acts->find()
                            ->where([
                                'lead_id' => $msg->lead_id,
                                'activity_type' => 'email_in',
                                'payload_json LIKE' => '%' . $msg->message_id . '%',
                            ])->first();
                        if ($act && $act->payload_json) {
                            $p = json_decode($act->payload_json, true);
                            $gmailId = $p['gmail_id'] ?? null;
                        }
                    } catch (\Throwable $e) {}

                    if (!$gmailId) {
                        $out .= "  ⚠️ Brak gmail_id w LeadActivities.payload_json - musisz Reset Gmail history + Poll zeby zapisac.\n";
                    } else {
                        $out .= "  Gmail ID: {$gmailId}\n";
                        $freshMsg = $svc->getMessage($accessToken, $gmailId);
                        if ($freshMsg) {
                            $liveAttachments = $freshMsg['attachments'] ?? [];
                            $liveFetchDone = true;
                            $bodyText = $freshMsg['body_text']; // wez swiezy body tez
                            $out .= "  ✓ Pobrano " . count($liveAttachments) . " zalacznikow z Gmail\n";
                            foreach ($liveAttachments as $i => $att) {
                                $out .= sprintf("    [%d] %s | %s | %dB | id=%s\n",
                                    $i + 1,
                                    $att['filename'] ?? '?',
                                    $att['mime'] ?? '?',
                                    (int)($att['size'] ?? 0),
                                    substr($att['attachment_id'] ?? '', 0, 20) . '...'
                                );
                            }
                        } else {
                            $out .= "  ❌ Gmail getMessage() zwrocil null\n";
                        }
                    }
                }
            } catch (\Throwable $e) {
                $out .= "  Live fetch error: " . $e->getMessage() . "\n";
            }

            $hasAttach = !empty($liveAttachments) || !empty($attachmentsFromDb);
            $hasImage = false;
            foreach (($liveAttachments ?: $attachmentsFromDb) as $__att) {
                if (str_starts_with((string)($__att['mime'] ?? ''), 'image/')) { $hasImage = true; break; }
            }

            // KROK 1: filter body length
            $out .= "\n";
            if (strlen($bodyText) < 100 && !$hasAttach) {
                $out .= "❌ STOP: body_text < 100 chars ({$msg->body_length}) i brak zalacznikow.\n";
                $this->set('title', 'Analyze');
                $this->set('output', $out);
                $this->render('output');
                return;
            }
            $out .= "✓ STEP 1: body " . strlen($bodyText) . " chars" . ($hasAttach ? " + " . count($liveAttachments ?: $attachmentsFromDb) . " zalacznikow" : "") . "\n";

            // KROK 2: heurystyka sygnalow
            $signals = ['liefern', 'lieferung', 'transport', 'zlecenie', 'wycen', 'oferta',
                'quote', 'shipment', 'ladunek', 'zaladu', 'rozladu', 'anbieten',
                'offerte', 'preis', 'stawka', 'palet', 'kg', 'tonn', 'ldm',
                'kundenbestellnummer', 'transportauftrag', 'frachtbrief',
                'from:', 'to:', 'load:', 'unload:', 'pickup', 'delivery',
                'trasa', 'zaladunek', 'rozladunek', 'przewoz',
                'auftrag', 'sendung', 'fracht', 'lieferant', 'empfanger',
                'cargo', 'przewiezc', 'przewiozc', 'stawki'];
            // Szukaj w SUBJECT + BODY (forwarded maile maja tresc w tytule)
            $searchText = mb_strtolower((string)$msg->subject . ' ' . $bodyText);
            $matched = [];
            foreach ($signals as $s) {
                if (strpos($searchText, $s) !== false) { $matched[] = $s; }
            }
            // FALA 16 fix: hierarchia progu identyczna z Command:
            //  hasImage -> 0 (Vision widzi tabele na obrazku)
            //  hasAttach text/PDF -> 1
            //  tylko body -> 2
            if ($hasImage) {
                $requiredSignals = 0;
                $reason = " - 0 bo jest obrazek (Vision zobaczy tabele)";
            } elseif ($hasAttach) {
                $requiredSignals = 1;
                $reason = " - 1 bo zalaczniki tekstowe";
            } else {
                $requiredSignals = 2;
                $reason = "";
            }
            $out .= "\n✓ STEP 2: heurystyka pre-GPT (wymagane: {$requiredSignals}{$reason})\n";
            $out .= "  Znalezione sygnaly (" . count($matched) . "): " . implode(', ', $matched) . "\n";
            if (count($matched) < $requiredSignals) {
                $out .= "❌ STOP: <{$requiredSignals} sygnaly => tryExtractQuoteRequest zwraca 0.\n";
                $out .= "\nSUGESTIA: Body/subject nie zawiera slow kluczy transportowych.\n";
                $out .= "Pierwsze 500 znakow body:\n---\n" . mb_substr($bodyText, 0, 500) . "\n---\n";
                $this->set('title', 'Analyze');
                $this->set('output', $out);
                $this->render('output');
                return;
            }

            // KROK 3: OpenAI dostepne?
            // Case matters: OpenAiService uzywa 'OpenAI.apiKey' (duze AI)
            $apiKey = \Cake\Core\Configure::read('OpenAI.apiKey');
            if (!$apiKey) {
                $out .= "\n❌ STOP: brak Configure Openai.apiKey. Dodaj do app_local.php.\n";
                $this->set('title', 'Analyze');
                $this->set('output', $out);
                $this->render('output');
                return;
            }
            $out .= "\n✓ STEP 3: Openai.apiKey OK (len " . strlen($apiKey) . ")\n";

            // FALA 16: STEP 3b - fetch zalacznikow z Gmail + parse
            $imageDataUris = [];
            $attachmentTexts = [];
            $attSource = $liveAttachments ?: $attachmentsFromDb;
            if (!empty($attSource) && $liveFetchDone && isset($accessToken, $svc, $gmailId)) {
                $reader = new \App\Service\Email\EmailAttachmentReaderService();
                $out .= "\n=== STEP 3b: FETCH ZALACZNIKI (FALA 16) ===\n";
                $processed = 0;
                foreach ($attSource as $att) {
                    if ($processed >= 3) { $out .= "  ... +more skipped (max 3)\n"; break; }
                    $attId = $att['attachment_id'] ?? null;
                    if (!$attId) {
                        $out .= "  ⚠️ " . ($att['filename'] ?? '?') . ": brak attachment_id\n";
                        continue;
                    }
                    if (($att['size'] ?? 0) > 8 * 1024 * 1024) {
                        $out .= "  ❌ " . $att['filename'] . " za duzy: " . round($att['size'] / 1024 / 1024, 1) . "MB\n";
                        continue;
                    }
                    $out .= "  Fetching " . $att['filename'] . " (" . round($att['size'] / 1024, 1) . "KB)...\n";
                    $binary = $svc->getAttachment($accessToken, $gmailId, $attId, 8 * 1024 * 1024);
                    if ($binary === null) {
                        $out .= "    ❌ getAttachment zwrocilo null\n";
                        continue;
                    }
                    $read = $reader->read($binary, $att['mime'], $att['filename']);
                    if ($read['type'] === 'image') {
                        $imageDataUris[] = $read['content'];
                        $out .= "    ✓ IMAGE -> data URI (" . round(strlen($read['content']) / 1024, 1) . "KB base64) -> pojdzie do Vision\n";
                        $processed++;
                    } elseif ($read['type'] === 'text') {
                        $t = trim($read['content'] ?? '');
                        if ($t !== '') {
                            $attachmentTexts[] = "[ZALACZNIK: " . $att['filename'] . "]\n" . mb_substr($t, 0, 12000);
                            $out .= "    ✓ TEXT extracted (" . strlen($t) . " chars)\n";
                            $processed++;
                        } else {
                            $out .= "    ⚠️ TEXT empty\n";
                        }
                    } else {
                        $out .= "    ❌ " . ($read['error'] ?? 'unsupported') . "\n";
                    }
                }
            }

            // KROK 4: wywolaj GPT (identyczny prompt jak w Command)
            $useVision = !empty($imageDataUris);
            $out .= "\n✓ STEP 4: wywoluje GPT-4o-mini " . ($useVision ? "z " . count($imageDataUris) . " obr. (Vision multi-modal)" : "text only") . "...\n";
            $svc2 = new \App\Service\Ai\OpenAiService();
            $system = "Jestes spedytorem analizujacym maile z zapytaniami o transport. "
                . "Wyciagnij ze zrodla WSZYSTKIE zlecenia transportowe (mozna wiele w jednym mailu - np. tabela Excel wklejona w body, "
                . "lista zaladunkow, forwarded WG:/FW:/Weitergeleitete Nachricht). "
                . "Ignoruj podpisy, stopki, zaznaczenia zaufania, boilerplate. "
                . "Zwroc STRICT JSON: {"
                . "\"is_quote_request\": bool, "
                . "\"customer_name\": string, \"customer_contact\": string, "
                . "\"shipments_count\": int, "
                . "\"shipments\": [ {"
                . "\"customer_order_ref\": string, "
                . "\"from_country\": string, \"from_postal\": string, \"from_city\": string, \"from_company\": string, "
                . "\"to_country\": string, \"to_postal\": string, \"to_city\": string, \"to_company\": string, "
                . "\"load_date\": string, \"load_time\": string, \"unload_date\": string, \"unload_time\": string, "
                . "\"weight_kg\": int, \"pallets\": int, \"pallet_type\": string, "
                . "\"cargo_type\": string, \"vehicle_type\": string, \"notes\": string"
                . "} ] } "
                . "Puste pola = \"\" lub 0. is_quote_request=false jesli to zwykla korespondencja bez konkretnych zlecen.";
            $user = "Temat: {$msg->subject}\n\nNadawca: {$msg->from_name} <{$msg->from_email}>\n\nTresc:\n"
                . mb_substr($bodyText, 0, 6000);
            if (!empty($attachmentTexts)) {
                $user .= "\n\n=== TRESC ZALACZNIKOW ===\n" . implode("\n\n", $attachmentTexts);
            }
            if ($useVision) {
                $user .= "\n\n=== ZALACZNIKI OBRAZKOWE ===\nPrzeanalizuj tez zalaczone obrazy - moga zawierac tabele zlecen.";
            }

            $t0 = microtime(true);
            try {
                if ($useVision) {
                    $firstImg = array_shift($imageDataUris);
                    $extracted = $svc2->chatVisionJson($system, $user, $firstImg, 16000, $imageDataUris);
                } else {
                    $extracted = $svc2->chatJson($system, $user, 16000);
                }
                $dt = round((microtime(true) - $t0) * 1000);
                $out .= "  GPT odpowiedzial w {$dt}ms\n";
            } catch (\Throwable $e) {
                $out .= "❌ STOP: GPT rzucil wyjatek: " . $e->getMessage() . "\n";
                $this->set('title', 'Analyze');
                $this->set('output', $out);
                $this->render('output');
                return;
            }

            $out .= "\n=== GPT RESPONSE ===\n";
            $out .= "  is_quote_request: " . var_export($extracted['is_quote_request'] ?? null, true) . "\n";
            $out .= "  customer_name: " . ($extracted['customer_name'] ?? '(brak)') . "\n";
            $out .= "  customer_contact: " . ($extracted['customer_contact'] ?? '(brak)') . "\n";
            $out .= "  shipments_count: " . (int)($extracted['shipments_count'] ?? 0) . "\n";
            $ships = $extracted['shipments'] ?? [];
            $out .= "  shipments[] length: " . (is_array($ships) ? count($ships) : 'nie-array') . "\n\n";

            if (empty($extracted['is_quote_request'])) {
                $out .= "❌ STOP: GPT: is_quote_request=false. Analiza:\n";
                $out .= "  GPT ocenil ze to zwykla korespondencja, nie zapytanie o transport.\n";
                $out .= "  Aby zmusic ekstrakcje - popraw prompt/heurystyke lub oznacz manualnie.\n\n";
            } elseif (empty($ships)) {
                $out .= "❌ STOP: is_quote_request=true ALE shipments[] puste. Prompt GPT nie wyekstraktowal danych.\n\n";
            } else {
                $out .= "✓ STEP 5: {$extracted['shipments_count']} shipments znaleziono!\n\n";
                foreach ($ships as $i => $s) {
                    if ($i >= 5) { $out .= "  ... +" . (count($ships) - 5) . " wiecej\n"; break; }
                    $out .= sprintf("  %d. %s %s -> %s %s | %skg %spal | %s\n",
                        $i + 1,
                        $s['from_city'] ?? '', $s['from_country'] ?? '',
                        $s['to_city'] ?? '',   $s['to_country'] ?? '',
                        $s['weight_kg'] ?? '?', $s['pallets'] ?? '?',
                        $s['customer_order_ref'] ?? '');
                }
                $out .= "\nDLACZEGO nie ma tego w timeline leada? Sprawdz w /crm/view/{$msg->lead_id} czy jest activity quote_request.\n";
                $out .= "Jesli nie ma - Command sie wywalil PRZED tryExtractQuoteRequest lub cache stary.\n";
                $out .= "\nRAW JSON:\n" . json_encode($extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }

            $out .= "\n=== KOPIA BODY (500 chars) ===\n";
            $out .= mb_substr($bodyText, 0, 500) . "\n";

        } catch (\Throwable $e) {
            $out .= "\nEXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        }
        $this->set('title', 'Analyze Last Email');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * GET /crm/admin/find-lead?email=X - znajdz lead po emailu i pokaz kompletne info
     */
    public function findLead(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        $email = strtolower(trim((string)$this->request->getQuery('email', '')));
        $identity = $this->request->getAttribute('identity');
        $currentCompanyId = $identity?->get('company_id');

        $out = "=== FIND LEAD BY EMAIL ===\n\n";
        $out .= "Twoj company_id: {$currentCompanyId}\n";
        $out .= "Szukam email: <strong>{$email}</strong>\n\n";

        if ($email === '') {
            $out .= "Uzycie: /crm/admin/find-lead?email=marius.werth@wiegand-logistik.de\n";
            $this->set('title', 'Find Lead');
            $this->set('output', $out);
            $this->render('output');
            return;
        }

        $Leads = $this->fetchTable('Leads');

        // Match EXACT
        $exact = $Leads->find()
            ->where(['LOWER(email)' => $email])
            ->all()->toArray();
        $out .= "=== EXACT MATCH LOWER(email) = '{$email}' ===\n";
        $out .= "Znaleziono: " . count($exact) . "\n";
        foreach ($exact as $l) {
            $marker = ((string)$l->company_id === (string)$currentCompanyId) ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗ (INNA FIRMA!)</span>';
            $out .= "  {$marker} {$l->id} · {$l->company_name} · company_id={$l->company_id} · stage={$l->stage}\n";
        }

        // Match LIKE (dla wychwycenia dodatkowych spacji, prefix/suffix)
        $like = $Leads->find()
            ->where(['email LIKE' => '%' . str_replace(['%', '_'], ['\%', '\_'], $email) . '%'])
            ->all()->toArray();
        $out .= "\n=== LIKE '%{$email}%' (wykrywa spacje/prefixy) ===\n";
        $out .= "Znaleziono: " . count($like) . "\n";
        foreach ($like as $l) {
            $storedEmail = $l->email;
            $sameCompany = ((string)$l->company_id === (string)$currentCompanyId);
            $marker = $sameCompany ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>';
            $out .= "  {$marker} {$l->id} · {$l->company_name} · email='<strong>{$storedEmail}</strong>' · company_id={$l->company_id}\n";
            // Compare bytes
            if (strtolower($storedEmail) !== $email) {
                $out .= "    <span style='color:orange;'>UWAGA: rozny od szukanego!</span>\n";
                $out .= "    Szukane bytes: " . bin2hex($email) . "\n";
                $out .= "    W bazie bytes: " . bin2hex(strtolower($storedEmail)) . "\n";
            }
        }

        // Sprawdz tez email_messages
        try {
            $Msg = $this->fetchTable('CrmEmailMessages');
            $msgs = $Msg->find()
                ->where(['LOWER(from_email)' => $email])
                ->orderByDesc('received_at')
                ->limit(10)
                ->all()->toArray();
            $out .= "\n=== crm_email_messages od {$email} (ostatnie 10) ===\n";
            $out .= "Znaleziono: " . count($msgs) . "\n";
            foreach ($msgs as $m) {
                $d = $m->received_at ? $m->received_at->format('Y-m-d H:i') : '?';
                $out .= "  {$d} · {$m->subject} · lead_id={$m->lead_id}\n";
            }
        } catch (\Throwable $e) {
            $out .= "\ncrm_email_messages error: " . $e->getMessage() . "\n";
        }

        $this->set('title', "Find Lead: {$email}");
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/reset-gmail-history - wymus fresh Gmail sync (zeruje history_id)
     */
    public function resetGmailHistory(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $out = "=== RESET GMAIL HISTORY_ID ===\n\n";
        try {
            $EA = $this->fetchTable('CrmEmailAccounts');
            $accounts = $EA->find()->where([
                'company_id' => $companyId,
                'auth_type' => 'gmail_oauth',
            ])->all();
            $count = 0;
            foreach ($accounts as $acc) {
                $out .= "Reset: {$acc->label} ({$acc->username}) - stary history_id: " . ($acc->oauth_history_id ?? 'null') . "\n";
                $acc->oauth_history_id = null;
                $acc->last_synced_at = null; // ignore cooldown na nastepny poll
                $EA->save($acc);
                $count++;
            }
            $out .= "\n✓ Zresetowano {$count} kont. Nastepny poll pobierze inbox z ostatnich 30 dni (max 100 msg).\n";
            $out .= "\nSprobuj teraz: Poll emails NOW (FORCE).\n";
        } catch (\Throwable $e) {
            $out .= "❌ " . $e->getMessage() . "\n";
        }
        $this->set('title', 'Reset Gmail history');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * GET /crm/admin/file-check - diagnostyka aktualnej wersji CrmEmailPollCommand.php
     */
    public function fileCheck(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== FILE CHECK: CrmEmailPollCommand.php ===\n\n";

        $file = ROOT . DS . 'src' . DS . 'Command' . DS . 'CrmEmailPollCommand.php';
        $out .= "Path: {$file}\n";
        $out .= "Exists: " . (file_exists($file) ? 'TAK' : 'NIE') . "\n";
        if (!file_exists($file)) {
            $this->set('title', 'File check');
            $this->set('output', $out);
            $this->render('output');
            return;
        }
        $content = file_get_contents($file);
        $out .= "Size: " . number_format(strlen($content)) . " bytes\n";
        $out .= "Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
        $out .= "SHA-256: " . hash('sha256', $content) . "\n\n";

        // Sprawdz WERSJE - nowa ma warning zamiast error
        $hasOldVersion = strpos($content, "PHP IMAP extension nie jest zainstalowane. Zainstaluj php-imap.") !== false;
        $hasNewVersion = strpos($content, "PHP IMAP extension nie jest dostepne - konta auth_type=imap zostana pominiete") !== false;
        $hasGmailBranch = strpos($content, 'syncGmailOauth') !== false;

        // FALA 14: sprawdz auto-create lead metody
        $hasAutoCreateCall = strpos($content, "tryCreateLeadFromEmail(\$data") !== false;
        $hasAutoCreateDef  = strpos($content, "private function tryCreateLeadFromEmail") !== false;

        $out .= "WERSJA:\n";
        $out .= "  Stara (return error): " . ($hasOldVersion ? '<span style="color:red;">TAK</span>' : 'NIE') . "\n";
        $out .= "  Nowa (warning+continue): " . ($hasNewVersion ? '<span style="color:green;">TAK</span>' : '<span style="color:red;">NIE - STARY PLIK!</span>') . "\n";
        $out .= "  syncGmailOauth (FALA 13): " . ($hasGmailBranch ? '<span style="color:green;">TAK</span>' : '<span style="color:red;">NIE - STARY PLIK!</span>') . "\n";
        $out .= "  FALA 14 wywolanie tryCreateLeadFromEmail: " . ($hasAutoCreateCall ? '<span style="color:green;">TAK</span>' : 'NIE') . "\n";
        $out .= "  FALA 14 DEFINICJA tryCreateLeadFromEmail: " . ($hasAutoCreateDef ? '<span style="color:green;">TAK</span>' : '<span style="color:red;">NIE - BRAK METODY!</span>') . "\n\n";
        if ($hasAutoCreateCall && !$hasAutoCreateDef) {
            $out .= "<strong style='color:red;'>PROBLEM: masz WYWOLANIE metody ale nie masz jej DEFINICJI!</strong>\n";
            $out .= "Plik zostal wgrany CZESCIOWO. Uzyj Admin Tools -> Git Pull FORCE (reset --hard).\n\n";
        }

        // Sekcja execute() - pokaz pierwsze 20 linii
        preg_match('/public function execute\(.*?\).*?\{(.*?)^\s{4}\}/sm', $content, $m);
        $execBody = $m[1] ?? '';
        $execFirstLines = implode("\n", array_slice(explode("\n", $execBody), 0, 20));
        $out .= "=== execute() first 20 lines ===\n";
        $out .= $execFirstLines . "\n\n";

        // OPcache status
        $out .= "=== OPCACHE ===\n";
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if ($status) {
                $out .= "Enabled: " . ($status['opcache_enabled'] ?? '?') . "\n";
                $out .= "Cached scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";
                $scripts = @opcache_get_status(true)['scripts'] ?? [];
                if (isset($scripts[$file])) {
                    $s = $scripts[$file];
                    $out .= "Ten plik W OPCACHE:\n";
                    $out .= "  timestamp: " . date('Y-m-d H:i:s', $s['timestamp'] ?? 0) . "\n";
                    $out .= "  hits: " . ($s['hits'] ?? '?') . "\n";
                } else {
                    $out .= "Ten plik NIE jest w opcache\n";
                }
            } else {
                $out .= "opcache_get_status() zwrocil false (moze zablokowane)\n";
            }
        } else {
            $out .= "opcache_get_status() niedostepne\n";
        }

        if ($hasOldVersion || !$hasNewVersion) {
            $out .= "\n<strong style='color:red;'>PROBLEM: plik na serwerze to STARA wersja!</strong>\n\n";
            $out .= "MOZLIWE PRZYCZYNY:\n";
            $out .= " 1. Git pull nie zdeployowal tego pliku (mimo ze pokazuje 'juz najnowszy')\n";
            $out .= " 2. Wgrales pliki recznie ale ten sie NIE zaladowal (moze conflict, moze skip)\n";
            $out .= " 3. Filesystem cache trzyma stara wersje\n\n";
            $out .= "FIX:\n";
            $out .= " - Wgraj plik ROZNICE recznie przez FTP/SFTP na serwer\n";
            $out .= " - Skopiuj z /src/Command/CrmEmailPollCommand.php z lokalu do serwera\n";
            $out .= " - Potem: /crm/admin/nuclear-clear (usunie ALL cache + regeneruje autoload)\n";
        }

        $this->set('title', 'File check: CrmEmailPollCommand.php');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/nuclear-clear - brute force cache reset (opcache + autoload)
     */
    public function nuclearClear(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== NUCLEAR CACHE CLEAR ===\n\n";

        try {
            // 1. Cake cache
            \Cake\Cache\Cache::clearAll();
            $out .= "✓ Cake Cache::clearAll()\n";

            // 2. Wszystkie pliki w tmp/ rekursywnie
            $tmpDir = ROOT . DS . 'tmp';
            $count = 0;
            if (is_dir($tmpDir)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iter as $f) {
                    $basename = $f->getBasename();
                    if ($basename === 'empty' || $basename === '.htaccess') continue;
                    if ($f->isFile()) {
                        @unlink($f->getPathname());
                        $count++;
                    }
                }
            }
            $out .= "✓ tmp/**: {$count} plikow usunieto (rekursywnie)\n";

            // 3. OPcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $out .= "✓ opcache_reset()\n";
            }
            if (function_exists('opcache_invalidate')) {
                $file = ROOT . DS . 'src' . DS . 'Command' . DS . 'CrmEmailPollCommand.php';
                if (file_exists($file)) {
                    opcache_invalidate($file, true);
                    $out .= "✓ opcache_invalidate(CrmEmailPollCommand.php, force=true)\n";
                }
            }

            // 4. Touch wszystkie .php w src/ zeby OPcache widzial jako 'zmienione'
            $srcDir = ROOT . DS . 'src';
            $touched = 0;
            if (is_dir($srcDir)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iter as $f) {
                    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                        @touch($f->getPathname());
                        $touched++;
                    }
                }
            }
            $out .= "✓ touch: {$touched} plikow .php w src/ (OPcache invalidation trigger)\n";

            // 5. Composer autoload regeneracja - via shell
            $composerCmd = "cd " . escapeshellarg(ROOT) . " && composer dump-autoload -o 2>&1";
            $out .= "\n> {$composerCmd}\n";
            $composerOut = @shell_exec($composerCmd);
            $out .= ($composerOut ?: "(brak output - composer moze niedostepny)") . "\n";

            $out .= "\n✓ ZAKONCZONO. Sprobuj teraz /crm/admin/file-check zeby zobaczyc czy plik jest zaktualizowany.\n";
            $out .= "Jesli plik dalej stary - trzeba go recznie wgrac przez FTP.\n";
        } catch (\Throwable $e) {
            $out .= "❌ EXCEPTION: " . $e->getMessage() . "\n";
        }

        $this->set('title', 'Nuclear clear');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/git-pull?force=1 - git pull lub git reset --hard
     */
    public function gitPull(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $force = $this->request->getQuery('force') === '1';
        $out = "=== " . ($force ? 'GIT RESET --HARD (brute force)' : 'GIT PULL') . " ===\n\n";

        $rootDir = ROOT;
        $gitBinary = trim((string)shell_exec('which git 2>&1')) ?: 'git';

        if (!is_dir($rootDir . DS . '.git')) {
            $out .= "❌ Brak katalogu .git w " . $rootDir . "\n";
        } else {
            $currentBefore = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse HEAD 2>&1"));
            $out .= "Commit przed: {$currentBefore}\n\n";

            if ($force) {
                // git fetch + reset --hard origin/current-branch
                $branch = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse --abbrev-ref HEAD 2>&1"));
                $out .= "Branch: {$branch}\n\n";

                $cmd1 = "cd " . escapeshellarg($rootDir) . " && {$gitBinary} fetch origin 2>&1";
                $out .= "> {$cmd1}\n" . (string)shell_exec($cmd1) . "\n";

                $cmd2 = "cd " . escapeshellarg($rootDir) . " && {$gitBinary} reset --hard origin/" . escapeshellarg($branch) . " 2>&1";
                $out .= "> {$cmd2}\n" . (string)shell_exec($cmd2) . "\n";
            } else {
                $cmd = "cd " . escapeshellarg($rootDir) . " && {$gitBinary} pull 2>&1";
                $out .= "> {$cmd}\n\n" . (string)shell_exec($cmd) . "\n";
            }

            $currentAfter = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse HEAD 2>&1"));
            $out .= "\nCommit po: {$currentAfter}\n";

            if ($currentBefore !== $currentAfter) {
                $out .= "\n✓ Zaktualizowano! Teraz KONIECZNIE Nuclear Clear (opcache_reset + touch).\n";
            } else {
                $out .= "\n= Brak zmian - juz miales najnowszy commit.\n";
            }
        }

        $this->set('title', 'Git pull');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * Zwraca info o aktualnym commit dla wyswietlania.
     */
    private function getGitInfo(): array
    {
        $rootDir = ROOT;
        if (!is_dir($rootDir . DS . '.git')) return ['available' => false];

        $gitBinary = trim((string)shell_exec('which git 2>&1')) ?: 'git';
        $commit = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse --short HEAD 2>&1"));
        $branch = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse --abbrev-ref HEAD 2>&1"));
        $date   = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} log -1 --format='%ci' 2>&1"));
        $msg    = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} log -1 --format='%s' 2>&1"));

        return [
            'available' => true,
            'commit'    => $commit,
            'branch'    => $branch,
            'date'      => $date,
            'message'   => $msg,
        ];
    }

    /**
     * POST /crm/admin/migrate - odpali `bin/cake migrations migrate`
     */
    public function migrate(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');

        $out = "=== MIGRATIONS MIGRATE ===\n\n";
        try {
            $migrations = new Migrations();
            $ok = $migrations->migrate(['connection' => 'default']);
            $out .= $ok ? "OK - wszystkie migracje uruchomione\n\n" : "Migracje uruchomione (partial)\n\n";
            $status = $migrations->status(['connection' => 'default']);
            foreach ($status as $m) {
                $mark = ($m['status'] ?? '') === 'up' ? '✓' : '✗';
                $out .= sprintf("  %s  %s  %s\n",
                    $mark,
                    str_pad((string)($m['id'] ?? ''), 20),
                    (string)($m['name'] ?? '')
                );
            }
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n\n";
            $out .= $e->getTraceAsString() . "\n";
        }
        $this->set('title', 'Migracje');
        $this->set('output', $out);
        $this->render('output');
    }

    public function migrationStatus(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== MIGRATIONS STATUS ===\n\n";
        try {
            $migrations = new Migrations();
            $status = $migrations->status(['connection' => 'default']);
            $pending = 0;
            foreach ($status as $m) {
                $s = ($m['status'] ?? '');
                $mark = $s === 'up' ? '✓' : '✗';
                if ($s !== 'up') $pending++;
                $out .= sprintf("  %s  %s  %s\n",
                    $mark,
                    str_pad((string)($m['id'] ?? ''), 20),
                    (string)($m['name'] ?? '')
                );
            }
            $out .= "\nPENDING: {$pending}\n";
            if ($pending > 0) $out .= "\n→ Uruchom /crm/admin/migrate zeby dodac brakujace tabele.\n";
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n";
        }
        $this->set('title', 'Migration status');
        $this->set('output', $out);
        $this->render('output');
    }

    public function clearCache(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== CLEAR CACHE ===\n\n";
        try {
            // Cake cache
            \Cake\Cache\Cache::clearAll();
            $out .= "✓ Cake\\Cache::clearAll()\n";

            // tmp/cache/*
            foreach (['tmp/cache/models', 'tmp/cache/persistent', 'tmp/cache/views'] as $dir) {
                $path = ROOT . DS . $dir;
                if (is_dir($path)) {
                    $files = glob($path . DS . '*');
                    $count = 0;
                    foreach ($files ?: [] as $f) {
                        if (is_file($f) && basename($f) !== 'empty') {
                            @unlink($f);
                            $count++;
                        }
                    }
                    $out .= "✓ {$dir}: {$count} plikow usunieto\n";
                }
            }

            // OPcache reset
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $out .= "✓ opcache_reset()\n";
            } else {
                $out .= "⚠ opcache_reset() niedostepny\n";
            }

            // ORM schema cache
            try {
                $connection = \Cake\Datasource\ConnectionManager::get('default');
                $connection->getSchemaCollection()->getCacher()->clear();
                $out .= "✓ ORM schema cache clear\n";
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n";
        }
        $this->set('title', 'Cache clear');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/poll-emails - uruchom crm_email_poll (bez dry)
     */
    public function pollEmails(): void
    {
        $this->runCommand('crm_email_poll');
    }

    /**
     * GET /crm/cron/{command}?token=X - HTTP webhook dla cronu bez auth.
     * Token = Configure Crm.cronToken (ustaw w app_local.php).
     * Whitelist tych samych commands co runCron().
     *
     * Cyberfolks Cron Jobs example (co 5 min):
     *   curl -s "https://booklio.pl/crm/cron/crm_email_poll?token=XXX"
     */
    public function cronWebhook(string $command): void
    {
        // Tylko GET - curl domyslnie GET, nie ma CSRF issue
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        // Token check
        $expectedToken = trim((string)\Cake\Core\Configure::read('Crm.cronToken'));
        $providedToken = trim((string)$this->request->getQuery('token'));
        if ($expectedToken === '') {
            $this->response = $this->response->withStatus(500)
                ->withStringBody("Configure 'Crm.cronToken' not set in app_local.php\n");
            return;
        }
        if (!hash_equals($expectedToken, $providedToken)) {
            $this->response = $this->response->withStatus(401)
                ->withStringBody("Invalid token\n");
            return;
        }

        // Whitelist commands
        $allowed = ['crm_email_poll', 'crm_workflow_run', 'crm_tasks_digest', 'crm_contract_renewals', 'alerts'];
        if (!in_array($command, $allowed, true)) {
            $this->response = $this->response->withStatus(400)
                ->withStringBody("Command not allowed. Allowed: " . implode(', ', $allowed) . "\n");
            return;
        }

        // Opcje z query
        $options = [];
        if ($this->request->getQuery('force') === '1') $options['force'] = true;
        if ($this->request->getQuery('dry') === '1') $options['dry'] = true;

        // Run command - reuse runCommand() ale zbieramy output do zwrocenia plain
        try {
            $classMap = [
                'crm_email_poll'         => \App\Command\CrmEmailPollCommand::class,
                'crm_workflow_run'       => \App\Command\CrmWorkflowRunCommand::class,
                'crm_tasks_digest'       => \App\Command\CrmTasksDigestCommand::class,
                'crm_contract_renewals'  => \App\Command\CrmContractRenewalsCommand::class,
                'alerts'                 => \App\Command\AlertsCommand::class,
            ];
            $class = $classMap[$command] ?? null;
            if (!$class || !class_exists($class)) {
                $this->response = $this->response->withStatus(500)
                    ->withStringBody("Command class not found for: {$command}\n");
                return;
            }
            $cmd = new $class();
            $stubOutput = new StubConsoleOutput();
            $stubErr = new StubConsoleOutput();
            $io = new ConsoleIo($stubOutput, $stubErr, new StubConsoleInput([]));
            $args = new Arguments([], $options, []);
            $exitCode = $cmd->execute($args, $io);
            $lines = array_merge($stubOutput->messages(), $stubErr->messages());
            $out = "=== CRON WEBHOOK: {$command} ===\n\n"
                . implode("\n", $lines) . "\n\nEXIT: {$exitCode}\n";
            $this->response = $this->response->withType('text/plain')->withStatus(200)
                ->withStringBody($out);
        } catch (\Throwable $e) {
            $this->response = $this->response->withStatus(500)
                ->withStringBody("EXCEPTION: " . $e->getMessage() . "\n");
        }
    }

    /**
     * POST /crm/admin/run-cron/{name}?force=1&dry=1
     */
    public function runCron(string $name): void
    {
        $allowed = ['crm_email_poll', 'crm_workflow_run', 'crm_tasks_digest', 'crm_contract_renewals', 'alerts'];
        if (!in_array($name, $allowed, true)) {
            $this->Flash->error('Command niedozwolony');
            $this->redirect(['action' => 'tools']);
            return;
        }
        // FALA 16: Vision GPT z duzymi obrazkami + 8000 tokens = do 3 min per mail.
        // Zwiekszamy limit web-requestu zeby cron dokonczyl.
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        // Zbieramy opcje z query string
        $options = [];
        if ($this->request->getQuery('force') === '1') $options['force'] = true;
        if ($this->request->getQuery('dry') === '1')   $options['dry'] = true;
        $this->runCommand($name, $options);
    }

    private function runCommand(string $commandName, array $options = []): void
    {
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== CRON: bin/cake {$commandName} ===\n\n";

        try {
            $classMap = [
                'crm_email_poll'         => \App\Command\CrmEmailPollCommand::class,
                'crm_workflow_run'       => \App\Command\CrmWorkflowRunCommand::class,
                'crm_tasks_digest'       => \App\Command\CrmTasksDigestCommand::class,
                'crm_contract_renewals'  => \App\Command\CrmContractRenewalsCommand::class,
                'alerts'                 => \App\Command\AlertsCommand::class,
            ];
            $class = $classMap[$commandName] ?? null;
            if (!$class || !class_exists($class)) {
                throw new \RuntimeException("Command class nie istnieje: {$commandName}");
            }

            $command = new $class();
            $stubOutput = new StubConsoleOutput();
            $stubErr = new StubConsoleOutput();
            $io = new ConsoleIo($stubOutput, $stubErr, new StubConsoleInput([]));

            // Zbuduj ConsoleOptionParser + Arguments
            $parser = new ConsoleOptionParser($commandName);
            if (method_exists($command, 'buildOptionParser')) {
                $refl = new \ReflectionClass($command);
                $method = $refl->getMethod('buildOptionParser');
                $method->setAccessible(true);
                $parser = $method->invoke($command, $parser);
            }
            // Zbuduj Arguments z opcjami (np. force, dry)
            $args = new Arguments([], $options, []);

            $exitCode = $command->execute($args, $io);
            $lines = array_merge($stubOutput->messages(), $stubErr->messages());
            $out .= implode("\n", $lines);
            $out .= "\n\nEXIT CODE: {$exitCode}\n";
        } catch (\Throwable $e) {
            $out .= "❌ EXCEPTION: " . $e->getMessage() . "\n\n";
            $out .= $e->getTraceAsString() . "\n";
        }

        $this->set('title', "Cron: {$commandName}");
        $this->set('output', $out);
        $this->render('output');
    }
}

<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', __('CRM - pomoc i FAQ'));

// Definicja sekcji z modułami — każda ma id, tytul, ikone, url, role, opis, jak_uzyc, przyklady, wskazowki
$modules = [
    // 1. LEADY - PODSTAWY
    ['group' => __('Podstawy'), 'items' => [
        [
            'id' => 'lista',
            'icon' => 'ri-list-check',
            'color' => '#2563eb',
            'title' => __('Lista leadów'),
            'url' => '/crm',
            'desc' => __('Tabelaryczny widok wszystkich leadów (jak Excel klienta). Kolumny: nazwa firmy, kraj, miasto, kod pocztowy, osoba kontaktowa, telefon, e-mail, branża, tabor, etap pipeline, skuteczność, wartość, opiekun, ostatni kontakt, notatka.'),
            'features' => [
                __('Filtry: etap pipeline (nowy / kontakt / zapytanie / oferta / zlecenie / utracony / zdyskwalifikowany), branża, kraj, tabor, tylko moje, tylko wolne (bez opiekuna), search po nazwie/NIP/mieście/e-mail.'),
                __('Sortowanie kolumn: kliknięcie w nagłówek (asc/desc). Zabezpieczenie whitelistą pól przed SQL injection.'),
                __('Zapisane widoki (saved views) w localStorage — nazwana lista filtrów, klik = przywraca query string, X = usuwa.'),
                __('Bulk actions: checkbox w wierszu + sticky bar u góry — zmiana etapu / przypisanie handlowca / usunięcie masowe (max 500 rekordów).'),
                __('Avatary opiekunów (zdjęcie profilowe lub inicjały) w kolumnie „Opiekun" z tooltipem imię+nazwisko.'),
                __('Badge K·Z·O·Zl (Kontakt/Zapytanie/Oferta/Zlecenie) — jak w Excelu klienta, raz osiągnięty etap zostaje na zawsze.'),
            ],
            'howto' => __('Wpisz w URL /crm lub kliknij „CRM Leady → Lista" w menu bocznym. Użyj pola „Szukaj" do filtrowania w locie. Kliknij nazwę firmy aby otworzyć detal leada.'),
        ],
        [
            'id' => 'kanban',
            'icon' => 'ri-layout-column-line',
            'color' => '#7c3aed',
            'title' => __('Kanban (pipeline sprzedażowy)'),
            'url' => '/crm/kanban',
            'desc' => __('Tablica typu Trello z 6 kolumnami: NOWY → KONTAKT → ZAPYTANIE → OFERTA → ZLECENIE, plus UTRACONY. Każdy lead to karta którą można przeciągnąć między kolumnami (drag & drop) aby zmienić etap pipeline.'),
            'features' => [
                __('Drag & drop kart (SortableJS 1.15) — przenoszenie leada między kolumnami automatycznie loguje zdarzenie „stage_change" w timeline aktywności.'),
                __('Kolorowe etykiety (labels) na karcie — do 3 pokazane, kolejne jako „+N". Zarządzanie w /crm/etykiety.'),
                __('Avatary opiekunów z tooltipem — brak awatara oznacza „wolny" lead.'),
                __('Filtr „Tylko wolne" — pokazuje karty bez przypisanego opiekuna (do rozdania w zespole).'),
                __('Klik w kartę otwiera modal peek (iframe pełnego widoku /crm/view/{id}?embed=1) — zero duplikacji, zawsze aktualne dane.'),
                __('Inline label creator w modalu — dodanie nowej etykiety bez przechodzenia na osobną stronę.'),
                __('Auto-preset skuteczności przy zmianie etapu: nowy 10% / kontakt 25% / zapytanie 50% / oferta 75% / zlecenie 100% / utracony 0%.'),
                __('Sidebar auto-collapse — menu boczne zwija się automatycznie na tym widoku dla większej przestrzeni na tablicę.'),
            ],
            'howto' => __('/crm/kanban. Przeciągnij kartę myszą (chwyt za dowolne miejsce) do docelowej kolumny. Klik w kartę → modal ze wszystkimi danymi i akcjami.'),
        ],
        [
            'id' => 'detal-leada',
            'icon' => 'ri-user-star-line',
            'color' => '#0891b2',
            'title' => __('Widok szczegółów leada'),
            'url' => '/crm/view/{id}',
            'desc' => __('Pełny detal leada: dane firmy (NIP, adres), kontakt (osoba, telefon, e-mail, LinkedIn), pipeline (etap, skuteczność, wartość szacowana), notatka wewnętrzna, następna akcja z terminem, timeline aktywności, załączniki, etykiety, przypięte kontrakty.'),
            'features' => [
                __('Stepper etapów u góry — wizualnie widać na jakim etapie jest lead i ile dni już tam siedzi.'),
                __('Panel „Następna akcja" — data + opis (np. „zadzwoń w piątek 09:00").'),
                __('Timeline aktywności — chronologiczna lista wydarzeń: rozmowa telefoniczna, e-mail (in/out), spotkanie, notatka, task, zmiana etapu, przypisanie, wysłana oferta, zdobyta/utracona sprzedaż, quote_request wykryty przez AI.'),
                __('Formularz dodawania aktywności — typ + temat + treść + data + czas trwania (dla rozmów/spotkań) + termin (dla tasków).'),
                __('@mentions w treści aktywności — wpisz @email lub @login żeby oznaczyć innego użytkownika (dostaje wpis w „Wspomniano mnie" i notyfikację push).'),
                __('Przyciski akcji: Edytuj, Usuń, Konwertuj → Kontrahent, Utwórz zlecenie, Utwórz ofertę, Archiwizuj.'),
                __('Widget „Wykryte zlecenia" (FALA 15) — jeśli w e-mailu wpłynęła lista zapytań ofertowych, GPT je wyodrębnia; button „Utwórz wszystkie zlecenia" tworzy masowo w /zlecenia.'),
                __('Sekcja załączników — upload plików, PDF podglądy inline, download.'),
                __('Sekcja etykiet — multi-select Trello-style, można też utworzyć nową bez wychodzenia z widoku.'),
            ],
            'howto' => __('Kliknij nazwę firmy w liście /crm, w karcie Kanban, lub wpisz /crm/view/{uuid} bezpośrednio.'),
        ],
        [
            'id' => 'dodaj-edytuj',
            'icon' => 'ri-user-add-line',
            'color' => '#10b981',
            'title' => __('Dodaj / Edytuj leada'),
            'url' => '/crm/dodaj',
            'desc' => __('Formularz w 4 sekcjach: (1) Firma — nazwa, NIP, adres; (2) Kontakt — osoba, stanowisko, telefon, e-mail, LinkedIn URL, preferowany kanał; (3) Pipeline — etap, skuteczność (auto-preset lub ręcznie), wartość szacowana; (4) Notatka + follow-up (data i opis następnej akcji).'),
            'features' => [
                __('GUS-lookup po NIP — button obok pola NIP wywołuje istniejący endpoint /contractors/gus-lookup i auto-uzupełnia nazwę, adres, status VAT (aktywny/zwolniony).'),
                __('KRS-lookup — pobiera dane z KRS (członkowie zarządu, kapitał zakładowy, forma prawna) i dodaje do notatki.'),
                __('LinkedIn search — button „Znajdź na LinkedIn" otwiera Google z zapytaniem site:linkedin.com/in „Nazwa Firmy".'),
                __('On-blur dedup check — po wyjściu z pola NIP sprawdza czy nie istnieje już lead z tym NIP w firmie; pokazuje warning + link do istniejącego.'),
                __('Multi-select rodzajów taboru (tabor: Frigo/Tautliner/Gabaryt/Mega/Tandem/Cysterna itp.) i pojedyncza branża.'),
                __('Preferowany kanał kontaktu — phone / email / meeting / any.'),
                __('Publiczna widoczność źródła — kanał pozyskania: manual / import_csv / website (public form) / recommendation / cold_call.'),
            ],
            'howto' => __('/crm/dodaj. Wpisz NIP → klik „GUS" → auto-uzupełnienie. Ustaw etap i skuteczność. Zapisz.'),
        ],
    ]],

    // 2. DASHBOARDY
    ['group' => __('Dashboardy i raporty'), 'items' => [
        [
            'id' => 'dashboard-kpi',
            'icon' => 'ri-dashboard-line',
            'color' => '#f59e0b',
            'title' => __('Dashboard KPI (handlowy)'),
            'url' => '/crm/dashboard',
            'desc' => __('Osobisty dashboard handlowca — 4 kafelki KPI + pipeline funnel + wykresy aktywności + ranking + źródła leadów. Domyślnie filtr 90 dni (opcje 30/90/180/365).'),
            'features' => [
                __('4 kafelki KPI: liczba aktywnych leadów, wartość pipeline PLN, konwersja %, wygrane zlecenia.'),
                __('Pipeline funnel — poziomy pasek per etap z liczbą leadów i sumą wartości.'),
                __('Wykres liniowy (Chart.js CDN 4.4.0) aktywności per dzień — słupki z kolorami per typ (call/email/meeting/note).'),
                __('Ranking handlowców — leady, pipeline, wygrane, utracone, konwersja %; sortowanie po dowolnej kolumnie.'),
                __('Wykres kołowy źródeł nowych leadów (manual / CSV / website / cold_call / recommendation).'),
                __('Filtr okresu w URL (?days=30|90|180|365) — link do skopiowania.'),
            ],
            'howto' => __('Menu boczne → CRM Leady → Dashboard KPI. Zmień okres w rozwijanej liście u góry.'),
        ],
        [
            'id' => 'manager-dashboard',
            'icon' => 'ri-briefcase-4-line',
            'color' => '#dc2626',
            'title' => __('Manager Dashboard (executive)'),
            'url' => '/crm/manager',
            'desc' => __('Widok managerski z widokiem całego zespołu, alarmy o zaniedbanych leadach, dumpingu cenowym, przeterminowanych taskach. Dedykowany dla ról spedycja_manager / sales_manager.'),
            'features' => [
                __('Overview zespołu — kto ma ile leadów, kto ma najwięcej przeterminowanych tasków, kto najczęściej wygrywa/przegrywa.'),
                __('Alert „Zaniedbane leady" — bez aktywności >14 dni + stage aktywny.'),
                __('Alert „Cenowe anomalie" — oferty poniżej mediany historycznej klienta (dumping).'),
                __('Śledzenie SLA follow-upów — leady z next_action_at przeterminowanym.'),
            ],
            'howto' => __('/crm/manager. Widoczny tylko dla managerów w sidebarze.'),
        ],
        [
            'id' => 'moje-zadania',
            'icon' => 'ri-task-line',
            'color' => '#0ea5e9',
            'title' => __('Moje zadania'),
            'url' => '/crm/zadania',
            'desc' => __('Osobista lista tasków i follow-upów pobrana z timeline aktywności (activity_type=task, is_done=false) plus z pola leads.next_action_at.'),
            'features' => [
                __('3 KPI kafelki: Przeterminowane (czerwone), Dzisiaj (żółte), Nadchodzące (zielone).'),
                __('Toggle „Moje / Zespół" — sales_manager może zobaczyć taski wszystkich handlowców.'),
                __('Filtr okna czasowego: 7 / 14 / 30 / 60 / 90 dni.'),
                __('Dwie sekcje: (1) Task activities z terminami, (2) Follow-upy z leads.next_action_at.'),
                __('AJAX „Oznacz jako wykonane" — jednym klikiem bez odświeżenia strony.'),
                __('Codzienny e-mail digest wysyłany cronem crm_tasks_digest (patrz Cron jobs poniżej).'),
            ],
            'howto' => __('Menu boczne → Moje zadania. Klik „Oznacz jako wykonane" po zakończeniu.'),
        ],
    ]],

    // 3. KOMUNIKACJA
    ['group' => __('Komunikacja i inbox'), 'items' => [
        [
            'id' => 'gmail-imap',
            'icon' => 'ri-mail-lock-line',
            'color' => '#059669',
            'title' => __('Skrzynki e-mail (Gmail OAuth + IMAP)'),
            'url' => '/crm/email-accounts',
            'desc' => __('Integracja skrzynek pocztowych z leadami. Dwie metody: (1) Gmail OAuth 2.0 z zakresami gmail.readonly + gmail.send (rekomendowane), (2) IMAP klasyczny z zaszyfrowanym hasłem (AES-256-CBC z Security.salt).'),
            'features' => [
                __('Gmail OAuth — button „Połącz z Google" prowadzi przez consent screen, po autoryzacji zapisuje refresh_token; token access jest odnawiany automatycznie.'),
                __('Auto-pobieranie e-maili co 5 minut przez cron crm_email_poll (Gmail: incremental sync po historyId; IMAP: UID > last_seen_uid).'),
                __('Auto-matching wiadomości do leadów po e-mailu nadawcy (LOWER(from) = LOWER(Leads.email)) — dopisuje activity_type=email_in do timeline.'),
                __('Wykrywanie zapytań ofertowych (FALA 15) — GPT-4o mini analizuje treść e-maila; jeśli to zapytanie o wycenę → tworzy osobny wpis quote_request z rozpisanymi shipments (from/to/waga/palety/data).'),
                __('Reply przez Gmail API (FALA 19) — button „Odpowiedz przez Gmail" w timeline; wysyła w kontekście wątku (In-Reply-To + References).'),
                __('Test połączenia — button „Test" wywołuje imap_open lub Gmail API i pokazuje status + liczbę wiadomości.'),
                __('Bezpieczeństwo IMAP — hasło szyfrowane AES-256-CBC z random IV; ukryte w encji ($_hidden); tylko manager/user ma dostęp.'),
                __('Kolejka pilnych maili (/crm/pilne) — GPT klasyfikuje sentiment + priority; osobna lista dla wiadomości oznaczonych jako pilne.'),
            ],
            'howto' => __('/crm/email-accounts → „Dodaj skrzynkę" → wybierz Gmail (OAuth) lub IMAP. Dla Gmail: klik „Połącz z Google", zaakceptuj uprawnienia. Dla IMAP: host/port/SSL/login/hasło + test.'),
        ],
        [
            'id' => 'wspomniano-mnie',
            'icon' => 'ri-at-line',
            'color' => '#2563eb',
            'title' => __('Wspomniano mnie (@mentions)'),
            'url' => '/crm/wspomniano-mnie',
            'desc' => __('Skrzynka wspomnień — lista aktywności w których ktoś oznaczył Cię przez @twoj_email lub @twoj_login. Regex parser wyciąga wszystkie mentiony z treści komentarzy.'),
            'features' => [
                __('Regex parser: /@([a-zA-Z0-9._-]+(?:@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?)/ — matchuje po Users.email lub prefixie e-maila.'),
                __('Badge nieodczytanych w sidebarze — cyfra przy pozycji „Wspomniano mnie".'),
                __('Auto mark-as-seen — otwarcie strony zaznacza wszystkie widoczne wspomnienia jako przeczytane (znika badge).'),
                __('Toggle „Tylko nieprzeczytane / Pokaż wszystkie" (?all=1).'),
                __('Dedup per (activity_id, mentioned_user_id) — jeden e-mail = jedno wspomnienie na osobę.'),
                __('Klik w wspomnienie prowadzi do konkretnej aktywności w timeline leada (kotwica #act-{id}).'),
            ],
            'howto' => __('W dowolnej aktywności/komentarzu wpisz @email.pracownika lub @login. Osoba dostanie wpis w tej skrzynce + notyfikację push (jeśli włączyła).'),
        ],
        [
            'id' => 'push-notifications',
            'icon' => 'ri-notification-badge-line',
            'color' => '#7c3aed',
            'title' => __('Notyfikacje push (przeglądarka)'),
            'url' => 'N/A',
            'desc' => __('Notyfikacje web push (Web Push API + VAPID keys) — przeglądarka pokazuje toast nawet gdy karta CRM jest zamknięta. Wymaga jednorazowej zgody użytkownika.'),
            'features' => [
                __('Service Worker (webroot/sw-crm-push.js) obsługuje event push → showNotification, notificationclick → focus lub otwarcie okna.'),
                __('Rejestracja: strona wywołuje /crm/push/subscribe z endpoint + p256dh_key + auth_key; klucze VAPID publiczny z Configure.'),
                __('Auto-init: crm-push-init.js pobiera /crm/push/status, rejestruje SW, prosi o consent, subskrybuje.'),
                __('Wysyłka: server-side PushSender używa web-push protokołu do wywołania endpointu Firebase/Mozilla push service.'),
                __('Trigger events: nowe @mention, przypisanie leada, task overdue, przekroczenie SLA follow-upu.'),
                __('Idempotent — dedup po (user_id, endpoint), unsubscribe usuwa rekord z crm_push_subscriptions.'),
            ],
            'howto' => __('Przy pierwszej wizycie na /crm przeglądarka spyta o zgodę na notyfikacje — kliknij „Zezwól". Wyłączenie: w ustawieniach przeglądarki lub przez /crm/push/unsubscribe.'),
        ],
        [
            'id' => 'pilne-maile',
            'icon' => 'ri-alarm-warning-line',
            'color' => '#dc2626',
            'title' => __('Kolejka pilnych e-maili'),
            'url' => '/crm/pilne',
            'desc' => __('Lista wiadomości które GPT sklasyfikował jako pilne (reklamacja / krótki termin / zerwanie kontraktu / zapytanie priority=high). Alternatywa dla ręcznego skanowania skrzynki.'),
            'features' => [
                __('Kolejka posortowana wg wykrytego priorytetu (urgent → high → normal).'),
                __('Podgląd wątku z leadem, kontekst historii.'),
                __('Szybka odpowiedź przez Gmail (jeśli konto połączone).'),
            ],
            'howto' => __('/crm/pilne. Klasyfikacja robi się automatycznie przy imporcie e-maili przez crm_email_poll.'),
        ],
    ]],

    // 4. AUTOMATYZACJA
    ['group' => __('Automatyzacja i AI'), 'items' => [
        [
            'id' => 'workflows',
            'icon' => 'ri-flow-chart',
            'color' => '#0891b2',
            'title' => __('Workflows (automatyzacje)'),
            'url' => '/crm/workflows',
            'desc' => __('Silnik automatyzacji: gdy spełni się warunek (trigger) i filtr (condition) → wykonaj akcję. Uruchamiane cronem crm_workflow_run co 10 minut.'),
            'features' => [
                __('3 triggery: stage_no_activity_days (lead siedzi w etapie X > N dni bez aktywności), lead_age_days (lead istnieje > N dni), task_overdue (task przeterminowany).'),
                __('Condition filters (JSON): branch_type, country, probability_min-max, value_min.'),
                __('3 akcje: create_task (auto-utwórz task z due_days), change_stage (przenieś do wybranego etapu z whitelistą), send_email (wyślij template do assigned_user).'),
                __('Template variables: {{company}}, {{stage}}, {{value}}, {{contact_person}}.'),
                __('Cooldown per (workflow_id, lead_id) — domyślnie 24h — chroni przed spamem.'),
                __('Historia uruchomień w crm_workflow_runs — audyt kiedy co odpaliło.'),
            ],
            'howto' => __('/crm/workflows → „Dodaj workflow" → wybierz trigger + condition + akcja. Aktywuj przełącznikiem is_active. Cron obsłuży resztę.'),
        ],
        [
            'id' => 'ai-quote',
            'icon' => 'ri-robot-line',
            'color' => '#7c3aed',
            'title' => __('AI: ekstrakcja zapytań + auto-wycena'),
            'url' => 'auto',
            'desc' => __('Cały pipeline sprzedaży wspierany przez GPT-4o mini: (1) wykrywanie zapytań ofertowych w e-mailach, (2) sugerowanie ceny na bazie historii, (3) generowanie PDF oferty, (4) wysyłka do klienta jednym klikiem.'),
            'features' => [
                __('FALA 15 — ekstrakcja quote_request: GPT rozpoznaje maile z listą zleceń (nawet po niemiecku, w tabeli Excel wklejonej do body). Wyciąga customer_order_ref, from/to (kraj+kod+miasto+firma), load_date+time, unload_date+time, weight_kg, pallets+type, cargo_type, vehicle_type, notes.'),
                __('Heurystyka pre-GPT — min. 2 sygnały z listy 20+ słów kluczy (liefern/transport/zlecenie/wycen/palet/kg/…), inaczej pomija bez OpenAI (oszczędność tokenów).'),
                __('Widget „Wykryte zlecenia (N)" w timeline leada — zielony panel z tabelą 11-kolumnową; button „Utwórz wszystkie zlecenia w bazie" masowo tworzy /zlecenia (auto-numer M-NNNN/MM/YYYY, prefill buyer z leada).'),
                __('FALA 20 — auto-quote workflow: /crm/suggest-price analizuje historię 12mies stawek klienta na tej trasie + rynkową medianę, zwraca sugerowaną cenę + confidence.'),
                __('Alert dumpingu — jeśli sugerowana < 90% mediany → czerwony banner „To dumping — przemyśl".'),
                __('PDF oferty (quotePdf) — DomPdf renderuje ładny dokument z logo firmy, tabelą shipmentów, sumaryczną ceną; template templates/Leads/quote_pdf.php.'),
                __('sendQuoteJson — wysyła PDF jako załącznik + wpis w timeline leada activity_type=offer_sent + zmiana stage na „offer".'),
                __('AI Draft Response — GPT generuje szkic odpowiedzi na e-mail w kontekście historii leada; edytowalny przed wysłaniem.'),
                __('AI Summarize — jednym klikiem streszczenie długiej rozmowy telefonicznej lub długiego wątku e-mail.'),
            ],
            'howto' => __('Odbiera się automatycznie — po każdym e-mailu wchodzącym crm_email_poll sprawdza czy to zapytanie; jeśli tak, ląduje jako quote_request w timeline. Reszta workflow z przyciskiem.'),
        ],
        [
            'id' => 'auto-thanks',
            'icon' => 'ri-mail-check-line',
            'color' => '#10b981',
            'title' => __('Auto-thanks e-mail (przy wygranej)'),
            'url' => 'auto',
            'desc' => __('Gdy handlowiec przenosi lead na etap „order" (zlecenie zdobyte), system automatycznie wysyła do klienta e-mail z podziękowaniem — template gradient Booklio, dane opiekuna, tekst po polsku.'),
            'features' => [
                __('Idempotent — sprawdza _previous_stage w LeadsTable::afterSave(), wysyła tylko na faktyczne przejście do „order", nie na każdy save.'),
                __('Konfigurowalny przełącznik Configure Crm.autoThanksEnabled (default true).'),
                __('Autolog w timeline aktywności — activity_type=email_out z payload {auto: true, trigger: "stage_change_to_order"}.'),
                __('Best-effort try/catch — awaria wysyłki nie może wywrócić save() leada.'),
                __('Test mode Configure Crm.testEmailOverride — podczas testowania wszystkie automaty idą na jeden adres kontrolny (nie do prawdziwego klienta).'),
            ],
            'howto' => __('Włączone domyślnie. Aby wyłączyć — Configure::write(\'Crm.autoThanksEnabled\', false) w config/app_local.php.'),
        ],
        [
            'id' => 'auto-assign',
            'icon' => 'ri-shuffle-line',
            'color' => '#f59e0b',
            'title' => __('Auto-assign round-robin'),
            'url' => 'auto',
            'desc' => __('Nowe leady bez opiekuna są automatycznie przydzielane handlowcom według round-robin (kto ostatnio dostał, ten czeka na kolejną turę).'),
            'features' => [
                __('Wywoływany przy tworzeniu leada z publicznego formularza /kontakt/{companyId} lub przez import CSV.'),
                __('Whitelist ról: user + sales_manager + spedycja_manager.'),
                __('Zapamiętuje ostatni assignment per firma — nie faworyzuje nikogo.'),
                __('Autolog assignment w timeline aktywności.'),
            ],
            'howto' => __('Włączone domyślnie dla /kontakt. Ręcznie: bulk action „Przypisz" w liście leadów.'),
        ],
    ]],

    // 5. INTEGRACJE
    ['group' => __('Integracje ze spedycją'), 'items' => [
        [
            'id' => 'kontrakty-ramowe',
            'icon' => 'ri-file-text-line',
            'color' => '#0891b2',
            'title' => __('Kontrakty ramowe'),
            'url' => '/kontrakty',
            'desc' => __('Rejestr kontraktów ramowych z klientami — jeżeli klient ma podpisany kontrakt na trasę A→B po cenie X, system automatycznie sugeruje tę cenę przy nowym zleceniu i zlicza wolumen.'),
            'features' => [
                __('Priorytet matchingu: NIP + miasto LIKE → NIP + kraj → NIP bez trasy.'),
                __('Volumen tracking — committed_volume vs used_volume, progress bar.'),
                __('Alert wygasających kontraktów — 30 dni przed valid_to.'),
                __('AJAX widget w /zlecenia/dodaj — po zmianie NIP+miast (debounce 400ms) pokazuje zielony alert „Znaleziono kontrakt: nazwa · trasa · cena · vol%".'),
                __('Button „Zastosuj cenę" → wypełnia netto/currency/vat_rate/payment_days w formularzu zlecenia.'),
                __('Auto-inkrement used_volume po zapisie zlecenia (hidden field _from_contract_id).'),
                __('Cron crm_contract_renewals — codzienny e-mail HTML z alertem 30 dni przed wygaśnięciem.'),
            ],
            'howto' => __('/kontrakty → „Dodaj kontrakt" → NIP klienta + trasa + cena + wolumen + valid_from/to. Przy tworzeniu zlecenia system sam podpowie.'),
        ],
        [
            'id' => 'lead-do-zlecenia',
            'icon' => 'ri-truck-line',
            'color' => '#059669',
            'title' => __('Konwersja lead → zlecenie transportowe'),
            'url' => 'button w widoku leada',
            'desc' => __('W detalu leada button „Utwórz zlecenie" prowadzi do /zlecenia/dodaj?lead_id=<uuid> z pre-fillem buyer_* z leada + notatka. Po zapisie zlecenia lead automatycznie przechodzi na stage „order".'),
            'features' => [
                __('Multi-tenant guard — sprawdza company_id leada vs sesja użytkownika.'),
                __('Session Crm.orderFromLeadId triggeruje autolog order_won w timeline leada z symbolem zlecenia + kwotą.'),
                __('Prefill: nazwa firmy, NIP, kraj, kod pocztowy, miasto, ulica, osoba kontaktowa, telefon, e-mail leada.'),
                __('Podpowiedź kontraktu ramowego (jeśli istnieje) w widget zlecenia.'),
            ],
            'howto' => __('W /crm/view/{id} → button „Utwórz zlecenie" → wypełnij szczegóły transportu → zapisz.'),
        ],
        [
            'id' => 'lead-do-oferty',
            'icon' => 'ri-file-list-3-line',
            'color' => '#7c3aed',
            'title' => __('Konwersja lead → oferta cenowa'),
            'url' => 'button w widoku leada',
            'desc' => __('W detalu leada modal „Utwórz ofertę" → tworzy route_plan bez trasy + route_offer status=draft z access_token. Lead automatycznie przechodzi na stage „offer" i zapisuje wartość.'),
            'features' => [
                __('Autolog offer_sent w timeline.'),
                __('Redirect do /oferty/view/{id} — dokończenie oferty (dodanie trasy, ceny, wysyłka do klienta).'),
                __('Access token publiczny — klient dostaje link /oferty/wglad/{token} bez logowania (accept / reject).'),
            ],
            'howto' => __('W /crm/view/{id} → button „Utwórz ofertę" → modal z podstawowymi danymi → potem dokończ w /oferty.'),
        ],
        [
            'id' => 'lead-do-kontrahenta',
            'icon' => 'ri-user-follow-line',
            'color' => '#2563eb',
            'title' => __('Konwersja lead → kontrahent (Contractors)'),
            'url' => 'button w widoku leada',
            'desc' => __('Gdy lead staje się stałym klientem, button „Konwertuj → Kontrahent" tworzy rekord w /contractors z danymi leada i podpina leads.contractor_id (guard: tylko jeśli jeszcze nie podpięty).'),
            'features' => [
                __('Kopiuje wszystkie dane firmy (nazwa, NIP, adres, telefon, e-mail).'),
                __('Ustawia leads.contractor_id — od teraz lead „wie" że ma powiązanego kontrahenta.'),
                __('W liście kontrahentów pojawia się badge „Z CRM" lub link powrotny do leada.'),
            ],
            'howto' => __('W /crm/view/{id} → button „Konwertuj → Kontrahent". Jednorazowa operacja.'),
        ],
    ]],

    // 6. NARZĘDZIA
    ['group' => __('Narzędzia i utrzymanie'), 'items' => [
        [
            'id' => 'import-csv',
            'icon' => 'ri-file-excel-2-line',
            'color' => '#10b981',
            'title' => __('Import z pliku CSV'),
            'url' => '/crm/import-csv',
            'desc' => __('Masowy import leadów z Excela — flow upload → preview → confirm bulk insert. Rozpoznawane nagłówki po polsku i angielsku (case-insensitive).'),
            'features' => [
                __('Rozpoznawane kolumny (PL/EN): nazwa/name, kraj/country, kod/postal, miasto/city, kontakt/contact, tel/phone, email, gałąź/branch, etap/stage, skuteczność/probability, wartość/value, note.'),
                __('Auto-detect separator (,;\t) i kodowanie (UTF-8/Win-1250) + BOM strip.'),
                __('Dedup po NIP — pomija leady już istniejące.'),
                __('Szablon CSV do pobrania — /crm/import-csv/szablon.csv z przykładowym rekordem.'),
                __('Preview 10 pierwszych wierszy + tabela błędów walidacji przed importem.'),
                __('Auto-assign round-robin dla nowych leadów.'),
            ],
            'howto' => __('/crm/import-csv → wybierz plik → sprawdź preview → „Zaimportuj". Szablon: link „Pobierz szablon" u góry.'),
        ],
        [
            'id' => 'duplikaty',
            'icon' => 'ri-file-copy-line',
            'color' => '#f59e0b',
            'title' => __('Wykrywanie duplikatów + merge'),
            'url' => '/crm/duplikaty',
            'desc' => __('Silnik wykrywania duplikatów po: NIP / e-mail / telefon (>=6 cyfr) / znormalizowana nazwa (bez sp. z o.o./sa/gmbh) / fuzzy Levenshtein ≤ 2 (dla zbiorów < 500).'),
            'features' => [
                __('Lista par 2-kolumnowa z chipami powodów („Ten sam NIP", „Ta sama nazwa", „Ten sam e-mail").'),
                __('Merge review — tabela pole-po-polu z radio wyboru A|B, auto-scalanie stage (max ordering), probability (max), flag_* (OR), last_contacted_at (latest).'),
                __('Merge w transakcji: UPDATE Activities lead_id B→A + log merge activity + DELETE Lead B.'),
                __('Anti data-loss — usunięcie tylko po scaleniu wszystkich powiązań.'),
            ],
            'howto' => __('/crm/duplikaty → wybierz parę → „Scal" → w merge review wybierz które pole zostawić z leada A/B → potwierdź.'),
        ],
        [
            'id' => 'etykiety',
            'icon' => 'ri-price-tag-3-line',
            'color' => '#7c3aed',
            'title' => __('Etykiety (Trello labels)'),
            'url' => '/crm/etykiety',
            'desc' => __('Katalog własnych etykiet per firma (np. „ADR", „Pilne", „Duży kontrakt", „VIP"). Każdy lead może mieć wiele etykiet (many-to-many).'),
            'features' => [
                __('Kolorowa paleta hex (#RRGGBB) — dowolny kolor.'),
                __('Sortowanie sort_order — kolejność w dropdownach i na kartach Kanban.'),
                __('Inline creator w widoku leada i w modalu peek Kanban — nowa etykieta bez wychodzenia z widoku.'),
                __('Multi-select w formularzu leada.'),
                __('Widoczne na kartach Kanban (do 3 badge, +N dla reszty).'),
            ],
            'howto' => __('/crm/etykiety → „Dodaj etykietę" → nazwa + kolor. W widoku leada użyj multi-select w sekcji etykiet.'),
        ],
        [
            'id' => 'branze-tabor',
            'icon' => 'ri-price-tag-2-line',
            'color' => '#0891b2',
            'title' => __('Branże i rodzaje taboru'),
            'url' => '/crm/branze , /crm/rodzaje-taboru',
            'desc' => __('Katalogi konfigurowalne per firma — branża klienta (co przewozi) i rodzaj taboru (potrzebny sprzęt).'),
            'features' => [
                __('Branże (single) — np. Automotive, FMCG, Chemia, Metal, Farmacja, Rolnictwo.'),
                __('Rodzaje taboru (multi) — np. Frigo (chłodnia), Tautliner (plandeka), Gabaryt, Mega, Tandem, Cysterna, Kontener, ADR, Silos.'),
                __('CRUD per firma — każda firma ma swoją listę.'),
                __('Widoczne w filtrach listy /crm i formularzu dodawania leada.'),
            ],
            'howto' => __('/crm/branze i /crm/rodzaje-taboru → CRUD.'),
        ],
        [
            'id' => 'formularz-publiczny',
            'icon' => 'ri-global-line',
            'color' => '#0ea5e9',
            'title' => __('Publiczny formularz kontaktowy'),
            'url' => '/kontakt/{companyId}',
            'desc' => __('Formularz na stronę internetową — potencjalny klient wypełnia dane, ląduje jako lead w CRM (source=website, stage=new). Bez logowania.'),
            'features' => [
                __('Anti-spam 3-warstwowy: (1) honeypot field website_url off-screen (bots wypełniają → silent success), (2) timestamp min 3s od otwarcia formularza, (3) rate-limit 5/h per IP przez session.'),
                __('Inline CSS z Booklio green gradient — pasuje do landing page.'),
                __('Auto-log email_in activity z IP + User-Agent.'),
                __('Best-effort e-mail do admina firmy (pierwszy user by created asc).'),
                __('Auto-assign round-robin dla przypisania handlowca.'),
                __('URL zawiera UUID firmy — każda firma ma własny link do wklejenia na www.'),
            ],
            'howto' => __('Wklej link /kontakt/{twoje_company_id} na stronę www. Nowe leady wpadają do /crm z badge „Ze strony".'),
        ],
        [
            'id' => 'crm-admin',
            'icon' => 'ri-tools-line',
            'color' => '#dc2626',
            'title' => __('Narzędzia administracyjne'),
            'url' => '/crm/admin/tools',
            'desc' => __('Panel super-usera: uruchomienie migracji, czyszczenie cache, git pull, ręczne triggery cronów, diagnostyka leadów, reset historyId Gmail. Dostęp tylko dla ról manager/admin.'),
            'features' => [
                __('Migrate — uruchomienie oczekujących migracji Phinx.'),
                __('Clear cache — reset schema cache + model cache.'),
                __('Run cron manually — wywołanie crm_email_poll / crm_tasks_digest / crm_workflow_run / crm_contract_renewals / alerts z UI.'),
                __('Git pull — pobranie ostatnich zmian z repo (tylko na produkcji).'),
                __('File check — sprawdza czy istnieją klucze migracji i konfigi.'),
                __('Nuclear clear — całkowity reset cache + kompilacja route'),
                __('Find lead — diagnostyka: EXACT + LIKE + bin2hex compare (pokazuje różnice bajtowe/spacje/wielkość liter + ostatnie 10 msg w crm_email_messages).'),
                __('Reset Gmail history — zeruje oauth_history_id + last_synced_at → next poll pobierze inbox z ostatnich 30 dni max 100 msg.'),
                __('Analyze last email — dump ostatniej wiadomości z GPT rozpoznaniem.'),
                __('Cron webhook — /crm/cron/{name} z bypassAuth dla external cron providerów.'),
            ],
            'howto' => __('/crm/admin/tools. Ostrożnie z „Nuclear clear" i „Reset Gmail history".'),
        ],
    ]],

    // 7. CRONY
    ['group' => __('Cron jobs (background)'), 'items' => [
        [
            'id' => 'cron-email-poll',
            'icon' => 'ri-mail-download-line',
            'color' => '#059669',
            'title' => __('crm_email_poll — pobieranie e-maili'),
            'url' => 'bin/cake crm_email_poll',
            'desc' => __('Co 5 minut: dla każdej aktywnej skrzynki (Gmail/IMAP) pobiera nowe wiadomości od ostatniej synchronizacji.'),
            'features' => [
                __('Gmail: incremental sync po historyId (Gmail API v1) → tylko nowe wiadomości.'),
                __('IMAP: imap_search UID SINCE (last_seen_uid+1):* → dedup + sort + limit --max=100.'),
                __('Auto-matching do leadów po e-mailu nadawcy.'),
                __('Best-effort quote_request extraction (heurystyka + GPT).'),
                __('Update crm_email_accounts: last_seen_uid, counters, last_synced_at, last_error.'),
                __('Opcje: --account=<id>, --force, --max=N.'),
            ],
            'howto' => __('Crontab: */5 * * * * cd /home/... && php bin/cake.php crm_email_poll. Ręcznie: /crm/admin/tools → „Run cron: crm_email_poll".'),
        ],
        [
            'id' => 'cron-tasks-digest',
            'icon' => 'ri-mail-send-line',
            'color' => '#0ea5e9',
            'title' => __('crm_tasks_digest — codzienny digest zadań'),
            'url' => 'bin/cake crm_tasks_digest',
            'desc' => __('Codzienny e-mail HTML per handlowiec z listą zadań: przeterminowane / dzisiaj / nadchodzące / zaniedbane leady (bez aktywności >N dni).'),
            'features' => [
                __('Template gradient header + 4 sekcje color-coded (czerwone/żółte/zielone/szare).'),
                __('Buttony „Otwórz lead" per pozycja.'),
                __('CTA „Otwórz wszystkie zadania" → /crm/zadania.'),
                __('Opcje: --dry (preview bez wysyłki), --days=7 (okno tasków), --company=<uuid>, --user=<uuid>, --stale-days=14.'),
                __('Best-effort — awaria wysyłki loguje się, nie wywraca crona.'),
            ],
            'howto' => __('Crontab: 0 7 * * * php bin/cake.php crm_tasks_digest. Test: --dry --user=<uuid>.'),
        ],
        [
            'id' => 'cron-workflows',
            'icon' => 'ri-flow-chart',
            'color' => '#0891b2',
            'title' => __('crm_workflow_run — silnik automatyzacji'),
            'url' => 'bin/cake crm_workflow_run',
            'desc' => __('Co 10 minut: iteruje po aktywnych workflows, sprawdza trigger + condition, uruchamia akcję jeśli warunki spełnione.'),
            'features' => [
                __('Cooldown per (workflow_id, lead_id) — 24h.'),
                __('Historia w crm_workflow_runs.'),
                __('Idempotent.'),
            ],
            'howto' => __('Crontab: */10 * * * * php bin/cake.php crm_workflow_run.'),
        ],
        [
            'id' => 'cron-renewals',
            'icon' => 'ri-alarm-warning-line',
            'color' => '#f59e0b',
            'title' => __('crm_contract_renewals — alerty wygasających kontraktów'),
            'url' => 'bin/cake crm_contract_renewals',
            'desc' => __('Codzienny e-mail z listą kontraktów wygasających w ciągu N dni (default 60). Reuse CrmContractsTable::findExpiringSoon().'),
            'features' => [
                __('Opcje: --days=60, --dry, --company=<uuid>.'),
                __('Template gradient orange, color-coded badges (czerwone <14d, żółte <30d, zielone).'),
                __('Test mode Configure Crm.testEmailOverride.'),
            ],
            'howto' => __('Crontab: 0 8 * * * php bin/cake.php crm_contract_renewals.'),
        ],
    ]],
];
?>
<style>
.faq-hero { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 32px 24px; border-radius: 12px; margin-bottom: 24px; }
.faq-hero h1 { color: #fff; margin: 0 0 8px; font-size: 28px; font-weight: 700; }
.faq-hero p { color: rgba(255,255,255,.9); margin: 0; font-size: 15px; max-width: 780px; }
.faq-toc { background: #f8fafc; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
.faq-toc h6 { color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: .05em; margin: 0 0 12px; }
.faq-toc-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 6px; }
.faq-toc-list a { color: #475569; text-decoration: none; padding: 4px 8px; border-radius: 4px; font-size: 13px; transition: background .12s; }
.faq-toc-list a:hover { background: #e2e8f0; color: #0f172a; }
.faq-group { margin-bottom: 32px; }
.faq-group-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .08em; padding: 10px 0; border-bottom: 2px solid #e2e8f0; margin-bottom: 16px; }
.faq-card { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px; background: #fff; overflow: hidden; transition: box-shadow .12s; }
.faq-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.faq-header { display: flex; align-items: center; gap: 14px; padding: 16px 20px; cursor: pointer; user-select: none; }
.faq-icon { flex: 0 0 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; }
.faq-title-wrap { flex: 1; min-width: 0; }
.faq-title { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.3; margin: 0; }
.faq-url { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: #64748b; margin-top: 2px; }
.faq-caret { color: #94a3b8; transition: transform .2s; }
.faq-card.open .faq-caret { transform: rotate(90deg); }
.faq-body { padding: 0 20px 20px 74px; display: none; }
.faq-card.open .faq-body { display: block; }
.faq-desc { color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 14px; }
.faq-section-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin: 16px 0 8px; }
.faq-features { list-style: none; padding: 0; margin: 0; }
.faq-features li { position: relative; padding: 5px 0 5px 22px; color: #334155; font-size: 13px; line-height: 1.5; }
.faq-features li::before { content: '✓'; position: absolute; left: 0; top: 5px; color: #10b981; font-weight: 700; }
.faq-howto { background: #eff6ff; border-left: 3px solid #2563eb; padding: 10px 14px; border-radius: 4px; font-size: 13px; color: #1e40af; margin-top: 12px; }
.faq-howto strong { color: #1e3a8a; }
@media (max-width: 640px) {
    .faq-body { padding: 0 16px 16px 16px; }
    .faq-header { padding: 12px 14px; gap: 10px; }
}
</style>

<div class="faq-hero">
    <h1><i class="ri-question-line"></i> <?= __('CRM — pełna dokumentacja modułów') ?></h1>
    <p><?= __('Kompletny przewodnik po funkcjach CRM. Kliknij w moduł żeby rozwinąć szczegóły. Znajdziesz tu opis co robi każda funkcja, jak jej użyć i przykłady zastosowań.') ?></p>
</div>

<div class="faq-toc">
    <h6><i class="ri-list-unordered"></i> <?= __('Spis treści') ?></h6>
    <div class="faq-toc-list">
        <?php foreach ($modules as $group): ?>
            <?php foreach ($group['items'] as $m): ?>
                <a href="#faq-<?= h($m['id']) ?>" data-target="<?= h($m['id']) ?>">
                    <i class="<?= h($m['icon']) ?>" style="color: <?= h($m['color']) ?>;"></i>
                    <?= h($m['title']) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ($modules as $group): ?>
    <div class="faq-group">
        <div class="faq-group-title"><?= h($group['group']) ?></div>
        <?php foreach ($group['items'] as $m): ?>
            <div class="faq-card" id="faq-<?= h($m['id']) ?>">
                <div class="faq-header" data-toggle="faq">
                    <div class="faq-icon" style="background: <?= h($m['color']) ?>;">
                        <i class="<?= h($m['icon']) ?>"></i>
                    </div>
                    <div class="faq-title-wrap">
                        <div class="faq-title"><?= h($m['title']) ?></div>
                        <div class="faq-url"><?= h($m['url']) ?></div>
                    </div>
                    <i class="ri-arrow-right-s-line faq-caret" style="font-size: 20px;"></i>
                </div>
                <div class="faq-body">
                    <p class="faq-desc"><?= h($m['desc']) ?></p>

                    <?php if (!empty($m['features'])): ?>
                        <div class="faq-section-title"><?= __('Funkcje') ?></div>
                        <ul class="faq-features">
                            <?php foreach ($m['features'] as $f): ?>
                                <li><?= h($f) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($m['howto'])): ?>
                        <div class="faq-howto">
                            <strong><?= __('Jak używać:') ?></strong> <?= h($m['howto']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<div class="text-center text-muted small mt-4 py-3" style="border-top: 1px solid #e2e8f0;">
    <?= __('Nie znajdujesz odpowiedzi? Napisz do administratora systemu.') ?>
    &nbsp;·&nbsp;
    <a href="<?= $this->Url->build(['action' => 'index']) ?>"><?= __('Wróć do CRM') ?></a>
</div>

<script>
(function() {
    // Toggle accordion
    document.querySelectorAll('.faq-header').forEach(function(h) {
        h.addEventListener('click', function() {
            h.parentElement.classList.toggle('open');
        });
    });
    // TOC anchor scroll — auto-open target card
    document.querySelectorAll('.faq-toc-list a').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var id = a.getAttribute('data-target');
            var card = document.getElementById('faq-' + id);
            if (card) {
                card.classList.add('open');
                setTimeout(function() {
                    card.scrollIntoView({behavior: 'smooth', block: 'start'});
                }, 50);
            }
        });
    });
    // Deep-link — jeśli URL ma #faq-xxx, otwórz i przewiń
    if (window.location.hash && window.location.hash.startsWith('#faq-')) {
        var card = document.querySelector(window.location.hash);
        if (card) {
            card.classList.add('open');
            setTimeout(function() {
                card.scrollIntoView({behavior: 'smooth', block: 'start'});
            }, 100);
        }
    }
})();
</script>

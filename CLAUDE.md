# CLAUDE.md — Instrukcja dla Claude Code

Plik źródła prawdy dla asystenta AI pracującego w tym projekcie.
Czytaj go na początku każdej sesji.

---

## Projekt

**faktury24** — system fakturowania dla firmy partnersc (booklio.pl).
Aplikacja webowa w **CakePHP 5**, PHP 8.x, MySQL, Bootstrap 5, jQuery.
Środowisko produkcyjne: `/home/jjgroup1srv/domains/booklio.pl/public_html/`

---

## Zasady pracy — OBOWIĄZKOWE

### 1. Nie naprawiaj tego co działa
Jeśli funkcja działa poprawnie — nie ruszaj jej, nie refaktoryzuj, nie "ulepszaj".
Każda zmiana poza zleconym zakresem wymaga wyraźnej zgody użytkownika.

### 2. Potwierdź założenie przed implementacją
Przed rozpoczęciem nietrywialnej pracy **przedstaw plan** i poczekaj na potwierdzenie.
Wyjątek: proste bugi z oczywistą przyczyną i jedynym możliwym rozwiązaniem.

### 3. Po zakończeniu pracy — raport + commit + push
Gdy skończysz zadanie:
1. Napisz użytkownikowi **co zrobiłeś** — krótka lista zmian z nazwami plików
2. Zrób `git commit` z opisowym komunikatem (po polsku lub angielsku, konwencja: `feat:` / `fix:` / `refactor:`)
3. Zrób `git push`
4. Jeśli zmiana jest znacząca — zaktualizuj ten plik (`CLAUDE.md`) w sekcji Changelog

### 4. KRYTYCZNE — Nigdy nie halucynuj. To system księgowy.
**To produkcyjny system fakturowo-księgowy** (faktury VAT, KSeF, JPK, rozliczenia
bankowe). Błędne dane (numery faktur, kwoty, NIP-y, kody bankowe, nazwy kolumn DB,
opisy operacji, formaty plików MT940) mogą prowadzić do realnych strat finansowych,
błędów w JPK/KSeF i niezgodności z urzędem skarbowym.

**Nie wymyślaj. Nie zgaduj. Nie aproksymuj.**
- Jeśli nie znasz dokładnej nazwy kolumny → otwórz migrację.
- Jeśli nie znasz dokładnej składni API/biblioteki → przeczytaj kod / docs.
- Jeśli nie wiesz jak działa funkcja w innej części projektu → przeczytaj ją.
- Jeśli nie wiesz jakiego formatu używa bank w MT940 → poproś usera o dokument
  źródłowy lub fragment pliku. NIE wymyślaj kodów typu "D50" / "A61" na
  podstawie ogólnej wiedzy o SWIFT, jeśli faktyczny bank używa innego formatu.
- Jeśli nie wiesz jaki jest format numeru faktury w danym roku → pytasz.
- Jeśli dokumentacja jest niejednoznaczna → pytasz.
- Jeśli AI/OpenAI ma generować dane wrażliwe (numery faktur, kwoty) → zawsze
  daj twardą walidację regex/whitelist po stronie serwera. Halucynacja LLM-a
  nie może trafić do bazy lub UI bez weryfikacji.

**Lepiej zapytać użytkownika niż wyprodukować niepoprawny kod.**
Użytkownik woli odpowiedzieć na jedno pytanie niż naprawiać 5 błędów,
których byś nie zrobił gdybyś zapytał.

**Wyjątki gdzie zgadywanie jest OK:** czysto kosmetyczne preferencje
(odstęp, kolor border, czy `ms-2` czy `me-2`) — tu możesz wybrać i poprawić.

Przed modyfikacją zawsze **przeczytaj aktualny stan pliku** (Read tool).
Nie opieraj się wyłącznie na pamięci z poprzednich sesji — kod mógł się zmienić.

### 4a. Pola w migracjach — ZAWSZE sprawdzaj zanim użyjesz
Przed użyciem dowolnej kolumny w `where`, `select`, `contain.fields`, lub przy dostępie
do propertiesa encji (`$entity->kolumna`) — **otwórz odpowiednią migrację** w
`config/Migrations/` i potwierdź dokładną nazwę kolumny i typ.
Nie zgaduj że pole nazywa się `amount` / `date` / `name` — pola zwykle mają
specyficzne nazwy: `allocated_amount`, `value_date`, `party_name` itp.

**Workflow przy nowych migracjach:**
1. Po utworzeniu migracji w `config/Migrations/YYYYMMDDHHMMSS_*.php` →
   **od razu zaktualizuj** sekcję "Baza danych — kluczowe tabele" w tym CLAUDE.md,
   dopisując nową tabelę i jej **kompletny opis kolumn** (nazwa, typ, znaczenie).
2. Cel: kolejne sesje Claude czytają CLAUDE.md i mają pełną mapę bez konieczności
   otwierania każdej migracji. To zapobiega błędom typu `$alloc->amount` zamiast
   `$alloc->allocated_amount`.

### 5. Wszystko 2-językowo: PL + EN (i18n)
Każdy widoczny dla użytkownika tekst musi przechodzić przez `__('...')` (CakePHP I18n).
Dotyczy: nagłówków, etykiet, przycisków, placeholderów, opcji `<select>`, komunikatów
flash, tytułów `<title>`, atrybutów `title`/`aria-label`, treści error-pages, e-maili.

**Workflow:**
1. W szablonie/kontrolerze: `<?= __('Polski tekst') ?>` — klucz to polska wersja
2. Po dodaniu/zmianie kluczy: dopisz tłumaczenia EN w `resources/locales/en/default.po`
   ```po
   msgid "Polski tekst"
   msgstr "English translation"
   ```
3. Po zmianach `.po` — bez kompilacji, CakePHP czyta na bieżąco
4. Sprawdzenie: przełącz w portalu na EN (`/portal/lang/en`) i potwierdź że wszystko tłumaczone

**Wyjątki (można hardcode PL):**
- Nazwy własne (Booklio TMS, KSeF, FA(3), NIP, MPP)
- Logi techniczne (Cake\Log\Log::error itp. — to tylko dla devów)
- Komentarze w kodzie
- Nazwy kolumn w DB

**Locale jest ustawiane w `AppController::beforeFilter`** z sesji (`Config.locale`) —
domyślnie `pl`. Klient portalu przełącza `/portal/lang/{pl|en}`.

---

## Stack technologiczny

| Warstwa | Technologia |
|---------|-------------|
| Framework | CakePHP 5 |
| PHP | 8.x |
| Baza danych | MySQL (InnoDB, utf8mb3) |
| Frontend | Bootstrap 5, jQuery, Remixicon |
| Lightbox | GLightbox |
| Autentykacja | CakeDC/Users + CakePHP Authorization |
| CSRF | CsrfProtectionMiddleware (cookie-based) |
| PDF | własny renderer |
| XML/KSeF | ręcznie budowany XML (FA(3)) |

---

## Struktura aplikacji

```
src/
  Controller/         — kontrolery (jeden na moduł)
  Model/Table/        — ORM tabele
  Model/Entity/       — encje
  Service/            — serwisy (BankMatchingService, Mt940ParserService, ...)
templates/            — widoki PHP (.php), jeden katalog na kontroler
config/
  routes.php          — WSZYSTKIE trasy (nie używamy fallbacks dla nowych endpointów)
  Migrations/         — migracje bazy danych (phinx)
  permissions.php     — uprawnienia per akcja
webroot/              — pliki statyczne
```

---

## Moduły systemu

### Faktury (`InvoicesController`)
Główny i największy kontroler. Typy faktur:

| Typ | Opis |
|-----|------|
| `vat` | Faktura VAT krajowa |
| `novat` | Faktura bez VAT |
| `currency` | Faktura walutowa (EUR i inne) |
| `proforma` | Proforma |
| `advance` | Zaliczkowa |
| `final` | Końcowa |
| `correction` | Korekta (do każdego typu) |
| `margin` | Marża |
| `rental` | Najem |
| `oss` | OSS |
| `internal` | Wewnętrzna |
| `internalEvidence` | Dowód wewnętrzny |

Kluczowe akcje:
- `index` — lista faktur z filtrami
- `add`, `edit` — dodawanie/edycja (osobne widoki per typ)
- `addCorrection`, `addCorrectCurrency` — korekty
- `buildXml` / `buildLinesXml` / `buildVatSummaryXml` — generowanie XML dla KSeF FA(3)
- `checkCurrencyRates` — admin: faktury z błędnymi kursami NBP
- `checkRatesBatch` — batch check kursów z listy faktur

### KSeF (`KsefAuthorizationsController`)
Integracja z Krajowym Systemem e-Faktur.
Format: FA(3). Korekty: typ `KOR`, pola `StanPrzed`/`StanPo`.

### Rozliczenia (`ReconciliationsController`)
Lista faktur z informacją o stanie płatności i powiązanych przelewach.
- `index` — lista z filtrami (status, data, typ, szukaj)
- `addPayment` — ręczna wpłata → tworzy `invoice_payments`
- `deletePayment` — usunięcie wpłaty
- `bankTransactions(invoiceId)` — AJAX: zwraca JSON z przelewami kontrahenta

### Wyciągi bankowe (`BankTransactionsController` + `BankMatchingService`)
Import plików MT940 + automatyczne dopasowanie do faktur.

**Metody dopasowania** (kolejność priorytetów):
1. `/INV/numer` w tytule + kwota → confidence 95 → auto `matched`
2. `/IDC/NIP` w tytule + kwota → confidence 75 → `proposed`
3. IBAN kontrahenta + kwota → confidence 65 → `proposed`

**Statusy transakcji:** `unmatched` / `proposed` / `matched` / `ignored`

**Próg auto-potwierdzenia:** confidence ≥ 90 → od razu `matched` + tworzy `invoice_payment`

### Zlecenia Speed (`SpeedOrdersController`)
Synchronizacja z zewnętrznym systemem Speed ERP (zlecenia transportowe) **+ ręczne tworzenie zleceń**.
Powiązane z fakturami przez `invoice_id`.
Obsługuje załączniki CMR (upload + GLightbox).

**Źródło zlecenia** — kolumna `source`:
- `speed` — zlecenie zsynchronizowane z Speed ERP (read-only, sync by je nadpisał)
- `manual` — zlecenie utworzone ręcznie przez operatora w `/zlecenia/dodaj`

**Ręczne zlecenia** (`source='manual'`):
- CRUD: `GET/POST /zlecenia/dodaj`, `GET/POST /zlecenia/edytuj/{id}`, `POST /zlecenia/usun/{id}`
- AJAX autocomplete: `/zlecenia/drivers.json`, `/zlecenia/vehicles.json` (do datalist w formularzu)
- Numer generowany automatycznie w formacie **`M-NNNN/MM/YYYY`** — auto-increment per (company_nip, rok, mc)
- `speed_id` = NULL, `manual_seq` = kolejny numer
- Edycja zablokowana dla `source='speed'` (BadRequestException)
- Usunięcie zablokowane gdy jest podpięta faktura (`invoice_id` lub M:N w `AllInvoices`)
- Sync ze Speed zawsze ustawia `source='speed'` explicit
- W liście: badge **M** (fioletowy) przy symbolu manualnych + filtr **Źródło: Wszystkie/Speed/Ręczne**
- W widoku szczegółów manualnego: przyciski **Edytuj** i **Usuń** (guard invoice_id IS NULL)
- Auto-calc VAT/brutto z netto + stawki (23/8/5/0/np/zw/oo) — server-side w `prepareManualOrderData()`
- Autocomplete kontrahentów via istniejący `POST /contractors/search`

### Kontrahenci (`ContractorsController`)
Lookup GUS po NIP, import z Speed ERP, zarządzanie danymi.
`contractor_bank_accounts` — konta IBAN kontrahentów (używane do matchowania przelewów).

### Faktury kosztowe (`CostInvoicesController`)
Import faktur kosztowych z KSeF. Osobna tabela `cost_invoices`.

### CRM Leady (`LeadsController`)
Zarządzanie potencjalnymi klientami (leadami) — pipeline sprzedażowy dla działu handlowego spedycji.
Powstał na bazie ręcznego arkusza Excel klienta (kolumny: firma / kraj / kod / miasto / ulica /
kontakt / tel / email / gałąź / note / checkboxy Kontakt/Zapytanie/Oferta/Zlecenie / skuteczność %).

**Widoki:**
- `/crm` — lista tabelaryczna z filtrami (etap/gałąź/kraj/moje) + KPI pipeline (5 kafelków)
- `/crm/kanban` — Kanban 5 kolumn z drag&drop (SortableJS 1.15 CDN → AJAX `POST /crm/kanban/przenies/{id}`)
- `/crm/view/{id}` — detal leada + stepper etapów + panel „Następna akcja" + dane firmy/kontakt/notatka + timeline aktywności z formularzem dodawania
- `/crm/dodaj` / `/crm/edytuj/{id}` — formularz (4 sekcje: firma / kontakt / pipeline+wartość / notatka+follow-up)

**Etapy pipeline** (kolumna `stage`): `new` → `contact` → `inquiry` → `offer` → `order` (+ `lost`).

**Auto-preset skuteczności** (`probability`) przy zmianie stage w `LeadsTable::beforeSave()`:
`new=10 / contact=25 / inquiry=50 / offer=75 / order=100 / lost=0`. User może nadpisać ręcznie.

**Auto-flagi** (`flag_contact/inquiry/offer/order`) — raz osiągnięty etap → flaga zostaje na zawsze
(dla widoku K·Z·O·Zl w tabeli, jak w Excelu klienta).

**Konwersja lead → contractor**: `POST /crm/konwertuj/{id}` — tworzy rekord w `contractors`
z danymi leada i podpina `leads.contractor_id`. Guard: tylko jeśli jeszcze nie podpięty.

**Timeline aktywności** (`lead_activities`) — każdy rekord to jedno zdarzenie (rozmowa/email/spotkanie/
notatka/task/zmiana etapu). Typy: `phone_call / email_out / email_in / meeting / note / task / file /
stage_change / assignment / offer_sent / order_won / order_lost`. Formularz w widoku szczegółów:
typ + temat + treść + happened_at + duration_min + due_at (dla tasków). Zmiana etapu przez Kanban
auto-loguje `stage_change` przez helper `LeadActivitiesTable::logSystem()` (best-effort try/catch).

**Sidebar**: menu „CRM Leady" z 3 pozycjami (Lista / Pipeline / Nowy lead).
**Permissions**: `asystent_spedytora | mlodszy_spedytor | spedycja_manager | sales_manager | user`
(pełny CRUD + timeline + konwersja).

---

## Baza danych — kluczowe tabele

| Tabela | Opis |
|--------|------|
| `invoices` | Faktury (wszystkie typy) |
| `invoice_contractors` | Dane nabywcy snapshot per faktura |
| `invoice_company_details` | Dane sprzedawcy snapshot per faktura |
| `invoice_contents` | Pozycje faktury |
| `invoice_vat_contents` | Podsumowanie VAT per faktura |
| `invoice_payments` | Wpłaty (tworzone ręcznie lub z banku) |
| `invoice_series` | Serie numeracji |
| `bank_transactions` | Transakcje z wyciągów MT940 |
| `bank_statement_imports` | Nagłówki importów MT940 |
| `contractors` | Kontrahenci |
| `contractor_bank_accounts` | Konta bankowe kontrahentów |
| `speed_orders` | Zlecenia z Speed ERP |
| `cost_invoices` | Faktury kosztowe |
| `leads` | CRM leady (potencjalni klienci) |
| `lead_activities` | Timeline aktywności per lead |

### Ważne kolumny `invoices`

| Kolumna | Opis |
|---------|------|
| `type` | Typ faktury (patrz tabela typów) |
| `workflow_status` | `draft` / `issued` / `sent` |
| `paymentstate` | `unpaid` / `partial` / `paid` |
| `paymentdate` | Termin płatności |
| `alreadypaid` | Suma wpłat |
| `remaining` | Pozostało do zapłaty |
| `currency` | Waluta (PLN, EUR, ...) |
| `exchange_rate` | Kurs NBP |
| `fullnumber` | Pełny numer faktury (np. FV/2026/04/001) |
| `correction_id` | FK do faktury korygowanej |

### Pełne kolumny `bank_transactions`
Migracja: `20260416210000_CreateBankTransactions.php` + `20260416220000_AddMatchingFieldsToBankTransactions.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | FK firma |
| `import_id` | uuid | FK do `bank_statement_imports` |
| `account_number` | string(50) | nasze konto |
| `value_date` | date | **data waluty** (środki dostępne, używana do matchingu kwotowego) |
| `booking_date` | date | data księgowania (kiedy bank zapisał) — bywa różna od `value_date` |
| `direction` | string(2) | `C` = credit (wpłata u nas), `D` = debit (wypłata) |
| `amount` | decimal(15,2) | kwota transakcji (zawsze dodatnia) |
| `currency` | string(3) | PLN/EUR/USD itd. |
| `transaction_code` | string(10) | kod z MT940 |
| `customer_reference` | string(100) | referencja klienta |
| `bank_reference` | string(100) | referencja banku |
| `description` | text | dane bankowe |
| `party_name` | string(255) | **nazwa kontrahenta** (nadawca/odbiorca) |
| `party_account` | string(60) | IBAN kontrahenta |
| `title` | text | **tytuł przelewu** (zwykle zawiera /INV/ /IDC/ /VAT/) |
| `import_hash` | string(64) | unique, deduplikacja importu |
| `invoice_id` | uuid | FK do powiązanej faktury (legacy, nullable) |
| `is_matched` | bool | legacy flag |
| `match_status` | string(12) | `unmatched` / `proposed` / `matched` / `ignored` |
| `match_confidence` | int(3) | 0–100 |
| `match_reason` | string(100) | opis powodu dopasowania |
| `parsed_inv` | string(100) | nr faktury wyciągnięty z `/INV/` |
| `parsed_nip` | string(15) | NIP z `/IDC/` |
| `parsed_vat` | decimal(15,2) | kwota VAT z `/VAT/` |
| `tx_type_code` | string(10) | kod transakcji z `:86:` |

### Pełne kolumny `bank_transaction_allocations`
Migracja: `20260423100000_CreateBankTransactionAllocations.php`
Łącznik N:M: jeden przelew może być rozdzielony na wiele faktur (i odwrotnie).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | FK firma |
| `bank_transaction_id` | uuid | FK do `bank_transactions` (CASCADE) |
| `invoice_id` | uuid | FK do faktury systemowej (nullable) |
| `legacy_invoice_id` | char(36) | dla faktur z legacy DB (nullable) |
| `invoice_payment_id` | uuid | FK do `invoice_payments` (SET_NULL) — wpłata utworzona z tej alokacji |
| **`allocated_amount`** | **decimal(12,4)** | **kwota przypisana** — UWAGA: NIE `amount`! |
| `currency` | char(3) | waluta alokacji (zwykle ta sama co bank_transactions.currency) |
| `allocation_type` | string(10) | `gross` (default) / `net` / `vat` |
| `note` | string(255) | komentarz |

### Pełne kolumny `invoice_payments`
| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `invoice_id` | uuid | FK do `invoices` |
| `payment_date` | date | data wpłaty |
| `amount` | decimal | kwota wpłaty (UWAGA: tu jest `amount`, w przeciwieństwie do alokacji) |
| `currency` | string | waluta wpłaty |
| `payment_method` | string | metoda płatności |
| `payment_type` | string | `manual` / `bank` itp. |
| `bank_transaction_allocation_id` | uuid | back-link do alokacji (nullable) |

> Wpisy w `$_accessible` encji `InvoicePayment`: `currency`, `payment_type`,
> `bank_transaction_allocation_id` (dodane manualnie — bez tego mass-assign cicho je gubi).

### Pełne kolumny `invoice_notes`
Migracja: `20260528100100_CreateInvoiceNotes.php`
Notatki, komentarze, activity log dla faktur (używane głównie przez Kanban).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | FK firma |
| `invoice_id` | uuid | FK do `invoices` (nullable jeśli legacy) |
| `legacy_invoice_id` | uuid | FK do `legacy_invoices` (nullable) |
| `user_id` | uuid | autor (NULL = system) |
| `note_type` | string(20) | `note` / `system` / `reminder` / `phone_call` / `email` |
| `body` | text | treść |
| `payload_json` | text | metadane akcji (np. action, target_column, changes) |

### Pełne kolumny `cost_invoices`
Migracje: `20260409160000_CreateCostInvoices.php` + `20260622100000_AddPaymentFieldsToCostInvoices.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | int(PK auto) | PK |
| `source` | string(10) | `ksef` / `manual` |
| `ksef_number` | string(100) UNIQUE | numer z KSeF (NULL gdy manual) |
| `invoice_number` | string(100) | nr na fakturze |
| `contractor_name` / `contractor_nip` | string | przewoźnik |
| `issue_date` | date | data wystawienia |
| `receipt_date` | date | data wpływu do nas |
| `payment_date` | date | **termin płatności** |
| `paid_at` | date | **faktyczna data zapłaty** |
| `paid_amount` | decimal(12,2) | suma wpłat (przeliczana z `cost_invoice_payments`) |
| `payment_method` | string(20) | `transfer` / `cash` / `card` / `compensation` / `other` |
| `accounting_month` | string(7) | YYYY-MM |
| `netto` / `vat` / `brutto` | decimal(12,2) | kwoty |
| `currency` | string(5) | domyślnie PLN |
| `status` | string(20) | `received` / `verified` / `paid` (stan księgowy) |
| `cost_status` | tinyint(3) | **workflow operatora 1-9** (analog z `ksef_invoice_statuses`): 1=Do potwierdzenia, 2=Oczekuje na dok., 3=Gotowa, 4=Zaakceptowana, 5=Do opłacenia, 6=Przeterminowana, 7=Odrzucona, 8=Wstrzymana, 9=Do wyjaśnienia |
| `rejection_reason` | string(512) | Powód odrzucenia (gdy cost_status=7) |
| `pdf_path` / `xml_path` | string(500) | ścieżki plików |
| `ksef_raw_json` | text | raw payload z KSeF API |
| `notes` | text | uwagi |

### Pełne kolumny `cost_invoice_payments`
Migracja: `20260622110000_CreateCostInvoicePayments.php`
Historia wpłat per faktura kosztowa. Suma jest przeliczana do `cost_invoices.paid_amount` po każdej zmianie.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `cost_invoice_id` | int | FK do `cost_invoices` (CASCADE) |
| `payment_date` | date | kiedy zapłacono |
| `amount` | decimal(12,2) | kwota wpłaty |
| `currency` | char(3) | domyślnie PLN |
| `payment_method` | string(20) | sposób |
| `payment_type` | string(10) | `manual` / `bank` |
| `bank_transaction_id` | uuid | FK do `bank_transactions` (SET_NULL) — gdy z banku |
| `user_id` | uuid | kto dodał |
| `note` | string(255) | komentarz |

### Pełne kolumny `cost_invoice_notes`
Migracja: `20260622140000_CreateCostInvoiceNotes.php`
Activity log + ręczne notatki per faktura kosztowa.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `cost_invoice_id` | int | FK do `cost_invoices` (CASCADE) |
| `company_id` | uuid | |
| `user_id` | uuid | autor (NULL = system) |
| `note_type` | string(20) | `note` / `system` / `reminder` / `phone_call` / `email` |
| `body` | text | treść |
| `payload_json` | text | metadane akcji (action, old/new values, ids) |

### Pełne kolumny `cost_invoice_lines`
Migracja: `20260622130000_CreateCostInvoiceLines.php`
Pozycje faktur kosztowych — odpowiednik `ksef_booking_items` ale per cost_invoice (nie per ksef_number, więc działa też dla manualnych).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `cost_invoice_id` | int | FK do `cost_invoices` (CASCADE) |
| `line_index` | int | kolejność |
| `line_id` | string(64) | ID z XML (FaWiersz NrWierszaFa) |
| `name` | string(500) | nazwa pozycji |
| `quantity` / `unit` / `unit_price` | decimal/str | jeśli z FA |
| `net_amount` / `vat_rate` / `vat_amount` / `gross_amount` | decimal | kwoty pozycji |
| `currency` | char(3) | jeśli inna od faktury |
| **`cost_category_id`** | uuid | FK do `cost_categories` (SET NULL) — kategoria dekretacji |
| `cost_category_name` | string(255) | snapshot nazwy kategorii |
| `note` | text | uwagi operatora |
| `source_json` | text | raw fragment FaWiersz |

### Pivot `cost_invoice_orders`
Migracja: `20260409160000_CreateCostInvoices.php`
M:N: jedna FK kosztowa → wiele zleceń, jedno zlecenie → wiele FK kosztowych.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | int(PK auto) | PK |
| `cost_invoice_id` | int | FK |
| `speed_order_id` | int | FK |

### Pełne kolumny — moduły planera operacyjnego (Fala 1)

**Cel:** rozszerzenie planera tras z narzędzia jednorazowego na operacyjny pipeline
zlecenia (plan → oferta → zlecenie → przypisanie zasobów → brief → wykonanie → faktura).

Migracje: `20260710100000..20260710100400` (5 tabel).

#### `route_plans`
Nazwane plany trasy z wersjonowaniem i statusem. Rozszerzenie `route_searches` (historia)
— tutaj zapisujemy plan z pełnym P&L który potem konwertujemy w ofertę i zlecenie.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | multi-tenant |
| `author_user_id` | char(36) | kto planował |
| `contractor_id` | char(36) | FK do `contractors` (nullable — plan spekulacyjny) |
| `name` | string(200) | nazwa robocza |
| `status` | string(20) | `draft`/`offered`/`accepted`/`rejected`/`converted`/`archived` |
| `waypoints_json` | text | pełne waypoints trasy w JSON |
| `pickup_json` | text | podjazd (leg pusty przed startem) |
| `return_load_json` | text | sugerowany ładunek powrotny |
| `distance_km` / `duration_min` / `co2_kg` | decimal/int | z HERE |
| `calc_cost_json` | text | snapshot P&L (paliwo/myto/kierowca/amortyzacja) |
| `suggested_price` / `accepted_price` | decimal(12,2) | cena z historii / zaakceptowana |
| `currency` | char(3) | domyślnie PLN |
| `speed_order_id` | int | FK do `speed_orders` gdy plan → zlecenie |
| `parent_plan_id` | char(36) | wersjonowanie |
| `valid_until` | date | ważność oferty |
| `vehicle_combination_id`/`vehicle_id`/`trailer_id`/`driver_id` | | wybrany zestaw |
| `planned_start_at`/`planned_end_at` | datetime | okno realizacji |

CRUD: (nie ma osobnego UI w Fali 1 — używane głównie z RoutePlannerController "Zapisz plan")

#### `route_plan_legs`
Legi trasy z rolami (`pickup`/`loaded`/`positioning`/`return_load`/`home`).
`is_billed=true` → wliczone do przychodu.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `route_plan_id` | char(36) | FK |
| `leg_index` | int | kolejność (unique per plan) |
| `leg_type` | string(20) | jak wyżej |
| `from_json`/`to_json` | text | punkty |
| `distance_km` / `duration_min` | decimal/int | |
| `is_billed` | bool | domyślnie true |
| `country_code` | char(2) | dla kabotażu |
| `toll_cost` / `fuel_cost` / `currency` | decimal/char | |
| `planned_start_at`/`planned_end_at` | datetime | |

#### `driver_schedules`
Grafik kierowców — bloki dostępności/niedostępności. Kluczowa dla availability lookup.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | |
| `driver_id` | char(36) | FK do `drivers` |
| `starts_at`/`ends_at` | datetime | |
| `entry_type` | string(20) | `assignment`/`time_off`/`sickness`/`training`/`blocked` |
| `speed_order_id` | int | FK gdy assignment do konkretnego zlecenia |
| `route_plan_id` | char(36) | FK gdy jeszcze etap planowania |
| `vehicle_id`/`trailer_id` | char(36) | blokada zestawu razem z kierowcą |
| `created_by_user_id` | char(36) | kto zaplanował |

CRUD: `/grafik-kierowcow`. AJAX:
- `/grafik-kierowcow/wolni.json?start=ISO&end=ISO&override_time_off=1` — lista wolnych kierowców w oknie
- `/grafik-kierowcow/dla-kierowcy/{id}.json?from=&to=` — grafik konkretnego kierowcy

**Helper w `DriverSchedulesTable::findAvailableInWindow($companyId, $start, $end, $allowOverrideTimeOff)`.**

#### `vehicle_schedules`
Analogicznie dla pojazdów/naczep. Wypełnione DOKŁADNIE jedno z (`vehicle_id`, `trailer_id`).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | |
| `vehicle_id` **XOR** `trailer_id` | char(36) | tylko jedno wypełnione |
| `starts_at`/`ends_at` | datetime | |
| `entry_type` | string(20) | `assignment`/`maintenance`/`inspection`/`unavailable` |
| `speed_order_id` / `route_plan_id` | | |

CRUD: `/grafik-pojazdow`. AJAX:
- `/grafik-pojazdow/wolne.json?start=&end=`
- `/grafik-naczep/wolne.json?start=&end=`

**Helper: `VehicleSchedulesTable::findAvailableVehiclesInWindow()` + `findAvailableTrailersInWindow()`.**

#### `operational_events` (event bus)
Centralny append-only log wszystkich zdarzeń na planach/ofertach/zleceniach/grafikach.
Materiał do analytics dashboard, audytu, historii operacyjnej. **Nie modyfikujemy nigdy.**

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | |
| `entity_type` | string(40) | `route_plan`/`route_offer`/`speed_order`/`driver_schedule`/`vehicle_schedule`/`driver_brief`/`trip_event`/`invoice` |
| `entity_id` | string(40) | ID rekordu (string — wspiera int i uuid) |
| `event_name` | string(40) | `created`/`updated`/`status_changed`/`sent`/`viewed`/`accepted`/`rejected`/`deleted`/`assigned`/… |
| `user_id` / `impersonated_by_user_id` | char(36) | kto (null = system/cron) |
| `payload_json` | text | metadane (before/after, kontekst) |
| `ip_address` / `user_agent` | | |
| `created` | datetime | brak `modified` — append-only |

**Convenience helper:** `OperationalEventsTable::log($companyId, $entityType, $entityId, $eventName, $userId, $payload, $context)`.
Metoda best-effort — try/catch wewnątrz, żeby log nie mógł popsuć głównego flow.

**Zasada:** każdy CRUD w module operacyjnym → dopisz do `operational_events`, nawet
jeśli dashboardu jeszcze nie ma — zbieramy dane pod przyszłe agregacje.

### Pełne kolumny — moduły planera operacyjnego (Fala 2)

**Cel:** workflow ofertowy — od planu przez ofertę cenową do akceptacji klienta.
+ AJAX historii stawek widoczny w planerze.

Migracja: `20260710110000`.

#### `route_offers`
Oferty cenowe wysyłane klientowi (bez logowania — dostęp przez token).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | |
| `route_plan_id` | char(36) | FK do `route_plans` — każda oferta ma plan |
| `contractor_id` | char(36) | FK (nullable) |
| `sent_to_email` / `sent_to_name` | | |
| `subject` / `message_body` | | temat + treść wiadomości |
| `price` | decimal(12,2) | kwota netto |
| `currency` / `vat_rate` / `payment_days` | | |
| `access_token` | string(64) | unique — URL do akceptacji bez logowania |
| `valid_until` | date | |
| `status` | string(20) | `draft`/`sent`/`viewed`/`accepted`/`rejected`/`expired` |
| `sent_at` / `viewed_at` / `decided_at` | datetime | timeline |
| `decision_reason` | text | powód odrzucenia |
| `generated_speed_order_id` / `generated_invoice_id` / `pdf_path` | | dokumenty po akceptacji |

CRUD: `/oferty` (index/view/delete). Wysyłka: `POST /oferty/wyslij/{id}`.
Publiczny wgląd klienta: `GET /oferty/wglad/{token}` (bez auth) + akceptacja/odrzucenie.

Automat statusów:
- `send()` → `sent_at`, status `sent`, email HTML
- `accessByToken()` → jeśli status `sent` → auto zmiana na `viewed` + timestamp
- `accept()` → status `accepted` + `route_plan.status='accepted'` + `route_plan.accepted_price`
- `reject()` → status `rejected` + `route_plan.status='rejected'`

#### AJAX historii stawek — `RoutePlannerController::pricingHistory`

`POST /planer-tras/historia-stawek` — cascade query po własnej historii `speed_orders` + faktur.

**Dwa tryby** (`mode`):
- `client` (default gdy podano `contractor_nip`) — historia tylko dla jednego klienta
- `market` (gdy `contractor_nip` puste) — historia z całego rynku (wszyscy klienci firmy),
  **limit podniesiony do 50** rekordów, dodatkowo agregacja `by_buyer[]` (TOP 10 klientów
  z ilością zleceń, sumą PLN, średnią PLN)

**Kaskada trafień** (zwraca `match_level`):
1. **POZIOM 1** — oba miasta `LIKE` (+ klient w trybie client)
2. **POZIOM 2** — oba kraje + jedno miasto pasuje
3. **POZIOM 3** — oba kraje (dla dowolnego miasta)

Zwraca:
- `mode` — `client`/`market`
- `orders[]` — do 10 (client) / 50 (market) zleceń z `place_from_name`, `place_to_name`, `date_doc`, `symbol`, `title`, **`buyer_name`, `buyer_nip`**
- `orders[].invoice` — powiązana faktura (nr, data, kwota, waluta, `total_pln` po przelicz.)
- `stats` — count, min/max/avg/median w PLN (przelicz. po `currency_exchange`)
- `by_buyer[]` — TOP klienci (tylko w trybie market): `buyer_name`, `buyer_nip`, `count`, `sum_pln`, `avg_pln`

Filtr czasowy: ostatnie 12 miesięcy. Sortowanie: najnowsze na wierzchu.

**UI toggle** `Ten klient | Rynek` w panelu historii — market mode pokazuje dodatkową
kolumnę „Klient" w tabeli + sekcję „TOP klienci na tej trasie" ponad listą.

UI panel „**Historia stawek dla klienta na tej trasie**" pod tabelą tolls
w [templates/RoutePlanner/index.php](templates/RoutePlanner/index.php). Wywoływany
z `preparePricingHistoryPanel(points)` po kalkulacji trasy.

**Alert dumpingu:** gdy aktualna cena < 90% mediany historycznej — czerwony banner
„To dumping — przemyśl". Gdy 90-110% → zielony „Zgodne z medianą".

#### Przycisk „Wyślij ofertę" w planerze
Zielony button `#btn-send-offer` w hero. Modal `#sendOfferModal` z formularzem: nazwa,
odbiorca (email + firma + NIP), cena/waluta/VAT/termin, temat/wiadomość, ważność.
Prefill z ostatniej kalkulacji + z panelu historii (NIP).

Submit → `POST /oferty/utworz` z `plan_data` (waypoints + calc_cost + distance/duration)
→ tworzy `route_plan` na fly + `route_offer` → opcjonalnie automat `POST /oferty/wyslij`
z emailem HTML (template `templates/email/html/route_offer.php`).

Klient dostaje link `/oferty/wglad/{token}` → widok z akceptuj/odrzuć bez logowania.

### Pełne kolumny — moduły planera operacyjnego (Fala 3)

**Cel:** compliance i zarządzanie zasobami. Chronimy przed:
- jazdą bez ważnego badania technicznego / ubezpieczenia
- przekroczeniem czasu pracy kierowcy (UE 561/2006)
- planowaniem zasobu który jest niedostępny wg wzorca (weekend, ADR, noc)
- ryzykiem kabotażu / ADR / sankcji bez logu do audytu ITD

Migracje: `20260710120000..20260710120300` (4 tabele).

#### `vehicle_maintenance`
Historia serwisów, badań, ubezpieczeń per pojazd/naczepa. Kluczowa dla alertów.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | |
| `vehicle_id` **XOR** `trailer_id` | char(36) | tylko jedno |
| `maintenance_type` | string(30) | `technical_inspection`/`service`/`tacho_calibration`/`adr_cert`/`insurance`/`oc`/`ac`/`extinguisher`/`first_aid`/`other` |
| `performed_at` | date | wykonano |
| `valid_until` | date | do kiedy ważne (kluczowe dla alertów) |
| `reminder_days` | int | ile dni przed alert (domyślnie 30) |
| `cost` / `currency` / `supplier` | decimal/string | koszt + dostawca |
| `document_path` | string(500) | skan PDF/JPG |
| `cost_invoice_id` | int | FK do `cost_invoices` |
| `alert_sent_at` | datetime | idempotent — nie wysyłamy 2× |
| `is_active` | bool | false = zastąpiony nowszym |

CRUD: `/serwisy`. AJAX: `/serwisy/wygasajace.json?days=30`.
**Helpers ORM:**
- `findExpiringSoon(companyId, days)` — dla dashboardu i cron alertów
- `findMissingForDate(companyId, assetType, assetId, date, requiredTypes)` — sprawdź czy pojazd ma wszystko na X dzień (dla planera)

#### `driver_time_logs`
Dzienne wpisy czasu pracy kierowcy wg UE 561/2006 (min: 9h jazdy, 45h/tydz, 90h/2tyg).
Auto-fill `week_iso` (format `2026-W29`) z `log_date` w `beforeSave`.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `driver_id` | char(36) | |
| `log_date` | date | (unique per driver+date) |
| `week_iso` | string(8) | auto-fill z log_date |
| `driving_min` / `rest_min` / `other_work_min` / `availability_min` | int | minuty |
| `daily_rest_ok` / `weekly_rest_ok` | bool | flagi compliance |
| `extended_driving_used` | bool | użycie extension do 10h (max 2×/tydz) |
| `reduced_daily_rest_used` | bool | redukcja do 9h (max 3×/tydz) |
| `source` | string(20) | `tachograph`/`manual`/`estimated`/`import_ddd`/`import_csv` |
| `source_file_id` | string(100) | dla audytu importu |

CRUD: `/czas-pracy`. AJAX: `/czas-pracy/status/{driverId}.json?week_iso=2026-W29`.

**Stałe UE 561/2006 w Tabeli:**
`MAX_DRIVING_DAILY=540 (9h)`, `MAX_DRIVING_WEEKLY=3360 (56h)`, `MAX_DRIVING_BIWEEKLY=5400 (90h)`,
`MIN_DAILY_REST=660 (11h)`, `MIN_WEEKLY_REST=2700 (45h)`.

**Helpers:**
- `sumDrivingInWeek(driverId, weekIso)` → int (minuty)
- `hasBudgetInWeek(driverId, weekIso, additionalMinutes)` → bool
- `weeklyStatus(driverId, weekIso)` → array (used_min, remaining_min, biweekly_used, is_at_risk, is_over_limit)

#### `driver_availability`
Wzorce dostępności per kierowca × dzień tygodnia (7 rekordów per kierowca).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `driver_id` | char(36) | |
| `day_of_week` | int | 1=poniedziałek..7=niedziela (unique per driver) |
| `shift_start` / `shift_end` | time | null start = nie pracuje w ten dzień |
| `max_hours_this_day` | int | miękki limit dziennie |
| `accepts_international` / `accepts_adr` / `accepts_night` / `accepts_weekend` | bool | preferencje |

CRUD: `/dostepnosc-kierowcow` (index) + `/dostepnosc-kierowcow/{driverId}` (edycja 7 dni jednym formularzem, transakcyjny delete+insert).

#### `compliance_events`
Append-only log ostrzeżeń compliance (kabotaż/ADR/sankcje/przekroczenia).
**Uwaga:** różne od `operational_events` — tu specyficznie ryzyko prawne do audytu ITD.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `route_plan_id` / `route_offer_id` / `speed_order_id` / `driver_id` / `vehicle_id` / `trailer_id` | | do czego się odnosi (nullable) |
| `event_type` | string(40) | `cabotage_limit`/`cabotage_hard_limit`/`adr_missing`/`driver_hours_exceeded`/`weekly_rest_missing`/`oversize_no_permit`/`sanction_country`/`expired_inspection`/`expired_insurance`/… |
| `severity` | string(10) | `info`/`warning`/`error` |
| `description` | text | ludzki tekst |
| `context_json` | text | metadane |
| `is_dismissed` | bool | operator zaakceptował ryzyko |
| `dismissed_by_user_id` / `dismissed_at` / `dismissal_reason` | | dla audytu |
| `detected_at` | datetime | |

Read-only dashboard: `/ryzyko` z filtrem severity + `POST /ryzyko/akceptuj/{id}` z uzasadnieniem.

**Helper:** `ComplianceEventsTable::record($companyId, $eventType, $description, $severity='warning', $context=[], $links=[])`
— best-effort try/catch. Wywoływać z innych modułów zamiast handling w kontrolerze.

### Pełne kolumny — moduły planera operacyjnego (Fala 4)

**Cel:** live tracking + analityka + automatyzacje. Domyka pipeline operacyjny.

Migracje: `20260710130000..20260710130100` (2 tabele) + Cron command + integracje w RouteOffers.

#### `trip_events`
Timeline zdarzeń w trakcie zlecenia — dyspozytor + kierowca z telefonu przez token.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `speed_order_id` | int | FK do `speed_orders` (wymagane) |
| `route_plan_id` / `driver_id` | | powiązania |
| `event_type` | string(30) | `departure`/`arrival`/`loading_started`/`loading_completed`/`unloading_started`/`unloading_completed`/`border_crossed`/`delay_reported`/`pod_uploaded`/`cmr_signed`/`incident`/`note` |
| `happened_at` | datetime | kiedy się wydarzyło |
| `location_lat` / `location_lng` / `location_address` / `location_country` | | geolokalizacja |
| `delay_minutes` / `delay_reason` | | dla delay_reported |
| `notes` | text | |
| `photo_path` | string(500) | zdjęcie POD/CMR z telefonu |
| `source` | string(20) | `operator`/`driver_mobile`/`gps_track`/`api_webhook`/`system` |
| `reported_by_user_id` / `reported_by_name` | | kto zgłosił (name gdy kierowca via token) |

CRUD operatora: `/trip-events/zlecenie/{orderId}` (timeline + QR link dla kierowcy).

**Publiczne (bez auth, token deterministyczny sha256(salt+company+order_id) — 48 chars):**
- `GET /kierowca/{token}` — mobile view kierowcy: nagłówek zlecenia, timeline, przyciski
  „Załadowano" / „Rozładowano" / „Granica" / „Opóźnienie" / „POD z telefonu (aparat)" / „Incydent"
- `POST /kierowca/{token}/event` — kierowca dodaje event z geolokalizacją (jeśli pozwoli w przeglądarce) + upload photo

Publiczny link dostępny w modalu **„Link dla kierowcy"** w widoku operatora (QR z api.qrserver.com).

#### `return_load_candidates`
Kandydaci na ładunek powrotny dla planu trasy. Zapobiega pustym powrotom.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `route_plan_id` | char(36) | FK |
| `candidate_type` | string(20) | `internal`/`market`/`manual` |
| `speed_order_id` | int | dla internal |
| `external_ref` / `external_source` | | dla market (Trans/Timocom — future) |
| `from_city` / `from_country` / `from_lat` / `from_lng` | | pickup |
| `to_city` / `to_country` / `to_lat` / `to_lng` | | delivery |
| `pickup_from` / `pickup_to` | datetime | okno załadunku |
| `price` / `currency` | | |
| `distance_from_route_km` | decimal | deadhead km od końca trasy |
| `time_gap_hours` | decimal | odstęp godzin |
| `match_score` | decimal | 0-100 |
| `status` | string(20) | `suggested`/`dismissed`/`combined` |

CRUD: `/powroty/{planId}` (lista) + `POST /powroty/{planId}/szukaj` (matching engine).

**Matching engine** (`ReturnLoadsController::suggest`):
- Wyciąga endpoint z `route_plans.waypoints_json` (ostatni waypoint)
- Szuka otwartych `speed_orders` (invoice_id IS NULL) w oknie 24h przed / 5 dni po `planned_end_at`
- Match po nazwie miasta (LIKE) lub tym samym kraju
- Wylicza `match_score` przez `ReturnLoadCandidatesTable::calcMatchScore()`
  (deadhead km + time gap + czy ma cenę)

**Helper haversine** w `ReturnLoadCandidatesTable::haversineKm(lat1, lng1, lat2, lng2)` do przyszłych obliczeń dokładnego dystansu.

#### Analytics dashboard `/analytics`
Read-only agregator z 4 źródeł: `speed_orders`, `invoices`, `operational_events`.

**KPI kafelki:** liczba zleceń, faktur, suma faktur PLN, średnia cena, nieopłacone PLN.
**Wykresy/listy:**
- Top 10 tras (bar chart z progress-bar)
- Top 10 klientów (po ilości zleceń)
- Trend miesięczny — suma faktur w PLN (uwzględnia `currency_exchange` dla walutowych)
- Aktywność operacyjna (top 20 wpisów z `operational_events`)

Filtr: 30/90/180/365 dni (default 90).

#### Compliance auto-check w `RouteOffers::create` (Fala 4D)
Przy tworzeniu oferty automatyczny check dla planu:
1. **`vehicle_maintenance`** — dla `vehicle_id`+`trailer_id` sprawdza czy jest ważne `technical_inspection` + `oc` na `planned_start_at`. Brak → `compliance_events` typu `expired_inspection`, severity `error`
2. **`driver_time_logs.hasBudgetInWeek()`** — dla `driver_id` sprawdza czy ma budżet czasu w tygodniu (jeśli plan `duration_min > remaining_min`). Brak → `driver_hours_exceeded`, severity `warning`

Warnings zwrócone w JSON response `create()` → modal „Wyślij ofertę" pokazuje żółty alert box z listą ostrzeżeń + link do `/ryzyko`.

**Wpisy w `compliance_events` można potem zaakceptować z uzasadnieniem** (Fala 3 — `dismissal_reason` do audytu ITD).

#### Cron `bin/cake alerts` (Fala 4E)
Wysyła codzienne emaile 30 dni przed wygaśnięciem `vehicle_maintenance.valid_until`.

Opcje: `--dry` (preview), `--days=14` (inny próg), `--company=<uuid>` (jedna firma).

**Idempotent** przez `alert_sent_at` — nie wysyłamy 2x w ciągu 14 dni.

Email template: `templates/email/html/vehicle_expiring.php` (gradient header, tabela dokumentów, CTA „Otwórz listę serwisów").

Crontab prod (przykład):
```
0 8 * * * cd /home/jjgroup1srv/domains/booklio.pl/public_html && php bin/cake.php alerts
```

### Pełne kolumny `speed_order_stops`
Migracja: `20260804160000_CreateSpeedOrderStops.php`
Punkty pośrednie trasy (multi-stop A→B→C→D). Load/Unload primary są dalej w `speed_orders.load_*`/`unload_*` — tu tylko dodatkowe stopy.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `speed_order_id` | int unsigned | FK do `speed_orders` (CASCADE via hasMany) |
| `stop_index` | int | Kolejność 1..N (0 zarezerwowany dla primary load) |
| `stop_type` | string(20) | `pickup` / `delivery` / `transit` |
| `country_code` / `postal_code` / `city` / `address` / `place_name` | | Lokalizacja |
| `planned_at` / `actual_at` / `completed_at` | datetime | Czas (completed = zaznaczone przez kierowcę) |
| `contact_name` / `contact_phone` | | Kontakt na miejscu |
| `cargo_notes` | text | Palety, waga, uwagi |

Association: `SpeedOrders hasMany SpeedOrderStops` + `cascadeCallbacks + saveStrategy=replace`.

### Pełne kolumny `contractor_credit_limits`
Migracja: `20260804140000_CreateContractorCreditLimits.php`
Limity kredytowe per kontrahent — do warnowania przy nowym zleceniu.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | |
| `contractor_id` | uuid | FK do contractors (nullable) |
| `contractor_nip` | string(30) | klucz matchujący z `speed_orders.buyer_nip` (LIKE) |
| `credit_limit_pln` | decimal(12,2) | Maksymalna kwota nieopłaconych faktur w PLN |
| `warning_threshold_pct` | int(3) | Próg % powyżej którego yellow warning (default 80%) |
| `is_blocked` | bool | Twarda blokada — status w widoku „blocked" |
| `block_reason` | string(500) | Powód blokady |
| `notes` | text | |
| UNIQUE | | (`company_id`, `contractor_nip`) |

CRUD: `/limity-kredytowe`. AJAX: `/zlecenia/kredyt-klienta?nip=xxx`.

### Pola approval w `speed_orders`
Migracja: `20260804150000_AddApprovalToSpeedOrders.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `approval_status` | string(20) | `not_required` / `pending` / `approved` / `rejected` (indeks) |
| `approved_by_user_id` | uuid | FK do users |
| `approved_at` | datetime | |
| `approval_note` | string(500) | Komentarz managera przy accept/reject |

Auto-trigger: przy zapisie zlecenia brutto > `Configure.Orders.approvalThresholdPln` (default 10 000 PLN) → status = `pending`.

Akcje: `POST /zlecenia/{id}/zaakceptuj|odrzuc` (tylko manager/admin, note wymagane przy reject).

### Pełne kolumny `speed_order_notes`
Migracja: `20260804120000_CreateSpeedOrderNotes.php`
Notatki wewnętrzne per zlecenie (analog `invoice_notes`).

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | |
| `speed_order_id` | int unsigned | FK do `speed_orders` |
| `user_id` | uuid | autor (NULL = system) |
| `note_type` | string(20) | `note` / `system` / `reminder` / `phone_call` / `email` |
| `body` | text | treść |
| `payload_json` | text | metadata akcji |

Helper: `SpeedOrderNotesTable::logSystem($companyId, $orderId, $body, $payload)` — best-effort try/catch.

### Pełne kolumny `speed_order_templates`
Migracja: `20260804130000_CreateSpeedOrderTemplates.php`
Szablony zleceń dla powtarzalnych klientów/tras. Jednym clickiem prefill formularza.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | |
| `name` | string(150) | „HB RTS standard NL→DE" |
| `description` | string(500) | |
| `is_favorite` | bool | najczęściej używane na górze |
| `payload_json` | text | JSON z 30+ pól (buyer/load/unload/cargo/finance/incoterms) |
| `usage_count` | int unsigned | dla sortowania po popularity |
| `last_used_at` | datetime | |

CRUD: 5 AJAX endpointów `templatesListJson`/`templateSaveJson`/`templateDeleteJson`/`templateUseJson`/`templateFavoriteJson`.

### Rozszerzenia `speed_orders` — pola spedycyjne (Fala 6)
Migracja: `20260804110000_AddCargoFieldsToSpeedOrders.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `cargo_weight_kg` | int unsigned | Waga ładunku (kg) |
| `cargo_volume_m3` | decimal(8,2) | Objętość m³ |
| `cargo_ldm` | decimal(5,2) | Loading meters |
| `cargo_pallets` | int unsigned | Ilość palet |
| `cargo_pallet_type` | string(20) | EUR/PLA/BOX/DISP/INNE |
| `adr_class` | string(10) | 1..9 (indeks) |
| `adr_un` | string(10) | UN1230 |
| `temperature_min` / `temperature_max` | decimal(4,1) | °C dla chłodni |
| `incoterms` | string(10) | EXW/FCA/DAP/DDP/... |
| `incoterms_place` | string(100) | Miejsce dla INCOTERMS |
| `cmr_number` | string(50) | Nr listu przewozowego CMR (indeks) |
| `insurance_value` / `insurance_currency` | decimal + string | Ubezpieczenie ładunku |

### Pełne kolumny — rozszerzenia `speed_orders` (Fala manualnych zleceń)
Migracja: `20260804100000_AddSourceToSpeedOrders.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `speed_id` | int unsigned, **nullable** | Było NOT NULL. Teraz NULL dla `source='manual'`. Unique index zostaje (MySQL dopuszcza wiele NULL w unique) |
| `source` | string(10), default `'speed'` | `speed` \| `manual` |
| `manual_seq` | int unsigned, nullable | Numer kolejny per (`company_nip`, `rok`, `mc`) dla manualnych. UNIQUE index: (`company_nip`, `source`, `rok`, `mc`, `manual_seq`) |

Backfill: wszystkie istniejące rekordy → `source='speed'`.

**Konwencja symbolu manualnych:** `M-NNNN/MM/YYYY` (np. `M-0001/08/2026`). Numer resetuje się co miesiąc per firma. Prefix `M-` odróżnia od Speed (Speed używa `0099/04/2026`).

### Pełne kolumny `vehicle_combinations`
Migracja: `20260623160000_CreateVehicleCombinations.php`
Nazwane zestawy: **ciągnik + naczepa + kierowca**. Planer tras pozwala wybrać cały zestaw jednym kliknięciem zamiast dobierać każdy element osobno.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | char(36) | PK |
| `company_id` | char(36) | FK firma |
| `name` | string(150) | Nazwa robocza (np. „Volvo FH + Krone Cool + Kowalski") |
| `vehicle_id` | char(36) | FK do `vehicles.id` (ciągnik/solo, nullable) |
| `trailer_id` | char(36) | FK do `trailers.id` (naczepa/przyczepa, nullable) |
| `driver_id` | char(36) | FK do `drivers.id` (kierowca, nullable) |
| `notes` | text | opcjonalne |
| `is_active` | bool | domyślnie `true` |
| `is_default` | bool | Domyślny zestaw firmy — autoselect w planerze. Zapis nowego default automatycznie zeruje default u innych |

CRUD: `/zestawy`. AJAX endpoint dla planera: `/zestawy/lista.json`.

### Pełne kolumny `vehicle_type_categories`
Migracja: `20260623150000_CreateVehicleTypeCategories.php`
Mapowanie: **typ zestawu → kategoria w konkretnym systemie tolls** (np. „Standard w PL A2 AWSA = kat. 4").
Planer tras używa tej mapy zamiast zgadywać po ilości osi/DMC.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | FK firma |
| `vehicle_type_code` | string(20) | `standard|mega|fridge|tandem|solo|bus|oversize` (zgodne z `vehicles.combination_type`) |
| `country_code` | char(2) | ISO 3166-1 alpha-2 (`PL`, `DE`, `AT`, `CZ`, `IT`, `FR`, `CH`, `NL`…) |
| `system_name` | string(60) | np. `A2 AWSA`, `Toll Collect`, `MYTO CZ`, `e-TOLL`, `ASFA`, `GO-Box` |
| `category_label` | string(100) | Etykieta do wyświetlenia (np. „kat. 4", „Achsklasse 5+") |
| `notes` | text | opcjonalne komentarze |
| `is_active` | bool | domyślnie `true` |
| UNIQUE | | (`company_id`, `vehicle_type_code`, `country_code`, `system_name`) |

CRUD: `/admin/vehicle-type-categories`. AJAX endpoint dla planera: `/admin/vehicle-type-categories/for-type/{type}`.

### Pola Kanban na `invoices`
Migracja: `20260528100000_AddKanbanFieldsToInvoices.php`

| Kolumna | Typ | Opis |
|---------|-----|------|
| `snooze_until` | date | data odłożenia karty (NULL = aktywna) |
| `dispute_flag` | bool | spór/windykacja |
| `dispute_reason` | text | powód sporu |
| `assigned_to_user_id` | uuid | FK do `users.id` (kto pilnuje) |
| `kanban_pinned` | bool | przypięcie karty na górze kolumny |

### Pełne kolumny `leads`
Migracja: `20260819100000_CreateLeads.php`
CRM — potencjalni klienci. Multi-tenant przez `company_id`. Może być podpięty do `contractors`
(gdy skonwertowany) lub istnieć samodzielnie ("cold lead").

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | multi-tenant |
| `contractor_id` | uuid | FK do `contractors` (nullable — set po konwersji) |
| `company_name` | string(255) | nazwa firmy (wymagane) |
| `nip` | string(30) | NIP/VAT — klucz dedup + matchowanie z contractors |
| `country_code` | string(2) | ISO 3166-1 alpha-2 |
| `postal_code` | string(20) | |
| `city` | string(100) | |
| `street` | string(255) | |
| `contact_person` | string(150) | imię+nazwisko osoby kontaktowej |
| `contact_role` | string(100) | stanowisko |
| `phone` / `email` | string | |
| `contact_channel` | string(30) | preferowany: `phone / email / meeting / any` |
| `branch_type` | string(50) | `road / road_reefer / road_adr / road_oversize / sea / rail / air / intermodal / any` |
| `stage` | string(20) | pipeline: `new / contact / inquiry / offer / order / lost` |
| `probability` | int(3) | skuteczność 0-100 (auto-preset przy zmianie stage) |
| `value_pln` | decimal(12,2) | szacowana wartość oferty netto |
| `currency` | string(3) | default PLN |
| `flag_contact / flag_inquiry / flag_offer / flag_order` | bool | Excel-style checkboxy — raz osiągnięty etap → true na zawsze |
| `assigned_to_user_id` | uuid | FK do users (handlowiec pilnujący) |
| `source` | string(50) | `manual / import_csv / website / recommendation / cold_call` |
| `kanban_pinned` | bool | przypięcie na górze Kanban |
| `snooze_until` | date | karta ukryta do daty |
| `note` | text | notatka wewnętrzna (widoczna w tabeli/detalu) |
| `next_action_at` | datetime | termin następnej zaplanowanej akcji |
| `next_action_description` | string(500) | opis akcji („zadzwoń w piątek 09:00") |
| `last_contacted_at` | datetime | ostatni kontakt (auto-update po activity) |
| `stage_changed_at` | datetime | kiedy trafił do aktualnego etapu (dla „days in stage") |
| `lost_reason` | string(500) | powód utraty (gdy stage=lost) |

**Preset skuteczności** (LeadsTable::STAGE_PROBABILITY): 10 / 25 / 50 / 75 / 100 / 0.
**Preset odbywa się w beforeSave** jeśli user nie ustawił ręcznie.

### Pełne kolumny `lead_activities`
Migracja: `20260819100100_CreateLeadActivities.php`
Timeline aktywności per lead — CASCADE delete gdy lead usunięty.

| Kolumna | Typ | Opis |
|---------|-----|------|
| `id` | uuid | PK |
| `company_id` | uuid | |
| `lead_id` | uuid | FK do `leads` (CASCADE) |
| `user_id` | uuid | autor (NULL = system) |
| `activity_type` | string(30) | `phone_call / email_out / email_in / meeting / note / task / file / stage_change / assignment / offer_sent / order_won / order_lost` |
| `subject` | string(255) | krótki temat |
| `body` | text | treść/opis |
| `duration_min` | int | czas rozmowy/spotkania w minutach |
| `happened_at` | datetime | kiedy się wydarzyło (dla history — może być w przeszłości) |
| `due_at` | datetime | termin (dla task/reminder) |
| `is_done` | bool | dla task |
| `done_at` | datetime | |
| `file_path` / `file_name` | string | dla `file` (POD/CMR/oferta) |
| `payload_json` | text | metadane akcji (np. `{old:"inquiry", new:"offer"}` dla stage_change) |

**Helper**: `LeadActivitiesTable::logSystem($companyId, $leadId, $type, $subject, $body, $payload, $userId)`
— best-effort try/catch, używać zamiast ręcznego save z kontrolera dla eventów systemowych.

---

## ORM — pułapki i konwencje

### Zawsze używaj closure w `contain` dla ograniczenia kolumn
```php
// ŹLE — ORM doczyta schema cache i może próbować wybrać nieistniejące kolumny:
->contain(['InvoiceContractors' => ['fields' => ['invoice_id', 'name', 'nip']]])

// DOBRZE:
->contain(['InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
    return $q->select(['id', 'invoice_id', 'name', 'nip']);
}])
```

### `hasOne` vs `hasMany`
`InvoiceContractors`, `InvoiceCompanyDetails`, `InvoiceRecipients` → `hasOne` → CakePHP robi JOIN.
`InvoiceContents`, `InvoicePayments`, `InvoiceVatContents` → `hasMany` → osobne zapytania.

### PHP — `0.0 ?: null` zwraca `null`
```php
// ŹLE — gdy $diff === 0.0, wynik to null:
$xml[] = $diff ?: null;

// DOBRZE:
if ($diff !== null) { $xml[] = $diff; }
// lub sprawdź czy grupa ma pozycje ($hasGroup) i wtedy emituj 0
```

---

## Frontend — konwencje

- Bootstrap 5 + Remixicon (`ri-*`)
- jQuery dla AJAX i interakcji
- Formularze AJAX używają tokenu CSRF z ukrytego pola generowanego przez `$this->Form->create()`
- Modalne okna: Bootstrap Modal (`data-bs-toggle="modal"`)
- Lightbox: GLightbox (dla załączników CMR)

### Pułapka z jQuery `$(function(){})` i kolejnością
Jeśli prefill danych w formularzu odbywa się w pierwszym `$(function(){})`, a `rowCalc`/`allCalc` są zdefiniowane w drugim — użyj `setTimeout(fn, 0)` lub triggeruj event `input` zamiast wywoływać funkcje bezpośrednio.

```javascript
// Zamiast rowCalc($tr) — wyzwól event, który wykona handler zarejestrowany później:
$tr.find('.item-price').trigger('input');
```

---

## Trasy — zasady

Wszystkie trasy są jawnie zdefiniowane w `config/routes.php`.
`$builder->fallbacks()` jest na końcu — nie polegaj na nim dla nowych akcji.

Konwencja URL:
- `/rozliczenia` — lista rozliczeń
- `/wyciagi` — wyciągi bankowe
- `/zlecenia` — zlecenia Speed
- `/koszty` — faktury kosztowe
- `/admin/*` — akcje administracyjne

---

## Workflow pracy z Claude

1. **Przed zmianą** — przeczytaj aktualny plik (`Read` tool)
2. **Jeśli zmiana duża** — przedstaw plan, poczekaj na `tak`
3. **Podczas pracy** — zmieniaj tylko to co konieczne
4. **Po zmianie** — potwierdź że działa (sprawdź czy nie ma oczywistych błędów składniowych)
5. **Na końcu** — raport zmian + commit + push + aktualizacja tego pliku jeśli potrzeba

---

## Changelog (ostatnie znaczące zmiany)

| Data | Opis | Pliki |
|------|------|-------|
| 2026-08-24 | Feat: **CRM FALA 15 „Ekstrakcja zapytań o wycenę z emaila"** — GPT auto-rozpoznaje maile zawierające listę zleceń (np. tabela Excel wklejona w body, forwarded WG:/FW:/Weitergeleitet, wiele zaladowań w jednym mailu) i tworzy osobny wpis `activity_type='quote_request'` w timeline leada. Pipeline: `syncGmailOauth` po każdym `email_in` woła `tryExtractQuoteRequest()` (best-effort try/catch). Heurystyka pre-GPT: minimum 2 sygnały z listy 20+ słów kluczy (liefern/transport/zlecenie/wycen/palet/kg/kundenbestellnummer/…) — inaczej pomija bez OpenAI (oszczędność tokenów). GPT prompt (JSON schema): `is_quote_request` + `customer_name/contact` + `shipments[]` (customer_order_ref/from_country+postal+city+company/to_*/load_date+time/unload_date+time/weight_kg/pallets+type/cargo_type/vehicle_type/notes). Dedup po `message_id`. Auto-podnies stage `new/contact → inquiry` (zapytanie = etap oferty) z autologiem `stage_change`. Widget w `templates/Leads/view.php` timeline pod activity: zielony border-left panel, "Wykryte zlecenia (N)", toggle "Pokaż/ukryj", tabela 11-kolumnowa (Ref/Z/Do/Data/Kg/Palet/Uwagi z badge sprzęt+cargo). Button **„Utwórz wszystkie zlecenia w bazie"** wywołuje `POST /crm/utworz-zlecenia-z-quote/{activityId}` → dla każdego shipment tworzy `speed_orders` (source=manual, auto numer M-NNNN/MM/YYYY, kolejny manual_seq per company_nip+rok+mc, prefill buyer_* z leada, load_*/unload_*/cargo_* z shipment, notes zawiera Kundenbestellnummer+cargo_type+notes, currency EUR domyślnie). Loguje w `speed_order_notes` typ system z payload `{source: quote_request, lead_id, activity_id}` + w lead timeline nowy activity `note` "Utworzono N zleceń manualnych z zapytania o wycenę". Aktualizuje `payload_json` aktywności o `orders_created_at/count/by_user_id`. Diagnostyka: dodano też `/crm/admin/find-lead?email=X` (EXACT+LIKE+bin2hex compare pokazuje różnice bajtowe/spacje/wielkość liter+ostatnie 10 msg w `crm_email_messages`) + `/crm/admin/reset-gmail-history` (zeruje `oauth_history_id`+`last_synced_at` → next Poll pobierze inbox z ostatnich 30 dni max 100 msg zamiast tylko `historyId>X`). Karta „Diagnostyka leadow/emaila" (pomarańczowy border) w `CrmAdmin/tools.php`. | src/Command/CrmEmailPollCommand.php (+tryExtractQuoteRequest 130 lines + wire-up po email_in + $data['gmail_id']), src/Controller/LeadsController.php (+createOrdersFromQuote 140 lines), src/Controller/CrmAdminController.php (+findLead + resetGmailHistory), templates/Leads/view.php (widget shipments + JS toggler + ikona quote_request), templates/CrmAdmin/tools.php (karta diagnostyki), config/routes.php (+3 trasy), config/permissions.php (+createOrdersFromQuote/findLead/resetGmailHistory) |
| 2026-08-24 | Feat: **CRM FALA 5-7** — dorównanie do HubSpot/Pipedrive/Salesforce (~60% luki funkcjonalnej pokryte). **Fala 5a „Kontrakty ramowe"**: migracja `crm_contracts` (id/company_id/contractor_nip+name/name/from_country+city+postal+to_country+city+postal/price_netto+currency+vat_rate+payment_days/required_vehicle_type/committed_volume+used_volume+volume_period/valid_from+valid_to/is_active/notes). `CrmContractsTable::findBestMatch(companyId, nip, route)` z priorytetem NIP+city LIKE → NIP+country → NIP+no_route. CRUD `/kontrakty` z alertem wygasających 30d + wolumen progress-bar. **AJAX GET `/kontrakty/match?nip=&from_country=&from_city=&to_country=&to_city=`** wywoływane w `SpeedOrders/add.php` po zmianie NIP/miast (debounce 400ms) → widget zielony alert „Znaleziono kontrakt: nazwa · trasa · cena · vol% · wygasa Xd" + button „Zastosuj cenę" wypełnia netto/currency/vat_rate/payment_days + trigger `input` event. Hidden field `_from_contract_id` po save w `SpeedOrders::add` wywołuje `CrmContracts::incrementUsedVolume()`. **Fala 5b „AI lead scoring"**: `LeadsTable::topPriority(companyId, userId=null, limit=10)` — rules-based (nie GPT, deterministic + tani): next_action_at przeterminowany +50 / w 24h +30 / stage: offer=25/inquiry=20/contact=15/new=5 / last_contacted NULL +15 / >14d +20 / >7d +10 / value_pln/1000 max +30 / probability/5 max +20. Widget „Top 10 do dzwonienia dzisiaj" na dashboardzie (zielona ramka) — # / score color-coded (red>=80/amber>=60/green) / firma+miasto / osoba+tel klikalny / stage / wartość / data last_contact / powody priorytetu (chipy) / button `tel:` + view. Toggle „Zespół / Tylko moje" (?top_mine=1). **Fala 5c „Public web form"**: `LeadsController::publicForm/publicFormThanks` z `allowUnauthenticated`. Routes `/kontakt/{companyId}` (UUID pattern), layout `ajax` bez sidebar admin, inline CSS z Booklio green gradient. Anti-spam 3-warstwowy: honeypot pole `website_url` off-screen (bots wypełniają → silent success), timestamp min 3s od otwarcia formularza, rate-limit 5/h per IP przez session. Po submicie: tworzy lead z `source=website + stage=new + next_action_at=+1d`, loguje `email_in` activity z IP+User-Agent, best-effort email do admina firmy (pierwszy user by created asc). **Fala 6 „Email 2-way sync (IMAP)"** — killer feature poziomu Pipedrive Mail. Migracja `crm_email_accounts` (imap_host/port/use_ssl/username/password_encrypted AES-256-CBC z Security.salt/folder/last_seen_uid/last_synced_at/last_error/counters/sync_frequency_min). `CrmEmailAccountsTable::encryptPassword/decryptPassword` (openssl AES-256-CBC + IV random) + `findDueForSync()` (aktywne + last_synced+freq_min <= now). CRUD `/crm/email-accounts` z POST `/test` (imap_open OP_READONLY, imap_status pokazuje msg count + next UID). Command `bin/cake crm_email_poll` (co 5 min): dla każdego aktywnego konta imap_open+imap_search UID SINCE (last_seen_uid+1):* → dedup+sort+limit --max=100. Dla każdego msg: imap_headerinfo → from email → match `LOWER(Leads.email)` = from → jeśli match: 500 znaków body przez imap_fetchbody + `LeadActivities::logSystem(email_in, subject, body, payload_json{imap_uid, from, account_id})` + update `Leads.last_contacted_at`. Update `last_seen_uid + counters + last_synced_at + last_error` na końcu. Bezpieczeństwo: hasła zaszyfrowane (dump DB nic nie da), `password_encrypted` w `$_hidden`, dostęp tylko manager/user, wymaga PHP imap extension. **Fala 7 „Duplicate merge UI + Workflows engine"**: `LeadsController::duplicates` — wykrywa pary po: same NIP / same email / same phone (>=6 cyfr) / normalized name (bez sp. z o.o./sa/gmbh) / fuzzy Levenshtein ≤ 2 (dla zbiorów < 500 dla wydajności). Widok `/crm/duplikaty` z tabelą par (2-kolumnowa) + chipy powodów. `mergeReview` — tabela pole-po-polu z radio wyboru A|B, auto-scalanie stage (max ordering) + probability (max) + flag_* (OR) + last_contacted_at (latest). POST `/crm/merge` w transakcji: `updateAll Activities lead_id B→A + log merge activity + delete Lead B`. Migracja `crm_workflows` + `crm_workflow_runs`. Command `bin/cake crm_workflow_run` (co 10 min): 3 triggers (`stage_no_activity_days`, `lead_age_days`, `task_overdue`) + condition filters JSON (branch_type/country/probability_min-max/value_min) + 3 actions (`create_task` z due_days / `change_stage` z whitelist / `send_email` z template `{{company}}/{{stage}}` do assigned_user). Cooldown per (workflow_id, lead_id) w `crm_workflow_runs` (default 24h) chroni przed duplikatami. Sidebar: +Duplikaty +Skrzynki IMAP +Kontrakty ramowe. **Rezultat**: nasz CRM pokrywa teraz Web forms/AI scoring/Contracts (unikat)/IMAP email sync/Duplicate merge/Workflows automation — plus zachowuje branzowe unikaty (KSeF/GUS/Zlecenia/Faktury). Największe braki vs HubSpot: mobile app native, Meeting scheduler (Calendly), Document tracking (Docsend), Contact enrichment (Clearbit/ZoomInfo). | migracje: `CreateCrmContracts` + `CreateCrmEmailAccounts` + `CreateCrmWorkflows`; nowe kontrolery: `CrmContractsController` + `CrmEmailAccountsController`; nowe Tables/Entities: `CrmContract` + `CrmEmailAccount` + `CrmWorkflow(+Run)`; nowe commands: `CrmEmailPollCommand` + `CrmWorkflowRunCommand`; templates: `CrmContracts/{index,add}` + `CrmEmailAccounts/{index,add}` + `Leads/{public_form,public_form_thanks,duplicates,merge_review}`; rozszerzenia: `LeadsController` (+publicForm/publicFormThanks/duplicates/mergeReview/merge, +beforeFilter allowUnauthenticated) + `LeadsTable::topPriority` + `SpeedOrders/add.php` widget kontraktu + `SpeedOrdersController::add` incrementUsedVolume; +14 tras, +5 grup permissions, +3 pozycji sidebara |
| 2026-08-20 | Feat: **CRM FALA 1-4** — dopracowanie modułu do produkcyjnego użycia. **Fala 1 „używalność"**: (1) Import CSV z Excela klienta `/crm/import-csv` — upload→preview→confirm bulk insert, rozpoznawane nagłówki PL/EN case-insensitive (nazwa/kraj/kod/miasto/kontakt/tel/email/gałąź/etap/skuteczność/wartość/note + checkboxy K/Z/O/Zl), auto-detect separator (,;\t) + UTF-8/Win-1250 + BOM strip, dedup po NIP; szablon `/crm/import-csv/szablon.csv` z przykładem SILESIAN FLOUR. (2) GUS-lookup w formularzu `/crm/dodaj` — button obok NIP → auto-fill nazwa/adres/VAT status (reuse `/contractors/gus-lookup`) + on-blur dedup check (warning „Istnieje już lead z tym NIP" + link). (3) Widok `/crm/zadania` — task-activities (activity_type=task, is_done=false, due_at w oknie 7/14/30/60/90 dni) + toggle Moje/Zespół + 3 KPI kafelki (Przeterminowane/Dzisiaj/Nadchodzące) + dwie sekcje (taski + follow-upy z `leads.next_action_at`) + AJAX „Oznacz jako wykonane". (4) Cron `bin/cake crm_tasks_digest` — codzienny email HTML per handlowiec (gradient header, 3 sekcje overdue/today/upcoming color-coded, buttony „Otwórz" per lead, CTA); opcje `--dry --days=7 --company=<uuid> --user=<uuid> --stale-days=14`. **Fala 2 „integracja"**: (5) Button „Utwórz zlecenie" w detalu leada → `/zlecenia/dodaj?lead_id=<uuid>` z prefillem `buyer_*/notes` (multi-tenant guard); po zapisie zlecenia session `Crm.orderFromLeadId` triggeruje `stage='order'` + autolog `order_won` z symbolem+kwotą. (6) Button „Utwórz ofertę" + modal → `POST /crm/{id}/utworz-oferte` → tworzy `route_plan` bez trasy + `route_offer` status=draft z access_token; jeśli stage IN [new/contact/inquiry] → stage='offer' + sync value_pln; autolog `offer_sent`; redirect do `/oferty/view/{id}`. **Fala 3 „UX+KPI"**: (7) Bulk actions w tabeli — checkbox row + master + sticky bar (change_stage/assign/delete) z whitelist stages + guard company_id + limit 500 + autolog `stage_change/assignment` payload `source=bulk`. (8) Sortowanie kolumn (whitelist company_name/city/stage/probability/value_pln/modified/created — anti SQL injection), toggle asc↔desc w nagłówkach. (9) Saved filters w localStorage (`crm_leads_saved_views`) — nazwana lista widoków z query string, badge z aktywnym highlight + × do usunięcia, XSS-safe. (10) Dashboard KPI `/crm/dashboard` — 4 kafelki (aktywne/pipeline/konwersja/wygrane) + pipeline funnel z bar charts per etap + Chart.js line chart aktywności per dzień (CDN 4.4.0) + ranking handlowców (leady/pipeline/wygrane/utracone/konwersja %) + źródła nowych leadów; filtr 30/90/180/365 dni. **Fala 4 „automatyzacja email"**: (11) `LeadsTable::afterSave()` hook — auto-thanks email do klienta gdy stage zmienił się na `order` (idempotent — sprawdza `_previous_stage`, wysyła tylko na prawdziwy przejście); template `templates/email/html/crm_lead_thanks.php` (gradient Booklio, dane opiekuna, tekst po polsku); kontrolowane przez `Configure Crm.autoThanksEnabled` (default true); autolog `email_out` z payload `{auto:true, trigger:"stage_change_to_order"}`; best-effort try/catch nie może wywrócić save(). (12) Cron rozszerzony o „Zapomniane leady" — bez aktywności ponad `--stale-days=14` (last_contacted_at NULL lub < próg + modified < próg + stage active + snooze OK) w mailu digest jako 4-ta szara sekcja. Sidebar +3 pozycje (Dashboard KPI / Moje zadania / Import CSV) | src/Controller/LeadsController.php (+bulk/dashboard/importCsv/importCsvTemplate/myTasks/taskDone/gusLookupJson/createOfferFromLead + helpery parseCsv/mapCsvRowToLead; index: sort whitelist + users), src/Controller/SpeedOrdersController.php (add: prefill lead_id + post-save stage sync), src/Model/Table/LeadsTable.php (+afterSave/sendThankYouEmail + import Mailer/Log), src/Command/CrmTasksDigestCommand.php (nowy + rozszerzenie o stale), templates/Leads/{import_csv,my_tasks,dashboard}.php (nowe), templates/Leads/{index,add,view}.php (bulk/sort/GUS/buttony), templates/email/html/{crm_tasks_digest,crm_lead_thanks}.php (2 nowe), templates/layout/default.php (sidebar +3), config/routes.php (+7 tras), config/permissions.php (+8 akcji) |
| 2026-08-19 | Feat: **CRM Leady** (pełny moduł) — 2 nowe tabele (`leads`, `lead_activities`), full CRUD `/crm` + Kanban 5 kolumn z drag&drop (SortableJS via CDN) + detal z stepper etapów + timeline aktywności z formularzem dodawania (call/email/meeting/note/task). Pipeline: `new → contact → inquiry → offer → order (+lost)` z auto-preset skuteczności per etap (10/25/50/75/100) w `LeadsTable::beforeSave()`. Auto-flagi Excel-style K·Z·O·Zl (raz osiągnięty etap → zostaje). Konwersja lead→contractor via `POST /crm/konwertuj/{id}`. Helper `LeadActivitiesTable::logSystem()` best-effort loguje event `stage_change` przy drag&drop w Kanban. Sidebar menu 3-poziomowe (Lista/Pipeline/Nowy). i18n EN kompletny (~90 kluczy). Poprzedzone designem 4-artboardowego CRM w Design skill (https://claude.ai/code/artifact/6a065118-d1e0-4069-b391-8ae580634544) z realnymi danymi z Excela klienta (Bowim, Mondi Simet, PipeLife, Tymbark, SILESIAN FLOUR, KATOENNATIE Cremona, Milcobel, Epifatech, Carlsberg, Dijo, Grodcono, Avery Denison, Omnivent) | 2 migracje `CreateLeads` + `CreateLeadActivities`, `LeadsTable` + `LeadActivitiesTable` + Entities, nowy `LeadsController` (index/kanban/kanbanMove/view/add/edit/delete/convertToContractor/activityAdd/activityDelete), 4 templates (`index.php` tabela, `kanban.php` drag&drop, `view.php` detal+timeline, `add.php` formularz), `routes.php`, `permissions.php`, `templates/layout/default.php` (sidebar), `resources/locales/en/default.po` (+~90 kluczy) |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **TSL upgrade** — pełny pakiet realnej spedycji: migracja 17 nowych kolumn (`load/unload_time_from/to`, `payment_days` + auto `payment_due_date` z `date_doc`, `required_vehicle_type` enum plandeka/mega/chłodnia/cysterna/wywrotka/kontener/bus/platforma/oversize, `pallets_exchange` bool + `pallets_exchange_count`, `docs_return_days` CMR/WZ, `load/unload_contact_name/phone/email`, `driver_instructions` osobne od notes); walidacja JS wagi cargo vs typowy DMC dla typu pojazdu (bus 3.5t / chłodnia 20t / plandeka 24t / oversize 50t); TSL flow quick-nav bar (Klient→Trasa→Ładunek→Transport→Cena) z badge numerów kroków 1-5 w headerach sekcji + kotwice `#sec-buyer/route/cargo/transport/finance` + smooth-scroll; AI parser JSON schema + JS mapping obejmuje wszystkie 17 nowych pól (boolean `pallets_exchange` osobny handler); Templates TPL_FIELDS rozszerzone o TSL fields; lat/lng widoczne edytowalne pola dla pickup/delivery/multi-stop z klikalnym linkiem Google Maps; naprawy 2 bugów JS (`$rateFx` i `$stopsAdd` używane przed definicją → refaktor kolejności + MutationObserver) | migracja `AddTslFieldsToSpeedOrders` + `AddLatLngToSpeedOrdersAndStops`, `SpeedOrdersController::prepareManualOrderData` (auto payment_due_date + normalizacja palet), Entity `SpeedOrder` (docblock 17 pól), `templates/SpeedOrders/add.php` (widgety kontaktów w sekcjach Za/Roz, dropdown required_vehicle_type + pallets_exchange w Ładunku, payment_days + due w Finansach, driver_instructions z warning "nie w email", quick-nav bar, badge kroków, JS payment auto + weight validation + AI mapping) |
| 2026-08-04 | Feat: `/zlecenia/tracking` **Tracking dashboard FALA 10** — live monitoring aktywnych zleceń w trasie: filtr `nordlogis_status IN [3,4]` + `pod_at IS NULL`, dla każdego zlecenia karta z ostatnim eventem z `trip_events` (typ + ikona + kolor + timestamp + adres + opóźnienie + zdjęcie POD); 4 KPI (total/loading/in_transit/delayed); filtry: kierowca/kontrakt/kraj; button „Timeline" linkuje do `/trip-events/zlecenie/{id}`; **auto-refresh 60s** przez `<meta refresh>`; layout responsive (3 kolumny na xl, 2 na md); mapa event_type → label/icon/color dla 12 typów (departure/arrival/loading/unloading/border/delay/pod/cmr/incident/note) | `SpeedOrdersController::tracking`, `templates/SpeedOrders/tracking.php`, `index.php` (button), routes, permissions |
| 2026-08-04 | Feat: `/zlecenia` **Excel export FALA 10** — nowa akcja `exportXlsx()` generuje plik `.xls` w formacie XML SpreadsheetML 2003 (Excel otwiera bezpośrednio, LibreOffice też wspiera); **bez PhpSpreadsheet** — czysty PHP przez XML builder; 44 kolumny snapshot (klient/trasa/ładunek/transport/finanse/statusy/dokumenty); style nagłówka bold + bg brand; wartości numeryczne jako `ss:Type="Number"` (SUM/AVG działa bez konwersji); limit 5000 zleceń; dropdown split button „CSV / Excel" w liście | `SpeedOrdersController::exportXlsx` + private `buildSpreadsheetXml`, `index.php` (dropdown), routes, permissions |
| 2026-08-04 | Feat: `/zlecenia/kanban` **Kanban zleceń FALA 10** — operacyjny widok 5 kolumn per `nordlogis_status`: Przyjęte (blue) / Zaplanowane (cyan) / Załadowane (amber) / Zrealizowane (green) / Zafakturowane (gray); drag & drop przez SortableJS 1.15 (CDN) → AJAX POST `/zlecenia/kanban/przenies/{id}` z guard company_nip; karta zawiera symbol (link do view), badge M/S, klient, trasa, kwota, meta ikony (data delivery, kierowca, pojazd, approval status); filtry: search + kontrakt + source; rollback pozycji karty gdy save fail; limit 500 zleceń | `SpeedOrdersController` (`kanban`, `kanbanMove`), `templates/SpeedOrders/kanban.php`, `index.php` (button), routes, permissions |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Kabotaż tracker FALA 9** (UE 1072/2009) — endpoint `GET /zlecenia/kabotaz?vehicle_plate=X&load_country=Y&unload_country=Z&date=D`: analizuje historię `speed_orders` dla pojazdu w oknie 14 dni, klasyfikuje operacje jako *międzynarodowy wjazd* (load≠unload, unload=X), *kabotaż* (load=unload=X) lub *wyjazd* (load=X, unload≠X); zwraca status `allowed`/`warning`/`limit_exceeded`/`no_entry`/`window_expired` + ostatni wjazd + count + okno 7 dni. Widget w formularzu (dodany do sekcji conflict/compliance): auto-trigger po zmianie pojazd+kraje+data | `SpeedOrdersController::cabotageCheckJson`, `templates/SpeedOrders/add.php` (JS fetchCabotage), routes, permissions |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Live mapka HERE FALA 9** — widget kalkulatora trasy rozszerzony o interaktywną mapę HERE Maps JS SDK v3.1.1: 2-kolumnowy layout (KPI po lewej, mapa 320px po prawej); rozszerzony `routeCalcJson` zwraca teraz `polyline` (flexible polyline z HERE Routing v8) + `lat`/`lng` dla from/to; JS decode via `H.geo.LineString.fromFlexiblePolyline`, rysowanie niebieskiego `H.map.Polyline` + 2 markery + auto fit-to-bounds; deferred init (retry 50×200ms) czeka na script load; warunkowe ładowanie 5 script tagów SDK tylko gdy `Configure.Here.apiKey` ustawiony (fallback placeholder gdy brak API key) | `SpeedOrdersController` (routeCalcJson + add/edit przekazują `$hereApiKey`), `templates/SpeedOrders/add.php` (SDK tags + widget + JS map init + drawRouteOnMap) |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Multi-stop trasy FALA 9** — nowa tabela `speed_order_stops` (uuid PK, `speed_order_id` FK, `stop_index` 1..N, `stop_type` pickup/delivery/transit, adres, `planned_at`/`actual_at`/`completed_at`, kontakt, cargo_notes); `SpeedOrders hasMany SpeedOrderStops` z `cascadeCallbacks` + `saveStrategy=replace` (do usuwania stopów); UI sekcja „Dodatkowe stopy w trasie" z border-left orange akcent + dynamiczne wiersze (button „+ Dodaj stop") + JS renumeracji stop_index + wiersze usuwane bez leakage; `patchEntity` z `['associated' => ['SpeedOrderStops']]`; `prepareManualOrderData` filtruje puste stopy + puste datetime→null + normalizuje country_code; widok szczegółów: sekcja tabelaryczna z badge typu + adres + kontakt + planowany/rzeczywisty czas | migracja `CreateSpeedOrderStops`, Table+Entity, `SpeedOrdersTable` (hasMany), `add.php` (sekcja + JS), `view.php` (tabela stopów) |
| 2026-08-04 | Feat: `/zlecenia/import-csv` **Batch import CSV** — masowy import zleceń z pliku CSV: parser obsługuje separatory `,;\t` (auto-detect), kodowanie UTF-8/Win-1250 (auto-convert), BOM strip; szablon CSV do pobrania z przykładowym rekordem (`/zlecenia/import-csv/szablon.csv`); flow: upload → preview (10 wierszy + tabela błędów walidacji) → confirm & bulk insert; reuse `prepareManualOrderData` dla każdego rekordu (auto-numer M-, calc VAT/brutto, approval-check, normalizacja krajów); button „Import CSV" w liście zleceń | migracja n/d, kontroler `batchImport` + `batchImportTemplate` + private `parseCsv`, `templates/SpeedOrders/batch_import.php` (upload + preview), `templates/SpeedOrders/index.php` (button) |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Approval workflow FALA 8** — akceptacja managera dla dużych zleceń: migracja `AddApprovalToSpeedOrders` (4 kolumny: `approval_status` enum not_required/pending/approved/rejected, `approved_by_user_id`, `approved_at`, `approval_note`); próg globalny w `Configure.Orders.approvalThresholdPln` (default 10 000 PLN); auto-detect w `prepareManualOrderData` — jeśli brutto (PLN, po kursie) > próg → status `pending`; akcje `approve`/`reject` z guard tylko dla ról manager/admin + wymagany note przy odrzuceniu + auto-log w `SpeedOrderNotes`; widget w formularzu (JS live hint), sekcja approval-card w widoku (color-coded + buttony Akceptuj/Odrzuć dla managera), badge iconka w liście zleceń przy symbolu | migracja, kontroler (`approve`, `reject` + `prepareManualOrderData`), Entity SpeedOrder, `add.php` (hint + JS), `view.php` (approval-card), `index.php` (badge), routes, permissions |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Kredyt klienta FALA 8** — limity kredytowe + real-time saldo: migracja `CreateContractorCreditLimits` (uuid PK: `company_id`, `contractor_id` nullable, `contractor_nip`, `credit_limit_pln`, `warning_threshold_pct` default 80, `is_blocked` + `block_reason`, UNIQUE (company_id, contractor_nip)); endpoint `GET /zlecenia/kredyt-klienta?nip=xxx` zwraca limit + saldo nieopłaconych faktur (query Invoices.remaining matching InvoiceContractors.nip → przeliczenie na PLN wg exchange_rate) + przeterminowanie + status (ok/warning/exceeded/blocked/has_overdue); widget alert color-coded w formularzu; CRUD `/limity-kredytowe` (index + add + edit + delete) dostępny dla manager/user (bez młodszego) | migracja, Table+Entity `ContractorCreditLimits`, nowy `ContractorCreditLimitsController` (index/add/edit/delete), `SpeedOrdersController::creditCheckJson`, templates dla CRUD, widget w `SpeedOrders/add.php` z fetchCredit triggerem, routes, permissions |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Templates FALA 7** — reużywalne szablony zleceń dla powtarzalnych klientów/tras: nowa tabela `speed_order_templates` (id uuid, company_id, name, description, is_favorite, payload_json, usage_count, last_used_at) + CRUD AJAX (list, save, delete, use, favorite). W formularzu button „Szablony zleceń" + modal z listą (fav pierwszy, potem sortowane po usage_count/modified) z 3 przyciskami per row (Ulubione/Usuń/Załaduj) + drugi button „Zapisz jako szablon" (ikonka zakładki) → modal z nazwą/opisem (auto-suggest nazwy z klienta+trasy). Payload zapisuje 30+ pól (buyer/load/unload/cargo/finance/incoterms). Click „Załaduj" → prefill + increment usage_count + zamknij modal | 5 nowych route + permissions, migracja `CreateSpeedOrderTemplates`, Table+Entity, kontroler (5 metod: templatesListJson, templateSaveJson, templateDeleteJson, templateUseJson, templateFavoriteJson), `add.php` (2 modale + 200 lines JS) |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Notatki + fix NBP FALA 5-6** — (1) nowa tabela `speed_order_notes` (uuid, note_type=note/system/reminder/phone_call/email) + Table+Entity + helper `logSystem()`, kontroler `noteAdd`/`noteDelete` z guard company_id + author/admin, sekcja notatek w `view.php` z formularzem dodania (typ dropdown + textarea) i listą (ikonka+kolor per typ, autor, timestamp, button usuń); (2) **fix kursu NBP** — pole exchange_rate w Finansach teraz auto-fetchuje ostatni kurs z NBP dla wybranej waluty przez `GET /nbp/rates?code=X&from&to` (zakres 7 dni wstecz od date_doc, bierze ostatni mid), title pola pokazuje datę+tabelę NBP, cache w JS żeby uniknąć duplicate fetch; (3) **pola spedycyjne** — migracja `AddCargoFieldsToSpeedOrders` z 14 nowymi kolumnami (cargo_weight_kg/volume_m3/ldm/pallets/pallet_type, adr_class/adr_un/temperature_min/max, incoterms/incoterms_place, cmr_number, insurance_value/currency) + Entity docblock + rozszerzony accordion „Więcej opcji" z 3 podsekcjami + AI parser JSON schema + JS mapping obejmuje nowe pola | 2 migracje, 2 nowe Tables+Entities, kontroler (noteAdd/noteDelete), `view.php` (sekcja notatek), `add.php` (accordion + JS NBP fetch), Entity SpeedOrder |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Analytics FALA 5** — mini-profil klienta w formularzu po wpisaniu NIP: nowy endpoint `GET /zlecenia/profil-klienta?nip=xxx` zwraca 12-miesięczne statystyki (liczba zleceń, avg netto, suma, ostatnie zlecenie, TOP trasa, DSO z faktur przez `Invoices` matching `InvoiceContractors.nip`, ostatnie 3 zlecenia). Collapsible card pod polami Nabywca z 5 KPI + toggle „szczegóły" → TOP trasa + badgy zleceń z linkami | `SpeedOrdersController::buyerProfileJson`, `templates/SpeedOrders/add.php` (widget + JS `fetchBuyerProfile`), routes.php, permissions.php |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Integracje FALA 4** (3 commity) — (1) **Załaduj z planera tras** button + modal z tabelą route_plans (nazwa/status/trasa/km/cena/klient) → click „Załaduj" prefilluje load/unload/daty/netto/kontrahenta (endpoint `/zlecenia/plany-tras`); (2) **Wysyłka email do klienta** — checkbox w sticky bar, po zapisie wysyła HTML email z template `speed_order_confirmation.php` (gradient header + KPI trasy/ładunku/finansów); (3) **Zapisz + wystaw fakturę** — dropdown w sticky bar, redirect do `/invoices/add?type=vat\|currency&from_order_id={id}` (reuse istniejący prefill); (4) **Zapisz + dodaj CMR** — redirect do view z `?focus=attachments` + smooth scroll do sekcji + highlight ring; (5) **Auto-utwórz kontrahenta z GUS** — po sukcesie GUS lookup button „Zapisz jako kontrahenta" → AJAX POST do istniejącego `/contractors/add` → zapisuje w bazie; (6) **PDF potwierdzenia zlecenia** — nowa akcja `pdfConfirmation` przez CakePdf/DomPdf, button „PDF" w widoku szczegółów, template `pdf/pdf_confirmation.php` (DejaVu Sans, A4, dwie kolumny nabywca/wykonawca, gradient sections) | `SpeedOrdersController` (routePlansJson, pdfConfirmation, sendOrderEmail + save_and_invoice/save_and_attach w add), `templates/SpeedOrders/add.php` (modal planów, dropdown split button, checkbox email, JS handlers), `templates/SpeedOrders/view.php` (PDF button, focus=attachments scroll), `templates/SpeedOrders/pdf/pdf_confirmation.php` (nowy), `templates/email/html/speed_order_confirmation.php` (nowy), `routes.php`, `permissions.php` |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Warnings FALA 3** (2 commity) — reuse infrastruktury Fala 1-3 planera operacyjnego dla walidacji operatora: (1) **Kolizja grafika kierowcy/pojazdu** — po zmianie driver/vehicle+daty AJAX do `/zlecenia/conflict-check` (query po `driver_schedules`/`vehicle_schedules` overlap) → alert z listą kolizji + entry_type + linked zlecenie; (2) **Compliance auto-check** — `VehicleMaintenanceTable::findMissingForDate()` sprawdza czy pojazd ma ważne `technical_inspection`+`oc` na dzień załadunku, `DriverTimeLogsTable::hasBudgetInWeek()` sprawdza budżet UE 561/2006; (3) **Duplikat zlecenia** — query po (buyer_nip + load_city) w oknie ±1 dzień → hint z linkiem do istniejącego; (4) **Znajdź wolne zasoby w oknie** — button w sekcji Transport → modal z listą wolnych kierowców (badge ADR) + pojazdów w oknie z dat załadunku/rozładunku (reuse `findAvailableInWindow`); (5) Widget renderuje 3 sekcje razem: kolizja (warning), compliance (danger), duplikat (info) z najwyższym severity dla ramki | `SpeedOrdersController` (conflictCheckJson, freeResourcesJson), `templates/SpeedOrders/add.php` (widget warning + modal wolnych + JS), `routes.php`, `permissions.php` |
| 2026-08-04 | Feat: `/zlecenia/dodaj` **Smart Auto-fill FALA 2** — kompletny pakiet 7 features: (1) **Auto-fill GUS** po NIP button obok pola + badge status VAT z Białej Listy MF, (2) **HERE autocomplete miast** dla load_city/unload_city/buyer_city — dropdown z propozycjami city+postal_code+country z HERE Autosuggest (endpoint `/zlecenia/cities`), (3) **Live kalkulator trasy HERE** — widget z km/duration/tolls/sugestia ceny (km × stawka + tolls), button „Ustaw jako netto" (endpoint `/zlecenia/route-calc`), (4) **Live pricingHistory + alert dumpingu** — mediana z historii tras + czerwony alert < 90% mediany / zielony > 110% (reuse `/planer-tras/historia-stawek`), (5) **Toggle client/market mode** w pricingHistory, (6) **AI parser email/screenshot** — modal z textarea + drop zone + Ctrl+V paste image + `chatVisionJson()` OpenAI z gpt-4o-mini Vision → strukturalny JSON → auto-fill wszystkich pól formularza (endpoint `/zlecenia/ai-parse`), (7) **Fix 403 CSRF** — dodano `_csrfToken` + header `X-CSRF-Token` do wszystkich AJAX POST. **Fix routing 404**: `setExtensions(['json'])` stripuje `.json` z URL przed matchowaniem — trasy definiowane bez `.json` w path. **Fix 500**: `jsonResp()` teraz wyłącza autoRender | `SpeedOrdersController` (citiesJson, routeCalcJson, aiParseOrderJson + fix jsonResp), `HereRoutingService::autosuggest` (rozszerzone o city+postal_code), `OpenAiService::chatVisionJson` (nowa), `templates/SpeedOrders/add.php` (widgety GUS/HERE/pricing/AI + modal), `routes.php`, `permissions.php` |
| 2026-08-04 | Feat: `/zlecenia/dodaj` UX FALA 1 — sticky action bar (Zapisz/Zapisz+kolejne/Anuluj zawsze widoczne), autosave co 30s do localStorage + recovery prompt, skróty klawiszowe (Ctrl+S, Ctrl+Enter, Esc), progressive disclosure (mniej używane pola w accordion „Więcej opcji"), hint „Ostatnie zlecenia w tym miesiącu" z klikalnymi badge, prefill z ostatniego zlecenia klienta po wpisaniu NIP (endpoint `/zlecenia/ostatnie-dla-klienta.json` + zielony banner „Znaleźliśmy ostatnie zlecenie — użyj jako szablon"), obsługa `?dup={id}` (duplikat z widoku szczegółów), przycisk „Duplikuj" w widoku szczegółów, batch mode „Zapisz i dodaj kolejne", live brutto preview w sticky bar, color accents na kartach sekcji (Załadunek=green, Rozładunek=red, Finanse=blue) | `SpeedOrdersController` (add z ?dup= + save_and_new, lastForBuyerJson, loadRecentManualInMonth), `templates/SpeedOrders/add.php` (pełny rewrite), `templates/SpeedOrders/view.php` (Duplikuj), `routes.php`, `permissions.php` |
| 2026-08-04 | Feat: ręczne tworzenie zleceń transportowych `/zlecenia/dodaj` — analogicznie do sync ze Speed, ale przez formularz. Nowa kolumna `source` (speed/manual) + `manual_seq` + `speed_id` nullable. Auto-numer `M-NNNN/MM/YYYY`. Formularz z 6 sekcjami (numer/nabywca/załadunek/rozładunek/ładunek/transport/finanse), autocomplete kontrahentów (existing `/contractors/search`), datalist kierowców + pojazdów, auto-calc VAT/brutto. UI listy: badge M/S + filtr Źródło. Widok szczegółów manualnego: Edytuj/Usuń (blokada gdy podpięta faktura) | migracja `AddSourceToSpeedOrders`; `SpeedOrdersController` (add/edit/delete/driversJson/vehiclesJson/nextManualSymbol/prepareManualOrderData); `SpeedOrdersTable` (walidacja source/speed_id); `SpeedOrder` Entity (docblock); `templates/SpeedOrders/add.php`; `templates/SpeedOrders/index.php` (badge + filtr + przycisk); `templates/SpeedOrders/view.php` (Edytuj/Usuń); `routes.php`; `permissions.php` |
| 2026-08-04 | Fix: KSeF FA(3) walidacja XSD dla faktur AT — KodUE musi być 2-znakowy (AT, nie ATU), NrVatUE bez literowego kodu kraju (Austria: `U12345678`). Nowe helpery `kodUEForCountry()` + `stripCountryPrefixFromVatUE()` | `InvoicesController.php` |
| 2026-07-10 | Fix: planer tras — koszty per pojazd + widoczność parametrów HERE. Dodano pole `vehicles.fuel_consumption_l_per_100km` + `fuel_type` (poprzednio spalanie hardcoded 30 l/100km w JS niezależne od pojazdu). Auto-fill w planerze przy wyborze pojazdu (jak dla stawki kierowcy). Nowy przycisk „Co poszło do HERE" pokazuje wszystkie parametry vehicle[grossWeight/axleCount/weightPerAxle/height/width/length/emissionType/tunnelCategory/shippedHazardousGoods] wysłane do routing API. Warning alert w edycji naczepy gdy brak `axle_count`/`gross_weight_kg` (zaniża tolls). W dropdownie naczep w planerze prefix „⚠️" + txt „braki!" dla niepełnych. Checkbox „Doliczaj amortyzację" w kalkulatorze — bierze `trailers.amortization_per_day_pln` × dni trasy | `AddFuelConsumptionToVehicles` migracja; `Service/Routing/HereRoutingService.php` (dodane `sent_to_here` w response); `templates/element/vehicles/form.php`, `templates/element/trailers/form.php`, `templates/RoutePlanner/index.php` |
| 2026-07-10 | Feat: planer operacyjny — FALA 4 (live tracking + analytics + automatyzacje) — 2 nowe tabele: `trip_events` (timeline zdarzeń z mobile view kierowcy /kierowca/{token} + POD upload z aparatu telefonu + geolokalizacja), `return_load_candidates` (matching engine dla ładunków powrotnych z score 0-100 + cascade query po własnych speed_orders). Analytics dashboard `/analytics` (KPI + top tras/klientów + trend miesięczny). Compliance auto-check przy Wyślij ofertę (vehicle_maintenance + driver_time_logs → compliance_events). Cron `bin/cake alerts` z emailem 30 dni przed wygasnięciem badań (idempotent przez alert_sent_at) | 2 migracje + Tables/Entities `TripEvent`, `ReturnLoadCandidate`; kontrolery `TripEventsController` (public token+mobile view), `ReturnLoadsController`, `AnalyticsController`; `AlertsCommand`; templates `TripEvents/{for_order,driver_view,driver_error}.php`, `ReturnLoads/for_plan.php`, `Analytics/index.php`, `email/html/vehicle_expiring.php`; integracja compliance-check w `RouteOffersController::create` + JS w `RoutePlanner/index.php`; `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-07-10 | Feat: planer operacyjny — FALA 3 (zasoby + compliance) — 4 nowe tabele: `vehicle_maintenance` (badania/ADR/OC z auto-alertem), `driver_time_logs` (UE 561/2006 z helperami weeklyStatus), `driver_availability` (7-dniowe wzorce z preferencjami ADR/noc/weekend), `compliance_events` (append-only log ryzyk z „akceptuję ryzyko" i uzasadnieniem do audytu ITD). CRUD `/serwisy`, `/czas-pracy`, `/dostepnosc-kierowcow`, `/ryzyko`. AJAX dla planera: `/serwisy/wygasajace.json`, `/czas-pracy/status/{driverId}.json`. Helper `ComplianceEventsTable::record()` do zbierania ostrzeżeń z innych modułów | 4 migracje + Tables/Entities `VehicleMaintenance`, `DriverTimeLog`, `DriverAvailability`, `ComplianceEvent`; kontrolery `VehicleMaintenanceController`, `DriverTimeLogsController`, `DriverAvailabilityController`, `ComplianceEventsController`; templates dla wszystkich; `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-07-10 | Feat: planer operacyjny — FALA 2 (workflow ofertowy) — AJAX `pricingHistory` z cascade query historii stawek klienta (poziom 1-3) + UI panel „Historia stawek" pod tabelą tolls w planerze z alertem dumpingu; tabela `route_offers` (draft→sent→viewed→accepted/rejected) + CRUD `/oferty` + publiczny wgląd klienta `/oferty/wglad/{token}` bez logowania + email HTML; przycisk „Wyślij ofertę" w hero planera → modal z prefillem waypoints + integracja z `route_plans` (Fala 1) | migracja `CreateRouteOffers`, `RouteOffersTable`+Entity, `RouteOffersController`; nowe metody `RoutePlannerController::pricingHistory`; `templates/RouteOffers/{index,view,access_by_token}.php`; `templates/email/html/route_offer.php`; `templates/RoutePlanner/index.php` (panel historii + modal Wyślij ofertę + JS); `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-07-10 | Feat: planer operacyjny — FALA 1 (fundament) — 5 nowych tabel: `route_plans`, `route_plan_legs`, `driver_schedules`, `vehicle_schedules`, `operational_events` (append-only event bus). CRUD dla grafików: `/grafik-kierowcow` + `/grafik-pojazdow` + AJAX endpointy „kto wolny w oknie X-Y" gotowe dla planera tras. Helper `OperationalEventsTable::log()` do dopisywania w każdym module | 5 migracji + Tables/Entities `RoutePlan(Legs)`, `DriverSchedule`, `VehicleSchedule`, `OperationalEvent`; `DriverSchedulesController`, `VehicleSchedulesController`; `templates/DriverSchedules/*`, `templates/VehicleSchedules/*`; `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-07-09 | Feat: zestawy pojazd+naczepa+kierowca `/zestawy` — nazwane kombinacje, CRUD, auto-fill w planerze tras (jeden click ustawia ciągnik/naczepę/kierowcę), domyślny zestaw firmy | `VehicleCombinationsController.php`, `VehicleCombinationsTable.php`, `VehicleCombination.php`, `templates/VehicleCombinations/*`, migracja `CreateVehicleCombinations`, `RoutePlannerController.php`, `templates/RoutePlanner/index.php`, `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-07-09 | Feat: kategorie tolls per typ zestawu `/admin/vehicle-type-categories` — CRUD mapowań (Standard/Mega/… × kraj × system) + AJAX endpoint `for-type/{type}` + integracja w planerze tras (nadpisuje auto-klasyfikację) | `VehicleTypeCategoriesController.php`, `VehicleTypeCategoriesTable.php`, `VehicleTypeCategory.php`, `templates/VehicleTypeCategories/*`, migracja `CreateVehicleTypeCategories`, `templates/RoutePlanner/index.php`, `routes.php`, `permissions.php`, `layout/default.php` |
| 2026-05-28 | Feat: Kanban rozliczeń `/rozliczenia/kanban` — 6 kolumn (W terminie, Wysłane, Za 7 dni, Przeterminowane, Spór, Opłacone), drag-drop, kebab menu na karcie, notatki + activity log, snooze, severity gradient, mini-stats (DSO, Inkaso, At-risk), saved views (localStorage), bulk actions, compact mode, assign do usera, AI: następna akcja | `ReconciliationsController.php`, `templates/Reconciliations/kanban.php`, `templates/element/Reconciliations/kanban_card.php`, migracje, `InvoiceNotes*`, sidebar |
| 2026-05-27 | Feat: serwis `Mt940TransactionCodes` (pełna mapa mBank z PDF + legacy SWIFT) + popovery z opisem kodów (`A61`, `D50`, `N150` itp.) w liście transakcji | `src/Service/Mt940TransactionCodes.php`, `templates/BankTransactions/transactions.php` |
| 2026-05-27 | Feat: AI parser tytułu przelewu — `/wyciagi/ai-parse-title/{id}` → OpenAI wyciąga numery faktur (system + legacy), modal pokazuje listę z copy/search; fix wyszukiwania faktur (pokazuj WSZYSTKIE gdy user wpisuje query) | `BankTransactionsController.php`, `templates/BankTransactions/transactions.php`, `config/routes.php` |
| 2026-05-27 | Doc: kompletna mapa kolumn `bank_transactions`, `bank_transaction_allocations`, `invoice_payments` + zasada #4a (sprawdzać migracje przed użyciem nazw pól) | `CLAUDE.md` |
| 2026-05-27 | Feat: kalendarz rozliczeń — przelewy MT940 z statusem dopasowania, booking_date jako kotwica, Select2 dla kontrahentów | `ReconciliationsController.php`, `templates/Reconciliations/calendar.php` |
| 2026-05-09 | Feat: panel admina — CRUD klientów portalu (lista, dodawanie, edycja, usuwanie); pozycja w sidebarze | `AdminClientsController.php`, `templates/AdminClients/{index,add,edit}.php`, `templates/layout/default.php` |
| 2026-05-09 | Feat: portal klienta (rola `client`) — moduł "Zlecenia transportowe" wiązany przez NIP, pobieranie CMR i faktur PDF, i18n PL/EN | `ClientPortalController.php`, `ClientProfilesTable.php`, `ClientProfile.php`, `templates/ClientPortal/*`, `resources/locales/en/default.po`, migracja `CreateClientProfiles`, sidebar warunkowy w `templates/layout/default.php` |
| 2026-04-17 | Fix: ReconciliationsController — closure w contain, naprawa błędu `contractor_id` | `ReconciliationsController.php` |
| 2026-04-17 | Feat: modal rozliczania faktury — sekcja przelewów bankowych kontrahenta (AJAX) | `ReconciliationsController.php`, `templates/Reconciliations/index.php`, `config/routes.php` |
| 2026-04-17 | Fix: XML korekty walutowej — P_13_x=0 przy korekcie kursu, prefill pozycji w JS | `InvoicesController.php`, `templates/Invoices/add_correct_currency.php` |
| 2026-04-16 | Feat: wyciągi bankowe MT940 — import, dopasowanie, BankMatchingService | `BankTransactionsController.php`, `BankMatchingService.php`, `Mt940ParserService.php` |
| 2026-04-16 | Feat: rozliczenia — moduł ReconciliationsController + widok | `ReconciliationsController.php`, `templates/Reconciliations/index.php` |

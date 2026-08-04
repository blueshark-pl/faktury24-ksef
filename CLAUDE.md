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

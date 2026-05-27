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

### 4. Nie zgaduj — czytaj kod
Przed modyfikacją zawsze przeczytaj aktualny stan pliku.
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
Synchronizacja z zewnętrznym systemem Speed ERP (zlecenia transportowe).
Powiązane z fakturami przez `invoice_id`.
Obsługuje załączniki CMR (upload + GLightbox).

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

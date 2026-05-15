# CLAUDE.md — faktury24

Instrukcja dla Claude Code. Przeczytaj przed rozpoczęciem pracy w tym repozytorium.

---

## Stack technologiczny

- **PHP 8.1+**, **CakePHP 5.2.x** (NIE CakePHP 4 — różnice opisane niżej)
- **MySQL** — baza danych produkcyjna
- **Bootstrap 5** + **jQuery** + **Select2** — frontend
- **KSeF FA(3)** — integracja z Krajowym Systemem e-Faktur (XML, XSD)
- **CakeDC/Auth** — uwierzytelnianie, 2FA

---

## Struktura projektu

```
src/Controller/InvoicesController.php   — główny kontroler (~7000 linii)
src/Model/Table/InvoicesTable.php       — model faktur
templates/Invoices/                     — szablony widoków
templates/element/Invoices/             — wielokrotnie używane elementy
webroot/assets/js/                      — JS specyficzny dla aplikacji
```

Faktury mają typy: `vat`, `proforma`, `advance` (zaliczka), `final` (końcowa), `correction`, `margin`, `currency`, `rental`, `novat`.

---

## CakePHP 5 — ważne różnice względem CakePHP 4

### Paginacja — zmienione klucze params

W CakePHP 5 `$this->Paginator->params()` zwraca inne klucze niż w CakePHP 4:

| Znaczenie | CakePHP 4 | CakePHP 5 |
|---|---|---|
| Łączna liczba rekordów | `count` | `totalCount` |
| Rekordy na bieżącej stronie | `current` | `count` |
| Numer bieżącej strony | `page` | `currentPage` |
| Liczba stron | `pageCount` | `pageCount` (bez zmian) |

Zawsze używaj: `$params['totalCount']` dla sumy, `$params['currentPage']` dla strony.

### QueryExpression — or_() usunięte

W CakePHP 5 metoda `or_()` została usunięta. Używaj `or()`:

```php
// NIEPOPRAWNE (CakePHP 4):
$exp->or_(['field LIKE' => '%val%'])

// POPRAWNE (CakePHP 5):
$exp->or(['field LIKE' => '%val%'])
```

### contain() + WHERE po asocjacji

Gdy filtrujesz po polach asocjacji `hasOne` w `where()`, dodaj `leftJoinWith()` aby wymusić JOIN:

```php
$query->contain(['InvoiceContractors'])
      ->leftJoinWith('InvoiceContractors')
      ->where(function($exp) use ($q) {
          return $exp->or([
              'Invoices.fullnumber LIKE' => "%$q%",
              'InvoiceContractors.name LIKE' => "%$q%",
          ]);
      });
```

---

## Konwencje kodu

### Daty — format ISO dla KSeF

`FrozenDate`/`Chronos` w CakePHP zwraca daty w locale polskim przez `__toString()` (np. `20.04.2026`). KSeF wymaga formatu `YYYY-MM-DD`. Zawsze wywołuj `->format('Y-m-d')`:

```php
// NIEPOPRAWNE:
$p6 = (string)$frozenDate;   // "20.04.2026" — błąd XSD

// POPRAWNE:
$p6 = method_exists($frozenDate, 'format')
    ? $frozenDate->format('Y-m-d')
    : substr((string)$frozenDate, 0, 10);
```

### Formularze w szablonach — nie zagnieżdżaj

HTML nie obsługuje zagnieżdżonych `<form>`. Jeśli strona ma formularz POST (np. `bulk-actions-form`) i formularz GET (np. filtry), muszą być **obok siebie, nie zagnieżdżone**.

### Przekazywanie zmiennych do widoku

Zawsze upewnij się, że `$this->set(compact(...))` zawiera wszystkie zmienne używane w szablonie — szczególnie zmienne filtrów (`$q`, `$state`, `$from`, `$to`, `$currency`).

---

## Moduł faktur zaliczkowych

### Źródło prawdy = proforma

Przy tworzeniu/edycji faktury zaliczkowej wszystkie dane podatkowe (VAT, GTU, waluta, nazwy pozycji) są pobierane **z proformy**, nie z formularza. Formularz `add_advance.php` przyjmuje tylko:
- kwotę brutto zaliczki
- wybór proformy
- serię, daty, płatność, uwagi

### API proformaDetails

Endpoint `GET /invoices/proformaDetails/{id}?exclude_id={editedId}` zwraca szczegóły proformy z sumą już wystawionych zaliczek. Parametr `exclude_id` wyklucza bieżącą edytowaną fakturę z sumy (aby uniknąć podwójnego liczenia).

### price_mode

Pole `price_mode` na pozycji może być `'net'` (domyślnie) lub `'gross'`. Przy `gross` kontroler odwraca cenę przed zapisem:
```php
$netto = round($price / (1 + $rate / 100), 2);
```

---

## Asocjacje Invoices

```php
hasOne:  InvoiceContractors   (invoice_id)
hasOne:  InvoiceCompanyDetails (invoice_id)
hasOne:  InvoiceRecipients    (invoice_id)
hasMany: InvoiceContents      (invoice_id)
hasMany: InvoiceVatContents   (invoice_id)
hasMany: InvoicePayments      (invoice_id)
hasMany: ChildInvoices        (parent_id) — zaliczki i faktury końcowe do proformy
```

---

## Integracja KSeF

- Tryb KSeF per firma: `companies.ksef_mode` (włączony/wyłączony)
- Schemat XML: **FA(3)** — walidowany przez XSD MF
- Pola krytyczne w XSD: `P_6` (data — typ `xs:date`, musi być `YYYY-MM-DD`)
- UPO logowane w tabeli `ksef_upo_logs`
- Błędy KSeF logowane w `error.log`

### ⚠ Zmiany w XML KSeF — zawsze weryfikuj XSD + broszurę

Zanim cokolwiek dodasz / zmienisz w XML FA(3) (nowy element, nowa wartość enum, nowa flaga, mapowanie pola), **OBOWIĄZKOWO** sprawdź:

1. **`src/faktura.xsd`** — schemat MF FA(3):
   - czy element istnieje (`<xsd:element name="...">`)?
   - czy wartość mieści się w enum (`<xsd:enumeration value="...">`)?
   - jaka jest pozycja w sekwencji (`<xsd:sequence>`) — kolejność jest sztywna
   - jakie ograniczenia (`minOccurs`, `maxOccurs`, typ — `TWybor1`, `TZnakowy`, `xs:date`, etc.)
2. **`webroot/broszura-informacyjna-dotyczaca-struktury-logicznej-fa-3.pdf`** lub **`_broszura_text.txt`** — oficjalna broszura informacyjna MF:
   - opis pola, kontekst prawny, podstawa ustawowa
   - przykłady wypełnienia
   - reguły biznesowe niewyrażone w XSD

Pułapki, które już się zdarzyły:
- `RodzajFaktury` enum **nie zawiera `FP`** — dla faktury do paragonu używamy osobnej flagi `<FP>1</FP>` w sekwencji `Fa` (nie `RodzajFaktury=FP`)
- FA(3) nie ma elementów `<NrParagonu>` / `<DataParagonu>` — dane paragonu idą jako `<DodatkowyOpis>` z parą Klucz/Wartość (broszura, Przykład 17)
- `<DataZaplaty>` musi być formatowane przez `->format('Y-m-d')` — **nigdy** `(string)$cakeDate` (Polish locale → `DD.MM.YYYY` → walidator XSD odrzuca)
- Dla `np` / `nie podl.` rozróżniamy przez słowo `spoza` w nazwie:
  - `nie podl. spoza UE` → `np I` (P_13_8) — poza terytorium kraju, nabywca spoza UE
  - `nie podl. UE` → `np II` (P_13_9) — wewnątrzwspólnotowe usługi, art. 100 ust. 1 pkt 4

**Kierunek myślenia**: jeśli broszura mówi „pole [fakultatywne]" i XSD ma `minOccurs="0"` — emitujemy tylko gdy mamy wartość. Pusty element często wywala walidację.

---

## UI / Frontend

- **Bootstrap 5** — klasy utility, karty, modalne, formularze
- **Select2** — selecty z wyszukiwaniem (`js-example-*`, `.select2-client-search`)
- **countrySelect.js** — własny picker kraju z flagą (element `contractor_country_select.php`)
  - inicjalizowany przez `window.__csQueue` / `window.__flushCsQueue()`
- **SweetAlert2** (`Swal`) — potwierdzenia akcji bulk, dialogi
- Bulk-actions na liście faktur działają przez **AJAX** (`bulkAjax()`), nie przez submit formularza

---

## Środowisko produkcyjne

- Domena: `faktury24.com`
- Serwer: `/home/jjgroup1srv/domains/faktury24.com/public_html/`
- Logi błędów: `error.log` w katalogu projektu
- Format logu: `[ERR-XXXXXXXX] HTTP 5xx | URL: ... | Exception: ...`

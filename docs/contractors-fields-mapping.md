# Kontrahenci — mapowanie pól DB ↔ formularz

Dokumentacja wszystkich pól w tabeli `contractors` (+ powiązanej `contractors_settings`), wraz z odwzorowaniem na pola formularza w modalu „Dodaj/Edytuj kontrahenta" (`templates/Contractors/index.php`) i logiką po stronie backendu.

Stan na: **2026-05-15**.

---

## 1. Tabela `contractors`

| Kolumna | Typ | Null | Domyślnie | Opis biznesowy |
|---|---|---|---|---|
| `id` | uuid (CHAR 36) | NO | (uuid) | PK |
| `company_id` | uuid | NO | — | FK → companies.id (właściciel rekordu) |
| `name` | string(255) | YES | NULL | Nazwa firmy lub pełne imię+nazwisko (po sklejeniu w trybie osoba) |
| `altname` | string(160) | YES | NULL | Skrócona nazwa firmy (legacy — pole usunięte z UI 2026-05) |
| `first_name` | string(80) | YES | NULL | Imię (tylko gdy `is_person=1`) |
| `last_name` | string(80) | YES | NULL | Nazwisko (tylko gdy `is_person=1`) |
| `is_person` | boolean (TINYINT) | NO | 0 | **1 = osoba fizyczna, 0 = firma**. Źródło prawdy ustalane przez chip-picker w formularzu (hidden input). |
| `nip` | string(20) | YES | NULL | NIP PL **lub** zagraniczny VAT-ID bez prefiksu (zaakceptowane, walidator nie wymusza checksumy) |
| `pesel` | string(11) | YES | NULL | PESEL — tylko dla osób fizycznych, opcjonalny |
| `regon` | string(14) | YES | NULL | REGON — obecnie nie używany w UI |
| `eu_vat` | boolean | YES | 0 | **DEPRECATED** — legacy flag „kontrahent UE". Aktualnie używamy `vat_prefix` + `vat_eu`. |
| `vat_prefix` | string(8) | YES | NULL | Prefiks VAT-UE (np. `DE`, `PL`, `FR`). Wartość `NONE` oznacza, że kontrahent jest **spoza UE** (chip „Spoza UE"). |
| `vat_eu` | string(32) | YES | NULL | Numer VAT-UE bez prefiksu (np. `123456789` dla DE123456789) |
| `eori` | string(32) | YES | NULL | Numer EORI (opcjonalny w trybie VAT-UE) |
| `tax_id_other` | string(64) | YES | NULL | Inny identyfikator podatkowy (NrID w XSD FA(3)) — tylko gdy `vat_prefix='NONE'` |
| `tax_id_other_country` | string(8) | YES | NULL | Kod ISO2 kraju dla `tax_id_other` |
| `country` | string(8) | YES | `PL` | ISO2 kraju adresu (uppercase) |
| `postal_code` | string(16) | YES | NULL | Kod pocztowy. Dla PL wymagany format `NN-NNN`. |
| `city` | string(120) | YES | NULL | Miejscowość |
| `street` | string(255) | YES | NULL | Ulica i nr (pole łączone — wcześniej osobne `local_number`) |
| `local_number` | string(32) | YES | NULL | **DEPRECATED** — usunięte z UI, zostaje dla legacy danych |
| `correspondence_country` | string(8) | YES | NULL | Kraj adresu korespondencyjnego (legacy) |
| `correspondence_postal_code` | string(16) | YES | NULL | (legacy) |
| `correspondence_city` | string(120) | YES | NULL | (legacy) |
| `correspondence_street` | string(255) | YES | NULL | (legacy) |
| `email` | string(255) | YES | NULL | Adres e-mail |
| `phone` | string(40) | YES | NULL | Telefon — dowolny format, bez wymuszania E.164 (`intl-tel-input` wyłączony) |
| `gln` | string(13) | YES | NULL | Global Location Number — obecnie nie używany w UI |
| `notes` | text | YES | NULL | Notatki swobodne |
| `privacy_consent` | boolean | YES | 0 | Zgoda RODO (legacy, usunięte z UI) |
| `privacy_basis` | string(16) | YES | NULL | Podstawa prawna (legacy, usunięte z UI) |
| `is_active` | boolean | NO | 1 | **1 = aktywny, 0 = nieaktywny**. Checkbox w formularzu (UWAGA: CakePHP renderuje hidden + checkbox; JS targetuje `input[type="checkbox"][name="is_active"]`). |
| `created` | datetime | NO | — | (timestamp) |
| `modified` | datetime | NO | — | (timestamp) |

Indeksy: `company_id`, unique `(company_id, nip)` gdy nip nie-null.

---

## 2. Tabela `contractors_settings`

Per-kontrahent ustawienia powiadomień i wysyłki faktur.

| Kolumna | Typ | Domyślnie | Opis |
|---|---|---|---|
| `id` | integer (AI) | — | PK |
| `company_id` | uuid | — | FK → companies.id |
| `contractor_id` | uuid | — | FK → contractors.id (unique) |
| `share_invoices` | boolean | 0 | Czy udostępniać faktury w panelu kontrahenta |
| `notify_sms` | boolean | 0 | Powiadomienia SMS |
| `notify_email` | boolean | 1 | Powiadomienia e-mail |
| `notify_invoice_message` | text | NULL | Szablon wiadomości (legacy) |
| `attach_invoice_pdf_mode` | string(16) | `inherit` | `inherit` / `always` / `never` — czy załączać PDF do e-maila |
| `created`, `modified` | datetime | — | timestampy |

UI: osobny modal `#contractor-settings` (chwilowo usunięty z dropdownu akcji, ale model i kontroler `ContractorsSettings` działają — można przywrócić zewnętrzny przycisk).

---

## 3. Mapowanie DB → formularz (modal `#contractor-create`)

### 3.1 Sekcja: Dane podstawowe (`#type-switch-section` + nazwa)

| Pole formularza | name= | Kolumna DB | Tryb | Notatka |
|---|---|---|---|---|
| Chip „Firma" / „Osoba fizyczna" | (UI) | `is_person` | oba | Ukryty checkbox `#use-pesel` jako źródło prawdy + hidden `name="is_person"` (id `is-person-hidden`) wysyłane do backendu. |
| Nazwa firmy | `name` | `name` | firma | Wymagane gdy `is_person=0`. |
| Imię | `first_name` | `first_name` | osoba | Wymagane gdy `is_person=1`. |
| Nazwisko | `last_name` | `last_name` | osoba | Wymagane gdy `is_person=1`. Backend skleja `first_name + last_name → name` przed zapisem. |

### 3.2 Sekcja: Identyfikacja kontrahenta (`#identification-section`) — chip-picker

3 chipy w `#id-type-chips` + ukryte pole `<input name="id_type" id="id-type-hidden" value="...">` (`nip_pl` / `vat_eu` / `non_eu`) — informacyjne, **nie zapisywane do DB**, używane tylko po stronie klienta do wyboru aktywnego panelu.

#### Panel: NIP PL (`data-id-panel="nip_pl"`)
| Pole | name= | DB | Notatka |
|---|---|---|---|
| NIP polski | `nip` | `nip` | + przycisk „Pobierz z GUS" pre-fillujący name/street/city/postal_code |
| (osoba) NIP opcjonalny | (UI `#person-nip` — bez name) | `nip` | Wartość przepisywana do `nip` w submit handlerze (`if (usePesel?.checked && personNipInput.value) fd.set('nip', personNipInput.value)`). Switch `#show-nip` decyduje o widoczności. |

#### Panel: VAT UE (`data-id-panel="vat_eu"`)
| Pole | name= | DB | Notatka |
|---|---|---|---|
| Prefiks UE (countrySelect) | `vat_prefix` | `vat_prefix` | `onlyCountries`: 27 krajów UE. Ukryty `vat-prefix-hidden` ISO2 uppercase. |
| Numer VAT-UE | `vat_eu` | `vat_eu` | Bez prefiksu kraju. |
| EORI (opcjonalnie) | `eori` | `eori` | — |

#### Panel: Spoza UE (`data-id-panel="non_eu"`)
| Pole | name= | DB | Notatka |
|---|---|---|---|
| Kraj (NrID) (countrySelect) | `tax_id_other_country` | `tax_id_other_country` | `excludeCountries`: 27 krajów UE — picker pokazuje tylko państwa spoza UE. |
| Identyfikator podatkowy | `tax_id_other` | `tax_id_other` | — |
| (przy zapisie wymuszone) | `vat_prefix` | `vat_prefix` | Submit ustawia `vat_prefix='NONE'` jako marker „spoza UE". |

### 3.3 Sekcja: Dane kontaktowe

| Pole | name= | DB | Notatka |
|---|---|---|---|
| Email | `email` | `email` | — |
| Telefon | `phone` | `phone` | Bez `intl-tel-input` (pole plain text, dowolny format). |
| PESEL (osoba, opcjonalnie) | `pesel` | `pesel` | Pokazywane po włączeniu switcha `#show-pesel`. Walidacja PESEL (suma kontrolna). |

### 3.4 Sekcja: Dane adresowe

| Pole | name= | DB | Notatka |
|---|---|---|---|
| Kraj (countrySelect) | `country` (hidden) | `country` | UI input `#country-ui` (bez name), hidden `country-hidden` przechowuje ISO2 uppercase. |
| Miejscowość | `city` | `city` | Wymagane gdy `country='PL'`. |
| Ulica i nr | `street` | `street` | Wymagane gdy `country='PL'`. |
| Kod pocztowy | `postal_code` | `postal_code` | Wymagane gdy `country='PL'`. |

### 3.5 Notatki + status

| Pole | name= | DB | Notatka |
|---|---|---|---|
| Notatki | `notes` | `notes` | textarea |
| Aktywny | `is_active` (checkbox) | `is_active` | CakePHP renderuje **2 inputy**: hidden `value="0"` + checkbox `value="1"`. JS w trybie edycji musi targetować precyzyjnie `input[type="checkbox"][name="is_active"]`, inaczej `chk.checked = false` ustawia ukryty input i nie ma efektu. |

---

## 4. Mapowanie z `viewJson` (ContractorsController::viewJson) — używane przy edycji

Endpoint `GET /contractors/view-json/{id}` zwraca JSON `{success, contractor: {...}}` zawierający:

```
id, name, altname, first_name, last_name, is_person, nip, pesel,
email, phone, country, postal_code, city, street, local_number,
correspondence_*, notes, privacy_consent, privacy_basis, is_active,
vat_prefix, vat_eu, eori, tax_id_other, tax_id_other_country
```

JS w `bindEditHandler` (templates/Contractors/index.php) wstawia te wartości w odpowiednie pola formularza, dobiera aktywny chip na podstawie:
- `vat_prefix === 'NONE'` lub `tax_id_other_*` ustawione → chip **non_eu**,
- `vat_prefix` (≠NONE) lub `vat_eu`/`eori` ustawione → chip **vat_eu**,
- inaczej → chip **nip_pl**.

Typ kontrahenta: `is_person === 1` → chip „Osoba", inaczej „Firma".

---

## 5. Walidatory (`ContractorsTable::validationDefault`)

### `nipOrPesel` (dla pola `nip`)
1. Preferuj `$data['is_person']` (jawna flaga z chip-pickera). Fallback: heurystyka po polach (`first_name`/`last_name` vs `name`).
2. Dla **firmy**: NIP wymagany **chyba że** wypełnione są międzynarodowe identyfikatory (`vat_prefix`, `vat_eu`, `eori`, `tax_id_other`).
3. Dla **osoby**: brak wymagań (PESEL i NIP opcjonalne).
4. Jeśli PESEL podany — sprawdzana suma kontrolna.

### `nameOrFirstLast` (dla pola `name`)
1. Preferuj `$data['is_person']`. Fallback: heurystyka.
2. **Osoba**: wymaga `first_name` i `last_name`.
3. **Firma**: wymaga `name`.

### Adres (`postal_code`, `city`, `street`)
Wymagane gdy `country='PL'` (walidacja stosowana w JS przez `applyCountryRequirements`; w backendzie brak twardej walidacji).

---

## 6. Submit logic (`form#contractor-form` submit handler w `index.php`)

Kolejność operacji:

1. **HTML5 `checkValidity()`** — sprawdza `required` na widocznych polach.
2. **NIP/PESEL compliance**:
   - Person mode: jeśli PESEL podany, waliduj sumę kontrolną.
   - Company mode: NIP wymagany tylko dla chip `nip_pl`.
3. **Sync countrySelect → hidden** (`vat_prefix` i `tax_id_other_country` pobierane z plugina, gdyby `change` event się nie odpalił).
4. **Czyszczenie pól zależnie od `id_type`** (chip):
   - `nip_pl` → czyści `vat_prefix`, `vat_eu`, `eori`, `tax_id_other`, `tax_id_other_country`.
   - `vat_eu` → czyści `nip`, `tax_id_other`, `tax_id_other_country`. Wymusza `vat_prefix` z hidden.
   - `non_eu` → czyści `nip`, `vat_eu`, `eori`. Wymusza `vat_prefix='NONE'`.
5. **Person NIP** (jeśli `#use-pesel` zaznaczony i `#person-nip` wypełniony) → przepisuje do `nip` w FormData.
6. POST do `/contractors/add` lub `/contractors/edit/{id}` (`_method=PUT`).

---

## 7. Backend (ContractorsController) — kluczowe poprawki post-refactor

- `add()` i `edit()` — przy detekcji `is_person` **preferują jawną flagę z formularza**, heurystykę po polach traktują tylko jako fallback (dla API/legacy).
- `viewJson()` — SELECT zawiera m.in. `is_person`, `vat_prefix`, `vat_eu`, `eori`, `tax_id_other`, `tax_id_other_country` (potrzebne do poprawnego pre-fill chipa w trybie edycji).
- `invoices()` — porównanie po `contractor_id` jako **string** (UUID), nie `(int)` — wcześniejszy bug sprawiał, że tabela faktur była zawsze pusta.

---

## 8. Znane ograniczenia / TODO

- `altname`, `local_number`, `correspondence_*`, `privacy_consent`, `privacy_basis`, `eu_vat`, `regon`, `gln` — pola zachowane w schemacie dla zgodności wstecznej, **usunięte z UI**. Wartości historyczne pozostają w bazie. Można je w przyszłości zmigrować lub usunąć.
- `gln` — zostawione na wypadek przyszłej obsługi GLN (Peppol/EDI).
- Walidator `regon` checksum istnieje w modelu, ale pole nie jest aktualnie pokazywane w formularzu.
- Sekcja „recipient" (dodawanie odbiorcy razem z kontrahentem) — ~80 linii JS pozostaje jako dead code; markup w modalu został wycięty. Do sprzątnięcia w osobnym commicie.

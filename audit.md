# Audyt funkcjonalności – Faktury24 (nowy system)

Data: 2026-03-11
Projekt: `g:\2025\partnersc\faktury24` (CakePHP 5)

Legenda statusów:
- ✅ **JEST** — funkcjonalność zaimplementowana i obecna w kodzie
- ⚠️ **CZĘŚCIOWO** — zaczęte / szkielet istnieje, ale wymaga dokończenia
- ❌ **BRAK** — nie znaleziono implementacji w kodzie

---

## 1. Faktury niewysłane do KSeF

**Status: ✅ JEST**

System posiada pełny workflow „draft → issued → sent → error":

| Element | Obecny | Pliki |
|---|---|---|
| Kolumna `workflow_status` (draft/issued/sent/error) | ✅ | `20260220124500_AddDraftWorkflowToInvoices.php` |
| Kolumna `planned_ksef_send_at` (scheduler) | ✅ | jw. |
| Kolumna `ksef_mode_enabled` na firmie | ✅ | `20260220103000_AddKsefModeEnabledToCompanies.php` |
| Widok listy draftów (niewysłanych) | ✅ | `templates/Invoices/drafts.php`, `AppController.php` ($draftInvoicesCount) |
| Wysłanie natychmiastowe `sendToKsef()` | ✅ | `InvoicesController.php` (linia ~4125) |
| Scheduler wsadowy `runPlannedDrafts()` | ✅ | `InvoicesController.php` (linia ~4228) |
| Planowanie daty wysyłki `scheduleDraft()` | ✅ | `InvoicesController.php` (linia ~811) |
| Logi wysyłek `ksef_send_logs` | ✅ | `20260220140500_AddUpoStorageAndKsefSendLogs.php` |
| Przechowywanie `ksef_number`, `ksef_session_reference`, `ksef_invoice_reference` | ✅ | `20251105120000_AddKsefReferencesToInvoices.php` |

**Uwagi:** Kompletny flow. Faktura może być wystawiona jako draft (bez wysyłki), a następnie wysłana ręcznie lub automatycznie wg harmonogramu. Flaga `ksef_mode_enabled` umożliwia włączanie/wyłączanie trybu KSeF per firma.

---

## 2. Rachunki zamiast faktury bez VAT (podmioty zwolnione)

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| Typ dokumentu `novat` (Faktura bez VAT) | ✅ | `CompaniesController.php:507`, formularz `add_no_vat.php` |
| Korekta faktury bez VAT | ✅ | `add_correct_no_vat.php` |
| Pole `vat_payer` na firmie (podatnik VAT?) | ✅ | `20251002120000_CreateCompanies.php:27` |
| Obsługa zwolnienia z VAT w XML (P_19, P_19A/B/C, P_19N) | ✅ | `InvoicesController.php:5058–5110` |
| Typ dokumentu „rachunek" (osobny od „Faktura bez VAT") | ❌ | Brak |

**Uwagi:** System obsługuje **Faktury bez VAT** oraz prawidłowo taguje sprzedaż zwolnioną w XML KSeF. Natomiast **brak osobnego typu „Rachunek"** — podmioty zwolnione z VAT (art. 113 u.p.t.u.) powinny wystawiać rachunki, nie faktury. W obecnym systemie zawsze generowany jest dokument „Faktura bez VAT", co może być wystarczające formalnie (KSeF nie wymaga rachunków), ale nie jest w pełni poprawne dla podmiotów niezarejestrowanych do VAT. Do rozważenia: czy to realne wymaganie w kontekście KSeF.

---

## 3. Faktury zaliczkowe i przyporządkowanie do zamówień

**Status: ✅ JEST**

| Element | Obecny | Pliki |
|---|---|---|
| Typ `proforma` (oferta/zamówienie) | ✅ | `CompaniesController.php:510`, `addProforma()` |
| Typ `advance` (faktura zaliczkowa) | ✅ | `CompaniesController.php:511`, `addAdvance()` |
| Powiązanie advance → proforma (`parent_id`) | ✅ | `InvoicesController.php:1271–1328` |
| Walidacja kwoty zaliczki vs proforma | ✅ | `InvoicesController.php:1296–1306` (sprawdza sumę wcześniejszych zaliczek) |
| Faktura końcowa (rozliczeniowa) `final` | ✅ | `InvoicesController.php:641–654` |
| Widok z linkowaniem proforma → zaliczki → końcowa | ✅ | `InvoicesController.php:591–750` (advanceCounts, advancesByProforma, finalByProforma) |
| Formularz dodawania zaliczki | ✅ | `templates/Invoices/add_advance.php` |
| Korekty faktur zaliczkowych | ✅ | XML: `InvoicesController.php:4822` |
| Uprawnienia `addProforma`, `addAdvance`, `proformaSearch`, `proformaDetails` | ✅ | `permissions.php:169–187` |

**Uwagi:** Pełna obsługa cyklu proforma → zaliczka(i) → faktura końcowa. Dane kontrahenta kopiowane z proformy. Sprawdzane jest czy suma zaliczek nie przekracza kwoty proformy. Każda zaliczka jest powiązana z parent_id (proformą).

---

## 4. Pobranie listy towarów, usług oraz kontrahentów ze starego systemu

**Status: ✅ JEST**

| Element | Obecny | Pliki |
|---|---|---|
| Endpoint eksportu w starym systemie (JSON API) | ✅ | `faktur24com/models/api_export.php` (350 linii) |
| Eksport kontrahentów starego systemu | ✅ | `GET /api_export/contractors/{bu_uuid}` |
| Eksport produktów/usług starego systemu | ✅ | `GET /api_export/products/{bu_uuid}` |
| Eksport faktur z pozycjami i VAT | ✅ | `GET /api_export/invoices/{bu_uuid}` |
| Eksport listy firm (businesses) | ✅ | `GET /api_export/businesses` |
| Komenda CLI importu w nowym systemie | ✅ | `src/Command/ImportLegacyCommand.php` (777 linii) |
| Auto-match firm po NIP | ✅ | `--auto-match` — pobiera firmy z obu systemów, łączy po NIP |
| Import kontrahentów | ✅ | deduplikacja po NIP, mapowanie pól |
| Import produktów/usług | ✅ | mapowanie VAT i jednostek, deduplikacja po nazwie |
| Import faktur (nagłówki + pozycje + VAT) | ✅ | pełne mapowanie typów, metod płatności, stanów |
| Tryb dry-run | ✅ | `--dry-run` — podgląd bez zapisu |
| Autentykacja API (shared secret) | ✅ | header `X-Export-Key`, paginacja |
| Eksport CSV kontrahentów (nowy system) | ✅ | `ContractorsController::exportCsv` |
| Eksport CSV produktów (nowy system) | ✅ | `ProductsController` |

**Uwagi:** Pełny pipeline import/eksport zbudowany:

1. **Stary system** (`faktur24com/models/api_export.php`) — 4 endpointy JSON z autentykacją `X-Export-Key`, paginacja, eksport businesses/contractors/products/invoices.
2. **Nowy system** (`src/Command/ImportLegacyCommand.php`) — komenda CakePHP CLI z dwoma trybami:
   - `--auto-match` — automatyczne dopasowanie firm po NIP między systemami i import danych dla każdej pary,
   - ręczny: `--company-id=<UUID> --bu-uuid=<UUID>` dla pojedynczej firmy.
3. **Mapowanie danych:** VAT (po nazwie/stawce), jednostki (z aliasami: szt., h→godz., itp.), typy faktur (vat→invoice, korekta→correction, zaliczkowa→advance, marza→margin), metody i stany płatności.
4. **Deduplikacja:** kontrahenci po NIP, produkty po nazwie, faktury po numerze (`fullnumber`).

**Niezweryfikowane:** Endpoint eksportu nie był jeszcze wdrożony na serwer produkcyjny (`faktury.partnersc.com`). Przed uruchomieniem importu należy:
- Wgrać `api_export.php` na serwer starego systemu.
- Przetestować: `curl -H "X-Export-Key: ..." https://faktury.partnersc.com/api_export/businesses`
- Uruchomić: `bin/cake import_legacy --auto-match --dry-run`

---

## 5. Dostęp do rozliczonych faktur w starym systemie

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| Import faktur ze starego systemu do nowego | ✅ | `ImportLegacyCommand` — migruje faktury z pozycjami i VAT |
| Widok/link do faktur ze starego F24 | ❌ | Brak osobnego widoku „archiwum" |
| Proxy/bridge do starego systemu (read-only) | ❌ | `bridge/` i `bridge-laravel/` dotyczą KSeF API |
| Dane historyczne rozliczeń | ⚠️ | Faktury można zaimportować, ale brak pól rozliczeniowych (np. stan opłacenia w kontekście księgowym) |

**Uwagi:** Po wdrożeniu importu (pkt 4) faktury ze starego systemu trafią do tabel nowego systemu — będą widoczne na standardowej liście faktur. Natomiast:
- Brak osobnego widoku „archiwum starego systemu" (zaimportowane faktury mieszają się z nowymi).
- Brak pól związanych z rozliczeniami księgowymi (np. data księgowania, dekret).
- **Po uruchomieniu importu** ten punkt będzie w dużej mierze rozwiązany — faktury będą dostępne w nowym systemie.

---

## 6. Dostęp do archiwum faktur wystawionych w starym systemie

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| Migracja faktur do tabel nowego systemu | ✅ | `ImportLegacyCommand` importuje nagłówki, pozycje, podsumowanie VAT |
| Migracja kontrahentów i produktów | ✅ | Importowane razem z fakturami |
| Archiwum / tabela `legacy_invoices` | ❌ | Faktury trafiają do standardowych tabel (nie osobnych archiwum) |
| Widok „archiwum starego systemu" | ❌ | Brak filtra/oznaczenia zaimportowanych faktur |
| PDF/pliki archiwalne ze starego systemu | ❌ | Brak migracji PDF-ów |

**Uwagi:** Dzięki komendzie `import_legacy` (pkt 4) dane archiwalne zostaną przeniesione do standardowych tabel nowego systemu. Zaimportowane faktury będą dostępne na liście i w podglądzie jak każda inna faktura. Pozostaje do rozważenia:
- Oznaczenie zaimportowanych faktur flagą (np. `is_legacy=true`) dla łatwego filtrowania.
- Migracja plików PDF ze starego systemu (jeśli generowane były PDFy).
- Ewentualny widok „Archiwum" z filtrem na stare dokumenty.

---

## 7. Dostęp księgowych do nowego Faktury24 (moduł „Twoi Klienci")

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| Tabela `accounting_authorizations` | ✅ | `20251022120112_CreateAccountingAuthorizations.php` |
| CRUD tokenów integracji księgowej | ✅ | `AccountingAuthorizationsController.php` |
| Widoki: dodaj token, lista tokenów, szczegóły, weryfikacja | ✅ | `templates/AccountingAuthorizations/` (add, index, view, check) |
| Integracja z systemem księgowym (wFirma, inFakt, enova365, Optima) | ✅ | UI mówi o wklejaniu tokenów zewnętrznych systemów |
| Moduł „Twoi Klienci" – lista firm klienta dla księgowego | ❌ | Brak |
| Pobieranie faktur klientów przez księgowego (jak w starym systemie) | ❌ | Brak |
| Rola/uprawnienie „księgowy" z dostępem multi-firma | ❌ | Brak |

**Uwagi:** Istnieje mechanizm tokenów autoryzacyjnych do łączenia z zewnętrznym systemem księgowym (API push/pull). Natomiast **brak dedykowanego modułu „Twoi Klienci"** pozwalającego księgowemu na:
- logowanie jednym kontem i przeglądanie faktur wielu firm-klientów,
- pobieranie zbiorczych plików (PDF/CSV/XML) dla klientów,
- switch kontekstu między firmami.

To jest istotna luka — w starym systemie księgowi mieli taki panel.

---

## 8. Kwestia wysyłki faktur do KSeF przez nasze biuro

**Status: ✅ JEST (infrastruktura)**

| Element | Obecny | Uwagi |
|---|---|---|
| Wysyłka do KSeF per firma (cert master) | ✅ | `ksef.php` — `masterCertDir`, `MasterCertProvider.php` |
| Fabryka klientów KSeF (`KsefFactory`) | ✅ | `KsefFactory.php` |
| Obsługa cert/p12/PEM | ✅ | Konfiguracja w `ksef.php` i `app_local.php` |
| Środowiska test/prod | ✅ | Konfiguracja `apiUrl`, env-aware routes |
| Autoryzacja KSeF (sesja, grant, token) | ✅ | `KsefAuthorizationsController.php`, `KsefSessionService.php` |
| Uprawnienia wysyłki (`sendToKsef`) | ✅ | `permissions.php:196` |
| Pobieranie `ksefSchedulerKey` dla crona | ✅ | `app.php:65` |

**Uwagi:** Infrastruktura do wysyłki faktur w imieniu firm jest gotowa — certyfikat master, sesje KSeF, autoryzacja. Kluczowe jest:
- Upewnić się, że certyfikat produkcyjny (master PFX/P12) jest wgrany na serwer.
- Skonfigurować cron do `runPlannedDrafts`.
- Przetestować na środowisku testowym KSeF (api-test.ksef.mf.gov.pl).

**Niezweryfikowane:** Brak dostępu do serwera produkcyjnego — nie mogę potwierdzić, czy certyfikat master jest wgrany i czy cron jest skonfigurowany.

---

## 9. Pobieranie faktur z nowego systemu do KAPER

**Status: ❌ BRAK**

| Element | Obecny | Uwagi |
|---|---|---|
| Eksport do formatu KAPER (XML/CSV specyficzny) | ❌ | Brak |
| Endpoint API dla KAPER | ❌ | Brak |
| Eksport CSV faktur (ogólny) | ✅ | `InvoicesController.php` ~linia 494–546 |
| Eksport CSV kontrahentów | ✅ | `ContractorsController::exportCsv` |
| Eksport CSV produktów | ✅ | `ProductsController` |

**Uwagi:** System posiada ogólny eksport CSV faktur, kontrahentów i produktów. Natomiast **brak dedykowanego eksportu w formacie KAPER** (Comarch). Do zbudowania:
- Specyfikacja formatu importu KAPER (pola, separatory, kodowanie).
- Endpoint generujący plik w tym formacie.
- Opcjonalnie: automatyczny push/plik na FTP.

---

## 10. Dodatkowe dane na fakturze (kolumny, procedury, firmy powiązane)

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| GTU (GTU_01–GTU_13) na pozycjach | ✅ | `gtu_code` w Products, InvoiceContents, XML `<GTU>` |
| MPP – mechanizm podzielonej płatności | ✅ | `is_split_payment`, XML `P_18A` |
| FP – faktura do paragonu | ✅ | `is_receipt_invoice`, `receipt_number`, `receipt_date` |
| Procedura marży (art. 119/120) | ✅ | `addMargin()`, `margin_type`, XML `<PMarzy>`, `P_PMarzy` |
| Adnotacje w XML KSeF (`<Adnotacje>`) | ✅ | `InvoicesController.php:5032–5071` |
| Zwolnienie z VAT (P_19, P_19A/B/C) | ✅ | `InvoicesController.php:5058–5110` |
| Sprzedaż poza terytorium kraju (P_13_8) | ✅ | `InvoicesController.php:4914` |
| Procedura szczególna dział XII (P_13_5) | ✅ | `InvoicesController.php:4903` |
| TP – transakcje z podmiotami powiązanymi | ❌ | **Brak** flagi TP i `P_17` w XML |
| SW – sprzedaż wysyłkowa PL | ❌ | Brak |
| EE – usługi telekomunikacyjne/elektroniczne | ❌ | Brak |
| I_42 / I_63 – procedury importowe | ❌ | Brak |
| B_SPV / B_SPV_DOSTAWA / B_MPV_PROWIZJA | ❌ | Brak |
| Dodatkowe kolumny użytkownika (custom fields) | ❌ | Brak konfigurowalnych kolumn |
| Firmy powiązane – dedykowane oznaczanie | ❌ | Brak UI i flagi TP |

**Uwagi:** Podstawowe procedury i adnotacje (GTU, MPP, FP, marża, zwolnienie) SĄ zaimplementowane. Brakuje:
- Flagi **TP** (podmioty powiązane) — kluczowe dla firm z transakcjami z powiązanymi podmiotami.
- Procedur **SW, EE, I_42, I_63, B_SPV** itd. — mniej popularne, ale wymagane przez niektórych klientów.
- **Konfigurowalnych kolumn** na fakturze — w starym systemie była taka opcja.

---

## 11. Korekty faktur

**Status: ✅ JEST**

| Element | Obecny | Pliki |
|---|---|---|
| Typ `correction` (Faktura korygująca) | ✅ | DB schema, `addCorrection()` |
| Powiązanie korekta → oryginał (`parent_id`) | ✅ | `InvoicesController.php:1177` |
| `correction_type` (typ skutku korekty) | ✅ | Entity `Invoice`, `InvoicesTable` |
| `correction_reason` | ✅ | `InvoicesController.php:1191` |
| XML KSeF `<DaneFaKorygowanej>` | ✅ | `buildCorrectionHeaderXml()`, `buildFa3XmlCorrection()` |
| Formularz dodawania korekty | ✅ | `add_correct.php` |
| Korekta faktury walutowej | ✅ | `add_correct_currency.php` |
| Korekta faktury marża | ✅ | `add_correct_margin.php` |
| Korekta faktury bez VAT | ✅ | `add_correct_no_vat.php` |
| Preload danych z oryginału | ✅ | `InvoicesController.php:941–942` |
| Blokada edycji wysłanej faktury (tylko korekta) | ✅ | `InvoicesController.php:2189` |
| Uprawnienia `addCorrection`, `editCorrection` | ✅ | `permissions.php:177, 188` |

**Uwagi:** Pełna obsługa korekt: zwykłych, walutowych, marżowych, bez VAT. Poprawnie generowany XML FA(3) z sekcją `DaneFaKorygowanej`. Faktura wysłana do KSeF jest blokowana przed edycją — wymaga korekty.

---

## 12. Tryby awaryjne wystawiania faktur

**Status: ⚠️ CZĘŚCIOWO**

| Element | Obecny | Uwagi |
|---|---|---|
| Monitorowanie statusu KSeF (Latarnia API) | ✅ | `LatarniaKsefClient.php`, `KsefStatusCell.php` |
| Status: AVAILABLE / MAINTENANCE / FAILURE / TOTAL_FAILURE | ✅ | Pobierane i cache'owane |
| Wyświetlanie statusu KSeF w UI (stopka, banner) | ✅ | Cell + widoki + modal „Komunikaty MF" |
| `ksef_mode_enabled` per firma (można wyłączyć KSeF) | ✅ | `AppController.php:157–161` |
| Draft workflow (faktura bez wysyłki) | ✅ | `workflow_status=draft` |
| Automatyczne wstrzymanie wysyłki gdy KSeF niedostępny | ❌ | Brak logiki sprawdzającej status Latarni przed wysyłką |
| Tryb offlinowy z lokalną numeracją awaryjną | ❌ | Brak |
| Notyfikacja do użytkownika o przejściu w tryb awaryjny | ❌ | Seed z notyfikacją o pracach serwisowych istnieje, ale brak automatyki |
| Późniejsze wysłanie paczki po przywróceniu KSeF | ⚠️ | `runPlannedDrafts()` wysyła zaplanowane, ale nie ma osobnej kolejki awaryjnej |

**Uwagi:** System **wie**, że KSeF bywa niedostępny (Latarnia API) i informuje o tym użytkownika. Użytkownik może ręcznie nie wysyłać faktury (draft). Natomiast **brak pełnej automatyki trybu awaryjnego**:
- Przed wysyłką do KSeF NIE sprawdza się statusu Latarni (wysyłka zakończy się błędem, ale nie jest proaktywnie blokowana).
- Brak odrębnego trybu „offline" z osobną numeracją awaryjną wg przepisów.
- Brak automatycznego przejścia „tryb normalny ↔ tryb awaryjny" z odpowiednim logowaniem i powiadomieniem.

---

## Podsumowanie

| # | Funkcjonalność | Status |
|---|---|---|
| 1 | Faktury niewysłane do KSeF | ✅ JEST |
| 2 | Rachunki (podmioty zwolnione) | ⚠️ CZĘŚCIOWO — jest „Faktura bez VAT", brak „Rachunku" |
| 3 | Faktury zaliczkowe + zamówienia | ✅ JEST |
| 4 | Import danych ze starego systemu | ✅ JEST — eksport API + komenda CLI z auto-match po NIP |
| 5 | Dostęp do rozliczonych faktur (stary system) | ⚠️ CZĘŚCIOWO — import faktur gotowy, brak widoku archiwum |
| 6 | Archiwum faktur (stary system) | ⚠️ CZĘŚCIOWO — migracja danych gotowa, brak filtra/oznaczenia legacy |
| 7 | Moduł „Twoi Klienci" dla księgowych | ⚠️ CZĘŚCIOWO — tokeny integracji są, brak panelu multi-firma |
| 8 | Wysyłka KSeF przez nasze biuro | ✅ JEST (infrastruktura) |
| 9 | Eksport do KAPER | ❌ BRAK (jest CSV ogólny) |
| 10 | Dodatkowe dane na fakturze | ⚠️ CZĘŚCIOWO — GTU/MPP/FP/marża/zwolnienie są; brak TP, SW, EE, custom cols |
| 11 | Korekty faktur | ✅ JEST |
| 12 | Tryby awaryjne | ⚠️ CZĘŚCIOWO — monitoring statusu jest, brak pełnej automatyki |

### Priorytety do realizacji

1. ~~**Import danych ze starego systemu** (pkt 4)~~ → ✅ **ZROBIONE** (export API + CLI import z auto-match po NIP). Wymaga deploy `api_export.php` na serwer i uruchomienia `bin/cake import_legacy --auto-match`.
2. **Archiwum / dostęp do starych faktur** (pkt 5+6) — ⚠️ import danych gotowy, pozostaje: flaga `is_legacy`, filtr w UI, migracja PDFów
3. **Moduł „Twoi Klienci"** (pkt 7) — kluczowe dla biur rachunkowych
4. **Eksport KAPER** (pkt 9) — potrzebne do integracji z Comarch
5. **Tryb awaryjny** (pkt 12) — wymóg prawny od obowiązkowego KSeF
6. **Dodatkowe procedury** (pkt 10: TP, SW, EE…) — stopniowo w miarę potrzeb
7. **Rachunki** (pkt 2) — niska pilność, „Faktura bez VAT" wystarczy dla większości

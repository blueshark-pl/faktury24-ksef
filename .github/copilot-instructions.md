# Copilot instructions (faktury24)

Ten plik opisuje zasady pracy Copilota w tym repo.

## Tryb pracy

- Działaj możliwie samodzielnie: wybieraj kolejny krok, wdrażaj zmiany, weryfikuj i komunikuj wynik.
- Pytaj tylko wtedy, gdy brakuje krytycznych informacji (np. dostęp do środowiska/sekretów, niejasne wymagania biznesowe).
- Jeśli workspace ma wiele folderów/projektów: wykonuj zmiany i komendy tylko w tym repo (`G:\2025\partnersc\faktury24`).

## Nie poszerzam scope

- Nie poprawiam rzeczy „przy okazji”.
- Jeśli zauważysz problem obok, dopisz krótki TODO w README (albo w najbliższym tematycznie pliku docs, jeśli README jest ogólne).

## Nie usuwam wcześniejszych zmian

- Nie usuwaj/nie nadpisuj wcześniej wprowadzonych modyfikacji (szczególnie `templates/plugin/**` oraz custom UI auth) bez jednoznacznej prośby lub uzasadnionej potrzeby.
- Jeśli musisz coś „cofnąć”, najpierw sprawdź call sites i potencjalny regres (auth/i18n/modale) oraz opisz w commicie dlaczego.

## Weryfikacja (minimalny standard)

Zanim uznam zmianę za zakończoną:

- Sprawdź realne miejsca użycia (call sites), nie tylko definicje.
- Uruchom minimalną weryfikację lokalnie:
  - PHP lint dla zmienianych plików: `php -l <plik>`
  - jeśli dotyczy logiki/testów: `composer test`
  - jeśli dotyczy stylu: `composer cs-check`
  - opcjonalnie pełny zestaw: `composer check`

Jeśli czegoś nie da się zweryfikować (np. brak SMTP/sekretów/backendu):

- Napisz wprost, co jest **niezweryfikowane** i jak to sprawdzić manualnie.

## Zasady email HTML

- Trzymaj się kompatybilnego HTML (tabele, inline CSS). Unikaj nowoczesnych układów opartych o flex/grid.
- Zakładaj, że część klientów blokuje `data:` URI w obrazkach. Preferuj:
  1) publiczny URL (`App.emailLogoUrl`),
  2) CID inline attachment (jeśli wdrażane),
  3) `data:` tylko jako fallback.
- Każdy obrazek ma mieć sensowny `alt`.

## CakePHP / migracje

- Zmiany schematu rób przez migracje (CakePHP Migrations), nie „ręcznie” w bazie.
- Kontrolery mają być „slim”, logika domenowa w warstwie modelu/serwisów.

## Wersjonowanie aplikacji

- Numer wersji trzymamy w `config/app.php` jako `App.version` i wyświetlamy go w stopce (layout `auth` i `default`).
- Format: `1.<miesiąc>.<dzień> (<build>)` np. `1.2.13 (1)`.
- Zasady podbijania:
  - Jeśli zmieniasz dzień pracy: ustaw `miesiąc.dzień` na aktualną datę i zacznij build od `(1)`.
  - Jeśli robisz kolejne commity tego samego dnia: zwiększaj tylko `<build>` o `+1`.

## Git: każda zmiana = commit + push

- Po każdej zmianie w repo (kod/konfig/dokumentacja/migracje) kończ pracę pełnym cyklem:
  1) `git status`
  2) `git add -A`
  3) mały, logiczny commit (bez mieszania tematów)
  4) `git push` na aktualny branch

- Jeśli w repo są lokalne zmiany **niezrobione przeze mnie** i nie mają commita (np. ktoś coś poprawił na branchu/maszynie):
  - nie robię `git restore` / czyszczenia „żeby było czysto”,
  - opisuję je krótko (co i dlaczego) i **commituję** jako osobny, logiczny commit,
  - wyjątek: `git restore` tylko w naprawdę uzasadnionych przypadkach (np. ewidentny śmietnik/artefakty) i wtedy w commicie/PR opisuję uzasadnienie.

- Commit message z prostym prefiksem: `docs:`, `fix:`, `feat:`, `refactor:`.

## TODO — Zgodność ze strukturą FA(3) KSeF

Pełna analiza broszury informacyjnej FA(3) (wrzesień 2025) vs aktualny schemat DB.
Legenda: 🔴 wysoki / 🟡 średni / 🟢 niski priorytet.

### 🔴 WYSOKI PRIORYTET (wymagane do pełnej zgodności FA(3))

- [x] **BUG: brak kolumny `gtu_code` w `invoice_contents`** — entity ma ją w `$_accessible`, builder generuje `<GTU>`, ale żadna migracja nie tworzy kolumny. Dane GTU są cicho gubione.
- [x] **Okres faktury** (`invoices.period_from`, `invoices.period_to`) — builder odczytuje te pola do `<OkresFa>`, ale kolumny nie istnieją w DB. Potrzebne dla usług ciągłych/mediów.
- [x] **Numer WZ** (`invoices.wz_number`) — builder emituje `<WZ>`, ale kolumna nie istnieje.
- [x] **Przyczyna korekty** (`invoices.correction_reason`) — builder emituje `<PrzyczynaKorekty>`, kolumna brak.
- [x] **Miejsce wystawienia** (`invoices.place_of_issue`) — builder czyta z `$seller->city`, ale powinno być osobnym polem.
- [x] **UUID wiersza** (`invoice_contents.uu_id`) — `<UU_ID>` potrzebne przy korektach (identyfikacja wiersza korygowanego). Brak w DB.
- [x] **Kwota VAT per-wiersz** (`invoice_contents.vat_amount`) — `<P_11Vat>` brak w DB, builder nie emituje.
- [x] **Data sprzedaży per-wiersz** (`invoice_contents.line_date`) — `<P_6A>` brak.
- [x] **PKWiU** (`invoice_contents.pkwiu`) — `<PKWiU>` brak w `invoice_contents` (jest w `products`).
- [x] **Cena brutto jedn.** (`invoice_contents.gross_unit_price`) — `<P_9B>` brak.
- [x] **Rola podmiotu trzeciego** (`invoice_recipients.rola`) — `<Rola>` brak. Tabela pozwala tylko 1 rekord/fakturę (unique index) — powinno być wiele.
- [x] **Dane kontaktowe sprzedawcy** (`invoice_company_details.email`, `phone`) — `<DaneKontaktowe>` brak.
- [x] **Stopka: KRS/REGON/BDO na snapshoty** (`invoice_company_details.krs`, `regon`, `bdo`) — istnieją w `company_registers`, ale nie w snapshocie faktury.
- [x] **Tekst stopki** (`invoices.footer_text`) — `<StopkaFaktury>` brak.
- [x] **Link do płatności** (`invoices.payment_link`) — `<LinkDoPlatnosci>` brak.

### 🟡 ŚREDNI PRIORYTET

- [x] **DodatkowyOpis** (klucz/wartość) — nowa tabela `invoice_additional_descriptions` + builder `buildDodatkowyOpisXml`. Commit: 1.2.23 (8).
- [x] **Płatności częściowe** — `<ZaplataCzesciowa>` z `invoice_payments` (loop) + fallback na skalar `alreadypaid`. Commit: 1.2.23 (8).
- [x] **Bank SWIFT** — kolumna `swift` w `company_bank_accounts` + `invoice_company_details`, emitowane jako `<SWIFT>` po `<NrRB>`. Commit: 1.2.23 (8).
- [x] **Rozliczenie faktur zaliczkowych** — `<FakturaZaliczkowa>` emitowane dla ROZ: szukamy advance siblings przez parent_id. Commit: 1.2.23 (8).
- [x] **GLN sprzedawcy/nabywcy** — kolumna `gln` w `companies`, `contractors`, `invoice_company_details`, `invoice_contractors`; emitowane w `<Adres>`. Commit: 1.2.23 (8).
- [x] **NrKlienta nabywcy** — kolumna `nr_klienta` w `invoice_contractors`; emitowane w `<Podmiot2>`. Commit: 1.2.23 (8).
- [x] **Kwota rabatu** (`invoice_contents.discount_amount`) — `<P_10>` emitowane między P_9B a P_11; automatycznie obliczane przy zapisie. Commit: 1.2.23 (8).

### 🟢 NISKI PRIORYTET

- [x] **NoweSrodkiTransportu** — tabela `invoice_new_transports` + refactor buildera z dynamicznych właściwości na DB. Commit: 1.2.23 (9).
- [x] **WarunkiTransakcji** — kolumna JSON `transaction_conditions_json` na `invoices` + builder `buildWarunkiTransakcjiXml`. Commit: 1.2.23 (9).
- [x] **Zamówienie** — tabela `invoice_order_lines` + builder `buildZamowienieXml` (ZAL). Commit: 1.2.23 (9).
- [x] **Rozliczenie obciążeń/odliczeń** — tabela `invoice_charges` + builder `buildRozliczenieXml`. Commit: 1.2.23 (9).
- [x] **Skonto** — kolumny `skonto_conditions`, `skonto_amount` na `invoices` + emisja `<Skonto>` w `buildPaymentXml`. Commit: 1.2.23 (9).
- [x] **Kurs waluty per-wiersz** — kolumna `kurs_waluty` na `invoice_contents` + emisja `<KursWaluty>` w `buildSingleLineXml`. Commit: 1.2.23 (9).
- [x] **RachunekBankowyFaktora** — tabela `invoice_factor_banks` + emisja w `buildPaymentXml`. Commit: 1.2.23 (9).
- [x] **Adres korespondencyjny** — kolumny `koresp_*` na `invoice_company_details` + `invoice_contractors` + emisja `<AdresKoresp>`. Commit: 1.2.23 (9).
- [x] **StatusInfoPodatnika** — kolumna `status_info_podatnika` na `invoices` + emisja w `buildSellerXml`. Commit: 1.2.23 (9).
- [x] **Podmiot Upoważniony** — tabela `invoice_authorized_entities` + builder `buildPodmiotUpowaznionyXml`. Commit: 1.2.23 (9).

## TODO — Audyt widoków / formularzy / XML (2026-03-18)

Wynik audytu: które pola FA(3) mają formularze w templatech, czy są zapisywane w `handleAdd()`, i czy trafiają do XML.

### 🔴 KRYTYCZNY BUG: Annotations JSON → XML

- [x] **`buildAnnotationsXml()` — NAPRAWIONE** (commit cd61c8c). Dekoduje JSON `$inv->annotations` i mapuje cash_method→P_16, reverse_charge→P_18, is_split_payment→P_18A, triangular→P_23. Także `buildZwolnienieXml()` poprawione.

### 🔴 WYSOKI: Brakujące taby w formularzach

- [x] **NAPRAWIONE** (commit cd61c8c). Wyodrębniono wspólne elementy `tab_annotations.php` i `tab_identifiers.php` i dodano do wszystkich 11 szablonów (10× add_*.php + edit.php). `add_advance.php` przerobiony na strukturę tabów.

### 🔴 WYSOKI: `view()` nie ładuje relacji FA(3)

- [x] **NAPRAWIONE** (commit cd61c8c). `view()` i `edit()` teraz ładują: `InvoicePayments`, `InvoiceAdditionalDescriptions`, `InvoiceRecipients`, `InvoiceNewTransports`, `InvoiceCharges`, `InvoiceFactorBanks`, `InvoiceAuthorizedEntities`, `InvoiceOrderLines`.

### 🟡 ŚREDNI: Nowe pola FA(3) LOW bez form fields

Pola dodane w migracji `20260318160000` istnieją w DB i XML builderach, ale **żaden formularz** ich nie zawiera:

- [ ] `skonto_conditions` / `skonto_amount` — brak inputów we wszystkich templatech
- [ ] `status_info_podatnika` — brak selecta/inputu
- [ ] `is_new_transport_wdt` — brak checkboxa
- [ ] `koresp_country_code` / `koresp_address_l1` / `koresp_address_l2` / `koresp_gln` — brak pól korespondencyjnych na kontrahentach
- [ ] `transaction_conditions_json` — brak formularza (JSON, wymaga dedykowanego UI)
- [ ] `order_total_gross` / `invoice_order_lines` — brak formularza zamówienia (tylko advance)
- [ ] `invoice_charges` (obciążenia/odliczenia) — brak formularza
- [ ] `invoice_factor_banks` — brak formularza rachunku faktora
- [ ] `invoice_authorized_entities` — brak formularza podmiotu upoważnionego

### 🟡 ŚREDNI: Nowe pola LOW brak w `handleAdd()` save logic

- [ ] `handleAdd()` (linia ~1830-1920) **nie zapisuje** nowych pól FA(3) LOW z POST:
  - `skonto_conditions`, `skonto_amount`, `status_info_podatnika`
  - `is_new_transport_wdt`, `p_42_5`, `transaction_conditions_json`, `order_total_gross`
  - `koresp_*` na invoiceContractor / invoiceCompanyDetail snapshot

### 🟢 NISKI: Pola obecne tylko w `add.php`

Te pola mają inputy w `add.php`, ale brakuje ich w innych formularzach. Mogą być potrzebne:

- `buyer_is_jst`, `buyer_in_vat_group` — tylko `add.php`
- `seller_vat_prefix`, `seller_vat_eu`, `seller_eori` — tylko `add.php`
- `buyer_vat_prefix`, `buyer_vat_eu`, `buyer_eori` — tylko `add.php`
- `buyer_tax_id_other`, `buyer_tax_id_other_country` — tylko `add.php`

## TODO — Analiza przepływu faktur proforma → zaliczkowa → końcowa (2026-03)

### 🔴 KRYTYCZNE BUGI (naprawione)

- [x] **`buildFakturaZaliczkowaXml()` linia ~5168 — `issuedate` → `date`** — kolumna `issuedate` nie istnieje w tabeli `invoices`; poprawny sort to `Invoices.date`. Powodowało SQL error przy generowaniu XML faktury końcowej (ROZ).
- [x] **`buildFakturaZaliczkowaXml()` linia ~5185 — `$adv->number` → `$adv->fullnumber`** — pole `number` to sekwencyjny int (1, 2, 3), a `<NrFaZaliczkowej>` wymaga pełnego numeru faktury (np. `FZ/1/2026`). Generowało nieprawidłowy XML.
- [x] **Routing templatek przy pustym `$contents`** — `handleAdd()` renderował `'add'` dla wszystkich typów poza `novat`. Teraz routuje poprawnie: advance/final → `add_advance`, proforma → `add_proforma`, margin → `add_margin`, currency → `add_currency`, correction → `add_correction`.

### 🟡 DESIGN ISSUES (do decyzji / przyszłe)

- [ ] **Auto-klasyfikacja jako końcowa** — gdy kwota zaliczki ≈ pozostała kwota proformy (±0.01), faktura jest automatycznie oznaczana jako `final`. Użytkownik może tego nie zauważyć. Rozważyć: wyraźniejsze powiadomienie lub potwierdzenie.
- [ ] **Snapshot kontrahenta z proformy** — dane kontrahenta kopiowane z proformy mogą być nieaktualne, jeśli kontrahent zmienił dane po wystawieniu proformy. Rozważyć: porównanie z aktualnym kontrahentem i ostrzeżenie.
- [ ] **Opis duplikowany przy re-submit** — opis „Rozlicza zaliczki: ..." jest dopisywany do `description` przy każdym POST bez sprawdzenia, czy już istnieje. Wielokrotne odrzucenie + ponowne wysłanie formularza skutkuje zduplikowanymi fragmentami opisu.

### ✅ Pozytywna weryfikacja (działa poprawnie)

- `handleAdd()` — branch advance (linie ~1261-1409): prawidłowo waliduje proformę, oblicza remaining, waliduje kwotę ≤ remaining, tworzy pojedynczą pozycję, mapuje parent_id.
- `edit()` — branch advance/final: prawidłowo wyklucza bieżącą fakturę z sumy zaliczek, preselektuje proformę, ustawia `is_final`.
- `proformaSearch()` i `proformaDetails()` — AJAX endpointy działają poprawnie, zwracają historię zaliczek i remaining.
- `resolveRodzajFaktury()` — mapowanie: advance→ZAL, final→ROZ, correction→KOR, reszta→VAT. Proforma słusznie nie mapowana (nie jest wysyłana do KSeF).
- `add_advance.php` template — Select2 dla proformy, recompute netto/VAT, walidacja overpayment, auto-przełączanie serii zaliczkowa↔końcowa, finalize button z blokowaniem gdy końcowa istnieje.


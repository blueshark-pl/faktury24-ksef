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

- [ ] **DodatkowyOpis** (klucz/wartość) — potrzebna nowa tabela `invoice_additional_descriptions`. Opcjonalne pole FA(3) ale częste w praktyce.
- [ ] **Płatności częściowe** — `<ZaplataCzesciowa>` (kwota+data) powtarzalne. Nowa tabela `invoice_partial_payments` lub rozszerzenie `invoice_payments`.
- [ ] **Bank SWIFT / NazwaBanku** — `company_bank_accounts` potrzebuje `swift`, `bank_name`, `bank_own_account`, `description`.
- [ ] **Rozliczenie faktur zaliczkowych** — `<FakturaZaliczkowa>` (NrFaZaliczkowej, data, kwota). Nowa tabela lub JSON.
- [ ] **GLN sprzedawcy/nabywcy/odbiorcy** — `<GLN>` opcjonalne ale coraz częstsze w dużych sieciach.
- [ ] **NrKlienta nabywcy** — `<NrKlienta>` w Podmiot2, brak w `invoice_contractors`.
- [ ] **Kwota rabatu** (`invoice_contents.discount_amount`) — `<P_10>` brak (mamy tylko `discount_percent`).

### 🟢 NISKI PRIORYTET

- [ ] **NoweSrodkiTransportu** — pola pojazdów/łodzi/samolotów. Builder ma pełną obsługę, ale dane czyta z dynamicznych właściwości (brak DB).
- [ ] **WarunkiTransakcji** — Incoterms, umowy, transport. Opcjonalne w FA(3).
- [ ] **Zamówienie** — linie zamówienia do faktur zaliczkowych (ZAL).
- [ ] **Rozliczenie obciążeń/odliczeń** — `<Obciazenia>` / `<Odliczenia>`.
- [ ] **Skonto** — warunki skonta do płatności.
- [ ] **Kurs waluty per-wiersz** — `<KursWaluty>` w FaWiersz.
- [ ] **RachunekBankowyFaktora** — rachunek bankowy faktora.
- [ ] **Adres korespondencyjny** — sprzedawca/nabywca.
- [ ] **StatusInfoPodatnika** — status informacyjny podatnika.
- [ ] **Podmiot Upoważniony** — pełna sekcja `<PodmiotUpowazniony>`.


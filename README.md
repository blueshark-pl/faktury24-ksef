# CakePHP Application Skeleton

![Build Status](https://github.com/cakephp/app/actions/workflows/ci.yml/badge.svg?branch=5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/app.svg?style=flat-square)](https://packagist.org/packages/cakephp/app)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

A skeleton for creating applications with [CakePHP](https://cakephp.org) 5.x.

The framework source code can be found here: [cakephp/cakephp](https://github.com/cakephp/cakephp).

## Installation

1. Download [Composer](https://getcomposer.org/doc/00-intro.md) or update `composer self-update`.
2. Run `php composer.phar create-project --prefer-dist cakephp/app [app_name]`.

If Composer is installed globally, run

```bash
composer create-project --prefer-dist cakephp/app
```

In case you want to use a custom app dir name (e.g. `/myapp/`):

```bash
composer create-project --prefer-dist cakephp/app myapp
```

You can now either use your machine's webserver to view the default home page, or start
up the built-in webserver with:

```bash
bin/cake server -p 8765
```

Then visit `http://localhost:8765` to see the welcome page.

## Update

Since this skeleton is a starting point for your application and various files
would have been modified as per your needs, there isn't a way to provide
automated upgrades, so you have to do any updates manually.

## Configuration

Read and edit the environment specific `config/app_local.php` and set up the
`'Datasources'` and any other configuration relevant for your application.
Other environment agnostic settings can be changed in `config/app.php`.

## Layout

The app skeleton uses [Milligram](https://milligram.io/) (v1.3) minimalist CSS
framework by default. You can, however, replace it with any other library or
custom styles.

## Dziennik prac (czas człowieka)

### 2026-02-13

- Dodanie wersjonowania aplikacji i wyświetlanie wersji w stopkach (auth + default).
- Commit: 61c8ef3
- Czas: 0.5h
- Do sprawdzenia manualnie: stopka na `/users/login` oraz w layoutcie `default` po zalogowaniu.

- Latarnia KSeF: pobieranie publicznego statusu i komunikatu + wyświetlanie w stopkach (auth + default).
- Commit: cf38fb9
- Czas: 0.5h
- Do sprawdzenia manualnie: czy stopka pokazuje `KSeF: ...` oraz czy przy braku API stopka nie „wybucha” (powinno być „Brak danych”).

- Komunikaty MF: klikalny link w stopce otwiera modal z listą komunikatów (aktywne + nadchodzące) oraz banner na stronie logowania dla ważnych/nadchodzących zdarzeń.
- Commit: aff11d0
- Czas: 0.5h
- Do sprawdzenia manualnie: na `/users/login` widoczny banner przy nadchodzącym wyłączeniu i link „Komunikaty MF” otwiera modal niezależnie od statusu.

- Naprawa: modal „Komunikaty MF” renderowany na poziomie `<body>` (blok `ksefModals`), żeby nie znikał przy kliknięciu (wcześniej backdrop był, ale okno nie). 
- Commit: e653c40
- Czas: 0.2h
- Do sprawdzenia manualnie: klik w „Komunikaty MF” w stopce na `/users/login` i po zalogowaniu powinien pokazać okno z treścią.

- Naprawa: usunięcie `onclick="return false"` z linków otwierających modale (w tym „Komunikaty MF”), bo w niektórych przypadkach blokowało otwarcie.
- Commit: 2188faa
- Czas: 0.1h
- Do sprawdzenia manualnie: klik „Komunikaty MF” zawsze otwiera modal (nawet gdy pokazuje „Brak komunikatów.”).

- Naprawa: „Komunikaty MF” modal nie jest już renderowany przez blok view z Cell (renderuje się wprost i jest przenoszony do `<body>`), co eliminuje błąd JS i brak okna.
- Commit: 3e7992c
- Czas: 0.2h
- Do sprawdzenia manualnie: klik „Komunikaty MF” na `/users/login` i po zalogowaniu otwiera modal; w konsoli brak błędu `...reading 'backdrop'`.

- UI: ujednolicenie przycisku „Zamknij” w modalu „Komunikaty MF” ze stylem z Regulaminu/Polityki (btn-outline-secondary).
- Commit: 3444460
- Czas: 0.1h
- Do sprawdzenia manualnie: w modalu „Komunikaty MF” przycisk „Zamknij” ma identyczny wygląd jak w Regulamin/Polityka.

- Regulamin: dodanie linku „Załącznik nr 1 (DPA)” w §14 i osobnego modala DPA (sticky TOC + smooth-scroll) z przełączaniem między modalami.
- Commit: 93ea2d7
- Czas: 0.2h
- Do sprawdzenia manualnie: na `/users/login` w Regulaminie klik „Otwórz Załącznik nr 1 (DPA)” zamyka Regulamin i otwiera DPA; przycisk „Wróć do Regulaminu” działa w drugą stronę.

- UI: modal DPA ma identyczny layout i style jak modal Regulaminu (spis, typografia, header, karty).
- Commit: 9ec90ef
- Czas: 0.1h
- Do sprawdzenia manualnie: w DPA wygląd spisu treści i sekcji jest 1:1 jak w Regulaminie.

- Auth (CakeDC Users): wymuszenie locale `pl` + override tłumaczeń domeny `cake_d_c/users`, żeby UI nie mieszał EN/PL.
- Commit: a3b6c26
- Czas: 0.2h
- Do sprawdzenia manualnie: `/users/login`, `/users/register`, reset hasła i komunikaty flash powinny być po polsku.

- 2FA: dopisanie linków do pobrania Google Authenticator (Android/iOS) na ekranie weryfikacji + dopięcie brakujących tłumaczeń (Verify/Verifying/Don’t share...).
- Commit: d3d85c2
- Czas: 0.1h
- Do sprawdzenia manualnie: `/users/verify` ma linki do sklepów i wszystkie etykiety/ostrzeżenia są po polsku.

- Naprawa: przeniesienie cache tłumaczeń (`_cake_translations_`) do `tmp/cache/translations`, żeby ominąć `Permission denied` na pojedynczym pliku cache w `tmp/cache/persistent`.
- Commit: 4758dbe
- Czas: 0.1h
- Do sprawdzenia manualnie: odśwież `/users/login` (i inne widoki) i potwierdź brak warningu `SplFileInfo::openFile(...cake_d_c.users.pl_PL): Permission denied`; katalog `tmp/cache/translations` powinien się utworzyć automatycznie.

- 2FA (opcja dla chętnych): dodanie opt-in 2FA per user (wymagane tylko gdy `secret_verified=1`) + ekran ustawień `/konto/2fa` (włącz/zweryfikuj/wyłącz).
- Commit: b75164d
- Czas: 0.3h
- Do sprawdzenia manualnie: użytkownik bez 2FA loguje się bez weryfikacji; po włączeniu i weryfikacji 2FA logowanie przekierowuje na `/users/verify`.

- Rejestracja: naprawa błędu `Tampered field username` (ukryte `username` jest odblokowane i synchronizowane z `email`).
- Commit: ec2527a
- Czas: 0.1h
- Do sprawdzenia manualnie: rejestracja działa bez błędu, a w bazie `users.username` = e-mail.

- Logowanie/onboarding: dopisanie uprawnień dla roli `user` do `Companies::onboarding/saveOnboarding` (oraz podstawowych akcji `Dashboard` i `TwoFactor`), żeby nie było pętli redirectów (`ERR_TOO_MANY_REDIRECTS`) po rejestracji.
- Commit: 3a1ee5a
- Czas: 0.1h
- Do sprawdzenia manualnie: świeżo zarejestrowany użytkownik po zalogowaniu trafia na onboarding, a po uzupełnieniu firmy trafia na dashboard (bez pętli przekierowań).

- Logi: naprawa warningów `Error saving user ... password after rehashing: identifier Password not found` przez przejście na konfigurację rehash per-authenticator (`Auth.PasswordRehash.authenticators`) i wyłączenie deprecated `Auth.PasswordRehash.identifiers`.
- Commit: 5c8ccee
- Czas: 0.1h
- Do sprawdzenia manualnie: zaloguj się kilka razy i potwierdź, że warning nie pojawia się w `logs/error.log`.

### 2026-03-18

- Audyt pól formularzy i DB pod FA(3) KSeF. Migracja 20260318100000 — dodanie 19 brakujących kolumn do tabel invoices, invoice_contents, invoice_contractors. Aktualizacja encji i logiki zapisu w kontrolerze.
- Commit: 3c077f4
- Czas: 1–2h
- Do sprawdzenia manualnie: w phpMyAdmin zweryfikować, że kolumny istnieją; wystawić fakturę i sprawdzić czy gtu_code, gtin, cn_code, pkob, excise_amount, procedure_marking, receipt_number, receipt_date, is_receipt_invoice, is_split_payment, sold_date, paid_at, partial_paid_at, lang, auto_send, buyer_is_jst, buyer_in_vat_group, annotations, annotations_tax_free_field, seller/buyer vat_prefix/vat_eu/eori/buyer_tax_id_other/buyer_tax_id_other_country i company_bank_account_id trafiają do bazy.

- Analiza broszury FA(3) KSeF vs schemat DB i XML builder. Migracja 20260318120000 — kolumny FA(3): gtu_code BUG FIX, uu_id, vat_amount, line_date, pkwiu, gross_unit_price (invoice_contents); period_from/to, wz_number, correction_reason, place_of_issue, footer_text, payment_link (invoices); rola, rola_opis, identyfikatory VAT (invoice_recipients); email, phone, krs, regon, bdo, bank_name, bank_desc, country_code (invoice_company_details). Pełna aktualizacja XML buildera (seller DaneKontaktowe, buildSingleLineXml z UU_ID/P_6A/P_9B/P_11A/P_11Vat/PKWiU, buildStopkaXml, LinkDoPlatnosci, place_of_issue), encji i logiki zapisu. TODO FA(3) w copilot-instructions.md.
- Commit: ec3cc05
- Czas: 2–3h
- Do sprawdzenia manualnie: w phpMyAdmin zweryfikować nowe kolumny; wystawić fakturę i sprawdzić XML FA(3) (zwłaszcza GTU, UU_ID, Stopka, DaneKontaktowe). Sprawdzić czy edycja faktury zachowuje period_from/to, place_of_issue, footer_text, payment_link.

- FA(3) MEDIUM priority — 7 elementów: DodatkowyOpis (nowa tabela + builder), płatności częściowe (loop po invoice_payments + fallback), SWIFT w RachunekBankowy, FakturaZaliczkowa (rozliczenie zaliczek ROZ), GLN sprzedawcy/nabywcy, NrKlienta nabywcy, P_10 kwota rabatu. Migracja 20260318140000 (6 tabel zmienionych, 1 nowa). Nowe metody buildera: buildDodatkowyOpisXml, buildFakturaZaliczkowaXml. Lazy-load relacji w buildFa3XmlBase.
- Commit: 5796586
- Czas: 1–2h
- Do sprawdzenia manualnie: w phpMyAdmin zweryfikować nowe kolumny (swift, gln, nr_klienta, discount_amount, tabela invoice_additional_descriptions); wystawić fakturę z rabatem kwotowym i sprawdzić <P_10> w XML; dodać wpis DodatkowyOpis i sprawdzić emisję.

- FA(3) LOW priority — research 10 elementów XSD: NoweSrodkiTransportu, WarunkiTransakcji, Zamówienie (ZAL), Obciazenia/Odliczenia, Skonto, KursWaluty per-wiersz, RachunekBankowyFaktora, AdresKoresp, StatusInfoPodatnika, PodmiotUpowazniony. Raport w `docs/FA3_LOW_PRIORITY_RESEARCH.md`. Wynik: 9 nowych tabel, ~25 nowych kolumn, 9 metod buildera do napisania (tylko NoweSrodkiTransportu ma istniejący builder bez DB).
- Commit: 314b489
- Czas: 0.5h
- Do sprawdzenia manualnie: przeczytać raport w `docs/FA3_LOW_PRIORITY_RESEARCH.md`, zweryfikować struktury XSD vs faktyczne potrzeby biznesowe.

## TODO (Invoices)

- Uporządkować duplikat kontrolera: w repo są dwie klasy `InvoicesController` ([src/Controller/InvoicesController.php](src/Controller/InvoicesController.php) oraz [src/InvoicesController.php](src/InvoicesController.php)). Zostawić jedno źródło prawdy i usunąć/oznaczyć plik legacy.
- Rozdzielić bardzo duże metody `handleAdd()` i `edit()` w [src/Controller/InvoicesController.php](src/Controller/InvoicesController.php) na serwisy domenowe (numeracja, walidacja pozycji, snapshot nabywcy, wysyłka KSeF), żeby ograniczyć regresje.
- Ujednolicić model pola serii (nazwa vs UUID) między frontendem i backendem: dziś `series` bywa nazwą, a mapowanie kończy na `invoice_series_id`; warto przejść na stabilny identyfikator + jawny DTO mapowania.
- Dodać testy integracyjne dla scenariuszy roboczych i KSeF: zapis draftu, zmiana serii przy edycji, wysyłka draftu z datą inną niż dziś, renumeracja po zmianie daty.
- Naprawić migrację testową `20251002120002_AddUniqueNipToCompanies` pod SQLite (błąd `MODIFY`), bo obecnie blokuje `composer test` i utrudnia pełną weryfikację modułu.

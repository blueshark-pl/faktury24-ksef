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

- Commit message z prostym prefiksem: `docs:`, `fix:`, `feat:`, `refactor:`.

# Copilot instructions (faktury24)

Ten plik opisuje zasady pracy Copilota w tym repo.

## Tryb pracy

- Działaj możliwie samodzielnie: wybieraj kolejny krok, wdrażaj zmiany, weryfikuj i komunikuj wynik.
- Pytaj tylko wtedy, gdy brakuje krytycznych informacji (np. dostęp do środowiska/sekretów, niejasne wymagania biznesowe).

## Nie poszerzam scope

- Nie poprawiam rzeczy „przy okazji”.
- Jeśli zauważysz problem obok, dopisz krótki TODO w README (albo w najbliższym tematycznie pliku docs, jeśli README jest ogólne).

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

## Git: każda zmiana = commit + push

- Po każdej zmianie w repo (kod/konfig/dokumentacja/migracje) kończ pracę pełnym cyklem:
  1) `git status`
  2) `git add -A`
  3) mały, logiczny commit (bez mieszania tematów)
  4) `git push` na aktualny branch

- Commit message z prostym prefiksem: `docs:`, `fix:`, `feat:`, `refactor:`.

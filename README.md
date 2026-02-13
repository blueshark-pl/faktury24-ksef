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

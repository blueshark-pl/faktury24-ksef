# System Integracji Faktur - Dokumentacja Kompletnej Implementacji

## Przegląd Systemu

System został w pełni zintegrowany i umożliwia kompleksowe zarządzanie fakturami z wykorzystaniem:
- Automatycznego zarządzania produktami i kontrahentami
- Zaawansowanego systemu serii faktur z inteligentną numeracją
- Kompletnej obsługi bazy danych z prawidłowymi relacjami
- Modalnego interfejsu użytkownika z katalogami

## Struktura Bazy Danych

### Główne Tabele
- `invoices` - główna tabela faktur
- `invoice_company_details` - dane sprzedawcy (1:1)
- `invoice_contractors` - dane nabywcy (1:1) 
- `invoice_contents` - pozycje faktury (1:N)
- `invoice_series` - serie faktur z wzorcami numeracji

### Relacje
```php
Invoice belongsTo InvoiceSeries
Invoice hasOne InvoiceCompanyDetails
Invoice hasOne InvoiceContractors  
Invoice hasMany InvoiceContents
```

## Funkcjonalności

### 1. Zarządzanie Produktami
**Controller**: `ProductsController.php`
**Metody**:
- `search()` - wyszukiwanie produktów dla Select2
- `add()` - dodawanie nowych produktów przez AJAX

**Implementacja**:
- Select2 dropdown z wyszukiwaniem
- Modal do dodawania nowych produktów
- Automatyczne odświeżanie listy po dodaniu

### 2. Zarządzanie Kontrahentami
**Controller**: `ContractorsController.php`
**Metody**:
- `search()` - wyszukiwanie kontrahentów
- `add()` - dodawanie nowych kontrahentów

**Funkcje**:
- Katalog kontrahentów z wyszukiwaniem
- Przypisywanie kontrahenta do faktury
- Walidacja danych NIP/REGON

### 3. System Serii Faktur
**Controller**: `InvoiceSeriesController.php`
**Zaawansowane funkcje**:

#### Wzorce numeracji z placeholderami:
- `[numer]` - podstawowy numer
- `[numer:zera_wiodące=3]` - numer z zerami wiodącymi
- `[rok]` - pełny rok (2025)
- `[rok:format_dwucyfrowy]` - rok dwucyfrowy (25)
- `[miesiac]` - miesiąc z zerem wiodącym (01-12)
- `[dzien]` - dzień z zerem wiodącym (01-31)
- `[kwartał]` - numer kwartału (1-4)

#### Przykłady wzorców:
```
FV/[numer:zera_wiodące=4]/[rok]/[miesiac]
→ FV/0001/2025/01

PF-[rok:format_dwucyfrowy]-[kwartał]-[numer:zera_wiodące=3]  
→ PF-25-1-001
```

#### Typy numeracji:
- **Ciągła** - numeracja przez całe życie serii
- **Roczna** - reset na początku roku
- **Miesięczna** - reset co miesiąc

### 4. Terminy Płatności
**Predefiniowane opcje**:
- 7 dni (domyślne)
- 14 dni
- 21 dni  
- 30 dni
- Płatne przy odbiorze
- Płatne z góry

**Funkcje**:
- Automatyczne obliczanie daty płatności
- Status płatności (nieopłacone/częściowo/opłacone/przeterminowane)

### 5. Akcje Faktury
**Przyciski akcji**:
- **Zapisz i Podgląd** - zapisz i przejdź do podglądu
- **Zapisz i Nowa** - zapisz i utwórz nową fakturę
- **Zapisz i Duplikuj** - zapisz i skopiuj do nowej

## Implementacja Techniczna

### Kontroler Główny
**Plik**: `src/Controller/InvoicesController.php`
**Metoda**: `handleAdd()`

#### Logika zapisu:
1. Walidacja serii faktury
2. Generowanie numeru według wzorca  
3. Zapisanie głównych danych faktury
4. Zapisanie danych sprzedawcy (invoice_company_details)
5. Zapisanie danych nabywcy (invoice_contractors)
6. Zapisanie pozycji faktury (invoice_contents)
7. Transakcyjny commit/rollback

### JavaScript Frontend
**Plik**: `templates/Invoices/add.php`

#### Funkcjonalności:
- Select2 dla produktów i kontrahentów
- Podpowiedzi numerów faktur
- Modalne okna zarządzania
- Automatyczne obliczenia VAT
- Walidacja formularzy

### Bezpieczeństwo
- CSRF protection dla wszystkich formularzy
- Walidacja danych wejściowych
- Transakcje bazodanowe
- Kontrola uprawnień użytkownika

## Sposób Użytkowania

### Tworzenie Faktury:
1. Wybierz lub utwórz serię faktury
2. System automatycznie zaproponuje numer
3. Dodaj/wybierz kontrahenta z katalogu
4. Dodaj pozycje faktury (produkty/usługi)
5. Ustaw termin płatności
6. Zapisz używając wybranej akcji

### Zarządzanie Seriami:
1. Kliknij "+" przy polu serii
2. Określ wzorzec numeracji
3. Wybierz typ i okres numeracji
4. Ustaw numer startowy
5. Seria jest gotowa do użycia

### Dodawanie Produktów:
1. W pozycji faktury kliknij "+"
2. Uzupełnij dane produktu
3. Produkt zostanie dodany do bazy i będzie dostępny w przyszłości

## Status Implementacji: ✅ KOMPLETNY

Wszystkie komponenty systemu zostały w pełni zaimplementowane i przetestowane:
- ✅ Integracja produktów z kontrolerem
- ✅ Katalog kontrahentów z wyszukiwaniem  
- ✅ Zaawansowany system serii z wzorcami
- ✅ Kompletna obsługa bazy danych
- ✅ Interface użytkownika z modalami
- ✅ Terminy płatności i statusy
- ✅ Przyciski akcji i nawigacja
- ✅ Walidacja i bezpieczeństwo

System jest gotowy do produkcyjnego użytkowania.
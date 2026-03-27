# faktury24 — API v1

Zewnętrzne API umożliwia wystawianie faktur VAT z poziomu dowolnego systemu
(ERP, sklep internetowy, CRM, itp.) bez logowania przez przeglądarkę.

---

## Uwierzytelnianie

Każde żądanie musi zawierać nagłówek:

```
Authorization: Bearer <token>
```

Token generujesz w panelu: **Ustawienia → Tokeny API**.
Token identyfikuje Twoją firmę — traktuj go jak hasło.
Jeden token = jeden dostęp; możesz mieć wiele tokenów (np. osobne dla każdego systemu).

---

## Base URL

```
https://twoja-domena.pl/api/v1
```

Wszystkie odpowiedzi są w formacie **JSON** (`Content-Type: application/json`).

---

## Endpoint: Wystaw fakturę VAT

### `POST /api/v1/invoices`

Tworzy nową fakturę VAT i zwraca jej identyfikator oraz nadany numer.

---

### Nagłówki

| Nagłówek        | Wartość                       | Wymagany |
|-----------------|-------------------------------|----------|
| Authorization   | `Bearer <token>`              | Tak      |
| Content-Type    | `application/json`            | Tak      |

---

### Ciało żądania (JSON)

```json
{
  "series": "FV",
  "date": "2025-04-01",
  "payment_method": "transfer",
  "payment_date": "2025-04-15",
  "description": "Usługi informatyczne — marzec 2025",
  "buyer": {
    "name": "Acme Sp. z o.o.",
    "nip": "1234567890",
    "street": "ul. Przykładowa 1",
    "city": "Warszawa",
    "zip": "00-001",
    "country": "Polska",
    "email": "ksiegowosc@acme.pl"
  },
  "items": [
    {
      "name": "Usługa programistyczna",
      "quantity": 10,
      "unit": "godz.",
      "price": 150.00,
      "vat": "23%"
    },
    {
      "name": "Licencja oprogramowania",
      "quantity": 1,
      "unit": "szt.",
      "price": 500.00,
      "vat": "23%"
    }
  ]
}
```

---

### Pola żądania

#### Główne

| Pole              | Typ     | Wymagany | Opis |
|-------------------|---------|----------|------|
| `buyer`           | object  | **Tak**  | Dane nabywcy (kupującego) |
| `items`           | array   | **Tak**  | Lista pozycji faktury (min. 1) |
| `series`          | string  | Nie*     | Nazwa serii numeracji, np. `"FV"` |
| `series_id`       | string  | Nie*     | UUID serii (alternatywa dla `series`) |
| `date`            | string  | Nie      | Data wystawienia `YYYY-MM-DD` (domyślnie: dzisiaj) |
| `sold_date`       | string  | Nie      | Data sprzedaży `YYYY-MM-DD` (domyślnie: = `date`) |
| `payment_method`  | string  | Nie      | Metoda płatności: `transfer` / `cash` / `card` / `blik` (domyślnie: `transfer`) |
| `payment_date`    | string  | Nie      | Termin płatności `YYYY-MM-DD` |
| `already_paid`    | number  | Nie      | Kwota już zapłacona (domyślnie: `0`) |
| `description`     | string  | Nie      | Opis / uwagi na fakturze |
| `issuer`          | string  | Nie      | Wystawiający (imię i nazwisko) |
| `place_of_issue`  | string  | Nie      | Miejsce wystawienia |
| `footer_text`     | string  | Nie      | Tekst stopki |
| `is_split_payment`| boolean | Nie      | Mechanizm podzielonej płatności (MPP) |
| `lang`            | string  | Nie      | Język faktury: `pl` / `en` (domyślnie: `pl`) |

*Jeśli nie podasz `series` ani `series_id`, zostanie użyta domyślna seria firmy.

#### Nabywca (`buyer`)

| Pole      | Typ    | Wymagany | Opis |
|-----------|--------|----------|------|
| `name`    | string | **Tak**  | Nazwa firmy lub imię i nazwisko |
| `nip`     | string | Nie      | NIP (tylko cyfry) |
| `street`  | string | Nie      | Ulica i numer |
| `city`    | string | Nie      | Miasto |
| `zip`     | string | Nie      | Kod pocztowy |
| `country` | string | Nie      | Kraj (domyślnie: `Polska`) |
| `email`   | string | Nie      | Adres e-mail |
| `phone`   | string | Nie      | Telefon |

#### Pozycja faktury (`items[]`)

| Pole                | Typ    | Wymagany | Opis |
|---------------------|--------|----------|------|
| `name`              | string | **Tak**  | Nazwa towaru / usługi |
| `quantity`          | number | Nie      | Ilość (domyślnie: `1`) |
| `unit`              | string | Nie      | Jednostka miary (domyślnie: `szt.`) |
| `price`             | number | Nie*     | Cena jednostkowa **netto** |
| `price_gross`       | number | Nie*     | Cena jednostkowa **brutto** (alternatywa dla `price`) |
| `vat`               | string | **Tak**  | Stawka VAT: `"23%"`, `"8%"`, `"5%"`, `"0%"`, `"zw"`, `"np"` |
| `vat_code_id`       | string | Nie      | UUID stawki VAT (alternatywa dla `vat`) |
| `discount_percent`  | number | Nie      | Rabat w % (domyślnie: `0`) |
| `description`       | string | Nie      | Opis pozycji |
| `gtu_code`          | string | Nie      | Kod GTU (FA(3)/JPK) np. `"GTU_01"` |
| `pkwiu`             | string | Nie      | Symbol PKWiU |
| `gtin`              | string | Nie      | Kod GTIN/EAN |
| `cn_code`           | string | Nie      | Kod CN |

---

### Odpowiedź — sukces `201 Created`

```json
{
  "success": true,
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "fullnumber": "FV/1/2025",
    "date": "2025-04-01",
    "total": 2214.50,
    "netto": 1800.00,
    "tax": 414.50,
    "currency": "PLN",
    "series": "FV",
    "view_url": "/invoices/view/a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }
}
```

| Pole         | Opis |
|--------------|------|
| `id`         | UUID faktury w systemie |
| `fullnumber` | Nadany numer faktury |
| `date`       | Data wystawienia |
| `total`      | Kwota brutto łącznie |
| `netto`      | Kwota netto łącznie |
| `tax`        | Kwota VAT łącznie |
| `currency`   | Waluta (zawsze `PLN` w tej wersji) |
| `series`     | Nazwa użytej serii |
| `view_url`   | Ścieżka do podglądu faktury w panelu |

---

### Odpowiedź — błąd

```json
{
  "success": false,
  "error": "Opis błędu"
}
```

| Kod HTTP | Znaczenie |
|----------|-----------|
| `401`    | Brak tokenu lub token nieprawidłowy / unieważniony |
| `422`    | Błąd walidacji (brakujące / niepoprawne dane) |
| `500`    | Błąd wewnętrzny serwera |

---

## Przykłady

### cURL

```bash
curl -X POST https://twoja-domena.pl/api/v1/invoices \
  -H "Authorization: Bearer fv_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -H "Content-Type: application/json" \
  -d '{
    "series": "FV",
    "date": "2025-04-01",
    "payment_method": "transfer",
    "payment_date": "2025-04-15",
    "buyer": {
      "name": "Acme Sp. z o.o.",
      "nip": "1234567890",
      "street": "ul. Przykładowa 1",
      "city": "Warszawa",
      "zip": "00-001"
    },
    "items": [
      {
        "name": "Usługa programistyczna",
        "quantity": 10,
        "unit": "godz.",
        "price": 150.00,
        "vat": "23%"
      }
    ]
  }'
```

### PHP (Guzzle)

```php
$client = new \GuzzleHttp\Client();
$response = $client->post('https://twoja-domena.pl/api/v1/invoices', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
    'json' => [
        'series'         => 'FV',
        'date'           => '2025-04-01',
        'payment_method' => 'transfer',
        'payment_date'   => '2025-04-15',
        'buyer' => [
            'name'   => 'Acme Sp. z o.o.',
            'nip'    => '1234567890',
            'street' => 'ul. Przykładowa 1',
            'city'   => 'Warszawa',
            'zip'    => '00-001',
        ],
        'items' => [
            [
                'name'     => 'Usługa programistyczna',
                'quantity' => 10,
                'unit'     => 'godz.',
                'price'    => 150.00,
                'vat'      => '23%',
            ],
        ],
    ],
]);

$data = json_decode($response->getBody(), true);
echo 'Faktura: ' . $data['data']['fullnumber'];
```

### JavaScript (fetch)

```javascript
const res = await fetch('https://twoja-domena.pl/api/v1/invoices', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    series: 'FV',
    date: '2025-04-01',
    payment_method: 'transfer',
    payment_date: '2025-04-15',
    buyer: {
      name: 'Acme Sp. z o.o.',
      nip: '1234567890',
      street: 'ul. Przykładowa 1',
      city: 'Warszawa',
      zip: '00-001',
    },
    items: [
      { name: 'Usługa programistyczna', quantity: 10, unit: 'godz.', price: 150, vat: '23%' },
    ],
  }),
});
const data = await res.json();
console.log('Faktura:', data.data.fullnumber);
```

---

## Bezpieczeństwo

- Tokeny są przechowywane jako **hash SHA-256** — nawet administrator systemu nie widzi ich wartości.
- Token możesz **unieważnić** w dowolnej chwili w panelu (Ustawienia → Tokeny API).
- Możesz ustawić **datę wygaśnięcia** tokenu.
- Zawsze używaj połączenia **HTTPS**.
- Nie wbudowuj tokenu w kod publicznych repozytoriów — używaj zmiennych środowiskowych.

---

## Wersjonowanie

| Wersja | URL prefix  | Status   |
|--------|-------------|----------|
| v1     | `/api/v1`   | Aktywna  |

---

## Endpoint: Lista faktur

### `GET /api/v1/invoices`

Zwraca stronicowaną listę faktur firmy.

### Parametry query

| Parametr       | Typ    | Opis |
|----------------|--------|------|
| `page`         | int    | Numer strony (domyślnie: `1`) |
| `per_page`     | int    | Wyniki na stronę, maks. `100` (domyślnie: `25`) |
| `date_from`    | string | Filtr daty wystawienia od `YYYY-MM-DD` |
| `date_to`      | string | Filtr daty wystawienia do `YYYY-MM-DD` |
| `type`         | string | Typ faktury: `vat`, `proforma`, `advance`, `correction`, `margin`, `currency`, `noVat` |
| `paymentstate` | string | Status płatności: `unpaid`, `partial`, `paid` |
| `series`       | string | Nazwa serii (np. `FV`) lub UUID serii |
| `search`       | string | Szukaj po numerze lub opisie |

### Odpowiedź — sukces `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 142,
    "page": 1,
    "per_page": 25,
    "pages": 6,
    "invoices": [
      {
        "id": "a1b2c3d4-...",
        "fullnumber": "FV/1/2025",
        "date": "2025-04-01",
        "sold_date": "2025-04-01",
        "type": "vat",
        "total": 2214.50,
        "netto": 1800.00,
        "tax": 414.50,
        "currency": "PLN",
        "paymentmethod": "transfer",
        "paymentdate": "2025-04-15",
        "paymentstate": "unpaid",
        "alreadypaid": 0.00,
        "remaining": 2214.50,
        "description": "",
        "ksef_number": null,
        "ksef_status": null,
        "buyer": {
          "name": "Acme Sp. z o.o.",
          "nip": "1234567890",
          "city": "Warszawa"
        },
        "created": "2025-04-01 10:00:00"
      }
    ]
  }
}
```

---

## Endpoint: Szczegóły faktury

### `GET /api/v1/invoices/{id}`

Zwraca pełne dane faktury: pozycje, podsumowanie VAT, rozliczenia płatności.

### Parametry URL

| Parametr | Opis |
|----------|------|
| `id`     | UUID faktury |

### Odpowiedź — sukces `200 OK`

```json
{
  "success": true,
  "data": {
    "id": "a1b2c3d4-...",
    "fullnumber": "FV/1/2025",
    "series": "FV",
    "type": "vat",
    "date": "2025-04-01",
    "sold_date": "2025-04-01",
    "currency": "PLN",
    "lang": "pl",
    "description": "Usługi informatyczne — marzec 2025",
    "paymentmethod": "transfer",
    "paymentdate": "2025-04-15",
    "paymentstate": "partial",
    "total": 2214.50,
    "netto": 1800.00,
    "tax": 414.50,
    "alreadypaid": 1000.00,
    "remaining": 1214.50,
    "ksef_number": null,
    "ksef_status": null,
    "buyer": {
      "name": "Acme Sp. z o.o.",
      "nip": "1234567890",
      "street": "ul. Przykładowa 1",
      "city": "Warszawa",
      "zip": "00-001",
      "country": "Polska",
      "email": "ksiegowosc@acme.pl",
      "phone": ""
    },
    "seller": {
      "name": "Twoja Firma Sp. z o.o.",
      "nip": "9876543210",
      "street": "ul. Firmowa 5",
      "city": "Kraków",
      "zip": "30-001",
      "country": "Polska",
      "bank_account": "PL12345678901234567890123456",
      "bank_name": "PKO BP"
    },
    "items": [
      {
        "id": "b2c3d4e5-...",
        "name": "Usługa programistyczna",
        "description": "",
        "quantity": 10,
        "unit": "godz.",
        "price": 150.00,
        "gross_unit_price": 184.50,
        "discount_percent": 0,
        "netto": 1500.00,
        "vat_amount": 345.00,
        "brutto": 1845.00,
        "vat": "23%",
        "gtu_code": "",
        "pkwiu": "",
        "gtin": "",
        "cn_code": ""
      }
    ],
    "vat_summary": [
      { "vat": "23%", "netto": 1800.00, "tax": 414.50, "brutto": 2214.50 }
    ],
    "payments": [
      {
        "id": "c3d4e5f6-...",
        "payment_date": "2025-04-05",
        "amount": 1000.00,
        "payment_method": "transfer",
        "description": "Przelew częściowy",
        "created": "2025-04-05 14:30:00"
      }
    ],
    "created": "2025-04-01 10:00:00",
    "modified": "2025-04-05 14:30:00",
    "view_url": "/invoices/view/a1b2c3d4-..."
  }
}
```

| Kod HTTP | Znaczenie |
|----------|-----------|
| `200`    | OK |
| `404`    | Faktura nie znaleziona / nie należy do Twojej firmy |

---

## Endpoint: Dodaj rozliczenie płatności

### `POST /api/v1/invoices/{id}/payments`

Rejestruje wpłatę do faktury. Po zapisaniu system automatycznie przelicza `alreadypaid`, `remaining` i `paymentstate` na fakturze.

### Parametry URL

| Parametr | Opis |
|----------|------|
| `id`     | UUID faktury |

### Ciało żądania (JSON)

```json
{
  "payment_date": "2025-04-10",
  "amount": 1214.50,
  "payment_method": "transfer",
  "description": "Przelew końcowy"
}
```

| Pole             | Typ    | Wymagany | Opis |
|------------------|--------|----------|------|
| `payment_date`   | string | **Tak**  | Data wpłaty `YYYY-MM-DD` |
| `amount`         | number | **Tak**  | Kwota wpłaty (> 0) |
| `payment_method` | string | Nie      | Metoda: `transfer` / `cash` / `card` / `blik` (domyślnie: `transfer`) |
| `description`    | string | Nie      | Opis wpłaty |

### Odpowiedź — sukces `201 Created`

```json
{
  "success": true,
  "data": {
    "payment": {
      "id": "d4e5f6a7-...",
      "invoice_id": "a1b2c3d4-...",
      "payment_date": "2025-04-10",
      "amount": 1214.50,
      "payment_method": "transfer",
      "description": "Przelew końcowy"
    },
    "invoice": {
      "id": "a1b2c3d4-...",
      "total": 2214.50,
      "alreadypaid": 2214.50,
      "remaining": 0.00,
      "paymentstate": "paid"
    }
  }
}
```

| Kod HTTP | Znaczenie |
|----------|-----------|
| `201`    | Rozliczenie zapisane |
| `404`    | Faktura nie znaleziona |
| `422`    | Błąd walidacji (brakujące / niepoprawne dane) |

---

### Przykład: Rozliczenie faktury (cURL)

```bash
curl -X POST https://twoja-domena.pl/api/v1/invoices/a1b2c3d4-e5f6-7890-abcd-ef1234567890/payments \
  -H "Authorization: Bearer fv_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_date": "2025-04-10",
    "amount": 2214.50,
    "payment_method": "transfer"
  }'
```

### Przykład: Lista faktur nieopłaconych (cURL)

```bash
curl "https://twoja-domena.pl/api/v1/invoices?paymentstate=unpaid&date_from=2025-01-01&per_page=50" \
  -H "Authorization: Bearer fv_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8"
```

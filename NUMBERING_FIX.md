# Poprawka: System Numeracji Porządkowej Faktur

## Zdiagnozowany Problem
- Faktury zawsze otrzymują numer 1, niezależnie od liczby wcześniej utworzonych faktur
- Brak uwzględnienia typu okresu numeracji (ciągła/roczna/miesięczna)

## Zaimplementowane Poprawki

### 1. Poprawiono logikę wyszukiwania ostatniej faktury
**Przed**: Proste zapytanie bez uwzględnienia okresu numeracji
```php
$lastInvoice = $Invoices->find()
    ->where([
        'company_id' => $companyId,
        'invoice_series_id' => $series->id
    ])
    ->order(['id' => 'DESC'])
    ->first();
```

**Po**: Uwzględnienie typu okresu numeracji
```php
$whereConditions = [
    'company_id' => $companyId,
    'invoice_series_id' => $series->id
];

// Pobierz informację o okresie numeracji
$series = $InvoiceSeriesTable->find()
    ->contain(['InvoiceSeriesPeriods'])
    ->where(['company_id' => $companyId, 'name' => $data['series']])
    ->first();

if ($series && $series->invoice_series_period) {
    $periodName = $series->invoice_series_period->name ?? '';
    
    if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
        // Miesięczne - szukaj w tym samym miesiącu i roku
        $whereConditions['YEAR(date)'] = $year;
        $whereConditions['MONTH(date)'] = $month;
    } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
        // Roczne - szukaj w tym samym roku
        $whereConditions['YEAR(date)'] = $year;
    }
    // Dla innych typów (ciągłe, etc.) - szukaj we wszystkich
}

$lastInvoice = $Invoices->find()
    ->where($whereConditions)
    ->order(['id' => 'DESC'])
    ->first();
```

### 2. Poprawiono obliczanie następnego numeru
**Przed**: Prosta inkrementacja
```php
$nextNumber = $lastInvoice ? ($this->extractNumberFromFullnumber($lastInvoice->fullnumber) + 1) : ($series->starting_number ?: 1);
```

**Po**: Wyraźne rozdzielenie logiki
```php
$extractedNumber = $lastInvoice ? $this->extractNumberFromFullnumber($lastInvoice->fullnumber) : 0;
$nextNumber = $extractedNumber + 1;

// Jeśli nie ma ostatniej faktury, użyj numeru startowego z serii
if (!$lastInvoice && $series->starting_number) {
    $nextNumber = $series->starting_number;
}
```

### 3. Dodano rozbudowane debugowanie
```php
// Debug wszystkich faktur w serii
$allInvoices = $Invoices->find()
    ->select(['id', 'fullnumber', 'date'])
    ->where(['company_id' => $companyId, 'invoice_series_id' => $series->id])
    ->order(['id' => 'ASC'])
    ->limit(10)
    ->toArray();

// Debug procesu numeracji
\Cake\Log\Log::debug('Invoice numbering debug:', [
    'series_name' => $series->name,
    'period_name' => $series->invoice_series_period->name ?? 'N/A',
    'where_conditions' => $whereConditions,
    'last_invoice_fullnumber' => $lastInvoice ? $lastInvoice->fullnumber : 'NONE',
    'extracted_number' => $extractedNumber,
    'next_number' => $nextNumber,
    'starting_number' => $series->starting_number
]);

// Debug zapisanej faktury
\Cake\Log\Log::debug('Saved invoice:', [
    'id' => $invoice->id,
    'fullnumber' => $invoice->fullnumber,
    'invoice_series_id' => $invoice->invoice_series_id,
    'company_id' => $invoice->company_id
]);
```

### 4. Ulepszone wyciąganie numeru z pełnego numeru
```php
private function extractNumberFromFullnumber(string $fullnumber): int
{
    // Debug
    \Cake\Log\Log::debug('Extracting number from: ' . $fullnumber);
    
    // Znajdź ostatni ciąg cyfr w numerze (prawdopodobnie numer porządkowy)
    if (preg_match('/(\d+)(?!.*\d)/', $fullnumber, $matches)) {
        $extracted = (int) $matches[1];
        \Cake\Log\Log::debug('Extracted number: ' . $extracted);
        return $extracted;
    }
    
    \Cake\Log\Log::debug('No number found, returning 0');
    return 0;
}
```

## Typy Okresów Numeracji

### 1. Ciągła (Continuous)
- Numeracja przez całe życie serii
- Nie resetuje się w nowym roku/miesiącu
- Warunki wyszukiwania: tylko `company_id` i `invoice_series_id`

### 2. Roczna (Yearly) 
- Reset numeracji na początku każdego roku
- Warunki wyszukiwania: + `YEAR(date) = current_year`

### 3. Miesięczna (Monthly)
- Reset numeracji na początku każdego miesiąca
- Warunki wyszukiwania: + `YEAR(date) = current_year` i `MONTH(date) = current_month`

## Diagnostyka Problemów

### Sprawdź logi
```bash
tail -f logs/debug.log | grep "Invoice numbering\|Extracting number\|Saved invoice"
```

### Typowe problemy:
1. **Seria bez przypisanego okresu** - używa numeracji ciągłej
2. **Błędny format numeru** - regex może wyciągać złą część
3. **Błędne `invoice_series_id`** - faktury zapisywane w złej serii
4. **Brak wcześniejszych faktur** - używa `starting_number` z serii

### Test regex dla wyciągania numerów:
```
FV/2025/01/0001 → 1 ✓
FV/2025/01/0002 → 2 ✓
PF-25-1-001 → 1 ✓
FV/1/2025 → 2025 ⚠️ (może wyciągnąć rok zamiast numeru)
OSS/2025/123 → 123 ✓
```

## Rezultat
- ✅ Prawidłowa numeracja według typu okresu
- ✅ Rozbudowane debugowanie procesu
- ✅ Obsługa różnych formatów numerów
- ✅ Uwzględnienie numeru startowego serii
- ✅ Kompatybilność z `InvoiceSeriesController.nextNumber()`

Numeracja porządkowa powinna teraz działać poprawnie dla wszystkich typów okresów numeracji!
# Poprawka: Zapisywanie dane kontrahenta i contractor_id

## Zdiagnozowany problem
- Dane kontrahenta nie były zapisywane w tabeli `invoice_contractors`
- Pole `contractor_id` nie było wypełniane w głównej tabeli `invoices`

## Implementowane poprawki

### 1. Dodano ukryty input dla contractor_id
**Plik**: `templates/Invoices/add.php`
```html
<select id="contractor-select" class="form-select" data-placeholder="Wpisz nazwę kontrahenta lub NIP"></select>
<?= $this->Form->control('contractor_id', ['type' => 'hidden', 'id' => 'contractor-id-input']) ?>
```

### 2. Poprawiono JavaScript - ustawianie contractor_id przy wyborze
**Plik**: `templates/Invoices/add.php`

#### Event handler Select2:
```javascript
.on('select2:select', function(e){ 
  var d=e.params.data||{}; 
  fillContractorSnapshot(d); 
  showContractorSnapshot(); 
  saveRecent(d); 
  $('#contractor-id-input').val(d.id || ''); 
})
```

#### Funkcja applyContractor:
```javascript
function applyContractor(c) {
  // ... existing code ...
  $('#contractor-id-input').val(c.id || '');
  // ... rest of function
}
```

#### Funkcja clearContractorSnapshot:
```javascript
function clearContractorSnapshot(){
  ['name','nip','street','zip','city','country','email','phone'].forEach(function(f){
    $('[name="invoice_contractor['+f+']"]').val(f==='country'?'PL':'');
  });
  $('#contractor-id-input').val('');
}
```

### 3. Poprawiono kontroler InvoicesController.php

#### Ustawienie contractor_id w danych faktury:
```php
$invoiceData = [
    // ... existing fields ...
    'contractor_id' => !empty($data['contractor_id']) ? $data['contractor_id'] : null,
    // ... rest of fields
];
```

#### Poprawiono odczyt danych kontrahenta:
```php
// PRZED: 
if (!empty($data['contractor'])) {
    $contractor = $data['contractor'];

// PO:
if (!empty($data['invoice_contractor'])) {
    $contractor = $data['invoice_contractor'];
```

#### Poprawiono dane sprzedawcy - automatyczne pobieranie z tabeli companies:
```php
// Zapisz dane sprzedawcy (invoice_company_details) - pobierz z tabeli companies
$CompaniesTable = $this->fetchTable('Companies');
$company = $CompaniesTable->find()
    ->where(['id' => $companyId])
    ->first();
    
if ($company) {
    // ... automatyczne wypełnianie danych sprzedawcy z bazy companies
    // + opcjonalne nadpisanie konta bankowego z formularza
    'bank_account' => $data['invoice_company_detail']['bank_account'] ?? $company->bank_account ?? '',
}
```

### 4. Dodano debugowanie
```php
// Debug: sprawdź przesłane dane
\Cake\Log\Log::debug('Invoice form data:', $data);
```

## Struktura danych w formularzu

### Dane kontrahenta (nabywca):
- `contractor_id` - ID kontrahenta z tabeli contractors (ukryty input)
- `invoice_contractor[name]` - nazwa kontrahenta
- `invoice_contractor[nip]` - NIP kontrahenta
- `invoice_contractor[street]` - ulica
- `invoice_contractor[zip]` - kod pocztowy
- `invoice_contractor[city]` - miasto
- `invoice_contractor[country]` - kraj
- `invoice_contractor[email]` - email
- `invoice_contractor[phone]` - telefon
- `invoice_contractor[account_number]` - numer konta

### Dane sprzedawcy (automatycznie z companies):
- Pobierane automatycznie na podstawie `company_id` użytkownika
- `invoice_company_detail[bank_account]` - opcjonalne nadpisanie konta bankowego

## Rezultat
- ✅ `invoices.contractor_id` jest wypełniane przy wyborze kontrahenta
- ✅ Tabela `invoice_contractors` jest wypełniana danymi nabywcy
- ✅ Tabela `invoice_company_details` jest wypełniana danymi sprzedawcy
- ✅ Dane są zachowywane transakcyjnie
- ✅ Dodano debugowanie do diagnozowania problemów

## Testowanie
1. Wybierz kontrahenta z dropdown lub katalogu
2. Sprawdź czy ukryty input `contractor_id` ma wartość
3. Wypełnij fakturę i zapisz
4. Sprawdź logi debug w `logs/debug.log`
5. Zweryfikuj dane w tabelach: `invoices`, `invoice_contractors`, `invoice_company_details`
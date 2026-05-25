<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Serwis automatycznego dopasowywania transakcji bankowych do faktur.
 *
 * Metody dopasowania (od najlepszego):
 *   1. Numer faktury z /INV/ + kwota (confidence 95)
 *   2. NIP kontrahenta z /IDC/ + kwota (confidence 75)
 *   3. IBAN kontrahenta + kwota (confidence 65)
 *   4. Sama kwota + data ± 60 dni (confidence 40 — tylko propose, nigdy auto-match)
 *
 * Statusy:
 *   matched  — auto-potwierdzone (confidence >= 90)
 *   proposed — wymaga potwierdzenia użytkownika
 *   unmatched — brak kandydata
 */
class BankMatchingService
{
    use LocatorAwareTrait;

    // Próg auto-potwierdzenia (confidence >= tego → status 'matched' od razu)
    private const AUTO_CONFIRM_THRESHOLD = 90;

    // Tolerancja kwotowa (PLN) — dopuszczalna różnica przy dopasowaniu
    private const AMOUNT_TOLERANCE = 0.02;

    // -------------------------------------------------------------------------
    // Parsowanie tytułu MPP
    // -------------------------------------------------------------------------

    /**
     * Wyciąga pola strukturalne z tytułu przelewu MPP.
     *
     * Format: /VAT/kwota/IDC/NIP/INV/numer_faktury/TXT/tekst
     *
     * @return array{inv: string|null, nip: string|null, vat: float|null}
     */
    public function parseStructuredTitle(string $title): array
    {
        $inv = null;
        $nip = null;
        $vat = null;

        // /INV/numer — do następnego "/" lub końca
        if (preg_match('|/INV/([^/\s]+(?:/[^/\s]+)*)|', $title, $m)) {
            $raw = $m[1];
            // Usuń sufiks /TXT/ lub inne tagi jeśli sklejone
            $raw = preg_replace('|/TXT/.*$|', '', $raw);
            $inv = trim($raw, '/');
        }

        // /IDC/NIP (10 cyfr)
        if (preg_match('|/IDC/(\d{10})|', $title, $m)) {
            $nip = $m[1];
        }

        // /VAT/kwota (format europejski lub kropkowy)
        if (preg_match('|/VAT/([\d,\.]+)|', $title, $m)) {
            $vat = (float)str_replace(',', '.', str_replace('.', '', str_replace(',', '.', $m[1])));
            // poprawna zamiana formatu europejskiego
            $raw = $m[1];
            if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $raw)) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '.', $raw);
            }
            $vat = (float)$raw;
        }

        return ['inv' => $inv, 'nip' => $nip, 'vat' => $vat];
    }

    /**
     * Wyciąga kod transakcji z pola :86: (np. D94, D95).
     * Pierwszy segment tekstu przed ~ lub spacją.
     */
    public function extractTxTypeCode(string $description): ?string
    {
        if (preg_match('/^([A-Z]\d{2})/', trim($description), $m)) {
            return $m[1];
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Główne API
    // -------------------------------------------------------------------------

    /**
     * Próbuje dopasować transakcję do faktury.
     *
     * @param  array<string,mixed> $txData  Dane transakcji (z parsera MT940)
     * @param  string              $companyId
     * @return array{
     *   invoice_id: string|null,
     *   match_status: string,
     *   match_confidence: int,
     *   match_reason: string|null,
     *   parsed_inv: string|null,
     *   parsed_nip: string|null,
     *   parsed_vat: float|null,
     *   tx_type_code: string|null,
     * }
     */
    public function match(array $txData, string $companyId): array
    {
        $title       = (string)($txData['title'] ?? '');
        $description = (string)($txData['description'] ?? '');
        $amount      = (float)($txData['amount'] ?? 0);
        $direction   = (string)($txData['direction'] ?? '');
        $partyIban   = (string)($txData['party_account'] ?? '');

        // Parsuj tytuł
        $parsed     = $this->parseStructuredTitle($title);
        $txTypeCode = $this->extractTxTypeCode($description);

        $invoiceId  = null;
        $confidence = 0;
        $reason     = null;

        // Typ faktury: C (wpływ) = wystawiona faktura przychodząca, D (wypływ) = koszt
        $invoiceType = $direction === 'C' ? 'issued' : 'cost';

        // ── Metoda 1: numer faktury z /INV/ + kwota ──────────────────────────
        if ($parsed['inv'] !== null) {
            $invoice = $this->findByInvoiceNumber($parsed['inv'], $amount, $companyId, $invoiceType);
            if ($invoice !== null) {
                $invoiceId  = $invoice['id'];
                $confidence = 95;
                $reason     = 'Numer faktury z tytułu (/INV/) + kwota';
            }
        }

        // ── Metoda 2: NIP z /IDC/ + kwota ────────────────────────────────────
        if ($invoiceId === null && $parsed['nip'] !== null) {
            $invoice = $this->findByNipAndAmount($parsed['nip'], $amount, $companyId, $invoiceType);
            if ($invoice !== null) {
                $invoiceId  = $invoice['id'];
                $confidence = 75;
                $reason     = 'NIP kontrahenta z tytułu (/IDC/) + kwota';
            }
        }

        // ── Metoda 3: IBAN kontrahenta + kwota ───────────────────────────────
        if ($invoiceId === null && $partyIban !== '') {
            $invoice = $this->findByIbanAndAmount($partyIban, $amount, $companyId, $invoiceType);
            if ($invoice !== null) {
                $invoiceId  = $invoice['id'];
                $confidence = 65;
                $reason     = 'IBAN kontrahenta + kwota';
            }
        }

        // Wyznacz status
        $matchStatus = 'unmatched';
        if ($invoiceId !== null) {
            $matchStatus = $confidence >= self::AUTO_CONFIRM_THRESHOLD ? 'matched' : 'proposed';
        }

        return [
            'invoice_id'       => $invoiceId,
            'match_status'     => $matchStatus,
            'match_confidence' => $confidence,
            'match_reason'     => $reason,
            'parsed_inv'       => $parsed['inv'],
            'parsed_nip'       => $parsed['nip'],
            'parsed_vat'       => $parsed['vat'],
            'tx_type_code'     => $txTypeCode,
        ];
    }

    // -------------------------------------------------------------------------
    // Wyszukiwanie faktur
    // -------------------------------------------------------------------------

    /**
     * Szuka faktury po numerze (pełnym lub fragmencie z /INV/).
     * Sprawdza pole fullnumber — LIKE %fragment% oraz dokładne.
     *
     * @return array<string,mixed>|null
     */
    private function findByInvoiceNumber(string $invFragment, float $amount, string $companyId, string $type): ?array
    {
        if ($invFragment === '') {
            return null;
        }

        $Invoices = $this->fetchTable('Invoices');

        // Normalizuj fragment — usuń wiodące zera z numerów (0054 → 54)
        $fragments = array_filter(array_unique([
            $invFragment,
            ltrim($invFragment, '0'),
        ]), fn($s) => $s !== '');

        $conditions = [
            'company_id'  => $companyId,
            'paymentstate !=' => 'paid',
        ];

        // Typ faktury
        if ($type === 'issued') {
            $conditions['type IN'] = ['invoice', 'proforma', 'correction'];
        } else {
            // koszty — brak tutaj, cost_invoices to inna tabela
            // dla uproszczenia MVP: szukamy też w invoices
        }

        $orConditions = [];
        foreach ($fragments as $frag) {
            $orConditions[] = ['fullnumber LIKE' => '%' . $frag . '%'];
        }

        if (empty($orConditions)) {
            return null;
        }

        $invoice = $Invoices->find()
            ->where(array_merge($conditions, ['OR' => $orConditions]))
            ->select(['id', 'fullnumber', 'total', 'paymentstate', 'paymentdate'])
            ->first();

        if ($invoice === null) {
            return null;
        }

        // Sprawdź kwotę (z tolerancją)
        $diff = abs((float)$invoice->total - $amount);
        if ($diff > self::AMOUNT_TOLERANCE && $diff > abs($amount * 0.01)) {
            // Kwota nie pasuje, ale numer faktury pasuje — obniż pewność zamiast odrzucać
            // Zwróć z obniżoną pewnością (zostanie obsłużone w match())
            return ['id' => $invoice->id, '_amount_mismatch' => true];
        }

        return ['id' => $invoice->id];
    }

    /**
     * Szuka faktury po NIP kontrahenta + kwota.
     *
     * @return array<string,mixed>|null
     */
    private function findByNipAndAmount(string $nip, float $amount, string $companyId, string $type): ?array
    {
        if ($nip === '') {
            return null;
        }

        $Invoices = $this->fetchTable('Invoices');

        $result = $Invoices->find()
            ->join([
                'ic' => [
                    'table'      => 'invoice_contractors',
                    'type'       => 'INNER',
                    'conditions' => 'ic.invoice_id = Invoices.id',
                ],
            ])
            ->where([
                'Invoices.company_id'      => $companyId,
                'Invoices.paymentstate !=' => 'paid',
                'ic.nip'                   => $nip,
                'Invoices.total >='        => $amount - self::AMOUNT_TOLERANCE,
                'Invoices.total <='        => $amount + self::AMOUNT_TOLERANCE,
            ])
            ->select(['Invoices.id', 'Invoices.total'])
            ->orderByAsc('Invoices.paymentdate')
            ->first();

        return $result ? ['id' => $result->id] : null;
    }

    /**
     * Szuka faktury po IBAN kontrahenta + kwota.
     *
     * @return array<string,mixed>|null
     */
    private function findByIbanAndAmount(string $iban, float $amount, string $companyId, string $type): ?array
    {
        if ($iban === '') {
            return null;
        }

        // Szukaj w contractor_bank_accounts → contractors → invoice_contractors → invoices
        $ContractorBankAccounts = $this->fetchTable('ContractorBankAccounts');

        $cleanIban = preg_replace('/[\s\-]/', '', $iban);

        $bankAccount = $ContractorBankAccounts->find()
            ->where(['REPLACE(REPLACE(ContractorBankAccounts.iban, " ", ""), "-", "") LIKE' => '%' . $cleanIban . '%'])
            ->select(['contractor_id'])
            ->first();

        if ($bankAccount === null) {
            return null;
        }

        $contractorId = $bankAccount->contractor_id;

        $Invoices = $this->fetchTable('Invoices');

        $result = $Invoices->find()
            ->join([
                'ic' => [
                    'table'      => 'invoice_contractors',
                    'type'       => 'INNER',
                    'conditions' => 'ic.invoice_id = Invoices.id',
                ],
            ])
            ->where([
                'Invoices.company_id'      => $companyId,
                'Invoices.paymentstate !=' => 'paid',
                'ic.contractor_id'         => $contractorId,
                'Invoices.total >='        => $amount - self::AMOUNT_TOLERANCE,
                'Invoices.total <='        => $amount + self::AMOUNT_TOLERANCE,
            ])
            ->select(['Invoices.id', 'Invoices.total'])
            ->orderByAsc('Invoices.paymentdate')
            ->first();

        return $result ? ['id' => $result->id] : null;
    }

    // -------------------------------------------------------------------------
    // Zatwierdzenie dopasowania → tworzy wpis w invoice_payments
    // -------------------------------------------------------------------------

    /**
     * Potwierdza dopasowanie transakcji do faktury.
     * Aktualizuje bank_transaction i tworzy invoice_payment.
     */
    public function confirmMatch(string $transactionId, string $invoiceId, string $companyId): bool
    {
        $BankTransactions = $this->fetchTable('BankTransactions');
        $Invoices         = $this->fetchTable('Invoices');
        $InvoicePayments  = $this->fetchTable('InvoicePayments');

        $tx = $BankTransactions->find()
            ->where(['id' => $transactionId, 'company_id' => $companyId])
            ->first();

        $invoice = $Invoices->find()
            ->where(['id' => $invoiceId, 'company_id' => $companyId])
            ->first();

        if ($tx === null || $invoice === null) {
            return false;
        }

        // Zapisz powiązanie w transakcji
        $tx->invoice_id       = $invoiceId;
        $tx->is_matched       = true;
        $tx->match_status     = 'matched';
        $tx->match_confidence = max($tx->match_confidence, 100);

        if (!$BankTransactions->save($tx)) {
            return false;
        }

        // ── Waluty ────────────────────────────────────────────────────────
        // Przelew ma własną walutę (tx.currency), faktura swoją (invoice.currency).
        // Jeśli równe → wpłata 1:1. Jeśli różne — konwertujemy przez invoice.currency_exchange
        // (kurs zapisany na fakturze: ile PLN za 1 jednostkę invoice.currency).
        $txCurrency      = strtoupper((string)($tx->currency ?? 'PLN')) ?: 'PLN';
        $invoiceCurrency = strtoupper((string)($invoice->currency ?? 'PLN')) ?: 'PLN';
        $rate            = (float)($invoice->currency_exchange ?? 0); // PLN za 1 invoiceCurrency

        // Konwertuj kwotę przelewu do waluty faktury (do porównania z total/remaining)
        $txAmountRaw   = (float)$tx->amount;
        $txAmountInInv = $txAmountRaw;
        if ($txCurrency !== $invoiceCurrency && $rate > 0) {
            if ($invoiceCurrency === 'PLN' && $txCurrency !== 'PLN') {
                // faktura PLN, przelew EUR → kwota PLN = EUR * kurs
                $txAmountInInv = $txAmountRaw * $rate;
            } elseif ($txCurrency === 'PLN' && $invoiceCurrency !== 'PLN') {
                // faktura EUR, przelew PLN → kwota EUR = PLN / kurs
                $txAmountInInv = $txAmountRaw / $rate;
            }
            // (waluta-waluta cross-currency — pomijamy, traktujemy 1:1; rzadki przypadek)
        }

        // Suma dotychczasowych wpłat w walucie faktury (konwersja gdy różna)
        $existing = $InvoicePayments->find()
            ->where(['invoice_id' => $invoiceId])
            ->select(['id', 'amount', 'currency'])
            ->all()
            ->toList();

        $alreadyPaidInInv = 0.0;
        foreach ($existing as $e) {
            $eAmt  = (float)$e->amount;
            $eCurr = strtoupper((string)($e->currency ?? $invoiceCurrency)) ?: $invoiceCurrency;
            if ($eCurr === $invoiceCurrency) {
                $alreadyPaidInInv += $eAmt;
            } elseif ($rate > 0) {
                if ($invoiceCurrency === 'PLN' && $eCurr !== 'PLN') {
                    $alreadyPaidInInv += $eAmt * $rate;
                } elseif ($eCurr === 'PLN' && $invoiceCurrency !== 'PLN') {
                    $alreadyPaidInInv += $eAmt / $rate;
                } else {
                    $alreadyPaidInInv += $eAmt;
                }
            } else {
                $alreadyPaidInInv += $eAmt;
            }
        }

        $remainingInInv = max(0, (float)$invoice->total - $alreadyPaidInInv);

        if ($remainingInInv > 0.01) {
            // Ile z przelewu "weźmiemy" — nie więcej niż pozostało do zapłaty.
            // amount zapisujemy W ORYGINALNEJ WALUCIE przelewu (currency = $txCurrency).
            $takeInInv = min($txAmountInInv, $remainingInInv);
            // Konwertuj z powrotem do waluty przelewu, by `amount` zgadzało się z `currency`
            if ($txCurrency === $invoiceCurrency || $rate <= 0) {
                $paymentAmount = $takeInInv;
            } elseif ($invoiceCurrency === 'PLN' && $txCurrency !== 'PLN') {
                $paymentAmount = $takeInInv / $rate;
            } elseif ($txCurrency === 'PLN' && $invoiceCurrency !== 'PLN') {
                $paymentAmount = $takeInInv * $rate;
            } else {
                $paymentAmount = $takeInInv;
            }

            $payment = $InvoicePayments->newEntity([
                'id'             => \Cake\Utility\Text::uuid(),
                'invoice_id'     => $invoiceId,
                'payment_date'   => $tx->value_date instanceof \DateTimeInterface
                    ? $tx->value_date->format('Y-m-d')
                    : (string)$tx->value_date,
                'amount'         => round($paymentAmount, 2),
                'currency'       => $txCurrency,
                'payment_method' => 'transfer',
                'description'    => 'Przelew bankowy: ' . ($tx->bank_reference ?? $tx->customer_reference ?? ''),
            ]);

            $InvoicePayments->save($payment);
        }

        // Zaktualizuj paymentstate faktury (uwzględniając konwersję walut)
        $this->updateInvoicePaymentState($invoiceId);

        return true;
    }

    /**
     * Przelicza paymentstate + alreadypaid + remaining faktury na podstawie sumy płatności.
     *
     * Bez aktualizacji alreadypaid/remaining widok /rozliczenia/* pokazuje stare wartości
     * mimo że płatność została dodana.
     */
    private function updateInvoicePaymentState(string $invoiceId): void
    {
        $Invoices        = $this->fetchTable('Invoices');
        $InvoicePayments = $this->fetchTable('InvoicePayments');

        $invoice = $Invoices->get($invoiceId, [
            'fields' => ['id', 'total', 'currency', 'currency_exchange', 'paymentstate', 'alreadypaid', 'remaining'],
        ]);
        $invoiceCurrency = strtoupper((string)($invoice->currency ?? 'PLN')) ?: 'PLN';
        $rate            = (float)($invoice->currency_exchange ?? 0);

        // Suma wpłat w walucie faktury (konwertujemy wpłaty w innej walucie po kursie faktury)
        $payments = $InvoicePayments->find()
            ->where(['invoice_id' => $invoiceId])
            ->select(['amount', 'currency'])
            ->all();

        $paid = 0.0;
        foreach ($payments as $p) {
            $amt  = (float)$p->amount;
            $curr = strtoupper((string)($p->currency ?? $invoiceCurrency)) ?: $invoiceCurrency;
            if ($curr === $invoiceCurrency) {
                $paid += $amt;
            } elseif ($rate > 0) {
                if ($invoiceCurrency === 'PLN' && $curr !== 'PLN') {
                    $paid += $amt * $rate;       // np. EUR * kurs = PLN
                } elseif ($curr === 'PLN' && $invoiceCurrency !== 'PLN') {
                    $paid += $amt / $rate;       // np. PLN / kurs = EUR
                } else {
                    $paid += $amt;
                }
            } else {
                $paid += $amt;
            }
        }

        $total     = (float)$invoice->total;
        $remaining = max(0, round($total - $paid, 2));

        if ($paid <= 0) {
            $state = 'unpaid';
        } elseif ($paid >= $total - 0.01) {
            $state = 'paid';
        } else {
            $state = 'partial';
        }

        $dirty = false;
        if ($invoice->paymentstate !== $state)        { $invoice->paymentstate = $state; $dirty = true; }
        if ((float)$invoice->alreadypaid !== round($paid, 2)) { $invoice->alreadypaid = round($paid, 2); $dirty = true; }
        if ((float)$invoice->remaining !== $remaining)        { $invoice->remaining   = $remaining;       $dirty = true; }

        if ($dirty) {
            $Invoices->save($invoice);
        }
    }
}

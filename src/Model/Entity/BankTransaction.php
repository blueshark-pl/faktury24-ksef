<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * BankTransaction Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string $import_id
 * @property string|null $account_number
 * @property \Cake\I18n\Date $value_date
 * @property \Cake\I18n\Date|null $booking_date
 * @property string $direction  'C' = wpływ (credit), 'D' = wypływ (debit)
 * @property float $amount
 * @property string $currency
 * @property string|null $transaction_code
 * @property string|null $customer_reference
 * @property string|null $bank_reference
 * @property string|null $description
 * @property string|null $party_name
 * @property string|null $party_account
 * @property string|null $title
 * @property string $import_hash
 * @property string|null $invoice_id
 * @property bool $is_matched
 * @property string $match_status  unmatched|proposed|matched|ignored
 * @property int $match_confidence  0–100
 * @property string|null $match_reason
 * @property string|null $parsed_inv
 * @property string|null $parsed_nip
 * @property float|null $parsed_vat
 * @property string|null $tx_type_code
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\BankStatementImport $bank_statement_import
 */
class BankTransaction extends Entity
{
    protected array $_accessible = [
        'company_id'         => true,
        'import_id'          => true,
        'account_number'     => true,
        'value_date'         => true,
        'booking_date'       => true,
        'direction'          => true,
        'amount'             => true,
        'currency'           => true,
        'transaction_code'   => true,
        'customer_reference' => true,
        'bank_reference'     => true,
        'description'        => true,
        'party_name'         => true,
        'party_account'      => true,
        'title'              => true,
        'import_hash'        => true,
        'invoice_id'         => true,
        'is_matched'         => true,
        'match_status'       => true,
        'match_confidence'   => true,
        'match_reason'       => true,
        'parsed_inv'         => true,
        'parsed_nip'         => true,
        'parsed_vat'         => true,
        'tx_type_code'       => true,
    ];

    /** Kwota ze znakiem (ujemna = debet) */
    public function getSignedAmount(): float
    {
        return $this->direction === 'D' ? -$this->amount : $this->amount;
    }
}

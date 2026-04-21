<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * BankStatementImport Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $filename
 * @property string|null $account_number
 * @property string|null $currency
 * @property \Cake\I18n\Date|null $statement_from
 * @property \Cake\I18n\Date|null $statement_to
 * @property float|null $opening_balance
 * @property float|null $closing_balance
 * @property int $transaction_count
 * @property int $new_count
 * @property int $duplicate_count
 * @property string|null $imported_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\BankTransaction[] $bank_transactions
 */
class BankStatementImport extends Entity
{
    protected array $_accessible = [
        'company_id'        => true,
        'filename'          => true,
        'account_number'    => true,
        'currency'          => true,
        'statement_from'    => true,
        'statement_to'      => true,
        'opening_balance'   => true,
        'closing_balance'   => true,
        'transaction_count' => true,
        'new_count'         => true,
        'duplicate_count'   => true,
        'imported_by'       => true,
    ];
}

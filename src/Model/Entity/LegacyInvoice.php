<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property int $glo_id
 * @property int $rejestr
 * @property string $fullnumber
 * @property \Cake\I18n\Date|string|null $date
 * @property \Cake\I18n\Date|string|null $paymentdate
 * @property string $contractor_name
 * @property string|null $contractor_nip
 * @property string|null $contractor_city
 * @property string|null $contractor_country
 * @property string|null $contractor_skrot
 * @property float $total
 * @property float $netto
 * @property float $alreadypaid
 * @property float $remaining
 * @property string $currency
 * @property float $total_wal
 * @property float $alreadypaid_wal
 * @property float $remaining_wal
 * @property float|null $exchange_rate
 * @property string $paymentstate
 * @property int|null $dnit
 * @property string|null $platnosc
 * @property string|null $teczka
 * @property string|null $glo_tyt1
 * @property string|null $poz_naz7
 * @property string|null $poz_nazwa
 * @property string|null $glo_rozrach
 * @property \Cake\I18n\DateTime $synced_at
 */
class LegacyInvoice extends Entity
{
    protected array $_accessible = [
        'company_id'       => true,
        'glo_id'           => true,
        'rejestr'          => true,
        'fullnumber'       => true,
        'date'             => true,
        'paymentdate'      => true,
        'glo_tyt1'         => true,
        'poz_naz7'         => true,
        'poz_nazwa'        => true,
        'contractor_name'  => true,
        'contractor_nip'   => true,
        'contractor_city'  => true,
        'contractor_country' => true,
        'contractor_skrot' => true,
        'total'            => true,
        'netto'            => true,
        'alreadypaid'      => true,
        'remaining'        => true,
        'currency'         => true,
        'total_wal'        => true,
        'alreadypaid_wal'  => true,
        'remaining_wal'    => true,
        'exchange_rate'    => true,
        'paymentstate'     => true,
        'dnit'             => true,
        'platnosc'         => true,
        'teczka'           => true,
        'glo_rozrach'      => true,
        'synced_at'        => true,
    ];
}

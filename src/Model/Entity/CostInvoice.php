<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Faktura kosztowa (od przewoźnika) — z KSeF lub wgrana ręcznie.
 *
 * @property int         $id
 * @property string      $source          ksef|manual
 * @property string|null $ksef_number
 * @property string|null $invoice_number
 * @property string|null $contractor_name
 * @property string|null $contractor_nip
 * @property \Cake\I18n\Date|null $issue_date
 * @property \Cake\I18n\Date|null $receipt_date
 * @property \Cake\I18n\Date|null $payment_date   Termin płatności
 * @property \Cake\I18n\Date|null $paid_at        Faktyczna data zapłaty
 * @property float       $paid_amount      Suma wpłacona (0..brutto)
 * @property string|null $payment_method   transfer|cash|card|compensation|other
 * @property string|null $accounting_month  YYYY-MM
 * @property float       $netto
 * @property float       $vat
 * @property float       $brutto
 * @property string      $currency
 * @property string      $status           received|verified|paid
 * @property string|null $pdf_path
 * @property string|null $xml_path
 * @property string|null $ksef_raw_json
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\SpeedOrder[] $speed_orders
 */
class CostInvoice extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

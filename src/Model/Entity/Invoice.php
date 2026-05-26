<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Invoice Entity
 *
 * @property string $id
 * @property string|null $hash
 * @property string $company_id
 * @property string|null $parent_id
 * @property string|null $contractor_id
 * @property string|null $invoice_series_id
 * @property string|null $type
 * @property string|null $correction_type
 * @property bool $simplified_invoice
 * @property string|null $paymentmethod
 * @property \Cake\I18n\Date|null $paymentdate
 * @property string|null $paymentstate
 * @property \Cake\I18n\Date $date
 * @property string $total
 * @property string $netto
 * @property string $tax
 * @property string $alreadypaid
 * @property string $remaining
 * @property string|null $fullnumber
 * @property string|null $corrected_invoice_number
 * @property int|null $number
 * @property int|null $day
 * @property int|null $month
 * @property int|null $year
 * @property int|null $day_year
 * @property string $currency
 * @property \Cake\I18n\Date|null $currency_date
 * @property string $currency_exchange
 * @property string|null $description
 * @property string|null $margin_type
 * @property bool $is_receipt_invoice
 * @property bool $is_split_payment
 * @property string|null $receipt_number
 * @property \Cake\I18n\Date|null $receipt_date
 * @property \Cake\I18n\Date|null $sold_date
 * @property \Cake\I18n\Date|null $advance_received_date
 * @property \Cake\I18n\Date|null $paid_at
 * @property \Cake\I18n\Date|null $partial_paid_at
 * @property string|null $lang
 * @property bool $auto_send
 * @property bool $buyer_is_jst
 * @property bool $buyer_in_vat_group
 * @property array|null $annotations
 * @property string|null $annotations_tax_free
 * @property string|null $annotations_tax_free_field
 * @property string|null $seller_vat_prefix
 * @property string|null $seller_vat_eu
 * @property string|null $seller_eori
 * @property string|null $buyer_vat_prefix
 * @property string|null $buyer_vat_eu
 * @property string|null $buyer_eori
 * @property string|null $buyer_tax_id_other
 * @property string|null $buyer_tax_id_other_country
 * @property string|null $company_bank_account_id
 * @property \Cake\I18n\Date|null $period_from
 * @property \Cake\I18n\Date|null $period_to
 * @property string|null $wz_number
 * @property string|null $correction_reason
 * @property string|null $place_of_issue
 * @property string|null $footer_text
 * @property string|null $payment_link
 * @property bool $is_print
 * @property string|null $issuer
 * @property bool $is_sent
 * @property bool $is_api
 * @property string|null $workflow_status
 * @property \Cake\I18n\Date|null $planned_ksef_send_at
 * @property string|null $upo_xml
 * @property \Cake\I18n\DateTime|null $upo_downloaded_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\ParentInvoice $parent_invoice
 * @property \App\Model\Entity\InvoiceCompanyDetail $invoice_company_detail
 * @property \App\Model\Entity\InvoiceContractor $invoice_contractor
 * @property \App\Model\Entity\InvoiceContent[] $invoice_contents
 * @property \App\Model\Entity\InvoiceRecipient|null $invoice_recipient
 * @property \App\Model\Entity\InvoiceVatContent[] $invoice_vat_contents
 * @property \App\Model\Entity\ChildInvoice[] $child_invoices
 * @property \App\Model\Entity\InvoicePayment[] $invoice_payments
 */
class Invoice extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'hash' => true,
        'company_id' => true,
        'parent_id' => true,
        'contractor_id' => true,
        'invoice_series_id' => true,
        'type' => true,
        'correction_type' => true,
        'simplified_invoice' => true,
        'paymentmethod' => true,
        'paymentdate' => true,
        'paymentstate' => true,
        'date' => true,
        'total' => true,
        'netto' => true,
        'tax' => true,
        'alreadypaid' => true,
        'remaining' => true,
        'fullnumber' => true,
        'corrected_invoice_number' => true,
        'number' => true,
        'day' => true,
        'month' => true,
        'year' => true,
        'day_year' => true,
        'currency' => true,
        'currency_date' => true,
        'currency_exchange' => true,
        'description' => true,
        'margin_type' => true,
    'is_receipt_invoice' => true,
    'is_split_payment' => true,
    'receipt_number' => true,
    'receipt_date' => true,
        'sold_date' => true,
        'advance_received_date' => true,
        'paid_at' => true,
        'partial_paid_at' => true,
        'lang' => true,
        'auto_send' => true,
        'buyer_is_jst' => true,
        'buyer_in_vat_group' => true,
        'annotations' => true,
        'annotations_tax_free' => true,
        'annotations_tax_free_field' => true,
        'seller_vat_prefix' => true,
        'seller_vat_eu' => true,
        'seller_eori' => true,
        'buyer_vat_prefix' => true,
        'buyer_vat_eu' => true,
        'buyer_eori' => true,
        'buyer_tax_id_other' => true,
        'buyer_tax_id_other_country' => true,
        'company_bank_account_id' => true,
        'period_from' => true,
        'period_to' => true,
        'wz_number' => true,
        'correction_reason' => true,
        'place_of_issue' => true,
        'footer_text' => true,
        'payment_link' => true,
        'is_print' => true,
        'issuer' => true,
        'is_sent' => true,
        'is_api' => true,
        'workflow_status' => true,
        'planned_ksef_send_at' => true,
        'upo_xml' => true,
        'upo_downloaded_at' => true,
        'ksef_status' => true,
        'ksef_number' => true,
        'ksef_desc' => true,
    'ksef_session_reference' => true,
    'ksef_invoice_reference' => true,
        'ksef_xml_hash' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'parent_invoice' => true,
        'invoice_company_detail' => true,
        'invoice_contractor' => true,
        'invoice_contents' => true,
        'invoice_recipient' => true,
        'invoice_vat_contents' => true,
        'child_invoices' => true,
        'invoice_payments' => true,
        'is_new_transport_wdt' => true,
        'p_42_5' => true,
        'skonto_conditions' => true,
        'skonto_amount' => true,
        'status_info_podatnika' => true,
        'transaction_conditions_json' => true,
        'order_total_gross' => true,
        'invoice_new_transports' => true,
        'invoice_order_lines' => true,
        'invoice_charges' => true,
        'invoice_factor_banks' => true,
        'invoice_authorized_entities' => true,
        'email_sent_at' => true,
    ];
}

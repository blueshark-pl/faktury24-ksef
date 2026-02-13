<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceSeries Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $invoice_series_type_id
 * @property string|null $invoice_series_period_id
 * @property bool $is_default
 * @property string $name
 * @property string $series_template
 * @property int $starting_number
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\InvoiceSeriesType $invoice_series_type
 * @property \App\Model\Entity\InvoiceSeriesPeriod $invoice_series_period
 */
class InvoiceSeries extends Entity
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
        'company_id' => true,
        'invoice_series_type_id' => true,
        'invoice_series_period_id' => true,
        'is_default' => true,
        'is_system' => true,
        'is_blocked' => true,
        'parent_id' => true,
        'name' => true,
        'type' => true,
        'series_template' => true,
        'starting_number' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'invoice_series_type' => true,
        'invoice_series_period' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceFactorBank Entity — RachunekBankowyFaktora.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $nr_rb
 * @property string|null $swift
 * @property string|null $rachunek_wlasny_banku
 * @property string|null $nazwa_banku
 * @property string|null $opis_rachunku
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceFactorBank extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'nr_rb' => true,
        'swift' => true,
        'rachunek_wlasny_banku' => true,
        'nazwa_banku' => true,
        'opis_rachunku' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

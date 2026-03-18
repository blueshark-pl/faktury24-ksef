<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceCharge Entity — Rozliczenie: Obciążenia / Odliczenia.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $type   obciazenie | odliczenie
 * @property string $kwota
 * @property string $powod
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceCharge extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'type' => true,
        'kwota' => true,
        'powod' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

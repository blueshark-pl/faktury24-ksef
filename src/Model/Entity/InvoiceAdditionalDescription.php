<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceAdditionalDescription Entity
 *
 * Represents a single DodatkowyOpis (key/value) entry on an invoice.
 * FA(3) KSeF structure: <DodatkowyOpis> → <NrWiersza>, <Klucz>, <Wartosc>
 *
 * @property string $id
 * @property string $invoice_id
 * @property int|null $nr_wiersza
 * @property string $klucz
 * @property string $wartosc
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceAdditionalDescription extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'nr_wiersza' => true,
        'klucz' => true,
        'wartosc' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

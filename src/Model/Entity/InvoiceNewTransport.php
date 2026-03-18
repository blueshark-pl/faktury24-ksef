<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceNewTransport Entity — NoweSrodkiTransportu → NowySrodekTransportu.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string|null $p_22a
 * @property int|null $p_nrwierszanst
 * @property string|null $p_22bmk
 * @property string|null $p_22bmd
 * @property string|null $p_22bk
 * @property string|null $p_22bnr
 * @property string|null $p_22brp
 * @property string|null $p_22b
 * @property string|null $p_22b1
 * @property string|null $p_22b2
 * @property string|null $p_22b3
 * @property string|null $p_22b4
 * @property string|null $p_22bt
 * @property string|null $p_22c
 * @property string|null $p_22c1
 * @property string|null $p_22d
 * @property string|null $p_22d1
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceNewTransport extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'p_22a' => true,
        'p_nrwierszanst' => true,
        'p_22bmk' => true,
        'p_22bmd' => true,
        'p_22bk' => true,
        'p_22bnr' => true,
        'p_22brp' => true,
        'p_22b' => true,
        'p_22b1' => true,
        'p_22b2' => true,
        'p_22b3' => true,
        'p_22b4' => true,
        'p_22bt' => true,
        'p_22c' => true,
        'p_22c1' => true,
        'p_22d' => true,
        'p_22d1' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Encja statusu workflow faktury kosztowej z KSeF.
 *
 * @property string $id
 * @property string $company_id
 * @property string $environment
 * @property string $ksef_number
 * @property int    $cost_status
 * @property \Cake\I18n\FrozenDate|null $docs_received_at
 * @property \Cake\I18n\FrozenDate|null $payment_due_date
 * @property string|null $rejection_reason
 * @property string|null $notes
 * @property string|null $changed_by
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class KsefInvoiceStatus extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];

    /**
     * Słownik statusów workflow.
     */
    public const STATUSES = [
        1 => 'FV DO POTWIERDZENIA',
        2 => 'FV OCZEKUJĄCA NA DOKUMENTY',
        3 => 'FV GOTOWA DO AKCEPTACJI',
        4 => 'FV ZAAKCEPTOWANA',
        5 => 'FV DO OPŁACENIA',
        6 => 'FV PRZETERMINOWANA',
        7 => 'FV ODRZUCONA',
        8 => 'FV WSTRZYMANA',
        9 => 'FV DO WYJAŚNIENIA',
    ];

    /** Kolory Bootstrap per status (subtle badge) */
    public const STATUS_COLORS = [
        1 => 'warning',
        2 => 'orange',
        3 => 'primary',
        4 => 'success',
        5 => 'info',
        6 => 'danger',
        7 => 'dark',
        8 => 'secondary',
        9 => 'warning',
    ];

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->cost_status] ?? (string)$this->cost_status;
    }

    public function getStatusColor(): string
    {
        return self::STATUS_COLORS[$this->cost_status] ?? 'secondary';
    }
}

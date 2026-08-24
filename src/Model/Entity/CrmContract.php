<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property string|null $contractor_id
 * @property string|null $contractor_nip
 * @property string|null $contractor_name
 * @property string      $name
 * @property string|null $from_country
 * @property string|null $from_postal_code
 * @property string|null $from_city
 * @property string|null $to_country
 * @property string|null $to_postal_code
 * @property string|null $to_city
 * @property float       $price_netto
 * @property string      $currency
 * @property int|null    $vat_rate
 * @property int|null    $payment_days
 * @property string|null $required_vehicle_type
 * @property int|null    $committed_volume
 * @property string|null $volume_period
 * @property int         $used_volume
 * @property \Cake\I18n\Date|null $valid_from
 * @property \Cake\I18n\Date|null $valid_to
 * @property bool        $is_active
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Contractor|null $contractor
 */
class CrmContract extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];

    /**
     * Ile dni do wygasnicia (null jesli bezterminowy).
     */
    public function getDaysToExpire(): ?int
    {
        if (!$this->valid_to) return null;
        $today = new \DateTimeImmutable('today');
        $expire = new \DateTimeImmutable($this->valid_to->format('Y-m-d'));
        $diff = $today->diff($expire);
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Procent wykorzystania volumenu (0-100+, null jesli brak committed).
     */
    public function getVolumeUsedPct(): ?int
    {
        if (empty($this->committed_volume) || $this->committed_volume <= 0) return null;
        return (int)round(($this->used_volume / $this->committed_volume) * 100);
    }

    /**
     * Etykieta trasy do wyświetlenia.
     */
    public function getRouteLabel(): string
    {
        $from = trim(($this->from_city ?? '') . ($this->from_country ? ' (' . $this->from_country . ')' : ''));
        $to   = trim(($this->to_city ?? '') . ($this->to_country ? ' (' . $this->to_country . ')' : ''));
        if ($from === '' && $to === '') return '— dowolna trasa —';
        return ($from ?: '?') . ' → ' . ($to ?: '?');
    }
}

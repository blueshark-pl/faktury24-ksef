<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string                       $id
 * @property string                       $user_id
 * @property string                       $nip
 * @property string|null                  $company_name
 * @property string|null                  $contractor_id
 * @property string|null                  $caretaker_user_id
 * @property string|null                  $substitute_user_id
 * @property \Cake\I18n\Date|null         $substitute_from
 * @property \Cake\I18n\Date|null         $substitute_to
 * @property string                       $locale
 * @property \Cake\I18n\DateTime|null     $created
 * @property \Cake\I18n\DateTime|null     $modified
 */
class ClientProfile extends Entity
{
    protected array $_accessible = [
        'user_id'            => true,
        'nip'                => true,
        'company_name'       => true,
        'contractor_id'      => true,
        'caretaker_user_id'  => true,
        'substitute_user_id' => true,
        'substitute_from'    => true,
        'substitute_to'      => true,
        'locale'             => true,
        'created'            => true,
        'modified'           => true,
    ];

    protected array $_virtual = ['current_caretaker_user_id', 'is_substitute_active'];

    /**
     * Czy zastępstwo jest aktualnie aktywne (dziś mieści się w okresie).
     */
    protected function _getIsSubstituteActive(): bool
    {
        if (empty($this->substitute_user_id)) {
            return false;
        }
        // Porównujemy daty jako stringi YYYY-MM-DD — ISO 8601 sortuje się
        // leksykograficznie tak samo jak chronologicznie. Działa zarówno gdy
        // ORM hydratował kolumnę do Date/ChronosDate, jak i gdy zwrócił string.
        $today = date('Y-m-d');
        $from  = $this->_dateToString($this->substitute_from);
        $to    = $this->_dateToString($this->substitute_to);

        if ($from !== null && $today < $from) {
            return false;
        }
        if ($to !== null && $today > $to) {
            return false;
        }
        return true;
    }

    private function _dateToString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return substr((string)$value, 0, 10);
    }

    /**
     * Aktualny opiekun = zastępca w okresie zastępstwa, inaczej główny opiekun.
     */
    protected function _getCurrentCaretakerUserId(): ?string
    {
        return $this->is_substitute_active
            ? (string)$this->substitute_user_id
            : ($this->caretaker_user_id ? (string)$this->caretaker_user_id : null);
    }
}

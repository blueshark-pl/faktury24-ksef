<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Rekord wniosku o kredyt kupiecki z systemu Allianz Trade / Syntesys.
 *
 * @property int         $id
 * @property int         $external_id
 * @property string      $list_status        WITH_OPINION|PROCESSING|NO_OPINION|BUSINESS_ERROR
 * @property string|null $identifier         NIP kontrahenta
 * @property string|null $identifier_type_code
 * @property string|null $country
 * @property string|null $advice_type_code   CCAT1=Tak, CCAT2=Nie, CCAT3=Brak opinii
 * @property string|null $advice_reason_code CCCR*
 * @property array|null  $advice_json        zdekodowany JSON opinii
 * @property array|null  $client_json        zdekodowany JSON klienta
 * @property string|null $status_code
 * @property string|null $error_type_code    CCAN*
 * @property \Cake\I18n\DateTime|null $advice_created_at
 * @property string|null $created_by
 * @property bool        $latest_advice_with_opinion
 * @property bool        $automatic_renewal_excluded
 * @property bool        $created_by_automatic_renewal
 * @property string|null $contractor_id
 * @property \Cake\I18n\DateTime $synced_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class CreditCheck extends Entity
{
    protected array $_accessible = [
        'external_id'                  => true,
        'list_status'                  => true,
        'identifier'                   => true,
        'identifier_type_code'         => true,
        'country'                      => true,
        'advice_type_code'             => true,
        'advice_reason_code'           => true,
        'advice_json'                  => true,
        'client_json'                  => true,
        'client_name'                  => true,
        'client_vat_eu'                => true,
        'client_city'                  => true,
        'status_code'                  => true,
        'error_type_code'              => true,
        'advice_created_at'            => true,
        'created_by'                   => true,
        'latest_advice_with_opinion'   => true,
        'automatic_renewal_excluded'   => true,
        'created_by_automatic_renewal' => true,
        'contractor_id'                => true,
        'synced_at'                    => true,
    ];

    // -------------------------------------------------------------------------
    // Wirtualne właściwości / etykiety
    // -------------------------------------------------------------------------

    /** Etykieta czytelna dla list_status */
    protected function _getListStatusLabel(): string
    {
        return match ($this->list_status) {
            'WITH_OPINION'   => 'Opinia wydana',
            'PROCESSING'     => 'Oczekuje',
            'NO_OPINION'     => 'Brak opinii',
            'BUSINESS_ERROR' => 'Błąd',
            default          => $this->list_status ?? '',
        };
    }

    /** Etykieta dla advice_type_code CCAT* */
    protected function _getAdviceTypeLabel(): string
    {
        return match ($this->advice_type_code) {
            'CCAT1' => 'TAK',
            'CCAT2' => 'NIE',
            'CCAT3' => 'Brak opinii',
            default => $this->advice_type_code ?? '—',
        };
    }

    /** Klasa Bootstrap badge dla advice_type_code */
    protected function _getAdviceTypeBadgeClass(): string
    {
        return match ($this->advice_type_code) {
            'CCAT1' => 'success',
            'CCAT2' => 'danger',
            'CCAT3' => 'secondary',
            default => 'light text-muted',
        };
    }

    /** Czytelny opis błędu CCAN* */
    protected function _getErrorTypeLabel(): string
    {
        return match ($this->error_type_code) {
            'CCAN1'  => 'Nie odnaleziono klienta',
            'CCAN3'  => 'Kraj poza zakresem ubezpieczenia',
            'CCAN5'  => 'Opinia już wydana',
            'CCAN9'  => 'Firma zgłosiła sprzeciw (RODO)',
            'CCAN15' => 'Problem techniczny',
            'CCAN16' => 'Opinia już wydana',
            default  => $this->error_type_code ?? '—',
        };
    }
}

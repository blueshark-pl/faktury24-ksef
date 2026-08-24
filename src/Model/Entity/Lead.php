<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property string|null $contractor_id
 * @property string      $company_name
 * @property string|null $nip
 * @property string|null $country_code
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $street
 * @property string|null $contact_person
 * @property string|null $contact_role
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $linkedin_url
 * @property string|null $linkedin_company_url
 * @property string|null $contact_channel
 * @property string|null $branch_type
 * @property string      $stage
 * @property int         $probability
 * @property float|null  $value_pln
 * @property string      $currency
 * @property bool        $flag_contact
 * @property bool        $flag_inquiry
 * @property bool        $flag_offer
 * @property bool        $flag_order
 * @property string|null $assigned_to_user_id
 * @property string|null $source
 * @property bool        $kanban_pinned
 * @property \Cake\I18n\Date|null $snooze_until
 * @property string|null $note
 * @property \Cake\I18n\DateTime|null $next_action_at
 * @property string|null $next_action_description
 * @property \Cake\I18n\DateTime|null $last_contacted_at
 * @property \Cake\I18n\DateTime|null $stage_changed_at
 * @property string|null $lost_reason
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Contractor|null      $contractor
 * @property \App\Model\Entity\User|null            $assigned_user
 * @property \App\Model\Entity\LeadActivity[]|null  $lead_activities
 */
class Lead extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];

    /**
     * Ile dni w aktualnym etapie (dla widoku Kanban).
     */
    public function getDaysInStage(): ?int
    {
        if (!$this->stage_changed_at) {
            return null;
        }
        $now = new \DateTimeImmutable();
        $then = new \DateTimeImmutable($this->stage_changed_at->format('c'));
        return (int)$now->diff($then)->days;
    }

    /**
     * Kolor stage do UI (Bootstrap/tailwind-friendly).
     */
    public function getStageColor(): string
    {
        return match ($this->stage) {
            'new'     => 'primary',
            'contact' => 'info',
            'inquiry' => 'warning',
            'offer'   => 'purple',
            'order'   => 'success',
            'lost'    => 'secondary',
            default   => 'secondary',
        };
    }
}

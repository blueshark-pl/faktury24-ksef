<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Table\SupportTicketsTable;

class SupportTicket extends Entity
{
    protected array $_accessible = [
        'user_id'         => true,
        'company_id'      => true,
        'title'           => true,
        'category'        => true,
        'description'     => true,
        'status'          => true,
        'type'            => true,
        'attachment'      => true,
        'attachment_name' => true,
        'admin_note'      => true,
        'support_ticket_replies' => true,
    ];

    protected function _getCategoryLabel(): string
    {
        return SupportTicketsTable::CATEGORIES[$this->category] ?? $this->category ?? '';
    }

    protected function _getStatusLabel(): string
    {
        return SupportTicketsTable::STATUSES[$this->status] ?? $this->status ?? '';
    }

    protected function _getTypeLabel(): string
    {
        if (empty($this->type)) return '';
        return SupportTicketsTable::TYPES[$this->type] ?? $this->type;
    }

    protected function _getStatusBadgeClass(): string
    {
        return match($this->status) {
            'nowe'       => 'primary',
            'w_toku'     => 'warning',
            'rozwiazane' => 'success',
            'zamkniete'  => 'secondary',
            default      => 'secondary',
        };
    }
}

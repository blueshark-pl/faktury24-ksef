<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class SupportTicketReply extends Entity
{
    protected array $_accessible = [
        'support_ticket_id' => true,
        'user_id'           => true,
        'author_name'       => true,
        'is_admin_reply'    => true,
        'message'           => true,
    ];
}

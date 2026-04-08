<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SupportTicketRepliesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('support_ticket_replies');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('SupportTickets', ['foreignKey' => 'support_ticket_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('message', 'Wiadomość nie może być pusta.');
        return $validator;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SupportTicketsTable extends Table
{
    public const CATEGORIES = [
        'problem'  => 'Problem / błąd',
        'idea'     => 'Pomysł / sugestia',
        'question' => 'Pytanie',
        'other'    => 'Inne',
    ];

    public const STATUSES = [
        'nowe'       => 'Nowe',
        'w_toku'     => 'W toku',
        'rozwiazane' => 'Rozwiązane',
        'zamkniete'  => 'Zamknięte',
    ];

    public const TYPES = [
        'bug'      => 'Bug',
        'proposal' => 'Propozycja',
        'question' => 'Pytanie',
        'other'    => 'Inne',
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('support_tickets');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('SupportTicketReplies', [
            'foreignKey' => 'support_ticket_id',
            'sort'       => ['SupportTicketReplies.created' => 'ASC'],
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType'   => 'LEFT',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('title', 'Podaj tytuł zgłoszenia.')
            ->maxLength('title', 255)
            ->notEmptyString('category', 'Wybierz kategorię.')
            ->inList('category', array_keys(self::CATEGORIES))
            ->notEmptyString('description', 'Opisz problem lub sugestię.')
            ->allowEmptyString('type')
            ->allowEmptyString('attachment')
            ->allowEmptyString('admin_note');

        return $validator;
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderNotesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_notes');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->integer('speed_order_id')->notEmptyString('speed_order_id')
            ->scalar('body')->notEmptyString('body', 'Tresc jest wymagana')
            ->scalar('note_type')
            ->inList('note_type', ['note', 'system', 'reminder', 'phone_call', 'email']);

        return $validator;
    }

    /**
     * Convenience: zapisz notatke systemowa (bez uzytkownika).
     */
    public function logSystem(string $companyId, int $speedOrderId, string $body, array $payload = []): void
    {
        try {
            $entity = $this->newEntity([
                'company_id'     => $companyId,
                'speed_order_id' => $speedOrderId,
                'note_type'      => 'system',
                'body'           => $body,
                'payload_json'   => !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $this->save($entity);
        } catch (\Throwable) { /* best-effort */ }
    }
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderStatusLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_status_logs');
        $this->setPrimaryKey('id');
        // brak Timestamp — 'created' ustawiane ręcznie / przez DB default
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('field');
        return $validator;
    }
}

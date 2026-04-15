<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderAttachmentLabelsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_attachment_labels');
        $this->setEntityClass('App\Model\Entity\SpeedOrderAttachmentLabel');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('name')->maxLength('name', 100);
        $validator->notEmptyString('slug')->maxLength('slug', 60);
        return $validator;
    }
}

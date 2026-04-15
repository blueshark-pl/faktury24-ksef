<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderAttachmentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_attachments');
        $this->setEntityClass('App\Model\Entity\SpeedOrderAttachment');
        $this->belongsTo('SpeedOrderAttachmentLabels', [
            'foreignKey' => 'label_id',
            'joinType'   => 'LEFT',
        ]);
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('file_path');
        $validator->notEmptyString('original_name');
        return $validator;
    }
}

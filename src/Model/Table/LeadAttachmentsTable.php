<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeadAttachmentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('lead_attachments');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Leads', ['foreignKey' => 'lead_id']);
        $this->belongsTo('UploadedBy', [
            'className' => 'Users', 'foreignKey' => 'uploaded_by_user_id', 'joinType' => 'LEFT',
        ]);
    }
}

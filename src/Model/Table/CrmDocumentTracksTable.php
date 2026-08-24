<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmDocumentTracksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_document_tracks');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Leads', ['foreignKey' => 'lead_id']);
    }
}

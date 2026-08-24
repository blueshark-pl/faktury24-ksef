<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CrmEmailMessagesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('crm_email_messages');
        $this->setPrimaryKey('id');
        $this->belongsTo('Leads', ['foreignKey' => 'lead_id']);
        $this->belongsTo('CrmEmailAccounts', ['foreignKey' => 'account_id']);
    }
}

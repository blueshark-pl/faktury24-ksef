<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class InvoiceEmailQueueTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('invoice_email_queue');
        $this->setEntityClass('App\Model\Entity\InvoiceEmailQueue');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->uuid('invoice_id')->notEmptyString('invoice_id');
        $validator->uuid('company_id')->notEmptyString('company_id');
        $validator->email('email')->notEmptyString('email');
        $validator->inList('status', ['pending', 'sending', 'sent', 'failed']);
        return $validator;
    }
}

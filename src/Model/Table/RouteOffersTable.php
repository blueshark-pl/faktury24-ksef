<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RouteOffersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('route_offers');
        $this->setDisplayField('subject');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id']);
        $this->belongsTo('Contractors', ['foreignKey' => 'contractor_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->uuid('route_plan_id')->notEmptyString('route_plan_id');
        $validator
            ->numeric('price')->greaterThan('price', 0);
        $validator
            ->scalar('currency')->maxLength('currency', 3)->notEmptyString('currency');
        $validator
            ->scalar('access_token')->maxLength('access_token', 64)->notEmptyString('access_token');
        $validator
            ->scalar('status')
            ->inList('status', ['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired']);
        $validator
            ->email('sent_to_email', false, 'Niepoprawny format email')
            ->allowEmptyString('sent_to_email');

        return $validator;
    }
}

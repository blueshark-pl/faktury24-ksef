<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PalletTypesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('pallet_types');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->scalar('code')->notEmptyString('code', 'Kod wymagany')
            ->maxLength('code', 30)
            ->scalar('name')->notEmptyString('name', 'Nazwa wymagana')
            ->maxLength('name', 150);

        return $validator;
    }

    /**
     * Palety dostepne dla firmy - globalne (company_id=NULL) + custom firmy.
     */
    public function findForCompany(string $companyId): \Cake\ORM\Query\SelectQuery
    {
        return $this->find()
            ->where(['is_active' => true])
            ->where(function ($exp) use ($companyId) {
                return $exp->or([
                    'company_id IS' => null,
                    'company_id' => $companyId,
                ]);
            })
            ->orderByAsc('sort_order')
            ->orderByAsc('code');
    }
}

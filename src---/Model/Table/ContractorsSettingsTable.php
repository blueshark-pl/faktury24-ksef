<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ContractorsSettingsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('contractors_settings');

        // Jeden rekord na parę (company_id, contractor_id)
        $this->setPrimaryKey(['company_id', 'contractor_id']);

        $this->belongsTo('Contractors', [
            'foreignKey' => 'contractor_id',
            'joinType'   => 'INNER',
        ]);

        // jeśli masz tabelę firm:
        // $this->belongsTo('Companies', [
        //     'foreignKey' => 'company_id',
        //     'joinType'   => 'INNER',
        // ]);
    }

    public function validationDefault(Validator $v): Validator
    {
        // UUID dla obu kluczy
        $v->uuid('company_id')->requirePresence('company_id', 'create')->notEmptyString('company_id');
        $v->uuid('contractor_id')->requirePresence('contractor_id', 'create')->notEmptyString('contractor_id');

        // booleany
        foreach (['share_invoices','notify_sms','notify_email'] as $f) {
            $v->boolean($f);
            $v->allowEmptyString($f);
        }

        // enum (dziedziczenie z globalnych ustawień)
        $v->inList('attach_invoice_pdf_mode', ['inherit','yes','no'], 'Nieprawidłowa wartość.');
        $v->allowEmptyString('attach_invoice_pdf_mode');

        return $v;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // kontrahent musi istnieć
        $rules->add($rules->existsIn('contractor_id', 'Contractors'), 'existsContractor', [
            'errorField' => 'contractor_id',
            'message'    => 'Kontrahent nie istnieje.',
        ]);

        // jeśli masz Companies:
        // $rules->add($rules->existsIn('company_id', 'Companies'), 'existsCompany', [
        //     'errorField' => 'company_id',
        //     'message'    => 'Firma nie istnieje.',
        // ]);

        // tylko jeden rekord na (company_id, contractor_id)
        $rules->add($rules->isUnique(['company_id', 'contractor_id'], 'Ustawienia dla tego kontrahenta już istnieją.'));

        return $rules;
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\View\JsonView;

class ContractorsSettingsController extends AppController
{
    // Akcje JSON – Cake 5: JsonView + serialize
    public function view(string $contractorId): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','settings','message']);

        $companyId   = $this->request->getAttribute('identity')?->get('company_id');
        $Contractors = $this->fetchTable('Contractors');

        if (!$Contractors->exists(['id' => $contractorId, 'company_id' => $companyId])) {
            $this->set(['success' => false, 'settings' => null, 'message' => 'Not found']);
            return;
        }

        $ContractorsSettings = $this->fetchTable('ContractorsSettings');
        $settings = $ContractorsSettings->find()
            ->where(['company_id' => $companyId, 'contractor_id' => $contractorId]) // bez (int)!
            ->first();

        $this->set(['success' => true, 'settings' => $settings, 'message' => null]);
    }

    public function save(string $contractorId): void
    {
        $this->request->allowMethod(['post','put']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','settings','errors','message']);

        $companyId   = $this->request->getAttribute('identity')?->get('company_id');
        $Contractors = $this->fetchTable('Contractors');

        if (!$Contractors->exists(['id' => $contractorId, 'company_id' => $companyId])) {
            $this->set(['success' => false, 'settings' => null, 'errors' => null, 'message' => 'Not found']);
            return;
        }

        $ContractorsSettings = $this->fetchTable('ContractorsSettings');

        $entity = $ContractorsSettings->find()
            ->where(['company_id' => $companyId, 'contractor_id' => $contractorId])
            ->first() ?? $ContractorsSettings->newEmptyEntity();

        $data = $this->request->getData() ?: $this->request->getParsedBody();

        $patch = [
            'company_id'              => $companyId,
            'contractor_id'           => $contractorId, // bez rzutowania
            'share_invoices'          => !empty($data['share_invoices']),
            'notify_sms'              => !empty($data['notify_sms']),
            'notify_email'            => array_key_exists('notify_email', $data) ? (bool)$data['notify_email'] : true,
            'attach_invoice_pdf_mode' => (string)($data['attach_invoice_pdf_mode'] ?? 'inherit'),
        ];

        $entity  = $ContractorsSettings->patchEntity($entity, $patch);
        $success = (bool)$ContractorsSettings->save($entity);

        $this->set([
            'success'  => $success,
            'settings' => $entity,
            'errors'   => $success ? null : $entity->getErrors(),
            'message'  => $success ? null : 'Validation or save error',
        ]);
    }
}

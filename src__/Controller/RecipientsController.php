<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\View\JsonView;

/**
 * Recipients Controller
 * JSON endpoints used by Contractors UI modals
 */
class RecipientsController extends AppController
{

    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /**
     * GET /recipients/by-contractor/:contractorId
     * Returns recipients for a contractor (current company only).
     */
    public function byContractor($contractorId)
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','recipients','message']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $Contractors = $this->fetchTable('Contractors');
        $exists = $Contractors->exists(['id' => $contractorId, 'company_id' => $companyId]);
        if (!$exists) {
            $this->set(['success'=>false, 'message'=>'Not found', 'recipients'=>[]]);
            return;
        }

        $Recipients = $this->fetchTable('Recipients');
        $list = $Recipients->find()
            ->select(['id','contractor_id','name','email','phone','nip','city','street','postal_code','is_active','created'])
            ->where(['contractor_id' => $contractorId])
            ->order(['created' => 'DESC'])
            ->all();

        $this->set(['success'=>true, 'recipients'=>$list, 'message'=>null]);
    }

    /**
     * GET /recipients/view/:id
     */
    public function view($id)
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','recipient','message']);

        $Recipients = $this->fetchTable('Recipients');
        $r = $Recipients->find()
            ->select(['id','contractor_id','name','email','phone','nip','city','street','postal_code','is_active'])
            ->where(['id' => $id])
            ->first();

        if (!$r) {
            $this->set(['success'=>false, 'recipient'=>null, 'message'=>'Not found']);
            return;
        }

        $this->set(['success'=>true, 'recipient'=>$r, 'message'=>null]);
    }

    /**
     * POST /recipients/add
     */
    public function add()
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','recipient','errors','message']);

        $data = $this->request->getData();
        // enforce company scope
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>null, 'message'=>'Brak kontekstu firmy']);
            return;
        }
        $data['company_id'] = $companyId;
        // verify contractor belongs to current company
        $contractorId = $data['contractor_id'] ?? null;
        if (!$contractorId) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>['contractor_id'=>['required'=>'Wymagane']], 'message'=>'Brak kontrahenta']);
            return;
        }
        $Contractors = $this->fetchTable('Contractors');
        $exists = $Contractors->exists(['id' => $contractorId, 'company_id' => $companyId]);
        if (!$exists) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>['contractor_id'=>['invalid'=>'Kontrahent nie istnieje w tej firmie']], 'message'=>'Nieprawidłowy kontrahent']);
            return;
        }
        // normalize nip to digits-only
        if (isset($data['nip'])) {
            $data['nip'] = preg_replace('/\D+/', '', (string)$data['nip']);
        }
        $Recipients = $this->fetchTable('Recipients');
        $recipient = $Recipients->newEmptyEntity();
        $recipient = $Recipients->patchEntity($recipient, $data);
        if ($recipient->getErrors()) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>$recipient->getErrors(), 'message'=>'Walidacja nie powiodła się']);
            return;
        }
        if ($Recipients->save($recipient)) {
            $this->set(['success'=>true, 'recipient'=>$recipient, 'errors'=>null, 'message'=>'Zapisano odbiorcę']);
            return;
        }
        $this->set(['success'=>false, 'recipient'=>null, 'errors'=>$recipient->getErrors(), 'message'=>'Nie udało się zapisać odbiorcy']);
    }

    /**
     * POST /recipients/edit/:id
     */
    public function edit($id)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','recipient','errors','message']);

        $Recipients = $this->fetchTable('Recipients');
        $recipient = $Recipients->get($id);
        // ensure editing within current company
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId || (int)$recipient->company_id !== (int)$companyId) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>null, 'message'=>'Brak uprawnień do edycji']);
            return;
        }
        $data = $this->request->getData();
        // never allow changing company_id via payload
        $data['company_id'] = $recipient->company_id;
        if (isset($data['nip'])) {
            $data['nip'] = preg_replace('/\D+/', '', (string)$data['nip']);
        }
        $recipient = $Recipients->patchEntity($recipient, $data);
        if ($recipient->getErrors()) {
            $this->set(['success'=>false, 'recipient'=>null, 'errors'=>$recipient->getErrors(), 'message'=>'Walidacja nie powiodła się']);
            return;
        }
        if ($Recipients->save($recipient)) {
            $this->set(['success'=>true, 'recipient'=>$recipient, 'errors'=>null, 'message'=>'Zapisano odbiorcę']);
            return;
        }
        $this->set(['success'=>false, 'recipient'=>null, 'errors'=>$recipient->getErrors(), 'message'=>'Nie udało się zapisać odbiorcy']);
    }

    /**
     * POST /recipients/delete/:id
     */
    public function delete($id)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['success','message']);

        $Recipients = $this->fetchTable('Recipients');
        $r = $Recipients->get($id);
        if ($Recipients->delete($r)) {
            $this->set(['success'=>true, 'message'=>null]);
            return;
        }
        $this->set(['success'=>false, 'message'=>'Nie udało się usunąć odbiorcy']);
    }
}


<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Globalne wyszukiwanie — endpoint AJAX dla input'u w nagłówku.
 *
 * Zwraca JSON ze zgrupowanymi wynikami:
 *  - invoices    — po fullnumber + invoice_contractors.name (scope: company_id)
 *  - contractors — po name/nip/city (scope: company_id)
 *  - orders      — po symbol/buyer_name/buyer_nip/route_description
 *
 * Limit: 5 wyników na grupę.
 */
class SearchController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $event->setResult($this->redirect('/users/login'));
            return;
        }
        // Portal klienta nie używa globalnego search'a
        $role = strtolower((string)($identity->get('role') ?? ''));
        if ($role === 'client') {
            $event->setResult($this->response->withStatus(403));
        }
    }

    public function query(): Response
    {
        $this->disableAutoRender();
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity->get('company_id') ?? '');
        $q = trim((string)$this->request->getQuery('q', ''));

        $result = ['invoices' => [], 'contractors' => [], 'orders' => [], 'q' => $q];
        if (mb_strlen($q) < 2) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result));
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        // ── Faktury ─────────────────────────────────────────────────────────
        $Invoices = $this->fetchTable('Invoices');
        $invQ = $Invoices->find()
            ->select([
                'Invoices.id', 'Invoices.fullnumber', 'Invoices.type',
                'Invoices.workflow_status', 'Invoices.paymentstate',
                'Invoices.brutto', 'Invoices.currency', 'Invoices.created',
                'InvoiceContractors.name', 'InvoiceContractors.nip',
            ])
            ->contain(['InvoiceContractors' => function ($q) {
                return $q->select(['id', 'invoice_id', 'name', 'nip']);
            }])
            ->where([
                'OR' => [
                    'Invoices.fullnumber LIKE'    => $like,
                    'InvoiceContractors.name LIKE' => $like,
                    'InvoiceContractors.nip LIKE'  => $like,
                ],
            ])
            ->orderByDesc('Invoices.created')
            ->limit(5);
        if ($companyId !== '') {
            $invQ->where(['Invoices.company_id' => $companyId]);
        }
        foreach ($invQ->all() as $i) {
            $result['invoices'][] = [
                'id'         => (string)$i->id,
                'fullnumber' => (string)$i->fullnumber,
                'type'       => (string)$i->type,
                'workflow'   => (string)$i->workflow_status,
                'payment'    => (string)$i->paymentstate,
                'brutto'     => $i->brutto !== null ? (float)$i->brutto : null,
                'currency'   => (string)$i->currency,
                'contractor' => (string)($i->invoice_contractor->name ?? ''),
                'nip'        => (string)($i->invoice_contractor->nip ?? ''),
            ];
        }

        // ── Kontrahenci ─────────────────────────────────────────────────────
        $Contractors = $this->fetchTable('Contractors');
        $ctrQ = $Contractors->find()
            ->select(['id', 'name', 'nip', 'city', 'street', 'email'])
            ->where([
                'OR' => [
                    'Contractors.name LIKE' => $like,
                    'Contractors.nip LIKE'  => $like,
                    'Contractors.city LIKE' => $like,
                ],
            ])
            ->orderByAsc('Contractors.name')
            ->limit(5);
        if ($companyId !== '') {
            $ctrQ->where(['Contractors.company_id' => $companyId]);
        }
        foreach ($ctrQ->all() as $c) {
            $result['contractors'][] = [
                'id'    => (string)$c->id,
                'name'  => (string)$c->name,
                'nip'   => (string)$c->nip,
                'city'  => (string)$c->city,
                'street' => (string)$c->street,
            ];
        }

        // ── Zlecenia (Speed) ────────────────────────────────────────────────
        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $ordQ = $SpeedOrders->find()
            ->select([
                'id', 'symbol', 'buyer_name', 'buyer_nip',
                'place_from_name', 'place_to_name',
                'date_doc', 'netto', 'currency', 'nordlogis_status',
            ])
            ->where([
                'OR' => [
                    'SpeedOrders.symbol LIKE'            => $like,
                    'SpeedOrders.buyer_name LIKE'        => $like,
                    'SpeedOrders.buyer_nip LIKE'         => $like,
                    'SpeedOrders.route_description LIKE' => $like,
                ],
            ])
            ->orderByDesc('SpeedOrders.date_doc')
            ->limit(5);
        foreach ($ordQ->all() as $o) {
            $result['orders'][] = [
                'id'      => (string)$o->id,
                'symbol'  => (string)$o->symbol,
                'buyer'   => (string)$o->buyer_name,
                'nip'     => (string)$o->buyer_nip,
                'from'    => (string)$o->place_from_name,
                'to'      => (string)$o->place_to_name,
                'netto'   => $o->netto !== null ? (float)$o->netto : null,
                'currency' => (string)$o->currency,
                'status'  => (int)$o->nordlogis_status,
            ];
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode($result));
    }
}

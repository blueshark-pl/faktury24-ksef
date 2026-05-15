<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Expression\QueryExpression;
use Cake\View\JsonView;

/**
 * SearchController
 *
 * Globalne wyszukiwanie w navbarze: faktury, kontrahenci, produkty/usługi.
 * Endpoint: GET /search?q=...
 *
 * Wszystkie wyniki ograniczone do firmy aktualnie zalogowanej tożsamości
 * (`identity->company_id`). Bez company_id zwracamy puste listy.
 */
class SearchController extends AppController
{
    public function index()
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName(JsonView::class);
        $this->viewBuilder()->setOption('serialize', ['invoices', 'contractors', 'products']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $q = trim((string)$this->request->getQuery('q', ''));
        if (mb_strlen($q) < 2 || !$companyId) {
            $this->set([
                'invoices'    => [],
                'contractors' => [],
                'products'    => [],
            ]);
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $limitPerGroup = 5;

        // ── Faktury ─────────────────────────────────────────────────────────
        $invoices = [];
        try {
            $Invoices = $this->fetchTable('Invoices');
            $invQuery = $Invoices->find()
                ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.date', 'Invoices.total', 'Invoices.currency'])
                ->contain(['InvoiceContractors' => fn($q) => $q->select(['invoice_id', 'name', 'nip'])])
                ->leftJoinWith('InvoiceContractors')
                ->where(['Invoices.company_id' => $companyId])
                ->andWhere(function (QueryExpression $exp) use ($like) {
                    return $exp->or([
                        'Invoices.fullnumber LIKE' => $like,
                        'InvoiceContractors.name LIKE' => $like,
                        'InvoiceContractors.nip LIKE' => $like,
                    ]);
                })
                ->order(['Invoices.date' => 'DESC'])
                ->limit($limitPerGroup);

            foreach ($invQuery as $inv) {
                $contractor = $inv->invoice_contractor?->name ?? '';
                $date = is_object($inv->date) && method_exists($inv->date, 'format')
                    ? $inv->date->format('Y-m-d')
                    : (string)$inv->date;
                $invoices[] = [
                    'id'         => (string)$inv->id,
                    'fullnumber' => (string)($inv->fullnumber ?? ''),
                    'contractor' => $contractor,
                    'date'       => $date,
                    'total'      => (float)($inv->total ?? 0),
                    'currency'   => (string)($inv->currency ?? 'PLN'),
                    'url'        => '/invoices/view/' . $inv->id,
                ];
            }
        } catch (\Throwable) {
            $invoices = [];
        }

        // ── Kontrahenci ─────────────────────────────────────────────────────
        $contractors = [];
        try {
            $Contractors = $this->fetchTable('Contractors');
            $cQuery = $Contractors->find()
                ->select(['id', 'name', 'altname', 'nip', 'city', 'country'])
                ->where(['company_id' => $companyId])
                ->andWhere(function (QueryExpression $exp) use ($like) {
                    return $exp->or([
                        'name LIKE'    => $like,
                        'altname LIKE' => $like,
                        'nip LIKE'     => $like,
                        'city LIKE'    => $like,
                    ]);
                })
                ->order(['name' => 'ASC'])
                ->limit($limitPerGroup);

            foreach ($cQuery as $c) {
                $contractors[] = [
                    'id'      => (string)$c->id,
                    'name'    => (string)$c->name,
                    'nip'     => (string)($c->nip ?? ''),
                    'city'    => (string)($c->city ?? ''),
                    'country' => (string)($c->country ?? ''),
                    'url'     => '/contractors?q=' . rawurlencode((string)$c->name),
                ];
            }
        } catch (\Throwable) {
            $contractors = [];
        }

        // ── Produkty/Usługi ─────────────────────────────────────────────────
        $products = [];
        try {
            $Products = $this->fetchTable('Products');
            $pQuery = $Products->find()
                ->select(['id', 'name', 'code', 'pkwiu', 'price', 'currency'])
                ->where([
                    'company_id' => $companyId,
                    'deleted'    => 0,
                ])
                ->andWhere(function (QueryExpression $exp) use ($like) {
                    return $exp->or([
                        'name LIKE'    => $like,
                        'code LIKE'    => $like,
                        'pkwiu LIKE'   => $like,
                        'barcode LIKE' => $like,
                    ]);
                })
                ->order(['name' => 'ASC'])
                ->limit($limitPerGroup);

            foreach ($pQuery as $p) {
                $products[] = [
                    'id'       => (string)$p->id,
                    'name'     => (string)$p->name,
                    'code'     => (string)($p->code ?? ''),
                    'price'    => (float)($p->price ?? 0),
                    'currency' => (string)($p->currency ?? 'PLN'),
                    'url'      => '/products?q=' . rawurlencode((string)$p->name),
                ];
            }
        } catch (\Throwable) {
            $products = [];
        }

        $this->set(compact('invoices', 'contractors', 'products'));
    }
}

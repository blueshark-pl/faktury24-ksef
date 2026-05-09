<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\I18n;

/**
 * Portal klienta — dostęp tylko dla roli `client`.
 *
 * Klient widzi w portalu zlecenia transportowe (speed_orders), które są
 * powiązane z jego NIP-em (client_profiles.nip = speed_orders.buyer_nip).
 * Może pobrać załączniki CMR oraz fakturę sprzedażową w PDF.
 */
class ClientPortalController extends AppController
{
    private ?\App\Model\Entity\ClientProfile $profile = null;

    public function initialize(): void
    {
        parent::initialize();

        $identity = $this->request->getAttribute('identity');
        $userId   = $identity?->get('id');
        if ($userId) {
            $this->profile = $this->fetchTable('ClientProfiles')
                ->find()
                ->where(['user_id' => $userId])
                ->first();
        }
    }

    /**
     * Sprawdza czy zalogowany user ma profil klienta.
     * Zwraca Response z redirectem jeśli nie — wtedy trzeba zwrócić go z akcji.
     */
    private function ensureProfile(): ?Response
    {
        if (!$this->profile) {
            $this->Flash->error(__('Twoje konto nie ma jeszcze przypisanego profilu klienta. Skontaktuj się z administratorem.'));
            return $this->redirect('/');
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Lista zleceń klienta
    // -------------------------------------------------------------------------
    public function index(): ?Response
    {
        if ($r = $this->ensureProfile()) { return $r; }

        $page     = max(1, (int)$this->request->getQuery('page', 1));
        $limit    = 50;
        $q        = trim((string)$this->request->getQuery('q', ''));
        $status   = (string)$this->request->getQuery('status', ''); // '', 'active', 'closed'
        $invState = (string)$this->request->getQuery('inv', '');     // '', 'with', 'without', 'paid', 'unpaid'
        $cmrState = (string)$this->request->getQuery('cmr', '');     // '', 'with', 'without'
        $currency = strtoupper(trim((string)$this->request->getQuery('currency', '')));
        $dateFrom = (string)$this->request->getQuery('date_from', '');
        $dateTo   = (string)$this->request->getQuery('date_to', '');
        $sort     = (string)$this->request->getQuery('sort', 'date_desc'); // date_desc|date_asc|amount_desc|amount_asc

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $query = $SpeedOrders->find()
            ->where(['SpeedOrders.buyer_nip' => $this->profile->nip]);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'            => $like,
                'SpeedOrders.title1 LIKE'            => $like,
                'SpeedOrders.title2 LIKE'            => $like,
                'SpeedOrders.route_description LIKE' => $like,
                'SpeedOrders.place_from_name LIKE'   => $like,
                'SpeedOrders.place_to_name LIKE'     => $like,
            ]]);
        }
        if ($status === 'active')  { $query->where(['SpeedOrders.status' => 1]); }
        if ($status === 'closed')  { $query->where(['SpeedOrders.status' => 0]); }
        if ($currency !== '')      { $query->where(['SpeedOrders.currency' => $currency]); }
        if ($dateFrom !== '')      { $query->where(['SpeedOrders.date_doc >=' => $dateFrom]); }
        if ($dateTo   !== '')      { $query->where(['SpeedOrders.date_doc <=' => $dateTo]); }
        if ($invState === 'with')    { $query->where(['SpeedOrders.invoice_id IS NOT' => null]); }
        if ($invState === 'without') { $query->where(['SpeedOrders.invoice_id IS' => null]); }

        // Stan zapłaty wymaga JOIN na invoices
        if (in_array($invState, ['paid', 'unpaid'], true)) {
            $query->innerJoinWith('Invoices', function ($q) use ($invState) {
                if ($invState === 'paid')   { $q->where(['Invoices.paymentstate' => 'paid']); }
                if ($invState === 'unpaid') { $q->where(['Invoices.paymentstate IN' => ['unpaid', 'partial']]); }
                return $q;
            });
        }

        // Filtr CMR — wymaga subquery
        if ($cmrState === 'with') {
            $sub = $this->fetchTable('SpeedOrderAttachments')->find()
                ->select(['speed_order_id'])
                ->distinct(['speed_order_id']);
            $query->where(['SpeedOrders.id IN' => $sub]);
        } elseif ($cmrState === 'without') {
            $sub = $this->fetchTable('SpeedOrderAttachments')->find()
                ->select(['speed_order_id'])
                ->distinct(['speed_order_id']);
            $query->where(['SpeedOrders.id NOT IN' => $sub]);
        }

        // Sortowanie
        match ($sort) {
            'date_asc'      => $query->orderByAsc('SpeedOrders.date_doc'),
            'delivery_desc' => $query->orderByDesc('SpeedOrders.date_delivery'),
            'delivery_asc'  => $query->orderByAsc('SpeedOrders.date_delivery'),
            default         => $query->orderByDesc('SpeedOrders.date_doc'),
        };

        $total  = (clone $query)->count();
        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min($page, $pages);
        $orders = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $stats = ['count' => $total];

        // Lista walut do selecta filtra (dystyntne dla tego klienta)
        $currencyOptions = [];
        try {
            $rows = $SpeedOrders->find()
                ->select(['currency'])
                ->distinct(['currency'])
                ->where(['buyer_nip' => $this->profile->nip])
                ->all();
            foreach ($rows as $r) {
                if ($r->currency) { $currencyOptions[] = (string)$r->currency; }
            }
            sort($currencyOptions);
        } catch (\Throwable) { $currencyOptions = []; }

        // Mapa załączników CMR per zlecenie
        $cmrMap = [];
        $orderIds = array_map(fn($o) => $o->id, $orders->toArray());
        if (!empty($orderIds)) {
            try {
                $atts = $this->fetchTable('SpeedOrderAttachments')->find()
                    ->select(['id', 'speed_order_id', 'mime_type', 'original_name'])
                    ->where(['speed_order_id IN' => $orderIds])
                    ->orderByAsc('created')
                    ->all();
                foreach ($atts as $att) {
                    $cmrMap[$att->speed_order_id][] = [
                        'id'   => $att->id,
                        'mime' => $att->mime_type,
                        'name' => $att->original_name,
                    ];
                }
            } catch (\Throwable) { /* tabela może nie istnieć */ }
        }

        // Mapa faktur sprzedażowych — jakie zlecenia mają fakturę
        $invoiceIds = array_filter(array_map(fn($o) => $o->invoice_id, $orders->toArray()));
        $invoiceMap = [];
        if (!empty($invoiceIds)) {
            $invs = $this->fetchTable('Invoices')->find()
                ->select(['id', 'fullnumber', 'date', 'total', 'currency', 'paymentstate'])
                ->where(['id IN' => $invoiceIds])
                ->all();
            foreach ($invs as $inv) {
                $invoiceMap[(string)$inv->id] = $inv;
            }
        }

        $this->set(compact(
            'orders', 'cmrMap', 'invoiceMap', 'total', 'page', 'pages', 'limit',
            'q', 'status', 'invState', 'cmrState', 'currency', 'dateFrom', 'dateTo',
            'sort', 'stats', 'currencyOptions'
        ));
        $this->set('clientProfile', $this->profile);
        return null;
    }

    // -------------------------------------------------------------------------
    // Szczegóły zlecenia
    // -------------------------------------------------------------------------
    public function view(int $id): ?Response
    {
        if ($r = $this->ensureProfile()) { return $r; }

        $order = $this->fetchTable('SpeedOrders')->find()
            ->where([
                'SpeedOrders.id'        => $id,
                'SpeedOrders.buyer_nip' => $this->profile->nip,
            ])
            ->first();
        if (!$order) {
            $this->Flash->error(__('Zlecenie nie istnieje lub nie należy do Twojej firmy.'));
            return $this->redirect(['action' => 'index']);
        }

        // Faktura sprzedażowa (jeśli została wystawiona)
        $invoice = null;
        if ($order->invoice_id) {
            $invoice = $this->fetchTable('Invoices')->find()
                ->select(['id', 'fullnumber', 'date', 'total', 'currency', 'paymentstate', 'paymentdate'])
                ->where(['id' => $order->invoice_id])
                ->first();
        }

        // Załączniki CMR
        $attachments = [];
        try {
            $attachments = $this->fetchTable('SpeedOrderAttachments')->find()
                ->contain(['SpeedOrderAttachmentLabels'])
                ->where(['SpeedOrderAttachments.speed_order_id' => $id])
                ->orderByAsc('SpeedOrderAttachments.created')
                ->all()
                ->toArray();
        } catch (\Throwable) { /* ignore */ }

        $this->set(compact('order', 'invoice', 'attachments'));
        $this->set('clientProfile', $this->profile);
        return null;
    }

    // -------------------------------------------------------------------------
    // Pobranie pliku załącznika CMR
    // -------------------------------------------------------------------------
    public function downloadAttachment(int $attId): Response
    {
        if ($r = $this->ensureProfile()) { return $r; }

        $att = $this->fetchTable('SpeedOrderAttachments')->find()
            ->contain(['SpeedOrders'])
            ->where(['SpeedOrderAttachments.id' => $attId])
            ->first();

        if (!$att || !$att->speed_order || (string)$att->speed_order->buyer_nip !== (string)$this->profile->nip) {
            throw new NotFoundException();
        }

        $fullPath = WWW_ROOT . ltrim((string)$att->file_path, '/');
        if (!is_file($fullPath)) {
            throw new NotFoundException();
        }

        return $this->response
            ->withType($att->mime_type ?: 'application/octet-stream')
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', (string)($att->original_name ?: 'cmr')) . '"'
            )
            ->withFile($fullPath);
    }

    // -------------------------------------------------------------------------
    // Pobranie faktury sprzedażowej w PDF
    // — przekierowanie do /invoices/print/{id}?download=1.
    // Weryfikacja dostępu odbywa się tu (faktura musi wisieć przy zleceniu klienta);
    // permissions.php pozwala roli `client` na akcję Invoices/print z closure
    // sprawdzającym ten sam warunek (defense in depth).
    // -------------------------------------------------------------------------
    public function downloadInvoice(string $invoiceId): Response
    {
        if ($r = $this->ensureProfile()) { return $r; }

        $exists = $this->fetchTable('SpeedOrders')->find()
            ->where([
                'invoice_id' => $invoiceId,
                'buyer_nip'  => $this->profile->nip,
            ])
            ->count();
        if (!$exists) {
            throw new NotFoundException();
        }

        $lang = $this->request->getSession()->read('Config.locale') === 'en' ? 'en' : 'pl';
        return $this->redirect('/invoices/print/' . $invoiceId . '?download=1&lang=' . $lang);
    }

    // -------------------------------------------------------------------------
    // Przełączenie języka portalu (PL / EN)
    // -------------------------------------------------------------------------
    public function setLocale(string $lang): Response
    {
        $lang = in_array($lang, ['pl', 'en'], true) ? $lang : 'pl';
        $this->request->getSession()->write('Config.locale', $lang);
        I18n::setLocale($lang);

        if ($this->profile) {
            $this->profile->locale = $lang;
            $this->fetchTable('ClientProfiles')->save($this->profile);
        }

        $referer = $this->request->referer(true);
        return $this->redirect($referer ?: ['action' => 'index']);
    }
}

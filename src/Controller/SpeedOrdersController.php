<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\Date;

/**
 * Zlecenia z systemu Speed ERP.
 *
 * Akcje:
 *  - index  : lista zleceń (z lokalnej DB + opcja synchronizacji)
 *  - view   : szczegóły pojedynczego zlecenia
 *  - sync   : AJAX POST — pobiera/aktualizuje zlecenia z Speed API (paginowane)
 */
class SpeedOrdersController extends AppController
{
    // -------------------------------------------------------------------------
    // Lista zleceń
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $search       = trim((string)$this->request->getQuery('q', ''));
        $status       = $this->request->getQuery('status', '');
        $currency     = strtoupper(trim((string)$this->request->getQuery('currency', '')));
        $contract     = trim((string)$this->request->getQuery('contract', ''));
        $source       = trim((string)$this->request->getQuery('source', ''));
        $amountMin    = $this->request->getQuery('amount_min', '');
        $amountMax    = $this->request->getQuery('amount_max', '');
        $deliveryFrom = $this->request->getQuery('delivery_from', '');
        $deliveryTo   = $this->request->getQuery('delivery_to', '');
        $page         = max(1, (int)$this->request->getQuery('page', 1));
        $limit        = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200, 500], true)) {
            $limit = 50;
        }

        $SpeedOrders = $this->fetchTable('SpeedOrders');

        // Sortowanie kolumn — whitelist + walidacja kierunku
        $sortable = [
            'symbol'           => 'SpeedOrders.symbol',
            'date_doc'         => 'SpeedOrders.date_doc',
            'buyer_name'       => 'SpeedOrders.buyer_name',
            'date_deadline'    => 'SpeedOrders.date_deadline',
            'date_delivery'    => 'SpeedOrders.date_delivery',
            'carrier'          => 'SpeedOrders.carrier',
            'driver'           => 'SpeedOrders.driver',
            'contract'         => 'SpeedOrders.contract',
            'netto'            => 'SpeedOrders.netto',
            'currency'         => 'SpeedOrders.currency',
            'nordlogis_status' => 'SpeedOrders.nordlogis_status',
        ];
        $sortKey  = (string)$this->request->getQuery('sort', 'date_doc');
        $sortDir  = strtolower((string)$this->request->getQuery('direction', 'desc'));
        if (!isset($sortable[$sortKey]))         $sortKey = 'date_doc';
        if (!in_array($sortDir, ['asc','desc'])) $sortDir = 'desc';
        $sortCol = $sortable[$sortKey];

        $query = $SpeedOrders->find();
        if ($sortDir === 'asc') {
            $query->orderByAsc($sortCol);
        } else {
            $query->orderByDesc($sortCol);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'      => $like,
                'SpeedOrders.buyer_name LIKE'  => $like,
                'SpeedOrders.buyer_nip LIKE'   => $like,
                'SpeedOrders.title1 LIKE'      => $like,
                'SpeedOrders.title2 LIKE'      => $like,
                'SpeedOrders.route_description LIKE' => $like,
            ]]);
        }

        if ($status !== '') {
            if (str_starts_with($status, 'nl_')) {
                $query->where(['SpeedOrders.nordlogis_status' => (int)substr($status, 3)]);
            } elseif (str_starts_with($status, 'sp_')) {
                $query->where(['SpeedOrders.status' => (int)substr($status, 3)]);
            } elseif ($status === 'brak_pod') {
                $query->where(['SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
            } elseif ($status === 'brak_fk') {
                $query->where(['SpeedOrders.fk_at IS' => null]);
            } elseif ($status === 'niezafakt') {
                $query->where(['SpeedOrders.invoice_id IS' => null]);
            } elseif ($status === 'przetermin') {
                $query->where([
                    'SpeedOrders.date_delivery <'  => date('Y-m-d'),
                    'SpeedOrders.pod_at IS'        => null,
                    'SpeedOrders.invoice_id IS'    => null,
                ]);
            } else {
                $query->where(['SpeedOrders.status' => (int)$status]);
            }
        }

        if ($currency !== '') {
            $query->where(['SpeedOrders.currency' => $currency]);
        }

        if ($contract !== '') {
            $query->where(['SpeedOrders.contract' => $contract]);
        }

        if ($source !== '' && in_array($source, ['speed', 'manual'], true)) {
            $query->where(['SpeedOrders.source' => $source]);
        }

        if ($amountMin !== '') {
            $query->where(['SpeedOrders.netto >=' => (float)$amountMin]);
        }

        if ($amountMax !== '') {
            $query->where(['SpeedOrders.netto <=' => (float)$amountMax]);
        }

        if ($deliveryFrom !== '') {
            $query->where(['SpeedOrders.date_delivery >=' => $deliveryFrom]);
        }

        if ($deliveryTo !== '') {
            $query->where(['SpeedOrders.date_delivery <=' => $deliveryTo]);
        }

        $total  = (clone $query)->count();
        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min($page, $pages);
        $orders = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Mapa załączników CMR: speed_order_id → [[file_path, mime_type, label, original_name], …]
        // + flagi POL/POD per zlecenie (true gdy istnieje attachment z odpowiednią etykietą)
        $cmrMap = [];
        $hasPolPodMap = []; // speed_order_id → ['pol' => bool, 'pod' => bool]
        try {
            $orderIds = array_map(fn($o) => $o->id, $orders->toArray());
            if (!empty($orderIds)) {
                $attachRows = $this->fetchTable('SpeedOrderAttachments')
                    ->find()
                    ->select(['SpeedOrderAttachments.id', 'SpeedOrderAttachments.speed_order_id', 'SpeedOrderAttachments.file_path', 'SpeedOrderAttachments.mime_type', 'SpeedOrderAttachments.original_name', 'SpeedOrderAttachmentLabels.name', 'SpeedOrderAttachmentLabels.slug'])
                    ->contain(['SpeedOrderAttachmentLabels'])
                    ->where(['SpeedOrderAttachments.speed_order_id IN' => $orderIds])
                    ->orderByAsc('SpeedOrderAttachments.created')
                    ->all();
                foreach ($attachRows as $att) {
                    $cmrMap[$att->speed_order_id][] = [
                        'id'    => $att->id,
                        'path'  => $att->file_path,
                        'mime'  => $att->mime_type,
                        'name'  => $att->original_name,
                        'label' => $att->speed_order_attachment_label->name ?? '',
                    ];
                    $slug = (string)($att->speed_order_attachment_label->slug ?? '');
                    if (str_starts_with($slug, 'pol_')) {
                        $hasPolPodMap[$att->speed_order_id]['pol'] = true;
                    } elseif (str_starts_with($slug, 'pod_')) {
                        $hasPolPodMap[$att->speed_order_id]['pod'] = true;
                    }
                }
            }
        } catch (\Throwable) {
            $cmrMap = [];
            $hasPolPodMap = [];
        }

        // Mapa faktur (M:N): speed_order_id → [Invoice, Invoice, ...]
        // Pobierane jednym zapytaniem przez pivot speed_order_invoices,
        // zachowuje kolejność wstawienia (id ASC).
        $invoicesMap = [];
        try {
            if (!empty($orderIds)) {
                $rows = $this->fetchTable('SpeedOrderInvoices')->find()
                    ->select(['SpeedOrderInvoices.speed_order_id', 'Invoices.id', 'Invoices.fullnumber', 'Invoices.date', 'Invoices.paymentstate', 'Invoices.total'])
                    ->contain(['Invoices'])
                    ->where(['SpeedOrderInvoices.speed_order_id IN' => $orderIds])
                    ->orderByAsc('SpeedOrderInvoices.id')
                    ->all();
                foreach ($rows as $r) {
                    if ($r->invoice) {
                        $invoicesMap[$r->speed_order_id][] = $r->invoice;
                    }
                }
            }
        } catch (\Throwable) {
            $invoicesMap = [];
        }

        // Lista unikalnych kontraktów do dropdown filtra
        $contractsList = $SpeedOrders->find()
            ->select(['contract'])
            ->where(['contract IS NOT' => null, 'contract !=' => ''])
            ->groupBy('contract')
            ->orderBy(['contract' => 'ASC'])
            ->disableHydration()
            ->all()
            ->extract('contract')
            ->toArray();

        $this->set(compact('orders', 'total', 'page', 'pages', 'limit', 'search', 'status', 'currency', 'contract', 'contractsList', 'source', 'amountMin', 'amountMax', 'deliveryFrom', 'deliveryTo', 'cmrMap', 'hasPolPodMap', 'invoicesMap', 'sortKey', 'sortDir'));
    }

    // -------------------------------------------------------------------------
    // Eksport listy zleceń do CSV
    // -------------------------------------------------------------------------
    public function exportCsv(): void
    {
        $this->request->allowMethod(['get']);
        $this->disableAutoRender();

        $search   = trim((string)$this->request->getQuery('q', ''));
        $status   = $this->request->getQuery('status', '');
        $dateFrom = $this->request->getQuery('date_from', '');
        $dateTo   = $this->request->getQuery('date_to', '');
        $deliveryFrom = $this->request->getQuery('delivery_from', '');
        $deliveryTo   = $this->request->getQuery('delivery_to', '');

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $query = $SpeedOrders->find()->orderByDesc('SpeedOrders.date_doc');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'      => $like,
                'SpeedOrders.buyer_name LIKE'  => $like,
                'SpeedOrders.buyer_nip LIKE'   => $like,
            ]]);
        }
        if ($status !== '') {
            if (str_starts_with($status, 'nl_'))       $query->where(['SpeedOrders.nordlogis_status' => (int)substr($status, 3)]);
            elseif (str_starts_with($status, 'sp_'))   $query->where(['SpeedOrders.status' => (int)substr($status, 3)]);
            elseif ($status === 'brak_pod')             $query->where(['SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
            elseif ($status === 'brak_fk')              $query->where(['SpeedOrders.fk_at IS' => null]);
            elseif ($status === 'niezafakt')            $query->where(['SpeedOrders.invoice_id IS' => null]);
            elseif ($status === 'przetermin')           $query->where(['SpeedOrders.date_delivery <' => date('Y-m-d'), 'SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
        }
        if ($dateFrom !== '') $query->where(['SpeedOrders.date_doc >=' => $dateFrom]);
        if ($dateTo   !== '') $query->where(['SpeedOrders.date_doc <=' => $dateTo]);
        if ($deliveryFrom !== '') $query->where(['SpeedOrders.date_delivery >=' => $deliveryFrom]);
        if ($deliveryTo   !== '') $query->where(['SpeedOrders.date_delivery <=' => $deliveryTo]);

        $nlLabels = [1=>'Przyjęte',2=>'Zaplanowane',3=>'Załadowane',4=>'Zrealizowane',5=>'Zafakturowane'];
        $fdt = fn($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : substr((string)($v ?? ''), 0, 16);

        $out = fopen('php://output', 'w');
        // BOM dla Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Symbol','Data dok.','Zleceniodawca','NIP','Załadunek kraj','Załadunek miejsce',
            'Rozładunek kraj','Rozładunek miejsce','Kierowca','Przewoźnik',
            'Netto','Waluta','Status Nordlogis','POL','POD','FK','FS','Kompletne','Faktura ID','Data faktury',
        ], ';');

        foreach ($query->all() as $o) {
            fputcsv($out, [
                $o->symbol,
                $o->date_doc instanceof \DateTimeInterface ? $o->date_doc->format('Y-m-d') : substr((string)($o->date_doc ?? ''), 0, 10),
                $o->buyer_name,
                $o->buyer_nip,
                $o->load_country,
                trim(($o->load_postal_code ?? '') . ' ' . ($o->load_city ?? '')),
                $o->unload_country,
                trim(($o->unload_name ?? '') . ' ' . ($o->unload_city ?? '')),
                $o->driver,
                $o->carrier,
                str_replace('.', ',', (string)($o->netto ?? '')),
                $o->currency,
                $nlLabels[(int)($o->nordlogis_status ?? 1)] ?? '',
                $fdt($o->pol_at),
                $fdt($o->pod_at),
                $fdt($o->fk_at),
                $fdt($o->fs_at),
                $o->is_complete ? 'TAK' : 'NIE',
                $o->invoice_id ?? '',
                $fdt($o->invoiced_at),
            ], ';');
        }
        fclose($out);

        $filename = 'zlecenia-' . date('Y-m-d') . '.csv';
        $this->response = $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // -------------------------------------------------------------------------
    // Dashboard / control tower
    // -------------------------------------------------------------------------
    public function dashboard(): void
    {
        $this->request->allowMethod(['get']);
        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $today = date('Y-m-d');

        // Agregaty jednym zapytaniem
        $base = $SpeedOrders->find()->where(['SpeedOrders.invoice_id IS' => null]);

        $stats = [
            'total'        => $SpeedOrders->find()->count(),
            'bez_faktury'  => (clone $base)->count(),
            'bez_pod'      => (clone $base)->where(['SpeedOrders.pod_at IS' => null])->count(),
            'bez_fk'       => $SpeedOrders->find()->where(['SpeedOrders.fk_at IS' => null])->count(),
            'bez_fs'       => $SpeedOrders->find()->where(['SpeedOrders.fs_at IS' => null, 'SpeedOrders.invoice_id IS' => null])->count(),
            'przetermin'   => $SpeedOrders->find()->where([
                'SpeedOrders.date_delivery <' => $today,
                'SpeedOrders.pod_at IS'       => null,
                'SpeedOrders.invoice_id IS'   => null,
            ])->count(),
            'kompletne'    => $SpeedOrders->find()->where(['SpeedOrders.is_complete' => true])->count(),
        ];

        // Statusy Nordlogis — rozkład
        $nlCounts = [];
        for ($i = 1; $i <= 5; $i++) {
            $nlCounts[$i] = $SpeedOrders->find()->where(['SpeedOrders.nordlogis_status' => $i])->count();
        }

        // Przeterminowane — lista (max 20)
        $overdue = $SpeedOrders->find()
            ->where([
                'SpeedOrders.date_delivery <' => $today,
                'SpeedOrders.pod_at IS'       => null,
                'SpeedOrders.invoice_id IS'   => null,
            ])
            ->orderByAsc('SpeedOrders.date_delivery')
            ->limit(20)
            ->all();

        // Ostatnio zmodyfikowane — 10 zleceń
        $recent = $SpeedOrders->find()
            ->orderByDesc('SpeedOrders.modified')
            ->limit(10)
            ->all();

        $this->set(compact('stats', 'nlCounts', 'overdue', 'recent', 'today'));
    }

    // -------------------------------------------------------------------------
    // Szczegóły zlecenia
    // -------------------------------------------------------------------------
    public function view(int $id): void
    {
        $this->request->allowMethod(['get']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        // M:N: ładujemy wszystkie faktury sprzedażowe przez pivot — \$order->invoices
        $order = $SpeedOrders->find()
            ->where(['SpeedOrders.id' => $id])
            ->contain([
                'AllInvoices' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'fullnumber', 'date', 'total', 'currency', 'paymentstate'])
                        ->orderByAsc('AllInvoices.date');
                },
                'SpeedOrderStops',
                'SpeedOrderCargoItems',
            ])
            ->firstOrFail();
        $rawData = null;
        if (!empty($order->raw_json)) {
            $rawData = json_decode($order->raw_json, true);
        }

        // Załaduj przypisane faktury kosztowe
        $CostInvoiceOrders = $this->fetchTable('CostInvoiceOrders');
        $pivotRows = $CostInvoiceOrders->find()
            ->where(['speed_order_id' => $id])
            ->all()
            ->map(fn($r) => $r->cost_invoice_id)
            ->toArray();

        $costInvoices = [];
        if (!empty($pivotRows)) {
            $costInvoices = $this->fetchTable('CostInvoices')
                ->find()
                ->where(['id IN' => $pivotRows])
                ->orderByDesc('issue_date')
                ->all()
                ->toArray();
        }

        // Historia zmian statusów (tabela może nie istnieć przed migracją)
        try {
            $statusLogs = $this->fetchTable('SpeedOrderStatusLogs')
                ->find()
                ->where(['speed_order_id' => $id])
                ->orderByAsc('created')
                ->all()
                ->toArray();
        } catch (\Throwable) {
            $statusLogs = [];
        }
        $logAvatarMap = $this->buildLogAvatarMap($statusLogs);

        // Załączniki CMR
        try {
            $attachments = $this->fetchTable('SpeedOrderAttachments')
                ->find()
                ->contain(['SpeedOrderAttachmentLabels'])
                ->where(['SpeedOrderAttachments.speed_order_id' => $id])
                ->orderByAsc('SpeedOrderAttachments.created')
                ->all()
                ->toArray();
            $attachmentLabels = $this->fetchTable('SpeedOrderAttachmentLabels')
                ->find()
                ->orderByAsc('sort')
                ->all()
                ->toArray();
        } catch (\Throwable) {
            $attachments = [];
            $attachmentLabels = [];
        }

        // Notatki wewnetrzne
        $notes = [];
        try {
            $notes = $this->fetchTable('SpeedOrderNotes')->find()
                ->contain(['Users' => function ($q) {
                    return $q->select(['id', 'first_name', 'last_name', 'username']);
                }])
                ->where(['SpeedOrderNotes.speed_order_id' => $id])
                ->orderByDesc('SpeedOrderNotes.created')
                ->all()
                ->toList();
        } catch (\Throwable) {}

        $this->set(compact('order', 'rawData', 'costInvoices', 'statusLogs', 'logAvatarMap', 'attachments', 'attachmentLabels', 'notes'));
    }

    // -------------------------------------------------------------------------
    // Szczegóły zlecenia — AJAX (bez layoutu, do modala)
    // -------------------------------------------------------------------------
    public function viewModal(int $id): void
    {
        $this->request->allowMethod(['get']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        // M:N — wszystkie faktury sprzedażowe przez pivot
        $order = $SpeedOrders->find()
            ->where(['SpeedOrders.id' => $id])
            ->contain([
                'AllInvoices' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'fullnumber', 'date', 'total', 'currency', 'paymentstate'])
                        ->orderByAsc('AllInvoices.date');
                },
                'SpeedOrderStops',
                'SpeedOrderCargoItems',
            ])
            ->firstOrFail();
        $rawData = null;
        if (!empty($order->raw_json)) {
            $rawData = json_decode($order->raw_json, true);
        }

        $CostInvoiceOrders = $this->fetchTable('CostInvoiceOrders');
        $pivotRows = $CostInvoiceOrders->find()
            ->where(['speed_order_id' => $id])
            ->all()
            ->map(fn($r) => $r->cost_invoice_id)
            ->toArray();

        $costInvoices = [];
        if (!empty($pivotRows)) {
            $costInvoices = $this->fetchTable('CostInvoices')
                ->find()
                ->where(['id IN' => $pivotRows])
                ->orderByDesc('issue_date')
                ->all()
                ->toArray();
        }

        try {
            $statusLogs = $this->fetchTable('SpeedOrderStatusLogs')
                ->find()
                ->where(['speed_order_id' => $id])
                ->orderByAsc('created')
                ->all()
                ->toArray();
        } catch (\Throwable) {
            $statusLogs = [];
        }
        $logAvatarMap = $this->buildLogAvatarMap($statusLogs);

        try {
            $attachments = $this->fetchTable('SpeedOrderAttachments')
                ->find()
                ->contain(['SpeedOrderAttachmentLabels'])
                ->where(['SpeedOrderAttachments.speed_order_id' => $id])
                ->orderByAsc('SpeedOrderAttachments.created')
                ->all()
                ->toArray();
            $attachmentLabels = $this->fetchTable('SpeedOrderAttachmentLabels')
                ->find()
                ->orderByAsc('sort')
                ->all()
                ->toArray();
        } catch (\Throwable) {
            $attachments = [];
            $attachmentLabels = [];
        }

        $this->set(compact('order', 'rawData', 'costInvoices', 'statusLogs', 'logAvatarMap', 'attachments', 'attachmentLabels'));
        $this->set('isModal', true);
        $this->viewBuilder()->setTemplate('view');
        $this->viewBuilder()->disableAutoLayout();
    }

    // -------------------------------------------------------------------------
    // Upload załącznika CMR (AJAX POST)
    // -------------------------------------------------------------------------
    public function uploadAttachment(int $id): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $order = $this->fetchTable('SpeedOrders')->get($id);

        $file = $this->request->getUploadedFile('file');
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Brak pliku lub błąd uploadu']));
            $this->response = $this->response->withType('json');
            return;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $mime = $file->getClientMediaType();
        if (!in_array($mime, $allowedMimes, true)) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Niedozwolony typ pliku']));
            $this->response = $this->response->withType('json');
            return;
        }

        if ($file->getSize() > 15 * 1024 * 1024) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Plik za duży (max 15 MB)']));
            $this->response = $this->response->withType('json');
            return;
        }

        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $subDir = 'files' . DS . 'speed_orders' . DS . 'cmr' . DS . date('Y') . DS . date('m');
        $dir = WWW_ROOT . $subDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file->moveTo($dir . DS . $safeName);

        $labelId  = (int)$this->request->getData('label_id') ?: null;
        $identity = $this->request->getAttribute('identity');
        $username = $identity
            ? (string)($identity->get('username') ?? $identity->get('email') ?? $identity->getIdentifier())
            : null;

        $Attachments = $this->fetchTable('SpeedOrderAttachments');
        $entity = $Attachments->newEntity([
            'speed_order_id' => $id,
            'label_id'       => $labelId,
            'file_path'      => str_replace(DS, '/', $subDir . '/' . $safeName),
            'original_name'  => $file->getClientFilename(),
            'mime_type'      => $mime,
            'file_size'      => $file->getSize(),
            'uploaded_by'    => $username,
        ]);

        if (!$Attachments->save($entity)) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Błąd zapisu w bazie']));
            $this->response = $this->response->withType('json');
            return;
        }

        // Załaduj etykietę do odpowiedzi i zaktualizuj status POL/POD na zleceniu
        $label = null;
        $polAt = null;
        $podAt = null;
        $nordlogisStatus = null;
        if ($labelId) {
            try {
                $label = $this->fetchTable('SpeedOrderAttachmentLabels')->get($labelId);
                $slug  = (string)($label->slug ?? '');

                $SpeedOrders = $this->fetchTable('SpeedOrders');
                $order = $SpeedOrders->get($id);
                $now   = date('Y-m-d H:i:s');
                $changed = false;

                if (in_array($slug, ['pol_photo', 'pol_scan'], true) && empty($order->pol_at)) {
                    $order->set('pol_at', $now);
                    $changed = true;
                }
                if (in_array($slug, ['pod_photo', 'pod_scan'], true) && empty($order->pod_at)) {
                    $order->set('pod_at', $now);
                    $changed = true;
                }

                if ($changed) {
                    $this->applyAutoNlStatus($order);
                    $SpeedOrders->save($order);
                    $polAt = $order->pol_at ? (string)$order->pol_at : null;
                    $podAt = $order->pod_at ? (string)$order->pod_at : null;
                    $nordlogisStatus = (int)($order->nordlogis_status ?? 1);
                }
            } catch (\Throwable) {}
        }

        $this->response->getBody()->write(json_encode([
            'ok'              => true,
            'id'              => $entity->id,
            'file_path'       => $entity->file_path,
            'original_name'   => $entity->original_name,
            'mime_type'       => $mime,
            'label'           => $label ? $label->name : null,
            'label_slug'      => $label ? ($label->slug ?? null) : null,
            'uploaded_by'     => $username,
            'created'         => date('Y-m-d H:i'),
            'pol_at'          => $polAt,
            'pod_at'          => $podAt,
            'nordlogis_status' => $nordlogisStatus,
        ]));
        $this->response = $this->response->withType('json');
    }

    // -------------------------------------------------------------------------
    // Usunięcie załącznika CMR (AJAX DELETE/POST)
    // -------------------------------------------------------------------------
    public function deleteAttachment(int $orderId, int $attachmentId): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post', 'delete']);

        $Attachments = $this->fetchTable('SpeedOrderAttachments');
        try {
            $entity = $Attachments->get($attachmentId);
        } catch (\Throwable) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Nie znaleziono załącznika']));
            $this->response = $this->response->withType('json');
            return;
        }

        if ($entity->speed_order_id !== $orderId) {
            $this->response->getBody()->write(json_encode(['ok' => false, 'error' => 'Brak dostępu']));
            $this->response = $this->response->withType('json');
            return;
        }

        // Usuń plik z dysku
        $fullPath = WWW_ROOT . str_replace('/', DS, $entity->file_path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $Attachments->delete($entity);

        // Po usunięciu pliku re-evaluate status (mógł spaść z 3 → 2 gdy
        // usunięto ostatni POL, lub 4 → 3 gdy usunięto ostatni POD).
        try {
            $SpeedOrders = $this->fetchTable('SpeedOrders');
            $order = $SpeedOrders->get($orderId);
            [$hasPol, $hasPod] = $this->hasPolPodFiles($orderId);
            // Wyzeruj pol_at/pod_at gdy brak plików (spójność)
            if (!$hasPol && !empty($order->pol_at)) {
                $order->set('pol_at', null);
                $order->set('pol_by', null);
            }
            if (!$hasPod && !empty($order->pod_at)) {
                $order->set('pod_at', null);
                $order->set('pod_by', null);
            }
            $this->applyAutoNlStatus($order);
            $SpeedOrders->save($order);
        } catch (\Throwable) { /* best-effort */ }

        $this->response->getBody()->write(json_encode(['ok' => true]));
        $this->response = $this->response->withType('json');
    }

    // -------------------------------------------------------------------------
    // Synchronizacja z Speed API (AJAX POST)
    // -------------------------------------------------------------------------
    public function sync(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $apiUrl   = rtrim((string)(
            getenv('SPEED_API_URL') ?: Configure::read('Speed.apiUrl') ?: ''
        ), '/');
        $apiToken = (string)(
            getenv('SPEED_API_TOKEN') ?: Configure::read('Speed.apiToken') ?: ''
        );

        if ($apiUrl === '') {
            $this->jsonResp(['success' => false, 'error' => 'Brak konfiguracji SPEED_API_URL.']);
            return;
        }

        $startPage = max(1, (int)$this->request->getData('page', 1));
        $limit     = 100;

        try {
            $client  = new Client();
            $headers = $apiToken !== '' ? ['Authorization' => 'Bearer ' . $apiToken] : [];

            $resp = $client->get(
                $apiUrl . '/zlecenia',
                ['page' => $startPage, 'limit' => $limit],
                ['headers' => $headers, 'timeout' => 30]
            );

            if ($resp->getStatusCode() !== 200) {
                $this->jsonResp(['success' => false, 'error' => 'Speed API HTTP ' . $resp->getStatusCode()]);
                return;
            }

            $json = $resp->getJson();
            if (!is_array($json) || !isset($json['data'])) {
                $this->jsonResp(['success' => false, 'error' => 'Nieoczekiwana odpowiedź Speed API (brak "data").']);
                return;
            }

            $payload    = (array)$json['data'];
            $totalPages = (int)($json['totalPages'] ?? 1);
            $total      = (int)($json['total'] ?? 0);

            $saved   = 0;
            $updated = 0;
            $errors  = [];
            $now     = date('Y-m-d H:i:s');
            $SpeedOrders = $this->fetchTable('SpeedOrders');

            foreach ($payload as $r) {
                $speedId = (int)($r['GLO_ID'] ?? 0);
                if ($speedId === 0) {
                    continue;
                }

                // Sklejenie adresu trasy
                $routeDesc = trim((string)($r['GLO_NAZ9'] ?? ''));

                // parse dates
                $dateDoc      = $this->parseSpeedDate($r['GLO_DATA_DOK'] ?? null);
                $dateShip     = $this->parseSpeedDate($r['GLO_DATA_WYS'] ?? null);
                $dateDeadline = $this->parseSpeedDate($r['GLO_DATA_TER'] ?? null);
                $dateDelivery = $this->parseSpeedDate($r['GLO_DATA_ZAK'] ?? null);
                $speedModAt   = $this->parseSpeedDate($r['GLO_DATA_ZMI'] ?? null);

                // Załadunek
                $loadCountry = trim((string)($r['GLO_MIE_KRAJ']    ?? ''));
                $loadCode    = trim((string)($r['GLO_MIE_KOD']     ?? ''));
                $loadCity    = trim((string)($r['GLO_MIE_POCZTA']  ?? ''));

                // Rozładunek
                $unloadN1    = trim((string)($r['GLO_MIE_NAZWA1']  ?? ''));
                $unloadN2    = trim((string)($r['GLO_MIE_NAZWA2']  ?? ''));
                $unloadCity  = trim((string)($r['GLO_MIE_MIEJSC']  ?? ''));
                $unloadName  = implode(', ', array_filter([$unloadN1, $unloadN2])) ?: null;

                $data = [
                    'speed_id'          => $speedId,
                    'source'            => 'speed',
                    'company_nip'       => trim((string)($r['GLO_FIR_NIP'] ?? '')),
                    'company_name'      => trim((string)($r['GLO_FIR_NAZWA1'] ?? '')),
                    'symbol'            => trim((string)($r['GLO_SYMBOL'] ?? '')),
                    'ozn'               => trim((string)($r['GLO_OZN'] ?? '')),
                    'numer'             => (int)($r['GLO_NUMER'] ?? 0) ?: null,
                    'rok'               => trim((string)($r['GLO_ROK'] ?? '')),
                    'mc'                => trim((string)($r['GLO_MC'] ?? '')),
                    'teczka'            => trim((string)($r['GLO_TECZKA'] ?? '')),
                    'status'            => (int)($r['GLO_STATUS'] ?? 1),
                    'buyer_speed_id'    => (int)($r['GLO_ODB_ID'] ?? 0) ?: null,
                    'buyer_nip'         => trim((string)($r['GLO_ODB_NIP'] ?? '')),
                    'buyer_name'        => trim((string)($r['GLO_ODB_NAZWA1'] ?? '')),
                    'buyer_street'      => trim((string)($r['GLO_ODB_ULICA'] ?? '')),
                    'buyer_postal_code' => trim((string)($r['GLO_ODB_KOD'] ?? '')),
                    'buyer_city'        => trim((string)($r['GLO_ODB_MIEJSC'] ?? '')) ?: trim((string)($r['GLO_ODB_POCZTA'] ?? '')),
                    'buyer_country'     => trim((string)($r['GLO_ODB_KRAJ'] ?? '')),
                    'buyer_email'       => trim((string)($r['GLO_ODB_EMAIL'] ?? '')),
                    // Załadunek
                    'place_from_name'   => $unloadN1, // GLO_MIE_NAZWA1 = miejsce załadunku
                    'place_from_country'=> $loadCountry,
                    'load_country'      => $loadCountry ?: null,
                    'load_postal_code'  => $loadCode    ?: null,
                    'load_city'         => $loadCity    ?: null,
                    // Rozładunek
                    'place_to_name'     => $unloadN2,  // GLO_MIE_NAZWA2 = miejsce rozładunku
                    'place_to_country'  => $loadCountry, // fallback — Speed nie daje osobnego kraju rozładunku
                    'unload_name'       => $unloadName,
                    'unload_city'       => $unloadCity  ?: null,
                    'unload_country'    => null, // brak w API Speed — uzupełniamy ręcznie
                    // Trasa / opis
                    'route_description' => $routeDesc ?: null,
                    'title1'            => trim((string)($r['GLO_TYT1']    ?? '')),
                    'title2'            => trim((string)($r['GLO_TYT2']    ?? '')),
                    'cargo_type'        => trim((string)($r['GLO_NAZ10']   ?? '')),
                    // Transport
                    'driver'            => trim((string)($r['GLO_JEDNOSTKA'] ?? '')) ?: null,
                    'carrier'           => trim((string)($r['GLO_NAZ8']      ?? '')) ?: null,
                    // 'Spedytor 2' w Speed (GLO_NAZ7) używane jako kontrakt operacyjny
                    // (OWN 1, OWN PL 2, OWN X1 itd.)
                    'contract'          => trim((string)($r['GLO_NAZ7']      ?? '')) ?: null,
                    'transport_type'    => trim((string)($r['GLO_MIE_RODZAJ'] ?? '')) ?: null,
                    'vehicle_reg'       => trim((string)($r['GLO_KONTO']      ?? '')) ?: null,
                    // Finansowe
                    'notes'             => ($r['GLO_UWAGI'] ?? null) !== null ? (string)$r['GLO_UWAGI'] : null,
                    'payment_terms'     => trim((string)($r['GLO_PLATNOSC']  ?? '')) ?: null,
                    'our_ref'           => trim((string)($r['GLO_POD']       ?? '')) ?: null,
                    'date_doc'          => $dateDoc,
                    'date_ship'         => $dateShip,
                    'date_deadline'     => $dateDeadline,
                    'date_delivery'     => $dateDelivery,
                    'currency'          => trim((string)($r['GLO_WALUTA'] ?? 'PLN')) ?: 'PLN',
                    'netto'             => (float)($r['GLO_NETTO'] ?? 0),
                    'vat'               => (float)($r['GLO_VAT'] ?? 0),
                    'brutto'            => (float)($r['GLO_BRUTTO'] ?? 0),
                    'exchange_rate'     => ($r['GLO_WAL_PRZEL'] ?? null) !== null ? (float)$r['GLO_WAL_PRZEL'] : null,
                    'exchange_table'    => trim((string)($r['GLO_WAL_TABELA'] ?? '')),
                    'nick_created'      => trim((string)($r['GLO_NICK_WYS']  ?? '')),
                    'nick_modified'     => trim((string)($r['GLO_NICK_ZMI']  ?? '')),
                    'raw_json'          => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'imported_at'       => date('Y-m-d H:i:s'),
                    'speed_modified_at' => $speedModAt,
                ];

                // ── Auto: rzeczywisty załadunek/rozładunek (actual_*) na podstawie dat ──
                // Tylko gdy planowana data jest w przeszłości — wtedy domyślnie
                // zakładamy że zrealizowano. NIE ustawiamy pol_at/pod_at —
                // ikony POL/POD pozostają szare aż do faktycznego wgrania plików.
                $autoLoad   = ($dateDeadline !== null && $dateDeadline < date('Y-m-d'));
                $autoUnload = ($dateDelivery !== null && $dateDelivery < date('Y-m-d'));

                // Upsert po speed_id
                $existing = $SpeedOrders->find()->where(['speed_id' => $speedId])->first();
                if ($existing) {
                    $entity = $SpeedOrders->patchEntity($existing, $data);
                    // Nie cofamy ręcznie ustawionych dat — uzupełniamy tylko jeśli puste
                    if ($autoLoad && empty($entity->actual_load_at)) {
                        $entity->set('actual_load_at', $dateDeadline . ' 00:00:00');
                    }
                    if ($autoUnload && empty($entity->actual_unload_at)) {
                        $entity->set('actual_unload_at', $dateDelivery . ' 00:00:00');
                    }
                    $this->applyAutoNlStatus($entity);
                    if ($SpeedOrders->save($entity)) {
                        $updated++;
                    } else {
                        $errors[] = 'Błąd aktualizacji GLO_ID=' . $speedId;
                    }
                } else {
                    $entity = $SpeedOrders->newEntity($data);
                    if ($autoLoad)   $entity->set('actual_load_at',   $dateDeadline . ' 00:00:00');
                    if ($autoUnload) $entity->set('actual_unload_at', $dateDelivery . ' 00:00:00');
                    $this->applyAutoNlStatus($entity);
                    if ($SpeedOrders->save($entity)) {
                        $saved++;
                    } else {
                        $errors[] = 'Błąd zapisu GLO_ID=' . $speedId;
                    }
                }
            }

            $this->jsonResp([
                'success'    => true,
                'page'       => $startPage,
                'totalPages' => $totalPages,
                'total'      => $total,
                'saved'      => $saved,
                'updated'    => $updated,
                'errors'     => $errors,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResp(['success' => false, 'error' => 'Błąd: ' . $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function parseSpeedDate(mixed $val): ?string
    {
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        $s = (string)$val;
        // ISO 8601 datetime: 2026-04-08T00:00:00.000Z  →  "2026-04-08 00:00:00"
        // Cake DateTimeType::marshal() wymaga formatu z czasem (Y-m-d H:i:s),
        // sam Y-m-d nie pasuje do żadnego _marshalFormats i zwraca null.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/', $s, $m)) {
            return $m[1] . ' ' . $m[2]; // "2026-04-08 00:00:00"
        }
        // fallback: data bez czasu (dla kolumn typu `date`)
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1];
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Wsadowe tworzenie faktur z wielu zleceń (AJAX POST, JSON)
    // Payload: { groups: [ { order_ids: [1,2,3], currency: 'EUR', delivery_date: '...' }, ... ] }
    // Odpowiedź: { success: true, invoices: [ { url: '...', contractor: '...', count: N }, ... ] }
    // -------------------------------------------------------------------------
    public function createBatchInvoices(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $body   = (array)$this->request->getData();
        $groups = (array)($body['groups'] ?? []);

        if (empty($groups)) {
            $this->jsonResp(['success' => false, 'error' => 'Brak danych do przetworzenia.']);
            return;
        }

        $invoices = [];

        foreach ($groups as $group) {
            $orderIds = array_filter(array_map('intval', (array)($group['order_ids'] ?? [])));
            if (empty($orderIds)) continue;

            $currency = strtoupper(trim((string)($group['currency'] ?? 'PLN')));
            $action   = ($currency !== '' && $currency !== 'PLN') ? 'addCurrency' : 'addVat';

            // Buduj URL z wieloma order_ids (np. ?from_order_ids[]=1&from_order_ids[]=2)
            $queryParams = [];
            foreach ($orderIds as $id) {
                $queryParams['from_order_ids'][] = $id;
            }

            $url = \Cake\Routing\Router::url([
                'controller' => 'Invoices',
                'action'     => $action,
                '?'          => $queryParams,
            ], true);

            $invoices[] = [
                'url'          => $url,
                'contractor'   => (string)($group['contractor'] ?? ''),
                'delivery_date'=> (string)($group['delivery_date'] ?? ''),
                'currency'     => $currency,
                'count'        => count($orderIds),
                'order_ids'    => array_values($orderIds),
            ];
        }

        $this->jsonResp(['success' => true, 'invoices' => $invoices]);
    }

    // -------------------------------------------------------------------------
    // Zmiana statusu Nordlogis / checkboxów POL/POD/FK/FS (AJAX POST)
    // Payload: { id: 1, nordlogis_status: 3 } lub { id: 1, pol: true/false }
    // -------------------------------------------------------------------------
    public function updateStatus(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $id   = (int)$this->request->getData('id', 0);
        $SpeedOrders = $this->fetchTable('SpeedOrders');

        /** @var \App\Model\Entity\SpeedOrder|null $order */
        $order = $SpeedOrders->find()->where(['id' => $id])->first();
        if (!$order || $id === 0) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono zlecenia.']);
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Identyfikacja zalogowanego usera
        $identity  = $this->request->getAttribute('identity');
        $userId    = $identity ? (int)$identity->getIdentifier() : null;
        $username  = $identity
            ? (string)($identity->get('username') ?? $identity->get('email') ?? $identity->getIdentifier())
            : 'system';

        $Logs = $this->fetchTable('SpeedOrderStatusLogs');
        $logEntries = [];

        // Oryginalny status — używany później do walidacji edycji actual_*_at.
        // Capture na samym początku, bo nordlogis_status może zostać zmienione w trakcie.
        $originalNs = (int)($order->nordlogis_status ?? 1);

        // Powód + notatka (z modalu "Zatwierdź i oznacz jako…") — dopinane do wszystkich
        // wpisów logów generowanych w tym requeście. Pusta wartość = NULL w logach.
        $reqReason = trim((string)$this->request->getData('reason', ''));
        $reqNote   = trim((string)$this->request->getData('note', ''));
        $reqReason = $reqReason !== '' ? $reqReason : null;
        $reqNote   = $reqNote   !== '' ? $reqNote   : null;

        // Zmiana statusu operacyjnego (stepper).
        // Walidacja sekwencji do PRZODU: 4 (Zrealizowane) wymaga 3 (Załadowane);
        //                                 5 (Zafakturowane) wymaga 4 (Zrealizowane).
        // Admin może cofać status WSTECZ bez force — to standardowa korekta błędu.
        // force=1 jest potrzebny tylko żeby zablokować applyAutoNlStatus, które
        // mogłoby podbić status z powrotem na podstawie istniejących plików/pól.
        $isAdminStepper = (bool)($identity?->get('is_admin') ?? false)
                       || strtolower((string)($identity?->get('role') ?? '')) === 'admin';
        $isForceStepper = $isAdminStepper && (bool)$this->request->getData('force');
        $adminReversing = false;
        if ($this->request->getData('nordlogis_status') !== null) {
            $ns = (int)$this->request->getData('nordlogis_status');
            if ($ns >= 1 && $ns <= 5) {
                $oldNs = (int)($order->nordlogis_status ?? 1);
                // Admin cofa status → traktujemy jak force żeby auto-eskalacja
                // nie podniosła go z powrotem.
                if ($isAdminStepper && $ns < $oldNs) {
                    $adminReversing = true;
                }
                // Logiczny "effective" stary status: pol_at oznacza co najmniej 3,
                // pod_at co najmniej 4 (user mógł zaznaczyć pill bez bumpa statusu,
                // bo applyAutoNlStatus opiera się na plikach, nie polach _at).
                $effOldNs = $oldNs;
                if (!empty($order->pol_at)) $effOldNs = max($effOldNs, 3);
                if (!empty($order->pod_at)) $effOldNs = max($effOldNs, 4);
                if (!$isForceStepper && $ns > $oldNs) {
                    if ($ns === 4 && $effOldNs < 3) {
                        $this->jsonResp([
                            'success' => false,
                            'error'   => 'Niedostępne: najpierw ustaw status "Załadowane".',
                        ]);
                        return;
                    }
                    if ($ns === 5 && $effOldNs < 4) {
                        $this->jsonResp([
                            'success' => false,
                            'error'   => 'Niedostępne: najpierw ustaw status "Zrealizowane".',
                        ]);
                        return;
                    }
                }
                if ($oldNs !== $ns) {
                    $logEntries[] = ['field' => 'nordlogis_status', 'old' => (string)$oldNs, 'new' => (string)$ns];
                }
                $order->set('nordlogis_status', $ns);
            }
        }

        // Pole: dokumenty tylko elektronicznie
        if ($this->request->getData('docs_electronic_only') !== null) {
            $val = (bool)(int)$this->request->getData('docs_electronic_only');
            $order->set('docs_electronic_only', $val);
        }

        // Rzeczywisty załadunek/rozładunek (datetime, edytowalne przez spedytora).
        // Po Załadowane (3+) actual_load_at jest readonly, po Zrealizowane (4+) — actual_unload_at.
        // Admin może zawsze. Sprawdzamy ORYGINALNY status (przed ewentualnym bumpem
        // przez stepper), żeby nie zablokować POL button który w jednym requeście
        // bumpuje ns=2→3 i zapisuje actual_load_at.
        foreach (['actual_load_at', 'actual_unload_at'] as $field) {
            $val = $this->request->getData($field);
            if ($val === null) {
                continue;
            }
            // Lock guard: non-admin nie może zmieniać po fixacji statusu.
            $isLockedField = (!$isAdminEarly) && (
                ($field === 'actual_load_at'   && $originalNs >= 3)
             || ($field === 'actual_unload_at' && $originalNs >= 4)
            );
            if ($isLockedField) {
                $this->jsonResp([
                    'success' => false,
                    'error'   => $field === 'actual_load_at'
                        ? 'Nie można zmienić rzeczywistego załadunku — status to "Załadowane" lub wyżej. Skontaktuj się z administratorem.'
                        : 'Nie można zmienić rzeczywistego rozładunku — status to "Zrealizowane". Skontaktuj się z administratorem.',
                ]);
                return;
            }
            $val = trim((string)$val);
            $oldVal = $order->{$field} ? substr((string)$order->{$field}, 0, 16) : null;
            // Akceptowany format: 'YYYY-MM-DDTHH:MM' (datetime-local) lub puste = wyczyść
            if ($val === '') {
                $newVal = null;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $val)) {
                $newVal = str_replace('T', ' ', $val);
                if (strlen($newVal) === 16) {
                    $newVal .= ':00';
                }
            } else {
                continue; // bezpieczeństwo: nie zapisuj nieprawidłowych wartości
            }
            $order->set($field, $newVal);
            $newValStr = $newVal ? substr($newVal, 0, 16) : null;
            if ($oldVal !== $newValStr) {
                $logEntries[] = ['field' => $field, 'old' => $oldVal, 'new' => $newValStr];
            }
        }

        // Walidacja sekwencji: POD wymaga POL (chyba że force=1 dla admina).
        // POL/POD jest również warunkiem dla FK/FS (semantycznie), ale tu blokujemy
        // tylko PROD-bez-POL żeby nie psuć ewentualnych legacy zachowań na FK/FS.
        $isAdminEarly  = (bool)($identity?->get('is_admin') ?? false)
                      || strtolower((string)($identity?->get('role') ?? '')) === 'admin';
        $isForceEarly  = $isAdminEarly && (bool)$this->request->getData('force');
        $podRequested  = $this->request->getData('pod');
        if ($podRequested !== null && (bool)$podRequested && empty($order->pol_at) && !$isForceEarly) {
            $this->jsonResp([
                'success' => false,
                'error'   => 'Niedostępne: najpierw oznacz jako "Załadowane".',
            ]);
            return;
        }

        // Checkboxy — ustawiamy datę, pole *_by i null
        foreach (['pol', 'pod', 'fk', 'fs'] as $chk) {
            $val = $this->request->getData($chk);
            if ($val !== null) {
                $fieldAt = $chk . '_at';
                $fieldBy = $chk . '_by';
                $checked = (bool)$val;
                $oldVal  = $order->{$fieldAt} ? (string)$order->{$fieldAt} : null;
                $newVal  = $checked ? $now : null;

                $order->set($fieldAt, $newVal);
                // *_by: ustawiamy przy zaznaczeniu, czyścimy przy odznaczeniu
                $order->set($fieldBy, $checked ? $username : null);

                if ($chk === 'fs' && $checked) {
                    $order->set('invoiced_at', $now);
                }

                if ($oldVal !== $newVal) {
                    $logEntries[] = [
                        'field' => $fieldAt,
                        'old'   => $oldVal ? substr($oldVal, 0, 16) : null,
                        'new'   => $newVal ? substr($newVal, 0, 16) : null,
                    ];
                }
            }
        }

        // Automatyczne przejścia statusu + is_complete
        // Admin może wymusić cofnięcie statusu/dat (parametr force=1), wtedy
        // pomijamy applyAutoNlStatus żeby nie podbiło z powrotem.
        // Również gdy admin cofa nordlogis_status — same logic, omijamy auto-bump.
        $isAdmin = (bool)($identity?->get('is_admin') ?? false)
                || strtolower((string)($identity?->get('role') ?? '')) === 'admin';
        $forceAdmin = ($isAdmin && (bool)$this->request->getData('force')) || $adminReversing;
        if (!$forceAdmin) {
            $this->applyAutoNlStatus($order);
        } else {
            // Przy force i tak liczymy is_complete (read-only field)
            $order->set('is_complete',
                !empty($order->pol_at) && !empty($order->pod_at) &&
                !empty($order->fk_at)  && !empty($order->fs_at)
            );
        }

        if ($SpeedOrders->save($order)) {
            // Zapisz logi — reason/note z requestu trafiają do KAŻDEGO wpisu wygenerowanego
            // w tej zmianie (np. POL → status=3 → 2 logi, oba z tym samym reason/note).
            foreach ($logEntries as $entry) {
                $log = $Logs->newEntity([
                    'speed_order_id' => $order->id,
                    'field'          => $entry['field'],
                    'old_value'      => $entry['old'],
                    'new_value'      => $entry['new'],
                    'reason'         => $reqReason,
                    'note'           => $reqNote,
                    'user_id'        => $userId,
                    'username'       => $username,
                    'created'        => $now,
                ]);
                $Logs->save($log);
            }

            $this->jsonResp([
                'success'          => true,
                'nordlogis_status' => $order->nordlogis_status,
                'is_complete'      => $order->is_complete,
                'pol_at'           => $order->pol_at ? (string)$order->pol_at : null,
                'pol_by'           => $order->pol_by,
                'pod_at'           => $order->pod_at ? (string)$order->pod_at : null,
                'pod_by'           => $order->pod_by,
                'fk_at'            => $order->fk_at ? (string)$order->fk_at : null,
                'fk_by'            => $order->fk_by,
                'fs_at'            => $order->fs_at ? (string)$order->fs_at : null,
                'fs_by'            => $order->fs_by,
            ]);
        } else {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
        }
    }

    /**
     * Automatyczne przejścia statusu Nordlogis na podstawie:
     *  - fizycznych plików POL/POD (z speed_order_attachments + labels.slug)
     *  - pól fs_at / fk_at (FS/FK dalej z datetime)
     *
     * Status:
     *   3 (Załadowane)   ← gdy istnieje plik POL
     *   4 (Zrealizowane) ← gdy istnieje plik POD
     *   5 (Zafakturowane)← gdy fs_at != null
     *
     * is_complete: hasPol && hasPod && fk_at && fs_at.
     *
     * Wywołuj po zmianach pól lub załączników.
     */
    private function applyAutoNlStatus(\App\Model\Entity\SpeedOrder $entity): void
    {
        [$hasPol, $hasPod] = $this->hasPolPodFiles((int)$entity->id);
        $ns = (int)($entity->nordlogis_status ?? 1);

        if ($hasPol && $ns < 3) $ns = 3;
        if ($hasPod && $ns < 4) $ns = 4;
        if (!empty($entity->fs_at) && $ns < 5) $ns = 5;

        $entity->set('nordlogis_status', $ns);
        $entity->set('is_complete',
            $hasPol && $hasPod &&
            !empty($entity->fk_at) && !empty($entity->fs_at)
        );
    }

    /**
     * Sprawdza czy zlecenie ma załączniki POL i POD (po slug etykiety).
     *
     * @return array{0:bool,1:bool} [hasPol, hasPod]
     */
    private function hasPolPodFiles(int $orderId): array
    {
        if ($orderId <= 0) return [false, false];
        try {
            $rows = $this->fetchTable('SpeedOrderAttachments')->find()
                ->select(['SpeedOrderAttachmentLabels.slug'])
                ->contain(['SpeedOrderAttachmentLabels'])
                ->where(['SpeedOrderAttachments.speed_order_id' => $orderId])
                ->disableHydration()
                ->all();
            $hasPol = false; $hasPod = false;
            foreach ($rows as $r) {
                $slug = (string)($r['speed_order_attachment_label']['slug'] ?? '');
                if (str_starts_with($slug, 'pol_')) $hasPol = true;
                elseif (str_starts_with($slug, 'pod_')) $hasPod = true;
                if ($hasPol && $hasPod) break;
            }
            return [$hasPol, $hasPod];
        } catch (\Throwable) {
            return [false, false];
        }
    }

    /**
     * Zwraca mapę avatarów [user_id => '/files/avatars/...'] dla unikalnych user_id
     * w podanej liście logów. Pomija wpisy, w których plik avatara nie istnieje.
     */
    private function buildLogAvatarMap(array $statusLogs): array
    {
        $ids = [];
        foreach ($statusLogs as $log) {
            $uid = $log->user_id ?? null;
            if ($uid) {
                $ids[(string)$uid] = true;
            }
        }
        if (empty($ids)) {
            return [];
        }
        $map = [];
        try {
            $rows = $this->fetchTable('Users')->find()
                ->select(['id', 'avatar'])
                ->where(['id IN' => array_keys($ids), 'avatar IS NOT' => null])
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                if (empty($r['avatar'])) continue;
                $url = (string)$r['avatar'];
                if (str_starts_with($url, '/files/avatars/')) {
                    $diskPath = WWW_ROOT . ltrim($url, '/');
                    if (!is_file($diskPath)) continue;
                }
                $map[(string)$r['id']] = $url;
            }
        } catch (\Throwable) {
            // brak tabeli/avatara — zwracamy pustą mapę
        }
        return $map;
    }

    private function jsonResp(array $data): void
    {
        $this->viewBuilder()->disableAutoLayout();
        $this->autoRender = false;
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // =========================================================================
    // TRACKING - dashboard aktywnych zlecen z live trip_events
    // =========================================================================

    /**
     * GET /zlecenia/tracking - dashboard operatora dla zlecen w trasie.
     * Pokazuje aktywne zlecenia (nordlogis_status IN [3,4] bez pod_at) z ostatnim
     * eventem z trip_events, kierowca, pojazd, delay total, ETA.
     */
    public function tracking(): void
    {
        $this->request->allowMethod(['get']);

        $companyNip = $this->currentCompanyNip();
        $identity   = $this->request->getAttribute('identity');
        $companyId  = $identity?->get('company_id');
        if (!$companyNip) {
            $this->Flash->error(__('Brak NIP firmy'));
            $this->redirect(['action' => 'index']);
            return;
        }

        $filterDriver  = trim((string)$this->request->getQuery('driver', ''));
        $filterContract = trim((string)$this->request->getQuery('contract', ''));
        $filterCountry = strtoupper(trim((string)$this->request->getQuery('country', '')));

        // Aktywne zlecenia: zaladowane lub zrealizowane, bez POD
        $q = $this->fetchTable('SpeedOrders')->find()
            ->where([
                'company_nip'          => $companyNip,
                'nordlogis_status IN'  => [3, 4],
                'OR' => [
                    'pod_at IS' => null,
                    'is_complete' => false,
                ],
            ])
            ->orderByDesc('date_delivery')
            ->limit(100);

        if ($filterDriver !== '') $q->where(['driver LIKE' => '%' . $filterDriver . '%']);
        if ($filterContract !== '') $q->where(['contract' => $filterContract]);
        if ($filterCountry !== '') $q->where(['OR' => [
            'load_country'   => $filterCountry,
            'unload_country' => $filterCountry,
        ]]);

        $orders = $q->all();

        // Ostatni event per order (mapa order_id -> event)
        $lastEvents = [];
        try {
            $orderIds = array_map(fn($o) => $o->id, $orders->toArray());
            if (!empty($orderIds)) {
                $events = $this->fetchTable('TripEvents')->find()
                    ->where(['speed_order_id IN' => $orderIds, 'company_id' => $companyId])
                    ->orderByDesc('happened_at')
                    ->all();
                foreach ($events as $e) {
                    if (!isset($lastEvents[$e->speed_order_id])) {
                        $lastEvents[$e->speed_order_id] = $e;
                    }
                }
            }
        } catch (\Throwable) {}

        // Statystyki
        $stats = [
            'total'    => $orders->count(),
            'delayed'  => 0,
            'loading'  => 0,
            'in_transit' => 0,
            'unloading' => 0,
        ];
        foreach ($orders as $o) {
            $ev = $lastEvents[$o->id] ?? null;
            if ($ev) {
                if (in_array($ev->event_type, ['loading_started', 'loading_completed'], true)) $stats['loading']++;
                elseif (in_array($ev->event_type, ['departure', 'border_crossed'], true)) $stats['in_transit']++;
                elseif (in_array($ev->event_type, ['unloading_started', 'arrival'], true)) $stats['unloading']++;
                if ((int)($ev->delay_minutes ?? 0) > 0 || $ev->event_type === 'delay_reported') $stats['delayed']++;
            } else {
                $stats['in_transit']++;
            }
        }

        // Contracts dropdown
        $contractsList = [];
        try {
            $rows = $this->fetchTable('SpeedOrders')->find()
                ->select(['contract'])
                ->where(['company_nip' => $companyNip, 'contract IS NOT' => null])
                ->group('contract')
                ->orderByAsc('contract')
                ->disableHydration()
                ->all();
            foreach ($rows as $r) if (!empty($r['contract'])) $contractsList[] = $r['contract'];
        } catch (\Throwable) {}

        $this->set(compact('orders', 'lastEvents', 'stats', 'filterDriver', 'filterContract', 'filterCountry', 'contractsList'));
    }

    // =========================================================================
    // KANBAN operacyjny (5 kolumn per nordlogis_status)
    // =========================================================================

    /**
     * GET /zlecenia/kanban - operacyjny Kanban 5 kolumn.
     * Kolumny per nordlogis_status:
     *  1=Przyjete 2=Zaplanowane 3=Zaladowane 4=Zrealizowane 5=Zafakturowane
     */
    public function kanban(): void
    {
        $this->request->allowMethod(['get']);

        $companyNip = $this->currentCompanyNip();
        if (!$companyNip) {
            $this->Flash->error(__('Brak NIP firmy'));
            $this->redirect(['action' => 'index']);
            return;
        }

        $contract = trim((string)$this->request->getQuery('contract', ''));
        $source   = trim((string)$this->request->getQuery('source', ''));
        $search   = trim((string)$this->request->getQuery('q', ''));

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $query = $SpeedOrders->find()
            ->where(['SpeedOrders.company_nip' => $companyNip])
            ->orderByDesc('SpeedOrders.date_delivery')
            ->limit(500);

        if ($contract !== '') $query->where(['SpeedOrders.contract' => $contract]);
        if ($source !== '' && in_array($source, ['speed', 'manual'], true)) {
            $query->where(['SpeedOrders.source' => $source]);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'     => $like,
                'SpeedOrders.buyer_name LIKE' => $like,
                'SpeedOrders.title1 LIKE'     => $like,
            ]]);
        }

        $orders = $query->all();

        // Grupuj po nordlogis_status
        $columns = [
            1 => ['label' => 'Przyjęte',     'color' => 'primary',   'items' => []],
            2 => ['label' => 'Zaplanowane',  'color' => 'info',      'items' => []],
            3 => ['label' => 'Załadowane',   'color' => 'warning',   'items' => []],
            4 => ['label' => 'Zrealizowane', 'color' => 'success',   'items' => []],
            5 => ['label' => 'Zafakturowane','color' => 'secondary', 'items' => []],
        ];
        foreach ($orders as $o) {
            $s = (int)($o->nordlogis_status ?? 1);
            if (!isset($columns[$s])) $s = 1;
            $columns[$s]['items'][] = $o;
        }

        $contractsList = [];
        try {
            $rows = $SpeedOrders->find()
                ->select(['contract'])
                ->where(['SpeedOrders.company_nip' => $companyNip, 'SpeedOrders.contract IS NOT' => null])
                ->group('contract')
                ->orderByAsc('contract')
                ->disableHydration()
                ->all();
            foreach ($rows as $r) if (!empty($r['contract'])) $contractsList[] = $r['contract'];
        } catch (\Throwable) {}

        $this->set(compact('columns', 'contract', 'source', 'search', 'contractsList'));
    }

    /**
     * POST /zlecenia/kanban/przenies/{id} - drag-drop zmiana nordlogis_status.
     * Body: to (int 1..5)
     */
    public function kanbanMove(int $id): void
    {
        $this->request->allowMethod(['post']);
        $to = (int)$this->request->getData('to');
        if ($to < 1 || $to > 5) {
            $this->jsonResp(['ok' => false, 'error' => 'Bledny status']);
            return;
        }

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        try {
            $order = $SpeedOrders->get($id);
            $companyNip = $this->currentCompanyNip();
            if ($order->company_nip !== $companyNip) {
                $this->jsonResp(['ok' => false, 'error' => 'Nie masz uprawnien']);
                return;
            }
            $order->nordlogis_status = $to;
            $SpeedOrders->save($order);
            $this->jsonResp(['ok' => true, 'nordlogis_status' => $to]);
        } catch (\Throwable $e) {
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // EXPORT XML SpreadsheetML (Excel 2003+)
    // =========================================================================

    /**
     * GET /zlecenia/eksport-xlsx?[q,status,contract,source,delivery_from,delivery_to]
     * Zwraca plik .xls (SpreadsheetML XML 2003) - Excel otwiera bezposrednio bez
     * konwersji, LibreOffice tez wspiera. Bez zewnetrznej biblioteki (PhpSpreadsheet).
     *
     * Wybor pol: pelny snapshot zlecenia dla analizy w Excelu.
     */
    public function exportXlsx(): void
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;

        $companyNip = $this->currentCompanyNip();
        if (!$companyNip) {
            $this->response = $this->response->withStatus(400)->withStringBody('Brak NIP');
            return;
        }

        // Filtry - te same co index()
        $search   = trim((string)$this->request->getQuery('q', ''));
        $status   = (string)$this->request->getQuery('status', '');
        $contract = trim((string)$this->request->getQuery('contract', ''));
        $source   = trim((string)$this->request->getQuery('source', ''));
        $delFrom  = (string)$this->request->getQuery('delivery_from', '');
        $delTo    = (string)$this->request->getQuery('delivery_to', '');
        $currency = strtoupper((string)$this->request->getQuery('currency', ''));

        $q = $this->fetchTable('SpeedOrders')->find()
            ->where(['company_nip' => $companyNip])
            ->orderByDesc('date_delivery');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(['OR' => [
                'SpeedOrders.symbol LIKE'     => $like,
                'SpeedOrders.buyer_name LIKE' => $like,
                'SpeedOrders.buyer_nip LIKE'  => $like,
                'SpeedOrders.title1 LIKE'     => $like,
            ]]);
        }
        if ($status !== '' && ctype_digit($status)) $q->where(['nordlogis_status' => (int)$status]);
        if ($contract !== '') $q->where(['contract' => $contract]);
        if ($source !== '' && in_array($source, ['speed','manual'], true)) $q->where(['source' => $source]);
        if ($delFrom !== '') $q->where(['date_delivery >=' => $delFrom]);
        if ($delTo   !== '') $q->where(['date_delivery <=' => $delTo . ' 23:59:59']);
        if ($currency !== '') $q->where(['currency' => $currency]);

        $orders = $q->limit(5000)->all();

        // Header - pelny snapshot
        $headers = [
            'Symbol', 'Data dok.', 'Kontrakt', 'Zrodlo',
            'NIP', 'Klient', 'Ulica', 'Kod', 'Miasto', 'Kraj', 'Email',
            'Zaladunek: kraj', 'Zaladunek: kod', 'Zaladunek: miasto', 'Zaladunek: data',
            'Rozladunek: kraj', 'Rozladunek: miasto', 'Rozladunek: data',
            'Nr ref. klienta', 'Opis ladunku', 'Cargo type', 'Transport type',
            'Waga (kg)', 'Palety', 'ADR', 'INCOTERMS',
            'Kierowca', 'Pojazd', 'Przewoznik',
            'Waluta', 'Netto', 'VAT', 'Brutto', 'Kurs', 'Warunki platnosci',
            'Status Nordlogis', 'Status Speed', 'Approval',
            'POL', 'POD', 'FK', 'FS', 'Kompletne',
            'Nick wystawil', 'Utworzono',
        ];

        $statusLabels = [1 => 'Przyjete', 2 => 'Zaplanowane', 3 => 'Zaladowane', 4 => 'Zrealizowane', 5 => 'Zafakturowane'];

        // Rows
        $rows = [];
        foreach ($orders as $o) {
            $rows[] = [
                $o->symbol,
                $o->date_doc?->format('Y-m-d'),
                $o->contract,
                $o->source,
                $o->buyer_nip, $o->buyer_name, $o->buyer_street, $o->buyer_postal_code,
                $o->buyer_city, $o->buyer_country, $o->buyer_email,
                $o->load_country, $o->load_postal_code, $o->load_city,
                $o->date_deadline?->format('Y-m-d H:i'),
                $o->unload_country, $o->unload_city, $o->date_delivery?->format('Y-m-d H:i'),
                $o->title1, $o->title2, $o->cargo_type, $o->transport_type,
                $o->cargo_weight_kg, $o->cargo_pallets, $o->adr_class, $o->incoterms,
                $o->driver, $o->vehicle_reg, $o->carrier,
                $o->currency, (float)$o->netto, (float)$o->vat, (float)$o->brutto,
                (float)$o->exchange_rate, $o->payment_terms,
                $statusLabels[(int)$o->nordlogis_status] ?? $o->nordlogis_status,
                $o->status, $o->approval_status,
                $o->pol_at?->format('Y-m-d H:i'), $o->pod_at?->format('Y-m-d H:i'),
                $o->fk_at?->format('Y-m-d H:i'), $o->fs_at?->format('Y-m-d H:i'),
                (bool)$o->is_complete ? 'TAK' : 'NIE',
                $o->nick_created,
                $o->created?->format('Y-m-d H:i'),
            ];
        }

        $xml = $this->buildSpreadsheetXml('Zlecenia', $headers, $rows);
        $filename = 'zlecenia-' . date('Y-m-d-His') . '.xls';

        $this->response = $this->response
            ->withType('application/vnd.ms-excel')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($xml);
    }

    /**
     * Builder SpreadsheetML XML (Excel 2003+ / LibreOffice).
     */
    private function buildSpreadsheetXml(string $sheetName, array $headers, array $rows): string
    {
        $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cell = function ($value) use ($esc) {
            if ($value === null || $value === '') {
                return '<Cell/>';
            }
            if (is_bool($value)) {
                return '<Cell><Data ss:Type="Boolean">' . ($value ? '1' : '0') . '</Data></Cell>';
            }
            if (is_int($value) || is_float($value)) {
                return '<Cell><Data ss:Type="Number">' . $value . '</Data></Cell>';
            }
            return '<Cell><Data ss:Type="String">' . $esc((string)$value) . '</Data></Cell>';
        };

        $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $out .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $out .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $out .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $out .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $out .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        // Style: nagłówek pogrubiony
        $out .= '<Styles>';
        $out .= '<Style ss:ID="hdr"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0d6efd" ss:Pattern="Solid"/></Style>';
        $out .= '</Styles>';

        $out .= '<Worksheet ss:Name="' . $esc($sheetName) . '"><Table>';

        // Header row
        $out .= '<Row ss:StyleID="hdr">';
        foreach ($headers as $h) {
            $out .= '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . $esc($h) . '</Data></Cell>';
        }
        $out .= '</Row>';

        foreach ($rows as $row) {
            $out .= '<Row>';
            foreach ($row as $v) $out .= $cell($v);
            $out .= '</Row>';
        }

        $out .= '</Table></Worksheet></Workbook>';
        return $out;
    }

    // =========================================================================
    // BATCH IMPORT z CSV
    // =========================================================================

    /**
     * Formularz importu CSV + POST przetwarzania.
     * Kolumny CSV (wymagane): buyer_name, buyer_nip, load_country, load_city,
     *                          unload_country, unload_city, netto, currency
     * Opcjonalne: buyer_email, buyer_street, buyer_postal_code, buyer_city,
     *             load_postal_code, unload_name, date_deadline, date_delivery,
     *             title1, title2, cargo_type, driver, vehicle_reg,
     *             payment_terms, notes, contract, cargo_weight_kg, cargo_pallets,
     *             adr_class, incoterms
     */
    public function batchImport(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $companyNip  = $this->currentCompanyNip();
        $companyName = $this->currentCompanyName();

        $preview = null;
        $errors = [];
        $importedCount = 0;
        $errorRows = [];

        if ($this->request->is('post')) {
            $upload = $this->request->getUploadedFile('csv');
            $isConfirm = (bool)$this->request->getData('confirm');
            $csvText = (string)$this->request->getData('csv_text');

            $rows = [];
            if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
                if ($upload->getSize() > 5 * 1024 * 1024) {
                    $errors[] = 'Plik za duzy (max 5 MB)';
                } else {
                    $content = (string)$upload->getStream()->getContents();
                    $rows = $this->parseCsv($content);
                }
            } elseif ($isConfirm && $csvText !== '') {
                $rows = $this->parseCsv($csvText);
            }

            if (empty($errors) && empty($rows)) {
                $errors[] = 'CSV jest pusty lub niepoprawny';
            }

            if (!empty($rows)) {
                // Walidacja + preview lub zapis
                $SpeedOrders = $this->fetchTable('SpeedOrders');
                foreach ($rows as $idx => $r) {
                    // Wymagane pola
                    $missing = [];
                    foreach (['buyer_name', 'load_city', 'unload_city', 'netto'] as $req) {
                        if (empty(trim((string)($r[$req] ?? '')))) $missing[] = $req;
                    }
                    if (!empty($missing)) {
                        $errorRows[] = ['row' => $idx + 2, 'error' => 'Brak: ' . implode(', ', $missing), 'data' => $r];
                        continue;
                    }
                    if (!$isConfirm) continue; // preview - nie zapisujemy

                    // Zapis
                    try {
                        $data = $this->prepareManualOrderData($r, $companyNip, $companyName);
                        $order = $SpeedOrders->newEntity($data);
                        if ($SpeedOrders->save($order)) {
                            $importedCount++;
                        } else {
                            $errorRows[] = ['row' => $idx + 2, 'error' => 'Walidacja: ' . json_encode($order->getErrors()), 'data' => $r];
                        }
                    } catch (\Throwable $e) {
                        $errorRows[] = ['row' => $idx + 2, 'error' => $e->getMessage(), 'data' => $r];
                    }
                }

                if ($isConfirm) {
                    $msg = $importedCount . ' zleceń zaimportowanych';
                    if (!empty($errorRows)) $msg .= ', ' . count($errorRows) . ' bledow';
                    $this->Flash->success($msg);
                    if ($importedCount > 0) {
                        $this->redirect(['action' => 'index', '?' => ['source' => 'manual', 'sort' => 'date_doc', 'direction' => 'desc']]);
                        return;
                    }
                } else {
                    // Preview - pokaz co bedzie zapisane + carry CSV do submit z confirm=1
                    $preview = $rows;
                    $this->set('csvText', $content ?? $csvText);
                }
            }
        }

        $this->set(compact('preview', 'errors', 'errorRows', 'importedCount'));
    }

    /**
     * Parser CSV: obsluguje separatory , ; \t; encoding UTF-8 / Win-1250;
     * pierwsza linia = header, kolejne = dane. Zwraca array assoc rows.
     */
    private function parseCsv(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        $content = ltrim($content, "\xEF\xBB\xBF"); // strip BOM
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1250');
        }
        $lines = explode("\n", $content);
        if (count($lines) < 2) return [];

        // Detect separator
        $firstLine = $lines[0];
        $sep = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) $sep = ';';
        elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) $sep = "\t";

        $header = str_getcsv($firstLine, $sep);
        $header = array_map(function ($h) { return trim(strtolower(trim($h)), '"'); }, $header);

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;
            $cells = str_getcsv($line, $sep);
            if (count($cells) < count($header)) {
                $cells = array_pad($cells, count($header), '');
            }
            $row = [];
            foreach ($header as $j => $col) {
                $row[$col] = isset($cells[$j]) ? trim((string)$cells[$j]) : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Pobierz template CSV dla batch importu.
     */
    public function batchImportTemplate(): void
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;

        $header = [
            'buyer_nip', 'buyer_name', 'buyer_email', 'buyer_street', 'buyer_postal_code',
            'buyer_city', 'buyer_country',
            'load_country', 'load_postal_code', 'load_city',
            'unload_country', 'unload_city', 'unload_name',
            'date_deadline', 'date_delivery',
            'title1', 'title2', 'cargo_type', 'transport_type',
            'driver', 'vehicle_reg', 'carrier',
            'contract', 'currency', 'netto', 'payment_terms', 'notes',
            'cargo_weight_kg', 'cargo_pallets', 'adr_class', 'incoterms',
        ];
        $example = [
            '1234567890', 'HB RTS Sp. z o.o.', 'kontakt@hbrts.pl', 'Wielicka 22', '30-552',
            'Krakow', 'PL',
            'DE', '20095', 'Hamburg',
            'NL', 'Nijmegen', '',
            '2026-08-10 08:00', '2026-08-11 16:00',
            'REF-12345', 'Palety EUR x 33', 'FTL', 'plandeka',
            'Jan Kowalski', 'GD1234A', '',
            'OWN 1', 'EUR', '1200.00', 'Przelew 30 dni', 'Delikatny towar',
            '18000', '33', '', 'DAP',
        ];

        $csv  = implode(';', $header) . "\n";
        $csv .= implode(';', array_map(function ($v) {
            return str_contains($v, ';') ? '"' . str_replace('"', '""', $v) . '"' : $v;
        }, $example)) . "\n";

        $this->response = $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="szablon-import-zlecen.csv"')
            ->withStringBody($csv);
    }

    // =========================================================================
    // RECZNE TWORZENIE ZLECEN (source='manual')
    // =========================================================================

    /**
     * Formularz nowego zlecenia recznego.
     * GET: pokazuje formularz z automatycznym numerem M-NNNN/MM/YYYY.
     * POST: zapisuje zlecenie.
     */
    public function add(): void
    {
        $this->request->allowMethod(['get', 'post']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->newEmptyEntity();

        $companyNip  = $this->currentCompanyNip();
        $companyName = $this->currentCompanyName();

        if ($this->request->is('post')) {
            $data = $this->prepareManualOrderData($this->request->getData(), $companyNip, $companyName);
            $order = $SpeedOrders->patchEntity($order, $data, ['associated' => ['SpeedOrderStops', 'SpeedOrderCargoItems']]);
            if ($SpeedOrders->save($order)) {
                $this->Flash->success(__('Zlecenie {0} zostało utworzone.', $order->symbol));

                // Opcjonalna wysylka email do klienta po zapisie
                if ($this->request->getData('send_email') && !empty($order->buyer_email)) {
                    try {
                        $this->sendOrderEmail($order);
                        $this->Flash->info(__('Email z potwierdzeniem zlecenia wysłany do {0}.', $order->buyer_email));
                    } catch (\Throwable $e) {
                        $this->Flash->warning(__('Zlecenie zapisane, ale email nie został wysłany: {0}', $e->getMessage()));
                    }
                }

                // Batch mode: "Zapisz i dodaj kolejne" -> wracaj na formularz
                if ($this->request->getData('save_and_new')) {
                    $this->redirect(['action' => 'add']);
                    return;
                }
                // "Zapisz i wystaw fakture" -> Invoices::add z prefillem ze zlecenia
                if ($this->request->getData('save_and_invoice')) {
                    $invType = strtoupper((string)$order->currency) === 'PLN' ? 'vat' : 'currency';
                    $this->redirect([
                        'controller' => 'Invoices',
                        'action'     => 'add',
                        '?' => ['type' => $invType, 'from_order_id' => $order->id],
                    ]);
                    return;
                }
                // "Zapisz i dodaj zalacznik CMR" -> view z fokusem na attachments
                if ($this->request->getData('save_and_attach')) {
                    $this->redirect(['action' => 'view', $order->id, '?' => ['focus' => 'attachments']]);
                    return;
                }
                $this->redirect(['action' => 'view', $order->id]);
                return;
            }
            $this->Flash->error(__('Nie udało się zapisać zlecenia. Sprawdź błędy w formularzu.'));
        } else {
            // Prefill nowego rekordu
            [$symbol, $seq, $rok, $mc] = $this->nextManualSymbol($companyNip, new Date());
            $defaults = [
                'source'       => 'manual',
                'symbol'       => $symbol,
                'manual_seq'   => $seq,
                'rok'          => $rok,
                'mc'           => $mc,
                'ozn'          => 'M',
                'company_nip'  => $companyNip,
                'company_name' => $companyName,
                'date_doc'     => (new Date())->format('Y-m-d'),
                'currency'     => 'PLN',
                'status'       => 1,
                'nordlogis_status' => 1,
            ];

            // Duplikat: ?dup={id} -> prefill z istniejacego zlecenia (bez numeru i dat)
            $dupId = (int)$this->request->getQuery('dup');
            if ($dupId > 0) {
                $src = $SpeedOrders->find()->where(['id' => $dupId])->first();
                if ($src) {
                    $copyFields = [
                        'contract', 'our_ref',
                        'buyer_nip', 'buyer_name', 'buyer_street', 'buyer_postal_code',
                        'buyer_city', 'buyer_country', 'buyer_email',
                        'load_country', 'load_postal_code', 'load_city',
                        'unload_country', 'unload_city', 'unload_name',
                        'title1', 'title2', 'cargo_type', 'transport_type', 'notes',
                        'driver', 'vehicle_reg', 'carrier',
                        'currency', 'netto', 'vat', 'brutto', 'exchange_rate', 'payment_terms',
                    ];
                    foreach ($copyFields as $f) {
                        if (!empty($src->{$f}) || $src->{$f} === 0 || $src->{$f} === '0') {
                            $defaults[$f] = $src->{$f};
                        }
                    }
                    $this->Flash->info(__('Załadowano dane ze zlecenia {0}. Zmień co potrzebne i zapisz.', $src->symbol));
                }
            }

            $order = $SpeedOrders->newEntity($defaults);
        }

        $this->set(compact('order'));
        $this->set('isEdit', false);
        $this->set('drivers',       $this->loadDriversForSelect());
        $this->set('vehicles',      $this->loadVehiclesForSelect());
        $this->set('recentInMonth', $this->loadRecentManualInMonth($companyNip));
        $this->set('hereApiKey',    (string)\Cake\Core\Configure::read('Here.apiKey'));
        $this->render('add');
    }

    /**
     * Edycja zlecenia manualnego. Zlecenia Speed sa readonly (sync by je nadpisal).
     */
    public function edit(int $id): void
    {
        $this->request->allowMethod(['get', 'post']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->find()
            ->where(['id' => $id])
            ->contain(['SpeedOrderStops', 'SpeedOrderCargoItems'])
            ->first();
        if (!$order) {
            throw new NotFoundException(__('Zlecenie nie istnieje.'));
        }
        if ($order->source !== 'manual') {
            throw new BadRequestException(__('Zlecenia synchronizowane ze Speed nie mogą być edytowane tutaj.'));
        }

        $companyNip  = $this->currentCompanyNip();
        $companyName = $this->currentCompanyName();

        if ($this->request->is('post')) {
            $data = $this->prepareManualOrderData($this->request->getData(), $companyNip, $companyName);
            // Zablokuj zmiane symbolu i manual_seq przy edycji (numer sie nie zmienia).
            unset($data['symbol'], $data['manual_seq'], $data['rok'], $data['mc'], $data['source']);
            $order = $SpeedOrders->patchEntity($order, $data, ['associated' => ['SpeedOrderStops', 'SpeedOrderCargoItems']]);
            if ($SpeedOrders->save($order)) {
                $this->Flash->success(__('Zlecenie {0} zostało zaktualizowane.', $order->symbol));
                $this->redirect(['action' => 'view', $order->id]);
                return;
            }
            $this->Flash->error(__('Nie udało się zapisać zmian.'));
        }

        $this->set(compact('order'));
        $this->set('isEdit', true);
        $this->set('drivers',  $this->loadDriversForSelect());
        $this->set('vehicles', $this->loadVehiclesForSelect());
        $this->set('hereApiKey', (string)\Cake\Core\Configure::read('Here.apiKey'));
        $this->render('add');
    }

    /**
     * Usuwanie zlecenia manualnego. Zlecenia Speed nie moga byc usuwane
     * (integralnosc z historia sync + fakturami). Zlecenia z podpieta
     * faktura tez nie (blokada dla bezpieczenstwa ksiegowego).
     */
    public function delete(int $id): void
    {
        $this->request->allowMethod(['post']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->get($id, ['contain' => ['AllInvoices']]);

        if ($order->source !== 'manual') {
            $this->Flash->error(__('Zleceń ze Speed nie można usuwać.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }
        if (!empty($order->invoice_id) || !empty($order->invoices)) {
            $this->Flash->error(__('Nie można usunąć zlecenia z podpiętą fakturą.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        if ($SpeedOrders->delete($order)) {
            $this->Flash->success(__('Zlecenie {0} zostało usunięte.', $order->symbol));
        } else {
            $this->Flash->error(__('Nie udało się usunąć zlecenia.'));
        }
        $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX: sprawdz limit kredytowy klienta po NIP.
     * GET /zlecenia/kredyt-klienta?nip=xxx
     * Zwraca:
     *  - limit z contractor_credit_limits (jesli ustawiony)
     *  - saldo nieoplaconych faktur (Invoices.paymentstate != 'paid') przeliczone na PLN
     *  - status: ok / warning / exceeded / blocked
     */
    public function creditCheckJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $nipRaw = trim((string)$this->request->getQuery('nip', ''));
        $nip = preg_replace('/\D+/', '', $nipRaw);
        if (strlen($nip) < 5) {
            $this->jsonResp(['ok' => true, 'found' => false]);
            return;
        }

        // Sprobuj znalezc limit (matching po NIP - lub ostatnie 10 cyfr dla PL)
        $limit = null;
        try {
            $limit = $this->fetchTable('ContractorCreditLimits')->find()
                ->where(['company_id' => $companyId, 'contractor_nip LIKE' => '%' . $nip])
                ->orderByDesc('modified')
                ->first();
        } catch (\Throwable) {}

        // Oblicz saldo nieoplaconych faktur (matching InvoiceContractors.nip LIKE %nip)
        $unpaidPln = 0.0;
        $unpaidCount = 0;
        $overdueCount = 0;
        try {
            $Invoices = $this->fetchTable('Invoices');
            $rows = $Invoices->find()
                ->select([
                    'Invoices.remaining',
                    'Invoices.currency',
                    'Invoices.exchange_rate',
                    'Invoices.paymentdate',
                    'Invoices.paymentstate',
                ])
                ->matching('InvoiceContractors', function ($q) use ($nip) {
                    return $q->where(['InvoiceContractors.nip LIKE' => '%' . $nip]);
                })
                ->where([
                    'Invoices.paymentstate IN' => ['unpaid', 'partial'],
                    'Invoices.company_id' => $companyId,
                ])
                ->disableHydration()
                ->all();
            foreach ($rows as $r) {
                $rem = (float)($r['remaining'] ?? 0);
                if ($rem <= 0) continue;
                $cur = strtoupper((string)($r['currency'] ?? 'PLN'));
                $rate = (float)($r['exchange_rate'] ?? 1);
                $pln = $cur === 'PLN' ? $rem : $rem * $rate;
                $unpaidPln += $pln;
                $unpaidCount++;
                $pd = $r['paymentdate'] ?? null;
                if ($pd instanceof \DateTimeInterface && $pd < new \DateTime('today')) {
                    $overdueCount++;
                }
            }
        } catch (\Throwable) {}

        $unpaidPln = round($unpaidPln, 2);

        // Status
        $status = 'ok';
        $pct = null;
        if ($limit) {
            $lim = (float)$limit->credit_limit_pln;
            if ($lim > 0) {
                $pct = round(($unpaidPln / $lim) * 100, 1);
                if ($limit->is_blocked) {
                    $status = 'blocked';
                } elseif ($pct >= 100) {
                    $status = 'exceeded';
                } elseif ($pct >= (int)$limit->warning_threshold_pct) {
                    $status = 'warning';
                }
            }
        } elseif ($overdueCount > 0) {
            // Bez limitu - sam fakt zaleglych faktur to info
            $status = 'has_overdue';
        }

        $this->jsonResp([
            'ok'    => true,
            'found' => true,
            'has_limit'      => (bool)$limit,
            'credit_limit'   => $limit ? (float)$limit->credit_limit_pln : null,
            'warning_pct'    => $limit ? (int)$limit->warning_threshold_pct : null,
            'is_blocked'     => $limit ? (bool)$limit->is_blocked : false,
            'block_reason'   => $limit?->block_reason,
            'unpaid_pln'     => $unpaidPln,
            'unpaid_count'   => $unpaidCount,
            'overdue_count'  => $overdueCount,
            'used_pct'       => $pct,
            'available_pln'  => $limit ? round(max(0, (float)$limit->credit_limit_pln - $unpaidPln), 2) : null,
            'status'         => $status,
        ]);
    }

    /**
     * AJAX: mini-profil kontrahenta - statystyki wspolpracy.
     * GET /zlecenia/profil-klienta?nip=xxx
     * Zwraca: liczba zlecen ostatnie 12 mies, avg netto, top trasa,
     * ostatnie 3 zlecenia, srednia zwloki platnosci (DSO z faktur).
     */
    public function buyerProfileJson(): void
    {
        $this->request->allowMethod(['get']);
        $nipRaw = trim((string)$this->request->getQuery('nip', ''));
        $nip = preg_replace('/\D+/', '', $nipRaw);
        if (strlen($nip) < 5) {
            $this->jsonResp(['ok' => true, 'found' => false]);
            return;
        }

        $companyNip = $this->currentCompanyNip();
        $SpeedOrders = $this->fetchTable('SpeedOrders');

        $cutoff = (new \DateTime('-12 months'))->format('Y-m-d');

        // Podstawowe agregacje
        $stats = $SpeedOrders->find()
            ->select([
                'cnt'      => 'COUNT(*)',
                'avg_net'  => 'AVG(SpeedOrders.netto)',
                'sum_net'  => 'SUM(SpeedOrders.netto)',
                'max_date' => 'MAX(SpeedOrders.date_doc)',
            ])
            ->where([
                'SpeedOrders.company_nip' => $companyNip,
                'SpeedOrders.buyer_nip LIKE' => '%' . $nip,
                'SpeedOrders.date_doc >=' => $cutoff,
            ])
            ->disableHydration()
            ->first();

        if (!$stats || (int)($stats['cnt'] ?? 0) === 0) {
            $this->jsonResp(['ok' => true, 'found' => false]);
            return;
        }

        // Top trasa (najczestsza)
        $topRoute = $SpeedOrders->find()
            ->select([
                'load_city'   => 'SpeedOrders.load_city',
                'unload_city' => 'SpeedOrders.unload_city',
                'cnt'         => 'COUNT(*)',
            ])
            ->where([
                'SpeedOrders.company_nip' => $companyNip,
                'SpeedOrders.buyer_nip LIKE' => '%' . $nip,
                'SpeedOrders.date_doc >=' => $cutoff,
                'SpeedOrders.load_city IS NOT' => null,
                'SpeedOrders.unload_city IS NOT' => null,
            ])
            ->group(['SpeedOrders.load_city', 'SpeedOrders.unload_city'])
            ->orderByDesc('cnt')
            ->limit(1)
            ->disableHydration()
            ->first();

        // Ostatnie 3 zlecenia
        $recent = $SpeedOrders->find()
            ->select(['id', 'symbol', 'date_doc', 'load_city', 'unload_city', 'netto', 'currency'])
            ->where([
                'SpeedOrders.company_nip' => $companyNip,
                'SpeedOrders.buyer_nip LIKE' => '%' . $nip,
            ])
            ->orderByDesc('date_doc')
            ->orderByDesc('id')
            ->limit(3)
            ->disableHydration()
            ->all()
            ->toList();

        // DSO z faktur: srednia (paymentdate - issueddate) dla oplaconych
        // (zwloka moze byc ujemna gdy oplacone przed terminem)
        $dso = null;
        try {
            $Invoices = $this->fetchTable('Invoices');
            $dsoRow = $Invoices->find()
                ->select(['avg_days' => 'AVG(DATEDIFF(Invoices.paymentdate, Invoices.issueddate))'])
                ->matching('InvoiceContractors', function ($q) use ($nip) {
                    return $q->where(['InvoiceContractors.nip LIKE' => '%' . $nip]);
                })
                ->where([
                    'Invoices.paymentstate' => 'paid',
                    'Invoices.paymentdate IS NOT' => null,
                    'Invoices.issueddate IS NOT' => null,
                ])
                ->disableHydration()
                ->first();
            if ($dsoRow && $dsoRow['avg_days'] !== null) {
                $dso = round((float)$dsoRow['avg_days'], 1);
            }
        } catch (\Throwable) {}

        $this->jsonResp([
            'ok'    => true,
            'found' => true,
            'stats' => [
                'orders_12m'     => (int)$stats['cnt'],
                'avg_net'        => round((float)$stats['avg_net'], 2),
                'sum_net'        => round((float)$stats['sum_net'], 2),
                'last_order'     => $stats['max_date'],
                'top_route'      => $topRoute ? ($topRoute['load_city'] . ' -> ' . $topRoute['unload_city']) : null,
                'top_route_cnt'  => $topRoute ? (int)$topRoute['cnt'] : 0,
                'dso_days'       => $dso,
            ],
            'recent' => array_map(function ($r) {
                return [
                    'id'          => $r['id'],
                    'symbol'      => $r['symbol'],
                    'date_doc'    => $r['date_doc'] instanceof \DateTimeInterface ? $r['date_doc']->format('Y-m-d') : $r['date_doc'],
                    'route'       => trim(($r['load_city'] ?? '') . ' -> ' . ($r['unload_city'] ?? '')),
                    'amount'      => round((float)$r['netto'], 2),
                    'currency'    => $r['currency'],
                ];
            }, $recent),
        ]);
    }

    /**
     * AJAX: ostatnie zlecenie dla danego NIP klienta.
     * Uzywane w formularzu /zlecenia/dodaj do 'prefill z ostatniego zlecenia'.
     * Zwraca dane trasy + finansow + kontraktu (bez dat i numeru).
     */
    public function lastForBuyerJson(): void
    {
        $this->request->allowMethod(['get']);
        $nip = preg_replace('/\D+/', '', (string)$this->request->getQuery('nip', ''));
        if (strlen($nip) < 5) {
            $this->jsonResp(['ok' => true, 'found' => false]);
            return;
        }

        $companyNip = $this->currentCompanyNip();
        $SpeedOrders = $this->fetchTable('SpeedOrders');

        $order = $SpeedOrders->find()
            ->where([
                'company_nip'   => $companyNip,
                'buyer_nip LIKE' => '%' . $nip,
            ])
            ->orderByDesc('date_doc')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            $this->jsonResp(['ok' => true, 'found' => false]);
            return;
        }

        $this->jsonResp([
            'ok'    => true,
            'found' => true,
            'order' => [
                'symbol'         => $order->symbol,
                'date_doc'       => $order->date_doc?->format('Y-m-d'),
                'source'         => $order->source,
                'contract'       => $order->contract,
                'load_country'   => $order->load_country,
                'load_postal_code' => $order->load_postal_code,
                'load_city'      => $order->load_city,
                'unload_country' => $order->unload_country,
                'unload_city'    => $order->unload_city,
                'unload_name'    => $order->unload_name,
                'title2'         => $order->title2,
                'cargo_type'     => $order->cargo_type,
                'transport_type' => $order->transport_type,
                'currency'       => $order->currency,
                'netto'          => (float)$order->netto,
                'payment_terms'  => $order->payment_terms,
            ],
        ]);
    }

    /**
     * AJAX: lista zapisanych planow tras (route_plans) do wyboru w modalu
     * "Zaladuj z planera".
     * GET /zlecenia/plany-tras?limit=20&status=all|draft|accepted
     */
    public function routePlansJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $limit  = min(50, max(1, (int)$this->request->getQuery('limit', 20)));
        $status = trim((string)$this->request->getQuery('status', ''));

        $where = ['company_id' => $companyId, 'speed_order_id IS' => null];
        if ($status && $status !== 'all') {
            $where['status'] = $status;
        }

        $rows = [];
        try {
            $rows = $this->fetchTable('RoutePlans')->find()
                ->select(['id', 'name', 'status', 'waypoints_json', 'distance_km', 'duration_min',
                          'suggested_price', 'accepted_price', 'currency', 'planned_start_at', 'planned_end_at',
                          'contractor_id', 'created'])
                ->where($where)
                ->orderByDesc('created')
                ->limit($limit)
                ->contain(['Contractors' => function ($q) {
                    return $q->select(['id', 'name', 'nip']);
                }])
                ->all()
                ->toList();
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('routePlansJson: ' . $e->getMessage());
        }

        $out = array_map(function ($p) {
            $wp = $p->waypoints_json ? json_decode($p->waypoints_json, true) : [];
            $from = $wp[0]['city'] ?? $wp[0]['name'] ?? '?';
            $to   = end($wp);
            $to   = $to ? ($to['city'] ?? $to['name'] ?? '?') : '?';
            return [
                'id'              => (string)$p->id,
                'name'            => $p->name,
                'status'          => $p->status,
                'route'           => $from . ' → ' . $to,
                'distance_km'     => $p->distance_km !== null ? (float)$p->distance_km : null,
                'duration_min'    => $p->duration_min,
                'suggested_price' => $p->suggested_price !== null ? (float)$p->suggested_price : null,
                'accepted_price'  => $p->accepted_price !== null ? (float)$p->accepted_price : null,
                'currency'        => $p->currency,
                'planned_start_at' => $p->planned_start_at?->format('Y-m-d H:i'),
                'planned_end_at'  => $p->planned_end_at?->format('Y-m-d H:i'),
                'contractor_name' => $p->contractor?->name,
                'contractor_nip'  => $p->contractor?->nip,
                'waypoints'       => $wp,
                'created'         => $p->created?->format('Y-m-d H:i'),
            ];
        }, $rows);

        $this->jsonResp(['ok' => true, 'plans' => $out]);
    }

    /**
     * AJAX: kabotaz check dla pojazdu (UE 1072/2009).
     * GET /zlecenia/kabotaz?vehicle_plate=X&load_country=PL&unload_country=DE&date=YYYY-MM-DD
     *
     * Regula: max 3 operacje kabotazu w oknie 7 dni od miedzynarodowego wjazdu
     * do panstwa, do momentu wyjazdu z niego.
     *
     * Analizujemy istniejace speed_orders dla vehicle_reg z ostatnich 14 dni:
     *  - Miedzynarodowy wjazd = load_country != unload_country i unload_country == check_country
     *  - Kabotaz              = load_country == unload_country == check_country
     *  - Wyjazd z kraju       = load_country == check_country i unload_country != check_country
     */
    public function cabotageCheckJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $plate       = trim((string)$this->request->getQuery('vehicle_plate', ''));
        $loadCountry = strtoupper(trim((string)$this->request->getQuery('load_country', '')));
        $unloadCountry = strtoupper(trim((string)$this->request->getQuery('unload_country', '')));
        $dateStr     = (string)$this->request->getQuery('date', '');

        // Kabotaz mozliwy tylko gdy load == unload (transport wewnatrz kraju)
        if ($plate === '' || $loadCountry === '' || $loadCountry !== $unloadCountry) {
            $this->jsonResp(['ok' => true, 'applies' => false]);
            return;
        }

        try {
            $checkDate = $dateStr !== '' ? new \DateTime($dateStr) : new \DateTime();
        } catch (\Throwable) {
            $checkDate = new \DateTime();
        }
        $windowStart = (clone $checkDate)->modify('-14 days');

        $companyNip = $this->currentCompanyNip();
        $SpeedOrders = $this->fetchTable('SpeedOrders');

        // Historia zlecen tego pojazdu w oknie 14 dni
        $orders = $SpeedOrders->find()
            ->select(['id', 'symbol', 'date_deadline', 'date_delivery', 'load_country', 'unload_country'])
            ->where([
                'company_nip' => $companyNip,
                'vehicle_reg LIKE' => '%' . $plate . '%',
                'date_delivery >=' => $windowStart->format('Y-m-d 00:00:00'),
                'date_delivery <=' => (clone $checkDate)->modify('+1 day')->format('Y-m-d 23:59:59'),
            ])
            ->orderByAsc('date_delivery')
            ->disableHydration()
            ->all()
            ->toList();

        // Znajdz ostatni miedzynarodowy wjazd do kraju (najnowszy przed date)
        $lastEntry = null;
        $lastExit = null;
        foreach ($orders as $o) {
            $lc = strtoupper((string)($o['load_country'] ?? ''));
            $uc = strtoupper((string)($o['unload_country'] ?? ''));
            $when = $o['date_delivery'];
            if (!$when) continue;
            $whenDt = $when instanceof \DateTimeInterface ? $when : new \DateTime((string)$when);
            if ($whenDt > $checkDate) continue; // future - ignore

            if ($lc !== $loadCountry && $uc === $loadCountry) {
                $lastEntry = ['symbol' => $o['symbol'], 'date' => $whenDt->format('Y-m-d')];
            } elseif ($lc === $loadCountry && $uc !== $loadCountry) {
                $lastExit = ['symbol' => $o['symbol'], 'date' => $whenDt->format('Y-m-d')];
            }
        }

        // Jesli byl wyjazd po ostatnim wjezdzie -> pojazd opuscil kraj, kabotaz wygasl
        if ($lastEntry && $lastExit && $lastExit['date'] > $lastEntry['date']) {
            $lastEntry = null;
        }

        // Brak wjazdu -> nie ma podstawy do kabotazu
        if (!$lastEntry) {
            $this->jsonResp([
                'ok' => true,
                'applies' => true,
                'status' => 'no_entry',
                'msg' => 'Brak międzynarodowego wjazdu do kraju ' . $loadCountry . ' w ostatnich 14 dniach - kabotaż niedozwolony (UE 1072/2009).',
            ]);
            return;
        }

        // Okno 7 dni od wjazdu
        $entryDate = new \DateTime($lastEntry['date']);
        $limitDate = (clone $entryDate)->modify('+7 days');
        if ($checkDate > $limitDate) {
            $this->jsonResp([
                'ok' => true,
                'applies' => true,
                'status' => 'window_expired',
                'entry' => $lastEntry,
                'window_end' => $limitDate->format('Y-m-d'),
                'msg' => 'Okno kabotażu wygasło (7 dni po wjeździe ' . $lastEntry['date'] . ' upłynęło).',
            ]);
            return;
        }

        // Policz kabotaze w oknie
        $cabotageCount = 0;
        $cabotageOrders = [];
        foreach ($orders as $o) {
            $lc = strtoupper((string)($o['load_country'] ?? ''));
            $uc = strtoupper((string)($o['unload_country'] ?? ''));
            $when = $o['date_delivery'];
            if (!$when) continue;
            $whenDt = $when instanceof \DateTimeInterface ? $when : new \DateTime((string)$when);
            if ($whenDt <= $entryDate || $whenDt > $checkDate) continue;
            if ($lc === $loadCountry && $uc === $loadCountry) {
                $cabotageCount++;
                $cabotageOrders[] = ['symbol' => $o['symbol'], 'date' => $whenDt->format('Y-m-d')];
            }
        }

        $status = 'allowed';
        $msg = 'Kabotaż dozwolony (' . $cabotageCount . '/3 wykonane, do ' . $limitDate->format('Y-m-d') . ').';
        if ($cabotageCount >= 3) {
            $status = 'limit_exceeded';
            $msg = 'Limit 3 kabotaży w kraju ' . $loadCountry . ' WYCZERPANY. Wymagany wyjazd + nowy wjazd międzynarodowy.';
        } elseif ($cabotageCount >= 2) {
            $status = 'warning';
            $msg = 'Ostrzeżenie: ' . $cabotageCount . '/3 kabotaży wykonanych. Ostatnia dozwolona operacja.';
        }

        $this->jsonResp([
            'ok' => true,
            'applies' => true,
            'status' => $status,
            'entry' => $lastEntry,
            'window_end' => $limitDate->format('Y-m-d'),
            'count' => $cabotageCount,
            'max' => 3,
            'cabotage_orders' => $cabotageOrders,
            'msg' => $msg,
        ]);
    }

    /**
     * AJAX: sprawdz konflikty grafika dla podanego kierowcy/pojazdu i okna czasowego.
     * POST /zlecenia/conflict-check body: driver_name, vehicle_plate, start, end
     * Zwraca liste kolizji + status wolny/zajety.
     */
    public function conflictCheckJson(): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $driverName   = trim((string)$this->request->getData('driver_name', ''));
        $vehiclePlate = trim((string)$this->request->getData('vehicle_plate', ''));
        $startStr     = trim((string)$this->request->getData('start', ''));
        $endStr       = trim((string)$this->request->getData('end', ''));

        if ($startStr === '' || $endStr === '') {
            $this->jsonResp(['ok' => false, 'error' => 'Brak okna czasowego']);
            return;
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable) {
            $this->jsonResp(['ok' => false, 'error' => 'Niepoprawny format daty']);
            return;
        }

        $conflicts = [];

        // Kierowca: znajdz po full_name (LIKE) i sprawdz overlap w driver_schedules
        if ($driverName !== '') {
            try {
                $driver = $this->fetchTable('Drivers')->find()
                    ->select(['id', 'full_name'])
                    ->where(['company_id' => $companyId, 'full_name LIKE' => '%' . $driverName . '%'])
                    ->first();
                if ($driver) {
                    $rows = $this->fetchTable('DriverSchedules')->find()
                        ->select(['id', 'entry_type', 'starts_at', 'ends_at', 'speed_order_id', 'route_plan_id'])
                        ->where([
                            'company_id' => $companyId,
                            'driver_id'  => $driver->id,
                            'starts_at <'  => $end->format('Y-m-d H:i:s'),
                            'ends_at >'    => $start->format('Y-m-d H:i:s'),
                        ])
                        ->limit(10)
                        ->disableHydration()
                        ->all();
                    foreach ($rows as $r) {
                        $conflicts[] = [
                            'kind'       => 'driver',
                            'entity'     => $driver->full_name,
                            'entry_type' => $r['entry_type'],
                            'starts_at'  => $r['starts_at'],
                            'ends_at'    => $r['ends_at'],
                            'linked'     => $r['speed_order_id'] ? 'zlecenie #' . $r['speed_order_id'] : ($r['route_plan_id'] ? 'plan trasy' : null),
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        // Pojazd: znajdz po plate (LIKE) i sprawdz overlap w vehicle_schedules
        if ($vehiclePlate !== '') {
            try {
                $vehicle = $this->fetchTable('Vehicles')->find()
                    ->select(['id', 'name', 'plate'])
                    ->where(['company_id' => $companyId, 'plate LIKE' => '%' . $vehiclePlate . '%'])
                    ->first();
                if ($vehicle) {
                    $rows = $this->fetchTable('VehicleSchedules')->find()
                        ->select(['id', 'entry_type', 'starts_at', 'ends_at', 'speed_order_id', 'route_plan_id'])
                        ->where([
                            'company_id' => $companyId,
                            'vehicle_id' => $vehicle->id,
                            'starts_at <'  => $end->format('Y-m-d H:i:s'),
                            'ends_at >'    => $start->format('Y-m-d H:i:s'),
                        ])
                        ->limit(10)
                        ->disableHydration()
                        ->all();
                    foreach ($rows as $r) {
                        $conflicts[] = [
                            'kind'       => 'vehicle',
                            'entity'     => $vehicle->plate . ' (' . $vehicle->name . ')',
                            'entry_type' => $r['entry_type'],
                            'starts_at'  => $r['starts_at'],
                            'ends_at'    => $r['ends_at'],
                            'linked'     => $r['speed_order_id'] ? 'zlecenie #' . $r['speed_order_id'] : ($r['route_plan_id'] ? 'plan trasy' : null),
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        // Compliance: badania techniczne + OC pojazdu na date zaladunku
        $complianceIssues = [];
        if ($vehiclePlate !== '') {
            try {
                $vehicle = $this->fetchTable('Vehicles')->find()
                    ->select(['id', 'name', 'plate'])
                    ->where(['company_id' => $companyId, 'plate LIKE' => '%' . $vehiclePlate . '%'])
                    ->first();
                if ($vehicle) {
                    $missing = $this->fetchTable('VehicleMaintenance')
                        ->findMissingForDate($companyId, 'vehicle', (string)$vehicle->id, $start, ['technical_inspection', 'oc']);
                    if (!empty($missing)) {
                        $complianceIssues[] = [
                            'kind'    => 'vehicle_docs',
                            'entity'  => $vehicle->plate . ' (' . $vehicle->name . ')',
                            'missing' => $missing,
                            'msg'     => 'Pojazd nie ma ważnych dokumentów na dzień załadunku: ' . implode(', ', $missing),
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        // Compliance: czas pracy kierowcy w tygodniu ISO
        if ($driverName !== '') {
            try {
                $driver = $this->fetchTable('Drivers')->find()
                    ->select(['id', 'full_name'])
                    ->where(['company_id' => $companyId, 'full_name LIKE' => '%' . $driverName . '%'])
                    ->first();
                if ($driver) {
                    $weekIso  = $start->format('o') . '-W' . $start->format('W');
                    $duration = (int)(($end->getTimestamp() - $start->getTimestamp()) / 60);
                    $DTL = $this->fetchTable('DriverTimeLogs');
                    $status = $DTL->weeklyStatus((string)$driver->id, $weekIso);
                    if (!$DTL->hasBudgetInWeek((string)$driver->id, $weekIso, $duration)) {
                        $complianceIssues[] = [
                            'kind'   => 'driver_hours',
                            'entity' => $driver->full_name,
                            'msg'    => 'Kierowca przekracza budżet czasu pracy UE 561/2006 w tygodniu ' . $weekIso .
                                        ' (wykorzystane: ' . round(($status['used_min'] ?? 0) / 60, 1) . 'h, ' .
                                        'planowane: ' . round($duration / 60, 1) . 'h, ' .
                                        'pozostało: ' . round(($status['remaining_min'] ?? 0) / 60, 1) . 'h)',
                        ];
                    } elseif (!empty($status['is_at_risk'])) {
                        $complianceIssues[] = [
                            'kind'   => 'driver_hours_risk',
                            'entity' => $driver->full_name,
                            'msg'    => 'Kierowca zbliża się do limitu tygodniowego (' .
                                        round(($status['used_min'] ?? 0) / 60, 1) . 'h / 56h)',
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        // Duplikat: sprawdz czy klient (buyer_nip) ma juz zlecenie w oknie +-1 dzien
        //          z tego samego miasta zaladunku
        $duplicateHint = null;
        $buyerNip = trim((string)$this->request->getData('buyer_nip', ''));
        $loadCity = trim((string)$this->request->getData('load_city', ''));
        if ($buyerNip !== '' && $loadCity !== '') {
            try {
                $dayFrom = $start->modify('-1 day')->format('Y-m-d');
                $dayTo   = $start->modify('+1 day')->format('Y-m-d');
                $companyNip = $this->currentCompanyNip();
                $dup = $this->fetchTable('SpeedOrders')->find()
                    ->select(['id', 'symbol', 'date_doc', 'buyer_name', 'load_city', 'unload_city', 'source'])
                    ->where([
                        'company_nip'    => $companyNip,
                        'buyer_nip LIKE' => '%' . preg_replace('/\D+/', '', $buyerNip) . '%',
                        'load_city LIKE' => '%' . $loadCity . '%',
                        'date_deadline >=' => $dayFrom,
                        'date_deadline <=' => $dayTo . ' 23:59:59',
                    ])
                    ->orderByDesc('date_doc')
                    ->limit(1)
                    ->first();
                if ($dup) {
                    $duplicateHint = [
                        'id'          => $dup->id,
                        'symbol'      => $dup->symbol,
                        'date_doc'    => $dup->date_doc?->format('Y-m-d'),
                        'buyer_name'  => $dup->buyer_name,
                        'route'       => trim(($dup->load_city ?? '') . ' → ' . ($dup->unload_city ?? '')),
                        'source'      => $dup->source,
                        'msg'         => 'Znaleziono podobne zlecenie: ' . $dup->symbol . ' (' . $dup->date_doc?->format('Y-m-d') . ')',
                    ];
                }
            } catch (\Throwable) {}
        }

        $this->jsonResp([
            'ok'                 => true,
            'conflicts'          => $conflicts,
            'has_conflicts'      => !empty($conflicts),
            'compliance_issues'  => $complianceIssues,
            'has_compliance'     => !empty($complianceIssues),
            'duplicate_hint'     => $duplicateHint,
        ]);
    }

    /**
     * AJAX: znajdz WOLNE zasoby (kierowcow + pojazdy) w podanym oknie czasowym.
     * GET /zlecenia/wolne-zasoby?start=X&end=Y
     */
    public function freeResourcesJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $startStr = (string)$this->request->getQuery('start', '');
        $endStr   = (string)$this->request->getQuery('end', '');
        if ($startStr === '' || $endStr === '') {
            $this->jsonResp(['ok' => false, 'error' => 'Brak okna czasowego']);
            return;
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable) {
            $this->jsonResp(['ok' => false, 'error' => 'Niepoprawny format daty']);
            return;
        }

        $drivers = [];
        $vehicles = [];
        try {
            $DS = $this->fetchTable('DriverSchedules');
            $rows = $DS->findAvailableInWindow($companyId, $start, $end, false)->all();
            foreach ($rows as $d) {
                $drivers[] = [
                    'id'            => (string)$d->id,
                    'full_name'     => (string)$d->full_name,
                    'phone'         => (string)($d->phone ?? ''),
                    'adr_certified' => (bool)($d->adr_certified ?? false),
                ];
            }
        } catch (\Throwable) {}

        try {
            $VS = $this->fetchTable('VehicleSchedules');
            $rows = $VS->findAvailableVehiclesInWindow($companyId, $start, $end)->all();
            foreach ($rows as $v) {
                $vehicles[] = [
                    'id'    => (string)$v->id,
                    'name'  => (string)$v->name,
                    'plate' => (string)($v->plate ?? ''),
                    'type'  => (string)($v->type ?? ''),
                ];
            }
        } catch (\Throwable) {}

        $this->jsonResp([
            'ok'       => true,
            'drivers'  => $drivers,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * AJAX: AI parser emaila lub screenshotu -> structured order data.
     * POST /zlecenia/ai-parse-order body:
     *   - text: (opcjonalnie) tresc emaila / SMS / wiadomosci
     *   - image_base64: (opcjonalnie) data URL 'data:image/png;base64,XXXX'
     * Przynajmniej jedno musi byc obecne.
     *
     * Zwraca structured JSON z polami odpowiadajacymi speed_orders + confidence.
     */
    public function aiParseOrderJson(): void
    {
        $this->request->allowMethod(['post']);
        $text  = trim((string)$this->request->getData('text', ''));
        $image = trim((string)$this->request->getData('image_base64', ''));
        $pages = (array)$this->request->getData('image_pages', []);
        // Odfiltruj puste + tylko data URLs (bezpieczenstwo - nie akceptujemy external URLs)
        $pages = array_values(array_filter($pages, function ($p) {
            return is_string($p) && strncmp($p, 'data:image/', 11) === 0;
        }));

        if ($text === '' && $image === '' && empty($pages)) {
            $this->jsonResp(['ok' => false, 'error' => 'Wklej email/tekst LUB dodaj screenshot/PDF']);
            return;
        }

        $system = <<<'SYS'
Jestes asystentem AI dla firmy spedycyjnej. Analizujesz email / SMS / screenshot zapytania
o zlecenie transportowe (PL/EN/DE/UK) i wyciagasz strukturalne dane. Zwracasz WYLACZNIE
poprawny JSON o dokladnie tej strukturze:

{
  "buyer_nip": "10-cyfrowy PL NIP lub zagraniczny VAT UE (np. 'ATU12345678', 'DE123456789', 'NL822534538B01') lub pusty",
  "buyer_name": "nazwa klienta zlecajacego (spedycja/nadawca faktury) lub pusty",
  "buyer_email": "email kontaktowy lub pusty",
  "buyer_city": "miasto siedziby klienta lub pusty",
  "buyer_country": "kod ISO alpha-2 kraju klienta ('PL','DE',...) lub pusty",

  "load_country": "kod ISO alpha-2 zaladunku",
  "load_city": "miasto zaladunku",
  "load_postal_code": "kod pocztowy zaladunku lub pusty",
  "load_address": "ulica + numer miejsca zaladunku (np. 'Wielicka 22') lub pusty",
  "date_deadline": "YYYY-MM-DDTHH:MM planowana data zaladunku lub pusty",

  "unload_country": "kod ISO alpha-2 rozladunku",
  "unload_city": "miasto rozladunku",
  "unload_postal_code": "kod pocztowy rozladunku lub pusty",
  "unload_address": "ulica + numer miejsca rozladunku lub pusty",
  "unload_name": "nazwa magazynu/miejsca rozladunku lub pusty",
  "date_delivery": "YYYY-MM-DDTHH:MM planowana data rozladunku lub pusty",

  "title1": "nr referencyjny/zlecenia klienta (np. 'HB-2607068603' lub 'PO 269928501') lub pusty",
  "title2": "krotki opis ladunku (np. 'towar paletowy', 'zamrozone') lub pusty",
  "cargo_type": "FTL/LTL/ADR/CHILL/FROZEN lub pusty",
  "transport_type": "plandeka/chlodnia/wywrotka/cysterna lub pusty",
  "notes": "dodatkowe uwagi (temperatura, palety, wagi) lub pusty",

  "netto": liczba lub null,
  "currency": "PLN/EUR/USD/GBP/CHF/CZK/... lub pusty",
  "payment_terms": "np. 'Przelew 30 dni' lub pusty",

  "cargo_weight_kg": liczba lub null,
  "cargo_volume_m3": liczba lub null,
  "cargo_ldm": liczba lub null,
  "cargo_pallets": liczba lub null,
  "cargo_pallet_type": "EUR/PLA/BOX lub pusty",
  "adr_class": "1/2/3/4.1/... lub pusty",
  "adr_un": "UN1203 lub pusty",
  "temperature_min": liczba lub null,
  "temperature_max": liczba lub null,
  "incoterms": "EXW/FCA/DAP/DDP/... lub pusty",
  "incoterms_place": "miejsce dla INCOTERMS lub pusty",
  "cmr_number": "nr CMR lub pusty",

  "payment_days": liczba lub null (np. 30/45/60 z 'przelew 30 dni'),
  "required_vehicle_type": "plandeka/mega/chlodnia/cysterna/wywrotka/kontener/bus/platforma/oversize lub pusty",
  "pallets_exchange": true/false (czy wspomina o paletach wymiennych EUR/EPAL),
  "pallets_exchange_count": liczba lub null (ile palet do wymiany),
  "docs_return_days": liczba lub null (termin zwrotu CMR/WZ w dniach),
  "load_time_from": "HH:MM okno zaladunku od (bez daty) lub pusty",
  "load_time_to": "HH:MM okno zaladunku do lub pusty",
  "unload_time_from": "HH:MM okno rozladunku od lub pusty",
  "unload_time_to": "HH:MM okno rozladunku do lub pusty",
  "load_contact_name": "imie osoby na zaladunku lub pusty",
  "load_contact_phone": "telefon osoby na zaladunku lub pusty",
  "load_contact_email": "email osoby na zaladunku lub pusty",
  "unload_contact_name": "imie osoby na rozladunku lub pusty",
  "unload_contact_phone": "telefon lub pusty",
  "unload_contact_email": "email lub pusty",
  "driver_instructions": "instrukcje dla kierowcy (kod bramy, EPI, gdzie parkowac) lub pusty",

  "cargo_items": [
    {
      "product_code": "kod / ID / SKU produktu (np. '17' lub 'COMBO-285-BD-5R') lub pusty",
      "product_name": "nazwa/opis produktu (np. 'COMBO 285 BD 5R')",
      "is_dry": false,
      "is_wrapped": false,
      "is_strapped": false,
      "is_sort_only": false,
      "stack_height": liczba lub null (ile palet na sobie),
      "qty_advised": liczba lub null (Advised / deklarowana),
      "qty_real": liczba lub null (Real / rzeczywista - zwykle brak w zleceniu),
      "weight_kg": liczba lub null (waga tej pozycji),
      "unit": "szt/kg/m3/palety/kartony lub pusty"
    }
  ],

  "stops": [
    {
      "stop_type": "pickup / delivery / transit (Loading = pickup, Unloading = delivery)",
      "country_code": "kod ISO alpha-2 kraju",
      "postal_code": "kod pocztowy",
      "city": "miasto",
      "address": "ulica + numer (Street Address)",
      "place_name": "nazwa firmy/magazynu z Location (np. GEBRUEDER BAGUSAT lub TEREN PROLOGIS) lub pusty",
      "planned_at": "YYYY-MM-DDTHH:MM data przyjazdu (Arrival) lub pusty",
      "time_from": "HH:MM Opening hours od lub pusty",
      "time_to": "HH:MM Opening hours do lub pusty",
      "contact_name": "kontakt na miejscu lub pusty",
      "contact_phone": "telefon lub pusty",
      "contact_email": "email osoby na miejscu lub pusty",
      "lat": liczba lub null (jesli w dokumencie sa wspolrzedne GPS),
      "lng": liczba lub null,
      "cargo_notes": "kod, ilosc, waga total lub Location Instructions lub pusty (np. '3990 kg total, 2 items')"
    }
  ],

  "confidence": 0-100 (jak pewien jestes co do wyciagnietych danych),
  "note": "krotki komentarz dla operatora - co udalo sie wyciagnac a co nie"
}

Zasady:
- Data w formacie YYYY-MM-DDTHH:MM (bez sekund, bez strefy). Jesli brak godziny, uzyj 08:00 dla zaladunku, 16:00 dla rozladunku.
- Kraj: ZAWSZE 2-znakowy kod ISO (PL, DE, NL, CZ, SK, AT, FR, IT, ES, HU, RO, LT, LV, EE, GB, IE, CH, NO, SE, DK, FI, BE, LU, PT).
- NIP zagraniczny: zostaw z prefixem (np. 'DE123456789', 'ATU12345678').
- Cena: netto (bez VAT). Jesli klient podal brutto, przelicz netto = brutto/1.23 (PL) lub brutto (bez VAT UE).
- Wpisz "" (pusty string) zamiast null dla pol tekstowych; null tylko dla netto gdy brak.
- Confidence: 90-100 = pelne dane; 60-89 = brakuje kilku pol; 0-59 = fragment.

MULTI-STOP (wazne dla LTL / TOSCA / DB Schenker / Trans zlecen z wieloma stopami):
- Jesli dokument ma tabele "Shipment Detail Information" z Stop 1/2/3... - to jest multi-stop.
- Zawsze wyciagnij WSZYSTKIE stopy jako tablice `stops[]`.
- PIERWSZY stop typu Loading/pickup wypelnia rowniez PRIMARY load_* (load_country, load_city,
  load_postal_code, load_address, load_time_from/to, date_deadline, load_contact_*).
- OSTATNI stop typu Unloading/delivery wypelnia PRIMARY unload_* (unload_country, unload_city,
  unload_postal_code, unload_address, unload_time_from/to, date_delivery, unload_contact_*).
- WSZYSTKIE POZOSTALE stopy zostaja w tablicy `stops[]` (juz w niej sa - jako powtorki tak/nie
  jest OK, frontend rozpozna).
- Activity: Loading -> stop_type=pickup, Unloading -> stop_type=delivery.
- Total Weight ze Stop -> cargo_notes (np. "3990 kg total").
- Address wieloliniowy (np. "JEDNOSCI 4\nWSCHOWA") -> address = pierwsza linia (ulica),
  city = kolejna linia.

CARGO ITEMS w multi-stop:
- Jesli sa Item Name + Advised Quantity pod kazdym Stop - wyciagnij WSZYSTKIE items do
  jednej plaskiej tablicy `cargo_items[]` (na razie bez per-stop mapping).
- Dedupacja: item ktory pojawia sie w kilku stopach (np. "H1 BLUE 800X1200 (03)" na Stop 1
  i Stop 3 - to zwykle ten sam ladunek podnoszony i dostarczany) - dodaj TYLKO RAZ.
- Format "PRODUCT (CODE)" w Item Name: wyciagnij CODE do product_code, resztę do product_name.
- product_code = "03", product_name = "H1 BLUE 800X1200"

BARDZO WAZNE - CHECKBOXY OPAKOWANIA (is_dry, is_wrapped, is_strapped, is_sort_only):
- DOMYSLNIE ZAWSZE false dla WSZYSTKICH tych checkboxow.
- Ustaw true TYLKO gdy w dokumencie widzisz WYRAZNIE ZAZNACZONY checkbox lub X/✓ w konkretnym rows.
- Jak wygladaja checkboxy w PDF-ach spedycji:
  * □ (pusty kwadrat) = FALSE (unchecked)
  * ☒ ☑ ⊠ ⊗ ⊕ ✓ X x V (kwadrat z jakimkolwiek znakiem w srodku) = TRUE (checked)
  * Kolorowe/wypelnione kwadraty vs puste konturowe = odpowiednio TRUE/FALSE
- PATRZ CAŁKOWICIE na tabele - jesli sa 3 rows i 4 columny checkboxow (Dry/Wrap/Strap/Sort),
  to masz 12 checkboxow. Kazdy sprawdz osobno.
- BRAK kolumn Dry/Wrapping/Strapping/Sort Only w tabeli = ZAWSZE wszystko false. Nie zgaduj.
- BRAK wzmianki = false. NIE inferuj "palety" -> dry ani "chlodnia" -> wrapped itp.
- Roznice per row: w tabeli TOSCA moze byc:
   Row 1: ✓Dry □Wrap □Strap □Sort  -> is_dry:true, reszta false
   Row 2: □Dry ✓Wrap ✓Strap □Sort  -> is_wrapped:true, is_strapped:true, reszta false
   Row 3: □□□□  -> wszystko false
  Kazdy row moze byc INNY. NIE kopiuj z jednego row na kolejne.
- LTL / Shipment Detail Information / Trans / Timocom - te dokumenty NIE MAJA
  kolumn checkbox opakowania. Zwroc wszystkie false dla cargo_items[].
- TOSCA Collection Note wersja 1 (nie multi-stop) - ma te kolumny, patrz uwaznie.
- Jesli NIE JESTES PEWIEN co widzisz -> false. Latwiej user zaznaczy sam recznie
  niz odznaczac nieprawdziwie zaznaczone.

Format sciscle. Nie dodawaj tekstu poza JSON.
SYS;

        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $hasVision = ($image !== '' || !empty($pages));
            // Zwiekszone limity - schema ma stops[] + cargo_items[] + wszystkie TSL pola.
            // Duze zlecenia LTL generuja 2000-4000 tokens response.
            if ($hasVision) {
                $primary  = $image !== '' ? $image : $pages[0];
                $extra    = $image !== '' ? $pages : array_slice($pages, 1);
                // Limit 10 stron (frontend + backend). max_tokens skala per strona.
                $extra    = array_slice($extra, 0, 9);
                $maxToks  = 3000 + (min(count($extra), 9) * 500);
                $prompt   = $text !== '' ? $text
                    : (count($pages) > 1
                        ? 'Wyciagnij dane zlecenia z zalaczonych ' . count($pages) . ' stron/plikow. Polacz informacje z wszystkich stron w JEDNO spojne zlecenie (klient + trasa + multi-stop + cargo items).'
                        : 'Wyciagnij dane zlecenia z zalaczonego dokumentu.');
                $result = $ai->chatVisionJson($system, $prompt, $primary, $maxToks, $extra);
            } else {
                $result = $ai->chatJson($system, $text, 3000);
            }

            // Sanity check: jesli GPT ustawil is_* na true dla WSZYSTKICH items
            // (halucynacja / domyslka zamiast rzeczywistego rozpoznania) - sprowadz do false.
            // Legit case: kazdy item ma je zaznaczone tylko gdy dokument ma explicit
            // kolumny/oznaczenia. Zwykle nie wszystkie sa true jednoczesnie.
            if (!empty($result['cargo_items']) && is_array($result['cargo_items'])) {
                $flags = ['is_dry', 'is_wrapped', 'is_strapped', 'is_sort_only'];
                $count = count($result['cargo_items']);
                foreach ($flags as $flag) {
                    $trueCount = 0;
                    foreach ($result['cargo_items'] as $item) {
                        if (!empty($item[$flag])) $trueCount++;
                    }
                    // Jesli 100% items ma flag=true (i sa co najmniej 2 items) - podejrzane, sprowadz do false.
                    if ($count >= 2 && $trueCount === $count) {
                        foreach ($result['cargo_items'] as &$item) {
                            $item[$flag] = false;
                        }
                        unset($item);
                    }
                }
            }

            $this->jsonResp(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('aiParseOrder: ' . $e->getMessage());
            // Zwroc user-friendly message + tip
            $msg = $e->getMessage();
            if (str_contains($msg, 'nieprawidlowy JSON') || str_contains($msg, 'nieprawidłowy JSON')) {
                $msg .= ' — prawdopodobnie response ucięty. Spróbuj krótszy tekst albo mniej stron PDF.';
            }
            $this->jsonResp(['ok' => false, 'error' => $msg]);
        }
    }

    /**
     * AJAX: lekki live kalkulator trasy HERE dla formularza zlecenia.
     * POST /zlecenia/route-calc.json body: from_city, from_country, to_city, to_country,
     *                                       [currency=EUR], [rate_per_km=1.20]
     * Zwraca: distance_km, duration_min, tolls_total (EUR), tolls_by_country,
     *         suggested_price (km*rate + tolls), sugestia w PLN po kursie NBP.
     */
    public function routeCalcJson(): void
    {
        $this->request->allowMethod(['post']);
        $data = (array)$this->request->getData();

        $fromCity    = trim((string)($data['from_city'] ?? ''));
        $fromCountry = strtoupper(trim((string)($data['from_country'] ?? '')));
        $toCity      = trim((string)($data['to_city'] ?? ''));
        $toCountry   = strtoupper(trim((string)($data['to_country'] ?? '')));
        $currency    = strtoupper(trim((string)($data['currency'] ?? 'EUR')));
        $ratePerKm   = (float)($data['rate_per_km'] ?? 1.20);

        if ($fromCity === '' || $toCity === '') {
            $this->jsonResp(['ok' => false, 'error' => 'Brak miast trasy']);
            return;
        }

        try {
            $svc = new \App\Service\Routing\HereRoutingService();

            $fromQ = trim($fromCity . ($fromCountry ? ', ' . $fromCountry : ''));
            $toQ   = trim($toCity   . ($toCountry   ? ', ' . $toCountry   : ''));
            $from  = $svc->geocode($fromQ);
            $to    = $svc->geocode($toQ);

            if (!$from || !$to) {
                $this->jsonResp(['ok' => false, 'error' => 'Nie znaleziono lokalizacji']);
                return;
            }

            // Bez vehicle -> car mode (szybsze, dla wstepnej estymaty). Tolls beda car-tolls.
            $r = $svc->route(
                ['lat' => $from['lat'], 'lng' => $from['lng']],
                ['lat' => $to['lat'],   'lng' => $to['lng']],
                null,
                ['currency' => 'EUR']
            );

            $km     = (float)$r['distance_km'];
            $tollsEUR = (float)($r['tolls_total'] ?? 0);
            $suggestedEUR = round($km * $ratePerKm + $tollsEUR, 2);
            $suggestedInCurrency = $suggestedEUR;

            // Przeliczenie na waluta docelowa (przybliżony kurs statyczny — kurs NBP
            // moze byc pobrany osobno, tu tylko rough estymata).
            static $roughRates = ['EUR' => 1.0, 'PLN' => 4.30, 'USD' => 1.10, 'GBP' => 0.85, 'CHF' => 0.98];
            if (isset($roughRates[$currency])) {
                $suggestedInCurrency = round($suggestedEUR * $roughRates[$currency], 2);
            }

            $this->jsonResp([
                'ok'                => true,
                'distance_km'       => $km,
                'duration_min'      => (int)$r['duration_min'],
                'tolls_total_eur'   => round($tollsEUR, 2),
                'tolls_by_country'  => $r['tolls_by_country'] ?? [],
                'suggested_price'   => $suggestedInCurrency,
                'suggested_currency' => $currency,
                'rate_per_km'       => $ratePerKm,
                'polyline'          => (string)($r['polyline'] ?? ''),
                'from' => ['label' => $from['label'], 'country' => $from['country'], 'lat' => $from['lat'], 'lng' => $from['lng']],
                'to'   => ['label' => $to['label'],   'country' => $to['country'],   'lat' => $to['lat'],   'lng' => $to['lng']],
            ]);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('routeCalcJson: ' . $e->getMessage());
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: autocomplete miast/adresow z HERE Autosuggest.
     * GET /zlecenia/cities.json?q=Ham&country=DE
     * Zwraca max 8 propozycji z city, postal_code, country.
     */
    public function citiesJson(): void
    {
        $this->request->allowMethod(['get']);
        $q = trim((string)$this->request->getQuery('q', ''));
        if (mb_strlen($q) < 2) {
            $this->jsonResp(['ok' => true, 'items' => []]);
            return;
        }
        try {
            $svc = new \App\Service\Routing\HereRoutingService();
            $items = $svc->autosuggest($q);
            // Filter: tylko city/locality/postalCode (bez pojedynczych adresow ulicznych)
            $items = array_values(array_filter($items, function ($it) {
                $t = $it['type'] ?? '';
                return in_array($t, ['locality', 'city', 'administrativeArea', 'postalCodePoint', 'district'], true)
                    || !empty($it['city']);
            }));
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('HERE autosuggest error: ' . $e->getMessage());
            $items = [];
        }
        $this->jsonResp(['ok' => true, 'items' => $items]);
    }

    /**
     * AJAX: lista kierowcow do autocomplete/datalist.
     */
    public function driversJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $rows = [];
        try {
            $Drivers = $this->fetchTable('Drivers');
            $rows = $Drivers->find()
                ->select(['id', 'full_name', 'phone'])
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByAsc('full_name')
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) {}

        $this->jsonResp(['ok' => true, 'items' => $rows]);
    }

    /**
     * AJAX: lista pojazdow do autocomplete/datalist.
     */
    public function vehiclesJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $rows = [];
        try {
            $Vehicles = $this->fetchTable('Vehicles');
            $rows = $Vehicles->find()
                ->select(['id', 'name', 'plate', 'type'])
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByAsc('name')
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) {}

        $this->jsonResp(['ok' => true, 'items' => $rows]);
    }

    /**
     * AJAX: lista szablonow zlecen dla firmy.
     * GET /zlecenia/szablony
     */
    public function templatesListJson(): void
    {
        $this->request->allowMethod(['get']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $rows = [];
        try {
            $rows = $this->fetchTable('SpeedOrderTemplates')->find()
                ->where(['company_id' => $companyId])
                ->orderByDesc('is_favorite')
                ->orderByDesc('usage_count')
                ->orderByDesc('modified')
                ->limit(100)
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) {}

        $out = array_map(function ($t) {
            return [
                'id'           => $t['id'],
                'name'         => $t['name'],
                'description'  => $t['description'],
                'is_favorite'  => (bool)$t['is_favorite'],
                'usage_count'  => (int)$t['usage_count'],
                'last_used_at' => $t['last_used_at'] instanceof \DateTimeInterface
                    ? $t['last_used_at']->format('Y-m-d H:i')
                    : $t['last_used_at'],
                'payload'      => json_decode($t['payload_json'] ?? '{}', true),
            ];
        }, $rows);

        $this->jsonResp(['ok' => true, 'templates' => $out]);
    }

    /**
     * AJAX: zapisz nowy szablon z aktualnych danych formularza.
     * POST /zlecenia/szablony/zapisz body: name, description, payload_json
     */
    public function templateSaveJson(): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) {
            $this->jsonResp(['ok' => false, 'error' => 'Brak company_id']);
            return;
        }

        $name = trim((string)$this->request->getData('name', ''));
        $description = trim((string)$this->request->getData('description', ''));
        $payload = (string)$this->request->getData('payload_json', '{}');

        if ($name === '') {
            $this->jsonResp(['ok' => false, 'error' => 'Nazwa wymagana']);
            return;
        }
        // Sanity check JSON
        if (json_decode($payload, true) === null && $payload !== 'null' && $payload !== '{}') {
            $this->jsonResp(['ok' => false, 'error' => 'Niepoprawny payload_json']);
            return;
        }

        try {
            $SOT = $this->fetchTable('SpeedOrderTemplates');
            $tpl = $SOT->newEntity([
                'company_id'   => $companyId,
                'name'         => $name,
                'description'  => $description ?: null,
                'payload_json' => $payload,
                'is_favorite'  => false,
                'usage_count'  => 0,
            ]);
            if ($SOT->save($tpl)) {
                $this->jsonResp(['ok' => true, 'id' => $tpl->id]);
            } else {
                $this->jsonResp(['ok' => false, 'error' => 'Nie zapisano', 'errors' => $tpl->getErrors()]);
            }
        } catch (\Throwable $e) {
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: usun szablon.
     * POST /zlecenia/szablony/{id}/usun
     */
    public function templateDeleteJson(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        try {
            $SOT = $this->fetchTable('SpeedOrderTemplates');
            $tpl = $SOT->get($id);
            if ((string)$tpl->company_id !== (string)$companyId) {
                $this->jsonResp(['ok' => false, 'error' => 'Nie masz uprawnień']);
                return;
            }
            $SOT->delete($tpl);
            $this->jsonResp(['ok' => true]);
        } catch (\Throwable $e) {
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: toggle favorite + increment usage_count na apply.
     * POST /zlecenia/szablony/{id}/uzyj lub /zlecenia/szablony/{id}/favorite
     */
    public function templateUseJson(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        try {
            $SOT = $this->fetchTable('SpeedOrderTemplates');
            $tpl = $SOT->get($id);
            if ((string)$tpl->company_id !== (string)$companyId) {
                $this->jsonResp(['ok' => false, 'error' => 'Nie masz uprawnień']);
                return;
            }
            $tpl->usage_count = (int)$tpl->usage_count + 1;
            $tpl->last_used_at = new \DateTime();
            $SOT->save($tpl);
            $this->jsonResp(['ok' => true, 'usage_count' => $tpl->usage_count]);
        } catch (\Throwable $e) {
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function templateFavoriteJson(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        try {
            $SOT = $this->fetchTable('SpeedOrderTemplates');
            $tpl = $SOT->get($id);
            if ((string)$tpl->company_id !== (string)$companyId) {
                $this->jsonResp(['ok' => false, 'error' => 'Nie masz uprawnień']);
                return;
            }
            $tpl->is_favorite = !((bool)$tpl->is_favorite);
            $SOT->save($tpl);
            $this->jsonResp(['ok' => true, 'is_favorite' => (bool)$tpl->is_favorite]);
        } catch (\Throwable $e) {
            $this->jsonResp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /zlecenia/{id}/notatka - dodaj notatke do zlecenia.
     */
    public function noteAdd(int $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->getIdentifier();

        $body = trim((string)$this->request->getData('body', ''));
        $type = trim((string)$this->request->getData('note_type', 'note'));

        if ($body === '') {
            $this->Flash->error(__('Treść notatki nie może być pusta.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        try {
            $SON = $this->fetchTable('SpeedOrderNotes');
            $note = $SON->newEntity([
                'company_id'     => $companyId,
                'speed_order_id' => $id,
                'user_id'        => $userId,
                'note_type'      => in_array($type, ['note','reminder','phone_call','email'], true) ? $type : 'note',
                'body'           => $body,
            ]);
            if ($SON->save($note)) {
                $this->Flash->success(__('Notatka dodana.'));
            } else {
                $this->Flash->error(__('Nie udało się zapisać notatki.'));
            }
        } catch (\Throwable $e) {
            $this->Flash->error(__('Błąd: {0}', $e->getMessage()));
        }
        $this->redirect(['action' => 'view', $id]);
    }

    /**
     * POST /zlecenia/notatka/{noteId}/usun - usun notatke (autor + admin).
     */
    public function noteDelete(string $noteId): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        $userId    = $identity?->getIdentifier();
        $isAdmin   = (bool)($identity?->get('is_admin') ?? false)
            || (string)($identity?->get('role') ?? '') === 'admin';

        try {
            $SON = $this->fetchTable('SpeedOrderNotes');
            $note = $SON->get($noteId);
            if ((string)$note->company_id !== (string)$companyId) {
                throw new NotFoundException();
            }
            // Autor albo admin
            if (!$isAdmin && (string)$note->user_id !== (string)$userId) {
                $this->Flash->error(__('Nie masz uprawnień do usunięcia tej notatki.'));
                $this->redirect(['action' => 'view', $note->speed_order_id]);
                return;
            }
            $orderId = (int)$note->speed_order_id;
            $SON->delete($note);
            $this->Flash->success(__('Notatka usunięta.'));
            $this->redirect(['action' => 'view', $orderId]);
        } catch (\Throwable $e) {
            $this->Flash->error(__('Błąd: {0}', $e->getMessage()));
            $this->redirect(['action' => 'index']);
        }
    }

    /**
     * POST /zlecenia/{id}/zaakceptuj - manager akceptuje zlecenie.
     * Wymagane dla zlecen ktorych brutto przekracza Configure.Orders.approvalThresholdPln
     */
    public function approve(int $id): void
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $userId   = $identity?->getIdentifier();
        $role     = (string)($identity?->get('role') ?? '');
        $isMgr    = in_array($role, ['spedycja_manager', 'sales_manager'], true)
            || (bool)($identity?->get('is_admin') ?? false);

        if (!$isMgr) {
            $this->Flash->error(__('Tylko manager może akceptować zlecenia.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->get($id);
        if ($order->approval_status !== 'pending') {
            $this->Flash->warning(__('To zlecenie nie wymaga akceptacji (status: {0}).', $order->approval_status));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        $order->approval_status = 'approved';
        $order->approved_by_user_id = $userId;
        $order->approved_at = new \DateTime();
        $order->approval_note = trim((string)$this->request->getData('note', ''));
        $SpeedOrders->save($order);

        // Log w notatkach systemowych
        try {
            $this->fetchTable('SpeedOrderNotes')->logSystem(
                (string)$identity?->get('company_id'), $id,
                'Zlecenie zaakceptowane przez managera' . ($order->approval_note ? ': ' . $order->approval_note : ''),
                ['action' => 'approved', 'user_id' => $userId]
            );
        } catch (\Throwable) {}

        $this->Flash->success(__('Zlecenie zaakceptowane.'));
        $this->redirect(['action' => 'view', $id]);
    }

    /**
     * POST /zlecenia/{id}/odrzuc - manager odrzuca zlecenie.
     */
    public function reject(int $id): void
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $userId   = $identity?->getIdentifier();
        $role     = (string)($identity?->get('role') ?? '');
        $isMgr    = in_array($role, ['spedycja_manager', 'sales_manager'], true)
            || (bool)($identity?->get('is_admin') ?? false);

        if (!$isMgr) {
            $this->Flash->error(__('Tylko manager może odrzucić zlecenie.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        $note = trim((string)$this->request->getData('note', ''));
        if ($note === '') {
            $this->Flash->error(__('Powód odrzucenia jest wymagany.'));
            $this->redirect(['action' => 'view', $id]);
            return;
        }

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->get($id);
        $order->approval_status = 'rejected';
        $order->approved_by_user_id = $userId;
        $order->approved_at = new \DateTime();
        $order->approval_note = $note;
        $SpeedOrders->save($order);

        try {
            $this->fetchTable('SpeedOrderNotes')->logSystem(
                (string)$identity?->get('company_id'), $id,
                'Zlecenie odrzucone przez managera: ' . $note,
                ['action' => 'rejected', 'user_id' => $userId]
            );
        } catch (\Throwable) {}

        $this->Flash->success(__('Zlecenie odrzucone.'));
        $this->redirect(['action' => 'view', $id]);
    }

    /**
     * PDF potwierdzenia zlecenia dla klienta / wewnetrznego wydruku.
     * GET /zlecenia/pdf/{id}?download=1
     */
    public function pdfConfirmation(int $id): void
    {
        $this->request->allowMethod(['get']);
        $order = $this->fetchTable('SpeedOrders')->find()
            ->where(['id' => $id])
            ->contain(['SpeedOrderCargoItems', 'SpeedOrderStops'])
            ->first();
        if (!$order) {
            throw new NotFoundException(__('Zlecenie nie istnieje.'));
        }

        $download = (bool)$this->request->getQuery('download', 1);
        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setTemplate('pdf_confirmation')
            ->setLayout(false)
            ->setOptions([
                'pdfConfig' => [
                    'filename'    => 'Zlecenie-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$order->symbol) . '.pdf',
                    'download'    => $download,
                    'orientation' => 'portrait',
                    'paper'       => 'A4',
                    'engine'      => 'CakePdf.DomPdf',
                ],
            ]);

        $this->set(compact('order'));
    }

    /**
     * Wysyla email z potwierdzeniem zlecenia do klienta (buyer_email).
     * Uzywa template templates/email/html/speed_order_confirmation.php.
     */
    private function sendOrderEmail(\App\Model\Entity\SpeedOrder $order): void
    {
        if (empty($order->buyer_email)) {
            throw new \RuntimeException('Brak buyer_email');
        }
        $mailer = new \Cake\Mailer\Mailer('default');
        $subject = 'Potwierdzenie zlecenia ' . $order->symbol;
        $mailer->setTo($order->buyer_email)
            ->setSubject($subject)
            ->setEmailFormat('html')
            ->viewBuilder()->setLayout('default')->setTemplate('speed_order_confirmation');
        $mailer->setViewVars(['order' => $order]);
        $mailer->deliver();
    }

    // =========================================================================
    // HELPERY — RECZNE ZLECENIA
    // =========================================================================

    /**
     * Zwraca NIP zalogowanej firmy (do wypelnienia company_nip w zleceniu).
     * speed_orders.company_nip trzyma NIP bez prefixu (10 cyfr dla PL).
     */
    private function currentCompanyNip(): ?string
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) return null;
        try {
            $company = $this->fetchTable('Companies')->find()
                ->select(['nip'])
                ->where(['id' => $companyId])
                ->first();
            if ($company && !empty($company->nip)) {
                return preg_replace('/\D+/', '', (string)$company->nip);
            }
        } catch (\Throwable) {}
        return null;
    }

    private function currentCompanyName(): ?string
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) return null;
        try {
            $company = $this->fetchTable('Companies')->find()
                ->select(['name'])
                ->where(['id' => $companyId])
                ->first();
            return $company?->name;
        } catch (\Throwable) {}
        return null;
    }

    /**
     * Generuje kolejny symbol dla zlecenia recznego.
     * Format: M-NNNN/MM/YYYY (np. M-0001/08/2026).
     * Numer resetuje sie co miesiac per firma.
     *
     * @return array [symbol, manual_seq, rok, mc]
     */
    private function nextManualSymbol(?string $companyNip, Date $docDate): array
    {
        $rok = $docDate->format('Y');
        $mc  = $docDate->format('m');

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $maxRow = $SpeedOrders->find()
            ->select(['max_seq' => 'MAX(manual_seq)'])
            ->where([
                'source'      => 'manual',
                'company_nip' => $companyNip,
                'rok'         => $rok,
                'mc'          => $mc,
            ])
            ->disableHydration()
            ->first();

        $next = ((int)($maxRow['max_seq'] ?? 0)) + 1;
        $symbol = sprintf('M-%04d/%s/%s', $next, $mc, $rok);
        return [$symbol, $next, $rok, $mc];
    }

    /**
     * Sanityzuje i uzupelnia dane zlecenia manualnego przed zapisem.
     * Wywolywana z add() i edit().
     */
    private function prepareManualOrderData(array $data, ?string $companyNip, ?string $companyName): array
    {
        // Wymus source=manual i wypelnij pola meta.
        $data['source']       = 'manual';
        $data['speed_id']     = null;
        $data['company_nip']  = $companyNip;
        $data['company_name'] = $companyName;

        // Automatyczny numer jesli nie podano (fallback dla POST bez symbolu).
        if (empty($data['symbol'])) {
            $docDate = !empty($data['date_doc']) ? new Date($data['date_doc']) : new Date();
            [$symbol, $seq, $rok, $mc] = $this->nextManualSymbol($companyNip, $docDate);
            $data['symbol']     = $symbol;
            $data['manual_seq'] = $seq;
            $data['rok']        = $rok;
            $data['mc']         = $mc;
        }

        // Wylicz VAT/brutto z netto+rate (server-side jako bezpieczna weryfikacja
        // — JS liczy to samo, ale zapisu ufamy tylko po serwerowej weryfikacji).
        $netto = (float)($data['netto'] ?? 0);
        $vatRate = isset($data['vat_rate']) ? trim((string)$data['vat_rate']) : '23';

        if (is_numeric($vatRate)) {
            $rate = (float)$vatRate;
            $data['vat']    = round($netto * $rate / 100, 2);
            $data['brutto'] = round($netto + (float)$data['vat'], 2);
        } else {
            // np/zw/oo (nie-numeryczne stawki) — VAT=0, brutto=netto
            $data['vat']    = 0.0;
            $data['brutto'] = round($netto, 2);
        }
        unset($data['vat_rate']); // nie ma takiej kolumny w DB

        // Kurs walutowy: dla PLN wymuszamy 1.0
        $currency = strtoupper(trim((string)($data['currency'] ?? 'PLN')));
        $data['currency'] = $currency;
        if ($currency === 'PLN') {
            $data['exchange_rate'] = 1.0;
        }

        // Auto-wyliczenie payment_due_date z date_doc + payment_days
        // + auto-generacja payment_terms string ('Przelew 30 dni') gdy pusty
        $paymentDays = (int)($data['payment_days'] ?? 0);
        if ($paymentDays > 0 && !empty($data['date_doc'])) {
            try {
                $issueDate = new \DateTime((string)$data['date_doc']);
                $issueDate->modify('+' . $paymentDays . ' days');
                $data['payment_due_date'] = $issueDate->format('Y-m-d');
            } catch (\Throwable) {}
        }
        if ($paymentDays > 0 && empty($data['payment_terms'])) {
            $data['payment_terms'] = 'Przelew ' . $paymentDays . ' dni';
        }

        // Normalizacja bool palet wymiennych
        $data['pallets_exchange'] = !empty($data['pallets_exchange']) ? true : false;
        if (!$data['pallets_exchange']) {
            $data['pallets_exchange_count'] = null;
        }

        // Normalizacja krajow (2-znakowy uppercase, fallback PL)
        foreach (['buyer_country', 'load_country', 'unload_country'] as $ccKey) {
            if (isset($data[$ccKey])) {
                $data[$ccKey] = strtoupper(trim((string)$data[$ccKey])) ?: null;
            }
        }

        // Cargo items: filtruj puste (bez product_code i product_name), normalizuj booleany,
        // ponumeruj line_index od 1
        if (isset($data['speed_order_cargo_items']) && is_array($data['speed_order_cargo_items'])) {
            $clean = [];
            $idx = 1;
            foreach ($data['speed_order_cargo_items'] as $item) {
                if (!is_array($item)) continue;
                $code = trim((string)($item['product_code'] ?? ''));
                $name = trim((string)($item['product_name'] ?? ''));
                if ($code === '' && $name === '') continue;
                // Normalizuj booleany checkboxow
                foreach (['is_dry', 'is_wrapped', 'is_strapped', 'is_sort_only'] as $b) {
                    $item[$b] = !empty($item[$b]);
                }
                $item['line_index'] = $idx++;
                $clean[] = $item;
            }
            $data['speed_order_cargo_items'] = $clean;
        }

        // Multi-stop: normalizacja - filtruj puste stopy + puste datetime -> null,
        // normalizuj country_code, ponumeruj stop_index od 1
        if (isset($data['speed_order_stops']) && is_array($data['speed_order_stops'])) {
            $clean = [];
            $idx = 1;
            foreach ($data['speed_order_stops'] as $stop) {
                if (!is_array($stop)) continue;
                // Skip zupelnie puste (bez city + bez place_name)
                $city  = trim((string)($stop['city'] ?? ''));
                $place = trim((string)($stop['place_name'] ?? ''));
                if ($city === '' && $place === '') continue;
                // Puste datetime -> null (walidator Cake)
                foreach (['planned_at', 'actual_at', 'completed_at'] as $dtKey) {
                    if (isset($stop[$dtKey]) && trim((string)$stop[$dtKey]) === '') {
                        unset($stop[$dtKey]);
                    }
                }
                if (isset($stop['country_code'])) {
                    $stop['country_code'] = strtoupper(trim((string)$stop['country_code'])) ?: null;
                }
                $stop['stop_index'] = $idx++;
                $clean[] = $stop;
            }
            $data['speed_order_stops'] = $clean;
        }

        // Approval workflow: jesli brutto (w PLN) > prog -> pending, inaczej not_required
        $threshold = (float)(\Cake\Core\Configure::read('Orders.approvalThresholdPln') ?? 10000);
        $bruttoPln = (float)$data['brutto'];
        if ($currency !== 'PLN') {
            $bruttoPln *= (float)($data['exchange_rate'] ?? 1);
        }
        if ($threshold > 0 && $bruttoPln > $threshold) {
            // Ustaw pending TYLKO gdy jeszcze nie zaakceptowane (edit moze utrzymac approved)
            if (empty($data['approval_status']) || $data['approval_status'] === 'not_required') {
                $data['approval_status'] = 'pending';
            }
        } else {
            $data['approval_status'] = 'not_required';
        }

        // Nick wystawiajacego z sesji
        $identity = $this->request->getAttribute('identity');
        if ($identity) {
            $username = (string)($identity->get('username') ?? $identity->get('email') ?? $identity->getIdentifier());
            if (empty($data['nick_created'])) {
                $data['nick_created'] = $username;
            }
            $data['nick_modified'] = $username;
        }

        return $data;
    }

    /**
     * Lista kierowcow do render-time (dla datalist w formularzu).
     */
    private function loadDriversForSelect(): array
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) return [];
        try {
            return $this->fetchTable('Drivers')->find()
                ->select(['id', 'full_name', 'phone'])
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByAsc('full_name')
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) { return []; }
    }

    /**
     * Ostatnie zlecenia manualne biezacego miesiaca — do hint'a w formularzu
     * ("Ostatnie 5 zlecen w sierpniu: M-0001, M-0002...").
     */
    private function loadRecentManualInMonth(?string $companyNip): array
    {
        if (!$companyNip) return [];
        try {
            return $this->fetchTable('SpeedOrders')->find()
                ->select(['id', 'symbol', 'date_doc', 'buyer_name'])
                ->where([
                    'source'      => 'manual',
                    'company_nip' => $companyNip,
                    'rok'         => date('Y'),
                    'mc'          => date('m'),
                ])
                ->orderByDesc('manual_seq')
                ->limit(5)
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) { return []; }
    }

    /**
     * Lista pojazdow do render-time (dla datalist w formularzu).
     */
    private function loadVehiclesForSelect(): array
    {
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        if (!$companyId) return [];
        try {
            return $this->fetchTable('Vehicles')->find()
                ->select(['id', 'name', 'plate', 'type'])
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByAsc('name')
                ->disableHydration()
                ->all()
                ->toList();
        } catch (\Throwable) { return []; }
    }
}

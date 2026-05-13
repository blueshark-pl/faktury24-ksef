<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;

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

        $this->set(compact('orders', 'total', 'page', 'pages', 'limit', 'search', 'status', 'currency', 'contract', 'contractsList', 'amountMin', 'amountMax', 'deliveryFrom', 'deliveryTo', 'cmrMap', 'hasPolPodMap', 'sortKey', 'sortDir'));
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
        $order = $SpeedOrders->get($id);
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

        $this->set(compact('order', 'rawData', 'costInvoices', 'statusLogs', 'logAvatarMap', 'attachments', 'attachmentLabels'));
    }

    // -------------------------------------------------------------------------
    // Szczegóły zlecenia — AJAX (bez layoutu, do modala)
    // -------------------------------------------------------------------------
    public function viewModal(int $id): void
    {
        $this->request->allowMethod(['get']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->get($id);
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

        // Rzeczywisty załadunek/rozładunek (datetime, edytowalne przez spedytora)
        foreach (['actual_load_at', 'actual_unload_at'] as $field) {
            $val = $this->request->getData($field);
            if ($val === null) {
                continue;
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
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

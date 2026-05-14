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
                        // Dwa URL-e: 'path' dla podglądu inline (lightbox), 'download'
                        // wymusza Content-Disposition: attachment (przycisk Pobierz).
                        'path'     => \Cake\Routing\Router::url(['action' => 'downloadAttachment', $att->id, '?' => ['inline' => 1]]),
                        'download' => \Cake\Routing\Router::url(['action' => 'downloadAttachment', $att->id]),
                    ];
                }
            } catch (\Throwable) { /* tabela może nie istnieć */ }
        }

        // M:N: mapa wszystkich faktur per zlecenie przez pivot speed_order_invoices.
        // $invoicesMap[order_id] = [Invoice, Invoice, ...] (uporządkowane wg id).
        $invoicesMap = [];
        if (!empty($orderIds)) {
            try {
                $rows = $this->fetchTable('SpeedOrderInvoices')->find()
                    ->select(['SpeedOrderInvoices.speed_order_id', 'Invoices.id', 'Invoices.fullnumber', 'Invoices.date', 'Invoices.total', 'Invoices.currency', 'Invoices.paymentstate'])
                    ->contain(['Invoices'])
                    ->where(['SpeedOrderInvoices.speed_order_id IN' => $orderIds])
                    ->orderByAsc('SpeedOrderInvoices.id')
                    ->all();
                foreach ($rows as $r) {
                    if ($r->invoice) {
                        $invoicesMap[$r->speed_order_id][] = $r->invoice;
                    }
                }
            } catch (\Throwable) {
                $invoicesMap = [];
            }
        }

        $this->set(compact(
            'orders', 'cmrMap', 'invoicesMap', 'total', 'page', 'pages', 'limit',
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

        // M:N: ładujemy zlecenie wraz z wszystkimi fakturami sprzedażowymi.
        $order = $this->fetchTable('SpeedOrders')->find()
            ->where([
                'SpeedOrders.id'        => $id,
                'SpeedOrders.buyer_nip' => $this->profile->nip,
            ])
            ->contain([
                // UWAGA: bez select() restriction — belongsToMany potrzebuje pełnego SELECT
                // żeby SelectWithPivotLoader poprawnie zbudował result map (pivot keys).
                'AllInvoices' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->orderByAsc('Invoices.date');
                },
            ])
            ->first();
        if (!$order) {
            $this->Flash->error(__('Zlecenie nie istnieje lub nie należy do Twojej firmy.'));
            return $this->redirect(['action' => 'index']);
        }

        // Backward compat: niektóre szablony jeszcze czytają $invoice (pierwsza).
        $invoices = $order->invoices ?? [];
        $invoice  = !empty($invoices) ? $invoices[0] : null;

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

        $this->set(compact('order', 'invoice', 'invoices', 'attachments'));
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

        // ?inline=1 — wymuszamy 'inline' żeby PDF/IMG zostały wyświetlone w lightboxie.
        // Cake's withFile() ignoruje nasz Content-Disposition gdy 'download' true,
        // a przy false czasem nie ustawia żadnego — dlatego budujemy response ręcznie
        // przez Stream + jawny Content-Disposition: inline.
        $inline   = (bool)$this->request->getQuery('inline');
        $safeName = str_replace('"', '', (string)($att->original_name ?: 'cmr'));
        $disp     = ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"';

        $stream = new \Laminas\Diactoros\Stream($fullPath, 'rb');
        return $this->response
            ->withType($att->mime_type ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', $disp)
            ->withHeader('Content-Length', (string)filesize($fullPath))
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withBody($stream);
    }

    // -------------------------------------------------------------------------
    // Pobranie faktury sprzedażowej w PDF — wersja custom (CakePdf + print_custom).
    // Weryfikacja dostępu: faktura musi wisieć przy zleceniu klienta (NIP match).
    // Renderowanie inline (bez redirectu) żeby pominąć drugą warstwę permissions
    // dla /invoices/print-custom.
    // -------------------------------------------------------------------------
    public function downloadInvoice(string $invoiceId): ?Response
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

        // Renderujemy custom template PDF lokalnie (CakePdf/DomPdf) zamiast redirectu
        // na /invoices/print-custom — pomija permission redirect chain i podaje
        // klientowi bezpośredni download.
        $invoice = $this->fetchTable('Invoices')->get($invoiceId, [
            'contain' => [
                'InvoiceContractors',
                'InvoiceContents' => ['Vats'],
                'Companies',
                'InvoiceCompanyDetails',
            ],
        ]);

        // Dodatkowe opisy (DodatkowyOpis) — jak w InvoicesController::printCustom
        if (!$invoice->has('invoice_additional_descriptions')) {
            $invoice->invoice_additional_descriptions = $this->fetchTable('InvoiceAdditionalDescriptions')
                ->find()
                ->where(['invoice_id' => $invoice->id])
                ->orderByAsc('nr_wiersza')
                ->all()
                ->toArray();
        }

        // Kurs waluty
        $cur     = strtoupper((string)($invoice->currency ?? 'PLN'));
        $fxRate  = (float)($invoice->currency_exchange ?? $invoice->fx_rate ?? 0);
        $fxDate  = $invoice->currency_date ?? null;
        $fxTable = (string)($invoice->exchange_table ?? '');

        // Adnotacje (reverse_charge itp.)
        $ann = [];
        if (!empty($invoice->annotations)) {
            $ann = is_array($invoice->annotations)
                ? $invoice->annotations
                : (json_decode((string)$invoice->annotations, true) ?: []);
        }
        $hasReverseCharge = !empty($ann['reverse_charge']);
        if (!$hasReverseCharge && !empty($invoice->invoice_contents)) {
            foreach ($invoice->invoice_contents as $it) {
                $vatName = strtolower(trim((string)($it->vat->name ?? '')));
                if (str_contains($vatName, 'ue') || str_contains($vatName, 'nie podl') || str_starts_with($vatName, 'np')) {
                    $hasReverseCharge = true;
                    break;
                }
            }
        }

        $renderPdf  = true;
        $safeNumber = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($invoice->fullnumber ?: $invoice->id));
        $filename   = 'faktura_custom_' . $safeNumber . ($lang === 'en' ? '_EN' : '') . '.pdf';

        $this->set(compact('invoice', 'cur', 'fxRate', 'fxDate', 'fxTable', 'lang', 'ann', 'hasReverseCharge', 'renderPdf'));

        $this->viewBuilder()
            ->setClassName('CakePdf.Pdf')
            ->setTemplate('print_custom')
            ->setTemplatePath('Invoices')      // template w templates/Invoices/print_custom.php
            ->setLayout('ajax')
            ->setOptions([
                'pdfConfig' => [
                    'filename'    => $filename,
                    'download'    => true,
                    'orientation' => 'portrait',
                    'paper'       => 'A4',
                    'engine'      => 'CakePdf.DomPdf',
                ],
            ]);
        // disableAutoLayout? — print_custom używa setLayout('ajax') jak printCustom.

        return null;
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

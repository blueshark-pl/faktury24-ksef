<?php
/**
 * @var \App\View\AppView                                  $this
 * @var \App\Model\Entity\SpeedOrder                       $order
 * @var \App\Model\Entity\Invoice|null                     $invoice
 * @var \App\Model\Entity\SpeedOrderAttachment[]           $attachments
 * @var \App\Model\Entity\ClientProfile                    $clientProfile
 * @var string                                             $currentLocale
 */
$this->assign('title', __('Zlecenie') . ' ' . ($order->symbol ?: '#' . $order->id));

$fdate     = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fdatetime = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : '—';
$fnum      = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary mb-2">
            <i class="ri-arrow-left-line me-1"></i><?= __('Wróć do listy') ?>
        </a>
        <h4 class="mb-0 fw-semibold">
            <?= __('Zlecenie') ?> <span class="text-primary"><?= h($order->symbol ?: '#' . $order->id) ?></span>
        </h4>
    </div>
    <div class="btn-group btn-group-sm" role="group">
        <a href="<?= $this->Url->build(['action' => 'setLocale', 'pl']) ?>"
           class="btn btn-outline-secondary <?= $currentLocale === 'pl' ? 'active' : '' ?>">PL</a>
        <a href="<?= $this->Url->build(['action' => 'setLocale', 'en']) ?>"
           class="btn btn-outline-secondary <?= $currentLocale === 'en' ? 'active' : '' ?>">EN</a>
    </div>
</div>

<?= $this->Flash->render() ?>

<div class="row g-3">
    <!-- Lewa: dane zlecenia -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-truck-line me-1"></i><?= __('Dane transportu') ?></strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Załadunek') ?></div>
                        <div class="fw-semibold">
                            <i class="ri-arrow-up-circle-fill text-success me-1"></i>
                            <?= h($order->place_from_name ?: '—') ?>
                            <?php if ($order->place_from_country): ?>
                                <span class="badge bg-light text-secondary border ms-1"><?= h($order->place_from_country) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Rozładunek') ?></div>
                        <div class="fw-semibold">
                            <i class="ri-arrow-down-circle-fill text-danger me-1"></i>
                            <?= h($order->place_to_name ?: '—') ?>
                            <?php if ($order->place_to_country): ?>
                                <span class="badge bg-light text-secondary border ms-1"><?= h($order->place_to_country) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($order->route_description): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Trasa') ?></div>
                        <div><?= h($order->route_description) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Data dokumentu') ?></div>
                        <div><?= $fdate($order->date_doc) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Planowany załadunek') ?></div>
                        <div><?= $fdatetime($order->date_ship) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Planowane zakończenie') ?></div>
                        <div><?= $fdatetime($order->date_delivery) ?></div>
                    </div>
                    <?php if ($order->title1 || $order->title2 || $order->cargo_type): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Tytuł / Ładunek') ?></div>
                        <?php if ($order->title1): ?>
                            <div class="fw-semibold"><?= h($order->title1) ?></div>
                        <?php endif; ?>
                        <?php if ($order->title2): ?>
                            <div class="text-muted"><?= h($order->title2) ?></div>
                        <?php endif; ?>
                        <?php if ($order->cargo_type): ?>
                            <div class="small mt-1"><span class="badge bg-light text-secondary border"><?= h($order->cargo_type) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($order->notes): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Uwagi') ?></div>
                        <div class="text-prewrap" style="white-space:pre-wrap"><?= h($order->notes) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Załączniki CMR -->
        <div class="card shadow-sm mb-3" id="attachments">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-attachment-2 me-1"></i><?= __('Załączniki (CMR)') ?></strong>
                <span class="badge bg-secondary ms-2"><?= count($attachments) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <div class="text-muted small fst-italic"><?= __('Brak załączników.') ?></div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($attachments as $att): ?>
                        <?php
                            $isImage = str_starts_with((string)$att->mime_type, 'image/');
                            $label = $att->speed_order_attachment_label?->name ?? '';
                        ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="border rounded p-2 d-flex align-items-center gap-2">
                                <i class="<?= $isImage ? 'ri-image-line' : 'ri-file-pdf-line' ?> fs-4 text-secondary"></i>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="text-truncate small fw-semibold"
                                         title="<?= h($att->original_name) ?>">
                                        <?= h($att->original_name ?: ('cmr-' . $att->id)) ?>
                                    </div>
                                    <?php if ($label): ?>
                                        <div class="text-muted" style="font-size:.7em">
                                            <span class="badge bg-light text-secondary border"><?= h($label) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= $this->Url->build(['action' => 'downloadAttachment', $att->id]) ?>"
                                   class="btn btn-sm btn-outline-primary flex-shrink-0"
                                   title="<?= __('Pobierz') ?>">
                                    <i class="ri-download-line"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Prawa: kwota + faktura -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-money-euro-circle-line me-1"></i><?= __('Wartość zlecenia') ?></strong>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><?= __('Netto') ?></span>
                    <span class="fw-semibold"><?= $fnum($order->netto) ?> <?= h($order->currency) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><?= __('VAT') ?></span>
                    <span class="fw-semibold"><?= $fnum($order->vat) ?> <?= h($order->currency) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="text-muted"><?= __('Brutto') ?></span>
                    <span class="fw-bold fs-5 text-primary"><?= $fnum($order->brutto) ?> <?= h($order->currency) ?></span>
                </div>
                <?php if ($order->currency !== 'PLN' && $order->exchange_rate && (float)$order->exchange_rate > 0): ?>
                    <div class="text-muted small mt-2">
                        <?= __('Kurs') ?>: <?= number_format((float)$order->exchange_rate, 4, ',', ' ') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-file-text-line me-1"></i><?= __('Faktura') ?></strong>
            </div>
            <div class="card-body">
                <?php if ($invoice): ?>
                    <div class="fw-semibold mb-1"><?= h($invoice->fullnumber) ?></div>
                    <div class="text-muted small mb-2">
                        <?= __('Data wystawienia') ?>: <?= $fdate($invoice->date) ?>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small"><?= __('Kwota') ?></span>
                        <span class="fw-semibold"><?= $fnum($invoice->total) ?> <?= h($invoice->currency) ?></span>
                    </div>
                    <?php if ($invoice->paymentdate): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small"><?= __('Termin płatności') ?></span>
                        <span><?= $fdate($invoice->paymentdate) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                        $stMap = [
                            'paid'    => ['cls' => 'bg-success-subtle text-success border-success-subtle', 'lbl' => __('Opłacona')],
                            'partial' => ['cls' => 'bg-warning-subtle text-warning border-warning-subtle', 'lbl' => __('Częściowo opłacona')],
                            'unpaid'  => ['cls' => 'bg-danger-subtle text-danger border-danger-subtle',   'lbl' => __('Nieopłacona')],
                        ];
                        $st = $stMap[$invoice->paymentstate ?? 'unpaid'] ?? $stMap['unpaid'];
                    ?>
                    <div class="mb-3">
                        <span class="badge <?= $st['cls'] ?> border"><?= $st['lbl'] ?></span>
                    </div>
                    <a href="<?= $this->Url->build(['action' => 'downloadInvoice', $invoice->id]) ?>"
                       class="btn btn-primary w-100">
                        <i class="ri-download-line me-1"></i><?= __('Pobierz fakturę PDF') ?>
                    </a>
                <?php else: ?>
                    <div class="text-muted small fst-italic">
                        <i class="ri-information-line me-1"></i>
                        <?= __('Faktura jeszcze nie została wystawiona.') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

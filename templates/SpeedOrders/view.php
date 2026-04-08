<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SpeedOrder $order
 * @var array|null $rawData
 */

$this->assign('title', 'Zlecenie ' . h($order->symbol));

$statusLabel = match((int)$order->status) {
    1 => ['label' => 'Nowe',         'class' => 'bg-secondary-transparent'],
    2 => ['label' => 'W realizacji', 'class' => 'bg-warning-transparent'],
    3 => ['label' => 'Zrealizowane', 'class' => 'bg-success-transparent'],
    4 => ['label' => 'Anulowane',    'class' => 'bg-danger-transparent'],
    default => ['label' => (string)$order->status, 'class' => 'bg-light text-dark'],
};

$fdate = fn($v) => $v ? $v->format('d.m.Y') : '—';
$fdatetime = fn($v) => $v ? $v->format('d.m.Y H:i') : '—';
$fnum = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>"
       class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Lista zleceń
    </a>
    <h4 class="mb-0 fw-semibold ms-2">
        <?= h($order->symbol) ?>
        <?php if (!empty($order->ozn)): ?>
            <span class="text-muted fw-normal fs-6 ms-1"><?= h($order->ozn) ?></span>
        <?php endif; ?>
    </h4>
    <span class="badge <?= $statusLabel['class'] ?> ms-2"><?= h($statusLabel['label']) ?></span>
</div>

<div class="row g-3">

    <!-- Dane ogólne -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Dane zlecenia</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="w-40 ps-3">Symbol</th><td><?= h($order->symbol) ?></td></tr>
                        <?php if (!empty($order->ozn)): ?>
                        <tr><th class="ps-3">Oznaczenie</th><td><?= h($order->ozn) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->teczka)): ?>
                        <tr><th class="ps-3">Teczka</th><td><?= h($order->teczka) ?></td></tr>
                        <?php endif; ?>
                        <tr><th class="ps-3">Data dokumentu</th><td><?= $fdate($order->date_doc) ?></td></tr>
                        <?php if ($order->date_ship): ?>
                        <tr><th class="ps-3">Data wysyłki</th><td><?= $fdatetime($order->date_ship) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($order->date_deadline): ?>
                        <tr><th class="ps-3">Termin</th><td><?= $fdatetime($order->date_deadline) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($order->date_delivery): ?>
                        <tr><th class="ps-3">Data dostawy</th><td><?= $fdatetime($order->date_delivery) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->title1)): ?>
                        <tr><th class="ps-3">Tytuł 1</th><td><?= h($order->title1) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->title2)): ?>
                        <tr><th class="ps-3">Tytuł 2</th><td><?= h($order->title2) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->cargo_type)): ?>
                        <tr><th class="ps-3">Rodzaj ładunku</th><td><?= h($order->cargo_type) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->notes)): ?>
                        <tr><th class="ps-3">Uwagi</th><td style="white-space:pre-wrap"><?= h($order->notes) ?></td></tr>
                        <?php endif; ?>
                        <tr><th class="ps-3">Wystawił</th><td><?= h($order->nick_created) ?></td></tr>
                        <?php if (!empty($order->nick_modified)): ?>
                        <tr><th class="ps-3">Zmienił</th><td><?= h($order->nick_modified) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($order->speed_modified_at): ?>
                        <tr><th class="ps-3">Ostatnia zmiana (Speed)</th><td><?= $fdatetime($order->speed_modified_at) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Nabywca -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Nabywca</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="w-40 ps-3">Nazwa</th><td><?= h($order->buyer_name) ?></td></tr>
                        <?php if (!empty($order->buyer_nip)): ?>
                        <tr><th class="ps-3">NIP</th><td><?= h($order->buyer_nip) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->buyer_street)): ?>
                        <tr><th class="ps-3">Ulica</th><td><?= h($order->buyer_street) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->buyer_postal_code) || !empty($order->buyer_city)): ?>
                        <tr><th class="ps-3">Miejsc.</th><td><?= h(trim($order->buyer_postal_code . ' ' . $order->buyer_city)) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->buyer_country)): ?>
                        <tr><th class="ps-3">Kraj</th><td><?= h($order->buyer_country) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->buyer_email)): ?>
                        <tr><th class="ps-3">E-mail</th><td><a href="mailto:<?= h($order->buyer_email) ?>"><?= h($order->buyer_email) ?></a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Wartości -->
        <div class="card mt-3">
            <div class="card-header fw-semibold">Wartości</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="w-40 ps-3">Netto</th><td><?= $fnum($order->netto) ?> <?= h($order->currency ?? 'PLN') ?></td></tr>
                        <tr><th class="ps-3">VAT</th><td><?= $fnum($order->vat) ?> <?= h($order->currency ?? 'PLN') ?></td></tr>
                        <tr><th class="ps-3">Brutto</th><td class="fw-semibold"><?= $fnum($order->brutto) ?> <?= h($order->currency ?? 'PLN') ?></td></tr>
                        <?php if ($order->exchange_rate && $order->exchange_rate != 1): ?>
                        <tr><th class="ps-3">Kurs</th><td><?= number_format((float)$order->exchange_rate, 6, ',', ' ') ?><?= !empty($order->exchange_table) ? ' (tabela ' . h($order->exchange_table) . ')' : '' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Trasa -->
    <?php if (!empty($order->route_description) || !empty($order->place_from_name) || !empty($order->place_to_name)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Trasa</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php if (!empty($order->route_description)): ?>
                        <tr><th class="w-20 ps-3">Opis trasy</th><td><?= h($order->route_description) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->place_from_name)): ?>
                        <tr><th class="ps-3">Miejsce załadunku</th>
                            <td><?= h($order->place_from_name) ?><?= !empty($order->place_from_country) ? ' (' . h($order->place_from_country) . ')' : '' ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($order->place_to_name)): ?>
                        <tr><th class="ps-3">Miejsce rozładunku</th>
                            <td><?= h($order->place_to_name) ?><?= !empty($order->place_to_country) ? ' (' . h($order->place_to_country) . ')' : '' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Raw JSON -->
    <?php if ($rawData !== null): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Surowe dane Speed (GLO_*)</span>
                <button class="btn btn-sm btn-outline-secondary py-0"
                        data-bs-toggle="collapse" data-bs-target="#raw-json">
                    Pokaż / Ukryj
                </button>
            </div>
            <div class="collapse" id="raw-json">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 font-monospace small">
                        <tbody>
                            <?php foreach ($rawData as $k => $v): ?>
                            <tr>
                                <th class="ps-3 text-nowrap text-muted fw-normal" style="width:220px"><?= h($k) ?></th>
                                <td><?= h((string)($v ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $offers
 * @var string $status
 */
$this->assign('title', __('Oferty cenowe'));

$statusLabels = [
    'draft'    => ['Szkic',       'bg-secondary'],
    'sent'     => ['Wysłana',     'bg-info'],
    'viewed'   => ['Otworzył',    'bg-primary'],
    'accepted' => ['Zaakceptowana','bg-success'],
    'rejected' => ['Odrzucona',   'bg-danger'],
    'expired'  => ['Wygasła',     'bg-dark'],
];

$fmtMoney = static fn ($v, $cur = 'PLN') => number_format((float)$v, 2, ',', ' ') . ' ' . $cur;
$fmtDate  = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;
$fmtDT    = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : (string)$v;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Oferty cenowe') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Oferty wygenerowane z planera tras. Klient akceptuje przez link — bez logowania.') ?>
        </p>
    </div>
</div>

<!-- Filtr statusu -->
<div class="card mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex flex-wrap gap-2 align-items-center']) ?>
            <span class="small text-muted"><?= __('Status') ?>:</span>
            <?php foreach (['' => __('Wszystkie'), 'draft' => __('Szkic'), 'sent' => __('Wysłane'), 'viewed' => __('Otworzone'), 'accepted' => __('Zaakceptowane'), 'rejected' => __('Odrzucone')] as $val => $lbl):
                $isActive = $status === $val;
            ?>
                <button type="submit" name="status" value="<?= h($val) ?>"
                        class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= h($lbl) ?>
                </button>
            <?php endforeach ?>
        <?= $this->Form->end() ?>
    </div>
</div>

<?php if (count($offers) === 0): ?>
    <div class="alert alert-info"><?= __('Brak ofert. Wystawisz je z planera tras.') ?></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Kontrahent') ?></th>
                        <th><?= __('Plan / trasa') ?></th>
                        <th><?= __('Kwota') ?></th>
                        <th><?= __('Status') ?></th>
                        <th><?= __('Wysłano') ?></th>
                        <th><?= __('Ważna do') ?></th>
                        <th class="text-end" style="width:100px"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $o):
                        [$sLabel, $sCls] = $statusLabels[(string)$o->status] ?? [(string)$o->status, 'bg-secondary'];
                    ?>
                        <tr>
                            <td>
                                <?php if (!empty($o->contractor)): ?>
                                    <div><?= h($o->contractor->name) ?></div>
                                    <?php if (!empty($o->contractor->nip)): ?>
                                        <span class="badge bg-secondary-subtle text-body">NIP <?= h($o->contractor->nip) ?></span>
                                    <?php endif ?>
                                <?php elseif (!empty($o->sent_to_email)): ?>
                                    <div class="small text-muted"><?= h($o->sent_to_email) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if (!empty($o->route_plan)): ?>
                                    <div><?= h($o->route_plan->name) ?></div>
                                    <?php if (!empty($o->route_plan->distance_km)): ?>
                                        <span class="text-muted small"><?= number_format((float)$o->route_plan->distance_km, 0, ',', ' ') ?> km</span>
                                    <?php endif ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td><strong><?= h($fmtMoney($o->price, $o->currency)) ?></strong></td>
                            <td><span class="badge <?= $sCls ?>"><?= h($sLabel) ?></span></td>
                            <td class="small"><?= h($fmtDT($o->sent_at)) ?></td>
                            <td class="small"><?= h($fmtDate($o->valid_until)) ?></td>
                            <td class="text-end">
                                <?= $this->Html->link('<i class="ri-eye-line"></i>', ['action' => 'view', $o->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('Szczegóły')]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <ul class="pagination pagination-sm">
            <?= $this->Paginator->prev() ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next() ?>
        </ul>
    </div>
<?php endif ?>

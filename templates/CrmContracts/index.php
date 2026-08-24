<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\CrmContract> $rows
 * @var array $expiring
 * @var string $q
 * @var string|null $active
 */
$this->assign('title', __('Kontrakty ramowe'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1 fw-semibold"><i class="ri-file-list-3-line me-1 text-primary"></i><?= __('Kontrakty ramowe') ?></h4>
        <div class="text-muted small"><?= sprintf(__('Umowy z powtarzalnymi klientami. Auto-prefill ceny w %s przy nowych zleceniach.'), '<code>/zlecenia/dodaj</code>') ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success">
        <i class="ri-add-line me-1"></i><?= __('Nowy kontrakt') ?>
    </a>
</div>

<?php if (!empty($expiring)): ?>
    <div class="alert alert-warning">
        <i class="ri-alarm-warning-line me-1"></i>
        <strong><?= sprintf(__('%d kontraktów wygasa w ciągu 30 dni:'), count($expiring)) ?></strong>
        <ul class="mb-0 small mt-1">
            <?php foreach ($expiring as $e): ?>
                <li>
                    <a href="<?= $this->Url->build(['action' => 'edit', $e->id]) ?>"><?= h($e->name) ?></a>
                    · <?= h($e->contractor_name ?: $e->contractor_nip) ?>
                    · wygasa <strong><?= h($e->valid_to->format('d.m.Y')) ?></strong>
                    (<?= (int)$e->getDaysToExpire() ?> dni)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-2 align-items-center']) ?>
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" value="<?= h($q) ?>"
                       placeholder="<?= __('Nazwa, klient, NIP, miasto…') ?>">
            </div>
            <div class="col-md-3">
                <select name="active" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <option value="1" <?= $active === '1' ? 'selected' : '' ?>><?= __('Aktywne') ?></option>
                    <option value="0" <?= $active === '0' ? 'selected' : '' ?>><?= __('Nieaktywne') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary"><?= __('Filtruj') ?></button>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th><?= __('Nazwa') ?></th>
                    <th><?= __('Klient') ?></th>
                    <th>NIP</th>
                    <th><?= __('Trasa') ?></th>
                    <th class="text-end"><?= __('Cena netto') ?></th>
                    <th><?= __('Waluta') ?></th>
                    <th><?= __('Wolumen') ?></th>
                    <th><?= __('Ważność') ?></th>
                    <th><?= __('Status') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4"><?= __('Brak kontraktów.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $vp = $r->getVolumeUsedPct();
                        $dte = $r->getDaysToExpire();
                    ?>
                    <tr onclick="location.href='<?= $this->Url->build(['action' => 'edit', $r->id]) ?>'" style="cursor:pointer;">
                        <td class="fw-semibold"><?= h($r->name) ?></td>
                        <td><?= h($r->contractor_name ?: '—') ?></td>
                        <td><code><?= h($r->contractor_nip) ?></code></td>
                        <td class="small"><?= h($r->getRouteLabel()) ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$r->price_netto, 2, ',', ' ') ?></td>
                        <td><?= h($r->currency) ?></td>
                        <td>
                            <?php if ($vp !== null): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <div style="width:50px; height:6px; background:#f1f3f5; border-radius:3px; overflow:hidden;">
                                        <div style="width:<?= min(100, $vp) ?>%; height:100%;
                                             background:<?= $vp >= 90 ? '#dc3545' : ($vp >= 70 ? '#f59e0b' : '#94C81F') ?>;"></div>
                                    </div>
                                    <span class="small"><?= $r->used_volume ?>/<?= $r->committed_volume ?> (<?= $vp ?>%)</span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small"><?= $r->used_volume ?> zrealizowane</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($r->valid_to): ?>
                                <?= h($r->valid_to->format('d.m.Y')) ?>
                                <?php if ($dte !== null && $dte >= 0 && $dte <= 30): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?= $dte ?>d</span>
                                <?php elseif ($dte !== null && $dte < 0): ?>
                                    <span class="badge bg-danger ms-1"><?= __('wygasł') ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted"><?= __('bezterminowo') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r->is_active): ?>
                                <span class="badge bg-success">Aktywny</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nieaktywny</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end" onclick="event.stopPropagation();">
                            <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $r->id],
                                ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger',
                                 'confirm' => __('Usunąć kontrakt?')]
                            ) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

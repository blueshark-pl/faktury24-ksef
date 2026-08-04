<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PalletType[] $pallets
 * @var string $search
 * @var string $mfr
 * @var array $mfrs
 */
$this->assign('title', __('Katalog palet'));
$identity = $this->request->getAttribute('identity');
$isAdmin  = (bool)($identity?->get('is_admin') ?? false);
$companyId = $identity?->get('company_id');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-pallet-line me-1 text-warning"></i><?= __('Katalog palet i opakowań') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-primary">
        <i class="ri-add-line me-1"></i><?= __('Nowa paleta (custom)') ?>
    </a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm" placeholder="<?= __('Szukaj: kod, nazwa, opis...') ?>">
    </div>
    <div class="col-md-3">
        <select name="manufacturer" class="form-select form-select-sm">
            <option value="">— <?= __('Wszyscy producenci') ?> —</option>
            <?php foreach ($mfrs as $m): ?>
                <option value="<?= h($m) ?>" <?= $mfr === $m ? 'selected' : '' ?>><?= h($m) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ri-filter-line me-1"></i>Filtruj</button>
    </div>
    <?php if ($search || $mfr): ?>
    <div class="col-md-2">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary w-100"><i class="ri-close-line me-1"></i>Wyczyść</a>
    </div>
    <?php endif; ?>
</form>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:70px">Zdjęcie</th>
                <th style="width:120px">Kod</th>
                <th>Nazwa</th>
                <th>Producent</th>
                <th>Wymiary (mm)</th>
                <th class="text-end">Nośność</th>
                <th>Material</th>
                <th class="text-center">Pooling</th>
                <th style="width:100px" class="text-end">Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pallets as $p): ?>
                <tr class="<?= $p->company_id === null ? '' : 'table-info' ?>" title="<?= $p->company_id === null ? 'globalna' : 'custom firmy' ?>">
                    <td>
                        <?php if ($p->image_path): ?>
                            <img src="<?= h($p->image_path) ?>" style="max-width:60px;max-height:60px;border-radius:.2rem">
                        <?php else: ?>
                            <div class="text-muted small" style="width:60px;height:60px;background:#f3f4f6;border-radius:.2rem;display:flex;align-items:center;justify-content:center"><i class="ri-image-2-line"></i></div>
                        <?php endif; ?>
                    </td>
                    <td><code class="fw-bold"><?= h($p->code) ?></code></td>
                    <td>
                        <?= h($p->name) ?>
                        <?php if ($p->description): ?>
                            <div class="small text-muted"><?= h(mb_strimwidth($p->description, 0, 80, '…')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p->manufacturer): ?>
                            <span class="badge bg-secondary-subtle text-secondary"><?= h($p->manufacturer) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($p->length_mm && $p->width_mm): ?>
                            <?= h($p->length_mm) ?>×<?= h($p->width_mm) ?><?php if ($p->height_mm): ?>×<?= h($p->height_mm) ?><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end small"><?= $p->load_capacity_kg ? number_format($p->load_capacity_kg, 0, ',', ' ') . ' kg' : '<span class="text-muted">-</span>' ?></td>
                    <td class="small">
                        <?= h($p->material ?? '-') ?>
                        <?php if ($p->color): ?><span class="text-muted"> · <?= h($p->color) ?></span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?= $p->is_pooling ? '<i class="ri-recycle-line text-success" title="Pooling"></i>' : '' ?>
                    </td>
                    <td class="text-end">
                        <?php if ($p->company_id !== null || $isAdmin): ?>
                            <a href="<?= $this->Url->build(['action' => 'edit', $p->id]) ?>" class="btn btn-sm btn-outline-primary"><i class="ri-pencil-line"></i></a>
                            <?= $this->Form->postLink(
                                '<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $p->id],
                                ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('Usunąć paletę {0}?', $p->code)]
                            ) ?>
                        <?php else: ?>
                            <span class="badge bg-light text-muted small" title="Globalne — tylko admin"><i class="ri-lock-line"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Driver> $rows
 * @var string $q
 */
$this->assign('title', __('Kierowcy'));
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-user-3-line me-1"></i><?= __('Kierowcy') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i><?= __('Dodaj kierowcę') ?>
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small mb-1"><?= __('Szukaj') ?></label>
                <input type="search" name="q" class="form-control form-control-sm"
                       value="<?= h($q) ?>" placeholder="<?= __('Imię, nazwisko, telefon, email…') ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary btn-sm w-100"><i class="ri-filter-line me-1"></i><?= __('Filtruj') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3"><?= __('Imię i nazwisko') ?></th>
                    <th><?= __('Kontakt') ?></th>
                    <th><?= __('Kategorie') ?></th>
                    <th class="text-end"><?= __('Stawka/h') ?></th>
                    <th class="text-end"><?= __('Dieta/dzień') ?></th>
                    <th class="text-center"><?= __('Kompetencje') ?></th>
                    <th class="text-center"><?= __('Status') ?></th>
                    <th class="text-end pe-3" style="width:120px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows) || $rows->count() === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="ri-user-3-line d-block mb-2" style="font-size:2.4rem"></i>
                        <?= __('Brak kierowców. Dodaj pierwszego aby uwzględniać stawki w kalkulacji tras.') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $d): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bg-info-subtle text-info border d-inline-flex align-items-center justify-content-center"
                                          style="width:32px;height:32px;border-radius:50%">
                                        <i class="ri-user-3-line"></i>
                                    </span>
                                    <div>
                                        <div class="fw-semibold"><?= h($d->full_name) ?></div>
                                        <?php if ($d->experience_years): ?>
                                            <div class="text-muted small"><?= h($d->experience_years) ?> <?= __('lat doświadczenia') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <?php if (!empty($d->phone)): ?>
                                    <div><i class="ri-phone-line me-1 text-muted"></i><?= h($d->phone) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($d->email)): ?>
                                    <div class="text-muted"><i class="ri-mail-line me-1"></i><?= h($d->email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d->license_categories)): ?>
                                    <?php foreach (explode(',', $d->license_categories) as $cat): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border me-1"><?= h(trim($cat)) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($d->hourly_rate_pln): ?>
                                    <strong><?= number_format((float)$d->hourly_rate_pln, 2, ',', ' ') ?></strong>
                                    <small class="text-muted">PLN</small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($d->per_diem_pln): ?>
                                    <?= number_format((float)$d->per_diem_pln, 2, ',', ' ') ?> <small class="text-muted">PLN</small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($d->adr_certified)): ?>
                                    <span class="badge bg-danger-subtle text-danger border" title="<?= __('Certyfikat ADR') ?>">ADR</span>
                                <?php endif; ?>
                                <?php if (!empty($d->languages)): ?>
                                    <?php foreach (explode(',', $d->languages) as $lang): ?>
                                        <span class="badge bg-light text-dark border ms-1" style="font-size:.65rem"><?= h(strtoupper(trim($lang))) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($d->is_default): ?>
                                    <span class="badge bg-primary"><i class="ri-star-fill"></i> <?= __('domyślny') ?></span>
                                <?php elseif ($d->is_active): ?>
                                    <span class="badge bg-success-subtle text-success border"><?= __('aktywny') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= __('nieaktywny') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="<?= $this->Url->build(['action' => 'edit', $d->id]) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-edit-line"></i></a>
                                <form method="post" action="<?= $this->Url->build(['action' => 'delete', $d->id]) ?>" style="display:inline"
                                      onsubmit="return confirm('<?= __('Usunąć kierowcę?') ?>')">
                                    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

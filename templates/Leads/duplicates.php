<?php
/**
 * @var \App\View\AppView $this
 * @var array $pairs
 */
$this->assign('title', __('CRM - Duplikaty'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1 fw-semibold"><i class="ri-file-copy-2-line me-1 text-warning"></i><?= __('Wykryte duplikaty') ?></h4>
        <div class="text-muted small">
            <?= __('Pary lead-ów z tym samym NIP / email / telefonem / bardzo podobną nazwą (Levenshtein ≤ 2).') ?>
        </div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Do listy') ?>
    </a>
</div>

<?php if (empty($pairs)): ?>
    <div class="alert alert-success">
        <i class="ri-check-double-line"></i> <?= __('Brak wykrytych duplikatów. Twoja baza jest czysta.') ?>
    </div>
<?php else: ?>
    <div class="alert alert-info small">
        <strong><?= sprintf(__('Znaleziono %d par do przejrzenia.'), count($pairs)) ?></strong>
        <?= __('Kliknij „Scal" żeby wybrać które wartości zachować i połączyć w jednego leada.') ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle small">
                <thead class="table-light">
                    <tr>
                        <th style="width:50%;"><?= __('Lead A') ?></th>
                        <th style="width:50%;"><?= __('Lead B') ?></th>
                        <th class="text-end" style="width:120px;"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pairs as $p):
                        $a = $p['a']; $b = $p['b'];
                    ?>
                    <tr>
                        <td>
                            <a href="<?= $this->Url->build(['action' => 'view', $a->id]) ?>" class="fw-semibold text-dark text-decoration-none">
                                <?= h($a->company_name) ?>
                            </a>
                            <div class="text-muted small">
                                <?php if ($a->nip): ?>NIP: <?= h($a->nip) ?><?php endif; ?>
                                <?php if ($a->email): ?> · <?= h($a->email) ?><?php endif; ?>
                                <?php if ($a->phone): ?> · <?= h($a->phone) ?><?php endif; ?>
                            </div>
                            <div class="small">
                                <span class="badge bg-light text-dark border"><?= h($a->stage) ?></span>
                                <?= (int)$a->probability ?>%
                                <?php if ($a->value_pln): ?> · <?= number_format((float)$a->value_pln, 0, ',', ' ') ?> zł<?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?= $this->Url->build(['action' => 'view', $b->id]) ?>" class="fw-semibold text-dark text-decoration-none">
                                <?= h($b->company_name) ?>
                            </a>
                            <div class="text-muted small">
                                <?php if ($b->nip): ?>NIP: <?= h($b->nip) ?><?php endif; ?>
                                <?php if ($b->email): ?> · <?= h($b->email) ?><?php endif; ?>
                                <?php if ($b->phone): ?> · <?= h($b->phone) ?><?php endif; ?>
                            </div>
                            <div class="small">
                                <span class="badge bg-light text-dark border"><?= h($b->stage) ?></span>
                                <?= (int)$b->probability ?>%
                                <?php if ($b->value_pln): ?> · <?= number_format((float)$b->value_pln, 0, ',', ' ') ?> zł<?php endif; ?>
                            </div>
                            <div class="mt-1">
                                <?php foreach ($p['reasons'] as $r): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border"><?= h($r) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="<?= $this->Url->build(['action' => 'mergeReview', '?' => ['a' => $a->id, 'b' => $b->id]]) ?>"
                               class="btn btn-sm btn-primary">
                                <i class="ri-git-merge-line"></i> <?= __('Scal') ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

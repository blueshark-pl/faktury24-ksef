<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BankStatementImport[] $imports
 * @var array $stats
 * @var string $title
 */
$this->assign('title', 'Wyciągi bankowe');

$fdate     = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fdatetime = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : '—';
$fnum      = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Wyciągi bankowe <span class="text-muted fs-6 fw-normal">MT940</span></h4>
    <?= $this->Html->link(
        '<i class="ri-upload-2-line me-1"></i> Importuj MT940',
        ['action' => 'import'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<!-- Zakładki -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-archive-line me-1"></i> Importy
            <?php if (!empty($imports)): ?>
                <span class="badge bg-secondary ms-1"><?= count($imports) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'transactions']) ?>">
            <i class="ri-exchange-line me-1"></i> Wszystkie transakcje
        </a>
    </li>
</ul>

<?= $this->Flash->render() ?>

<!-- Kafelki statystyk -->
<?php if (!empty($stats) && $stats['total'] > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-exchange-line fs-5 text-secondary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
                    <div class="text-muted small">Wszystkich transakcji</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'transactions', '?' => ['status' => 'proposed']]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $stats['proposed'] > 0 ? 'border-warning border' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-alert-line fs-5 text-warning"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 <?= $stats['proposed'] > 0 ? 'text-warning' : '' ?>"><?= $stats['proposed'] ?></div>
                    <div class="text-muted small">Do potwierdzenia</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'transactions', '?' => ['status' => 'unmatched']]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-question-mark fs-5 text-secondary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1"><?= $stats['unmatched'] ?></div>
                    <div class="text-muted small">Niedopasowane</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'transactions', '?' => ['status' => 'matched']]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-checkbox-circle-line fs-5 text-success"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-success"><?= $stats['matched'] ?></div>
                    <div class="text-muted small">Dopasowane</div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (empty($imports)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="ri-bank-line fs-1 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Brak importów</h5>
            <p class="text-muted mb-4">Nie zaimportowano jeszcze żadnego wyciągu bankowego.</p>
            <?= $this->Html->link(
                '<i class="ri-upload-2-line me-1"></i> Importuj pierwszy wyciąg MT940',
                ['action' => 'import'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Data importu</th>
                        <th>Plik</th>
                        <th>Numer rachunku</th>
                        <th>Okres</th>
                        <th class="text-end">Saldo otwarcia</th>
                        <th class="text-end">Saldo zamknięcia</th>
                        <th class="text-center">Łącznie</th>
                        <th class="text-center">Nowe</th>
                        <th class="text-center">Duplikaty</th>
                        <th class="pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($imports as $import): ?>
                    <tr>
                        <td class="ps-3 text-nowrap text-muted small"><?= $fdatetime($import->created) ?></td>
                        <td>
                            <?= $this->Html->link(
                                h($import->filename ?? '(bez nazwy)'),
                                ['action' => 'view', $import->id],
                                ['class' => 'fw-semibold text-decoration-none']
                            ) ?>
                        </td>
                        <td>
                            <code class="small text-muted"><?= h($import->account_number ?? '—') ?></code>
                        </td>
                        <td class="text-nowrap small">
                            <?php if ($import->statement_from && $import->statement_to): ?>
                                <?= $fdate($import->statement_from) ?> – <?= $fdate($import->statement_to) ?>
                            <?php elseif ($import->statement_from): ?>
                                <?= $fdate($import->statement_from) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap small">
                            <?= $import->opening_balance !== null
                                ? $fnum($import->opening_balance) . ' ' . h($import->currency ?? 'PLN')
                                : '—' ?>
                        </td>
                        <td class="text-end text-nowrap small">
                            <?= $import->closing_balance !== null
                                ? $fnum($import->closing_balance) . ' ' . h($import->currency ?? 'PLN')
                                : '—' ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary border"><?= $import->transaction_count ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success"><?= $import->new_count ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($import->duplicate_count > 0): ?>
                                <span class="badge bg-warning-subtle text-warning border"><?= $import->duplicate_count ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <?= $this->Html->link(
                                    '<i class="ri-eye-line"></i>',
                                    ['action' => 'view', $import->id],
                                    ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => 'Pokaż transakcje']
                                ) ?>
                                <?= $this->Form->postLink(
                                    '<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $import->id],
                                    [
                                        'class'   => 'btn btn-sm btn-outline-danger',
                                        'escape'  => false,
                                        'title'   => 'Usuń import',
                                        'confirm' => 'Usunąć import „' . addslashes($import->filename ?? $import->id) . '" i wszystkie jego transakcje?',
                                    ]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

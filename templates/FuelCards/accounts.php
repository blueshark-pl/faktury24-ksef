<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $accounts
 * @var string $title
 */
$this->assign('title', $title);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-gas-station-line me-1 text-warning"></i> E100 — Konta
    </h4>
    <?= $this->Html->link(
        '<i class="ri-add-line me-1"></i> Dodaj konto',
        ['action' => 'addAccount'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-list-check me-1"></i> Transakcje
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'accounts']) ?>">
            <i class="ri-settings-3-line me-1"></i> Konta
        </a>
    </li>
</ul>

<?php if ($accounts->isEmpty()): ?>
    <div class="alert alert-info">
        <i class="ri-information-line me-1"></i>
        Brak kont E100.
        <?= $this->Html->link('Dodaj pierwsze konto', ['action' => 'addAccount'], ['class' => 'alert-link']) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Nazwa</th>
                    <th>Login</th>
                    <th>Klient E100</th>
                    <th>Waluta</th>
                    <th>Ostatnia sync</th>
                    <th>Token ważny do</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $acc): ?>
                    <tr>
                        <td class="fw-semibold"><?= h($acc->label) ?></td>
                        <td class="font-monospace"><?= h($acc->username) ?></td>
                        <td><?= h($acc->client_code ?? '—') ?><?php if ($acc->fullname): ?><br><small class="text-muted"><?= h($acc->fullname) ?></small><?php endif; ?></td>
                        <td><?= h($acc->defcur ?? 'EUR') ?></td>
                        <td><?= $acc->last_sync_at ? $acc->last_sync_at->format('d.m.Y H:i') : '—' ?></td>
                        <td><?= $acc->token_expires_at ? $acc->token_expires_at->format('d.m.Y H:i') : '—' ?></td>
                        <td>
                            <?php if ($acc->is_active): ?>
                                <span class="badge bg-success">Aktywne</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nieaktywne</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?= $this->Html->link('<i class="ri-edit-line"></i>', ['action' => 'editAccount', $acc->id], ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false, 'title' => 'Edytuj']) ?>
                            <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'deleteAccount', $acc->id], [
                                'class'   => 'btn btn-outline-danger btn-sm ms-1',
                                'escape'  => false,
                                'title'   => 'Usuń konto i wszystkie transakcje',
                                'confirm' => 'Usunąć konto "' . h($acc->label) . '" wraz ze wszystkimi transakcjami? Operacja jest nieodwracalna.',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

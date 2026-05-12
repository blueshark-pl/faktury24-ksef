<?php
/**
 * AJAX: historia maili powitalnych dla usera (wstawiana w modal).
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $user
 * @var \Cake\ORM\ResultSet $logs
 */
$logsArr = $logs->toArray();
$name = trim(((string)$user->first_name) . ' ' . ((string)$user->last_name));
?>

<div class="mb-3">
    <strong><?= __('Użytkownik') ?>:</strong> <?= h($user->email) ?>
    <?php if ($name !== ''): ?>
        <span class="text-muted">(<?= h($name) ?>)</span>
    <?php endif; ?>
</div>

<?php if (empty($logsArr)): ?>
    <div class="text-center text-muted py-4">
        <i class="ri-mail-line" style="font-size:1.6em"></i><br>
        <?= __('Brak wysłanych maili powitalnych do tego użytkownika.') ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:160px"><?= __('Data') ?></th>
                    <th style="width:60px" class="text-center"><?= __('Język') ?></th>
                    <th><?= __('Temat') ?></th>
                    <th style="width:90px" class="text-center"><?= __('Status') ?></th>
                    <th class="pe-3" style="width:180px"><?= __('Wysłał') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logsArr as $log): ?>
                <tr>
                    <td class="ps-3 text-muted small">
                        <?= $log->created ? $log->created->i18nFormat('yyyy-MM-dd HH:mm') : '—' ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary-transparent"><?= h(strtoupper($log->lang)) ?></span>
                    </td>
                    <td class="small"><?= h($log->subject ?? '—') ?></td>
                    <td class="text-center">
                        <?php if ($log->status === 'sent'): ?>
                            <span class="badge bg-success-transparent"><i class="ri-check-line"></i> <?= __('Wysłano') ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger-transparent" title="<?= h($log->error_message ?? '') ?>">
                                <i class="ri-close-line"></i> <?= __('Błąd') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="pe-3 small text-muted">
                        <?= h($log->sender_email ?: '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

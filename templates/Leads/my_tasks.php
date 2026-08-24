<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\LeadActivity> $tasks
 * @var iterable<\App\Model\Entity\Lead> $nextActions
 * @var bool $onlyMine
 * @var int $range
 * @var int $overdueCnt
 * @var int $todayCnt
 * @var int $upcomingCnt
 */
$this->assign('title', __('Moje zadania CRM'));
$today = new \DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');

function daysBadge(\DateTimeInterface $due, \DateTimeInterface $today, \DateTimeInterface $tomorrow): array {
    if ($due < $today) return ['bg-danger', __('PRZETERMINOWANY')];
    if ($due < $tomorrow) return ['bg-warning text-dark', __('DZIŚ')];
    $diff = (int)$today->diff($due)->days;
    if ($diff <= 3) return ['bg-info', __('za ') . $diff . ' ' . __('dni')];
    return ['bg-light text-dark border', __('za ') . $diff . ' ' . __('dni')];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-semibold">
            <i class="ri-checkbox-line me-1 text-success"></i>
            <?= $onlyMine ? __('Moje zadania CRM') : __('Wszystkie zadania CRM') ?>
        </h4>
        <div class="text-muted small">
            <?= sprintf(__('Zakres: %d dni w przód + 30 dni przeterminowane'), $range) ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group btn-group-sm" role="group">
            <?php foreach ([7, 14, 30, 60, 90] as $d): ?>
                <a href="<?= $this->Url->build(['action' => 'myTasks', '?' => array_merge($this->request->getQuery(), ['days' => $d])]) ?>"
                   class="btn <?= $range === $d ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $d ?>d</a>
            <?php endforeach; ?>
        </div>
        <a href="<?= $this->Url->build(['action' => 'myTasks', '?' => array_merge($this->request->getQuery(), ['all' => $onlyMine ? '1' : '0'])]) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="ri-<?= $onlyMine ? 'group' : 'user' ?>-line"></i>
            <?= $onlyMine ? __('Zespół') : __('Tylko moje') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-list-check"></i> <?= __('Do leadów') ?>
        </a>
    </div>
</div>

<!-- KPI -->
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted"><?= __('Przeterminowane') ?></div>
                        <div class="fs-3 fw-bold text-danger"><?= $overdueCnt ?></div>
                    </div>
                    <i class="ri-alarm-warning-line fs-2 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted"><?= __('Dzisiaj') ?></div>
                        <div class="fs-3 fw-bold text-warning"><?= $todayCnt ?></div>
                    </div>
                    <i class="ri-calendar-todo-line fs-2 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted"><?= __('Nadchodzące') ?></div>
                        <div class="fs-3 fw-bold text-info"><?= $upcomingCnt ?></div>
                    </div>
                    <i class="ri-calendar-check-line fs-2 text-info opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tasks -->
<div class="card mb-3">
    <div class="card-header bg-white fw-semibold"><i class="ri-task-line"></i> <?= __('Zadania (activity_type=task)') ?></div>
    <div class="table-responsive">
        <?php if (count($tasks) === 0): ?>
            <div class="text-center text-muted py-4"><?= __('Brak zaplanowanych zadań w tym zakresie.') ?></div>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"></th>
                        <th><?= __('Termin') ?></th>
                        <th><?= __('Lead') ?></th>
                        <th><?= __('Zadanie') ?></th>
                        <th><?= __('Autor') ?></th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t):
                        [$badgeCls, $badgeLbl] = daysBadge(
                            new \DateTimeImmutable($t->due_at->format('c')),
                            $today, $tomorrow
                        );
                        $author = trim(($t->user?->first_name ?? '') . ' ' . ($t->user?->last_name ?? ''));
                    ?>
                    <tr data-task-id="<?= h($t->id) ?>">
                        <td>
                            <button class="btn btn-sm btn-outline-success task-done-btn" title="<?= __('Oznacz jako wykonane') ?>">
                                <i class="ri-check-line"></i>
                            </button>
                        </td>
                        <td>
                            <span class="badge <?= $badgeCls ?>"><?= h($badgeLbl) ?></span>
                            <div class="small text-muted"><?= h($t->due_at->format('d.m.Y H:i')) ?></div>
                        </td>
                        <td>
                            <a href="<?= $this->Url->build(['action' => 'view', $t->lead->id ?? '']) ?>" class="fw-semibold">
                                <?= h($t->lead->company_name ?? '—') ?>
                            </a>
                            <div class="small text-muted">
                                <?= h(strtoupper((string)($t->lead->country_code ?? ''))) ?>
                                <?php if ($t->lead?->city): ?>· <?= h($t->lead->city) ?><?php endif; ?>
                                <?php if ($t->lead?->stage): ?>· <span class="badge bg-light text-dark border"><?= h($t->lead->stage) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($t->subject): ?><div class="fw-semibold"><?= h($t->subject) ?></div><?php endif; ?>
                            <?php if ($t->body): ?><div class="small text-muted"><?= h(mb_substr($t->body, 0, 120)) ?><?= mb_strlen($t->body) > 120 ? '…' : '' ?></div><?php endif; ?>
                        </td>
                        <td class="small"><?= h($author ?: '—') ?></td>
                        <td>
                            <a href="<?= $this->Url->build(['action' => 'view', $t->lead->id ?? '']) ?>" class="btn btn-sm btn-link">
                                <i class="ri-arrow-right-line"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Next actions z leads.next_action_at -->
<?php if (count($nextActions) > 0): ?>
<div class="card">
    <div class="card-header bg-white fw-semibold">
        <i class="ri-time-line"></i> <?= __('Zaplanowane follow-upy (leads.next_action_at)') ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= __('Termin') ?></th>
                    <th><?= __('Lead') ?></th>
                    <th><?= __('Akcja') ?></th>
                    <th><?= __('Opiekun') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nextActions as $l):
                    [$badgeCls, $badgeLbl] = daysBadge(
                        new \DateTimeImmutable($l->next_action_at->format('c')),
                        $today, $tomorrow
                    );
                    $owner = trim(($l->assigned_user?->first_name ?? '') . ' ' . ($l->assigned_user?->last_name ?? ''));
                ?>
                <tr>
                    <td>
                        <span class="badge <?= $badgeCls ?>"><?= h($badgeLbl) ?></span>
                        <div class="small text-muted"><?= h($l->next_action_at->format('d.m.Y H:i')) ?></div>
                    </td>
                    <td>
                        <a href="<?= $this->Url->build(['action' => 'view', $l->id]) ?>" class="fw-semibold">
                            <?= h($l->company_name) ?>
                        </a>
                        <div class="small text-muted"><span class="badge bg-light text-dark border"><?= h($l->stage) ?></span></div>
                    </td>
                    <td><?= h($l->next_action_description ?: '—') ?></td>
                    <td class="small"><?= h($owner ?: '—') ?></td>
                    <td>
                        <a href="<?= $this->Url->build(['action' => 'view', $l->id]) ?>" class="btn btn-sm btn-link">
                            <i class="ri-arrow-right-line"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    document.querySelectorAll('.task-done-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tr = btn.closest('tr');
            var id = tr.dataset.taskId;
            if (!id) return;
            var form = new FormData();
            form.append('_csrfToken', csrf);
            fetch('<?= $this->Url->build(['action' => 'taskDone']) ?>/' + id, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: form,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (j.ok) {
                    tr.style.transition = 'opacity 0.3s';
                    tr.style.opacity = '0.4';
                    tr.style.textDecoration = 'line-through';
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-check-double-line"></i>';
                    btn.classList.replace('btn-outline-success', 'btn-success');
                } else {
                    alert('<?= __('Błąd zapisu') ?>: ' + (j.error || ''));
                }
            });
        });
    });
})();
</script>

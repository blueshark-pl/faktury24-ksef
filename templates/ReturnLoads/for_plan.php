<?php
/**
 * @var \App\View\AppView $this
 * @var object $plan
 * @var iterable $candidates
 */
$this->assign('title', __('Powroty dla planu: :n', [':n' => $plan->name]));

$fmt = static fn ($dt) => $dt instanceof \DateTimeInterface ? $dt->format('d.m.Y H:i') : (string)$dt;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Ładunki powrotne') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Plan') ?>: <strong><?= h($plan->name) ?></strong>
        </p>
    </div>
    <?= $this->Form->postLink(
        '<i class="ri-search-line me-1"></i>' . __('Szukaj ponownie'),
        ['action' => 'suggest', $plan->id],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<?= $this->Flash->render() ?>

<?php if (count($candidates) === 0): ?>
    <div class="alert alert-info">
        <?= __('Brak sugestii. Kliknij „Szukaj ponownie" żeby uruchomić matching.') ?>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($candidates as $c):
            $so = $c->speed_order ?? null;
            $scoreCls = $c->match_score >= 70 ? 'text-success' : ($c->match_score >= 40 ? 'text-warning' : 'text-danger');
        ?>
            <div class="col-md-6">
                <div class="card border-<?= $c->match_score >= 70 ? 'success' : 'secondary' ?>">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span>
                            <span class="fs-4 fw-bold <?= $scoreCls ?>"><?= number_format((float)$c->match_score, 0) ?></span>
                            <small class="text-muted">/ 100</small>
                        </span>
                        <?php if ($c->status === 'suggested'): ?>
                            <?= $this->Form->postLink('<i class="ri-close-line"></i>',
                                ['action' => 'dismiss', $c->id],
                                ['class' => 'btn btn-sm btn-outline-danger', 'escape' => false,
                                 'title' => __('Odrzuć')]) ?>
                        <?php endif ?>
                    </div>
                    <div class="card-body">
                        <?php if ($so): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <code><?= h($so->symbol ?? '') ?></code>
                                <span class="text-muted small"><?= h($fmt($so->date_load)) ?></span>
                            </div>
                        <?php endif ?>
                        <div class="mb-1">
                            <i class="ri-map-pin-line text-success me-1"></i>
                            <strong><?= h($c->from_city) ?></strong>
                            <?php if ($c->from_country): ?><span class="badge bg-secondary-subtle text-body ms-1"><?= h($c->from_country) ?></span><?php endif ?>
                        </div>
                        <div class="mb-2">
                            <i class="ri-arrow-down-line text-muted me-1"></i>
                            <strong><?= h($c->to_city) ?></strong>
                            <?php if ($c->to_country): ?><span class="badge bg-secondary-subtle text-body ms-1"><?= h($c->to_country) ?></span><?php endif ?>
                        </div>

                        <div class="mt-3 pt-2 border-top small text-muted">
                            <?php if ($c->distance_from_route_km !== null): ?>
                                <div><i class="ri-road-map-line me-1"></i><?= __('Deadhead') ?>: <?= number_format((float)$c->distance_from_route_km, 0) ?> km</div>
                            <?php endif ?>
                            <?php if ($c->time_gap_hours !== null): ?>
                                <div><i class="ri-time-line me-1"></i><?= __('Odstęp') ?>: <?= number_format((float)$c->time_gap_hours, 1) ?>h</div>
                            <?php endif ?>
                        </div>

                        <?php if ($so): ?>
                            <div class="mt-3">
                                <?= $this->Html->link(
                                    '<i class="ri-external-link-line me-1"></i>' . __('Otwórz zlecenie'),
                                    ['controller' => 'SpeedOrders', 'action' => 'view', $so->id],
                                    ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'target' => '_blank']
                                ) ?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

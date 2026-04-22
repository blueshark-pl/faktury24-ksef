<?php
/**
 * @var \App\View\AppView $this
 * @var array $stations
 * @var \Cake\ORM\ResultSet $accounts
 * @var string $title
 * @var string|null $selectedAccountId
 * @var string|null $country
 * @var bool $searched
 */
$this->assign('title', $title);
$fnum = fn($v, $d = 2) => $v !== null ? number_format((float)$v, $d, ',', ' ') : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-map-pin-2-line me-1 text-danger"></i> Stacje paliw E100
    </h4>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-list-check me-1"></i> Transakcje
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'cards']) ?>">
            <i class="ri-bank-card-2-line me-1"></i> Karty
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'limits']) ?>">
            <i class="ri-funds-line me-1"></i> Limity
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'stations']) ?>">
            <i class="ri-map-pin-2-line me-1"></i> Stacje
        </a>
    </li>
</ul>

<!-- Filtry -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['url' => ['action' => 'stations'], 'type' => 'get', 'class' => 'row g-2 align-items-end']) ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Konto E100</label>
                <?= $this->Form->select('account_id',
                    array_merge(['' => '— wszystkie —'], array_column(iterator_to_array($accounts, false), 'label', 'id')),
                    ['class' => 'form-select form-select-sm', 'value' => $selectedAccountId]
                ) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Kraj (kod)</label>
                <?= $this->Form->text('country', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'np. PL, DE',
                    'value' => $country,
                    'maxlength' => 2
                ]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Brand</label>
                <?= $this->Form->text('brand', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'np. BP, Shell',
                    'value' => $this->request->getQuery('brand')
                ]) ?>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="ri-search-2-line me-1"></i> Szukaj
                </button>
                <a href="<?= $this->Url->build(['action' => 'stations']) ?>" class="btn btn-outline-secondary btn-sm ms-1">
                    <i class="ri-refresh-line"></i> Reset
                </a>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<?php if ($searched): ?>
    <?php if (empty($stations)): ?>
        <div class="alert alert-info">Brak stacji dla podanych filtrów.</div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">Znaleziono: <strong><?= count($stations) ?></strong> stacji</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle small">
                <thead class="table-light">
                    <tr>
                        <th>ID stacji</th>
                        <th>Nazwa / Brand</th>
                        <th>Adres</th>
                        <th>Miasto</th>
                        <th>Kraj</th>
                        <th>Szer. geogr. / Dług.</th>
                        <th>Kategoria</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stations as $st): ?>
                        <tr>
                            <td class="text-muted font-monospace" style="font-size:.72rem">
                                <?= h($st['stationId'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= h($st['brand'] ?? '') ?></span>
                                <?php if (!empty($st['name']) && $st['name'] !== $st['brand']): ?>
                                    <br><small class="text-muted"><?= h($st['name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= h($st['address'] ?? '—') ?></td>
                            <td><?= h($st['city'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($st['countryCode'])): ?>
                                    <span class="badge bg-secondary"><?= h($st['countryCode']) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-muted font-monospace" style="font-size:.72rem">
                                <?php if (isset($st['latitude'], $st['longitude'])): ?>
                                    <?= number_format((float)$st['latitude'], 5) ?> /
                                    <?= number_format((float)$st['longitude'], 5) ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= h($st['category'] ?? '—') ?></td>
                            <td>
                                <?php
                                $status = $st['status'] ?? '';
                                $cls    = $status === 'Active' ? 'bg-success' : 'bg-secondary';
                                ?>
                                <span class="badge <?= $cls ?>"><?= h($status ?: '—') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-light border text-muted">
        <i class="ri-information-line me-1"></i>
        Wybierz konto i opcjonalnie kraj, aby wyszukać stacje paliw z sieci E100.
    </div>
<?php endif; ?>

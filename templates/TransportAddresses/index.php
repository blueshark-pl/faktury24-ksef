<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $rows
 * @var string $q
 * @var string $type
 * @var string $country
 * @var string[] $countriesList
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $sortKey
 * @var string $sortDir
 */
$this->assign('title', 'Słownik adresów');

$sortLink = function (string $field, string $label, string $extraClass = '') use ($sortKey, $sortDir) {
    $newDir = ($sortKey === $field && $sortDir === 'asc') ? 'desc' : 'asc';
    $params = array_filter([
        'q' => $this->request->getQuery('q'),
        'type' => $this->request->getQuery('type'),
        'country' => $this->request->getQuery('country'),
        'sort' => $field,
        'direction' => $newDir,
    ], fn($v) => $v !== null && $v !== '');
    $url = $this->Url->build(['controller' => 'TransportAddresses', 'action' => 'index', '?' => $params]);
    if ($sortKey === $field) {
        $arrow = $sortDir === 'asc' ? ' <i class="ri-arrow-up-s-fill"></i>' : ' <i class="ri-arrow-down-s-fill"></i>';
        $cls = 'fw-bold text-primary';
    } else {
        $arrow = ' <i class="ri-arrow-up-down-line text-muted opacity-50" style="font-size:.85em"></i>';
        $cls = 'text-dark';
    }
    return '<th class="' . $extraClass . '"><a href="' . h($url) . '" class="text-decoration-none ' . $cls . '">' . h($label) . $arrow . '</a></th>';
};

$typeBadge = function (string $t) {
    return match ($t) {
        'loading'   => '<span class="badge bg-info-transparent">załadunek</span>',
        'unloading' => '<span class="badge bg-success-transparent">rozładunek</span>',
        'both'      => '<span class="badge bg-primary-transparent">oba</span>',
        default     => '<span class="text-muted small">—</span>',
    };
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-map-pin-line me-1"></i>Słownik adresów</h4>
        <div class="text-muted small mt-1">Unikalne miejsca załadunku i rozładunku — używane w autocomplete formularzy.</div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i>Dodaj adres
    </a>
</div>

<?= $this->Flash->render() ?>

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm"
               placeholder="Szukaj: nazwa, miasto, kod pocztowy…">
    </div>
    <div class="col-md-2">
        <select name="type" class="form-select form-select-sm">
            <option value="">Wszystkie</option>
            <option value="loading"   <?= $type === 'loading'   ? 'selected' : '' ?>>Załadunek</option>
            <option value="unloading" <?= $type === 'unloading' ? 'selected' : '' ?>>Rozładunek</option>
            <option value="both"      <?= $type === 'both'      ? 'selected' : '' ?>>Oba</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="country" class="form-select form-select-sm">
            <option value="">Wszystkie kraje</option>
            <?php foreach ($countriesList as $c): ?>
                <option value="<?= h($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary btn-sm"><i class="ri-search-line"></i></button>
        <?php if ($q !== '' || $type !== '' || $country !== ''): ?>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="ri-close-line"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="col-auto ms-auto text-muted small">
        <?php if ($total > 0): ?><?= number_format($total, 0, ',', ' ') ?> adresów<?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <?= $sortLink('name',         'Nazwa') ?>
                    <?= $sortLink('city',         'Miasto') ?>
                    <?= $sortLink('postal_code',  'Kod pocztowy') ?>
                    <?= $sortLink('country',      'Kraj') ?>
                    <?= $sortLink('address_type', 'Typ') ?>
                    <?= $sortLink('times_used',   'Użyć', 'text-end') ?>
                    <th class="text-end" style="width:140px">Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows) || count($rows->toArray()) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="ri-map-pin-line" style="font-size:2em"></i><br>
                        Brak adresów<?= $q !== '' || $type !== '' || $country !== '' ? ' dla podanych kryteriów' : '' ?>.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= h($r->name) ?></td>
                        <td><?= h($r->city ?: '—') ?></td>
                        <td><code><?= h($r->postal_code ?: '—') ?></code></td>
                        <td><span class="badge bg-secondary-transparent"><?= h($r->country) ?></span></td>
                        <td><?= $typeBadge($r->address_type) ?></td>
                        <td class="text-end fw-semibold"><?= (int)$r->times_used ?></td>
                        <td class="text-end">
                            <a href="<?= $this->Url->build(['action' => 'edit', $r->id]) ?>" class="btn btn-sm btn-outline-primary me-1" title="Edytuj">
                                <i class="ri-edit-line"></i>
                            </a>
                            <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $r->id],
                                ['class' => 'btn btn-sm btn-outline-danger', 'escape' => false,
                                 'confirm' => 'Usunąć adres "' . $r->name . '"?']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-footer d-flex justify-content-center">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => array_filter([
                            'q' => $q ?: null, 'type' => $type ?: null, 'country' => $country ?: null,
                            'sort' => $sortKey !== 'times_used' ? $sortKey : null,
                            'direction' => $sortDir !== 'desc' ? $sortDir : null,
                            'page' => $p,
                        ], fn($v) => $v !== null)]) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 * Reusable sortable column header.
 *
 * @var \App\View\AppView $this
 * @var string $field       Klucz sortowania (np. 'date_doc')
 * @var string $label       Etykieta kolumny
 * @var string $sortKey     Aktualny klucz z URL
 * @var string $sortDir     Aktualny kierunek z URL ('asc'|'desc')
 * @var array  $preserve    Dodatkowe query params do zachowania
 * @var string $extraClass  Dodatkowe klasy CSS dla <th>
 */
$field      = $field ?? '';
$label      = $label ?? '';
$sortKey    = $sortKey ?? '';
$sortDir    = $sortDir ?? 'desc';
$preserve   = $preserve ?? [];
$extraClass = $extraClass ?? '';

$newDir = ($sortKey === $field && $sortDir === 'asc') ? 'desc' : 'asc';

$params = array_filter(
    array_merge($preserve, ['sort' => $field, 'direction' => $newDir]),
    fn($v) => $v !== null && $v !== ''
);
$url = $this->Url->build(['?' => $params]);

if ($sortKey === $field) {
    $arrow = $sortDir === 'asc' ? ' <i class="ri-arrow-up-s-fill"></i>' : ' <i class="ri-arrow-down-s-fill"></i>';
    $cls   = 'fw-bold text-primary';
} else {
    $arrow = ' <i class="ri-arrow-up-down-line text-muted opacity-50" style="font-size:.85em"></i>';
    $cls   = 'text-dark';
}
?>
<th class="<?= h($extraClass) ?>">
    <a href="<?= h($url) ?>" class="text-decoration-none <?= h($cls) ?>"><?= h($label) ?><?= $arrow ?></a>
</th>

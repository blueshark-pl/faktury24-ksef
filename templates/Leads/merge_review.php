<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Lead $a
 * @var \App\Model\Entity\Lead $b
 */
$this->assign('title', __('Scalanie leadów'));

$fields = [
    'company_name'   => 'Nazwa firmy',
    'nip'            => 'NIP',
    'country_code'   => 'Kraj',
    'postal_code'    => 'Kod',
    'city'           => 'Miasto',
    'street'         => 'Ulica',
    'contact_person' => 'Osoba',
    'contact_role'   => 'Stanowisko',
    'phone'          => 'Telefon',
    'email'          => 'Email',
    'contact_channel'=> 'Kanał',
    'branch_type'    => 'Gałąź',
    'value_pln'      => 'Wartość',
    'currency'       => 'Waluta',
    'assigned_to_user_id' => 'Opiekun (user_id)',
    'note'           => 'Notatka',
    'next_action_description' => 'Opis akcji',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><i class="ri-git-merge-line me-1"></i><?= __('Scalanie leadów') ?></h4>
    <a href="<?= $this->Url->build(['action' => 'duplicates']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Anuluj') ?>
    </a>
</div>

<div class="alert alert-info small">
    <?= __('Wybierz dla każdego pola którą wartość zachować. Wynikowy lead zastąpi <strong>Lead A</strong>; Lead B zostanie usunięty. Wszystkie activities z B zostaną przeniesione do A. Stage i probability są scalone jako maksimum. Auto-flagi K/Z/O/Zl - OR.') ?>
</div>

<?= $this->Form->create(null, ['url' => ['action' => 'merge']]) ?>
<input type="hidden" name="a" value="<?= h($a->id) ?>">
<input type="hidden" name="b" value="<?= h($b->id) ?>">

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:180px;"><?= __('Pole') ?></th>
                    <th><?= __('Lead A') ?>: <span class="fw-normal"><?= h($a->company_name) ?></span></th>
                    <th><?= __('Lead B') ?>: <span class="fw-normal"><?= h($b->company_name) ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $f => $label):
                    $va = (string)($a->{$f} ?? '');
                    $vb = (string)($b->{$f} ?? '');
                    $same = ($va === $vb);
                ?>
                <tr class="<?= $same ? 'text-muted' : '' ?>">
                    <td class="fw-semibold small"><?= h($label) ?></td>
                    <td>
                        <label class="d-flex align-items-start gap-2 mb-0" style="cursor:pointer;">
                            <input type="radio" name="field_source_<?= h($f) ?>" value="a"
                                   <?= ($va !== '' || $same) ? 'checked' : '' ?>>
                            <span class="small"><?= h($va) ?: '<em class="text-muted">(puste)</em>' ?></span>
                        </label>
                    </td>
                    <td>
                        <label class="d-flex align-items-start gap-2 mb-0" style="cursor:pointer;">
                            <input type="radio" name="field_source_<?= h($f) ?>" value="b"
                                   <?= ($va === '' && $vb !== '') ? 'checked' : '' ?>>
                            <span class="small"><?= h($vb) ?: '<em class="text-muted">(puste)</em>' ?></span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-warning">
                    <td class="fw-semibold small">Stage / Probability</td>
                    <td class="small"><span class="badge bg-secondary"><?= h($a->stage) ?></span> · <?= (int)$a->probability ?>%</td>
                    <td class="small"><span class="badge bg-secondary"><?= h($b->stage) ?></span> · <?= (int)$b->probability ?>%
                        <div class="text-muted mt-1">Auto: max z obu</div>
                    </td>
                </tr>
                <tr class="table-warning">
                    <td class="fw-semibold small">Activities</td>
                    <td class="small"><?= count($a->lead_activities ?? []) ?> wpisów</td>
                    <td class="small"><?= count($b->lead_activities ?? []) ?> wpisów
                        <div class="text-muted mt-1">Auto: wszystkie z B przeniesione do A</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-end">
        <a href="<?= $this->Url->build(['action' => 'duplicates']) ?>" class="btn btn-outline-secondary"><?= __('Anuluj') ?></a>
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('<?= __('Scalić leady? Lead B zostanie USUNIĘTY, tego nie można cofnąć.') ?>');">
            <i class="ri-git-merge-line"></i> <?= __('Scal i usuń Lead B') ?>
        </button>
    </div>
</div>
<?= $this->Form->end() ?>

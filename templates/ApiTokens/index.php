<?php
/** @var \Cake\View\View $this */
/** @var iterable<\App\Model\Entity\ApiToken> $tokens */

$this->assign('title', 'Tokeny API');
?>

<div class="row">
  <div class="col-xl-3">
    <div class="card custom-card">
      <div class="card-body">
        <?= $this->element('Users/settings_nav') ?>
      </div>
    </div>
  </div>

  <div class="col-xl-9">
    <div class="card custom-card">
      <div class="card-header justify-content-between">
        <div class="card-title">Tokeny API</div>
      </div>
      <div class="card-body">

        <?= $this->Flash->render() ?>

        <p class="text-muted small mb-3">
          Tokeny API umożliwiają integrację zewnętrznych systemów z faktury24.
          Każdy token identyfikuje Twoją firmę — traktuj go jak hasło i nie udostępniaj osobom trzecim.
        </p>

        <!-- Generate form -->
        <div class="card border mb-4">
          <div class="card-header bg-light py-2">
            <strong><i class="ri-add-line me-1"></i> Wygeneruj nowy token</strong>
          </div>
          <div class="card-body">
            <?= $this->Form->create(null, ['url' => ['action' => 'generate'], 'class' => 'row g-2 align-items-end']) ?>
              <div class="col-md-5">
                <?= $this->Form->control('name', [
                  'label'       => 'Nazwa tokenu',
                  'class'       => 'form-control form-control-sm',
                  'placeholder' => 'np. System ERP, Sklep WooCommerce',
                  'value'       => 'Token API',
                ]) ?>
              </div>
              <div class="col-md-4">
                <?= $this->Form->control('expires_at', [
                  'label'       => 'Wygasa (opcjonalnie)',
                  'type'        => 'date',
                  'class'       => 'form-control form-control-sm',
                ]) ?>
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                  <i class="ri-key-2-line me-1"></i> Generuj token
                </button>
              </div>
            <?= $this->Form->end() ?>
          </div>
        </div>

        <!-- Token list -->
        <?php if (iterator_count($tokens) === 0): ?>
          <p class="text-muted text-center py-3">Brak wygenerowanych tokenów.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Nazwa</th>
                <th>Prefix</th>
                <th>Status</th>
                <th>Ostatnie użycie</th>
                <th>Wygasa</th>
                <th>Utworzono</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tokens as $tk): ?>
              <tr class="<?= $tk->is_active ? '' : 'text-muted' ?>">
                <td><?= h($tk->name) ?></td>
                <td><code><?= h($tk->token_prefix) ?>…</code></td>
                <td>
                  <?php if ($tk->is_active): ?>
                    <span class="badge bg-success">Aktywny</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Unieważniony</span>
                  <?php endif; ?>
                </td>
                <td><?= $tk->last_used_at ? h($tk->last_used_at->format('d.m.Y H:i')) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $tk->expires_at ? h($tk->expires_at->format('d.m.Y')) : '<span class="text-muted">Brak</span>' ?></td>
                <td><?= h($tk->created->format('d.m.Y')) ?></td>
                <td class="text-end">
                  <?php if ($tk->is_active): ?>
                    <?= $this->Form->postLink(
                      '<i class="ri-forbid-line me-1"></i> Unieważnij',
                      ['action' => 'revoke', $tk->id],
                      [
                        'class'   => 'btn btn-outline-danger btn-sm',
                        'escape'  => false,
                        'confirm' => 'Czy na pewno chcesz unieważnić token "' . h($tk->name) . '"?',
                      ]
                    ) ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

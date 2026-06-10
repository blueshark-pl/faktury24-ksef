<?php
/**
 * @var \App\View\AppView $this
 * @var array $state
 * @var bool $active
 * @var string $flagPath
 */
$this->assign('title', 'Przerwa techniczna');

// ISO → format pola datetime-local (YYYY-MM-DDTHH:MM)
$toLocal = function ($iso): string {
    $iso = trim((string)($iso ?? ''));
    if ($iso === '') {
        return '';
    }
    try {
        return (new \DateTimeImmutable($iso))->format('Y-m-d\TH:i');
    } catch (\Throwable) {
        return '';
    }
};

$enabled    = !empty($state['enabled']);
$message    = (string)($state['message'] ?? '');
$from       = $toLocal($state['from'] ?? null);
$to         = $toLocal($state['to'] ?? null);
$noticeFrom = $toLocal($state['notice_from'] ?? null);
$allowCron  = array_key_exists('allow_cron', (array)$state) ? !empty($state['allow_cron']) : true;
?>
<div class="container py-4" style="max-width:760px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Przerwa techniczna</h3>
    <?php if ($active): ?>
      <span class="badge bg-danger fs-6">AKTYWNA teraz</span>
    <?php elseif ($enabled): ?>
      <span class="badge bg-warning text-dark fs-6">Zaplanowana (poza oknem)</span>
    <?php else: ?>
      <span class="badge bg-success fs-6">Wyłączona</span>
    <?php endif; ?>
  </div>

  <div class="alert alert-info small">
    Gdy tryb jest aktywny, zwykli użytkownicy widzą stronę 503, a portal/API otrzymuje 503 JSON.
    <strong>Administratorzy</strong> oraz <strong>crony z kluczem</strong> (kolejka maili, planowane wysyłki) działają dalej.
    Jeśli ustawisz okno <em>od/do</em>, blokada uruchomi się dopiero o godzinie „od" i wyłączy automatycznie po „do".
  </div>

  <?= $this->Form->create(null, ['url' => ['action' => 'save']]) ?>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
      <label class="form-check-label fw-semibold" for="enabled">Włącz tryb przerwy technicznej</label>
    </div>

    <div class="mb-3">
      <label class="form-label" for="message">Komunikat dla użytkowników</label>
      <textarea class="form-control" id="message" name="message" rows="3" placeholder="Trwają prace techniczne, wrócimy ok. 22:30."><?= h($message) ?></textarea>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="from">Start blokady (opcjonalnie)</label>
        <input type="datetime-local" class="form-control" id="from" name="from" value="<?= h($from) ?>">
        <div class="form-text">Puste = blokada od razu po włączeniu.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="to">Koniec blokady (opcjonalnie)</label>
        <input type="datetime-local" class="form-control" id="to" name="to" value="<?= h($to) ?>">
        <div class="form-text">Po tym czasie tryb wyłączy się automatycznie.</div>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="notice_from">Baner zapowiedzi od (opcjonalnie)</label>
      <input type="datetime-local" class="form-control" id="notice_from" name="notice_from" value="<?= h($noticeFrom) ?>">
      <div class="form-text">Od tego momentu (do „startu blokady") użytkownicy widzą żółty baner z zapowiedzią prac. Wymaga ustawionego „startu blokady".</div>
    </div>

    <div class="form-check form-switch mb-4">
      <input class="form-check-input" type="checkbox" role="switch" id="allow_cron" name="allow_cron" value="1" <?= $allowCron ? 'checked' : '' ?>>
      <label class="form-check-label" for="allow_cron">Pozwól cronom działać w trakcie prac (kolejka maili, planowane wysyłki KSeF)</label>
    </div>

    <button type="submit" class="btn btn-primary">Zapisz</button>
    <a href="/" class="btn btn-outline-secondary">Powrót</a>
  <?= $this->Form->end() ?>

  <p class="text-muted small mt-3 mb-0">Plik stanu: <code><?= h($flagPath) ?></code></p>
</div>

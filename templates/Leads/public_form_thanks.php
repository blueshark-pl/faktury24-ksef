<?php
/**
 * @var \App\View\AppView $this
 * @var object|null $company
 */
$this->assign('title', __('Dziękujemy'));
?>
<style>
    body { background: #f5f6fa; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 40px 20px; }
    .pf-thanks { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
    .pf-thanks-header { background: linear-gradient(135deg, #94C81F, #6b8f14); color: #fff;
        padding: 40px 30px; text-align: center; }
    .pf-thanks-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,0.25);
        display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; font-size: 32px; }
    .pf-thanks-header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 700; }
    .pf-thanks-header p { margin: 0; opacity: 0.9; }
    .pf-thanks-body { padding: 28px 30px; color: #4b5563; font-size: 15px; line-height: 1.6; }
</style>

<div class="pf-thanks">
    <div class="pf-thanks-header">
        <div class="pf-thanks-icon">✓</div>
        <h1><?= __('Dziękujemy za zapytanie!') ?></h1>
        <p><?= h($company->name ?? 'Booklio TMS') ?></p>
    </div>
    <div class="pf-thanks-body">
        <p><?= __('Otrzymaliśmy Twoje zapytanie i skontaktujemy się z Tobą w ciągu 24 godzin, aby przedstawić ofertę.') ?></p>
        <p style="color:#9ca3af; font-size: 13px; margin-top: 20px;">
            <?= __('Jeśli sprawa jest pilna – zadzwoń do nas bezpośrednio.') ?>
        </p>
    </div>
</div>

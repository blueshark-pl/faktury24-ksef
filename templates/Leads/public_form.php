<?php
/**
 * @var \App\View\AppView $this
 * @var object|null $company
 * @var array $errors
 * @var bool $submitted
 */
$this->assign('title', __('Kontakt') . ' – ' . ($company->name ?? 'Booklio TMS'));
?>
<style>
    body { background: #f5f6fa; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px 0; }
    .pf-wrap { max-width: 640px; margin: 0 auto; }
    .pf-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
    .pf-header { background: linear-gradient(135deg, #94C81F, #6b8f14); color: #fff; padding: 32px 30px; }
    .pf-header h1 { margin: 0 0 8px; font-size: 22px; font-weight: 700; }
    .pf-header p { margin: 0; opacity: 0.9; font-size: 14px; }
    .pf-body { padding: 28px 30px; }
    .pf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px; }
    .pf-row.full { grid-template-columns: 1fr; }
    .pf-field label { display: block; font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
    .pf-field input, .pf-field textarea, .pf-field select {
        width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px;
        font-size: 14px; font-family: inherit; box-sizing: border-box;
    }
    .pf-field input:focus, .pf-field textarea:focus, .pf-field select:focus {
        border-color: #94C81F; outline: none; box-shadow: 0 0 0 3px rgba(148,200,31,0.12);
    }
    .pf-hp { position: absolute; left: -9999px; top: -9999px; }
    .pf-submit { background: #94C81F; color: #fff; border: none; padding: 14px 28px;
        border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; }
    .pf-submit:hover { background: #6b8f14; }
    .pf-errors { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
        padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
    .pf-errors ul { margin: 4px 0 0 20px; padding: 0; }
    .pf-footer { text-align: center; color: #9ca3af; font-size: 11px; padding: 16px 20px; }
</style>

<div class="pf-wrap">
    <div class="pf-card">
        <div class="pf-header">
            <h1><?= __('Zapytanie transportowe') ?></h1>
            <p><?= h($company->name ?? 'Booklio TMS') ?> – <?= __('odpowiemy w ciągu 24h') ?></p>
        </div>
        <div class="pf-body">
            <?php if (!empty($errors)): ?>
                <div class="pf-errors">
                    <strong><?= __('Popraw błędy:') ?></strong>
                    <ul>
                        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?= $this->Form->create(null, ['type' => 'post']) ?>
                <!-- Honeypot (ukryte pole, boty je wypełniają) -->
                <div class="pf-hp" aria-hidden="true">
                    <label>Nie wypełniaj tego pola: <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
                </div>
                <!-- Timestamp otwarcia (min 3s do submitu) -->
                <input type="hidden" name="t" value="<?= time() ?>">

                <div class="pf-row">
                    <div class="pf-field">
                        <label for="pf-company"><?= __('Nazwa firmy') ?> *</label>
                        <input type="text" id="pf-company" name="company_name" required
                               value="<?= h($this->request->getData('company_name')) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="pf-nip">NIP</label>
                        <input type="text" id="pf-nip" name="nip"
                               value="<?= h($this->request->getData('nip')) ?>">
                    </div>
                </div>

                <div class="pf-row">
                    <div class="pf-field">
                        <label for="pf-person"><?= __('Imię i nazwisko') ?></label>
                        <input type="text" id="pf-person" name="contact_person"
                               value="<?= h($this->request->getData('contact_person')) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="pf-email">E-mail *</label>
                        <input type="email" id="pf-email" name="email" required
                               value="<?= h($this->request->getData('email')) ?>">
                    </div>
                </div>

                <div class="pf-row">
                    <div class="pf-field">
                        <label for="pf-phone"><?= __('Telefon') ?></label>
                        <input type="tel" id="pf-phone" name="phone"
                               value="<?= h($this->request->getData('phone')) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="pf-country"><?= __('Kraj') ?></label>
                        <input type="text" id="pf-country" name="country_code" maxlength="2"
                               placeholder="PL" style="text-transform:uppercase;"
                               value="<?= h($this->request->getData('country_code')) ?>">
                    </div>
                </div>

                <div class="pf-row">
                    <div class="pf-field">
                        <label for="pf-city"><?= __('Miasto') ?></label>
                        <input type="text" id="pf-city" name="city"
                               value="<?= h($this->request->getData('city')) ?>">
                    </div>
                    <div class="pf-field">
                        <label for="pf-branch"><?= __('Rodzaj transportu') ?></label>
                        <select id="pf-branch" name="branch_type">
                            <option value=""><?= __('— wybierz —') ?></option>
                            <option value="road">Drogowy</option>
                            <option value="road_reefer">Drogowy chłodnia</option>
                            <option value="road_adr">Drogowy ADR</option>
                            <option value="road_oversize">Drogowy Oversize</option>
                            <option value="sea">Morski</option>
                            <option value="rail">Kolejowy</option>
                            <option value="air">Lotniczy</option>
                            <option value="intermodal">Intermodalny</option>
                        </select>
                    </div>
                </div>

                <div class="pf-row full">
                    <div class="pf-field">
                        <label for="pf-message"><?= __('Treść zapytania') ?></label>
                        <textarea id="pf-message" name="message" rows="5"
                                  placeholder="<?= __('Opisz szczegóły: skąd/dokąd, waga, palety, terminy…') ?>"><?= h($this->request->getData('message')) ?></textarea>
                    </div>
                </div>

                <div class="pf-row full">
                    <div class="pf-field">
                        <button type="submit" class="pf-submit"><?= __('Wyślij zapytanie') ?></button>
                    </div>
                </div>
            <?= $this->Form->end() ?>
        </div>
        <div class="pf-footer">
            <?= __('Wysyłając formularz akceptujesz przetwarzanie danych w celu obsługi zapytania.') ?>
        </div>
    </div>
</div>

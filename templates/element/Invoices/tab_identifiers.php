<?php
/**
 * Element: Identyfikatory międzynarodowe (zakładka na formularzu faktury)
 *
 * Zmienne wymagane:
 *   $invoice — encja Invoice (lub nowa przy add)
 *   $__isEdit — bool, true = tryb edycji
 */
?>
<div class="tab-pane fade" id="pane-intl" role="tabpanel" aria-labelledby="tab-intl">
  <div class="vstack gap-4">

    <!-- Sprzedawca -->
    <div>
      <h6 class="fw-semibold mb-3"><i class="ri-building-line me-1"></i> Moje dane sprzedawcy</h6>
      <div class="row g-3">
        <div class="col-md-2">
          <?= $this->Form->control('seller_vat_prefix', [
            'label' => 'Prefiks VAT',
            'class' => 'form-control',
            'placeholder' => 'np. PL',
            'maxlength' => 2,
            'value' => $invoice->seller_vat_prefix ?? null
          ]) ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Numer VAT-UE
            <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
              title="Numer VAT-UE sprzedawcy"
              data-bs-content="<div class='small text-start'>Numer identyfikacji podatkowej z prefiksem kraju UE, wymagany przy transakcjach unijnych B2B (WDT, usługi dla podatnika z&nbsp;UE, procedura trójstronna).</div>">
              <i class="ri-question-line"></i>
            </button>
          </label>
          <?= $this->Form->control('seller_vat_eu', ['label' => false, 'class' => 'form-control', 'placeholder' => 'np. PL1234567890', 'value' => $invoice->seller_vat_eu ?? null]) ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Numer EORI
            <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
              title="Numer EORI sprzedawcy"
              data-bs-content="<div class='small text-start'>Numer do operacji celnych &mdash; import/eksport poza UE, odprawy celne. To numer celny, nie vatowski.</div>">
              <i class="ri-question-line"></i>
            </button>
          </label>
          <?= $this->Form->control('seller_eori', ['label' => false, 'class' => 'form-control', 'placeholder' => 'np. PL123456789000', 'value' => $invoice->seller_eori ?? null]) ?>
        </div>
      </div>
    </div>

    <hr class="my-0">

    <!-- Kontrahent -->
    <div>
      <h6 class="fw-semibold mb-3"><i class="ri-user-line me-1"></i> Dane kontrahenta (nabywcy)</h6>
      <div class="row g-3">
        <div class="col-md-2">
          <?= $this->Form->control('buyer_vat_prefix', [
            'label' => 'Prefiks VAT',
            'class' => 'form-control',
            'placeholder' => 'np. DE',
            'maxlength' => 2,
            'value' => $invoice->buyer_vat_prefix ?? null
          ]) ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Numer VAT-UE
            <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
              title="Numer VAT-UE nabywcy"
              data-bs-content="<div class='small text-start'>Numer identyfikacji podatkowej z prefiksem kraju UE nabywcy, wymagany przy transakcjach unijnych B2B.</div>">
              <i class="ri-question-line"></i>
            </button>
          </label>
          <?= $this->Form->control('buyer_vat_eu', ['label' => false, 'class' => 'form-control', 'placeholder' => 'np. DE123456789', 'value' => $invoice->buyer_vat_eu ?? null]) ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Numer EORI
            <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
              title="Numer EORI nabywcy"
              data-bs-content="<div class='small text-start'>Numer do operacji celnych nabywcy &mdash; import/eksport poza UE, odprawy celne.</div>">
              <i class="ri-question-line"></i>
            </button>
          </label>
          <?= $this->Form->control('buyer_eori', ['label' => false, 'class' => 'form-control', 'placeholder' => 'np. DE123456789000', 'value' => $invoice->buyer_eori ?? null]) ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Identyfikator podatkowy inny
            <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
              title="Identyfikator podatkowy inny"
              data-bs-content="<div class='small text-start'>Gdy kontrahent nie ma polskiego NIP ani numeru VAT-UE, ale posiada inny numer podatkowy. W&nbsp;KSeF pole &laquo;identyfikator podatkowy inny niż NIP&raquo; z&nbsp;opcjonalnym kodem kraju.</div>">
              <i class="ri-question-line"></i>
            </button>
          </label>
          <?= $this->Form->control('buyer_tax_id_other', ['label' => false, 'class' => 'form-control', 'placeholder' => 'Inny numer podatkowy nabywcy', 'value' => $invoice->buyer_tax_id_other ?? null]) ?>
        </div>
        <div class="col-md-3">
          <?= $this->Form->control('buyer_tax_id_other_country', [
            'label' => 'Kod kraju (inny ID)',
            'class' => 'form-control',
            'placeholder' => 'np. US',
            'maxlength' => 2,
            'value' => $invoice->buyer_tax_id_other_country ?? null
          ]) ?>
        </div>
      </div>
    </div>

  </div>
</div>

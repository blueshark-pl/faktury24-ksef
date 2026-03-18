<?php
/**
 * Element: Rozszerzony KSeF FA(3) (zakładka na formularzu faktury)
 *
 * Zmienne wymagane:
 *   $invoice — encja Invoice (lub nowa przy add)
 *   $__isEdit — bool, true = tryb edycji
 *
 * Pola: skonto, status_info_podatnika, is_new_transport_wdt,
 *       koresp (adres korespondencyjny nabywcy/sprzedawcy),
 *       transaction_conditions_json, order_total_gross.
 */
$kind = $kind ?? 'vat';
?>
<div class="tab-pane fade" id="pane-fa3ext" role="tabpanel" aria-labelledby="tab-fa3ext">
  <div class="row g-3">

    <!-- ── Skonto ── -->
    <div class="col-12">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-discount-percent-line me-1"></i> Skonto</h6>
    </div>
    <div class="col-md-8">
      <?= $this->Form->control('skonto_conditions', [
        'label' => 'Warunki skonta',
        'type' => 'textarea',
        'class' => 'form-control',
        'rows' => 2,
        'placeholder' => 'np. 2% rabatu przy płatności w ciągu 10 dni',
        'value' => $invoice->skonto_conditions ?? '',
      ]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('skonto_amount', [
        'label' => 'Wysokość skonta',
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => 'np. 200.00',
        'value' => $invoice->skonto_amount ?? '',
      ]) ?>
      <small class="text-muted">Kwota lub opis (pole tekstowe w KSeF).</small>
    </div>

    <!-- ── Status podatnika ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-shield-check-line me-1"></i> Status podatnika</h6>
    </div>
    <div class="col-md-6">
      <?= $this->Form->control('status_info_podatnika', [
        'label' => 'StatusInfoPodatnika',
        'type' => 'select',
        'class' => 'form-select',
        'empty' => '— brak (domyślnie) —',
        'options' => [
          1 => '1 — Podmiot zwolniony (art. 113 ust. 1 i 9)',
          2 => '2 — Podatnik VAT czynny (nieobowiązkowe)',
          3 => '3 — Podatnik VAT marża (art. 120)',
        ],
        'value' => $invoice->status_info_podatnika ?? '',
      ]) ?>
      <small class="text-muted">Pole opcjonalne. Wartość 1 wymagana dla zwolnionych podmiotów.</small>
    </div>
    <div class="col-md-6">
      <div class="form-check form-switch mt-3">
        <input class="form-check-input" type="checkbox" id="buyer-is-jst" name="buyer_is_jst" value="1"<?= !empty($invoice->buyer_is_jst) ? ' checked' : '' ?>>
        <label class="form-check-label" for="buyer-is-jst">Jednostka samorządu terytorialnego (JST)</label>
      </div>
      <div class="form-check form-switch mt-2">
        <input class="form-check-input" type="checkbox" id="buyer-in-vat-group" name="buyer_in_vat_group" value="1"<?= !empty($invoice->buyer_in_vat_group) ? ' checked' : '' ?>>
        <label class="form-check-label" for="buyer-in-vat-group">Członek grupy VAT</label>
      </div>
    </div>

    <!-- ── Nowe środki transportu (WDT) ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-truck-line me-1"></i> Nowe środki transportu (WDT)</h6>
    </div>
    <div class="col-md-6">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="is-new-transport-wdt" name="is_new_transport_wdt" value="1"<?= !empty($invoice->is_new_transport_wdt) ? ' checked' : '' ?>>
        <label class="form-check-label" for="is-new-transport-wdt">Dostawa nowego środka transportu (art. 42 ust. 1–5)</label>
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="p-42-5" name="p_42_5" value="1"<?= !empty($invoice->p_42_5) ? ' checked' : '' ?>>
        <label class="form-check-label" for="p-42-5">Wewnątrzwspólnotowa dostawa (art. 42 ust. 5)</label>
      </div>
    </div>

    <!-- ── Warunki transakcji ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-file-list-3-line me-1"></i> Warunki transakcji</h6>
    </div>
    <div class="col-12">
      <?php
        $tcJson = $invoice->transaction_conditions_json ?? null;
        $tcArr = [];
        if (!empty($tcJson)) {
          $tcArr = is_array($tcJson) ? $tcJson : json_decode((string)$tcJson, true);
          if (!is_array($tcArr)) $tcArr = [];
        }
        $tcUmowy = $tcArr['Umowy'] ?? [];
        $tcZamowienia = $tcArr['Zamowienia'] ?? [];
      ?>
      <div id="tc-section">
        <p class="text-muted small mb-2">Warunki transakcji (umowy i zamówienia). Dodaj wpisy jeśli wymagane.</p>
        <!-- Umowy -->
        <label class="form-label fw-semibold">Umowy</label>
        <div id="tc-umowy-list">
          <?php foreach ($tcUmowy as $i => $u): ?>
          <div class="input-group mb-1 tc-umowa-row">
            <input type="text" class="form-control" name="tc_umowy[]" value="<?= h($u) ?>" placeholder="Nr umowy">
            <button type="button" class="btn btn-outline-danger btn-sm tc-remove-row"><i class="ri-delete-bin-line"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="tc-add-umowa"><i class="ri-add-line"></i> Dodaj umowę</button>

        <!-- Zamówienia -->
        <label class="form-label fw-semibold">Zamówienia</label>
        <div id="tc-zamowienia-list">
          <?php foreach ($tcZamowienia as $i => $z): ?>
          <div class="input-group mb-1 tc-zamowienie-row">
            <input type="text" class="form-control" name="tc_zamowienia[]" value="<?= h($z) ?>" placeholder="Nr zamówienia">
            <button type="button" class="btn btn-outline-danger btn-sm tc-remove-row"><i class="ri-delete-bin-line"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="tc-add-zamowienie"><i class="ri-add-line"></i> Dodaj zamówienie</button>
      </div>
    </div>

    <?php if (in_array($kind, ['advance', 'final'])): ?>
    <!-- ── Wartość zamówienia (advance/final) ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-shopping-cart-line me-1"></i> Zamówienie</h6>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('order_total_gross', [
        'label' => 'Wartość zamówienia brutto',
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => 'np. 15000.00',
        'value' => $invoice->order_total_gross ?? '',
      ]) ?>
      <small class="text-muted">WartoscZamowienia — łączna wartość zamówienia (KSeF ZAL/ROZ).</small>
    </div>
    <?php endif; ?>

    <!-- ── Adres korespondencyjny nabywcy ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-mail-send-line me-1"></i> Adres korespondencyjny nabywcy</h6>
    </div>
    <?php
      $ctr = null;
      if ($__isEdit && !empty($invoice->invoice_contractor)) {
        $ctr = $invoice->invoice_contractor;
      }
    ?>
    <div class="col-md-2">
      <?= $this->Form->control('invoice_contractor.koresp_country_code', [
        'label' => 'Kraj',
        'type' => 'text',
        'class' => 'form-control',
        'maxlength' => 2,
        'placeholder' => 'PL',
        'value' => $ctr->koresp_country_code ?? '',
      ]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('invoice_contractor.koresp_address_l1', [
        'label' => 'Adres linia 1',
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => 'ul. Główna 10, 00-001 Warszawa',
        'value' => $ctr->koresp_address_l1 ?? '',
      ]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('invoice_contractor.koresp_address_l2', [
        'label' => 'Adres linia 2',
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => '(opcjonalnie)',
        'value' => $ctr->koresp_address_l2 ?? '',
      ]) ?>
    </div>
    <div class="col-md-2">
      <?= $this->Form->control('invoice_contractor.koresp_gln', [
        'label' => 'GLN koresp.',
        'type' => 'text',
        'class' => 'form-control',
        'placeholder' => 'GLN',
        'value' => $ctr->koresp_gln ?? '',
      ]) ?>
    </div>

    <!-- ── Obciążenia / Odliczenia ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-scales-3-line me-1"></i> Obciążenia / Odliczenia</h6>
    </div>
    <div class="col-12">
      <?php $charges = ($__isEdit && !empty($invoice->invoice_charges)) ? $invoice->invoice_charges : []; ?>
      <div id="charges-list">
        <?php foreach ($charges as $i => $ch): ?>
        <div class="row g-2 mb-1 charge-row">
          <div class="col-md-3">
            <select class="form-select form-select-sm" name="charges[<?= $i ?>][type]">
              <option value="obciazenie"<?= ($ch->type ?? '') === 'obciazenie' ? ' selected' : '' ?>>Obciążenie</option>
              <option value="odliczenie"<?= ($ch->type ?? '') === 'odliczenie' ? ' selected' : '' ?>>Odliczenie</option>
            </select>
          </div>
          <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="charges[<?= $i ?>][kwota]" value="<?= h($ch->kwota ?? '') ?>" placeholder="Kwota">
          </div>
          <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" name="charges[<?= $i ?>][powod]" value="<?= h($ch->powod ?? '') ?>" placeholder="Powód">
          </div>
          <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-charge"><i class="ri-add-line"></i> Dodaj obciążenie/odliczenie</button>
    </div>

    <!-- ── Rachunek bankowy faktora ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-bank-line me-1"></i> Rachunek bankowy faktora</h6>
    </div>
    <div class="col-12">
      <?php $factorBanks = ($__isEdit && !empty($invoice->invoice_factor_banks)) ? $invoice->invoice_factor_banks : []; ?>
      <div id="factor-banks-list">
        <?php foreach ($factorBanks as $i => $fb): ?>
        <div class="row g-2 mb-1 factor-bank-row">
          <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][nr_rb]" value="<?= h($fb->nr_rb ?? '') ?>" placeholder="Nr rachunku">
          </div>
          <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][swift]" value="<?= h($fb->swift ?? '') ?>" placeholder="SWIFT">
          </div>
          <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][nazwa_banku]" value="<?= h($fb->nazwa_banku ?? '') ?>" placeholder="Nazwa banku">
          </div>
          <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][opis_rachunku]" value="<?= h($fb->opis_rachunku ?? '') ?>" placeholder="Opis rachunku">
          </div>
          <div class="col-md-2">
            <div class="form-check form-check-inline mt-1">
              <input class="form-check-input" type="checkbox" name="factor_banks[<?= $i ?>][rachunek_wlasny_banku]" value="1"<?= !empty($fb->rachunek_wlasny_banku) ? ' checked' : '' ?>>
              <label class="form-check-label small">Własny bank</label>
            </div>
          </div>
          <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-factor-bank"><i class="ri-add-line"></i> Dodaj rachunek faktora</button>
    </div>

    <!-- ── Podmiot upoważniony ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-user-star-line me-1"></i> Podmiot upoważniony</h6>
    </div>
    <div class="col-12">
      <?php $authEntities = ($__isEdit && !empty($invoice->invoice_authorized_entities)) ? $invoice->invoice_authorized_entities : []; ?>
      <div id="auth-entities-list">
        <?php foreach ($authEntities as $i => $ae): ?>
        <div class="card card-body p-2 mb-2 auth-entity-row">
          <div class="row g-2">
            <div class="col-md-2">
              <input type="number" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][rola]" value="<?= h($ae->rola ?? '') ?>" placeholder="Rola (1-4)" min="1" max="4">
            </div>
            <div class="col-md-3">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][name]" value="<?= h($ae->name ?? '') ?>" placeholder="Nazwa">
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][nip]" value="<?= h($ae->nip ?? '') ?>" placeholder="NIP">
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][nr_eori]" value="<?= h($ae->nr_eori ?? '') ?>" placeholder="EORI">
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][country_code]" value="<?= h($ae->country_code ?? '') ?>" placeholder="Kraj" maxlength="2">
            </div>
            <div class="col-md-1">
              <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button>
            </div>
            <div class="col-md-4">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][address_l1]" value="<?= h($ae->address_l1 ?? '') ?>" placeholder="Adres linia 1">
            </div>
            <div class="col-md-4">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][address_l2]" value="<?= h($ae->address_l2 ?? '') ?>" placeholder="Adres linia 2 (opcj.)">
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][email]" value="<?= h($ae->email ?? '') ?>" placeholder="Email">
            </div>
            <div class="col-md-2">
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][phone]" value="<?= h($ae->phone ?? '') ?>" placeholder="Telefon">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-auth-entity"><i class="ri-add-line"></i> Dodaj podmiot upoważniony</button>
    </div>

    <?php if (in_array($kind, ['advance', 'final'])): ?>
    <!-- ── Pozycje zamówienia (advance/final) ── -->
    <div class="col-12 mt-3">
      <h6 class="text-muted border-bottom pb-1 mb-2"><i class="ri-file-list-line me-1"></i> Pozycje zamówienia (KSeF ZAL/ROZ)</h6>
    </div>
    <div class="col-12">
      <?php $orderLines = ($__isEdit && !empty($invoice->invoice_order_lines)) ? $invoice->invoice_order_lines : []; ?>
      <div id="order-lines-list">
        <?php foreach ($orderLines as $i => $ol): ?>
        <div class="row g-2 mb-1 order-line-row">
          <div class="col-md-1">
            <input type="number" class="form-control form-control-sm" name="order_lines[<?= $i ?>][nr_wiersza]" value="<?= h($ol->nr_wiersza ?? ($i + 1)) ?>" placeholder="Nr" min="1">
          </div>
          <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][name]" value="<?= h($ol->name ?? '') ?>" placeholder="Nazwa">
          </div>
          <div class="col-md-1">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][unit]" value="<?= h($ol->unit ?? '') ?>" placeholder="Jm.">
          </div>
          <div class="col-md-1">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][quantity]" value="<?= h($ol->quantity ?? '') ?>" placeholder="Ilość">
          </div>
          <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][price]" value="<?= h($ol->price ?? '') ?>" placeholder="Cena netto">
          </div>
          <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][netto]" value="<?= h($ol->netto ?? '') ?>" placeholder="Netto">
          </div>
          <div class="col-md-1">
            <input type="text" class="form-control form-control-sm" name="order_lines[<?= $i ?>][vat_rate]" value="<?= h($ol->vat_rate ?? '') ?>" placeholder="VAT%">
          </div>
          <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-order-line"><i class="ri-add-line"></i> Dodaj pozycję zamówienia</button>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
(function(){
  // Dynamic rows for transaction conditions
  function addRowHandler(listSel, inputName, placeholder, btnSel) {
    $(document).on('click', btnSel, function(){
      var row = $('<div class="input-group mb-1">' +
        '<input type="text" class="form-control" name="' + inputName + '" placeholder="' + placeholder + '">' +
        '<button type="button" class="btn btn-outline-danger btn-sm tc-remove-row"><i class="ri-delete-bin-line"></i></button>' +
        '</div>');
      $(listSel).append(row);
      row.find('input').focus();
    });
  }
  addRowHandler('#tc-umowy-list', 'tc_umowy[]', 'Nr umowy', '#tc-add-umowa');
  addRowHandler('#tc-zamowienia-list', 'tc_zamowienia[]', 'Nr zamówienie', '#tc-add-zamowienie');
  $(document).on('click', '.tc-remove-row', function(){ $(this).closest('.input-group').remove(); });

  // Generic remove handler for relational rows
  $(document).on('click', '.fa3-remove-row', function(){
    $(this).closest('.charge-row, .factor-bank-row, .auth-entity-row, .order-line-row').remove();
  });

  // Counter for new row indexes
  var chargeIdx = <?= count($charges ?? []) ?>;
  var fbIdx = <?= count($factorBanks ?? []) ?>;
  var aeIdx = <?= count($authEntities ?? []) ?>;
  var olIdx = <?= count($orderLines ?? []) ?>;

  // Add charge row
  $('#add-charge').on('click', function(){
    var i = chargeIdx++;
    var html = '<div class="row g-2 mb-1 charge-row">' +
      '<div class="col-md-3"><select class="form-select form-select-sm" name="charges['+i+'][type]"><option value="obciazenie">Obciążenie</option><option value="odliczenie">Odliczenie</option></select></div>' +
      '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="charges['+i+'][kwota]" placeholder="Kwota"></div>' +
      '<div class="col-md-5"><input type="text" class="form-control form-control-sm" name="charges['+i+'][powod]" placeholder="Powód"></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button></div>' +
      '</div>';
    $('#charges-list').append(html);
  });

  // Add factor bank row
  $('#add-factor-bank').on('click', function(){
    var i = fbIdx++;
    var html = '<div class="row g-2 mb-1 factor-bank-row">' +
      '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][nr_rb]" placeholder="Nr rachunku"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][swift]" placeholder="SWIFT"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][nazwa_banku]" placeholder="Nazwa banku"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][opis_rachunku]" placeholder="Opis rachunku"></div>' +
      '<div class="col-md-2"><div class="form-check form-check-inline mt-1"><input class="form-check-input" type="checkbox" name="factor_banks['+i+'][rachunek_wlasny_banku]" value="1"><label class="form-check-label small">Własny bank</label></div></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button></div>' +
      '</div>';
    $('#factor-banks-list').append(html);
  });

  // Add authorized entity row
  $('#add-auth-entity').on('click', function(){
    var i = aeIdx++;
    var html = '<div class="card card-body p-2 mb-2 auth-entity-row"><div class="row g-2">' +
      '<div class="col-md-2"><input type="number" class="form-control form-control-sm" name="auth_entities['+i+'][rola]" placeholder="Rola (1-4)" min="1" max="4"></div>' +
      '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][name]" placeholder="Nazwa"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][nip]" placeholder="NIP"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][nr_eori]" placeholder="EORI"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][country_code]" placeholder="Kraj" maxlength="2"></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button></div>' +
      '<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][address_l1]" placeholder="Adres linia 1"></div>' +
      '<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][address_l2]" placeholder="Adres linia 2 (opcj.)"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][email]" placeholder="Email"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][phone]" placeholder="Telefon"></div>' +
      '</div></div>';
    $('#auth-entities-list').append(html);
  });

  // Add order line row
  $('#add-order-line').on('click', function(){
    var i = olIdx++;
    var nr = i + 1;
    var html = '<div class="row g-2 mb-1 order-line-row">' +
      '<div class="col-md-1"><input type="number" class="form-control form-control-sm" name="order_lines['+i+'][nr_wiersza]" value="'+nr+'" placeholder="Nr" min="1"></div>' +
      '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][name]" placeholder="Nazwa"></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][unit]" placeholder="Jm."></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][quantity]" placeholder="Ilość"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][price]" placeholder="Cena netto"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][netto]" placeholder="Netto"></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][vat_rate]" placeholder="VAT%"></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row"><i class="ri-delete-bin-line"></i></button></div>' +
      '</div>';
    $('#order-lines-list').append(html);
  });
})();
</script>

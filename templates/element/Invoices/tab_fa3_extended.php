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
$__isEdit = $__isEdit ?? (!empty($isEdit) || !empty($invoice?->id));
?>
<div class="tab-pane fade" id="pane-fa3ext" role="tabpanel" aria-labelledby="tab-fa3ext">
  <div class="vstack gap-3 pt-3">

    <!-- ── Info banner ── -->
    <div class="alert alert-info alert-dismissible mb-0 py-2" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="ri-government-line fs-16 flex-shrink-0"></i>
        <div class="small">
          <strong>KSeF FA(3)</strong> — pola rozszerzone wymagane przez schemat faktury ustrukturyzowanej.
          Wypełniaj tylko te sekcje, które dotyczą danej transakcji. Pola opcjonalne możesz pozostawić puste.
        </div>
      </div>
      <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Zamknij"></button>
    </div>

    <!-- ── Skonto ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="ri-discount-percent-line fs-16 text-primary"></i>
        <strong class="small">Skonto</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <div class="row g-3">
        <div class="col-md-8">
          <?= $this->Form->control('skonto_conditions', [
            'label' => 'Warunki skonta',
            'type' => 'textarea',
            'class' => 'form-control',
            'rows' => 2,
            'placeholder' => 'np. 2% rabatu przy płatności w ciągu 10 dni',
            'value' => $invoice->skonto_conditions ?? '',
          ]) ?>
          <small class="text-muted">Opis warunków skonta zgodnie z art. 106e ust. 1 pkt 18 ustawy o VAT.</small>
        </div>
        <div class="col-md-4">
          <?= $this->Form->control('skonto_amount', [
            'label' => 'Wysokość skonta',
            'type' => 'text',
            'class' => 'form-control',
            'placeholder' => 'np. 200.00',
            'value' => $invoice->skonto_amount ?? '',
          ]) ?>
          <small class="text-muted">Kwota lub opis (pole tekstowe w FA(3)).</small>
        </div>
      </div>
    </div>

    <!-- ── Status podatnika ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="ri-shield-check-line fs-16 text-primary"></i>
        <strong class="small">Status podatnika</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <div class="row g-3">
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
          <small class="text-muted">Wartość 1 wymagana dla podmiotów zwolnionych z VAT.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold text-muted">Statusy nabywcy</label>
          <div class="vstack gap-2 mt-1">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="buyer-is-jst" name="buyer_is_jst" value="1"<?= !empty($invoice->buyer_is_jst) ? ' checked' : '' ?>>
              <label class="form-check-label" for="buyer-is-jst">
                Jednostka samorządu terytorialnego (JST)
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="buyer-in-vat-group" name="buyer_in_vat_group" value="1"<?= !empty($invoice->buyer_in_vat_group) ? ' checked' : '' ?>>
              <label class="form-check-label" for="buyer-in-vat-group">
                Członek grupy VAT
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Nowe środki transportu (WDT) ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="ri-truck-line fs-16 text-primary"></i>
        <strong class="small">Nowe środki transportu (WDT)</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is-new-transport-wdt" name="is_new_transport_wdt" value="1"<?= !empty($invoice->is_new_transport_wdt) ? ' checked' : '' ?>>
            <label class="form-check-label" for="is-new-transport-wdt">
              Dostawa nowego środka transportu (art. 42 ust. 1–5)
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="p-42-5" name="p_42_5" value="1"<?= !empty($invoice->p_42_5) ? ' checked' : '' ?>>
            <label class="form-check-label" for="p-42-5">
              Wewnątrzwspólnotowa dostawa (art. 42 ust. 5)
            </label>
          </div>
        </div>
      </div>
      <small class="text-muted d-block mt-2">Zaznacz jeśli faktura dotyczy WDT nowych środków transportu do UE.</small>
    </div>

    <!-- ── Warunki transakcji ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-file-list-3-line fs-16 text-primary"></i>
        <strong class="small">Warunki transakcji</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <small class="text-muted d-block mb-3">Numery umów i zamówień powiązanych z fakturą (element <code>WarunkiTransakcji</code> w FA(3)).</small>
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
        <div class="row g-3">
          <!-- Umowy -->
          <div class="col-md-6">
            <label class="form-label fw-semibold small"><i class="ri-file-paper-2-line me-1"></i>Umowy</label>
            <div id="tc-umowy-list" class="vstack gap-1">
              <?php foreach ($tcUmowy as $i => $u): ?>
              <div class="input-group tc-umowa-row">
                <input type="text" class="form-control form-control-sm" name="tc_umowy[]" value="<?= h($u) ?>" placeholder="Nr umowy">
                <button type="button" class="btn btn-outline-danger btn-sm tc-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="tc-add-umowa">
              <i class="ri-add-line me-1"></i>Dodaj umowę
            </button>
          </div>
          <!-- Zamówienia -->
          <div class="col-md-6">
            <label class="form-label fw-semibold small"><i class="ri-shopping-basket-line me-1"></i>Zamówienia</label>
            <div id="tc-zamowienia-list" class="vstack gap-1">
              <?php foreach ($tcZamowienia as $i => $z): ?>
              <div class="input-group tc-zamowienie-row">
                <input type="text" class="form-control form-control-sm" name="tc_zamowienia[]" value="<?= h($z) ?>" placeholder="Nr zamówienia">
                <button type="button" class="btn btn-outline-danger btn-sm tc-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="tc-add-zamowienie">
              <i class="ri-add-line me-1"></i>Dodaj zamówienie
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php if (in_array($kind, ['advance', 'final'])): ?>
    <!-- ── Wartość zamówienia (advance/final) ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="ri-shopping-cart-line fs-16 text-primary"></i>
        <strong class="small">Wartość zamówienia</strong>
        <span class="badge bg-warning-transparent ms-auto">ZAL / ROZ</span>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <?= $this->Form->control('order_total_gross', [
            'label' => 'Wartość zamówienia brutto',
            'type' => 'text',
            'class' => 'form-control',
            'placeholder' => 'np. 15000.00',
            'value' => $invoice->order_total_gross ?? '',
          ]) ?>
          <small class="text-muted"><code>WartoscZamowienia</code> — łączna wartość zamówienia (KSeF ZAL/ROZ).</small>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Adres korespondencyjny nabywcy ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-mail-send-line fs-16 text-primary"></i>
        <strong class="small">Adres korespondencyjny nabywcy</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <small class="text-muted d-block mb-3">Wypełnij jeśli adres korespondencyjny nabywcy różni się od adresu siedziby.</small>
      <?php
        $ctr = null;
        if ($__isEdit && !empty($invoice->invoice_contractor)) {
          $ctr = $invoice->invoice_contractor;
        }
      ?>
      <div class="row g-3">
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
        <div class="col-md-5">
          <?= $this->Form->control('invoice_contractor.koresp_address_l1', [
            'label' => 'Adres — linia 1',
            'type' => 'text',
            'class' => 'form-control',
            'placeholder' => 'ul. Główna 10, 00-001 Warszawa',
            'value' => $ctr->koresp_address_l1 ?? '',
          ]) ?>
        </div>
        <div class="col-md-3">
          <?= $this->Form->control('invoice_contractor.koresp_address_l2', [
            'label' => 'Adres — linia 2',
            'type' => 'text',
            'class' => 'form-control',
            'placeholder' => '(opcjonalnie)',
            'value' => $ctr->koresp_address_l2 ?? '',
          ]) ?>
        </div>
        <div class="col-md-2">
          <?= $this->Form->control('invoice_contractor.koresp_gln', [
            'label' => 'GLN',
            'type' => 'text',
            'class' => 'form-control',
            'placeholder' => 'GLN',
            'value' => $ctr->koresp_gln ?? '',
          ]) ?>
          <small class="text-muted">Globalny nr lokalizacyjny.</small>
        </div>
      </div>
    </div>

    <!-- ── Obciążenia / Odliczenia ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-scales-3-line fs-16 text-primary"></i>
        <strong class="small">Obciążenia / Odliczenia</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <small class="text-muted d-block mb-3">Dodatkowe opłaty lub pomniejszenia kwoty faktury (np. rabaty, kaucje, narzuty).</small>
      <?php $charges = ($__isEdit && !empty($invoice->invoice_charges)) ? $invoice->invoice_charges : []; ?>
      <div id="charges-list" class="vstack gap-2 mb-2">
        <?php foreach ($charges as $i => $ch): ?>
        <div class="row g-2 align-items-center charge-row">
          <div class="col-md-3">
            <select class="form-select form-select-sm" name="charges[<?= $i ?>][type]">
              <option value="obciazenie"<?= ($ch->type ?? '') === 'obciazenie' ? ' selected' : '' ?>>Obciążenie</option>
              <option value="odliczenie"<?= ($ch->type ?? '') === 'odliczenie' ? ' selected' : '' ?>>Odliczenie</option>
            </select>
          </div>
          <div class="col-md-3">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control" name="charges[<?= $i ?>][kwota]" value="<?= h($ch->kwota ?? '') ?>" placeholder="Kwota">
              <span class="input-group-text">PLN</span>
            </div>
          </div>
          <div class="col-md-5">
            <input type="text" class="form-control form-control-sm" name="charges[<?= $i ?>][powod]" value="<?= h($ch->powod ?? '') ?>" placeholder="Powód / opis">
          </div>
          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-charge">
        <i class="ri-add-line me-1"></i>Dodaj obciążenie / odliczenie
      </button>
    </div>

    <!-- ── Rachunek bankowy faktora ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-bank-line fs-16 text-primary"></i>
        <strong class="small">Rachunek bankowy faktora</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <small class="text-muted d-block mb-3">Dane rachunku bankowego faktora (faktorowanie wierzytelności — element <code>RachunekBankowyFaktora</code>).</small>
      <?php $factorBanks = ($__isEdit && !empty($invoice->invoice_factor_banks)) ? $invoice->invoice_factor_banks : []; ?>
      <div id="factor-banks-list" class="vstack gap-2 mb-2">
        <?php foreach ($factorBanks as $i => $fb): ?>
        <div class="border rounded p-2 factor-bank-row">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label small mb-1">Nr rachunku (IBAN)</label>
              <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][nr_rb]" value="<?= h($fb->nr_rb ?? '') ?>" placeholder="PL00 0000 0000 0000…">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">SWIFT / BIC</label>
              <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][swift]" value="<?= h($fb->swift ?? '') ?>" placeholder="PKOPPLPW">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Nazwa banku</label>
              <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][nazwa_banku]" value="<?= h($fb->nazwa_banku ?? '') ?>" placeholder="PKO Bank Polski">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Opis rachunku</label>
              <input type="text" class="form-control form-control-sm" name="factor_banks[<?= $i ?>][opis_rachunku]" value="<?= h($fb->opis_rachunku ?? '') ?>" placeholder="Opis (opcj.)">
            </div>
            <div class="col-md-1 text-end">
              <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
            </div>
            <div class="col-12">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="factor_banks[<?= $i ?>][rachunek_wlasny_banku]" value="1"<?= !empty($fb->rachunek_wlasny_banku) ? ' checked' : '' ?>>
                <label class="form-check-label small">Rachunek własny banku</label>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-factor-bank">
        <i class="ri-add-line me-1"></i>Dodaj rachunek faktora
      </button>
    </div>

    <!-- ── Podmiot upoważniony ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-user-star-line fs-16 text-primary"></i>
        <strong class="small">Podmiot upoważniony</strong>
        <span class="badge bg-secondary-transparent ms-auto">opcjonalne</span>
      </div>
      <small class="text-muted d-block mb-3">Podmiot upoważniony do wystawiania faktur w imieniu sprzedawcy lub odbiorcy (element <code>PodmiotUpowazniony</code>).</small>
      <?php $authEntities = ($__isEdit && !empty($invoice->invoice_authorized_entities)) ? $invoice->invoice_authorized_entities : []; ?>
      <div id="auth-entities-list" class="vstack gap-2 mb-2">
        <?php foreach ($authEntities as $i => $ae): ?>
        <div class="border rounded p-2 auth-entity-row">
          <div class="row g-2 align-items-end">
            <div class="col-md-1">
              <label class="form-label small mb-1">Rola</label>
              <input type="number" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][rola]" value="<?= h($ae->rola ?? '') ?>" placeholder="1–4" min="1" max="4">
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Nazwa</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][name]" value="<?= h($ae->name ?? '') ?>" placeholder="Nazwa podmiotu">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">NIP</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][nip]" value="<?= h($ae->nip ?? '') ?>" placeholder="NIP">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Nr EORI</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][nr_eori]" value="<?= h($ae->nr_eori ?? '') ?>" placeholder="EORI">
            </div>
            <div class="col-md-1">
              <label class="form-label small mb-1">Kraj</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][country_code]" value="<?= h($ae->country_code ?? '') ?>" placeholder="PL" maxlength="2">
            </div>
            <div class="col-md-1">
              <label class="form-label small mb-1">&nbsp;</label>
              <button type="button" class="btn btn-outline-danger btn-sm d-block fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
            </div>
            <div class="col-md-4">
              <label class="form-label small mb-1">Adres linia 1</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][address_l1]" value="<?= h($ae->address_l1 ?? '') ?>" placeholder="Ulica, nr, kod, miasto">
            </div>
            <div class="col-md-4">
              <label class="form-label small mb-1">Adres linia 2 (opcj.)</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][address_l2]" value="<?= h($ae->address_l2 ?? '') ?>" placeholder="(opcjonalnie)">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Email</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][email]" value="<?= h($ae->email ?? '') ?>" placeholder="email@firma.pl">
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Telefon</label>
              <input type="text" class="form-control form-control-sm" name="auth_entities[<?= $i ?>][phone]" value="<?= h($ae->phone ?? '') ?>" placeholder="+48…">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-auth-entity">
        <i class="ri-add-line me-1"></i>Dodaj podmiot upoważniony
      </button>
    </div>

    <?php if (in_array($kind, ['advance', 'final'])): ?>
    <!-- ── Pozycje zamówienia (advance/final) ── -->
    <div class="border rounded p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-file-list-line fs-16 text-primary"></i>
        <strong class="small">Pozycje zamówienia</strong>
        <span class="badge bg-warning-transparent ms-auto">ZAL / ROZ</span>
      </div>
      <small class="text-muted d-block mb-3">Pozycje zamówienia dla faktur zaliczkowych i rozliczeniowych (KSeF ZAL/ROZ).</small>
      <?php $orderLines = ($__isEdit && !empty($invoice->invoice_order_lines)) ? $invoice->invoice_order_lines : []; ?>
      <div id="order-lines-list" class="vstack gap-1 mb-2">
        <?php foreach ($orderLines as $i => $ol): ?>
        <div class="row g-2 align-items-center order-line-row">
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
          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="add-order-line">
        <i class="ri-add-line me-1"></i>Dodaj pozycję zamówienia
      </button>
    </div>
    <?php endif; ?>

  </div><!-- /vstack -->
</div><!-- /tab-pane -->

<script>
(function(){
  // Dynamic rows for transaction conditions
  function addRowHandler(listSel, inputName, placeholder, btnSel) {
    document.querySelector(btnSel)?.addEventListener('click', function(){
      var row = document.createElement('div');
      row.className = 'input-group tc-umowa-row tc-zamowienie-row';
      row.innerHTML = '<input type="text" class="form-control form-control-sm" name="' + inputName + '" placeholder="' + placeholder + '">' +
        '<button type="button" class="btn btn-outline-danger btn-sm tc-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button>';
      document.querySelector(listSel)?.appendChild(row);
      row.querySelector('input')?.focus();
    });
  }
  addRowHandler('#tc-umowy-list',     'tc_umowy[]',     'Nr umowy',      '#tc-add-umowa');
  addRowHandler('#tc-zamowienia-list','tc_zamowienia[]','Nr zamówienia', '#tc-add-zamowienie');

  document.addEventListener('click', function(e){
    if (e.target.closest('.tc-remove-row'))   { e.target.closest('.input-group')?.remove(); }
    if (e.target.closest('.fa3-remove-row'))  {
      e.target.closest('.charge-row, .factor-bank-row, .auth-entity-row, .order-line-row')?.remove();
    }
  });

  // Counter for new row indexes
  var chargeIdx = <?= count($charges ?? []) ?>;
  var fbIdx     = <?= count($factorBanks ?? []) ?>;
  var aeIdx     = <?= count($authEntities ?? []) ?>;
  var olIdx     = <?= count($orderLines ?? []) ?>;

  // Add charge row
  document.getElementById('add-charge')?.addEventListener('click', function(){
    var i = chargeIdx++;
    var div = document.createElement('div');
    div.className = 'row g-2 align-items-center charge-row';
    div.innerHTML =
      '<div class="col-md-3"><select class="form-select form-select-sm" name="charges['+i+'][type]"><option value="obciazenie">Obciążenie</option><option value="odliczenie">Odliczenie</option></select></div>' +
      '<div class="col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control" name="charges['+i+'][kwota]" placeholder="Kwota"><span class="input-group-text">PLN</span></div></div>' +
      '<div class="col-md-5"><input type="text" class="form-control form-control-sm" name="charges['+i+'][powod]" placeholder="Powód / opis"></div>' +
      '<div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button></div>';
    document.getElementById('charges-list')?.appendChild(div);
  });

  // Add factor bank row
  document.getElementById('add-factor-bank')?.addEventListener('click', function(){
    var i = fbIdx++;
    var div = document.createElement('div');
    div.className = 'border rounded p-2 factor-bank-row';
    div.innerHTML =
      '<div class="row g-2 align-items-end">' +
        '<div class="col-md-4"><label class="form-label small mb-1">Nr rachunku (IBAN)</label><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][nr_rb]" placeholder="PL00 0000…"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">SWIFT / BIC</label><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][swift]" placeholder="PKOPPLPW"></div>' +
        '<div class="col-md-3"><label class="form-label small mb-1">Nazwa banku</label><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][nazwa_banku]" placeholder="PKO Bank Polski"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">Opis rachunku</label><input type="text" class="form-control form-control-sm" name="factor_banks['+i+'][opis_rachunku]" placeholder="Opis (opcj.)"></div>' +
        '<div class="col-md-1 text-end"><label class="form-label small mb-1">&nbsp;</label><button type="button" class="btn btn-outline-danger btn-sm d-block fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button></div>' +
        '<div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="factor_banks['+i+'][rachunek_wlasny_banku]" value="1"><label class="form-check-label small">Rachunek własny banku</label></div></div>' +
      '</div>';
    document.getElementById('factor-banks-list')?.appendChild(div);
  });

  // Add authorized entity row
  document.getElementById('add-auth-entity')?.addEventListener('click', function(){
    var i = aeIdx++;
    var div = document.createElement('div');
    div.className = 'border rounded p-2 auth-entity-row';
    div.innerHTML =
      '<div class="row g-2 align-items-end">' +
        '<div class="col-md-1"><label class="form-label small mb-1">Rola</label><input type="number" class="form-control form-control-sm" name="auth_entities['+i+'][rola]" placeholder="1–4" min="1" max="4"></div>' +
        '<div class="col-md-3"><label class="form-label small mb-1">Nazwa</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][name]" placeholder="Nazwa podmiotu"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">NIP</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][nip]" placeholder="NIP"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">Nr EORI</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][nr_eori]" placeholder="EORI"></div>' +
        '<div class="col-md-1"><label class="form-label small mb-1">Kraj</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][country_code]" placeholder="PL" maxlength="2"></div>' +
        '<div class="col-md-1"><label class="form-label small mb-1">&nbsp;</label><button type="button" class="btn btn-outline-danger btn-sm d-block fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button></div>' +
        '<div class="col-md-4"><label class="form-label small mb-1">Adres linia 1</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][address_l1]" placeholder="Ulica, nr, kod, miasto"></div>' +
        '<div class="col-md-4"><label class="form-label small mb-1">Adres linia 2 (opcj.)</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][address_l2]" placeholder="(opcjonalnie)"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">Email</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][email]" placeholder="email@firma.pl"></div>' +
        '<div class="col-md-2"><label class="form-label small mb-1">Telefon</label><input type="text" class="form-control form-control-sm" name="auth_entities['+i+'][phone]" placeholder="+48…"></div>' +
      '</div>';
    document.getElementById('auth-entities-list')?.appendChild(div);
  });

  // Add order line row
  document.getElementById('add-order-line')?.addEventListener('click', function(){
    var i = olIdx++;
    var div = document.createElement('div');
    div.className = 'row g-2 align-items-center order-line-row';
    div.innerHTML =
      '<div class="col-md-1"><input type="number" class="form-control form-control-sm" name="order_lines['+i+'][nr_wiersza]" placeholder="Nr" min="1" value="'+(i+1)+'"></div>' +
      '<div class="col-md-3"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][name]" placeholder="Nazwa"></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][unit]" placeholder="Jm."></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][quantity]" placeholder="Ilość"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][price]" placeholder="Cena netto"></div>' +
      '<div class="col-md-2"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][netto]" placeholder="Netto"></div>' +
      '<div class="col-md-1"><input type="text" class="form-control form-control-sm" name="order_lines['+i+'][vat_rate]" placeholder="VAT%"></div>' +
      '<div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger btn-sm fa3-remove-row" title="Usuń"><i class="ri-delete-bin-line"></i></button></div>';
    document.getElementById('order-lines-list')?.appendChild(div);
  });

})();
</script>

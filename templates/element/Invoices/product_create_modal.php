<!-- Modal: Dodaj produkt -->
<datalist id="prod-units-list">
  <option value="szt.">szt. – sztuki</option>
  <option value="kg">kg – kilogram</option>
  <option value="g">g – gram</option>
  <option value="l">l – litr</option>
  <option value="ml">ml – mililitr</option>
  <option value="m">m – metr</option>
  <option value="m2">m² – metr kwadratowy</option>
  <option value="m3">m³ – metr sześcienny</option>
  <option value="km">km – kilometr</option>
  <option value="cm">cm – centymetr</option>
  <option value="t">t – tona</option>
  <option value="h">h – godzina</option>
  <option value="godz.">godz. – godzina</option>
  <option value="min.">min. – minuta</option>
  <option value="dn.">dn. – dzień</option>
  <option value="mies.">mies. – miesiąc</option>
  <option value="rok">rok</option>
  <option value="kpl.">kpl. – komplet</option>
  <option value="op.">op. – opakowanie</option>
  <option value="par">par – para</option>
  <option value="zest.">zest. – zestaw</option>
  <option value="usł.">usł. – usługa</option>
  <option value="MB">MB – megabajt</option>
  <option value="GB">GB – gigabajt</option>
  <option value="TB">TB – terabajt</option>
</datalist>

<div class="modal fade" id="product-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-price-tag-3-line me-1"></i> Dodaj produkt / usługę</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= $this->Form->create(null, ['url' => ['controller' => 'Products','action' => 'add'], 'id' => 'product-create-form']) ?>
      <div class="modal-body">

        <!-- Podstawowe informacje -->
        <div class="mb-3">
          <label class="form-label fw-semibold">Nazwa <span class="text-danger">*</span></label>
          <input type="text" name="name" id="product-create-name" class="form-control" required placeholder="Nazwa produktu lub usługi">
          <div class="invalid-feedback">Podaj nazwę.</div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Kod / SKU</label>
            <input type="text" name="code" id="product-create-code" class="form-control" placeholder="np. PRD-001 (auto)">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Typ</label>
            <select name="is_service" class="form-select" id="product-create-type">
              <option value="0">📦 Produkt / towar</option>
              <option value="1">🔧 Usługa</option>
            </select>
          </div>
        </div>

        <!-- Cena i jednostka -->
        <div class="row g-2 mb-3">
          <div class="col-4">
            <label class="form-label fw-semibold">Jednostka miary</label>
            <input type="text" name="unit_name" id="product-create-unit" class="form-control"
              value="szt." list="prod-units-list" autocomplete="off"
              placeholder="np. szt., kg, h…">
            <div class="form-text">Wpisz lub wybierz z listy</div>
          </div>
          <div class="col-4">
            <label class="form-label fw-semibold">Cena netto</label>
            <div class="input-group">
              <input type="number" name="net_price" step="0.01" min="0" class="form-control text-end" placeholder="0.00" id="product-create-price">
              <span class="input-group-text" id="prod-currency-label">PLN</span>
            </div>
          </div>
          <div class="col-4">
            <label class="form-label fw-semibold">Stawka VAT</label>
            <?= $this->Form->control('vat_id', ['label' => false, 'type' => 'select', 'options' => $vats, 'class' => 'form-select', 'id' => 'product-create-vat']) ?>
          </div>
        </div>

        <!-- Klasyfikacja -->
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold">PKWiU</label>
            <input type="text" name="pkwiu" class="form-control" placeholder="np. 62.01.10.0" id="product-create-pkwiu">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Kod GTU</label>
            <select name="gtu_code" class="form-select" id="product-create-gtu">
              <option value="">— brak GTU —</option>
              <option value="GTU_01">GTU_01 – napoje alkoholowe</option>
              <option value="GTU_02">GTU_02 – paliwa silnikowe</option>
              <option value="GTU_03">GTU_03 – oleje opałowe / smarowe</option>
              <option value="GTU_04">GTU_04 – wyroby tytoniowe</option>
              <option value="GTU_05">GTU_05 – odpady</option>
              <option value="GTU_06">GTU_06 – urządzenia elektroniczne</option>
              <option value="GTU_07">GTU_07 – pojazdy i części</option>
              <option value="GTU_08">GTU_08 – metale szlachetne</option>
              <option value="GTU_09">GTU_09 – leki i wyroby medyczne</option>
              <option value="GTU_10">GTU_10 – budynki, budowle i grunty</option>
              <option value="GTU_11">GTU_11 – usługi w zakresie przenoszenia uprawnień do emisji</option>
              <option value="GTU_12">GTU_12 – usługi niematerialne (IT, doradcze, reklamowe…)</option>
              <option value="GTU_13">GTU_13 – transport i gospodarka magazynowa</option>
            </select>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Opis</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Opcjonalny opis produktu / usługi"></textarea>
          </div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-12">
            <label class="form-label fw-semibold">Kod kreskowy / EAN</label>
            <input type="text" name="barcode" class="form-control" placeholder="np. 5901234123457">
          </div>
        </div>

        <!-- Sekcja KSeF – zwijana -->
        <div class="border rounded p-0 mt-3">
          <button type="button" class="btn btn-link text-decoration-none d-flex align-items-center w-100 px-3 py-2 text-start gap-1"
            data-bs-toggle="collapse" data-bs-target="#prod-ksef-section" aria-expanded="false" aria-controls="prod-ksef-section"
            id="prod-ksef-toggle">
            <i class="ri-arrow-right-s-line" id="prod-ksef-chevron" style="transition: transform .2s"></i>
            <span class="small fw-semibold text-muted">Pola klasyfikacyjne KSeF / JPK</span>
            <span class="badge bg-secondary-subtle text-secondary ms-1 small">opcjonalne</span>
          </button>
          <div class="collapse" id="prod-ksef-section">
            <div class="px-3 pb-3 pt-1">

              <div class="row g-2 mb-2">
                <div class="col-4">
                  <label class="form-label small">GTIN / EAN
                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                      data-bs-toggle="tooltip" title="Unikatowy kod handlowy produktu (EAN-8, EAN-13, UPC). Np. 5901234123457.">
                      <i class="ri-question-line text-muted"></i>
                    </button>
                  </label>
                  <input type="text" name="gtin" class="form-control form-control-sm" placeholder="5901234123457">
                </div>
                <div class="col-4">
                  <label class="form-label small">Kod CN
                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                      data-bs-toggle="tooltip" title="Kod towaru wg Nomenklatury Scalonej (CN) – dla obrotu międzynarodowego.">
                      <i class="ri-question-line text-muted"></i>
                    </button>
                  </label>
                  <input type="text" name="cn_code" class="form-control form-control-sm" placeholder="8471 30 00">
                </div>
                <div class="col-4">
                  <label class="form-label small">PKOB
                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                      data-bs-toggle="tooltip" title="Polska Klasyfikacja Obiektów Budowlanych – dla budynków i budowli.">
                      <i class="ri-question-line text-muted"></i>
                    </button>
                  </label>
                  <input type="text" name="pkob" class="form-control form-control-sm" placeholder="1110">
                </div>
              </div>

              <div class="row g-2 mb-2">
                <div class="col-6">
                  <label class="form-label small">Kwota akcyzy
                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                      data-bs-toggle="tooltip" title="Kwota podatku akcyzowego zawarta w cenie (paliwa, alkohol, tytoń).">
                      <i class="ri-question-line text-muted"></i>
                    </button>
                  </label>
                  <input type="number" name="excise_amount" step="0.01" min="0" class="form-control form-control-sm" placeholder="0.00">
                </div>
                <div class="col-6 d-flex align-items-end pb-1">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="prod-attachment15" name="is_attachment15" value="1">
                    <label class="form-check-label small" for="prod-attachment15">
                      Towar/usługa z zał.&nbsp;15 (MPP)
                      <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                        data-bs-toggle="tooltip" title="Pozycja z załącznika nr 15 ustawy o VAT – obowiązkowy mechanizm podzielonej płatności.">
                        <i class="ri-question-line text-muted"></i>
                      </button>
                    </label>
                  </div>
                </div>
              </div>

              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label small">Oznaczenie procedury (FA(3))
                    <button type="button" class="btn btn-link p-0 ms-1 align-baseline" tabindex="-1"
                      data-bs-toggle="tooltip" title="Dodatkowe oznaczenia procedur wymagane w KSeF / JPK_VAT (np. metoda kasowa, powiązane podmioty).">
                      <i class="ri-question-line text-muted"></i>
                    </button>
                  </label>
                  <select name="procedure_marking" class="form-select form-select-sm">
                    <option value="">— brak —</option>
                    <option value="MR_T">MR_T – metoda kasowa (dostawa towarów)</option>
                    <option value="MR_UZ">MR_UZ – metoda kasowa (świadczenie usług)</option>
                    <option value="EE">EE – energia elektryczna, gaz, usługi dystrybucji</option>
                    <option value="TP">TP – podmioty powiązane (art. 32 ustawy VAT)</option>
                    <option value="TT_WNT">TT_WNT – WNT w transakcji trójstronnej uproszczonej</option>
                    <option value="TT_D">TT_D – dostawa w transakcji trójstronnej uproszczonej</option>
                    <option value="I_42">I_42 – WDT po imporcie w procedurze celnej 42</option>
                    <option value="I_63">I_63 – WDT po imporcie w procedurze celnej 63</option>
                    <option value="B_SPV">B_SPV – transfer bonu jednego przeznaczenia</option>
                    <option value="B_SPV_DOSTAWA">B_SPV_DOSTAWA – dostawa towarów dot. bonu jednego przeznaczenia</option>
                    <option value="B_MPV_PROWIZJA">B_MPV_PROWIZJA – prowizja dot. bonu różnego przeznaczenia</option>
                    <option value="MPP">MPP – mechanizm podzielonej płatności</option>
                  </select>
                </div>
              </div>

            </div>
          </div>
        </div>

        <?= $this->Form->hidden('unit_id', ['value' => 1]) ?>
        <?= $this->Form->hidden('currency', ['value' => 'PLN']) ?>
        <?= $this->Form->hidden('is_active', ['value' => 1]) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button type="submit" id="product-create-submit" class="btn btn-primary">
          <span class="spinner-border spinner-border-sm d-none me-1" id="prod-submit-spinner" role="status" aria-hidden="true"></span>
          <i class="ri-save-line me-1" id="prod-submit-icon"></i>
          <span>Zapisz produkt</span>
        </button>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

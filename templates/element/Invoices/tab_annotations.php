<?php
/**
 * Element: Adnotacje KSeF (zakładka na formularzu faktury)
 *
 * Zmienne wymagane:
 *   $invoice — encja Invoice (lub nowa przy add)
 *   $__isEdit — bool, true = tryb edycji
 *
 * Dekoduje JSON z kolumny `annotations` aby wstępnie zaznaczyć checkboxy.
 */
$ann = [];
if (!empty($invoice->annotations)) {
    $decoded = is_array($invoice->annotations) ? $invoice->annotations : json_decode((string)$invoice->annotations, true);
    if (is_array($decoded)) {
        $ann = $decoded;
    }
}
?>
<div class="tab-pane fade" id="pane-annotations" role="tabpanel" aria-labelledby="tab-annotations">
  <div class="vstack gap-3">
    <p class="text-muted small mb-0">Dodatkowe adnotacje KSeF — zaznacz jeśli faktura dotyczy jednej z poniższych procedur.</p>

    <!-- Metoda kasowa -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-cash-method" name="annotations[cash_method]" value="1"<?= !empty($ann['cash_method']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-cash-method">Metoda kasowa</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Metoda kasowa"
        data-bs-content="<div class='small text-start'>Oznacza, że sprzedawca (co do zasady mały podatnik stosujący metodę kasową) rozlicza VAT dopiero po otrzymaniu zapłaty. Dla nabywcy odliczenie VAT z&nbsp;takiej faktury następuje nie wcześniej niż po zapłacie.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

    <!-- Odwrotne obciążenie -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-reverse-charge" name="annotations[reverse_charge]" value="1"<?= !empty($ann['reverse_charge']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-reverse-charge">Odwrotne obciążenie</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Odwrotne obciążenie"
        data-bs-content="<div class='small text-start'>VAT rozlicza nabywca, a&nbsp;nie sprzedawca. Adnotacja może występować tam, gdzie przepisy przewidują taki mechanizm, w&nbsp;tym czasowo dla niektórych transakcji z&nbsp;art.&nbsp;145e ustawy o&nbsp;VAT.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

    <!-- Procedura trójstronna uproszczona -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-triangular" name="annotations[triangular]" value="1"<?= !empty($ann['triangular']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-triangular">Procedura trójstronna uproszczona</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Procedura trójstronna uproszczona"
        data-bs-content="<div class='small text-start'>Unijna transakcja 3&nbsp;firm z&nbsp;3 różnych państw UE, gdzie towar jedzie od pierwszego dostawcy do ostatniego nabywcy, a&nbsp;środkowy podmiot korzysta z&nbsp;uproszczenia. Na fakturze musi być specjalna adnotacja, a&nbsp;VAT rozlicza ostatni nabywca.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

    <!-- Procedura OSS -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-oss" name="annotations[oss]" value="1"<?= !empty($ann['oss']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-oss">Procedura OSS (One Stop Shop)</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Procedura OSS"
        data-bs-content="<div class='small text-start'>Rozliczanie VAT od określonej sprzedaży B2C w&nbsp;UE przez jeden system w&nbsp;jednym państwie, zamiast rejestrować się do VAT w&nbsp;każdym kraju osobno. Dotyczy sprzedaży do konsumentów w&nbsp;innych krajach UE.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

    <!-- Oznaczenie TP -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-tp" name="annotations[tp]" value="1"<?= !empty($ann['tp']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-tp">Oznaczenie TP (powiązania)</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Oznaczenie TP"
        data-bs-content="<div class='small text-start'>Informacja, że między sprzedawcą a&nbsp;nabywcą istnieją powiązania. To przede wszystkim oznaczenie ewidencyjne/JPK; w&nbsp;KSeF może pojawić się na fakturze jako dodatkowa informacja.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

    <!-- Zwrot akcyzy -->
    <div class="d-flex align-items-start gap-2">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="ann-excise-return" name="annotations[excise_return]" value="1"<?= !empty($ann['excise_return']) ? ' checked' : '' ?>>
        <label class="form-check-label" for="ann-excise-return">Zwrot akcyzy</label>
      </div>
      <button type="button" class="btn btn-link p-0 align-baseline" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
        title="Zwrot akcyzy"
        data-bs-content="<div class='small text-start'>Dodatkowa informacja związana ze zwrotem podatku akcyzowego zawartego w&nbsp;cenie oleju napędowego. Dotyczy głównie paliwa do produkcji rolnej; producent rolny składa wniosek na podstawie faktur, a&nbsp;urząd gminy nanosi adnotację o&nbsp;przyjęciu do zwrotu.</div>">
        <i class="ri-question-line"></i>
      </button>
    </div>

  </div>
</div>

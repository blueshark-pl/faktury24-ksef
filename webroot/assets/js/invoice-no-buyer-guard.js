/*
 * Strażnik formularza faktury: zapis / wysyłka do KSeF bez danych nabywcy wymaga świadomego potwierdzenia.
 *
 * Tło: faktura sprzedażowa bez nazwy i NIP nabywcy przechodzi walidację (wystarczy wybrany kontrahent lub nazwa)
 * i trafia do KSeF jako Podmiot2 z <BrakID>, więc kontrahent nigdy jej nie widzi (zgłoszenie biura, FV/1/09/2026).
 * Działa na każdym formularzu `form.needs-validation` z polem invoice_contractor[name]; nasłuch w fazie capture,
 * więc uruchamia się PRZED walidacją AJAX z szablonu. Po potwierdzeniu dodaje pole confirm_no_buyer=1,
 * które serwer honoruje w sendInvoiceToKsefCore().
 */
(function () {
  'use strict';

  function val(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? String(el.value || '').trim() : '';
  }

  function buyerMissing(form) {
    if (!form.querySelector('[name="invoice_contractor[name]"]')) return false; // to nie jest formularz faktury
    return ['invoice_contractor[name]', 'invoice_contractor[nip]', 'invoice_contractor[vat_eu]', 'invoice_contractor[tax_id_other]']
      .every(function (n) { return val(form, n) === ''; });
  }

  function willSendToKsef(form, submitter) {
    if (submitter && submitter.name === 'save_and_send_ksef') return true;
    var chk = form.querySelector('[name="ksef_send"]');
    if (chk && (chk.type !== 'checkbox' || chk.checked) && String(chk.value) === '1') return true;
    return false;
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('needs-validation')) return;
    if (form.dataset.noBuyerConfirmed === '1') return;
    if (!buyerMissing(form)) return;

    var submitter = e.submitter || null;
    var sending = willSendToKsef(form, submitter);
    e.preventDefault();
    e.stopImmediatePropagation();

    var title = 'Faktura bez danych nabywcy';
    var html = '<div class="text-start">'
      + '<p>Nie podano <strong>nazwy, NIP-u ani adresu nabywcy</strong>.</p>'
      + (sending
          ? '<p>Faktura trafi do KSeF jako dokument <strong>bez identyfikatora nabywcy</strong> — kontrahent <strong>nie zobaczy jej</strong> w swoim KSeF i nie odliczy VAT.</p>'
          : '<p>Taka faktura po wysłaniu do KSeF nie będzie widoczna u kontrahenta.</p>')
      + '<p class="mb-0">Jeśli to pomyłka, wróć i wybierz kontrahenta.</p></div>';

    function proceed() {
      var hidden = form.querySelector('input[name="confirm_no_buyer"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'confirm_no_buyer';
        form.appendChild(hidden);
      }
      hidden.value = '1';
      form.dataset.noBuyerConfirmed = '1';
      if (submitter && typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter);
      } else if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    }

    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'warning',
        title: title,
        html: html,
        showCancelButton: true,
        confirmButtonText: sending ? 'Wystaw i wyślij bez nabywcy' : 'Zapisz bez nabywcy',
        cancelButtonText: 'Wróć i popraw',
        confirmButtonColor: '#dc3545',
        focusCancel: true,
        reverseButtons: true
      }).then(function (r) { if (r.isConfirmed) proceed(); });
    } else if (window.confirm('Faktura nie ma danych nabywcy (nazwa, NIP, adres). Nabywca nie zobaczy jej w KSeF. Kontynuować?')) {
      proceed();
    }
  }, true);
})();

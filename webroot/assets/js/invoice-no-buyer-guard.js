/*
 * Strażnik formularza faktury: zapis / wystawienie / wysyłka do KSeF bez danych nabywcy wymaga
 * świadomego potwierdzenia (SweetAlert). Po potwierdzeniu formularz dostaje pole confirm_no_buyer=1,
 * które honorują validateAjax() i sendInvoiceToKsefCore() po stronie serwera.
 *
 * Tło: faktura bez nazwy i NIP nabywcy trafiała do KSeF jako Podmiot2 z <BrakID> — kontrahent nigdy jej
 * nie widział (zgłoszenie biura, FV/1/09/2026). Szkice omijają walidację, a przyciski w add.php wysyłają
 * formularz przez jQuery trigger('submit'), które NIE generuje natywnego zdarzenia submit — dlatego
 * przechwytujemy KLIKNIĘCIA w przyciski (faza capture, przed handlerami jQuery) i dodatkowo submit
 * (Alt+S / requestSubmit).
 */
(function () {
  'use strict';

  var FORM_SEL = 'form.needs-validation';
  var BUYER_FIELDS = ['invoice_contractor[name]', 'invoice_contractor[nip]', 'invoice_contractor[vat_eu]', 'invoice_contractor[tax_id_other]'];

  function invoiceForm() {
    var forms = document.querySelectorAll(FORM_SEL);
    for (var i = 0; i < forms.length; i++) {
      if (forms[i].querySelector('[name="invoice_contractor[name]"]')) return forms[i];
    }
    return null;
  }

  function val(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? String(el.value || '').trim() : '';
  }

  function buyerMissing(form) {
    return BUYER_FIELDS.every(function (n) { return val(form, n) === ''; });
  }

  function confirmed(form) {
    var h = form.querySelector('input[name="confirm_no_buyer"]');
    return !!(h && String(h.value) === '1');
  }

  function markConfirmed(form) {
    var h = form.querySelector('input[name="confirm_no_buyer"]');
    if (!h) {
      h = document.createElement('input');
      h.type = 'hidden';
      h.name = 'confirm_no_buyer';
      form.appendChild(h);
    }
    h.value = '1';
  }

  // Gdy użytkownik wróci i uzupełni nabywcę, potwierdzenie przestaje obowiązywać.
  function clearConfirmationIfBuyerFilled(form) {
    if (!buyerMissing(form)) {
      var h = form.querySelector('input[name="confirm_no_buyer"]');
      if (h) h.value = '0';
    }
  }

  function ask(mode, onConfirm) {
    var sending = mode === 'send';
    var draft = mode === 'draft';
    var html = '<div class="text-start">'
      + '<p>Nie podano <strong>nazwy, NIP-u ani adresu nabywcy</strong>.</p>'
      + (sending
          ? '<p>Faktura trafi do KSeF jako dokument <strong>bez identyfikatora nabywcy</strong> — kontrahent <strong>nie zobaczy jej</strong> w swoim KSeF i nie odliczy VAT.</p>'
          : draft
            ? '<p>Robocza zostanie zapisana bez nabywcy. Przed wystawieniem lub wysyłką do KSeF trzeba będzie uzupełnić dane nabywcy albo ponownie potwierdzić.</p>'
            : '<p>Po wysłaniu do KSeF taka faktura nie będzie widoczna u kontrahenta.</p>')
      + '<p class="mb-0">Jeśli to pomyłka, wróć i wybierz kontrahenta.</p></div>';
    var confirmText = sending ? 'Wystaw i wyślij bez nabywcy' : draft ? 'Zapisz roboczą bez nabywcy' : 'Wystaw bez nabywcy';

    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'warning',
        title: 'Faktura bez danych nabywcy',
        html: html,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Wróć i popraw',
        confirmButtonColor: '#dc3545',
        focusCancel: true,
        reverseButtons: true
      }).then(function (r) { if (r.isConfirmed) onConfirm(); });
    } else if (window.confirm('Faktura nie ma danych nabywcy (nazwa, NIP, adres). Nabywca nie zobaczy jej w KSeF. Kontynuować?')) {
      onConfirm();
    }
  }

  function modeForButton(btn) {
    var name = btn.getAttribute('name') || '';
    var id = btn.id || '';
    if (name === 'save_and_send_ksef' || id === 'ksef-confirm-send-btn') return 'send';
    if (name === 'save_draft') return 'draft';
    return 'issue';
  }

  // 1) Kliknięcia w przyciski zapisu/wysyłki (capture — przed handlerami jQuery z add.php)
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('button, input[type="submit"]') : null;
    if (!btn) return;
    var isSubmit = (btn.type === 'submit') || btn.getAttribute('name') === 'save_and_send_ksef' || btn.getAttribute('name') === 'save_draft';
    var isModalConfirm = btn.id === 'ksef-confirm-send-btn';
    if (!isSubmit && !isModalConfirm) return;
    var form = btn.form || invoiceForm();
    if (!form || !form.querySelector('[name="invoice_contractor[name]"]')) return;
    if (btn.id === 'product-create-submit' || btn.id === 'contractor-create-submit' || btn.closest('#product-create-form, #contractor-create-form')) return;

    clearConfirmationIfBuyerFilled(form);
    if (!buyerMissing(form) || confirmed(form)) return;

    e.preventDefault();
    e.stopImmediatePropagation();
    ask(modeForButton(btn), function () {
      markConfirmed(form);
      btn.click(); // ponowne kliknięcie — teraz strażnik przepuszcza, dalej działa normalny przepływ
    });
  }, true);

  // 2) Natywny submit (Alt+S, requestSubmit) — dla pełności
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('needs-validation')) return;
    if (!form.querySelector('[name="invoice_contractor[name]"]')) return;
    clearConfirmationIfBuyerFilled(form);
    if (!buyerMissing(form) || confirmed(form)) return;
    var submitter = e.submitter || null;
    e.preventDefault();
    e.stopImmediatePropagation();
    ask(submitter ? modeForButton(submitter) : 'issue', function () {
      markConfirmed(form);
      if (submitter) { submitter.click(); return; }
      if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
    });
  }, true);
})();

<?php
/**
 * @var \App\View\AppView $this
 * @var string $sourceFullnumber
 * @var string $duplicatedInvoiceId
 */
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    title: 'Faktura zduplikowana ✓',
    html: `<div style="text-align: left; font-size: 0.95rem;">
      <p><strong>Nowa faktura jest w trybie roboczym.</strong></p>
      <p style="margin-bottom: 1rem; color: #666;">Pamiętaj:</p>
      <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
        <li><strong>Data wystawienia:</strong> <em>dzisiejsza</em></li>
        <li><strong>Data sprzedaży:</strong> <em>zachowana z oryginału</em></li>
        <li><strong>Bez numeracji KSeF, płatności i statusów</strong></li>
      </ul>
    </div>`,
    icon: 'success',
    confirmButtonText: 'Przejdź do faktury',
    confirmButtonColor: '#0d6efd',
    allowOutsideClick: false,
    allowEscapeKey: false,
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = '<?= $this->Url->build(['action' => 'view', $duplicatedInvoiceId]) ?>';
    }
  });
});
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var string $sourceFullnumber
 * @var string $duplicatedInvoiceId
 */

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'duplicatedInvoiceId' => $duplicatedInvoiceId,
    'message' => 'Faktura zduplikowana pomyślnie'
]);
exit;
?>

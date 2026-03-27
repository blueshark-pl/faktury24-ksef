<?php
declare(strict_types=1);

namespace App\Service\Invoice;

use Cake\Http\Client;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Generuje PDF faktury i wysyła go e-mailem.
 * Używany zarówno przez InvoicesController::emailInvoice() jak i przez ProcessEmailQueueCommand.
 */
class InvoiceEmailSender
{
    use LocatorAwareTrait;

    /**
     * Pobiera fakturę z bazy, generuje PDF i wysyła na wskazany adres.
     *
     * @param string $invoiceId  UUID faktury
     * @param string $companyId  UUID firmy (walidacja właściciela)
     * @param string $email      Adres e-mail odbiorcy
     * @param callable $xmlBuilder  fn(Invoice): string — generuje FA(3) XML
     * @return array{success: bool, error?: string}
     */
    public function send(string $invoiceId, string $companyId, string $email, callable $xmlBuilder): array
    {
        /** @var \Cake\ORM\Table $Invoices */
        $Invoices = $this->fetchTable('Invoices');

        try {
            $invoice = $Invoices->get($invoiceId, [
                'contain' => [
                    'InvoiceContractors',
                    'InvoiceContents' => ['Vats'],
                    'Companies',
                    'InvoiceCompanyDetails',
                ],
                'conditions' => ['Invoices.company_id' => $companyId],
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Faktura nie znaleziona: ' . $e->getMessage()];
        }

        $pdfContent = $this->generatePdf($invoice, $xmlBuilder);
        if ($pdfContent === '') {
            return ['success' => false, 'error' => 'Nie udało się wygenerować PDF faktury.'];
        }

        $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
        $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';
        $sellerName = trim((string)($invoice->invoice_company_detail?->name ?? $invoice->company?->name ?? ''));
        $subject    = 'Faktura ' . $fullnumber . ($sellerName !== '' ? ' od ' . $sellerName : '');

        try {
            $mailer = new Mailer('default');
            $mailer->setTo($email);
            $mailer->setSubject($subject);
            $mailer->setEmailFormat('html');
            $mailer->setAttachments([
                $filename => ['data' => $pdfContent, 'mimetype' => 'application/pdf'],
            ]);
            $mailer->viewBuilder()->setLayout('default')->setTemplate('invoice_email');
            $mailer->setViewVars([
                'invoice'    => $invoice,
                'fullnumber' => $fullnumber,
                'sellerName' => $sellerName,
            ]);
            $mailer->deliver();

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('[InvoiceEmailSender] send failed invoice=' . $invoiceId . ': ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function generatePdf(object $invoice, callable $xmlBuilder): string
    {
        try {
            $xml = $xmlBuilder($invoice);
            if (!is_string($xml) || trim($xml) === '') {
                return '';
            }

            $isDraft = ((string)($invoice->workflow_status ?? '')) === 'draft';
            $apiUrl  = $isDraft
                ? (getenv('INVOICE_DRAFT_API_URL') ?: 'https://faktury24-draft.3ckstudio.pl/api/invoice')
                : (getenv('INVOICE_API_URL') ?: 'https://faktury24.3ckstudio.pl/api/invoice');

            $seller    = $invoice->invoice_company_detail ?? null;
            $nip       = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
            $issueDate = $invoice->date ? $invoice->date->format('d-m-Y') : '';
            $invRef    = (string)($invoice->ksef_invoice_reference ?? '');
            $qrCode    = ($nip !== '' && $issueDate !== '' && $invRef !== '')
                ? ('https://ksef-test.mf.gov.pl/client-app/invoice/' . $nip . '/' . $issueDate . '/' . $invRef)
                : '';

            $http = new Client(['timeout' => 60]);
            $resp = $http->post($apiUrl, [
                'xml'            => $xml,
                'additionalData' => [
                    'nrKSeF'    => (string)($invoice->ksef_number ?? ''),
                    'qrCode'    => $qrCode,
                    'isPreview' => $isDraft,
                ],
            ], ['type' => 'json']);

            return $resp->getStatusCode() === 200 ? (string)$resp->getBody() : '';
        } catch (\Throwable $e) {
            Log::error('[InvoiceEmailSender] PDF error invoice=' . $invoice->id . ': ' . $e->getMessage());
            return '';
        }
    }
}

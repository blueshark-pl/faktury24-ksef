<?php
declare(strict_types=1);

namespace App\Service\Invoice;

use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Wysyła przypomnienie o płatności (BEZ załączania faktury — tylko monit).
 * Inny niż InvoiceEmailSender, który wysyła pełną fakturę z PDF.
 */
class PaymentReminderEmailService
{
    use LocatorAwareTrait;

    /**
     * @param string $invoiceId
     * @param string $companyId
     * @param string $recipientEmail
     * @param string $customMessage  Opcjonalna wiadomość użytkownika (zastępuje template)
     * @return array{success: bool, error?: string, subject?: string}
     */
    public function send(string $invoiceId, string $companyId, string $recipientEmail, string $customMessage = ''): array
    {
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Nieprawidłowy adres email odbiorcy.'];
        }

        $Invoices = $this->fetchTable('Invoices');
        try {
            $invoice = $Invoices->find()
                ->contain([
                    'InvoiceContractors' => function ($q) {
                        return $q->select(['id', 'invoice_id', 'name', 'nip']);
                    },
                    'InvoiceCompanyDetails' => function ($q) {
                        return $q->select(['id', 'invoice_id', 'name', 'email']);
                    },
                    'Companies' => function ($q) {
                        return $q->select(['id', 'name']);
                    },
                ])
                ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
                ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.remaining',
                          'Invoices.currency', 'Invoices.paymentdate', 'Invoices.paymentstate',
                          'Invoices.date'])
                ->first();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Faktura nie znaleziona.'];
        }

        if (!$invoice) {
            return ['success' => false, 'error' => 'Faktura nie istnieje lub brak uprawnień.'];
        }

        $fullnumber = (string)$invoice->fullnumber;
        $remaining  = (float)$invoice->remaining;
        $currency   = (string)$invoice->currency;
        $today      = date('Y-m-d');
        $pd         = $invoice->paymentdate;
        $pdStr      = $pd instanceof \DateTimeInterface ? $pd->format('Y-m-d') : substr((string)$pd, 0, 10);
        $daysOverdue = 0;
        $daysToDue   = 0;
        if ($pdStr) {
            $diff = (int)floor((strtotime($pdStr) - strtotime($today)) / 86400);
            if ($diff < 0) $daysOverdue = abs($diff);
            else $daysToDue = $diff;
        }

        $sellerName = trim((string)($invoice->invoice_company_detail?->name ?? $invoice->company?->name ?? ''));
        $sellerEmail = (string)($invoice->invoice_company_detail?->email ?? '');

        $subject = $daysOverdue > 0
            ? 'Przypomnienie o przeterminowanej płatności — ' . $fullnumber
            : 'Przypomnienie o płatności — ' . $fullnumber;

        try {
            $mailer = new Mailer('default');
            $mailer->setTo($recipientEmail);
            if ($sellerEmail !== '' && filter_var($sellerEmail, FILTER_VALIDATE_EMAIL)) {
                $mailer->setReplyTo($sellerEmail);
            }
            $mailer->setSubject($subject);
            $mailer->setEmailFormat('html');
            $mailer->viewBuilder()->setLayout(false)->disableAutoLayout();
            $mailer->setViewVars([
                'fullnumber'   => $fullnumber,
                'remaining'    => $remaining,
                'currency'     => $currency,
                'paymentdate'  => $pdStr,
                'days_overdue' => $daysOverdue,
                'days_to_due'  => $daysToDue,
                'seller_name'  => $sellerName,
                'contractor_name' => (string)($invoice->invoice_contractor->name ?? ''),
                'custom_message' => $customMessage,
            ]);
            $mailer->viewBuilder()->setTemplate('payment_reminder');
            $mailer->deliver();

            return ['success' => true, 'subject' => $subject];
        } catch (\Throwable $e) {
            Log::error('[PaymentReminderEmailService] send failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

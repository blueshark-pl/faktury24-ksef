<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * InvoicePayments Controller
 *
 * @property \App\Model\Table\InvoicePaymentsTable $InvoicePayments
 */
class InvoicePaymentsController extends AppController
{
    /**
     * Add payment method - AJAX
     */
    public function add()
    {
        $this->request->allowMethod(['post']);

        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $data = $this->request->getData();
        
        // Sprawdź czy faktura należy do firmy
        $invoice = $this->InvoicePayments->Invoices->find()
            ->where([
                'id' => $data['invoice_id'],
                'company_id' => $companyId
            ])
            ->first();

        if (!$invoice) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Faktura nie istnieje lub nie masz uprawnień'
                ]));
        }

        // Utwórz płatność
        $payment = $this->InvoicePayments->newEmptyEntity();
        $paymentData = [
            'invoice_id' => $data['invoice_id'],
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?: 'transfer',
            'description' => $data['description'] ?? null
        ];

        $payment = $this->InvoicePayments->patchEntity($payment, $paymentData);

        if ($this->InvoicePayments->save($payment)) {
            // Pobierz zaktualizowaną fakturę
            $updatedInvoice = $this->InvoicePayments->Invoices->get($data['invoice_id']);
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Płatność została dodana',
                    'payment' => [
                        'id' => $payment->id,
                        'payment_date' => $payment->payment_date->format('Y-m-d'),
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'description' => $payment->description
                    ],
                    'invoice' => [
                        'alreadypaid' => $updatedInvoice->alreadypaid,
                        'remaining' => $updatedInvoice->remaining,
                        'paymentstate' => $updatedInvoice->paymentstate
                    ]
                ]));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Błąd zapisu płatności',
                'errors' => $payment->getErrors()
            ]));
    }

    /**
     * List payments for invoice - AJAX
     */
    public function index()
    {
        $this->request->allowMethod(['get']);

        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');
        
        $invoiceId = $this->request->getQuery('invoice_id');

        if (!$invoiceId) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Brak ID faktury'
                ]));
        }

        // Sprawdź czy faktura należy do firmy
        $invoice = $this->InvoicePayments->Invoices->find()
            ->where([
                'id' => $invoiceId,
                'company_id' => $companyId
            ])
            ->first();

        if (!$invoice) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Faktura nie istnieje lub nie masz uprawnień'
                ]));
        }

        // Pobierz płatności
        $payments = $this->InvoicePayments->find()
            ->where(['invoice_id' => $invoiceId])
            ->order(['payment_date' => 'DESC', 'created' => 'DESC'])
            ->all()
            ->toArray();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'payments' => array_map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_date' => $payment->payment_date->format('Y-m-d'),
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'description' => $payment->description,
                        'created' => $payment->created->format('Y-m-d H:i')
                    ];
                }, $payments)
            ]));
    }

    /**
     * Delete payment method - AJAX
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $identity = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $payment = $this->InvoicePayments->get($id, [
            'contain' => ['Invoices']
        ]);

        if (!$payment || $payment->invoice->company_id !== $companyId) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Płatność nie istnieje lub nie masz uprawnień'
                ]));
        }

        if ($this->InvoicePayments->delete($payment)) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Płatność została usunięta'
                ]));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Błąd usuwania płatności'
            ]));
    }
}

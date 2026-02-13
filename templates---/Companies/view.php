<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Company $company
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Company'), ['action' => 'edit', $company->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Company'), ['action' => 'delete', $company->id], ['confirm' => __('Are you sure you want to delete # {0}?', $company->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Companies'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Company'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="companies view content">
            <h3><?= h($company->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($company->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($company->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Altname') ?></th>
                    <td><?= h($company->altname) ?></td>
                </tr>
                <tr>
                    <th><?= __('Nip') ?></th>
                    <td><?= h($company->nip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Regon') ?></th>
                    <td><?= h($company->regon) ?></td>
                </tr>
                <tr>
                    <th><?= __('Country') ?></th>
                    <td><?= h($company->country) ?></td>
                </tr>
                <tr>
                    <th><?= __('Postal Code') ?></th>
                    <td><?= h($company->postal_code) ?></td>
                </tr>
                <tr>
                    <th><?= __('City') ?></th>
                    <td><?= h($company->city) ?></td>
                </tr>
                <tr>
                    <th><?= __('Street') ?></th>
                    <td><?= h($company->street) ?></td>
                </tr>
                <tr>
                    <th><?= __('Local Number') ?></th>
                    <td><?= h($company->local_number) ?></td>
                </tr>
                <tr>
                    <th><?= __('Phone') ?></th>
                    <td><?= h($company->phone) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bank Name') ?></th>
                    <td><?= h($company->bank_name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bank Account') ?></th>
                    <td><?= h($company->bank_account) ?></td>
                </tr>
                <tr>
                    <th><?= __('Logo Url') ?></th>
                    <td><?= h($company->logo_url) ?></td>
                </tr>
                <tr>
                    <th><?= __('Issuer') ?></th>
                    <td><?= h($company->issuer) ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice Template') ?></th>
                    <td><?= h($company->invoice_template) ?></td>
                </tr>
                <tr>
                    <th><?= __('Register Date') ?></th>
                    <td><?= h($company->register_date) ?></td>
                </tr>
                <tr>
                    <th><?= __('Subscription End') ?></th>
                    <td><?= h($company->subscription_end) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($company->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($company->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Vat Payer') ?></th>
                    <td><?= $company->vat_payer ? __('Yes') : __('No'); ?></td>
                </tr>
                <tr>
                    <th><?= __('Is Active') ?></th>
                    <td><?= $company->is_active ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
            <div class="related">
                <h4><?= __('Related Company Bank Accounts') ?></h4>
                <?php if (!empty($company->company_bank_accounts)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Iban') ?></th>
                            <th><?= __('Bank Name') ?></th>
                            <th><?= __('Currency') ?></th>
                            <th><?= __('Is Default') ?></th>
                            <th><?= __('Label') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->company_bank_accounts as $companyBankAccount) : ?>
                        <tr>
                            <td><?= h($companyBankAccount->id) ?></td>
                            <td><?= h($companyBankAccount->company_id) ?></td>
                            <td><?= h($companyBankAccount->iban) ?></td>
                            <td><?= h($companyBankAccount->bank_name) ?></td>
                            <td><?= h($companyBankAccount->currency) ?></td>
                            <td><?= h($companyBankAccount->is_default) ?></td>
                            <td><?= h($companyBankAccount->label) ?></td>
                            <td><?= h($companyBankAccount->created) ?></td>
                            <td><?= h($companyBankAccount->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'CompanyBankAccounts', 'action' => 'view', $companyBankAccount->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'CompanyBankAccounts', 'action' => 'edit', $companyBankAccount->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'CompanyBankAccounts', 'action' => 'delete', $companyBankAccount->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $companyBankAccount->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Contractors') ?></h4>
                <?php if (!empty($company->contractors)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Altname') ?></th>
                            <th><?= __('Nip') ?></th>
                            <th><?= __('Regon') ?></th>
                            <th><?= __('Eu Vat') ?></th>
                            <th><?= __('Country') ?></th>
                            <th><?= __('Postal Code') ?></th>
                            <th><?= __('City') ?></th>
                            <th><?= __('Street') ?></th>
                            <th><?= __('Local Number') ?></th>
                            <th><?= __('Phone') ?></th>
                            <th><?= __('Email') ?></th>
                            <th><?= __('Notes') ?></th>
                            <th><?= __('Is Active') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->contractors as $contractor) : ?>
                        <tr>
                            <td><?= h($contractor->id) ?></td>
                            <td><?= h($contractor->company_id) ?></td>
                            <td><?= h($contractor->name) ?></td>
                            <td><?= h($contractor->altname) ?></td>
                            <td><?= h($contractor->nip) ?></td>
                            <td><?= h($contractor->regon) ?></td>
                            <td><?= h($contractor->eu_vat) ?></td>
                            <td><?= h($contractor->country) ?></td>
                            <td><?= h($contractor->postal_code) ?></td>
                            <td><?= h($contractor->city) ?></td>
                            <td><?= h($contractor->street) ?></td>
                            <td><?= h($contractor->local_number) ?></td>
                            <td><?= h($contractor->phone) ?></td>
                            <td><?= h($contractor->email) ?></td>
                            <td><?= h($contractor->notes) ?></td>
                            <td><?= h($contractor->is_active) ?></td>
                            <td><?= h($contractor->created) ?></td>
                            <td><?= h($contractor->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Contractors', 'action' => 'view', $contractor->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Contractors', 'action' => 'edit', $contractor->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Contractors', 'action' => 'delete', $contractor->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $contractor->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Invoice Series') ?></h4>
                <?php if (!empty($company->invoice_series)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Invoice Series Type Id') ?></th>
                            <th><?= __('Invoice Series Period Id') ?></th>
                            <th><?= __('Is Default') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Series Template') ?></th>
                            <th><?= __('Starting Number') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->invoice_series as $invoiceSeries) : ?>
                        <tr>
                            <td><?= h($invoiceSeries->id) ?></td>
                            <td><?= h($invoiceSeries->company_id) ?></td>
                            <td><?= h($invoiceSeries->invoice_series_type_id) ?></td>
                            <td><?= h($invoiceSeries->invoice_series_period_id) ?></td>
                            <td><?= h($invoiceSeries->is_default) ?></td>
                            <td><?= h($invoiceSeries->name) ?></td>
                            <td><?= h($invoiceSeries->series_template) ?></td>
                            <td><?= h($invoiceSeries->starting_number) ?></td>
                            <td><?= h($invoiceSeries->created) ?></td>
                            <td><?= h($invoiceSeries->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'InvoiceSeries', 'action' => 'view', $invoiceSeries->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'InvoiceSeries', 'action' => 'edit', $invoiceSeries->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'InvoiceSeries', 'action' => 'delete', $invoiceSeries->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $invoiceSeries->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Invoices') ?></h4>
                <?php if (!empty($company->invoices)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Hash') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Interest Status') ?></th>
                            <th><?= __('Warehouse Type') ?></th>
                            <th><?= __('Paymentmethod') ?></th>
                            <th><?= __('Paymentdate') ?></th>
                            <th><?= __('Paymentstate') ?></th>
                            <th><?= __('Disposaldate Format') ?></th>
                            <th><?= __('Disposaldate Empty') ?></th>
                            <th><?= __('Disposaldate') ?></th>
                            <th><?= __('Date') ?></th>
                            <th><?= __('Total') ?></th>
                            <th><?= __('Total Composed') ?></th>
                            <th><?= __('Alreadypaid') ?></th>
                            <th><?= __('Alreadypaid Initial') ?></th>
                            <th><?= __('Remaining') ?></th>
                            <th><?= __('Number') ?></th>
                            <th><?= __('Day') ?></th>
                            <th><?= __('Month') ?></th>
                            <th><?= __('Year') ?></th>
                            <th><?= __('Day Year') ?></th>
                            <th><?= __('Fullnumber') ?></th>
                            <th><?= __('Type') ?></th>
                            <th><?= __('Correction Type') ?></th>
                            <th><?= __('Parent Id') ?></th>
                            <th><?= __('Simplified Invoice') ?></th>
                            <th><?= __('Corrections') ?></th>
                            <th><?= __('Formal Data Corrections') ?></th>
                            <th><?= __('Formal Data Corrections Note') ?></th>
                            <th><?= __('Currency') ?></th>
                            <th><?= __('Currency Exchange') ?></th>
                            <th><?= __('Currency Label') ?></th>
                            <th><?= __('Currency Date') ?></th>
                            <th><?= __('Price Currency Exchange') ?></th>
                            <th><?= __('Type Of Sale') ?></th>
                            <th><?= __('Auto Send Postivo') ?></th>
                            <th><?= __('Auto Send') ?></th>
                            <th><?= __('Auto Sms') ?></th>
                            <th><?= __('Account Date') ?></th>
                            <th><?= __('Template') ?></th>
                            <th><?= __('Semitemplatenumber') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('Header') ?></th>
                            <th><?= __('Footer') ?></th>
                            <th><?= __('User Name') ?></th>
                            <th><?= __('Schema') ?></th>
                            <th><?= __('Schema Bill') ?></th>
                            <th><?= __('Schema Cancelled') ?></th>
                            <th><?= __('Netto') ?></th>
                            <th><?= __('Tax') ?></th>
                            <th><?= __('Is Print') ?></th>
                            <th><?= __('Is Sent') ?></th>
                            <th><?= __('Is Api') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->invoices as $invoice) : ?>
                        <tr>
                            <td><?= h($invoice->id) ?></td>
                            <td><?= h($invoice->hash) ?></td>
                            <td><?= h($invoice->company_id) ?></td>
                            <td><?= h($invoice->interest_status) ?></td>
                            <td><?= h($invoice->warehouse_type) ?></td>
                            <td><?= h($invoice->paymentmethod) ?></td>
                            <td><?= h($invoice->paymentdate) ?></td>
                            <td><?= h($invoice->paymentstate) ?></td>
                            <td><?= h($invoice->disposaldate_format) ?></td>
                            <td><?= h($invoice->disposaldate_empty) ?></td>
                            <td><?= h($invoice->disposaldate) ?></td>
                            <td><?= h($invoice->date) ?></td>
                            <td><?= h($invoice->total) ?></td>
                            <td><?= h($invoice->total_composed) ?></td>
                            <td><?= h($invoice->alreadypaid) ?></td>
                            <td><?= h($invoice->alreadypaid_initial) ?></td>
                            <td><?= h($invoice->remaining) ?></td>
                            <td><?= h($invoice->number) ?></td>
                            <td><?= h($invoice->day) ?></td>
                            <td><?= h($invoice->month) ?></td>
                            <td><?= h($invoice->year) ?></td>
                            <td><?= h($invoice->day_year) ?></td>
                            <td><?= h($invoice->fullnumber) ?></td>
                            <td><?= h($invoice->type) ?></td>
                            <td><?= h($invoice->correction_type) ?></td>
                            <td><?= h($invoice->parent_id) ?></td>
                            <td><?= h($invoice->simplified_invoice) ?></td>
                            <td><?= h($invoice->corrections) ?></td>
                            <td><?= h($invoice->formal_data_corrections) ?></td>
                            <td><?= h($invoice->formal_data_corrections_note) ?></td>
                            <td><?= h($invoice->currency) ?></td>
                            <td><?= h($invoice->currency_exchange) ?></td>
                            <td><?= h($invoice->currency_label) ?></td>
                            <td><?= h($invoice->currency_date) ?></td>
                            <td><?= h($invoice->price_currency_exchange) ?></td>
                            <td><?= h($invoice->type_of_sale) ?></td>
                            <td><?= h($invoice->auto_send_postivo) ?></td>
                            <td><?= h($invoice->auto_send) ?></td>
                            <td><?= h($invoice->auto_sms) ?></td>
                            <td><?= h($invoice->account_date) ?></td>
                            <td><?= h($invoice->template) ?></td>
                            <td><?= h($invoice->semitemplatenumber) ?></td>
                            <td><?= h($invoice->description) ?></td>
                            <td><?= h($invoice->header) ?></td>
                            <td><?= h($invoice->footer) ?></td>
                            <td><?= h($invoice->user_name) ?></td>
                            <td><?= h($invoice->schema) ?></td>
                            <td><?= h($invoice->schema_bill) ?></td>
                            <td><?= h($invoice->schema_cancelled) ?></td>
                            <td><?= h($invoice->netto) ?></td>
                            <td><?= h($invoice->tax) ?></td>
                            <td><?= h($invoice->is_print) ?></td>
                            <td><?= h($invoice->is_sent) ?></td>
                            <td><?= h($invoice->is_api) ?></td>
                            <td><?= h($invoice->created) ?></td>
                            <td><?= h($invoice->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Invoices', 'action' => 'view', $invoice->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Invoices', 'action' => 'edit', $invoice->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Invoices', 'action' => 'delete', $invoice->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $invoice->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Services') ?></h4>
                <?php if (!empty($company->services)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Unit Id') ?></th>
                            <th><?= __('Vat Id') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Net Price') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th><?= __('Gs Uuid') ?></th>
                            <th><?= __('Gs Bu Uuid') ?></th>
                            <th><?= __('Gs Name') ?></th>
                            <th><?= __('Gs Unit') ?></th>
                            <th><?= __('Gs Net Price') ?></th>
                            <th><?= __('Gs Vat Uuid') ?></th>
                            <th><?= __('Gs Rvr Pkwiu') ?></th>
                            <th><?= __('Gs Rvr Add Info') ?></th>
                            <th><?= __('Gs Addedby') ?></th>
                            <th><?= __('Gs Addedip') ?></th>
                            <th><?= __('Gs Addedon') ?></th>
                            <th><?= __('Gs Modifiedby') ?></th>
                            <th><?= __('Gs Modifiedip') ?></th>
                            <th><?= __('Gs Modifiedon') ?></th>
                            <th><?= __('Gs Deletedby') ?></th>
                            <th><?= __('Gs Deletedip') ?></th>
                            <th><?= __('Gs Deletedon') ?></th>
                            <th><?= __('Gs Service Id') ?></th>
                            <th><?= __('Gs Service Rabat') ?></th>
                            <th><?= __('Gs Service Uwagi') ?></th>
                            <th><?= __('Gs Service Special Type') ?></th>
                            <th><?= __('Gs Service Service Type') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->services as $service) : ?>
                        <tr>
                            <td><?= h($service->id) ?></td>
                            <td><?= h($service->company_id) ?></td>
                            <td><?= h($service->unit_id) ?></td>
                            <td><?= h($service->vat_id) ?></td>
                            <td><?= h($service->name) ?></td>
                            <td><?= h($service->net_price) ?></td>
                            <td><?= h($service->description) ?></td>
                            <td><?= h($service->created) ?></td>
                            <td><?= h($service->modified) ?></td>
                            <td><?= h($service->gs_uuid) ?></td>
                            <td><?= h($service->gs_bu_uuid) ?></td>
                            <td><?= h($service->gs_name) ?></td>
                            <td><?= h($service->gs_unit) ?></td>
                            <td><?= h($service->gs_net_price) ?></td>
                            <td><?= h($service->gs_vat_uuid) ?></td>
                            <td><?= h($service->gs_rvr_pkwiu) ?></td>
                            <td><?= h($service->gs_rvr_add_info) ?></td>
                            <td><?= h($service->gs_addedby) ?></td>
                            <td><?= h($service->gs_addedip) ?></td>
                            <td><?= h($service->gs_addedon) ?></td>
                            <td><?= h($service->gs_modifiedby) ?></td>
                            <td><?= h($service->gs_modifiedip) ?></td>
                            <td><?= h($service->gs_modifiedon) ?></td>
                            <td><?= h($service->gs_deletedby) ?></td>
                            <td><?= h($service->gs_deletedip) ?></td>
                            <td><?= h($service->gs_deletedon) ?></td>
                            <td><?= h($service->gs_service_id) ?></td>
                            <td><?= h($service->gs_service_rabat) ?></td>
                            <td><?= h($service->gs_service_uwagi) ?></td>
                            <td><?= h($service->gs_service_special_type) ?></td>
                            <td><?= h($service->gs_service_service_type) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Services', 'action' => 'view', $service->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Services', 'action' => 'edit', $service->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Services', 'action' => 'delete', $service->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $service->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Users') ?></h4>
                <?php if (!empty($company->users)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Username') ?></th>
                            <th><?= __('Email') ?></th>
                            <th><?= __('Password') ?></th>
                            <th><?= __('First Name') ?></th>
                            <th><?= __('Last Name') ?></th>
                            <th><?= __('Token') ?></th>
                            <th><?= __('Token Expires') ?></th>
                            <th><?= __('Api Token') ?></th>
                            <th><?= __('Activation Date') ?></th>
                            <th><?= __('Secret') ?></th>
                            <th><?= __('Secret Verified') ?></th>
                            <th><?= __('Tos Date') ?></th>
                            <th><?= __('Active') ?></th>
                            <th><?= __('Is Superuser') ?></th>
                            <th><?= __('Role') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th><?= __('Additional Data') ?></th>
                            <th><?= __('Last Login') ?></th>
                            <th><?= __('Lockout Time') ?></th>
                            <th><?= __('Login Token') ?></th>
                            <th><?= __('Login Token Date') ?></th>
                            <th><?= __('Token Send Requested') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($company->users as $user) : ?>
                        <tr>
                            <td><?= h($user->id) ?></td>
                            <td><?= h($user->username) ?></td>
                            <td><?= h($user->email) ?></td>
                            <td><?= h($user->password) ?></td>
                            <td><?= h($user->first_name) ?></td>
                            <td><?= h($user->last_name) ?></td>
                            <td><?= h($user->token) ?></td>
                            <td><?= h($user->token_expires) ?></td>
                            <td><?= h($user->api_token) ?></td>
                            <td><?= h($user->activation_date) ?></td>
                            <td><?= h($user->secret) ?></td>
                            <td><?= h($user->secret_verified) ?></td>
                            <td><?= h($user->tos_date) ?></td>
                            <td><?= h($user->active) ?></td>
                            <td><?= h($user->is_superuser) ?></td>
                            <td><?= h($user->role) ?></td>
                            <td><?= h($user->created) ?></td>
                            <td><?= h($user->modified) ?></td>
                            <td><?= h($user->additional_data) ?></td>
                            <td><?= h($user->last_login) ?></td>
                            <td><?= h($user->lockout_time) ?></td>
                            <td><?= h($user->login_token) ?></td>
                            <td><?= h($user->login_token_date) ?></td>
                            <td><?= h($user->token_send_requested) ?></td>
                            <td><?= h($user->company_id) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Users', 'action' => 'view', $user->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Users', 'action' => 'edit', $user->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Users', 'action' => 'delete', $user->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $user->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
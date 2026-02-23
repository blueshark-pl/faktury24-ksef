<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use App\Model\Table\UsersTable as AppUsersTable;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\Routing\Router;
/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    private function traceRegisterCompany(string $message, array $context = []): void
    {
        try {
            $line = sprintf(
                "%s %s %s%s",
                date('Y-m-d H:i:s'),
                $message,
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
                PHP_EOL
            );
            file_put_contents(LOGS . 'register-company.log', $line, FILE_APPEND);
        } catch (\Throwable) {
            // diagnostic only
        }
    }

    public $currentCompanyId = null;

    private function setKsefModeViewVars(?string $companyId): void
    {
        $enabled = true;
        if (!empty($companyId)) {
            try {
                $Companies = $this->fetchTable('Companies');
                $company = $Companies->find()
                    ->select(['id', 'ksef_mode_enabled'])
                    ->where(['id' => $companyId])
                    ->first();
                if ($company !== null) {
                    $enabled = (bool)($company->ksef_mode_enabled ?? true);
                }
            } catch (\Throwable) {
                // migration not applied or temporary DB issue -> default safe behavior
                $enabled = true;
            }
        }

        $this->set('ksefModeEnabled', $enabled);
    }

    private function setDraftsViewVars(?string $companyId): void
    {
        $draftCount = 0;
        if (!empty($companyId)) {
            try {
                $Invoices = $this->fetchTable('Invoices');
                $draftCount = (int)$Invoices->find()
                    ->where([
                        'company_id' => $companyId,
                        'workflow_status' => 'draft',
                    ])
                    ->count();
            } catch (\Throwable) {
                $draftCount = 0;
            }
        }

        $this->set('draftInvoicesCount', $draftCount);
    }
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        // $this->loadComponent('Authentication.Authentication');
        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $this->set('ksefModeEnabled', true);
            $this->set('draftInvoicesCount', 0);
            return; // nie zalogowany → nic nie rób
        }

        // Jeśli identity nie ma company_id, ale w DB user ma już przypisaną firmę,
        // ustaw kontekst na podstawie DB (zapobiega pętli redirectów po onboardingu).
        if (empty($identity->get('company_id'))) {
            $this->traceRegisterCompany('App.beforeFilter.identityWithoutCompany', [
                'identity_id' => (string)$identity->getIdentifier(),
            ]);
            try {
                /** @var \Cake\ORM\Table|\App\Model\Table\UsersTable $Users */
                $Users = $this->getTableLocator()->get('Users', ['className' => AppUsersTable::class]);
                $this->traceRegisterCompany('App.beforeFilter.usersTableClass', [
                    'class' => get_class($Users),
                    'has_ensure_method' => method_exists($Users, 'ensureCompanyForUserId') ? 1 : 0,
                ]);
                $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id', 'additional_data']]);
                $this->traceRegisterCompany('App.beforeFilter.dbUserLoaded', [
                    'user_id' => (string)$dbUser->id,
                    'has_company_id' => !empty($dbUser->company_id) ? 1 : 0,
                ]);
                if (empty($dbUser->company_id) && method_exists($Users, 'ensureCompanyForUserId')) {
                    try {
                        /** @var string|null $ensuredCompanyId */
                        $ensuredCompanyId = $Users->ensureCompanyForUserId((string)$dbUser->id);
                        $this->traceRegisterCompany('App.beforeFilter.ensureCompanyResult', [
                            'user_id' => (string)$dbUser->id,
                            'ensured_company_id' => (string)($ensuredCompanyId ?? ''),
                        ]);
                        if (!empty($ensuredCompanyId)) {
                            $dbUser = $Users->get($identity->getIdentifier(), ['fields' => ['id', 'company_id']]);
                            $this->traceRegisterCompany('App.beforeFilter.dbUserReloadedAfterEnsure', [
                                'user_id' => (string)$dbUser->id,
                                'company_id' => (string)($dbUser->company_id ?? ''),
                            ]);
                        }
                    } catch (\Throwable) {
                        $this->traceRegisterCompany('App.beforeFilter.ensureCompanyException', [
                            'user_id' => (string)$dbUser->id,
                        ]);
                        // best-effort
                    }
                }
                if (!empty($dbUser->company_id)) {
                    $this->currentCompanyId = $dbUser->company_id;
                    $this->setKsefModeViewVars((string)$dbUser->company_id);
                    $this->setDraftsViewVars((string)$dbUser->company_id);
                    try {
                        /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                        $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                        $copied = $InvoiceSeries->copySystemSeriesForCompany((string)$dbUser->company_id);
                        if ($copied > 0) {
                            Log::info('Skopiowano '.$copied.' systemowych serii dla firmy '.$dbUser->company_id.' (stale identity path)', ['series_init']);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Nie udało się skopiować systemowych serii (stale identity path): '.$e->getMessage(), ['series_init']);
                    }
                    try {
                        if (!$this->components()->has('Authentication')) {
                            $this->loadComponent('Authentication.Authentication');
                        }
                        $this->Authentication->setIdentity($dbUser);
                    } catch (\Throwable) {
                        // best-effort
                    }
                    return;
                }
            } catch (\Throwable) {
                // best-effort
            }
        }
        // debug($identity->get('company_id'));
        // jeśli user ma już firmę → zapewnij skopiowanie systemowych serii (idempotentnie)
        if (!empty($identity->get('company_id'))) {
            try {
                $this->currentCompanyId = $identity->get('company_id');
                $this->setKsefModeViewVars((string)$this->currentCompanyId);
                $this->setDraftsViewVars((string)$this->currentCompanyId);
                /** @var \App\Model\Table\InvoiceSeriesTable $InvoiceSeries */
                $InvoiceSeries = $this->fetchTable('InvoiceSeries');
                $copied = $InvoiceSeries->copySystemSeriesForCompany($identity->get('company_id'));
                if ($copied > 0) {
                    Log::info('Skopiowano '.$copied.' systemowych serii dla firmy '.$identity->get('company_id'), ['series_init']);
                }
            } catch (\Throwable $e) {
                Log::warning('Nie udało się skopiować systemowych serii: '.$e->getMessage(), ['series_init']);
            }
            return; // nic więcej – logika poniżej dotyczy braku firmy
        }

        $this->set('ksefModeEnabled', true);
        $this->set('draftInvoicesCount', 0);

        // whitelist akcji (żeby nie robić pętli)
        $allowed = [
            'Companies' => ['onboarding', 'saveOnboarding'],
            'Users'     => ['login', 'logout', 'register'],
            'TwoFactor' => ['index', 'enable', 'verify', 'disable'],
            'Invoices'  => ['runPlannedDrafts'],
        ];

        $controller = $this->request->getParam('controller');
        $action     = $this->request->getParam('action');

        if (isset($allowed[$controller]) && in_array($action, $allowed[$controller], true)) {
            return;
        }

        // jeśli to AJAX/JSON – zwróć błąd
        if ($this->request->is('ajax') || $this->request->is('json')) {
            $this->set([
                'error' => 'onboarding_required',
                '_serialize' => ['error']
            ]);
            $this->response = $this->response->withStatus(428);
            return;
        }

        // domyślnie: redirect do kreatora
        return $this->redirect(Router::url(['controller' => 'Companies', 'action' => 'onboarding']));
    }
}

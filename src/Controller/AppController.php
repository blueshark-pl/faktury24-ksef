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

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Routing\Router;
use Cake\Log\Log;
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
    public $currentCompanyId = null;
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
            return; // nie zalogowany → nic nie rób
        }
        // debug($identity->get('company_id'));
        // jeśli user ma już firmę → zapewnij skopiowanie systemowych serii (idempotentnie)
        if (!empty($identity->get('company_id'))) {
            try {
                $this->currentCompanyId = $identity->get('company_id');
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

        // whitelist akcji (żeby nie robić pętli)
        $allowed = [
            'Companies' => ['onboarding', 'saveOnboarding'],
            'Users'     => ['login', 'logout', 'register'],
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

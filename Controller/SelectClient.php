<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Controller\ListCliente as ParentListCliente;


class SelectClient extends ParentListCliente
{
    private $postAction = '';

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
    }

    private function init()
    {

        $this->customSettingsView();
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Selecciona cliente';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    private function customSettingsView()
    {
        //
        $this->addButton('ListCliente', [
            'action' => $this->postAction,
            'label' => 'Seleccionar',
            'color' => 'info'
        ]);
        $this->setSettings('ListCliente', 'btnNew', false);
        $this->setSettings('ListCliente', 'btnDelete', false);
        $this->setSettings('ListCliente', 'btnPrint', false);
        $this->setSettings('ListCliente', 'btnSave', false);
        $this->setSettings('ListCliente', 'megasearch', false);
        $this->setSettings('ListCliente', 'clickable', false);
    }

    protected function execPreviousAction($action)
    {

        if($action == "" && $this->request->query->get('action')){
            $action = $this->request->query->get('action');
        }

        switch ($action) {
            case 'invoicing':
                $this->postAction = 'selectClient';
                break;
            case'changing':
                $this->postAction = 'changeClient';
                break;
        }
        parent::execPreviousAction($action);
        $this->init();
    }

    protected function execAfterAction($action)
    {
        switch ($action) {
            case 'selectClient':
                $this->selectClient();
                break;
            case 'changeClient':
                $this->changeClient();
                break;
            default:
                break;
        }
        parent::execAfterAction($action);

    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
    }

    private function selectClient()
    {
        $customer_id = $this->request->request->get('code')[0];

        if ($customer_id !== null && strlen($customer_id) > 0) {
            $this->redirect('CreateInvoiceStripe?action=clientOk&codcliente=' . $customer_id);
        } else {
            $this->toolbox()->log()->error('No se ha podido vincular el cliente de facturascript. Alguno de los valores no es correcto');
        }
    }

    private function changeClient()
    {
        $customer_id = $this->request->request->get('code')[0];
        $stripe_customer_id = $this->request->query->get('stripe_customer_id');

        switch ($this->request->query->get('source')) {
            case 'ListClient':
                $this->redirect('ListClient?action=linkClient&customer_id='.$customer_id.'&stripe_customer_id='.$stripe_customer_id);
                break;
            case 'ListInvoiceStripe':
                $this->redirect('ListInvoiceStripe?action=linkClient&customer_id='.$customer_id.'&stripe_customer_id='.$stripe_customer_id);
                break;

            case 'CreateInvoiceStripe':
                $this->redirect('CreateInvoiceStripe?action=linkClient&customer_id='.$customer_id.'&stripe_customer_id='.$stripe_customer_id);
                break;
        }
    }

}

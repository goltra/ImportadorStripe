<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Controller\ListCliente as ParentListCliente;
use FacturaScripts\Core\Tools;


class SelectClient extends ParentListCliente
{
    private string $postAction = '';

    /**
     * @param $response
     * @param $user
     * @param $permissions
     * @return void
     */
    public function privateCore(&$response, $user, $permissions): void
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

    /**
     * @throws Exception
     */
    private function customSettingsView(): void
    {
        //
        $this->tab('ListCliente')->addButton([
            'action' => $this->postAction,
            'icon' => 'fas fa-check',
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

    protected function execPreviousAction($action): bool
    {
        if ($action === '' && $this->request->query->get('action')) {
            $action = $this->request->query->get('action');
        }

        switch ($action) {
            case 'invoicing':
                $this->postAction = 'selectClient';
                break;
            case 'changing':
                $this->postAction = 'changeClient';
                break;
        }

        $result = parent::execPreviousAction($action);
        $this->init();

        return $result;
    }

    protected function execAfterAction($action): void
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

    protected function loadData($viewName, $view): void
    {
        parent::loadData($viewName, $view);
    }

    private function selectClient(): void
    {
        $customerId = $this->getSelectedCode();

        if ($customerId !== null) {
            $this->redirect('CreateInvoiceStripe?action=clientOk&codcliente=' . $customerId);
        } else {
            Tools::log()->error('No se ha podido vincular el cliente de facturascript. Alguno de los valores no es correcto');
        }
    }

    private function changeClient(): void
    {
        $customerId = $this->getSelectedCode();
        $stripeCustomerId = $this->request->query->get('stripe_customer_id');

        switch ($this->request->query->get('source')) {
            case 'ListClient':
                $this->redirect('ListClient?action=linkClient&customer_id=' . $customerId . '&stripe_customer_id=' . $stripeCustomerId);
                break;
            case 'ListInvoiceStripe':
                $this->redirect('ListInvoiceStripe?action=linkClient&customer_id=' . $customerId . '&stripe_customer_id=' . $stripeCustomerId);
                break;
            case 'CreateInvoiceStripe':
                $this->redirect('CreateInvoiceStripe?action=linkClient&customer_id=' . $customerId . '&stripe_customer_id=' . $stripeCustomerId);
                break;
        }
    }

    private function getSelectedCode(): ?string
    {
        $codes = $this->request->request->get('codes');

        if (!is_string($codes)) {
            return null;
        }

        $decoded = unserialize($codes, ['allowed_classes' => false]);

        return is_array($decoded) && !empty($decoded[0]) ? (string)$decoded[0] : null;
    }
}

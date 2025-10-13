<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Controller\ListProducto as ParentListProducto;
use FacturaScripts\Core\Tools;


class SelectProduct extends ParentListProducto
{

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

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Selecciona un artículo';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    private function init()
    {

        $this->customSettingsView();
        session_start();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function customSettingsView(): void
    {
        //
        $this->addButton('ListProducto', [
            'action' => 'selectProduct',
            'label' => 'Seleccionar',
            'color' => 'info'
        ]);
        $this->setSettings('ListProducto', 'btnNew', false);
        $this->setSettings('ListProducto', 'btnDelete', false);
        $this->setSettings('ListProducto', 'btnPrint', false);
        $this->setSettings('ListProducto', 'btnSave', false);
        $this->setSettings('ListProducto', 'megasearch', false);
        $this->setSettings('ListProducto', 'clickable', false);
    }

    protected function execPreviousAction($action): void
    {
        parent::execPreviousAction($action);
        $this->init();
    }

    protected function execAfterAction($action): void
    {
        switch ($action) {
            case 'selectProduct':
                $st_product_id = $this->request->query->get('st_product_id');
                if ($st_product_id === null || strlen($st_product_id) == 0) {
                    Tools::log()->error('No se ha recibo el código del producto de stripe');
                    break;
                }
                $_SESSION['st_product_id'] = $st_product_id;
                $this->selectProduct();
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

    private function selectProduct(): void
    {
        $id = $this->request->request->get('code')[0];

        if ($id !== null && strlen($id) > 0) {
            $this->redirect('ListProduct?action=linkProduct&codproduct=' . $id);
        } else {
            Tools::log()->error('No se ha podido vincular el producto de facturascript. Alguno de los valores no es correcto');
        }
    }

}

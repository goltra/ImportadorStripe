<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Controller\ListProducto as ParentListProducto;


class SelectProduct extends ParentListProducto
{

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
    }

    public function getPageData():array
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

    private function customSettingsView()
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

    protected function execPreviousAction($action)
    {
        parent::execPreviousAction($action);
        $this->init();
    }

    protected function execAfterAction($action)
    {
        switch ($action) {
            case 'selectProduct':
                $st_product_id = $this->request->query->get('st_product_id');
                if ($st_product_id === null || strlen($st_product_id) == 0) {
                    $this->toolbox()->log()->error('No se ha recibo el código del producto de stripe');
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

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);
    }

    private function selectProduct()
    {
        $id = $this->request->request->get('code')[0];
        if ($id !== null && count($id) > 0) {
            $this->redirect('ListProduct?action=linkProduct&codproduct=' . $id);
        } else {
            $this->toolbox()->log()->error('No se ha podido vincular el producto de facturascript. Alguno de los valores no es correcto');
        }
    }

}

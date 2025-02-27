<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;


class TestTransaction extends Controller
{

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Webhook de stripe';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->init();
    }

    public function init(){
        
//        Documentación de lo que hay que hacer aquí: https://github.com/goltra/ImportadorStripe/issues/3

        $payoutId = 'po_1QhK6gHDuQaJAlOmouHWIs8M';

        //

    }
}

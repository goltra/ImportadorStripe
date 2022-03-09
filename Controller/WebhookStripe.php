<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Lib\Email\NewMail;
use FacturaScripts\Core\Lib\Export\PDFExport;
use FacturaScripts\Core\Lib\ExtendedController\BaseController;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Model\Cliente;
use FacturaScripts\Core\Model\EmailSent;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Model\FormaPago;
use FacturaScripts\Plugins\ImportadorStripe\Model\InvoiceStripe;
use FacturaScripts\Core\Lib\ExportManager;


class WebhookStripe extends Controller
{

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Crear nueva factura desde stripe';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function publicCore(&$response)
    {
        $this->init();
    }

    private function init(){
        var_dump('hey');
    }
}
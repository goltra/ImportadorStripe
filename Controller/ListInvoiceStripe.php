<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\ReciboCliente;
use FacturaScripts\Plugins\ImportadorStripe\Model\Helper;
use \FacturaScripts\Plugins\ImportadorStripe\Model\InvoiceStripe;
use FacturaScripts\Core\Lib\AssetManager;

class ListInvoiceStripe extends Controller
{

    public $invoices = [];
    public $sks_stripe = [];
    public $action = '';
    public $sk_stripe_index = null;
    public $textFilter = '';
    public $f_ini='';
    public $f_fin='';

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Facturas';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->init();
    }


    private function init()
    {
        AssetManager::add('css', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/CSS/stripe.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/Helper.js');
        $this->action = $this->request->query->get('action');
        $this->sks_stripe = InvoiceStripe::loadSkStripe();
        /*$this->test();*/
        switch ($this->action) {
            case('load'):
                if ($this->request->request->get('sk_stripe_index') !== null) {
                    $this->sk_stripe_index = $this->request->request->get('sk_stripe_index');
                } elseif ($this->request->query->get('sk_stripe_index') !== null) {
                    $this->sk_stripe_index = $this->request->query->get('sk_stripe_index');
                } else {
                    $this->toolBox()->log()->error('No se ha recibido el sk correspondiente');
                    return false;
                }

                $start = $this->request->query->get('start');
                $limit = $this->request->query->get('limit');

                if ($limit === null || count($limit) == 0)
                    $limit = 5;
                if ($start === null || count($start) == 0)
                    $start = null;

                $f_ini=null;
                $f_fin=null;
                // OBTENGO FECHAS SIN VIENEN EN EL POST Y LAS CONVIERTO A TIMESTAMP
                if ($this->request->request->get('f-ini-date')) {
                    $this->f_ini = $this->request->request->get('f-ini-date');
                    $f_ini = Helper::parseDateToTS($this->f_ini,'Y-m-d');
                }
                if ($this->request->request->get('f-fin-date')) {
                    $this->f_fin=$this->request->request->get('f-fin-date');
                    $f_fin = Helper::parseDateToTS($this->f_fin,'Y-m-d');
                }

                $this->getData($this->sk_stripe_index, $start, $limit, $f_ini, $f_fin);
                $this->textFilter='Filtrando de ' . $this->f_ini . ' a ' . $this->f_fin;
                break;
            default:
                //si no pasa accion, debe mostrar solo el desplegable para elegir que cuenta de stripe usar.

                break;
        }

        if ($this->request->query->get('action') && $this->request->query->get('action') == 'test') {

        } else {

        }
    }

    /**
     * Carga las facturas de stripe que no tienen en los metadatos la variable fs_idFatura con los filtros de fecha.
     *
     * @param $sk_stripe_index
     * @param null $start
     * @param int $limit
     * @param null $f_ini
     * @param null $f_fin
     */
    public function getData($sk_stripe_index, $start = null, $limit = 5, $f_ini = null, $f_fin = null)
    {
        try {
            $data = InvoiceStripe::loadInvoicesNotProcessed($sk_stripe_index, $start, $limit, $f_ini, $f_fin);
            if ($data['status'] === false) {
                $this->toolbox()->log()->error('No se han podido cargar las facturas ' . $data['message']);
            } else {
                $this->invoices = $data;
            }
        } catch (Exception $e) {
            $this->toolbox()->log()->error('No se han podido cargar las facturas ' . $e->getMessage());
        }

    }

    public function test(){
        $recibo = new ReciboCliente();
        $where = [new DataBaseWhere('idfactura',10388)];

        $recibos  = $recibo->all($where);
        var_dump($recibos);
        /*$recibo->pagado=true;
        if($recibo->save())
            var_dump($recibo);
        else
            echo'error';*/
    }
}

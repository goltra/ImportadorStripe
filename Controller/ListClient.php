<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Model\ClientModel;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;

class ListClient extends Controller
{

    public $clients = [];
    public $sks_stripe = [];
    public $action = '';
    public $sk_stripe_index = null;
    public $paymentMethods = [];

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Clientes';
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
        session_start();

        AssetManager::add('css', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/CSS/stripe.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/Helper.js');
        $this->action = $this->request->query->get('action');
        $this->sks_stripe = ClientModel::loadSkStripe();
        switch ($this->action) {
            case('linkPaymentMethod'):
                if (
                    ($pm = $this->request->query->get('pm')) !== null &&
                    ($stripe_customer_id = $this->request->query->get('stripe_customer_id')) !== null
                ) {
                        $res = ClientModel::addPaymentMethodInMetaData($stripe_customer_id, $_SESSION['sk_stripe_index'], $pm);

//                        if ($res['status'] === true) {
//                            $this->toolBox()->log()->info('Cliente vinculado correctamente.');
//                        } else {
//                            $this->toolBox()->log()->error($res['message']);
//                        }

                }
                break;
            case('load'):

                if ($this->request->request->get('sk_stripe_index') !== null) {
                    /*echo'post';*/
                    $this->sk_stripe_index = $this->request->request->get('sk_stripe_index');
                } elseif ($this->request->query->get('sk_stripe_index') !== null) {
                    /*echo 'get';*/
                    $this->sk_stripe_index = $this->request->query->get('sk_stripe_index');
                } elseif (isset($_SESSION['sk_stripe_index'])) {
                    /*echo 'session';*/
                    $this->sk_stripe_index = $_SESSION['sk_stripe_index'];
                } else {
                    $this->toolBox()->log()->error('No se ha recibido el sk correspondiente');
                    return false;
                }

                $start = $this->request->query->get('start');
                $limit = $this->request->query->get('limit');
                $pm = new FormaPago();
                $this->paymentMethods = $pm->all();

                $_SESSION['sk_stripe_index'] = $this->sk_stripe_index;

                if ($limit === null || count($limit) == 0)
                    $limit = 1000;
                if ($start === null || count($start) == 0)
                    $start = null;

                $this->getData($this->sk_stripe_index, $start, $limit);
                break;

            case 'linkClient':
                $customer_id = $this->request->query->get('customer_id');
                $stripe_customer_id = $this->request->query->get('stripe_customer_id');

                if (strlen($customer_id) > 0){
                    $res = ClientModel::linkFsClientToStripeCustomer($stripe_customer_id, $_SESSION['sk_stripe_index'], $customer_id);

                    if ($res['status'] === true) {
                        $this->toolBox()->log()->info('Cliente vinculado correctamente.');
                    } else {
                        $this->toolBox()->log()->error($res['message']);
                    }
                }
                else
                    $this->toolBox()->log()->error('Error al seleccionar el cliente');

                break;

            default:
                //si no pasa accion, debe mostrar solo el desplegable para elegir que cuenta de stripe usar.

                break;
        }
    }

    public function getData($sk_stripe_index, $start = null, $limit = 10)
    {
        try{
            $data = ClientModel::loadStripeCustomers($sk_stripe_index, $start, $limit);

            if (array_key_exists('status', $data) && $data['status'] === false) {
                $this->toolbox()->log()->error( 'Error: ' . $data['message']);
            } else {
                $this->clients = $data;
            }
        } catch (\Exception $ex){
            $this->toolbox()->log()->error( 'Error: ' . $ex->getMessage());
        }


    }


}

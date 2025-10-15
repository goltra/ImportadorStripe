<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ImportadorStripe\Model\InvoiceStripe;
use FacturaScripts\Plugins\ImportadorStripe\Model\ProductModel;
use FacturaScripts\Core\Lib\AssetManager;

class ListProduct extends Controller
{

    public array $products = [];
    public array $sks_stripe = [];
    public string $action = '';
    public int|null $sk_stripe_index = null;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Productos';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fa-solid fa-cubes';
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    /**
     * @throws KernelException
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);
        $this->init();
    }

    private function init(): void
    {
        session_start();

        AssetManager::add('css', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/CSS/stripe.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/Helper.js');
        $this->action = $this->request->query->get('action');
        $this->sks_stripe = ProductModel::loadSkStripe();
        switch ($this->action) {
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
                    Tools::log()->error('No se ha recibido el sk correspondiente');
                    return;
                }

                $start = $this->request->query->get('start');
                $limit = $this->request->query->get('limit');

                $_SESSION['sk_stripe_index'] = $this->sk_stripe_index;

                if ($limit === null || count($limit) == 0)
                    $limit = 1000;
                if ($start === null || count($start) == 0)
                    $start = null;

                $this->getData($this->sk_stripe_index, $start, $limit);
                break;
            case('linkProduct'):
                $codproduct = $this->request->query->get('codproduct');
                if ($codproduct === null || strlen($codproduct) == 0) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido el producto de FS');
                    break;
                }
                if (!isset($_SESSION['sk_stripe_index'])) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido la cuenta de stripe');
                    break;
                }
                if (!isset($_SESSION['st_product_id'])) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido el producto de stripe');
                    break;
                }

                $this->sk_stripe_index = $_SESSION['sk_stripe_index'];
                $st_product_id = $_SESSION['st_product_id'];

                try {
                    ProductModel::linkFsProductToStripeProduct($this->sk_stripe_index, $codproduct, $st_product_id);
                    $this->redirect('ListProduct?action=load',0);
                } catch (\Exception $e) {
                    Tools::log()->error('No se ha podido enlazar el producto' . $e->getMessage());
                }
                break;
            default:
                //si no pasa accion, debe mostrar solo el desplegable para elegir que cuenta de stripe usar.

                break;
        }

        /*if ($this->request->query->get('action') && $this->request->query->get('action') == 'test') {

        } else {

        }*/
    }

    public function getData($sk_stripe_index, $start = null, $limit = 10): void
    {
        try{
            $data = ProductModel::loadStripeProducts($sk_stripe_index, $start, $limit);

            if (array_key_exists('status', $data) && $data['status'] === false) {
                Tools::log()->error( 'Error: ' . $data['message']);
            } else {
                $this->products = $data;
            }
        } catch (\Exception $ex){
            Tools::log()->error( 'Error: ' . $ex->getMessage());
        }


    }


}

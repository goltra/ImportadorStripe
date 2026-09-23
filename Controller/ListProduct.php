<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeProduct;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSession;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;

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
        StripeSession::start();

        AssetManager::add('css', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/CSS/style.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/Helper.js');
        $this->action = $this->request->query->get('action') ?? '';
        $this->sks_stripe = StripeSettings::getSks();

        switch ($this->action) {
            case 'load':
                $this->sk_stripe_index = $this->request->request->get('sk_stripe_index')
                    ?? $this->request->query->get('sk_stripe_index')
                    ?? StripeSession::get('sk_stripe_index');

                if ($this->sk_stripe_index === null) {
                    Tools::log()->error('No se ha recibido el sk correspondiente');
                    return;
                }

                StripeSession::set('sk_stripe_index', $this->sk_stripe_index);
                $this->getData($this->sk_stripe_index);
                break;

            case 'linkProduct':
                $codproduct = $this->request->query->get('codproduct');

                if (empty($codproduct)) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido el producto de FS');
                    break;
                }
                if (!StripeSession::has('sk_stripe_index')) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido la cuenta de stripe');
                    break;
                }
                if (!StripeSession::has('st_product_id')) {
                    Tools::log()->error('No se ha podido enlazar el producto, no se ha definido el producto de stripe');
                    break;
                }

                $this->sk_stripe_index = StripeSession::get('sk_stripe_index');

                try {
                    StripeProduct::linkToFsProduct((int)$this->sk_stripe_index, $codproduct, StripeSession::get('st_product_id'));
                    $this->redirect('ListProduct?action=load', 0);
                } catch (Exception $e) {
                    Tools::log()->error('No se ha podido enlazar el producto' . $e->getMessage());
                }
                break;

            default:
                // Si no hay acción, solo se muestra el desplegable para elegir la cuenta de stripe.
                break;
        }
    }

    public function getData($sk_stripe_index): void
    {
        try {
            $this->products = StripeProduct::loadAll((int)$sk_stripe_index);
        } catch (Exception $e) {
            Tools::log()->error('Error: ' . $e->getMessage());
        }
    }
}

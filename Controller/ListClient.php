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
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeCustomer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSession;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;

class ListClient extends Controller
{
    public array $clients = [];
    public array $sks_stripe = [];
    public string|null $action = '';
    public int|null $sk_stripe_index = null;
    public string $stripe_customer_email = '';
    public array $paymentMethods = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Clientes';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fa fa-users';
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

        AssetManager::add('css', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/CSS/stripe.css');
        AssetManager::add('js', FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/Helper.js');
        $this->action = $this->request->query->get('action');
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

                $this->stripe_customer_email = $this->request->request->get('stripe_customer_email') ?? StripeSession::get('stripe_customer_email', '');
                $paymentMethod = new FormaPago();
                $this->paymentMethods = $paymentMethod->all();

                StripeSession::set('sk_stripe_index', $this->sk_stripe_index);
                StripeSession::set('stripe_customer_email', $this->stripe_customer_email);

                $this->getData($this->sk_stripe_index, $this->stripe_customer_email);
                break;

            case 'linkClient':
                $customerId = $this->request->query->get('customer_id');
                $stripeCustomerId = $this->request->query->get('stripe_customer_id');

                if (!empty($customerId)) {
                    try {
                        StripeCustomer::linkToFsCustomer((int)StripeSession::get('sk_stripe_index'), $stripeCustomerId, $customerId);
                        Tools::log()->info('Cliente vinculado correctamente.');
                    } catch (Exception $e) {
                        Tools::log()->error($e->getMessage());
                    }
                } else {
                    Tools::log()->error('Error al seleccionar el cliente');
                }
                break;

            default:
                // Si no hay acción, solo se muestra el desplegable para elegir la cuenta de stripe.
                break;
        }
    }

    public function getData($sk_stripe_index, string $stripe_customer_email): void
    {
        try {
            $this->clients = StripeCustomer::loadAll((int)$sk_stripe_index, $stripe_customer_email);
        } catch (Exception $e) {
            Tools::log()->error('Error: ' . $e->getMessage());
        }
    }
}

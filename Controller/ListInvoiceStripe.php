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
use FacturaScripts\Plugins\ImportadorStripe\Lib\DateHelper;
use FacturaScripts\Plugins\ImportadorStripe\Lib\InvoiceImporter;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeCustomer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;

class ListInvoiceStripe extends Controller
{
    public $invoices = [];
    public $sks_stripe = [];
    public $action = '';
    public $sk_stripe_index = null;
    public $textFilter = '';
    public $f_ini = '';
    public $f_fin = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Facturas';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fa-solid fa-file-invoice-dollar';
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
        $this->sks_stripe = StripeSettings::getSks();

        switch ($this->action) {
            case 'load':
                $this->sk_stripe_index = $this->request->request->get('sk_stripe_index')
                    ?? $this->request->query->get('sk_stripe_index');

                if ($this->sk_stripe_index === null) {
                    Tools::log()->error('No se ha recibido el sk correspondiente');
                    return;
                }

                $start = $this->request->query->get('start') ?: null;
                $limit = (int)($this->request->query->get('limit') ?: 5);
                $fIni = null;
                $fFin = null;

                // Obtengo las fechas si vienen en el POST y las convierto a timestamp.
                if ($this->request->request->get('f-ini-date')) {
                    $this->f_ini = $this->request->request->get('f-ini-date');
                    $fIni = DateHelper::parseDateToTS($this->f_ini, 'Y-m-d');
                }
                if ($this->request->request->get('f-fin-date')) {
                    $this->f_fin = $this->request->request->get('f-fin-date');
                    $fFin = DateHelper::parseDateToTS($this->f_fin, 'Y-m-d');
                }

                $this->getData($this->sk_stripe_index, $start, $limit, $fIni, $fFin);
                $this->textFilter = 'Filtrando de ' . $this->f_ini . ' a ' . $this->f_fin;
                break;

            case 'linkClient':
                $customerId = $this->request->query->get('customer_id');
                $stripeCustomerId = $this->request->query->get('stripe_customer_id');

                if (!empty($customerId)) {
                    try {
                        StripeCustomer::linkToFsCustomer((int)$_SESSION['sk_stripe_index'], $stripeCustomerId, $customerId);
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

    /**
     * Carga las facturas de stripe que no tienen en los metadatos la variable fs_idFactura, con los filtros de fecha.
     */
    public function getData($sk_stripe_index, $start = null, int $limit = 5, $f_ini = null, $f_fin = null): void
    {
        try {
            $data = InvoiceImporter::loadInvoicesNotProcessed($sk_stripe_index, $start, $limit, (int)$f_ini, $f_fin);

            if ($data['status'] === false) {
                Tools::log()->error('No se han podido cargar las facturas ' . $data['message']);
            } else {
                $this->invoices = $data;
            }
        } catch (Exception $e) {
            Tools::log()->error('No se han podido cargar las facturas ' . $e->getMessage());
        }
    }
}

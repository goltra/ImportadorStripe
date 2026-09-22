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
use FacturaScripts\Core\Model\Cliente;
use FacturaScripts\Core\Model\FormaPago;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ImportadorStripe\Lib\InvoiceImporter;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeCustomer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSession;

class CreateInvoiceStripe extends Controller
{
    public $existClient = false;
    public $clientFs;
    public $sk_stripe_index = null;
    public $action = '';
    public $error = false;
    public $payment_methods = [];
    public $customer_id = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Crear nueva factura desde stripe';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
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
        $this->action = $this->request->query->get('action');
        $this->sk_stripe_index = $this->request->query->get('sk_stripe_index') ?? StripeSession::get('sk_stripe_index');
        $this->paymentMethods();

        if ($this->sk_stripe_index === null) {
            Tools::log()->error('No se ha definido el sk');
            return;
        }

        switch ($this->action) {
            case 'check':
                $id = $this->request->query->get('id');
                StripeSession::set('id_stripe_invoice', $id);
                StripeSession::set('sk_stripe_index', $this->sk_stripe_index);

                if ($id !== null) {
                    $this->processInvoice($id, $this->sk_stripe_index);
                }
                break;

            case 'createInvoice':
                $markAsPaid = $this->request->request->get('ck_paid') !== null && $this->request->request->get('ck_paid') !== 'false';
                $paymentMethod = $markAsPaid && $this->request->request->get('payment_method') !== null
                    ? $this->request->request->get('payment_method')
                    : null;
                $sendByEmail = $this->request->request->get('send_email') !== null && $this->request->request->get('send_email') !== 'false';

                $this->generateFSInvoice(StripeSession::get('id_stripe_invoice'), $this->sk_stripe_index, $markAsPaid, $paymentMethod, $sendByEmail);
                break;

            case 'linkClient':
                $customerId = $this->request->query->get('customer_id');
                $stripeCustomerId = $this->request->query->get('stripe_customer_id');

                if (!empty($customerId)) {
                    try {
                        StripeCustomer::linkToFsCustomer((int)StripeSession::get('sk_stripe_index'), $stripeCustomerId, $customerId);
                        Tools::log()->info('Cliente vinculado correctamente.');
                        $this->setClientToStripeClient();
                    } catch (Exception $e) {
                        Tools::log()->error($e->getMessage());
                    }
                } else {
                    Tools::log()->error('Error al seleccionar el cliente');
                }
                break;

            case 'clientOk':
                $this->customer_id = $this->request->query->get('codcliente');
                if ($this->customer_id !== null) {
                    $this->setClientToStripeClient();
                } else {
                    Tools::log()->error('No se pudo ejecutar la acción');
                }
                break;
        }
    }

    /**
     * Carga las formas de pago para mostrarlas en un select de la vista.
     */
    private function paymentMethods(): void
    {
        $paymentMethods = new FormaPago();

        foreach ($paymentMethods->all() as $payment) {
            $this->payment_methods[$payment->codpago] = $payment->descripcion;
        }
    }

    /**
     * Carga la factura de stripe para comprobar si el cliente está asociado a un cliente de FS y guarda en una sesión
     * el id del cliente de stripe, de modo que podamos usarlo para vincularlo con el cliente de FS en caso de que no lo esté.
     * Si no está vinculado, la vista muestra la opción de vincularlo a un cliente existente o crear uno nuevo en FS.
     * También carga en la variable clienteFs los datos del cliente de FS cuando está vinculado para mostrarlo en la vista.
     */
    private function processInvoice($id, $sk_stripe_index): void
    {
        $invoice = InvoiceImporter::loadInvoiceFromStripe($id, $sk_stripe_index);

        if (isset($invoice['data'][0]->customer_id)) {
            StripeSession::set('stripe_customer_id', $invoice['data'][0]->customer_id);
        }

        if (!empty($invoice['data'][0]->fs_idFsCustomer)) {
            $this->existClient = true;
            $this->clientFs = new Cliente();
            $this->clientFs->load($invoice['data'][0]->fs_idFsCustomer);
        }
    }

    private function setClientToStripeClient(): void
    {
        try {
            StripeCustomer::linkToFsCustomer((int)StripeSession::get('sk_stripe_index'), StripeSession::get('stripe_customer_id'), $this->customer_id);
            $this->processInvoice(StripeSession::get('id_stripe_invoice'), StripeSession::get('sk_stripe_index'));
            Tools::log()->info('Cliente vinculado correctamente.');
        } catch (Exception $e) {
            Tools::log()->error($e->getMessage());
        }
    }

    /**
     * Crea la factura en FS y la vincula con la de Stripe.
     * Devuelve el código de la factura creada o null en caso de fallar.
     *
     * @return int|null
     */
    private function generateFSInvoice($id_invoice_stripe, $sk_stripe_index, $mark_as_paid, $payment_method, $send_by_email)
    {
        try {
            $res = InvoiceImporter::generateFSInvoice($id_invoice_stripe, $sk_stripe_index, $mark_as_paid, $payment_method, $send_by_email);
            $this->error = !$res['status'];
            return $res['code'];
        } catch (Exception $e) {
            $this->error = true;
            Tools::log()->error($e->getMessage());
            return null;
        }
    }
}

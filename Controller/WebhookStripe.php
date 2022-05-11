<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\ImportadorStripe\Model\InvoiceStripe;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;


class WebhookStripe extends Controller
{

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Webhook de stripe';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function publicCore(&$response)
    {
        $this->init();
    }


    public function init(){


        $payload = @file_get_contents('php://input');

        if(!$payload){
            InvoiceStripe::log('No entra');
            http_response_code(400);
            exit();
        }

        $data = json_decode($payload);

        if(!isset($_GET['source'])){
            InvoiceStripe::log('No viene source');
            http_response_code(400);
            exit();
        }

        $source = $_GET['source'];
        $sk_index = InvoiceStripe::loadSkStripeByToken($source);
        InvoiceStripe::log('source: '.$source);
        InvoiceStripe::log('sk_index: '.$sk_index);


        if ($sk_index === -1){
            InvoiceStripe::log('El source recibido no corresponde a ninguno de stripe');
            http_response_code(400);
            exit();
        }

        $sk = InvoiceStripe::loadSkStripe()[$sk_index];
        InvoiceStripe::log('sk: '.serialize($sk));


        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
        } catch(ApiErrorException $e) {

            InvoiceStripe::log('Error en data');
            http_response_code(400);
            exit();
        }

//            $event->type === 'invoice.paid'
        if($event->type == 'invoice.payment_succeeded') {
            $id = $event->data->object->id;

            $this->toolbox()->log('stripe')->error($event->data);

            try {

                InvoiceStripe::generateFSInvoice($id, $sk_index, false, 'TARJETA', true, $event->data->object->customer, 'webhook');
                InvoiceStripe::log('invoice id correcto: ' . $id);
            } catch (Exception $ex) {
                InvoiceStripe::log('invoice id error: ' . $id);
                var_dump($ex->getMessage());
                http_response_code(400);
                exit();
            }
        }

        http_response_code(200);
    }
}

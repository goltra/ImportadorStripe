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
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    public function publicCore(&$response)
    {
        $this->init();
    }


    public function init(){

//        $sk = 0;
//        $id = 'in_1KtvAyHDuQaJAlOmqaj0kxOl';
//
//        try {
//            InvoiceStripe::generateFSInvoice($id, $sk, false, 'TARJETA', false);
//        } catch (Exception $ex) {
//            $this->toolbox()->log()->error($ex->getMessage());
//            http_response_code(400);
//            exit();
//        }
//
//
//        die();


        $payload = @file_get_contents('php://input');

        if(!$payload){
            http_response_code(400);
            exit();
        }

        $data = json_decode($payload);

        $sk_index = 0;
        $sk = InvoiceStripe::loadSkStripe()[$sk_index];

        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
        } catch(ApiErrorException $e) {
            http_response_code(400);
            exit();
        }

//            $event->type === 'invoice.paid'
        if($event->type == 'invoice.payment_succeeded') {
            $id = $event->data->object->id;

            try {
                InvoiceStripe::generateFSInvoice($id, $sk_index, false, 'TARJETA', true);
                $this->toolbox()->log('stripe')->error('invoice id correcto: ' . $event->data->object->id);
            } catch (Exception $ex) {
                $this->toolbox()->log('stripe')->error('invoice id error: ' . $event->data->object->id);
                $this->toolbox()->log('stripe')->error($ex->getMessage());
                http_response_code(400);
                exit();
            }
        }

        http_response_code(200);
    }
}

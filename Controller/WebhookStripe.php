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
            $this->toolbox()->log('stripe')->error('no entra');
            http_response_code(400);
            exit();
        }

        $data = json_decode($payload);

        if(!isset($data->data->object->account_name)){
            $this->toolbox()->log('stripe')->error('no viene el account name');
            http_response_code(400);
            exit();
        }

        $account_name = $data->data->object->account_name;
        $sk_index = InvoiceStripe::loadSkStripeByAccountName($account_name);

        if(!$sk_index){
            $this->toolbox()->log('stripe')->error('El sk de la empresa no está dada de alta en facturascript');
            http_response_code(400);
            exit();
        }

        $sk = InvoiceStripe::loadSkStripe()[$sk_index];

        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
        } catch(ApiErrorException $e) {
            $this->toolbox()->log('stripe')->error('Error en data');
            http_response_code(400);
            exit();
        }

//            $event->type === 'invoice.paid'
        if($event->type == 'invoice.payment_succeeded') {
            $id = $event->data->object->id;

            try {
                InvoiceStripe::generateFSInvoice($id, $sk_index, false, 'TARJETA', true, $event->data->object->customer);
                $this->toolbox()->log('stripe')->error('invoice id correcto: ' . $id);
            } catch (Exception $ex) {
                $this->toolbox()->log('stripe')->error('invoice id error: ' . $id);
                $this->toolbox()->log('stripe')->error($ex->getMessage());
                http_response_code(400);
                exit();
            }
        }

        http_response_code(200);
    }
}

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
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;


class WebhookStripe extends Controller
{

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Webhook de stripe';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function publicCore(&$response): void
    {

        $this->initTest();
//        $this->init();
    }

    public function initTest()
    {
        $model = new StripeTransactionsQueue();
        $model->processQueue();
    }


    public function init(): void
    {

        $payload = @file_get_contents('php://input');

        if(!$payload){
            InvoiceStripe::log('No entra');
            http_response_code(400);
            exit();
        }

        $data = json_decode($payload);

//        if(!isset($_GET['source'])){
//            InvoiceStripe::log('No viene source');
//            http_response_code(400);
//            exit();
//        }

//        $source = $_GET['source'];
        $source = 'c38113434288e0c3cd160210ba3f2158';

        //        todo esto ha cambiado, ahora loadSkStripeByToken devuelve el sk completo para tener también el name y guardarlo en la cola
        $sk = SettingStripeModel::loadSkStripeByToken($source);

        if (empty($sk)){
            InvoiceStripe::log('El source recibido no corresponde a ninguno de stripe');
            http_response_code(400);
            exit();
        }

        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
        } catch(ApiErrorException $e) {

            InvoiceStripe::log('Error en data');
            http_response_code(400);
            exit();
        }

        if($event->type == 'invoice.payment_succeeded') {
            $id = $event->data->object->id;

            if($event->data->object->amount_paid === 0){
                InvoiceStripe::log('Se ha pagado 0€, no se factura');
                http_response_code(200);
                exit();
            }

            try {
                StripeTransactionsQueue::setStripeTransaction(
                    $sk['name'],
                    StripeTransactionsQueue::EVENT_INVOICE_PAYMENT_SUCCEEDED,
                    $id,
                    date('Y-m-d H:i:s'),
                    StripeTransactionsQueue::TRANSACTION_TYPE_INVOICE,
                    $id,
                    StripeTransactionsQueue::DESTINATION_CUSTOMER,
                    $event->data->object->customer,
                );

                InvoiceStripe::log('invoice id correcto: ' . $id);
            } catch (Exception $ex) {
//                InvoiceStripe::log('invoice id error: ' . $id);
//                InvoiceStripe::sendMailError($id, serialize($ex->getMessage()));
//                /*
//                 * Tenemos un bug de facturascript que cuando entran dos facturas al mismo tiempo, la segunda coge el código de la primera y luego al guardar da error.
//                 * Por tanto, si el error es ese, le mandamos un código 400 para que stripe vuelva a llamar más tarde.
//                 */
//                if ($ex->getMessage() === 'Error al generar la factura.'){
//                    http_response_code(400);
//                }
//                else{
//                    var_dump($ex->getMessage());
//                    http_response_code(200);
//                }

                exit();
            }
        }

        http_response_code(200);
    }
}

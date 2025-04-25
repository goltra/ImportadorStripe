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
                $enviarEmail = SettingStripeModel::getSetting('enviarEmail') == 1;
                InvoiceStripe::generateFSInvoice($id, $sk_index, false, 'TARJETA', $enviarEmail, $event->data->object->customer, 'webhook');
                InvoiceStripe::log('invoice id correcto: ' . $id);
            } catch (Exception $ex) {
                InvoiceStripe::log('invoice id error: ' . $id);
                InvoiceStripe::sendMailError($id, serialize($ex->getMessage()));
                /*
                 * Tenemos un bug de facturascript que cuando entran dos facturas al mismo tiempo, la segunda coge el código de la primera y luego al guardar da error.
                 * Por tanto, si el error es ese, le mandamos un código 400 para que stripe vuelva a llamar más tarde.
                 */
                if ($ex->getMessage() === 'Error al generar la factura.'){
                    http_response_code(400);
                }
                else{
                    var_dump($ex->getMessage());
                    http_response_code(200);
                }

                exit();
            }
        }

        http_response_code(200);
    }
}

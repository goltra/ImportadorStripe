<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

//stripe listen --forward-to localhost:8081/WebhookStripeRemesasSepa

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\InvoiceStripe;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA as RemesaSEPAAlias;
use PHPMailer\PHPMailer\Exception;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\StripeClient;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * Controlador que va a hacer de webservice para crear remesas en base a los payout de stripe.
 * El funcionamiento es que por cada transferencia que haga Stripe, llamará aquí y se creará una remesa con todas las facturas asociadas a esa transferencia
 */
class WebhookStripeRemesasSepa extends Controller
{

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Webhook de stripe para remesas sepa';
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
//        InvoiceStripe::log('entro al init', 'remesa');
//
//        if (!SettingStripeModel::getSetting('remesasSEPA')){
//            InvoiceStripe::log('No tienes remesas activadas en los ajustes del plugin', 'remesa');
//            $this->sendMailError();
//            echo 'Debes activar las remesas en Stripe >> Ajustes';
//            return;
//        }
//
//        $payload = @file_get_contents('php://input');
//
//        if(!$payload){
//            InvoiceStripe::log('No viene payload', 'remesa');
//            $this->sendMailError();
//            http_response_code(400);
//            exit();
//        }
//
//        $data = json_decode($payload);
//
//        if(!isset($_GET['source'])){
//            InvoiceStripe::log('No viene source', 'remesa');
//            $this->sendMailError();
//            http_response_code(400);
//            exit();
//        }
//
//        $source = $_GET['source'];
//        // $source = 'd4d9a56531e84cd5b842e208b3ee65ef';
//        $sk_index = InvoiceStripe::loadSkStripeByToken($source);
//
//
//
//        if ($sk_index === -1){
//            InvoiceStripe::log('No hay sk_index', 'remesa');
//            $this->sendMailError();
//            http_response_code(400);
//            exit();
//        }
//
//        $sk = InvoiceStripe::loadSkStripe()[$sk_index];
//
//        InvoiceStripe::log('SK '. serialize($sk), 'remesa');
//
//        Stripe::setApiKey($sk['sk']);
//
//        try {
//            $event = Event::retrieve($data->id);
//            InvoiceStripe::log('Recuperamos event', 'remesa');
//        } catch(ApiErrorException $e) {
//            InvoiceStripe::log('Error al recuperar el evento', 'remesa');
//            $this->sendMailError();
//
//            http_response_code(400);
//            exit();
//        }
//
//        if($event->type == 'payout.paid') {
//
//            $payoutId = $event->data->object->id;;
//            InvoiceStripe::log('payout id: ' . $payoutId, 'remesa');
//
//            //        $payoutId = 'po_1QhK6gHDuQaJAlOmouHWIs8M';
            $payoutId = 'po_1S3xnSHDuQaJAlOmOfcCD9RU';
            $sk = 'sk_test_51ILOeaHDuQaJAlOmoxCwXO9mYqMKmXk6c9ByTDILdJ3vujXorxScbbyTNBrQeXb82oNeqq4UsioajKWiSaRMEGL700xoDW92tk';

            try {
//                $this->processPayout($sk['sk'], $payoutId);
                $this->processPayout($sk, $payoutId);
            }
            catch (Exception|ApiErrorException|LoaderError|RuntimeError|SyntaxError $e) {
                $this->sendMailError(serialize($e->getMessage()));
            }
//
//            echo ' todo ok';
//        }
//
//
//
//        http_response_code(200);

        /**
         * todo flujo:
         * Recibo el payout_id
         * Comprobamos que ese pago no esté ya registrado
         * Creo la remesa y me guardo el id de la remesa
         * Leo el payout_id y meto todos los cargos junto a la remesa en la base de datos.
         * Mando un email avisando que se ha recibido el cargo.
         */

    }


    /**
     * @param $sk
     * @param $payoutId
     * @return void
     * @throws ApiErrorException
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function processPayout($sk, $payoutId): void
    {
        InvoiceStripe::log('Entra a processPayout', 'remesa');
        echo '<pre>';
        $stripe = new StripeClient($sk);

        //  Pido los datos del pago
        $payout = $stripe->payouts->retrieve($payoutId, []);


        $totalIngresoStripe = $payout['amount'] / 100;
        InvoiceStripe::log('Total ingreso: ' . $totalIngresoStripe, 'remesa');

        //  Creo la remesa
        $remesa = new RemesaSEPA();

        $remesa->nombre = $payoutId;
        $remesa->descripcion = 'Pago CJL';
        $remesa->fecha = date('Y-m-d H:i:s');
        $remesa->fechacargo  = date('Y-m-d', $payout['arrival_date']);
        $remesa->estado = RemesaSEPAAlias::STATUS_WAIT;
        $remesa->codcuenta = (int)SettingStripeModel::getSetting('cuentaRemesaSEPA');
        $remesa->save();

        InvoiceStripe::log('Se genera la remesa. ', 'remesa');

        // Pido el balance transaction
        $balanceTransaction = $stripe->balanceTransactions->all([
            'payout' => $payoutId,
            'limit' => 10000,
        ]);

        $errors = [];

        InvoiceStripe::log('El pago trae ' . count($balanceTransaction->data) . 'cargos.', 'remesa');

        foreach ($balanceTransaction->data as $transaction) {

            if (empty($transaction['source'])){
                InvoiceStripe::log('No viene source en transaction', 'remesa');
                continue;
            }


        }



//        if (SettingStripeModel::getSetting('adminEmail'))
//            $this->sendMail($errors, $remesa->total, $totalIngresoStripe, $remesa->idremesa);

//        echo 'Importación completada con éxito <br />';
//        echo 'Errores: <br />';
//        var_dump($errors);
//        echo 'Total transferencia: ' . $remesa->total . ' €';

    }



    /**
     * @param string $error
     * @return void
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendMailError(string $error = ''): void
    {
        $subject = 'Error al generar la remesa de cobro de stripe';
        $body = "Hola, \r\n La llamada de stripe para generar una remesa ha dado error: . \r\n";

        if ($error)
            $body .= $error;


        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('adminEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();

    }


    /**
     * Método que va a mandar un email
     * @param $errors
     * @param $totalRemesa
     * @param $totalIngresoStripe
     * @param $idRemesa
     * @return void
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendMail($errors, $totalRemesa, $totalIngresoStripe, $idRemesa): void
    {
        $subject = 'Nueva remesa de cobro de stripe creada';
        $body = "Hola, \r\n Se ha creado la remesa $idRemesa de forma automática por un pago de stripe. \r\n";
        $body .= "Total de la remesa: $totalRemesa €\r\n";
        $body .= "Total del ingreso: $totalIngresoStripe €\n";

        if (count($errors) > 0)
            $body .= "Errores:\r\n" . implode("\r\n", $errors);

        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('adminEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }
}

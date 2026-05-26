<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\ImportadorStripe\Model\InvoiceStripe;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class   WebhookStripe extends Controller
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

//        $this->initTest();
        $this->init();
    }

//    public function initTest()
//    {
//        StripeTransactionsQueue::processQueue();
//    }


    public function init(): void
    {
        $payload = @file_get_contents('php://input');

        if(!$payload)
            $this->sendError('Error: No viene payload', 400);

        $data = json_decode($payload);

        if (!isset($_GET['source']))
            $this->sendError('Error: No viene source', 400);

        $source = $_GET['source'];
//        $source = 'c38113434288e0c3cd160210ba3f2158';

        $sk = SettingStripeModel::loadSkStripeByToken($source);

        if (count($sk) === 0)
            $this->sendError('Error: No hay sk', 400);

        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
        } catch(ApiErrorException $e) {
            $this->sendError('Error: Error en data: ' . $e->getMessage(), 400);
            exit();
        }

        if($event->type == 'invoice.finalized') {
            InvoiceStripe::log('invoice.finalized');

            try {
                $paymentMethod = StripeTransactionsQueue::getPaymentMethodFromPI($event->data->object->payment_intent, $sk['sk']);

                if (!$paymentMethod)
                    $this->sendError('invoice.finalized: No encuentro el método de pago ', 200);

                if ($paymentMethod !== 'sepa_debit')
                    $this->sendError('invoice.finalized: No es un pago sepa, no se agrega a la cola ', 200, false);

                InvoiceStripe::log('PaymentMethod: ' . $paymentMethod);
                $this->addEventToQueue($event, $sk);
            } catch (Exception $ex) {
                $this->sendError('Error: Error al registrar la factura en la cola. '. $ex->getMessage(), 200);
            }
        }


        if($event->type == 'invoice.paid') {
            InvoiceStripe::log('invoice.paid');

            try {
                $paymentMethod = StripeTransactionsQueue::getPaymentMethodFromPI($event->data->object->payment_intent, $sk['sk']);

                if (!$paymentMethod)
                    $this->sendError('invoice.finalized: No encuentro el método de pago ', 200);

                if (!$paymentMethod || $paymentMethod === 'sepa_debit')
                    $this->sendError('invoice.paid: Es un pago sepa, no se agrega a la cola ', 200, false);

                InvoiceStripe::log('PaymentMethod: ' . $paymentMethod);

                $this->addEventToQueue($event, $sk);
            } catch (Exception $ex) {
                $this->sendError('Error: Error al registrar la factura en la cola. '. $ex->getMessage(), 200);
            }

        }


        if ($event->type == 'invoice.marked_uncollectible') {
            InvoiceStripe::log('invoice.marked_uncollectible');
            $factura_numero = $event->data->object->number;
            $factura_id = $event->data->object->id;
            $facturaFS = new FacturaCliente();

            if ($facturaFS->loadWhere([Where::eq('numero2', $factura_numero)])) {
                $this->sendMailFacturaIncobrable($factura_id, $facturaFS->idfactura, $facturaFS->codigo);
            }
        }

        http_response_code(200);
        exit();
    }


    /**
     * @param Event $event
     * @param array $sk
     * @return void
     */
    private function addEventToQueue(Event $event, array $sk): void
    {
        $invoiceId = $event->data->object->id;

        if($event->data->object->amount_due === 0)
            $this->sendError('Se ha pagado 0€, no se factura', 200, false);

        try {

            if (StripeTransactionsQueue::existsObjectId($invoiceId, StripeTransactionsQueue::EVENT_INVOICE_PAYMENT_SUCCEEDED))
                $this->sendError('Error: La factura ya está en la cola ', 200);

            StripeTransactionsQueue::setStripeTransaction(
                $sk['name'],
                StripeTransactionsQueue::EVENT_INVOICE_PAYMENT_SUCCEEDED,
                $invoiceId,
                date('Y-m-d H:i:s'),
                StripeTransactionsQueue::TRANSACTION_TYPE_INVOICE,
                $invoiceId,
                StripeTransactionsQueue::DESTINATION_CUSTOMER,
                $event->data->object->customer,
            );

            InvoiceStripe::log('invoice id correcto: ' . $invoiceId);
        } catch (Exception $ex) {
            $this->sendError('Error: Error al registrar la factura en la cola. '. $ex->getMessage(), 200);
        }
    }

    /**
     * @param $error
     * @param $response_code
     * @param bool $send_email
     * @return void
     */
    private function sendError($error, $response_code, bool $send_email = true): void
    {
        echo $error;
        InvoiceStripe::log($error);

        if ($send_email){
            try {
                $this->sendMailError($error);
            }
            catch (Exception $e) {
                InvoiceStripe::log('No se ha podido mandar el email. '. $e->getMessage());
            }
        }

        http_response_code($response_code);
        exit();
    }

    /**
     * @param string $error
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function sendMailError(string $error = ''): void
    {
        $subject = 'Error al agregar a la cola la factura de stripe';
        $body = "Hola, \r\n La llamada de stripe para agregar a la cola una factura ha dado error: . \r\n";

        if ($error)
            $body .= $error;

        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('satEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }

    private function sendMailFacturaIncobrable($factura_stripe_id, $factura_fs_id, $factura_fs_codigo)
    {
        $subject = 'No se ha podido cobrar la factura';
        $body = "Hola, \r\n La factura de stripe <a href=\"https://dashboard.stripe.com/acct_1ILOeaHDuQaJAlOm/invoices/$factura_stripe_id\">$factura_stripe_id</a> no se ha podido cobrar y había sido generada con el código <a href=\"https://goltratec.facturasenlanube.es/EditFacturaCliente?code=$factura_fs_id\">$factura_fs_codigo</a>.";

        try {
            $mail = NewMail::create()
                ->to(SettingStripeModel::getSetting('adminEmail'))
                ->subject($subject)
                ->body($body);

            $mail->send();
        }
        catch (Exception $ex) {
            InvoiceStripe::log('Error al enviar el email cuando la factura no se puede cobrar');
            InvoiceStripe::log(serialize($ex->getMessage()));
        }
    }
}

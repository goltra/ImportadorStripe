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
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\ImportadorStripe\Lib\Logger;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeGateway;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeMailer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;

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
        $this->init();
    }

    public function init(): void
    {
        $payload = @file_get_contents('php://input');

        if (!$payload) {
            $this->sendError('Error: No viene payload', 400);
        }

        $data = json_decode($payload);

        if (!isset($_GET['source'])) {
            $this->sendError('Error: No viene source', 400);
        }

        $sk = StripeSettings::loadSkStripeByToken($_GET['source']);

        if (count($sk) === 0) {
            $this->sendError('Error: No hay sk', 400);
        }

        $gateway = new StripeGateway($sk['sk']);

        try {
            $event = $gateway->retrieveEvent($data->id);
        } catch (ApiErrorException $e) {
            $this->sendError('Error: Error en data: ' . $e->getMessage(), 400);
        }

        if ($event->type === 'invoice.finalized' || $event->type === 'invoice.paid') {
            Logger::log($event->type);
            $this->handleInvoiceEvent($event, $sk, $gateway, $event->type === 'invoice.finalized');
        }

        if ($event->type === 'invoice.marked_uncollectible') {
            Logger::log($event->type);
            $facturaNumero = $event->data->object->number;
            $facturaId = $event->data->object->id;
            $facturaFs = new FacturaCliente();

            if ($facturaFs->loadWhere([Where::eq('numero2', $facturaNumero)])) {
                StripeMailer::sendUncollectible($facturaId, $facturaFs->idfactura, $facturaFs->codigo);
            }
        }

        http_response_code(200);
        exit();
    }

    /**
     * Procesa los eventos de factura pagada. $esSepa indica si el cobro es domiciliado (invoice.finalized).
     */
    private function handleInvoiceEvent(Event $event, array $sk, StripeGateway $gateway, bool $esSepa): void
    {
        try {
            if ($event->data->object->amount_due === 0) {
                $this->sendError('Se ha pagado 0€, no se factura', 200, false);
            }

            $paymentIntent = $event->data->object->payment_intent;

            if (!$paymentIntent) {
                $this->sendError('No viene Payment Intent', 200);
            }

            $paymentMethod = $gateway->getPaymentMethodType($paymentIntent);

            if (!$paymentMethod) {
                $this->sendError($event->type . ': No encuentro el método de pago', 200);
            }

            if ($esSepa && $paymentMethod !== 'sepa_debit') {
                $this->sendError($event->type . ': No es un pago sepa, no se agrega a la cola', 200, false);
            }

            if (!$esSepa && $paymentMethod === 'sepa_debit') {
                $this->sendError($event->type . ': Es un pago sepa, no se agrega a la cola', 200, false);
            }

            Logger::log('PaymentMethod: ' . $paymentMethod);
            $this->addEventToQueue($event, $sk);
        } catch (Exception $ex) {
            $this->sendError('Error al registrar la factura en la cola. ' . $ex->getMessage(), 200);
        }
    }

    private function addEventToQueue(Event $event, array $sk): void
    {
        $invoiceId = $event->data->object->id;

        try {
            if (StripeTransactionsQueue::existsObjectId($invoiceId, StripeTransactionsQueue::EVENT_INVOICE_PAYMENT_SUCCEEDED)) {
                $this->sendError('Error: La factura ya está en la cola', 200);
            }

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

            Logger::log('invoice id correcto: ' . $invoiceId);
        } catch (Exception $ex) {
            $this->sendError('Error al registrar la factura en la cola. ' . $ex->getMessage(), 200);
        }
    }

    private function sendError(string $error, int $responseCode, bool $sendEmail = true): void
    {
        echo $error;
        Logger::log($error);

        if ($sendEmail) {
            try {
                StripeMailer::sendWebhookError($error);
            } catch (Exception $e) {
                Logger::log('No se ha podido mandar el email. ' . $e->getMessage());
            }
        }

        http_response_code($responseCode);
        exit();
    }
}

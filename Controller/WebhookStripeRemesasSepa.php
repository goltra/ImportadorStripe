<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

//stripe listen --forward-to localhost:8081/WebhookStripeRemesasSepa

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\InvoiceStripe;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue as StripeTransactionsQueueAlias;
use PHPMailer\PHPMailer\Exception;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
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

    public function publicCore(&$response): void
    {
        $this->init();
    }


    public function init(): void
    {
        InvoiceStripe::log('entro al init', 'remesa');

        if (StripeTransactionsQueue::canUseRemesas())
            $this->sendError('Error: No tienes remesas activadas en los ajustes del plugin', 400);

        $payload = @file_get_contents('php://input');

        if (!$payload)
            $this->sendError('Error: No viene payload', 400);

        $data = json_decode($payload);

        if (!isset($_GET['source']))
            $this->sendError('Error: No viene source', 400);

        $source = $_GET['source'];
        // $source = 'd4d9a56531e84cd5b842e208b3ee65ef';
        $sk = SettingStripeModel::loadSkStripeByToken($source);


        if (count($sk) === 0)
            $this->sendError('Error: No hay sk', 400);

        InvoiceStripe::log('SK ' . serialize($sk), 'remesa');

//        $payoutId = 'po_1S899KHDuQaJAlOmVNHE1ZIN';
        Stripe::setApiKey($sk['sk']);

        try {
            $event = Event::retrieve($data->id);
            InvoiceStripe::log('Recuperamos event', 'remesa');
        } catch (ApiErrorException $e) {
            $this->sendError('Error: Error al recuperar el evento. '. $e->getMessage(), 400);
            exit();
        }

        if ($event->type == 'payout.paid') {

            $payoutId = $event->data->object->id;
            InvoiceStripe::log('payout id: ' . $payoutId, 'remesa');

            if (StripeTransactionsQueue::existsObjectId($payoutId, StripeTransactionsQueueAlias::EVENT_PAYOUT_PAID))
                $this->sendError('Error: El pago ya ha sido registrado previamente ', 200);

            try {
                $this->processPayout($sk, $payoutId);
            } catch (\Exception $e) {
                $this->sendError('Error: Error al registrar la remesa en la cola. '. $e->getMessage(), 200);
            }

            http_response_code(200);
            exit();
        }
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
        $stripe = new StripeClient($sk['sk']);

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
        $remesa->estado = RemesaSEPA::STATUS_WAIT;
        $remesa->codcuenta = (int)SettingStripeModel::getSetting('cuentaRemesaSEPA');

        if (!$remesa->save()){
            InvoiceStripe::log('No se ha podido crear la remesa. ', 'remesa');
            throw new Exception('Error al guardar la remesa');
        }

        InvoiceStripe::log('Se genera la remesa. ', 'remesa');

        // Pido el balance transaction
        $balanceTransactions = $this->getAllBalanceTransactions($stripe, $payoutId, 50);

        InvoiceStripe::log('El pago trae ' . count($balanceTransactions) . 'cargos.', 'remesa');
        $cargos = 0;
        $ok = 0;
        $errors = [];

        foreach ($balanceTransactions as $transaction) {

            if (empty($transaction['source']) || $transaction['type'] === 'payout' || !in_array($transaction['type'], ['payment', 'charge'])){
                continue;
            }

            $cargos ++;

            if (StripeTransactionsQueue::setStripeTransaction(
                $sk['name'],
                StripeTransactionsQueueAlias::EVENT_PAYOUT_PAID,
                $payoutId,
                date('Y-m-d H:i:s'),
                $transaction['type'] === 'charge' ? StripeTransactionsQueueAlias::TRANSACTION_TYPE_CHARGE : StripeTransactionsQueueAlias::TRANSACTION_TYPE_PAYMENT_INTENT,
                $transaction['source'],
                StripeTransactionsQueueAlias::DESTINATION_REMESA,
                $remesa->idremesa,
            ))
                $ok++;
            else{

                $lineaError = [
                    'sk' => $sk['name'],
                    'evento' => StripeTransactionsQueueAlias::EVENT_PAYOUT_PAID,
                    'pago' => $payoutId,
                    'fecha' => date('Y-m-d H:i:s'),
                    'transacción' => $transaction['type'] === 'charge' ? StripeTransactionsQueueAlias::TRANSACTION_TYPE_CHARGE : StripeTransactionsQueueAlias::TRANSACTION_TYPE_PAYMENT_INTENT,
                    'transaccion_id' => $transaction['source'],
                    'destino' => StripeTransactionsQueueAlias::DESTINATION_REMESA,
                    'destino_id' => $remesa->idremesa,
                ];

                $errors[] = serialize($lineaError);
            }

        }


        $this->sendMail($cargos, $ok, $errors, $totalIngresoStripe, $remesa->idremesa );

    }


    /**
     * @param $stripe
     * @param $payoutId
     * @param int $limitPerRequest
     * @param string $startingAfter
     * @param array $accumulated
     * @return array
     */
    private function getAllBalanceTransactions($stripe, $payoutId, int $limitPerRequest = 50, string $startingAfter = '', array $accumulated = []): array
    {
        // Stripe permite un máximo de 100 por request
        $limit = min($limitPerRequest, 100);

        $params = [
            'payout' => $payoutId,
            'limit'  => $limit,
        ];

        if ($startingAfter) {
            $params['starting_after'] = $startingAfter;
        }

        // Hacemos la llamada a Stripe
        $response = $stripe->balanceTransactions->all($params);

        // Acumulamos los resultados
        $accumulated = array_merge($accumulated, $response->data);

        // Si hay más páginas, seguimos recursivamente
        if ($response->has_more) {
            $lastId = end($response->data)->id;
            return $this->getAllBalanceTransactions($stripe, $payoutId, $limitPerRequest, $lastId, $accumulated);
        }

        // Si no hay más páginas, devolvemos todos los resultados acumulados
        return $accumulated;
    }

    /**
     * @param $error
     * @param $response_code
     * @return void
     */
    private function sendError($error, $response_code): void
    {
        echo $error;
        InvoiceStripe::log($error, 'remesa');

        try {
            $this->sendMailError($error);
        }
        catch (\Exception $e) {
            InvoiceStripe::log('No se ha podido mandar el email. '. $e->getMessage());
        }

        http_response_code($response_code);
        exit();
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
            ->to(SettingStripeModel::getSetting('satEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }

    /**
     * Método que va a mandar un email
     * @param $numCargos
     * @param $cargosCorrectos
     * @param $errores
     * @param $totalIngresoStripe
     * @param $idRemesa
     * @return void
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendMail($numCargos, $cargosCorrectos, $errores, $totalIngresoStripe, $idRemesa): void
    {
        $subject = 'Nueva remesa de cobro de stripe agregada a la cola.';
        $body = "Hola, \r\n Se ha creado la remesa $idRemesa de forma automática por un pago de stripe. Y todas las líneas se han agregado a la cola para su procesamiento. \r\n";
        $body .= "Total del ingreso: $totalIngresoStripe €\n";
        $body .= "Total cargos: $numCargos €\r\n";
        $body .= "Num cargos registrados: $cargosCorrectos €\r\n";

        if (count($errores) > 0)
            $body .= "Errores:\r\n" . implode("\r\n", $errores);

        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('adminEmail'))
            ->cc(SettingStripeModel::getSetting('satEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }
}

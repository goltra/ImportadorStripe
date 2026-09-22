<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\ImportadorStripe\Lib\Logger;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeGateway;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeMailer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use Stripe\Exception\ApiErrorException;

/**
 * Webhook que crea remesas en base a los payouts de stripe.
 * Por cada transferencia de Stripe se crea una remesa con todas las facturas asociadas.
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
        Logger::log('entro al init', Logger::CHANNEL_REMESA);

        if (!StripeTransactionsQueue::canUseRemesas()) {
            $this->sendError('Error: No tienes remesas activadas en los ajustes del plugin', 400);
        }

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

        Logger::log('SK ' . serialize($sk), Logger::CHANNEL_REMESA);

        $gateway = new StripeGateway($sk['sk']);

        try {
            $event = $gateway->retrieveEvent($data->id);
            Logger::log('Recuperamos event', Logger::CHANNEL_REMESA);
        } catch (ApiErrorException $e) {
            $this->sendError('Error: Error al recuperar el evento. ' . $e->getMessage(), 400);
        }

        if ($event->type === 'payout.paid') {
            $payoutId = $event->data->object->id;
            Logger::log('payout id: ' . $payoutId, Logger::CHANNEL_REMESA);

            if (StripeTransactionsQueue::existsObjectId($payoutId, StripeTransactionsQueue::EVENT_PAYOUT_PAID)) {
                $this->sendError('Error: El pago ya ha sido registrado previamente', 200);
            }

            try {
                $this->processPayout($sk, $payoutId, $gateway);
            } catch (Exception $e) {
                $this->sendError('Error: Error al registrar la remesa en la cola. ' . $e->getMessage(), 200);
            }

            http_response_code(200);
            exit();
        }
    }

    private function processPayout(array $sk, string $payoutId, StripeGateway $gateway): void
    {
        Logger::log('Entra a processPayout', Logger::CHANNEL_REMESA);

        $payout = $gateway->retrievePayout($payoutId);
        $totalIngreso = $payout['amount'] / 100;
        Logger::log('Total ingreso: ' . $totalIngreso, Logger::CHANNEL_REMESA);

        $remesa = new RemesaSEPA();
        $remesa->nombre = $this->getEmpresaNombre();
        $remesa->descripcion = 'Pago ' . $sk['name'];
        $remesa->fecha = date('Y-m-d H:i:s');
        $remesa->fechacargo = date('Y-m-d', $payout['arrival_date']);
        $remesa->estado = RemesaSEPA::STATUS_WAIT;
        $remesa->codcuenta = (int)StripeSettings::getSetting('cuentaRemesaSEPA');

        if (!$remesa->save()) {
            Logger::log('No se ha podido crear la remesa.', Logger::CHANNEL_REMESA);
            throw new Exception('Error al guardar la remesa');
        }

        Logger::log('Se genera la remesa.', Logger::CHANNEL_REMESA);

        $balanceTransactions = $gateway->listAllBalanceTransactions($payoutId);
        Logger::log('El pago trae ' . count($balanceTransactions) . ' cargos.', Logger::CHANNEL_REMESA);

        $cargos = 0;
        $ok = 0;
        $errores = [];

        foreach ($balanceTransactions as $transaction) {
            if (empty($transaction['source']) || $transaction['type'] === 'payout' || !in_array($transaction['type'], ['payment', 'charge'], true)) {
                continue;
            }

            $cargos++;

            $saved = StripeTransactionsQueue::setStripeTransaction(
                $sk['name'],
                StripeTransactionsQueue::EVENT_PAYOUT_PAID,
                $payoutId,
                date('Y-m-d H:i:s'),
                StripeTransactionsQueue::TRANSACTION_TYPE_INVOICE,
                $transaction['source']->invoice,
                StripeTransactionsQueue::DESTINATION_REMESA,
                $remesa->idremesa,
            );

            if ($saved) {
                $ok++;
                continue;
            }

            $errores[] = serialize([
                'sk' => $sk['name'],
                'evento' => StripeTransactionsQueue::EVENT_PAYOUT_PAID,
                'pago' => $payoutId,
                'fecha' => date('Y-m-d H:i:s'),
                'transacción' => StripeTransactionsQueue::TRANSACTION_TYPE_INVOICE,
                'transaccion_id' => $transaction['source']->invoice,
                'destino' => StripeTransactionsQueue::DESTINATION_REMESA,
                'destino_id' => $remesa->idremesa,
            ]);
        }

        StripeMailer::sendRemesaCreated($cargos, $ok, $errores, $totalIngreso, $remesa->idremesa);
    }

    private function getEmpresaNombre(): string
    {
        $empresa = new Empresa();
        $empresa->load(Tools::settings('default', 'idempresa'));

        return $empresa->nombre;
    }

    private function sendError(string $error, int $responseCode): void
    {
        echo $error;
        Logger::log($error, Logger::CHANNEL_REMESA);

        try {
            StripeMailer::sendRemesaWebhookError($error);
        } catch (Exception $e) {
            Logger::log('No se ha podido mandar el email. ' . $e->getMessage(), Logger::CHANNEL_REMESA);
        }

        http_response_code($responseCode);
        exit();
    }
}

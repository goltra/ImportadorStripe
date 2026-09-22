<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use Stripe\Exception\ApiErrorException;

/**
 * Orquesta la importación de facturas de Stripe a FacturaScripts.
 */
class InvoiceImporter
{
    /**
     * Devuelve las facturas de Stripe pagadas, con importe > 0 y sin metadato fs_idFactura.
     *
     * @throws Exception
     */
    public static function loadInvoicesNotProcessed(int $skIndex, $start = null, int $limit = 5, int $initDate = 631200892, ?int $endDate = null): array
    {
        try {
            $limit = 100000;
            $endDate ??= time();

            $response = StripeGateway::byIndex($skIndex)->listPaidInvoices($limit, $initDate, $endDate);

            $pending = array_filter($response->data, function ($stripeInvoice) {
                return $stripeInvoice->amount_due > 0 && empty($stripeInvoice->metadata['fs_idFactura']);
            });

            return [
                'status' => true,
                'data' => InvoiceParser::parse($pending, $skIndex),
                'last' => !empty($response->data) ? end($response->data)->id : null,
                'limit' => $limit,
                'has_more' => $response->has_more,
            ];
        } catch (ApiErrorException $e) {
            StripeMailer::sendInvoiceError('(no hay factura)', $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function loadInvoiceFromStripe(string $id, int $skIndex): array
    {
        Logger::log('loadInvoiceFromStripe');
        Logger::log('id de la factura a descargar: ' . $id);

        try {
            $stripeInvoice = StripeGateway::byIndex($skIndex)->retrieveInvoice($id);
            Logger::log('factura descargada');

            try {
                $parsed = InvoiceParser::parse([$stripeInvoice], $skIndex);
            } catch (Exception $e) {
                Logger::log('Error al procesar la factura de stripe ' . serialize($e->getMessage()));
                StripeMailer::sendInvoiceError($id, serialize($e->getMessage()));
                return ['status' => false, 'message' => 'Error al procesar la factura de stripe ' . $e->getMessage()];
            }

            return ['status' => true, 'data' => $parsed];
        } catch (Exception $ex) {
            Logger::log('Error al obtener la factura desde stripe ' . serialize($ex->getMessage()));
            StripeMailer::sendInvoiceError($id, serialize($ex->getMessage()));
            return ['status' => false, 'message' => 'Error al obtener la factura desde stripe ' . $ex->getMessage()];
        }
    }

    /**
     * Crea una nueva factura en FS a partir de la factura de Stripe.
     *
     * @return array{status: bool, code: int|null}
     * @throws Exception
     */
    public static function generateFSInvoice($id_invoice_stripe, $sk_stripe_index, $mark_as_paid = false, $payment_method = null, $send_by_email = false, $stripe_customer = '', $source = 'direct', $esBoceto = false): array
    {
        Logger::log('generateFSInvoice');

        $invoices = self::loadInvoiceFromStripe($id_invoice_stripe, $sk_stripe_index);

        Logger::log('vuelvo a generateFSInvoice');

        if (array_key_exists('status', $invoices) && $invoices['status'] === false) {
            Logger::log('Error al generar la factura: ' . $invoices['message']);
            Tools::log('stripe')->error('Error al generar la factura: ' . $invoices['message']);
            throw new Exception('Error al generar la factura: ' . $invoices['message']);
        }

        if (count($invoices['data']) === 0) {
            Logger::log('La factura de stripe ya ha sido generada');
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            throw new Exception('La factura de stripe ya ha sido generada');
        }

        $invoice = $invoices['data'][0];

        if (!empty($invoice->fs_idFactura)) {
            Logger::log('La factura de stripe ya está vinculada a la factura de FS ' . $invoice->fs_idFactura);
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            throw new Exception('La factura de stripe ya está vinculada a la factura de FS ' . $invoice->fs_idFactura);
        }

        $sk = StripeSettings::loadSkStripeByIndex($sk_stripe_index);
        $gateway = StripeGateway::byIndex($sk_stripe_index);
        $database = new DataBase();
        $database->beginTransaction();

        try {
            $build = FsInvoiceBuilder::build($invoice, $sk, $source, $esBoceto, $mark_as_paid, $payment_method);
            $invoiceFs = $build['model'];

            $gateway->updateInvoiceMetadata($id_invoice_stripe, ['fs_idFactura' => $invoiceFs->idfactura]);
            $result = $database->commit();
        } catch (Exception $e) {
            $database->rollback();
            Logger::log('No se ha podido crear la factura: ' . $e->getMessage());
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            StripeMailer::sendInvoiceError($id_invoice_stripe, serialize($e->getMessage()));
            throw new Exception($e->getMessage());
        }

        Logger::log('return ' . $invoiceFs->idfactura);

        if ($send_by_email === true && !$build['esClienteNoVinculado'] && !$build['usoProductoPorDefecto']) {
            Logger::log('Mandamos email');
            try {
                StripeMailer::sendInvoiceByEmail($invoiceFs->idfactura);
            } catch (Exception $e) {
                Logger::log('Error al mandar el email' . serialize($e->getMessage()));
                StripeMailer::sendInvoiceError('Error al mandar el email a la factura: ' . $invoiceFs->idfactura, serialize($e->getMessage()));
            }
        } else {
            Logger::log('No se manda email');
        }

        return ['status' => $result, 'code' => $invoiceFs->idfactura ?? null];
    }
}

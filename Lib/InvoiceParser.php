<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use Exception;
use FacturaScripts\Core\Model\Cliente as CoreCliente;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Tools;

/**
 * Convierte facturas de Stripe en objetos StripeInvoice.
 */
class InvoiceParser
{
    /**
     * @param \Stripe\Invoice[] $stripeInvoices
     * @return StripeInvoice[]
     * @throws Exception
     */
    public static function parse(array $stripeInvoices, int $skIndex, bool $withLines = true): array
    {
        Logger::log('InvoiceParser::parse');

        $gateway = StripeGateway::byIndex($skIndex);
        $parsedInvoices = [];

        foreach ($stripeInvoices as $stripeInvoice) {
            $customer = $gateway->retrieveCustomer($stripeInvoice->customer);

            if ($customer === null) {
                Logger::log('No se ha podido cargar el cliente de stripe correspondiente a la factura');
                Tools::log('stripe')->error('invoice id error: ' . $skIndex);
                throw new Exception('No se ha podido cargar el cliente de stripe correspondiente a la factura ' . $stripeInvoice->id);
            }

            if ($stripeInvoice->amount_due <= 0) {
                continue;
            }

            if (isset($stripeInvoice->metadata['fs_idFactura']) && $stripeInvoice->metadata['fs_idFactura'] !== '') {
                continue;
            }

            $parsedInvoices[] = self::parseInvoice($stripeInvoice, $customer, $skIndex, $withLines);
        }

        Logger::log('devolvemos las facturas parseadas');

        return $parsedInvoices;
    }

    /**
     * @throws Exception
     */
    private static function parseInvoice($stripeInvoice, $customer, int $skIndex, bool $withLines): StripeInvoice
    {
        Logger::log('Comprobamos si ya se ha pagado la factura o si ya ha sido descargada');

        $invoice = new StripeInvoice();
        $invoice->id = $stripeInvoice->id;
        $invoice->numero = $stripeInvoice->number;
        $invoice->status = $stripeInvoice->status;
        $invoice->customer_id = $stripeInvoice->customer;
        $invoice->customer_email = $stripeInvoice->customer_email;
        $invoice->starting_balance = $stripeInvoice->starting_balance;
        $invoice->fs_idFactura = $stripeInvoice->metadata['fs_idFactura'] ?? null;

        $fsCustomerId = $customer->metadata['fs_idFsCustomer'] ?? StripeSettings::getSetting('codcliente');
        $fsCustomer = new CoreCliente();
        $fsCustomer->load($fsCustomerId);

        if ($fsCustomerId !== null && $fsCustomer->exists()) {
            $invoice->fs_idFsCustomer = $fsCustomerId;
            $invoice->fs_customerName = $fsCustomer->nombre;
            Logger::log('cliente: ' . $fsCustomer->nombre);
        } else {
            Logger::log('cliente no encontrado en facturascripts');
        }

        $invoice->date = DateHelper::castTime($stripeInvoice->created);
        $invoice->amount = $stripeInvoice->amount_due / 100;

        if (isset($stripeInvoice->lines) && $withLines) {
            Logger::log('Hay lineas en la factura');
            $errors = [];

            foreach ($stripeInvoice->lines->data as $stripeLine) {
                $line = self::parseLine($stripeInvoice, $stripeLine, $fsCustomer, $skIndex, $errors);
                if ($line !== null) {
                    $invoice->lines[] = $line;
                }
            }

            Logger::log('Factura de stripe procesada correctamente');
            Logger::log('Errores: ' . count($errors));

            // el descuento es a nivel de factura
            $invoice->discount = self::calculateDiscount($stripeInvoice);

            if (count($errors) > 0) {
                Logger::log('errors: ' . serialize($errors));
                Tools::log('stripe')->error('invoice id error: ' . $stripeInvoice->id);
                throw new Exception(serialize($errors));
            }
        }

        return $invoice;
    }

    /**
     * @throws Exception
     */
    private static function parseLine($stripeInvoice, $stripeLine, CoreCliente $fsCustomer, int $skIndex, array &$errors): ?array
    {
        $periodStart = $stripeLine->period->start ?? null;
        $periodEnd = $stripeLine->period->end ?? null;
        $fsProductId = '';
        $tax = (object)[
            'codimpuesto' => null,
            'iva' => 0,
            'recargo' => 0,
        ];

        // El iva puede venir a nivel de factura o a nivel de linea. La prioridad es:
        // - Iva en linea
        // - Iva en factura
        // - Iva del artículo de FS
        $vatPercent = $stripeInvoice->tax_percent ?? null;
        $vatPercent = is_array($stripeLine->tax_rates) && count($stripeLine->tax_rates) > 0 && isset($stripeLine->tax_rates[0]['percentage'])
            ? $stripeLine->tax_rates[0]['percentage']
            : $vatPercent;

        if ($vatPercent === null && isset($stripeInvoice->default_tax_rates[0]->percentage)) {
            $vatPercent = $stripeInvoice->default_tax_rates[0]->percentage;
        }

        $vatIncluded = null;
        if (is_array($stripeLine->tax_amounts) && count($stripeLine->tax_amounts) > 0) {
            $vatIncluded = $stripeLine->tax_amounts[0]['inclusive'];
        }

        Logger::log('¿El iva está incluido?: ' . ($vatIncluded ? 'si' : 'no'));

        $priceProduct = $stripeLine->price->product ?? null;
        $pricingProduct = $stripeLine->pricing->price_details->product ?? '';

        if (($priceProduct !== null && $priceProduct !== '') || $pricingProduct !== '') {
            $stripeProductId = is_object($priceProduct) ? $priceProduct->id : ($priceProduct ?: $pricingProduct);
            $fsProductId = StripeProduct::getFsProductIdFromStripe($skIndex, $stripeProductId);

            if (strlen($fsProductId) === 0) {
                Logger::log('El producto de stripe no tiene correlación con el de FS');
                $errors[] = ['message' => 'El producto de stripe no tiene correlación con el de FS', 'data' => $stripeLine->description];
            } else {
                $product = new Producto();
                if (!$product->load($fsProductId)) {
                    Logger::log('El producto FS relacionado con el producto de stripe no existe: ' . $fsProductId);
                    $errors[] = ['message' => 'El producto FS relacionado con el producto de stripe no existe', 'data' => $fsProductId];
                } else {
                    if ($product->getTax() !== null) {
                        $tax = $product->getTax();
                    }

                    if ($vatPercent !== null) {
                        $tax->iva = $vatPercent;
                    }
                }
            }
        } else {
            $errors[] = ['message' => 'No se ha podido cargar el producto desde stripe', 'data' => $stripeLine];
        }

        $unitAmount = $stripeLine->amount / 100;
        Logger::log('Precio antes de impuestos ' . $unitAmount);

        if ($tax !== null && $fsCustomer->regimeniva === 'Exento') {
            if (method_exists($tax, 'load')) {
                $tax->load('IVA0');
            }
            Logger::log('Cliente exento de iva');

            if ($vatIncluded === null || $vatIncluded === false) {
                $unitAmount = $unitAmount * (1 + ($vatPercent / 100));
                Logger::log('Le sumamos el iva que viene de stripe: ' . $vatPercent);
            }
        } else {
            Logger::log('El cliente tiene iva');
        }

        if ($tax->iva !== 0 && ($vatIncluded === null || $vatIncluded)) {
            $unitAmount = $unitAmount / (1 + ($tax->iva / 100));
            Logger::log('Le restamos el iva que viene de stripe: ' . $tax->iva);
        }

        $decimals = Tools::settings('default', 'decimals') ?? 0;
        $lineAmount = round($unitAmount * $stripeLine->quantity, $decimals, PHP_ROUND_HALF_UP);
        $unitAmount = round($unitAmount * $stripeLine->quantity, $decimals, PHP_ROUND_HALF_UP);

        Logger::log('precio después de impuestos: ' . $lineAmount);
        Logger::log('unit precio después de impuestos: ' . $unitAmount);

        $description = is_object($priceProduct) && isset($priceProduct->name) ? $priceProduct->name : '';

        return [
            'codimpuesto' => $tax->codimpuesto,
            'iva' => $tax->iva,
            'recargo' => $tax->recargo,
            'unit_amount' => $unitAmount,
            'quantity' => $stripeLine->quantity,
            'fs_product_id' => $fsProductId,
            'amount' => $lineAmount,
            'description' => $description,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    private static function calculateDiscount($stripeInvoice): float
    {
        if (!isset($stripeInvoice->total_discount_amounts, $stripeInvoice->subtotal) || count($stripeInvoice->total_discount_amounts) === 0) {
            Logger::log('No hay descuentos o no hay subtotal en la factura.');
            return 0.0;
        }

        $discount = 0;
        foreach ($stripeInvoice->total_discount_amounts as $discountAmount) {
            $discount += round($discountAmount->amount / $stripeInvoice->subtotal * 100, 2, PHP_ROUND_HALF_UP);
        }

        Logger::log('Se aplica un descuento de ' . $discount);

        return (float)$discount;
    }
}

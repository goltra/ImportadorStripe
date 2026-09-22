<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use Exception;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Model\EstadoDocumento;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Model\Serie;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Accounting\InvoiceToAccounting;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;

/**
 * Crea la factura de FacturaScripts a partir de una StripeInvoice ya parseada.
 * No gestiona transacciones ni el envío de emails: de eso se encarga InvoiceImporter.
 */
class FsInvoiceBuilder
{
    /**
     * @return array{model: FacturaCliente, esClienteNoVinculado: bool, usoProductoPorDefecto: bool}
     * @throws Exception
     */
    public static function build(StripeInvoice $invoice, array $sk, string $source, bool $esBoceto, bool $markAsPaid, ?string $paymentMethod, string $stripeCustomer = ''): array
    {
        Logger::log('FsInvoiceBuilder::build');

        $invoiceFs = new FacturaCliente();

        $client = new Cliente();
        if (!$client->load($invoice->fs_idFsCustomer)) {
            Logger::log('El cliente no existe');
            throw new Exception('El cliente no existe');
        }

        $esClienteNoVinculado = $stripeCustomer !== '' && $client->codcliente === StripeSettings::getSetting('codcliente');

        if ($esClienteNoVinculado) {
            Logger::log('El cliente no está vinculado');
            $invoiceFs->observaciones = 'Cliente de Stripe no vinculado en Facturascripts (' . $invoice->customer_id . '). ';
        }

        $invoiceFs->setSubject($client);
        $invoiceFs->dtopor1 = $invoice->discount;

        // Agregamos la serie vinculada; en caso de que no haya, cogemos la del cliente.
        $serie = isset($sk['codserie']) && strlen($sk['codserie']) > 0 && $source === 'webhook' ? $sk['codserie'] : $client->codserie;
        Logger::log('source: ' . $source);
        Logger::log('serie usada: ' . $serie);

        $serieModel = new Serie();
        $serieModel->load($serie);
        Logger::log('serie devuelta al filtrar: ');
        Logger::log($serieModel);

        if ($serieModel->exists()) {
            $invoiceFs->codserie = $serie;
            Logger::log('Se asigna la serie ' . $serie);
        } else {
            Logger::log('serie da error.');
        }

        if (!$invoiceFs->save()) {
            Logger::log($invoiceFs);
            Logger::log('Ha ocurrido algún error mientras se creaba la factura.');
            throw new Exception('Ha ocurrido algún error mientras se creaba la factura.');
        }

        $usoProductoPorDefecto = false;

        foreach ($invoice->lines as $invoiceLine) {
            $usoProductoPorDefecto = $usoProductoPorDefecto || $invoiceLine['fs_product_id'] === StripeSettings::getSetting('codproducto');
            $line = self::buildLine($invoiceFs, $invoiceLine, $client);
            if (!$line) {
                throw new Exception('Ha ocurrido algún error mientras se creaban las lineas de la factura.');
            }
        }

        // Referencia del cliente de stripe en observaciones (la línea a coste 0 daba error con Verifactu).
        if (StripeSettings::getSetting('mostrarStripeCus') == 1 && !$esClienteNoVinculado) {
            $invoiceFs->observaciones = 'Referencia: ' . $invoice->customer_id . '. ';
        }

        if (!empty($invoice->starting_balance)) {
            self::buildStartingBalanceLine($invoiceFs, $invoice, $client);
        }

        // Recalculo los totales.
        $lines = $invoiceFs->getLines();
        Calculator::calculate($invoiceFs, $lines, true);

        $invoiceFs->numero2 = $invoice->numero;
        $invoiceFs->idestado = self::getEstadoId($esBoceto, $esClienteNoVinculado);

        if ($markAsPaid === true && $paymentMethod !== null) {
            $invoiceFs->codpago = $paymentMethod;
        }

        if (!$invoiceFs->save()) {
            Logger::log('Ha ocurrido algún error mientras se guardaba la factura.');
            throw new Exception('Ha ocurrido algún error mientras se guardaba la factura.');
        }

        if (!self::generateAccounting($invoiceFs)) {
            Logger::log('No se ha podido generar la factura porque hubo un error al generar el asiento contable');
            throw new Exception('No se ha podido generar la factura porque hubo un error al generar el asiento contable');
        }

        if ($markAsPaid === true && $paymentMethod !== null) {
            foreach ($invoiceFs->getReceipts() as $receipt) {
                $receipt->pagado = true;
                if (!$receipt->save()) {
                    Logger::log('No se ha podido generar la factura porque hubo un error al darla por pagada');
                    throw new Exception('No se ha podido generar la factura porque hubo un error al darla por pagada');
                }
            }
        }

        return [
            'model' => $invoiceFs,
            'esClienteNoVinculado' => $esClienteNoVinculado,
            'usoProductoPorDefecto' => $usoProductoPorDefecto,
        ];
    }

    private static function buildLine(FacturaCliente $invoiceFs, array $invoiceLine, Cliente $client)
    {
        Logger::log('linea stripe');

        $line = $invoiceFs->getNewLine();
        $line->idfactura = $invoiceFs->idfactura;
        $line->descripcion = $invoiceLine['description'];

        if ($invoiceLine['period_start']) {
            $line->descripcion .= ' desde ' . date('d-m-Y', $invoiceLine['period_start']);
        }

        if ($invoiceLine['period_end']) {
            $line->descripcion .= ' hasta ' . date('d-m-Y', $invoiceLine['period_end']);
        }

        if (empty($invoiceLine['fs_product_id'])) {
            Logger::log('No hay producto asignado');
            throw new Exception('No hay producto asignado');
        }

        $producto = new Producto();
        $producto->load($invoiceLine['fs_product_id']);
        Logger::log('Hay producto de fs vinculado. El producto es: ' . $producto->referencia);

        if ($invoiceLine['fs_product_id'] === StripeSettings::getSetting('codproducto')) {
            Logger::log('No hay producto de fs vinculado');
            $invoiceFs->observaciones .= 'Producto de Stripe no vinculado en Facturascripts (' . $invoiceLine['fs_product_id'] . '). ';
            $invoiceFs->save();
        }

        $line->idproducto = $invoiceLine['fs_product_id'];
        $line->referencia = $producto->referencia;
        $line->cantidad = $invoiceLine['quantity'];
        $line->pvpunitario = $invoiceLine['unit_amount'];
        $line->pvptotal = $invoiceLine['amount'];

        if ($client->regimeniva !== 'Exento') {
            $line->codimpuesto = $invoiceLine['codimpuesto'];
            $line->iva = $invoiceLine['iva'];
        }

        if (!$line->save()) {
            Logger::log($line);
            Logger::log('Ha ocurrido algún error mientras se creaban la lineas de la factura.');
            return null;
        }

        return $line;
    }

    private static function buildStartingBalanceLine(FacturaCliente $invoiceFs, StripeInvoice $invoice, Cliente $client): void
    {
        Logger::log('starting_balance: ' . $invoice->starting_balance);

        $line = $invoiceFs->getNewLine();
        $line->idfactura = $invoiceFs->idfactura;
        $line->descripcion = 'Descuento';
        $line->cantidad = 1;

        $pvp = $invoice->starting_balance / 100;
        $firstLine = $invoice->lines[0] ?? null;

        if ($firstLine !== null) {
            if (!empty($firstLine['fs_product_id'])) {
                $producto = new Producto();
                $producto->load($firstLine['fs_product_id']);
                $line->referencia = $producto->referencia;
            }

            if ($client->regimeniva !== 'Exento') {
                $line->codimpuesto = $firstLine['codimpuesto'];
                $line->iva = $firstLine['iva'];
                $pvp = $pvp / (1 + ($firstLine['iva'] / 100));
            }

            Logger::log('Se agrega un descuento de ' . $pvp . ' a la factura.');
        } else {
            Logger::log('No se ha aplicado el código ni el impuesto porque no hay linea de producto en la factura de stripe.');
        }

        $line->pvpunitario = $pvp;
        $line->pvptotal = $pvp;
        $line->save();
    }

    /**
     * @throws Exception
     */
    private static function getEstadoId(bool $esBoceto, bool $esClienteNoVinculado): int
    {
        $estadoLabel = 'Boceto';
        if (!$esBoceto) {
            $estadoLabel = StripeTransactionsQueue::canUseVerifactu() && !$esClienteNoVinculado ? 'Verifactu' : 'Emitida';
        }

        $estado = new EstadoDocumento();
        if (!$estado->loadWhere([Where::eq('tipodoc', 'FacturaCliente'), Where::eq('nombre', $estadoLabel)])) {
            Logger::log('El estado ' . $estadoLabel . ' no existe');
            throw new Exception('El estado ' . $estadoLabel . ' no existe');
        }

        return $estado->idestado;
    }

    private static function generateAccounting(FacturaCliente $invoice): bool
    {
        $generator = new InvoiceToAccounting();
        $generator->generate($invoice);

        Logger::log('Factura una vez generado el asiento contable. Si no hay idasiento, ha dado error interno.');
        Logger::log(serialize($invoice));

        return !empty($invoice->idasiento) && $invoice->save();
    }
}

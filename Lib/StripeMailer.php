<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use FacturaScripts\Core\Lib\Email\NewMail;
use FacturaScripts\Core\Lib\Export\PDFExport;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;

class StripeMailer
{
    public static function sendInvoiceError($invoice, string $error): void
    {
        NewMail::create()
            ->to(StripeSettings::getSetting('adminEmail'))
            ->subject('Error al generar factura en Facturascript')
            ->body('Se ha generado un error al crear la factura ' . $invoice . '. <br /> El error es: ' . $error)
            ->send();
    }

    public static function sendQueueError(string $objectId): void
    {
        $body = "Hola, \r\n se ha intentado procesar la factura $objectId y ha dado error. \r\n";

        NewMail::create()
            ->to(StripeSettings::getSetting('adminEmail'))
            ->cc(StripeSettings::getSetting('satEmail'))
            ->subject('Error al procesar la factura de stripe')
            ->body(nl2br($body))
            ->send();
    }

    /**
     * @param string[] $errorTransactionIds
     */
    public static function sendRemesaComplete(string $idRemesa, array $errorTransactionIds = []): void
    {
        $body = "La remesa se ha procesado completamente, por favor comprueba que está correcta. \r\n";

        if (count($errorTransactionIds) > 0) {
            $body .= "Errores:\r\n- Factura: " . implode("\r\n- Factura: ", $errorTransactionIds);
        }

        NewMail::create()
            ->to(StripeSettings::getSetting('adminEmail'))
            ->cc(StripeSettings::getSetting('satEmail'))
            ->subject('Remesa ' . $idRemesa . ' procesada')
            ->body(nl2br($body))
            ->send();
    }

    public static function sendWebhookError(string $error = ''): void
    {
        $body = "Hola, \r\n La llamada de stripe para agregar a la cola una factura ha dado error. \r\n";

        if ($error !== '') {
            $body .= $error;
        }

        NewMail::create()
            ->to(StripeSettings::getSetting('satEmail'))
            ->subject('Error al agregar a la cola la factura de stripe')
            ->body(nl2br($body))
            ->send();
    }

    public static function sendRemesaWebhookError(string $error = ''): void
    {
        $body = "Hola, \r\n La llamada de stripe para generar una remesa y agregar las facturas a la cola ha dado error. \r\n";

        if ($error !== '') {
            $body .= $error;
        }

        NewMail::create()
            ->to(StripeSettings::getSetting('satEmail'))
            ->subject('Error al generar la remesa de cobro de stripe')
            ->body(nl2br($body))
            ->send();
    }

    public static function sendRemesaCreated(int $numCargos, int $cargosCorrectos, array $errores, float $totalIngreso, string $idRemesa): void
    {
        $body = "Hola, \r\n Se ha creado la remesa $idRemesa de forma automática por un pago de stripe. "
            . "Y todas las líneas se han agregado a la cola para su procesamiento. \r\n";
        $body .= "Total del ingreso: $totalIngreso €\n";
        $body .= "Total cargos: $numCargos \r\n";
        $body .= "Num cargos agregados a la cola: $cargosCorrectos \r\n";

        if (count($errores) > 0) {
            $body .= "Errores:\r\n" . implode("\r\n", $errores);
        }

        NewMail::create()
            ->to(StripeSettings::getSetting('adminEmail'))
            ->subject('Nueva remesa de cobro de stripe agregada a la cola.')
            ->body(nl2br($body))
            ->send();
    }

    public static function sendUncollectible(string $stripeInvoiceId, string $fsInvoiceId, string $fsInvoiceCode): void
    {
        $stripeUrl = 'https://dashboard.stripe.com/invoices/' . $stripeInvoiceId;
        $fsUrl = Tools::siteUrl() . '/EditFacturaCliente?code=' . $fsInvoiceId;

        $body = 'Hola, <br /> La factura de stripe '
            . '<a href="' . $stripeUrl . '">' . $stripeInvoiceId . '</a>'
            . ' no se ha podido cobrar y había sido generada con el código '
            . '<a href="' . $fsUrl . '">' . $fsInvoiceCode . '</a>.';

        NewMail::create()
            ->to(StripeSettings::getSetting('adminEmail'))
            ->subject('No se ha podido cobrar la factura')
            ->body($body)
            ->send();
    }

    public static function sendInvoiceByEmail($code): void
    {
        $factura = new FacturaCliente();
        $factura->load($code);

        $cliente = new Cliente();
        $cliente->load($factura->codcliente);

        if (empty($cliente->email) || !filter_var($cliente->email, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->error('Se generará la factura pero no se puede enviar el email porque el cliente no tiene una dirección válida.');
            return;
        }

        $pdf = new PDFExport();
        $pdf->addBusinessDocPage($factura);

        $path = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR;
        $fileName = 'factura_' . $factura->codigo . '.pdf';

        if (file_put_contents($path . $fileName, $pdf->getDoc()) === false) {
            Tools::log()->error('Se generará la factura pero no se puede enviar el email porque hubo algún error al generar el fichero.');
            return;
        }

        $mail = NewMail::create()
            ->to(FS_DEBUG ? StripeSettings::getSetting('adminEmail') : $cliente->email)
            ->subject('Le enviamos su factura ' . $factura->codigo)
            ->body('Estimado cliente, le enviamos la factura correspondiente al servicio. Gracias por confiar en nosotros')
            ->addAttachment($path . $fileName, $fileName);

        if ($mail->send()) {
            $factura->femail = date('Y-m-d');
            $factura->save();
            Tools::log()->info('Correo enviado correctamente');
        } else {
            Tools::log()->info('Hubo algún error al enviar el correo');
        }

        if (file_exists($path . $fileName)) {
            unlink($path . $fileName);
        }
    }
}

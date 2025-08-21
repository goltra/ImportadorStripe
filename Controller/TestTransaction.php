<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use PHPMailer\PHPMailer\Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\StripeClient;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class TestTransaction extends Controller
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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->init();
    }

    public function init(){
        echo '<pre>';
//        $payoutId = 'po_1QhK6gHDuQaJAlOmouHWIs8M';
        $payoutId = 'po_1R1clUHDuQaJAlOmPOZRnWtO';
        $sk = 'sk_test_51ILOeaHDuQaJAlOmoxCwXO9mYqMKmXk6c9ByTDILdJ3vujXorxScbbyTNBrQeXb82oNeqq4UsioajKWiSaRMEGL700xoDW92tk';

        $stripe = new StripeClient($sk);

        //  Pido los datos del pago
        $payout = $stripe->payouts->retrieve($payoutId, []);

        //  Creo la remesa
        $remesa = new RemesaSEPA();

        //  TODO: revisar este nombre donde aparece y que quieren que se ponga aquí.
        $remesa->nombre = 'Pago Stripe Enero CJL';
        $remesa->descripcion = 'Pago Enero CJL';
        $remesa->fecha = date('Y-m-d H:i:s');
        $remesa->fechacargo  = date('Y-m-d', $payout['arrival_date']);

        // TODO: el código de cuenta tiene que ir automático.
        $remesa->codcuenta = 1;
//        $remesa->save();

        // Pido el balance transaction
        $balanceTransaction = $stripe->balanceTransactions->all([
            'payout' => $payoutId,
            'limit' => 10000,
        ]);

        $total = 0;
        $errors = [];


        foreach ($balanceTransaction->data as $transaction) {

            if (empty($transaction['source'])){
                continue;
            }

            if (!($invoice = $this->getInvoiceFromTransaction($stripe, $transaction['source'], $errors)))
                continue;


            $total += $invoice['amount_paid'] / 100;

            $facturaId = $invoice->metadata['fs_idFactura'];

            if (!isset($facturaId)){
                $errors[$invoice['id']] = 'La factura no está vinculada en stripe.';
                continue;
            }

            $reciboCliente = new ReciboCliente();

            $where = [new DataBaseWhere('idfactura', $facturaId), new DataBaseWhere('pagado', false)];
            $reciboCliente->loadFromCode('', $where);

            if (!$reciboCliente->idrecibo){
                $errors[$invoice['id']] = 'La factura no tiene un recibo o ya está pagado';
                continue;
            }

            if ($reciboCliente->idremesa){
                $errors[$invoice['id']] = 'La factura ya tiene una remesa asignada';
                continue;
            }

//            $reciboCliente->idremesa = $remesa->idremesa;
            $reciboCliente->pagado = true;
//            $reciboCliente->save();

            var_dump($invoice['id'] . ' - ' . $facturaId . ' - ' . $invoice['amount_paid'] / 100);
        }

        var_dump($errors);
        var_dump('total transferencia: '. $total);


        if (SettingStripeModel::getSetting('adminEmail'))
            $this->sendMail($errors, $total);


    }

    /**
     *  En un pago pueden venir varias procedencias de cobro.
     *  ch >> Es mediante cargo, que es la que nos interesa
     *  in >> Es una factura
     * @param StripeClient $stripe
     * @param string $source
     * @param array $errors
     * @return Invoice|null
     * @throws ApiErrorException
     */
    private function getInvoiceFromTransaction(StripeClient $stripe, string $source, array &$errors): ?Invoice
    {
        if (str_starts_with($source, 'ch_')) {
            $charge = $stripe->charges->retrieve($source, []);

            if (empty($charge->invoice)) {
                $errors[] = 'El cargo ' . $source . ' no tiene factura';
                return null;
            }

            return $stripe->invoices->retrieve($charge->invoice, []);
        }

        if (str_starts_with($source, 'in_')) {
            return $stripe->invoices->retrieve($source, []);
        }

        return null;
    }

    /**
     * Método que va a mandar un email
     * @param $errors
     * @param $total
     * @return void
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendMail($errors, $total): void
    {
        $subject = 'Nueva remesa de cobro de stripe creada';
        $body = "Total transferencia: " . $total . "€\n\n";

        if (count($errors) > 0)
            $body .= "Errores:\n" . implode("\n", $errors);

        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('adminEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }
}

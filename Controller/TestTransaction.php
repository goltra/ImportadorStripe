<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\ImportadorStripe\Model\BalanceTransactionModel;
use FacturaScripts\Plugins\ImportadorStripe\Model\PayoutModel;


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

//        Documentación de lo que hay que hacer aquí: https://github.com/goltra/ImportadorStripe/issues/3

        echo '<pre>';
        $payoutId = 'po_1QhK6gHDuQaJAlOmouHWIs8M';
        $sk = 'sk_test_51ILOeaHDuQaJAlOmoxCwXO9mYqMKmXk6c9ByTDILdJ3vujXorxScbbyTNBrQeXb82oNeqq4UsioajKWiSaRMEGL700xoDW92tk';

        $stripe = new \Stripe\StripeClient($sk);

        //  Pido los datos del pago
        $payout = $stripe->payouts->retrieve($payoutId, []);

        //  Creo la remesa
        $remesa = new RemesaSEPA();
        //  TODO: revisar este nombre donde aparece y que quieren que se ponga aquí.
        $remesa->nombre = 'Pago Enero CJL';
        $remesa->descripcion = 'Pago Enero CJL';
        $remesa->fecha = date('Y-m-d H:i:s');
        $remesa->fechacargo  = date('Y-m-d', $payout['arrival_date']);
        // todo, el código de cuenta tiene que ir automático.
        $remesa->codcuenta = 1;
        $remesa->save();

        // Pido el balance transaction
        $balanceTransaction = $stripe->balanceTransactions->all([
            'payout' => $payoutId,
            'limit' => 10000,
        ]);

        $total = 0;
        $errors = [];

        foreach ($balanceTransaction->data as $transaction) {

            if (empty($transaction['source']))
                continue;

            //  es un cargo
            if (strpos($transaction['source'], 'ch_') === 0) {
                $charge = $stripe->charges->retrieve($transaction['source'], []);

                if (!empty($charge->invoice)) {
                    $invoice = $stripe->invoices->retrieve($charge->invoice, []);
                    $total += $invoice['amount_paid'] / 100;

                    $facturaId = $invoice->metadata['fs_idFactura'];

                    if (!isset($facturaId)){
                        $errors[$invoice['id']] = 'La factura no está vinculada en stripe.';
                        continue;
                    }

                    $reciboCliente = new ReciboCliente();

                    $reciboCliente->loadFromCode('', [new DataBaseWhere('idfactura', $facturaId), new DataBaseWhere('pagado', false)]);

                    if (!$reciboCliente->idrecibo){
                        $errors[$invoice['id']] = 'La factura asignada no tiene recibos';
                        continue;
                    }


                    if ($reciboCliente->idremesa){
                        $errors[$invoice['id']] = 'La factura ya tiene una remesa asignada';
                        continue;
                    }

                    $reciboCliente->idremesa = $remesa->idremesa;
                    $reciboCliente->save();


                    var_dump($invoice['id'] . ' - ' .$facturaId. ' - ' . $invoice['amount_paid'] / 100);
                }
            }

            //  es un pago
//            if (strpos($transaction['source'], 'in_') === 0) {
//                var_dump($transaction['id']);
//
//                $invoice = $stripe->invoices->retrieve($transaction['source'], []);
//
//                var_dump($invoice['id'] . ' - ' . $invoice['amount_paid'] / 100);
//                $total += $invoice['amount_paid'] / 100;
//            }
        }

        var_dump($errors);
        var_dump('total transferencia: '. $total);



        //

    }
}

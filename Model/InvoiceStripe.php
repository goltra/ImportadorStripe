<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use Exception;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\Email\NewMail;
use FacturaScripts\Core\Lib\Export\PDFExport;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Model\Serie;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Accounting\InvoiceToAccounting;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InvoiceStripe
{
    public string $id;
    public string $numero;
    public string $date;
    public float $amount;
    public string $status;
    public string $customer_id;
    public string $customer_email;
    public int|null $fs_idFsCustomer = null;
    public int|null $fs_idFactura;
    public string $fs_customerName;
    public float $discount=0;
    public array $lines = [];


    public function __construct($data = [])
    {
    }

    static function loadSkStripe()
    {
        return SettingStripeModel::getSks();
    }

    /**
     * Devuelve las facturas de Stripe dentro del intervalo de fecha y a partir del id $start (en caso de recibirlo)
     * que han sido pagadas, tiene un importe > 0 y no tienen el metadato fs_idFactura
     * @param int $sk_stripe_index indice del array de sk de la cuenta de stripe que queremos consultar
     * @param null $start id de la factura desde la que comenzar a cargar
     * @param int $limit número máximo de registros que carga
     * @param int $initDate por defecto es 1 de Enero de 1990
     * @param int|null $endDate por defecto es la fecha actual
     * @return array
     * @throws Exception
     */
    static function loadInvoicesNotProcessed(int $sk_stripe_index, $start = null, int $limit = 5, int $initDate = 631200892, ?int $endDate = null): array
    {
        try {
            //fuerzo este valor
            $limit = 100000;
            // Cargo los las secretKeys de las cuentas de script que hay dadas de alta en los settings de fs

            $stripe_ids = self::loadSkStripe();
            // Cargo el index del sk pasado a la función
            $sk_stripe = $stripe_ids[$sk_stripe_index];
            if ($sk_stripe === '') {
                return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
            }
            $stripe_id = $sk_stripe['sk'];

            // Parametros para hacer filtro
            if ($endDate === null) { // seteo valor por defecto en caso que venga como null
                $endDate = time();
            }
            $params = ['status' => 'paid', 'limit' => $limit, 'created' => ['lte' => $endDate, 'gte' => $initDate]];

            $stripe = new \Stripe\StripeClient($stripe_id);
            Stripe::$apiVersion = '2020-08-27';
            $stripe_response = $stripe->invoices->all($params);

            $_data = [];

            array_filter($stripe_response->data, function ($inv) use (&$_data) {
                if ($inv->amount_paid > 0 && (!isset($inv->metadata['fs_idFactura']) || $inv->metadata['fs_idFactura'] == '')) {
                    $_data[] = $inv;
                }
            });

            $data = self::processInvoicesObject($_data, $sk_stripe_index);

            $response = [
                'status' => true,
                'data' => $data,
                'last' => $stripe_response->data[count($stripe_response->data) - 1]->id,
                'limit' => $limit,
                'has_more' => $stripe_response->has_more
            ];

            return $response;
        } catch (ApiErrorException $e) {
            self::sendMailError('(no hay factura)', $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    static function loadInvoiceFromStripe(string $id, int $sk_stripe_index): array
    {
        self::log('loadInvoiceFromStripe');
        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];

        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        self::log('id de la factura a descargar: '.$id);

        try {
            $stripe = new \Stripe\StripeClient($stripe_id);
            $invoices[] = $stripe->invoices->retrieve($id, [
                'expand' => ['lines.data.price.product'],
            ]); //guardamos en un array porque el método que genera el objeto lo tenemos definido así

            self::log('factura descargada');

            try {
                $res = self::processInvoicesObject($invoices, $sk_stripe_index);
            } catch (Exception $e) {
                self::log('Error al procesar la factura de stripe ' . serialize($e->getMessage()));
                self::sendMailError($id, serialize($e->getMessage()));
                return ['status' => false, 'message' => 'Error al procesar la factura de stripe ' . $e->getMessage()];
            }

            return ['status' => true, 'data' => $res];

        } catch (\Exception $ex) {
            self::log('Error al obtener la factura desde stripe ' . serialize($ex->getMessage()));
            self::sendMailError($id, serialize($ex->getMessage()));
            return ['status' => false, 'message' => 'Error al obtener la factura desde stripe ' . $ex->getMessage()];
        }
    }

    static public function setFsIdCustomer(string $stripe_customer_id, int $sk_stripe_index, string $fs_idFsCustomer): array
    {
        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        try {
            $stripe = new \Stripe\StripeClient($stripe_id);
            $customer = $stripe->customers->update($stripe_customer_id, [
                'metadata' => ['fs_idFsCustomer' => $fs_idFsCustomer]
            ]);
            return ['status' => true, 'data' => $customer];
        } catch (\Exception $ex) {
            return ['status' => false, 'message' => 'Error al obtener el cliente desde stripe ' . $ex->getMessage()];
        }
    }

    /**
     * Función que recibe un array de facturas de stripe y lo parsea para convertrlo en array de objetos de tipo
     * InvoiceStripe. Devuelve un array de InvoiceStripe
     * @param array $data
     * @param $sk_stripe_index
     * @param bool $withLines
     * @return array
     * @throws Exception
     */
    static private function processInvoicesObject(array $data, $sk_stripe_index, bool $withLines = true): array
    {
        self::log('processInvoicesObject');
        $res = [];
        $errors = [];

        foreach ($data as $inv) {
            //obtengo el cliente de stripe.
            $customer = self::getStripeClient($inv->customer, $sk_stripe_index);

            if ($customer === null) {
                self::log('cliente No se ha podido cargar el cliente de stripe correspondiente a la factura');
                Tools::log('stripe')->error('invoice id error: ' . $sk_stripe_index);
                throw new \Exception('No se ha podido cargar el cliente de stripe correspondiente a la factura ' . $inv->id);
            }

            self::log('Comprobamos si ya se ha pagado la factura o si ya ha sido descargada');

            if ($inv->amount_paid > 0 && (!isset($inv->metadata['fs_idFactura']) || $inv->metadata['fs_idFactura'] == '')) {
                $invoice = new InvoiceStripe();
                $invoice->id = $inv->id;
                $invoice->numero = $inv->number;
                $invoice->status = $inv->status;
                $invoice->customer_id = $inv->customer;
                $invoice->customer_email = $inv->customer_email;
                $invoice->fs_idFactura = $inv->metadata['fs_idFactura'] ?? null;
                $_fs_idCustomer = $customer->metadata['fs_idFsCustomer'] ?? SettingStripeModel::getSetting('codcliente');
                $fs_customer = new \FacturaScripts\Core\Model\Cliente();
                $fs_customer->load($_fs_idCustomer);

                if ($_fs_idCustomer !== null && $fs_customer->exists()) {
                    $invoice->fs_idFsCustomer = $_fs_idCustomer;
                    $invoice->fs_customerName = $fs_customer->nombre;
                    self::log('cliente: '.$fs_customer->nombre);
                }
                else{
                    self::log('cliente no encontrado en facturascripts');
                }

                $invoice->date = Helper::castTime($inv->created);
                $invoice->amount = $inv->amount_paid / 100;

                if (isset($inv->lines) && $withLines) {
                    self::log('Hay lineas en la factura');
                    foreach ($inv->lines->data as $l) {
                        $period_start = (isset($l->period->start)) ? $l->period->start : null;
                        $period_end = (isset($l->period->end)) ? $l->period->end : null;
                        $fs_product_id = '';
                        $tax = null;



                        // El iva puede venir a nivel de factura o a nivel de linea. La prioridad va a ser:
                        // - Iva en linea
                        // - Iva en factura
                        // - Iva del artículo de FS

                        $vat_perc = $inv->tax_percent!==null ? $inv->tax_percent : null; //Impuesto aplicado a factura
                        $vat_perc = ( is_array($l->tax_rates) && count($l->tax_rates)>0 && isset($l->tax_rates[0]['percentage']) ) ? $l->tax_rates[0]['percentage'] : $vat_perc; //Impuesto aplicado a linea

                        if ($vat_perc === null && isset($inv->default_tax_rates[0]->percentage))
                            $vat_perc = $inv->default_tax_rates[0]->percentage;

                        $vat_included = null;

                        if (is_array($l->tax_amounts) && count($l->tax_amounts) > 0)
                            $vat_included = $l->tax_amounts[0]['inclusive'];

                        self::log('¿El iva está incluido?: '.($vat_included ? 'si' : 'no'));

                        if (($l->price !== null && $l->price->product !== null && $l->price->product !== '') || $l->pricing->price_details->product !== '') {
                            $product_id = $l->price->product->id ?? $l->price->product ?? $l->pricing->price_details->product;
                            $fs_product_id = ProductModel::getFsProductIdFromStripe($sk_stripe_index, $product_id);


                            // Compruebo si hay correlación entre producto de stripe y fs
                            if (strlen($fs_product_id) === 0) {
                                self::log('El producto de stripe no tiene correlación con el de FS');
                                $errors[] = ['message' => 'El producto de stripe no tiene correlación con el de FS', 'data' => $l->price->product . '-' . $l->description];
                            } else {
                                // Comprueba si el fs_product_id existe en fs
                                $product = new Producto();
                                if (!$product->load($fs_product_id)){
                                    self::log('El producto FS relacionado con el producto de stripe no existe');
                                    $errors[] = ['message' => 'El producto FS relacionado con el producto de stripe no existe', 'data' => $fs_product_id];
                                }
                                else {
                                    $tax = $product->getTax();

                                    if ($vat_perc !== null)
                                        $tax->iva = $vat_perc;
                                }

                            }
                        } else {
                            $errors[] = ['message' => 'No se ha podido cargar el producto desde stripe', 'data' => $l];
                        }

                        // Obtengo el precio de la linea
                        $unit_amount = $l->amount / 100;

                        self::log('Precio antes de impuestos '.$unit_amount);


//                        // Aplico los descuentos que trae la linea, siempre van a ser porcentaje. Por tanto si el descuento es una cantidad fija, se calcula el porcentaje respecto al precio final.
                        if(isset($inv->total_discount_amounts) && isset($inv->subtotal) && count($inv->total_discount_amounts) > 0) {
                            $discount = 0;
                            foreach ($inv->total_discount_amounts as $d){
                                $discount += round($d->amount / $inv->subtotal * 100, 2, PHP_ROUND_HALF_UP);
                            }
//
                            $invoice->discount = $discount;
                            self::log('Se aplica un descuento de '.$discount);
                        }
                        else{
                            self::log('No hay descuentos o no hay subtotal en la factura.');
                        }


                        if($tax !== null && $fs_customer->regimeniva === 'Exento'){
                            $tax->load('IVA0');
                            self::log('Cliente exento de iva');

                            if($vat_included === null || $vat_included === false){
                                $unit_amount = $unit_amount * (1 + ($vat_perc / 100));
                                self::log('Le sumamos el iva que viene de stripe: '.$vat_perc);
                            }
                        }
                        else
                            self::log('El cliente tiene iva');


                        if ($tax->iva !== 0 && ($vat_included === null || $vat_included )){
                            $unit_amount = $unit_amount / (1 + ($tax->iva / 100));
                            self::log('Le restamos el iva que viene de stripe: '.$tax->iva);
                        }


                        // Multiplico por las unidades para obtener el total de la linea
                        $amount = round($unit_amount * $l->quantity, Tools::settings('default', 'decimals') ?? 0, PHP_ROUND_HALF_UP);
                        $unit_amount = round($unit_amount * $l->quantity, Tools::settings('default', 'decimals') ?? 0, PHP_ROUND_HALF_UP);

                        self::log('precio después de impuestos: '.$amount);
                        self::log('unit precio después de impuestos: '.$unit_amount);

                        // Asigno a cada variable el valor que debe tener en la linea
                        // todo: aquí tenemos una historia porque esto se carga siempre y no siempre viene el objeto del producto (porque no es necesario y no se en que caso es donde se pide sin él), entonces compruebo si es objeto y si viene esa propiedad o si no la punto a nada. revisar de donde se pide sin el producto.
                        $invoice->lines[] = ['codimpuesto' => $tax->codimpuesto, 'iva' => $tax->iva, 'recargo' => $tax->recargo, 'unit_amount' => $unit_amount, 'quantity' => $l->quantity, 'fs_product_id' => $fs_product_id, 'amount' => $amount, 'description' => is_object($l->price->product) && isset($l->price->product->name) ? $l->price->product->name : '', 'period_start' => $period_start, 'period_end' => $period_end];
                    }

                    self::log('Factura de stripe procesada correctamente');
                    self::log('Errores: '.count($errors));
                }

                if (count($errors) == 0)
                    $res[] = $invoice;
                else{
                    self::log('errors: '.serialize($errors));
                    Tools::log('stripe')->error('invoice id error: ' . $inv->id);
                    throw new Exception(serialize($errors));
                }
            }
        }

        self::log('devolvemos la factura');

        return $res;
    }

    /**
     * Devuelve el cliente de stripe que corresponde con el $customer_id recibido
     * @param $customer_id
     * @param $sk_stripe_index
     * @return mixed || null
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \PHPMailer\PHPMailer\Exception
     */
    static private function getStripeClient($customer_id, $sk_stripe_index): mixed
    {
        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        try {
            $stripe = new \Stripe\StripeClient($stripe_id);
            return $stripe->customers->retrieve($customer_id);
        } catch (\Exception $ex) {
            self::sendMailError($customer_id, serialize($ex->getMessage()));
            return null;
        }
    }

    /**
     * Funcion que crea una nueva factura en FS.
     * Crea la factura y deuvelve un array con las propiedades bool status y integer code
     * return Array
     * @throws Exception
     */
    static public function generateFSInvoice($id_invoice_stripe, $sk_stripe_index, $mark_as_paid = false, $payment_method = null, $send_by_email = false, $stripe_customer = '', $source = 'direct'): array
    {
        self::log('generateFSInvoice');
        $invoices = self::loadInvoiceFromStripe($id_invoice_stripe, $sk_stripe_index);

        self::log('vuelvo a generateFSInvoice');

        // COMPROBAMOS QUE LA FACTURA DE STRIPE SE HA CARGADO CORRECTAMENTE
        if (count($invoices['data']) === 0) {
            self::log('La factura de stripe ya ha sido generada');
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            throw new Exception('La factura de stripe ya ha sido generada');
        }

        $invoice = $invoices['data'][0];

        // COMPROBAMOS QUE LA FACTURA DE STRIPE NO ESTE VINCULADA YA A UNA FACTURA DE FS
        if (isset($invoice->fs_idFactura) && ($invoice->fs_idFactura === null || $invoice->fs_idFactura !== '')) {
            self::log('La factura de stripe ya está vinculada a la factura de FS ' . $invoice->fs_idFactura);
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            throw new Exception('La factura de stripe ya está vinculada a la factura de FS ' . $invoice->fs_idFactura);
        }


        // SI HA PASADO LAS COMPROBACIONES ENTONCES CREAMOS LA FACTURA DE FS.
        // INICIO UNA TRASACCIÓN
        $database = new DataBase();
        $database->beginTransaction();

        $invoiceFs = new FacturaCliente();

        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];

        /**
         * COMPROBAMOS QUE EL CLIENTE ASOCIADO EN FS EXISTE.
         * EN CASO DE QUE NO ESTÉ COGEMOS AL POR DEFECTO
         */

        $client = new Cliente();
        $client->load($invoice->fs_idFsCustomer);

        if ($stripe_customer !== '' && $client->codcliente === SettingStripeModel::getSetting('codcliente')){
            self::log('El cliente no está vinculado');
            $invoiceFs->observaciones = 'Cliente de Stripe no vinculado en Facturascripts ('.$stripe_customer.')';
        }

        $invoiceFs->setSubject($client);
        $invoiceFs->dtopor1 = $invoice->discount;

//        Agregamos la serie vinculada, en caso de que no haya, cogemos la del cliente.
        $default_serie = new Serie();
        $serie = isset($sk_stripe['codserie']) && strlen($sk_stripe['codserie']) > 0 && $source == 'webhook' ? $sk_stripe['codserie'] : $client->codserie;
        self::log('source: '.$source);
        self::log('serie usada: '.$serie);
        $default_serie->load($serie);

        self::log('serie devuelta al filtrar: ');
        self::log($default_serie);

        if ($default_serie->exists()){
            $invoiceFs->codserie = $serie;
            self::log('Se asigna la serie '.$serie);
        }
        else
            self::log('serie da error.');

        // Si se crea la factura, entonces creo las lineas.
        if ($invoiceFs->save()) {
            foreach ($invoice->lines as $l) {

                self::log('linea stripe');
                /** \FacturaScripts\Core\Model\LineaFacturaCliente $line */
                $line = $invoiceFs->getNewLine();

                $line->idfactura = $invoiceFs->idfactura;

                $line->descripcion = $l['description'];

                if ($l['period_start']) {
                    $line->descripcion = $line->descripcion . ' desde ' . date('d-m-Y', $l['period_start']);
                }

                if ($l['period_end']) {
                    $line->descripcion = $line->descripcion . ' hasta ' . date('d-m-Y', $l['period_end']);
                }

                /*
                 * Seleccionamos el producto que esté vinculado
                 * En caso de que no esté cogemos el que esté asignado por defecto
                 */
                if ($l['fs_product_id'] !== null && $l['fs_product_id'] !== '') {
                    $producto = new Producto();
                    $producto->load($l['fs_product_id']);
                    self::log('Hay producto de fs vinculado. El producto es: '.$producto->referencia);
                }
                else{
                    self::log('No hay producto asignado');
                    $database->rollback();
                    throw new Exception('No hay producto asignado');
                }


                if ($l['fs_product_id'] === SettingStripeModel::getSetting('codproducto')){
                    self::log('No hay producto de fs vinculado');

                    $invoiceFs->observaciones = 'Producto de Stripe no vinculado en Facturascripts ('.$l['fs_product_id'].')';
                    $invoiceFs->save();
                }

                $productCode = $producto->referencia;
                $line->idproducto = $l['fs_product_id'];
                $line->referencia = $productCode;
                $line->cantidad = $l['quantity'];

                $line->pvpunitario = $l['unit_amount'];
                $line->pvptotal = $l['amount'];
                if ($client->regimeniva !== 'Exento') {
                    $line->codimpuesto = $l['codimpuesto'];
                    $line->iva = $l['iva'];
                }

                if (!$line->save()) {
                    self::log('Ha ocurrido algún error mientras se creaban la lineas de la factura.');
                    self::log($line);
                    $database->rollback();
                    throw new Exception('Ha ocurrido algún error mientras se creaban la lineas de la factura.');
                }
            }

            //  Agrego una nueva línea sin coste con la referencia del cliente de stripe
            if (SettingStripeModel::getSetting('mostrarStripeCus') == 1){
                $line = $invoiceFs->getNewLine();
                $line->idfactura = $invoiceFs->idfactura;
                $line->descripcion = 'Referencia: '.$invoice->customer_id;
                $line->codimpuesto = null;
                $line->iva = 0;
                $line->save();
            }

        } else {
            self::log($invoiceFs);
            self::log('Ha ocurrido algun error mientras se creaba la factura.');
            $database->rollback();
            throw new Exception('Ha ocurrido algun error mientras se creaba la factura.');
        }

        // recalculo los totales
        $lines = $invoiceFs->getLines();
        Calculator::calculate($invoiceFs, $lines, true);

        // asigno al numero2 el numero de factura de stripe
        $invoiceFs->numero2 = $invoice->numero;
        // se marca como emitida
        $invoiceFs->idestado = 11;

        if ($mark_as_paid === true && $payment_method !== null) $invoiceFs->codpago = $payment_method;

        $invoiceFs->save();
        //Genero el asiento contable
        if (!self::generateAccounting($invoiceFs)) {
            self::log('No se ha podido generar la factura porque hubo un error al generar el asiento contable');
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            $database->rollback();
            throw new Exception('No se ha podido generar la factura porque hubo un error al generar el asiento contable');
        }

        if ($mark_as_paid === true && $payment_method !== null) {
            foreach ($invoiceFs->getReceipts() as $receipt) {
                $receipt->pagado = true;
                if (!$receipt->save()) {
                    $database->rollback();
                    self::log('No se ha podido generar la factura porque hubo un error al darla por pagada');
                    Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
                    throw new Exception('No se ha podido generar la factura porque hubo un error al darla por pagada');
                }
            }
        }
        // Inserto metadato en Stripe
        try {
            self::setFsIdToStripeInvoice($id_invoice_stripe, $invoiceFs->idfactura, $sk_stripe_index);
        } catch (Exception $ex) {
            $database->rollback();
            self::log('No se ha podido crear la factura porque ha fallado al actualizar el documento de stripe');
            self::sendMailError($invoice->fs_idFactura, serialize($ex->getMessage()));
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            throw new Exception('No se ha podido crear la factura porque ha fallado al actualizar el documento de stripe');
        }
        // Si todo ha ido bien hago un commit
        $result = $database->commit();

        self::log('return '.$invoiceFs->idfactura);

        if ($send_by_email === true && $client->codcliente !== SettingStripeModel::getSetting('codcliente') && $l['fs_product_id'] !== SettingStripeModel::getSetting('codproducto')){
            self::log('Mandamos email');
            try {
                self::exportAndSendEmail($invoiceFs->idfactura);
            }
            catch (Exception $ex){
                self::log('Error al mandar el email'.serialize($ex->getMessage()));
                self::sendMailError('Error al mandar el email a la factura: '.$invoice->fs_idFactura, serialize($ex->getMessage()));
            }
        }
        else{
            self::log('No se manda email');
        }

        return ['status' => $result, 'code' => $invoiceFs->idfactura ?? null];
    }

    static private function generateAccounting($invoice): bool
    {
        $generator = new InvoiceToAccounting();
        $generator->generate($invoice);

        self::log('Factura una vez generado el asiento contable. Si no hay idasiento, quiere decir que ha dado error interno y no se ha generado el asiento.');
        self::log(serialize($invoice));
        if (empty($invoice->idasiento) || !$invoice->save()) {
            return false;
        }
        return true;
    }

    /**
     * @throws Exception
     */
    static private function setFsIdToStripeInvoice($id_invoice_stripe, $fs_idFactura, $sk_stripe_index): void
    {
        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return;
        }
        $stripe_id = $sk_stripe['sk'];
        try {
            $stripe = new \Stripe\StripeClient($stripe_id);
            $stripe->invoices->update(
                $id_invoice_stripe,
                ['metadata' => ['fs_idFactura' => $fs_idFactura]]);
        } catch (Exception $ex) {
            Tools::log('stripe')->error('invoice id error: ' . $id_invoice_stripe);
            self::sendMailError($id_invoice_stripe, serialize($ex->getMessage()));
            throw new Exception('Error al vincular la factura de FS a la de Stripe ' . $ex->getMessage());
        }
    }


    static function log($valor, $flag = 'invoice'): void
    {
        switch ($flag) {
            case 'invoice':
                $dir = 'invoice-log.txt';
                break;
            case 'remesa':
                $dir = 'remesa-sepa-log.txt';
                break;
            default:
                $dir = 'stripe-log.txt';
                break;
        }

        $fecha = date('d-m-Y H:i:s');

        if (is_object($valor))
            $valor = serialize($valor);

        if(is_array($valor))
            $valor = serialize($valor);

        $file = fopen($dir, "a");

        fwrite($file, $fecha . ' - ' . $valor . PHP_EOL);
        fclose($file);
    }

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    static function sendMailError($factura, $error): void
    {
        $mail = new NewMail();
        $mail->to(SettingStripeModel::getSetting('adminEmail'));
        $mail->title = 'Error al generar factura en Facturascript';
        $mail->text = 'Se ha generado un error al crear la factura '.$factura.'. <br /> El error es: '.$error;
        $mail->send();
    }

    /**
     * Envía la factura por email
     * @param $code
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    static function exportAndSendEmail($code): void
    {
        $factura = new \FacturaScripts\Core\Model\FacturaCliente();
        $factura->load($code);
        $cliente = new \FacturaScripts\Core\Model\Cliente();
        $cliente->load($factura->codcliente);
        if ($cliente->email === null || strlen($cliente->email) == 0 && !filter_var($cliente->email, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->error('Se generará la factura pero no se puede enviar el email porque el cliente no tiene puesta una dirección.');
        } else {
            $pdf = new PDFExport();
            $pdf->addBusinessDocPage($factura);
            $path = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR;
            $fileName = 'factura_' . $factura->codigo . '.pdf';
            // TODO: Borrar fichero una vez enviado
            if (file_put_contents($path . $fileName, $pdf->getDoc())) {
                $mail = new NewMail();

                if( FS_DEBUG )
                    $mail->to(SettingStripeModel::getSetting('adminEmail'));
                else
                    $mail->to($cliente->email);

                $mail->title = 'Le enviamos su factura ' . $factura->codigo;
                $mail->text = 'Estimado cliente, le enviamos la factura correspondiente al servicio. Gracias por confiar en nosotros';
                $mail->addAttachment($path . $fileName, $fileName);
//                $mail->fromNick = $user->nick;
                if ($mail->send()) {
                    $factura->femail = date('Y-m-d');
                    $factura->save();
                    Tools::log()->info('Correo enviado correctamente');

                } else {
                    Tools::log()->info('Hubo algún error al enviar el correo');
                }
                unlink($path . $fileName);
            } else {
                Tools::log()->error('Se generará la factura pero no se puede enviar el email porque hubo algún error al generar el fichero.');
            }
        }
    }
}

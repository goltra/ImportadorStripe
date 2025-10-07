<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Dinamic\Model\InvoiceStripe;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA as RemesaSEPAAlias;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\StripeClient;

class StripeTransactionsQueue extends ModelClass
{
    use ModelTrait;

    public $id;
    public $event; // Evento (payout, invoice)
    public $object_id; // id del objecto del evento (po_xxx, inv_xxx)
    public $object_date; // fecha en la que se produjo el objecto del evento
    public $transaction_type; // tipo de transacción en stripe (cargo, invoice, transaction, etc.)
    public $transaction_id; // id de la transacción en stripe, el que campo se va a tener en cuenta para procesar la cola
    public $destination; // tipo de destino en FS (factura, remesa, etc.)
    public $destination_id; // id del destino
    public $status; // estado en la cola (Pendiente, Ok, error)
    public $error_type; // tipos de error que puede dar en la cola
    public $created_at; // fecha de creación


    /**
     * Origen de los datos de la cola.
     * Ahora mismo tenemos dos, cuando se realiza un payout y cuando se cobra una factura en stripe
     */
    CONST EVENT_PAYOUT_PAID = 1;
    CONST EVENT_INVOICE_PAYMENT_SUCCEEDED = 2;


    /**
     * Tipo de transacción que vamos a proccesar
     */
    CONST TRANSACTION_TYPE_CHARGE = 1;
    CONST TRANSACTION_TYPE_INVOICE = 2;


    /**
     * Tipo de destino donde vamos a procesar los datos
     */
    CONST DESTINATION_REMESA = 1;
    CONST DESTINATION_INVOICE = 2;

    /**
     * Estado la linea en la cola
     */
    CONST STATUS_PENDING = 0; // Pendiente a ser procesado
    CONST STATUS_SUCCESS = 1; // Procesado correctamente
    CONST STATUS_ERROR = 2; // Ha dado error al ser procesado.


    public function clear(): void
    {
        parent::clear();
        $this->status = self::STATUS_PENDING;
        $this->created_at = date('Y-m-d H:i:s');
    }

    /**
     * La columna primaria del modelo.
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Nombre de la tabla
     */
    public static function tableName(): string
    {
        return 'stripe_transactions_queue';
    }

    /**
     * Método para conseguir líneas pendientes de procesar
     */
    public static function pendientes(): array
    {
        return self::findWhere([ self::where('status', null) ]);
    }

    /**
     * Método para obtener todas las líneas de un payout_id
     */
    public static function linesOfPayout(string $payout_id): array
    {
        return self::findWhere([ self::where('payout_id', $payout_id) ], ['id' => 'ASC']);
    }


    public static function processQueue()
    {
        /**
         * Método al que llama el cron
         * TODO Flujo:
         * Cojo 50 lineas cuyo status = null
         * dependiendo del tipo de transacción voy llamando a stripe hasta llegar al invoice
         * Si tengo el id de la factura de FS, asigno la factura a la remesa
         * Actualizo el estado en la tabla
         * Continuo con el siguiente
         * ...
         * Una vez terminado este flujo, compruebo si me quedan más por procesar
         * En caso que no queden, mando un email avisando que la remesa está terminada y mando un status de como está todo.
         */
    }



    private function niIdeaAun()
    {
//        if (!($invoice = $this->getInvoiceFromTransaction($stripe, $transaction['source'], $errors))){
//            InvoiceStripe::log('El cargo no tiene factura. ', 'remesa');
//            continue;
//        }
//
//        $facturaId = $invoice->metadata['fs_idFactura'];
//
//        if (!isset($facturaId)){
//            InvoiceStripe::log('La factura ' . $facturaId. ' no está vinculada en stripe.', 'remesa');
//            $errors[$invoice['id']] = '- La factura ' . $facturaId. ' no está vinculada en stripe.';
//            continue;
//        }
//
//        $reciboCliente = new ReciboCliente();
//
//        $where = [new DataBaseWhere('idfactura', $facturaId), new DataBaseWhere('pagado', false)];
//        $reciboCliente->loadFromCode('', $where);
//
//        if (!$reciboCliente->idrecibo){
//            InvoiceStripe::log('La factura ' . $facturaId. ' no tiene un recibo o ya está pagado', 'remesa');
//            $errors[$invoice['id']] = '- La factura ' . $facturaId. ' no tiene un recibo o ya está pagado';
//            continue;
//        }
//
//        if ($reciboCliente->idremesa){
//            InvoiceStripe::log('La factura ' . $facturaId. ' ya tiene una remesa asignada', 'remesa');
//            $errors[$invoice['id']] = '- La factura ' . $facturaId. ' ya tiene una remesa asignada';
//            continue;
//        }
//
//        $reciboCliente->idremesa = $remesa->idremesa;
//
//        if ($reciboCliente->save()){
//            InvoiceStripe::log('Se genera linea de remesa con la factura:  ' . $facturaId, 'remesa');
//        }




        //  No va aquí, son trozos de código que me harán falta cuando lo monte todo

        // Cambio el estado de la remesa
//        $remesa->estado = RemesaSEPAAlias::STATUS_REVIEW;

        // Calculamos los totales de la remesa
//        $remesa->updateTotal();
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
     * Comprueba si existe una fila con el mismo objectId.
     *
     * @param string $objectId
     * @param int $event
     * @return bool
     */
    public static function existsObjectId(string $objectId, int $event): bool
    {
        $model = new self();
        $model->object_id = $objectId;
        $model->event = $event;
        return $model->exists();
    }
}

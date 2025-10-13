<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
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
    CONST EVENT_PAYOUT_PAID = 'Pago';
    CONST EVENT_INVOICE_PAYMENT_SUCCEEDED = 'Suscripción';


    static $eventOptions = [
        self::EVENT_PAYOUT_PAID => self::EVENT_PAYOUT_PAID,
        self::EVENT_INVOICE_PAYMENT_SUCCEEDED => self::EVENT_INVOICE_PAYMENT_SUCCEEDED,
    ];


    /**
     * Tipo de transacción que vamos a proccesar
     */
    CONST TRANSACTION_TYPE_CHARGE = 'Cargo';
    CONST TRANSACTION_TYPE_PAYMENT_INTENT = 'Payment intent';

    static $tansactionTypeOptions = [
        self::TRANSACTION_TYPE_CHARGE => self::TRANSACTION_TYPE_CHARGE,
        self::TRANSACTION_TYPE_PAYMENT_INTENT => self::TRANSACTION_TYPE_PAYMENT_INTENT,
    ];


    /**
     * Tipo de destino donde vamos a procesar los datos
     */
    CONST DESTINATION_REMESA = 'Remesa';
    CONST DESTINATION_INVOICE = 'Factura';

    static $destinoOptions = [
        self::DESTINATION_REMESA => self::DESTINATION_REMESA,
        self::DESTINATION_INVOICE => self::DESTINATION_INVOICE,
    ];

    /**
     * Estado la linea en la cola
     */
    CONST STATUS_PENDING = 'Pendiente'; // Pendiente a ser procesado
    CONST STATUS_SUCCESS = 'Procesado'; // Procesado correctamente
    CONST STATUS_ERROR = 'Error'; // Ha dado error al ser procesado.

    static $statusOptions = [
        self::STATUS_PENDING => self::STATUS_PENDING,
        self::STATUS_SUCCESS => self::STATUS_SUCCESS,
        self::STATUS_ERROR => self::STATUS_ERROR,
    ];

//    public static function getStatusValues(int $status): string
//    {
//        return self::$statusOptions[$status] ?? '';
//    }

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
    static function getInvoiceFromTransaction(StripeClient $stripe, string $source, array &$errors): ?Invoice
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
     * @param string $event
     * @return bool
     */
    public static function existsObjectId(string $objectId, string $event): bool
    {
        return static::count([
                new DataBaseWhere('object_id', $objectId),
                new DataBaseWhere('event', $event)
            ]) > 0;
    }


    static function setStripeTransaction(
        $event,
        $object_id,
        $object_date,
        $transaction_type,
        $transaction_id,
        $destination,
        $destination_id,
    ) {
        $model = new StripeTransactionsQueue();
        $model->event = $event;
        $model->object_id = $object_id;
        $model->object_date = $object_date;
        $model->transaction_type = $transaction_type;
        $model->transaction_id = $transaction_id;
        $model->destination = $destination;
        $model->destination_id = $destination_id;
        return $model->save();
    }

}

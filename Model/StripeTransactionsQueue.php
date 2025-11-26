<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Internal\Plugin;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\StripeClient;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class StripeTransactionsQueue extends ModelClass
{
    use ModelTrait;

    public int|null $id;
    public string|null $stripe_account; // cuenta de stripe
    public string|null $event; // Evento (payout, invoice)
    public string|null $object_id; // id del objecto del evento (po_xxx, inv_xxx)
    public string|null $object_date; // fecha en la que se produjo el objecto del evento
    public string|null $transaction_type; // tipo de transacción en stripe (cargo, invoice, transaction, etc.)
    public string|null $transaction_id; // id de la transacción en stripe, el que campo se va a tener en cuenta para procesar la cola
    public string|null $destination; // tipo de destino en FS (factura, remesa, etc.)
    public string|null $destination_id; // id del destino
    public string|null $status; // estado en la cola (Pendiente, Ok, error)
    public string|null $error_type; // tipos de error que puede dar en la cola
    public string|null $created_at; // fecha de creación

    /**
     * Origen de los datos de la cola.
     * Ahora mismo tenemos dos, cuando se realiza un payout y cuando se cobra una factura en stripe
     */
    CONST EVENT_PAYOUT_PAID = 'Pago';
    CONST EVENT_INVOICE_PAYMENT_SUCCEEDED = 'Suscripcion';

    static array $eventOptions = [
        self::EVENT_PAYOUT_PAID => self::EVENT_PAYOUT_PAID,
        self::EVENT_INVOICE_PAYMENT_SUCCEEDED => self::EVENT_INVOICE_PAYMENT_SUCCEEDED,
    ];


    /**
     * Tipo de transacción que vamos a proccesar
     */
    CONST TRANSACTION_TYPE_CHARGE = 'Cargo';
    CONST TRANSACTION_TYPE_PAYMENT_INTENT = 'Payment intent';
    CONST TRANSACTION_TYPE_INVOICE = 'Factura';

    static array $tansactionTypeOptions = [
        self::TRANSACTION_TYPE_CHARGE => self::TRANSACTION_TYPE_CHARGE,
        self::TRANSACTION_TYPE_PAYMENT_INTENT => self::TRANSACTION_TYPE_PAYMENT_INTENT,
        self:: TRANSACTION_TYPE_INVOICE => self:: TRANSACTION_TYPE_INVOICE
    ];


    /**
     * Tipo de destino donde vamos a procesar los datos
     */
    CONST DESTINATION_REMESA = 'Remesa';
    CONST DESTINATION_INVOICE = 'Factura';
    const DESTINATION_CUSTOMER = 'Cliente';

    static array $destinoOptions = [
        self::DESTINATION_REMESA => self::DESTINATION_REMESA,
        self::DESTINATION_INVOICE => self::DESTINATION_INVOICE,
    ];

    /**
     * Estado la linea en la cola
     */
    CONST STATUS_PENDING = 'Pendiente'; // Pendiente a ser procesado
    CONST STATUS_SUCCESS = 'Procesado'; // Procesado correctamente
    CONST STATUS_ERROR = 'Error'; // Ha dado error al ser procesado.

    static array $statusOptions = [
        self::STATUS_PENDING => self::STATUS_PENDING,
        self::STATUS_SUCCESS => self::STATUS_SUCCESS,
        self::STATUS_ERROR => self::STATUS_ERROR,
    ];


    CONST ERROR_TYPE_NO_EVENT = 'Evento no reconocido';
    CONST ERROR_TYPE_NOT_GENERATE_INVOICE = 'Factura no generada';
    CONST ERROR_TYPE_RECIBO_PAGADO = 'Recibo pagado';
    CONST ERROR_TYPE_FACTURA_NO_VINCULADA = 'Factura no vinculada';
    CONST ERROR_TYPE_ASIGNADO_OTRA_REMESA = 'Asignado otra remesa';



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
     *
     * @param int $limit número máximo de registros a devolver
     * @return StripeTransactionsQueue[]
     */
    public static function getPendingTransactions(int $limit = 5): array
    {
        $where = [ new DataBaseWhere('status', self::STATUS_PENDING) ];
        return self::all(
            $where,
            [],
            0,
            $limit
        );
    }

    /**
     * @return void
     */
    static function processQueue(): void
    {
        $data = self::getPendingTransactions();

        foreach ($data as $d) {
            $d->processQueueRow();
        }
    }

    /**
     * @return void
     */
    public function processQueueRow(): void
    {
        switch ($this->event) {
            case self::EVENT_PAYOUT_PAID:

                try {
                    $this->processPayoutTransaction();
                    $this->status = self::STATUS_SUCCESS;
                    $this->error_type = '';
                    $this->save();

                    if (self::checkAllTransactionCompleted($this->event, $this->object_id)) {
                        $remesa = new RemesaSEPA();
                        $remesa->load($this->destination_id);
                        $remesa->estado = RemesaSEPA::STATUS_REVIEW;
                        $remesa->save();

            //         Calculamos los totales de la remesa
                        $remesa->updateTotal();
                        $this->sendMailRemesaCompleta($remesa->idremesa);
                    }
                }
                catch (Exception $e) {
                    $this->status = self::STATUS_ERROR;
                    $this->error_type = $e->getMessage();
                    $this->save();
                }

                break;
            case self::EVENT_INVOICE_PAYMENT_SUCCEEDED:

                try {
                    $enviarEmail = SettingStripeModel::getSetting('enviarEmail') == 1;
                    InvoiceStripe::generateFSInvoice(
                        $this->transaction_id,
                        SettingStripeModel::loadSkIndexStripeByName($this->stripe_account),
                        false,
                        'TARJETA',
                        $enviarEmail,
                        $this->destination_id,
                        'webhook'
                    );

                    $this->status = self::STATUS_SUCCESS;
                    $this->error_type = '';
                    $this->save();

                 } catch (Exception) {
                    $this->status = self::STATUS_ERROR;
                    $this->error_type = self::ERROR_TYPE_NOT_GENERATE_INVOICE;
                    $this->save();
                }

                break;


            default:
                $this->status = self::STATUS_ERROR;
                $this->error_type = self::ERROR_TYPE_NO_EVENT;
                $this->save();
                break;
        }
    }


    /**
     * Método que va a mandar un email
     * @param $id_remesa
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \PHPMailer\PHPMailer\Exception
     */
    private function sendMailRemesaCompleta($id_remesa): void
    {
        $subject = 'Remesa ' . $id_remesa . ' procesada';
        $body = 'La remesa se ha procesado completamente, por favor comprueba que está correcta. \r\n';

        $errors = self::all([
            new DataBaseWhere('destination', self::DESTINATION_REMESA),
            new DataBaseWhere('destination_id', $id_remesa),
            new DataBaseWhere('status', self::STATUS_ERROR),
        ]);

        $res = [];

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $res[] = '- Factura: ' . $error->transaction_id;
            }
        }

        $body .= "Errores:\r\n" . implode("\r\n", $res);

        $mail = NewMail::create()
            ->to(SettingStripeModel::getSetting('adminEmail'))
            ->cc(SettingStripeModel::getSetting('satEmail'))
            ->subject($subject)
            ->body(nl2br($body));

        $mail->send();
    }


    /**
     * @return void
     * @throws ApiErrorException
     * @throws Exception
     */
    private function processPayoutTransaction(): void
    {
        $sk = SettingStripeModel::loadSkStripeByName($this->stripe_account);
        $invoice = $this->getInvoiceFromPayoutTransaction($this->transaction_id, $sk );
        $facturaId = $invoice->metadata['fs_idFactura'];

        if (!isset($facturaId)){
            InvoiceStripe::log('La factura ' . $invoice['id']. ' no está vinculada en stripe.', 'remesa');
            throw new Exception(self::ERROR_TYPE_FACTURA_NO_VINCULADA);
        }

        $reciboCliente = new ReciboCliente();

        $where = [new DataBaseWhere('idfactura', $facturaId), new DataBaseWhere('pagado', false)];
        $reciboCliente->loadWhere($where);

        if (!$reciboCliente->idrecibo){
            InvoiceStripe::log('La factura ' . $facturaId. ' no tiene un recibo o ya está pagado', 'remesa');
            throw new Exception(self::ERROR_TYPE_RECIBO_PAGADO);
        }

        if ($reciboCliente->idremesa){
            InvoiceStripe::log('La factura ' . $facturaId. ' ya tiene una remesa asignada', 'remesa');
            throw new Exception(self::ERROR_TYPE_ASIGNADO_OTRA_REMESA);
        }

        $reciboCliente->idremesa = $this->destination_id;

        if ($reciboCliente->save()){
            InvoiceStripe::log('Se genera linea de remesa con la factura:  ' . $facturaId, 'remesa');
        }
    }


    /**
     * @param $event
     * @param $objectId
     * @return bool
     */
    private function checkAllTransactionCompleted($event, $objectId): bool {
        $pending = self::findWhere([
            new DataBaseWhere('event', $event),
            new DataBaseWhere('object_id', $objectId),
            new DataBaseWhere('status', self::STATUS_PENDING),
        ]);

        return empty($pending);
    }




    /**
     *  En un pago pueden venir varias procedencias de cobro.
     *  ch >> Es mediante cargo, que es la que nos interesa
     *  in >> Es una factura
     * @param string $source
     * @param string $sk
     * @return Invoice|null
     * @throws ApiErrorException
     */
    public function getInvoiceFromPayoutTransaction(string $source, string $sk): ?Invoice
    {
        $stripe = new StripeClient($sk);

//        if (str_starts_with($source, 'ch_')) {
//            $charge = $stripe->charges->retrieve($source, []);
//
//            if (empty($charge->invoice)) {
////                $errors[] = 'El cargo ' . $source . ' no tiene factura';
//                return null;
//            }
//
//            return $stripe->invoices->retrieve($charge->invoice, []);
//        }

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

    /**
     * @param $stripe_account
     * @param $event
     * @param $object_id
     * @param $object_date
     * @param $transaction_type
     * @param $transaction_id
     * @param $destination
     * @param $destination_id
     * @return bool
     */
    static function setStripeTransaction(
        $stripe_account,
        $event,
        $object_id,
        $object_date,
        $transaction_type,
        $transaction_id,
        $destination,
        $destination_id,
    ): bool
    {
        $model = new StripeTransactionsQueue();
        $model->stripe_account = $stripe_account;
        $model->event = $event;
        $model->object_id = $object_id;
        $model->object_date = $object_date;
        $model->transaction_type = $transaction_type;
        $model->transaction_id = $transaction_id;
        $model->destination = $destination;
        $model->destination_id = $destination_id;
        return $model->save();
    }


    /**
     * Comprueba si puedo usar las remesas
     * @param bool $onlyVerifyPlugin
     * @return bool
     */
    static function canUseRemesas(bool $onlyVerifyPlugin = false): bool
    {
        if ($onlyVerifyPlugin)
            return Plugins::isInstalled('RemesasSEPA') && Plugins::isEnabled('RemesasSEPA');

        return SettingStripeModel::getSetting('remesasSEPA') === 1 && Plugins::isInstalled('RemesasSEPA') && Plugins::isEnabled('RemesasSEPA');
    }


    /**
     * Comprueba si puedo usar verifactu
     * @param bool $onlyVerifyPlugin
     * @return bool
     */
    static function canUseVerifactu(bool $onlyVerifyPlugin = false): bool
    {
        if ($onlyVerifyPlugin)
            return Plugins::isInstalled('Verifactu') && Plugins::isEnabled('Verifactu');

        return SettingStripeModel::getSetting('verifactu') === 1 && Plugins::isInstalled('Verifactu') && Plugins::isEnabled('Verifactu');
    }
}

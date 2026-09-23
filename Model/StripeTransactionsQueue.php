<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use Exception;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Plugins\ImportadorStripe\Lib\InvoiceImporter;
use FacturaScripts\Plugins\ImportadorStripe\Lib\Logger;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeGateway;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeMailer;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;
use Stripe\Invoice;

class StripeTransactionsQueue extends ModelClass
{
    use ModelTrait;

    public int|null $id;
    public string|null $stripe_account;

    /** Tipo de evento (pago de un payout o cobro de una factura). */
    public string|null $event;
    public string|null $object_id;
    public string|null $object_date;
    public string|null $transaction_type;
    public string|null $transaction_id;
    public string|null $destination;
    public string|null $destination_id;
    public string|null $status;
    public string|null $error_type;
    public string|null $created_at;

    public const EVENT_PAYOUT_PAID = 'Pago';
    public const EVENT_INVOICE_PAYMENT_SUCCEEDED = 'Suscripcion';

    public static array $eventOptions = [
        self::EVENT_PAYOUT_PAID => self::EVENT_PAYOUT_PAID,
        self::EVENT_INVOICE_PAYMENT_SUCCEEDED => self::EVENT_INVOICE_PAYMENT_SUCCEEDED,
    ];

    public const TRANSACTION_TYPE_CHARGE = 'Cargo';
    public const TRANSACTION_TYPE_PAYMENT_INTENT = 'Payment intent';
    public const TRANSACTION_TYPE_INVOICE = 'Factura';

    public static array $transactionTypeOptions = [
        self::TRANSACTION_TYPE_CHARGE => self::TRANSACTION_TYPE_CHARGE,
        self::TRANSACTION_TYPE_PAYMENT_INTENT => self::TRANSACTION_TYPE_PAYMENT_INTENT,
        self::TRANSACTION_TYPE_INVOICE => self::TRANSACTION_TYPE_INVOICE,
    ];

    public const DESTINATION_REMESA = 'Remesa';
    public const DESTINATION_INVOICE = 'Factura';
    public const DESTINATION_CUSTOMER = 'Cliente';

    public static array $destinationOptions = [
        self::DESTINATION_REMESA => self::DESTINATION_REMESA,
        self::DESTINATION_INVOICE => self::DESTINATION_INVOICE,
    ];

    public const STATUS_PENDING = 'Pendiente';
    public const STATUS_SUCCESS = 'Procesado';
    public const STATUS_ERROR = 'Error';

    public static array $statusOptions = [
        self::STATUS_PENDING => self::STATUS_PENDING,
        self::STATUS_SUCCESS => self::STATUS_SUCCESS,
        self::STATUS_ERROR => self::STATUS_ERROR,
    ];

    public const ERROR_TYPE_NO_EVENT = 'Evento no reconocido';
    public const ERROR_TYPE_NOT_GENERATE_INVOICE = 'Factura no generada';
    public const ERROR_TYPE_RECIBO_PAGADO = 'Recibo pagado';
    public const ERROR_TYPE_FACTURA_NO_VINCULADA = 'Factura no vinculada';
    public const ERROR_TYPE_NO_INVOICE = 'Sin factura';
    public const ERROR_TYPE_ASIGNADO_OTRA_REMESA = 'Asignado otra remesa';

    public function clear(): void
    {
        parent::clear();
        $this->status = self::STATUS_PENDING;
        $this->created_at = date('Y-m-d H:i:s');
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'stripe_transactions_queue';
    }

    /**
     * @return StripeTransactionsQueue[]
     */
    public static function getPendingTransactions(int $limit = 5): array
    {
        return self::all([Where::eq('status', self::STATUS_PENDING)], [], 0, $limit);
    }

    public static function processQueue(): void
    {
        foreach (self::getPendingTransactions() as $transaction) {
            $transaction->processQueueRow();
            sleep(2);
        }
    }

    public function processQueueRow(bool $sendMailError = true): void
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
                        $remesa->updateTotal();
                        StripeMailer::sendRemesaComplete($remesa->idremesa, $this->getRemesaErrors($remesa->idremesa));
                    }
                } catch (Exception $e) {
                    $this->status = self::STATUS_ERROR;
                    $this->error_type = $e->getMessage();
                    $this->save();
                }
                break;

            case self::EVENT_INVOICE_PAYMENT_SUCCEEDED:
                try {
                    $enviarEmail = StripeSettings::getSetting('enviarEmail') == 1 && $this->status !== self::STATUS_ERROR;

                    InvoiceImporter::generateFSInvoice(
                        $this->transaction_id,
                        StripeSettings::loadSkIndexStripeByName($this->stripe_account),
                        false,
                        'TARJETA',
                        $enviarEmail,
                        $this->destination_id,
                        'webhook',
                        $this->status === self::STATUS_ERROR,
                    );

                    $this->status = self::STATUS_SUCCESS;
                    $this->error_type = '';
                    $this->save();
                } catch (Exception) {
                    $this->status = self::STATUS_ERROR;
                    $this->error_type = self::ERROR_TYPE_NOT_GENERATE_INVOICE;

                    if ($this->save() && $sendMailError) {
                        StripeMailer::sendQueueError($this->object_id);
                    }
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
     * Indica si la línea se puede vincular manualmente a una factura de FacturaScripts.
     * Solo aplica a pagos (payouts) con error de factura no vinculada y con factura de Stripe.
     */
    public function canLinkInvoice(): bool
    {
        return $this->event === self::EVENT_PAYOUT_PAID
            && $this->error_type === self::ERROR_TYPE_FACTURA_NO_VINCULADA
            && $this->transaction_id !== null
            && str_starts_with($this->transaction_id, 'in_');
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function processPayoutTransaction(): void
    {
        $invoice = $this->getInvoiceFromPayoutTransaction($this->transaction_id);

        if ($invoice === null) {
            Logger::log('No se ha encontrado la factura ' . $this->transaction_id . ' del pago.', Logger::CHANNEL_REMESA);
            throw new Exception(self::ERROR_TYPE_NO_INVOICE);
        }

        $facturaId = $invoice->metadata['fs_idFactura'] ?? null;

        if (empty($facturaId)) {
            Logger::log('La factura ' . $invoice->id . ' no está vinculada en stripe.', Logger::CHANNEL_REMESA);
            throw new Exception(self::ERROR_TYPE_FACTURA_NO_VINCULADA);
        }

        $reciboCliente = new ReciboCliente();
        $reciboCliente->loadWhere([Where::eq('idfactura', $facturaId), Where::eq('pagado', false)]);

        if (!$reciboCliente->idrecibo) {
            Logger::log('La factura ' . $facturaId . ' no tiene un recibo o ya está pagado', Logger::CHANNEL_REMESA);
            throw new Exception(self::ERROR_TYPE_RECIBO_PAGADO);
        }

        if ($reciboCliente->idremesa) {
            Logger::log('La factura ' . $facturaId . ' ya tiene una remesa asignada', Logger::CHANNEL_REMESA);
            throw new Exception(self::ERROR_TYPE_ASIGNADO_OTRA_REMESA);
        }

        $reciboCliente->idremesa = $this->destination_id;

        if ($reciboCliente->save()) {
            Logger::log('Se genera linea de remesa con la factura: ' . $facturaId, Logger::CHANNEL_REMESA);
        }
    }

    private function checkAllTransactionCompleted(string $event, string $objectId): bool
    {
        $pending = self::findWhere([
            Where::eq('event', $event),
            Where::eq('object_id', $objectId),
            Where::eq('status', self::STATUS_PENDING),
        ]);

        return empty($pending);
    }

    /**
     * @return string[]
     */
    private function getRemesaErrors(string $idRemesa): array
    {
        $errors = self::all([
            Where::eq('destination', self::DESTINATION_REMESA),
            Where::eq('destination_id', $idRemesa),
            Where::eq('status', self::STATUS_ERROR),
        ]);

        $transactionIds = [];
        foreach ($errors as $error) {
            $transactionIds[] = $error->transaction_id;
        }

        return $transactionIds;
    }

    /**
     * En un pago pueden venir varias procedencias de cobro. Solo procesamos facturas (in_).
     *
     * @throws Exception
     */
    private function getInvoiceFromPayoutTransaction(?string $source): ?Invoice
    {
        if ($source !== null && str_starts_with($source, 'in_')) {
            return StripeGateway::byName($this->stripe_account)->retrieveInvoiceSimple($source);
        }

        return null;
    }

    public static function existsObjectId(string $objectId, string $event): bool
    {
        return static::count([
            Where::eq('object_id', $objectId),
            Where::eq('event', $event),
        ]) > 0;
    }

    public static function setStripeTransaction(
        string $stripe_account,
        string $event,
        string $object_id,
        string $object_date,
        string $transaction_type,
        ?string $transaction_id,
        string $destination,
        ?string $destination_id,
    ): bool {
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

    public static function canUseRemesas(bool $onlyVerifyPlugin = false): bool
    {
        if ($onlyVerifyPlugin) {
            return Plugins::isInstalled('RemesasSEPA') && Plugins::isEnabled('RemesasSEPA');
        }

        return StripeSettings::getSetting('remesasSEPA') && Plugins::isInstalled('RemesasSEPA') && Plugins::isEnabled('RemesasSEPA');
    }

    public static function canUseVerifactu(bool $onlyVerifyPlugin = false): bool
    {
        if ($onlyVerifyPlugin) {
            return Plugins::isInstalled('Verifactu') && Plugins::isEnabled('Verifactu');
        }

        return StripeSettings::getSetting('verifactu') && Plugins::isInstalled('Verifactu') && Plugins::isEnabled('Verifactu');
    }
}

<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\StripeClient;

class PayoutStripeQueue extends ModelClass
{
    use ModelTrait;

    public $id;
    public $payout_id;
    public $charge_id;
    public $remesa_id;
    public $created_at;
    public $status;
    public $nota;


    public function clear(): void
    {
        parent::clear();
        $this->status = null;
        $this->remesa_id = null;
        $this->created_at = date('Y-m-d H:i:s');
        $this->nota = null;
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
        return 'payout_stripe_queue';
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
}

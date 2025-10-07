<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

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
}

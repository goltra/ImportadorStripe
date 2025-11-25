<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\IdentificadorFiscal;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;
use Stripe\Exception\ApiErrorException;

class ListStripeTransactionsQueue extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["title"] = "Cola de transacciones";
        $data["menu"] = "Stripe";
        $data["icon"] = "fa-solid fa-bars-staggered";
        return $data;
    }


    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'generate':
                return $this->generateAction();
        }

        return parent::execPreviousAction($action);
    }

    private function generateAction()
    {
        if (!$this->request->request->get('codes')){
            Tools::log()->error('No has seleccionado una linea.');
            return true;
        }

        $codes = unserialize($this->request->request->get('codes'));

        if (count($codes) > 1){
            Tools::log()->error('Sólo se puede seleccionar una línea.');
            return true;
        }

        $code = $codes[0];
        $transaction = new StripeTransactionsQueue();
        $transaction->load($code);

        if ($transaction->status === StripeTransactionsQueue::STATUS_SUCCESS) {
            Tools::log()->error('La línea ya ha sido procesada.');
            return true;
        }

        $transaction->processQueueRow();

        Tools::log()->info('Linea procesada, revisa que no haya dado error.');


        return true;
    }


    protected function createViews(): void
    {
        // Se crean las pestañas usando funciones separadas para mayor claridad
        $this->createViewsProject();
    }

    protected function createViewsProject(string $viewName = 'ListStripeTransactionsQueue'): void
    {
        //  Orden
        $this->addView($viewName, 'StripeTransactionsQueue', 'Pagos de stripe')
            ->addOrderBy(['created_at'], 'fecha', 2)
            ->addSearchFields(['created_at']);

        //   Quito botones por defecto
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'clickable', false);

        $this->addButton($viewName, [
            'action' => 'generate',
            'icon' => 'fas fa-plus',
            'label' => 'Procesar',
        ]);

        //  Colores de las filas
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_PENDING, 'warning', 'Pendiente');
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_SUCCESS, '', 'Procesado');
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_ERROR, 'danger', 'Error');


        // Filtros
        $this->addSearchFields($viewName, ['object_id']);


        $this->addFilterSelect(
            $viewName,
            'stripe_account',
            'Cuenta de stripe',
            'stripe_account',
            $this->getDistinctStripeAccount()
        );

        $this->addFilterSelect(
            $viewName,
            'event',
            'Tipo de evento',
            'Event',
            StripeTransactionsQueue::$eventOptions
        );

//        $this->addFilterSelect(
//            $viewName,
//            'object_id',
//            'Evento',
//            'object_id',
//            $this->getDistinctPayouts()
//        );

        $this->addFilterSelect(
            $viewName,
            'transaction_type',
            'Transaccion',
            'transaction_type',
            StripeTransactionsQueue::$tansactionTypeOptions
        );

        $this->addFilterSelect(
            $viewName,
            'destination',
            'Destino',
            'destination',
            StripeTransactionsQueue::$destinoOptions
        );

        $this->addFilterSelect(
            $viewName,
            'status',
            'Estado',
            'status',
            StripeTransactionsQueue::$statusOptions
        );
    }


    /**
     * Listado de payouts que hay en la tabla para el filtro
     */
//    protected function getDistinctPayouts(): array
//    {
//        $items = StripeTransactionsQueue::all([ Where::like('event', StripeTransactionsQueue::EVENT_PAYOUT_PAID )]);
//        $ret = [];
//        foreach ($items as $line) {
//            if (array_key_exists($line->object_id, $ret))
//                continue;
//
//            $ret[$line->object_id] = $line->object_id;
//        }
//        return $ret;
//    }



    /**
     * Listado de payouts que hay en la tabla para el filtro
     */
    protected function getDistinctStripeAccount(): array
    {
        $db = new DataBase();
        $items = $db->select("SELECT DISTINCT stripe_account FROM stripe_transactions_queue");
        $res = [];
        foreach ($items as $line) {
            $res[$line['stripe_account']] = $line['stripe_account'];
        }
        return $res;
    }
}

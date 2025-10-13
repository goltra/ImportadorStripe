<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;

class ListStripeTransactionsQueue extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["title"] = "Pagos de stripe";
        $data["menu"] = "Stripe";
        $data["icon"] = "fas fa-file-signature";
        return $data;
    }

    protected function createViews(): void
    {
        // Se crean las pestañas usando funciones separadas para mayor claridad
        $this->createViewsProject();
    }

    protected function createViewsProject(string $viewName = 'ListStripeTransactionsQueue'): void
    {
        $this->addView($viewName, 'StripeTransactionsQueue', 'Pagos de stripe')
            ->addOrderBy(['created_at'], 'fecha')
            ->addSearchFields(['created_at']);

        //   Quito botones por defecto
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);

        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_PENDING, 'warning', 'Pendiente');
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_ERROR, 'danger', 'Pendiente');

        // Filtros
        $this->addFilterSelect(
            $viewName,
            'event',
            'Tipo de evento',
            'Event',
            StripeTransactionsQueue::$eventOptions
        );

        $this->addFilterSelect(
            $viewName,
            'object_id',
            'Evento',
            'object_id',
            $this->getDistinctPayouts()
        );

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

        /**
         * todo funcionalidades
         * - botón para procesar una linea y que a su vez compruebe si finaliza.
         * - color de linea dependiendo del estado
         */
    }


    /**
     * Listado de payouts que hay en la tabla para el filtro
     */
    protected function getDistinctPayouts(): array
    {
        $items = StripeTransactionsQueue::all();
        $ret = [];
        foreach ($items as $line) {
            $ret[$line->object_id] = $line->object_id;
        }
        return $ret;
    }
}

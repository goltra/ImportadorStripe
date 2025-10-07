<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;

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

    protected function createViews()
    {
        // Se crean las pestañas usando funciones separadas para mayor claridad
        $this->createViewsProject();
    }

    protected function createViewsProject(string $viewName = 'ListPayoutStripeQueue'): void
    {
        $this->addView($viewName, 'PayoutStripeQueue', 'Pagos de stripe')
            ->addOrderBy(['created_at'], 'fecha')
            ->addSearchFields(['created_at']);

        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);

        /**
         * todo funcionalidades
         * - filtro por payout
         * - filtro por remesa
         * - filtro por estado
         * - botón para procesar una linea y que a su vez compruebe si finaliza.
         * - color de linea dependiendo del estado
         */
    }


    /**
     * Listado de payouts que hay en la tabla para el filtro
     */
//    protected function getDistinctPayouts(): array
//    {
//        $items = PayoutStripeQueue::all();
//        $ret = [];
//        foreach ($items as $line) {
//            $ret[$line->payout_id] = $line->payout_id;
//        }
//        return $ret;
//    }
}

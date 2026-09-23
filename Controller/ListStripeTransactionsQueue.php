<?php
namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExtendedController\ListController;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeGateway;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;

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

            case 'stripe-link':
                return $this->linkInvoiceAction();
        }

        return parent::execPreviousAction($action);
    }

    private function generateAction(): bool
    {
        $codes = $this->request->request->get('codes');
        $decoded = is_string($codes) ? unserialize($codes, ['allowed_classes' => false]) : null;

        if (!is_array($decoded) || count($decoded) === 0) {
            Tools::log()->error('No has seleccionado una linea.');
            return true;
        }

        if (count($decoded) > 1) {
            Tools::log()->error('Sólo se puede seleccionar una línea.');
            return true;
        }

        $code = $decoded[0];
        $transaction = new StripeTransactionsQueue();
        if (false === $transaction->load($code)) {
            Tools::log()->error('No se ha encontrado la línea seleccionada.');
            return true;
        }

        if ($transaction->status === StripeTransactionsQueue::STATUS_SUCCESS) {
            Tools::log()->error('La línea ya ha sido procesada.');
            return true;
        }

        $transaction->processQueueRow(false);

        // Si la factura de Stripe no está vinculada, recargamos la página abriendo el
        // modal para elegir la factura de FacturaScripts y vincularla + procesarla.
        if ($transaction->canLinkInvoice()) {
            $this->redirect('ListStripeTransactionsQueue?activetab=' . $this->mainTabName() . '&linkInvoice=' . urlencode($code), 0);
            return true;
        }

        Tools::log()->info('Linea procesada, revisa que no haya dado error.');

        return true;
    }

    private function linkInvoiceAction(): bool
    {
        $codes = $this->request->request->getArray('codes');
        if (count($codes) !== 1) {
            Tools::log()->error('No has seleccionado una linea.');
            return true;
        }

        $transaction = new StripeTransactionsQueue();
        if (false === $transaction->load($codes[0])) {
            Tools::log()->error('No se ha encontrado la línea seleccionada.');
            return true;
        }

        if (false === $transaction->canLinkInvoice()) {
            Tools::log()->error('Sólo se pueden vincular facturas de pagos (payouts) con el error "Factura no vinculada".');
            return true;
        }

        $fsInvoice = new FacturaCliente();
        $fsInvoiceId = $this->request->request->get('fs_invoice_id');
        if (empty($fsInvoiceId) || false === $fsInvoice->load($fsInvoiceId)) {
            Tools::log()->error('No se ha encontrado la factura de FacturaScripts seleccionada.');
            return true;
        }

        try {
            StripeGateway::byName($transaction->stripe_account)
                ->updateInvoiceMetadata($transaction->transaction_id, ['fs_idFactura' => (string)$fsInvoice->idfactura]);
        } catch (Exception $e) {
            Tools::log()->error('No se ha podido vincular la factura en Stripe: ' . $e->getMessage());
            return true;
        }

        $transaction->processQueueRow(false);

        if ($transaction->status === StripeTransactionsQueue::STATUS_SUCCESS) {
            Tools::log()->notice('Factura vinculada y línea procesada correctamente.');
        } else {
            Tools::log()->warning('Factura vinculada, pero la línea no se ha podido procesar: ' . $transaction->error_type);
        }

        return true;
    }


    protected function createViews(): void
    {
        AssetManager::addJs(FS_ROUTE . '/Plugins/ImportadorStripe/Assets/JS/ListStripeTransactionsQueue.js');

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

        $this->tab($viewName)->addButton([
            'action' => 'generate',
            'icon' => 'fas fa-plus',
            'label' => 'Procesar',
        ]);

        //  Colores de las filas
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_PENDING, 'warning', 'Pendiente');
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_SUCCESS, '', 'Procesado');
        $this->addColor($viewName, 'status', StripeTransactionsQueue::STATUS_ERROR, 'danger', 'Error');


        // Filtros
        $this->addSearchFields($viewName, ['object_id', 'transaction_id']);


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

//        $this->addFilterSelect(
//            $viewName,
//            'transaction_type',
//            'Transaccion',
//            'transaction_type',
//            StripeTransactionsQueue::$tansactionTypeOptions
//        );

        $this->addFilterSelect(
            $viewName,
            'destination',
            'Destino',
            'destination',
            StripeTransactionsQueue::$destinationOptions
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
     * Listado de cuentas de stripe que hay en la tabla para el filtro.
     */
    protected function getDistinctStripeAccount(): array
    {
        $db = new DataBase();
        $items = $db->select("SELECT DISTINCT stripe_account FROM stripe_transactions_queue");
        $accounts = [];
        foreach ($items as $line) {
            $accounts[$line['stripe_account']] = $line['stripe_account'];
        }

        return $accounts;
    }
}

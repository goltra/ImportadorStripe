<?php

namespace FacturaScripts\Plugins\ImportadorStripe;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Lib\API\APIModel;

require_once __DIR__ . '/vendor/autoload.php';

class Init extends InitClass
{
    public function init(): void
    {
        /// se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).

        if (method_exists(APIModel::class, 'excludeModel')) {
            APIModel::excludeModel('ClientModel');
            APIModel::excludeModel('Helper');
            APIModel::excludeModel('InvoiceStripe');
            APIModel::excludeModel('ProductModel');
            APIModel::excludeModel('SettingStripeModel');
            APIModel::excludeModel('StripeTransactionsQueue');
        }

    }

    public function update(): void
    {
        /// se ejecutara cada vez que se instala o actualiza el plugin.
    }

    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}

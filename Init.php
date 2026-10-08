<?php

namespace FacturaScripts\Plugins\ImportadorStripe;

use FacturaScripts\Core\Template\InitClass;

require_once __DIR__ . '/vendor/autoload.php';

class Init extends InitClass
{
    public function init(): void
    {
        /// se ejecutará cada vez que carga FacturaScripts (si este plugin está activado).
    }

    public function update(): void
    {
        /// se ejecutará cada vez que se instala o actualiza el plugin.
    }

    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}

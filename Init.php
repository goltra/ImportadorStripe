<?php

namespace FacturaScripts\Plugins\ImportadorStripe;
require_once __DIR__ . '/vendor/autoload.php';
class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init()
    {
        /// se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).

    }

    public function update()
    {
        /// se ejecutara cada vez que se instala o actualiza el plugin.
    }
}

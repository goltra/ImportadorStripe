<?php

namespace FacturaScripts\Plugins\ImportadorStripe;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init()
    {
        /// se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).
        require_once('stripe/init.php');
    }

    public function update()
    {
        /// se ejecutara cada vez que se instala o actualiza el plugin.
    }
}

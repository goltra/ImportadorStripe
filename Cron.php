<?php
namespace FacturaScripts\Plugins\MyPlugin;

use FacturaScripts\Core\Template\CronClass;

class Cron extends CronClass
{
    public function run(): void
    {
        /*
        if ($this->isTimeForJob("my-job-name", "6 hours")) {
            /// su código aquí
            $this->jobDone("my-job-name");
        }
        */
    }
}


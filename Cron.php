<?php
namespace FacturaScripts\Plugins\ImportadorStripe;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Plugins\ImportadorStripe\Model\PayoutStripeQueue;

class Cron extends CronClass
{
    public function run(): void
    {
        $this->job('procesar-cola-pagos-stripe')
            ->every('1 hour')
            ->run(function () {
                PayoutStripeQueue::processQueue();
            });
    }
}


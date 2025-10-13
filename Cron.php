<?php
namespace FacturaScripts\Plugins\ImportadorStripe;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;

/**
 * Aquí se define las tareas cron que se van a ejecutar.
 * El parámetro every es cada cuanto se va a ejecutar ese cron pero tienes que ir haciendo pings desde el servidor para que vaya.
 * Es decir si tu configuras every para que se haga cada hora, tienes que hacer pings mínimo cada hora para que funcione.
 * Si haces pings cada 5 min y tienes 1 cron cada hora, sólo se ejecutará 1 vez cada hora.
 */
class Cron extends CronClass
{
    public function run(): void
    {
//        $this->job('procesar-cola-pagos-stripe')
//            ->every('1 hour')
//            ->run(function () {
//                StripeTransactionsQueue::processQueue();
//            });

        $this->job('prueba-hora')
            ->every('1 hour')
            ->run(function () {
                echo 'Hola cron: ' . date('Y-m-d H:i:s') . PHP_EOL;
                file_put_contents(__DIR__ . '/log_cron.txt', date('Y-m-d H:i:s') . " - job ejecutado\n", FILE_APPEND);
            });
    }
}


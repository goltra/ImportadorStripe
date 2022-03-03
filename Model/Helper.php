<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Model;
use DateTime;

class Helper{
    static function castTime($time): string
    {
        $d = date('d-m-Y', $time);
        return $d;
    }

    /**
     * recibe una fecha y un formato (por defecto elformato es d-m-Y) y lo convierte en
     * timestamp
     * @param string $fecha
     * @param string $format
     * @return string
     */
    static public function parseDateToTS(string $fecha, string $format='d-m-Y')
    {

        $res = DateTime::createFromFormat( $format . '|',  $fecha );

        return $res->getTimestamp();
    }



}


<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use DateTime;

class DateHelper
{
    public static function castTime(int $time): string
    {
        return date('d-m-Y', $time);
    }

    public static function parseDateToTS(string $date, string $format = 'd-m-Y'): int
    {
        $parsed = DateTime::createFromFormat($format . '|', $date);

        return $parsed ? $parsed->getTimestamp() : 0;
    }
}

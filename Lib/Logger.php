<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

class Logger
{
    const CHANNEL_INVOICE = 'invoice';
    const CHANNEL_REMESA = 'remesa';
    const CHANNEL_DEFAULT = 'default';

    private const FILES = [
        self::CHANNEL_INVOICE => 'invoice-log.txt',
        self::CHANNEL_REMESA => 'remesa-sepa-log.txt',
        self::CHANNEL_DEFAULT => 'stripe-log.txt',
    ];

    public static function log($value, string $channel = self::CHANNEL_INVOICE): void
    {
        if (is_object($value) || is_array($value)) {
            $value = serialize($value);
        }

        $fileName = self::FILES[$channel] ?? self::FILES[self::CHANNEL_DEFAULT];
        $file = FS_FOLDER . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($file, date('d-m-Y H:i:s') . ' - ' . $value . PHP_EOL, FILE_APPEND);
    }
}

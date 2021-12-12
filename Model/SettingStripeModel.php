<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use FacturaScripts\Core\App\AppSettings;

class SettingStripeModel
{
    public static function getSks()
    {
        $app = new AppSettings();
        $sk_serialized = $app->get('stripe', 'sks');
        return ($sk_serialized !== null) ? unserialize($sk_serialized) : [];
    }

    public static function removeSk($name)
    {
        $sks = self::getSks();
        foreach ($sks as $sk) {
            if ($sk['name'] === $name) {
                $index = array_search($sk,$sks);
                unset($sks[$index]);
                break;
            }
        }
        self::save($sks);
    }

    public static function addSk($name, $sk)
    {
        $sks = self::getSks();
        if (is_array($sks)) {
            $sks[] = ['name' => $name, 'sk' => $sk];
            self::save($sks);
        }
    }

    private static function save($data)
    {
        $app = new AppSettings();
        $app->set('stripe', 'sks', serialize($data));
        $app->save();
    }
}

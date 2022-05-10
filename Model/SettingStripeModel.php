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


    /**
     * Obtiene el sk por nombre
     * @param $name
     * @return mixed|null
     */
    public static function getSkIndexByName($name){
        $res = null;

        foreach (self::getSks() as $index => $sk){
            if ($sk['name'] === $name)
                $res = $index;
        }

        return $res;
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
        self::save($sks, 'sks');
    }

    public static function addSk($name, $sk, $serie)
    {
        $sks = self::getSks();
        if (is_array($sks)) {
            $sks[] = ['name' => $name, 'sk' => $sk, 'codserie' => $serie];
            self::save($sks, 'sks');
        }
    }


    public static function getSetting($setting){
        $app = new AppSettings();
        $settings_serialized = $app->get('stripe', 'settings');

        return ($settings_serialized !== null) ? unserialize($settings_serialized)[$setting] : '';
    }

    public static function addSettings($settings){
        if (is_array($settings)) {
            self::save($settings, 'settings');
        }
    }

    private static function save($data, $type)
    {
        $app = new AppSettings();
        $app->set('stripe', $type, serialize($data));
        $app->save();
    }
}

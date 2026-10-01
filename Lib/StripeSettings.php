<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use FacturaScripts\Core\Tools;

class StripeSettings
{
    public static function getSks(): array
    {
        $serialized = Tools::settings('stripe', 'sks');
        $sks = $serialized !== null ? unserialize($serialized) : [];

        return is_array($sks) ? $sks : [];
    }

    public static function removeSk(string $name): void
    {
        $sks = self::getSks();

        foreach ($sks as $index => $sk) {
            if ($sk['name'] === $name) {
                unset($sks[$index]);
                break;
            }
        }

        self::save(array_values($sks), 'sks');
    }

    public static function addSk(string $name, string $sk, string $serie): void
    {
        $sks = self::getSks();
        $sks[] = [
            'name' => $name,
            'sk' => $sk,
            'codserie' => $serie,
            'token' => md5($name . date('Y-m-d H:i:s')),
        ];

        self::save($sks, 'sks');
    }

    public static function getSetting(string $setting): mixed
    {
        return self::getSettings()[$setting] ?? '';
    }

    public static function addSettings(array $settings): void
    {
        self::save($settings, 'settings');
    }

    public static function loadSkStripeByToken(string $token): array
    {
        return self::findSk(fn (array $sk) => ($sk['token'] ?? '') === $token) ?? [];
    }

    public static function loadSkIndexStripeByName(string $name): ?int
    {
        foreach (self::getSks() as $index => $sk) {
            if ($sk['name'] === $name) {
                return $index;
            }
        }

        return null;
    }

    public static function loadSkStripeByName(string $name): ?string
    {
        foreach (self::getSks() as $sk) {
            if ($sk['name'] === $name) {
                return $sk['sk'];
            }
        }

        return null;
    }

    public static function loadSkStripeByIndex(int $index): array
    {
        return self::getSks()[$index] ?? [];
    }

    private static function getSettings(): array
    {
        $serialized = Tools::settings('stripe', 'settings');
        $settings = $serialized !== null ? unserialize($serialized) : [];

        return is_array($settings) ? $settings : [];
    }

    private static function findSk(callable $callback): ?array
    {
        foreach (self::getSks() as $sk) {
            if ($callback($sk)) {
                return $sk;
            }
        }

        return null;
    }

    private static function save(array $data, string $type): void
    {
        Tools::settingsSet('stripe', $type, serialize($data));
        Tools::settingsSave();
    }
}

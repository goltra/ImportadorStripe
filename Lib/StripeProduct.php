<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

class StripeProduct
{
    public $id = '';
    public $name = '';
    public $description = '';
    public $fs_idProduct = '';

    /**
     * @return StripeProduct[]
     * @throws \Exception
     */
    public static function loadAll(int $skIndex, int $limit = 1000): array
    {
        $gateway = StripeGateway::byIndex($skIndex);
        $response = $gateway->client()->products->all(['active' => true, 'limit' => $limit]);
        $products = $response->data;

        $stripeVersion = $response->getLastResponse()->headers['stripe-version'] ?? '';
        $productsFromPlans = [];

        if ($stripeVersion === '2017-08-15') {
            $plans = $gateway->client()->plans->all(['active' => true, 'limit' => $limit]);

            foreach ($plans->data ?? [] as $plan) {
                $productsFromPlans[] = $gateway->client()->products->retrieve($plan->product);
            }
        }

        return self::fromStripeObjects(array_merge($products, $productsFromPlans));
    }

    /**
     * @throws \Exception
     */
    public static function linkToFsProduct(int $skIndex, string $fsProductId, string $stripeProductId): void
    {
        StripeGateway::byIndex($skIndex)->updateProductMetadata($stripeProductId, ['fs_idProduct' => $fsProductId]);
    }

    /**
     * @throws \Exception
     */
    public static function getFsProductIdFromStripe(int $skIndex, string $stripeProductId): string
    {
        $metadata = StripeGateway::byIndex($skIndex)->retrieveProduct($stripeProductId)->metadata['fs_idProduct'];

        return $metadata === null ? StripeSettings::getSetting('codproducto') : $metadata;
    }

    /**
     * @param \Stripe\Product[] $data
     * @return StripeProduct[]
     */
    private static function fromStripeObjects(array $data): array
    {
        $products = [];

        foreach ($data as $item) {
            $product = new self();
            $product->id = $item['id'];
            $product->name = $item['name'];
            $product->description = $item['description'];
            $product->fs_idProduct = $item->metadata['fs_idProduct'] ?? '';
            $products[] = $product;
        }

        return $products;
    }
}

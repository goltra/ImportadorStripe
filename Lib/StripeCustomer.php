<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

class StripeCustomer
{
    public $id = '';
    public $email = '';
    public $fs_idClient = '';

    /**
     * @return StripeCustomer[]
     * @throws \Exception
     */
    public static function loadAll(int $skIndex, string $email = ''): array
    {
        return self::fromStripeObjects(StripeGateway::byIndex($skIndex)->listCustomers($email));
    }

    /**
     * Vincula un cliente de FS con un cliente de Stripe.
     *
     * @throws \Exception
     */
    public static function linkToFsCustomer(int $skIndex, string $stripeCustomerId, string $fsCustomerId): void
    {
        StripeGateway::byIndex($skIndex)->updateCustomerMetadata($stripeCustomerId, ['fs_idFsCustomer' => $fsCustomerId]);
    }

    /**
     * @param \Stripe\Customer[] $data
     * @return StripeCustomer[]
     */
    private static function fromStripeObjects(array $data): array
    {
        $customers = [];

        foreach ($data as $item) {
            $customer = new self();
            $customer->id = $item['id'];
            $customer->email = $item['email'] ?? '';
            $customer->fs_idClient = $item->metadata['fs_idFsCustomer'] ?? '';
            $customers[] = $customer;
        }

        return $customers;
    }
}

<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

use Exception;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\Payout;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * Punto único de acceso a la API de Stripe.
 */
class StripeGateway
{
    private const API_VERSION = '2020-08-27';

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public static function byIndex(int $index): self
    {
        $sk = StripeSettings::loadSkStripeByIndex($index);

        if (empty($sk['sk'])) {
            throw new Exception('No ha indicado el sk de stripe que desea consultar');
        }

        return new self($sk['sk']);
    }

    public static function byName(string $name): self
    {
        $key = StripeSettings::loadSkStripeByName($name);

        if (empty($key)) {
            throw new Exception('No se ha encontrado la cuenta de stripe ' . $name);
        }

        return new self($key);
    }

    public function client(): StripeClient
    {
        return new StripeClient($this->apiKey);
    }

    public function retrieveEvent(string $id): Event
    {
        return $this->client()->events->retrieve($id, []);
    }

    public function retrieveCustomer(string $customerId): ?Customer
    {
        try {
            return $this->client()->customers->retrieve($customerId);
        } catch (Exception $e) {
            Logger::log('Error al recuperar el cliente de stripe ' . $customerId . ': ' . $e->getMessage());
            return null;
        }
    }

    public function listPaidInvoices(int $limit, int $startDate, int $endDate): Collection
    {
        Stripe::$apiVersion = self::API_VERSION;

        return $this->client()->invoices->all([
            'status' => 'paid',
            'limit' => $limit,
            'created' => ['lte' => $endDate, 'gte' => $startDate],
        ]);
    }

    public function retrieveInvoice(string $id): Invoice
    {
        return $this->client()->invoices->retrieve($id, [
            'expand' => ['lines.data.price.product'],
        ]);
    }

    public function retrieveInvoiceSimple(string $id): Invoice
    {
        return $this->client()->invoices->retrieve($id, []);
    }

    public function updateCustomerMetadata(string $customerId, array $metadata): void
    {
        $this->client()->customers->update($customerId, ['metadata' => $metadata]);
    }

    public function updateInvoiceMetadata(string $invoiceId, array $metadata): void
    {
        $this->client()->invoices->update($invoiceId, ['metadata' => $metadata]);
    }

    public function retrieveProduct(string $productId): Product
    {
        return $this->client()->products->retrieve($productId);
    }

    public function updateProductMetadata(string $productId, array $metadata): void
    {
        $this->client()->products->update($productId, ['metadata' => $metadata]);
    }

    /**
     * @return Customer[]
     */
    public function listCustomers(string $email = '', int $limit = 100000): array
    {
        $params = ['limit' => $limit];

        if ($email !== '') {
            $params['email'] = $email;
        }

        $customers = [];
        foreach ($this->client()->customers->all($params)->autoPagingIterator() as $customer) {
            $customers[] = $customer;
        }

        return $customers;
    }

    public function retrievePayout(string $payoutId): Payout
    {
        return $this->client()->payouts->retrieve($payoutId, []);
    }

    /**
     * @return \Stripe\BalanceTransaction[]
     */
    public function listAllBalanceTransactions(string $payoutId, int $limitPerRequest = 50, string $startingAfter = '', array $accumulated = []): array
    {
        $limit = min($limitPerRequest, 100);

        $params = [
            'payout' => $payoutId,
            'limit' => $limit,
            'expand' => ['data.source.source'],
        ];

        if ($startingAfter !== '') {
            $params['starting_after'] = $startingAfter;
        }

        $response = $this->client()->balanceTransactions->all($params);
        $accumulated = array_merge($accumulated, $response->data);

        if ($response->has_more) {
            $lastId = end($response->data)->id;
            return $this->listAllBalanceTransactions($payoutId, $limitPerRequest, $lastId, $accumulated);
        }

        return $accumulated;
    }

    public function getPaymentMethodType(string $paymentIntentId): string
    {
        $runner = $this->client();
        $paymentIntent = $runner->paymentIntents->retrieve($paymentIntentId, []);
        $paymentMethodId = $paymentIntent->payment_method;

        if (empty($paymentMethodId)) {
            $paymentMethodId = $paymentIntent->charges->data[0]->payment_method ?? '';

            if (empty($paymentMethodId)) {
                Logger::log('No se ha encontrado el payment method id.');
                return '';
            }
        }

        Logger::log('paymentMethodId: ' . $paymentMethodId);

        return $runner->paymentMethods->retrieve($paymentMethodId, [])->type;
    }
}

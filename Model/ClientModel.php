<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use Exception;

class ClientModel
{

    public $id;
    public $email;
    public $fs_idClient;

    static function loadSkStripe()
    {
        return SettingStripeModel::getSks();
    }

    static public function loadStripeCustomers($sk_stripe_index, $stripe_customer_email = '', $start = null, int $limit = 10)
    {

        $limit = 100000;
        $stripe_ids = self::loadSkStripe();
        // Cargo el index del sk pasado a la función
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        $stripe = new \Stripe\StripeClient($stripe_id);

        $params = [
            'limit' => $limit,
        ];

        if (!empty($stripe_customer_email)) {
            $params['email'] = $stripe_customer_email;
        }

        $response = $stripe->customers->all($params);

        $customers = [];

        foreach ($response->autoPagingIterator() as $customer) {
            $customers[] = $customer;
        }

        if (count($customers) > 0)
            return self::processStripeObjects($customers);

        return [];
    }

    /**
     * Función que recibe un array de clientes de stripe y lo convierte en un array con los datos formateados con lo que
     * necesitamos. Entre otras cosas con el id del cliente de FS en caso que lo tenga.
     * @param $data
     * @return array Devuelve un array vacio o con objetos de tipo ProductModel
     */
    static private function processStripeObjects($data)
    {
        $res = [];
        foreach ($data as $item) {
            $obj = new ClientModel();
            $obj->id = $item['id'];
            $obj->email = $item['email'];
            $obj->fs_idClient = isset($item->metadata['fs_idFsCustomer']) && $item->metadata['fs_idFsCustomer'] !== '' ? $item->metadata['fs_idFsCustomer'] : '';
            $res[] = $obj;
        }

        return $res;
    }

    static public function linkFsClientToStripeCustomer(string $stripe_customer_id, int $sk_stripe_index, string $fs_idFsCustomer)
    {
        $stripe_ids = self::loadSkStripe();
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];

        try {
            $stripe = new \Stripe\StripeClient($stripe_id);
            $customer = $stripe->customers->update($stripe_customer_id, [
                'metadata' => ['fs_idFsCustomer' => $fs_idFsCustomer]
            ]);

            return ['status' => true, 'data' => $customer];
        } catch (\Exception $ex) {
            return ['status' => false, 'message' => 'Error al obtener el cliente desde stripe ' . $ex->getMessage()];
        }
    }


    static function addPaymentMethodInMetaData($customer_stripe_id, $sk_stripe_index, $paymentMethod){
//        enviar datos a stripe
    }
}

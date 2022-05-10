<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Model;

use Exception;

class ProductModel
{

    public $id;
    public $name;
    public $description;
    public $fs_idProduct;

    static function loadSkStripe()
    {
        return SettingStripeModel::getSks();
    }

    static public function loadStripeProducts($sk_stripe_index, $start = null, int $limit = 1000)
    {
        $stripe_ids = self::loadSkStripe();
        // Cargo el index del sk pasado a la función
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        $stripe = new \Stripe\StripeClient($stripe_id);
        $response = $stripe->products->all(['active'=>true, 'limit'=>$limit]);

        // compruebo version api. Si es 2017-08-15 el listado de productos se compone de:
        // Productos qeu aparecen a hacer un listado de planes + el listado de productos.
        $stripe_version = $response->getLastResponse()->headers['stripe-version'] ?? '';
        $products = $response->data;
        $products_from_plans = null;
        $products_id = [];

        if($stripe_version ==='2017-08-15'){
            $plans = $stripe->plans->all(['active'=>true, 'limit'=>$limit]);

            if( $plans->data && count($plans->data)>0){

                foreach ($plans->data as $plan){
                    $p = $stripe->products->retrieve($plan->product);
                    if($p!==null){
                        $products_from_plans[] =$p;
                    }

                }

            }
        }
        if($products_from_plans!==null && count($products_from_plans)>0)
            $res = array_merge($products,$products_from_plans);
        else
            $res = $products;

        if (isset($res))
            return self::processProductStripeObjects($res);
        return null;
    }

    /**
     * Función que recibe un array de productos de stripe y lo convierte en un array con los datos formateados con lo que
     * necesitamos. Entre otras cosas con el id del producto de FS en caso que lo tenga.
     * @param $data
     * @return array Devuelve un array vacio o con objetos de tipo ProductModel
     */
    static private function processProductStripeObjects($data)
    {
        $res = [];
        foreach ($data as $item) {
            $obj = new ProductModel();
            $obj->id = $item['id'];
            $obj->name = $item['name'];
            $obj->description = $item['description'];
            $obj->fs_idProduct = (isset($item->metadata['fs_idProduct']) && $item->metadata['fs_idProduct'] !== '') ? $item->metadata['fs_idProduct'] : '';
            $res[] = $obj;
        }
        return $res;
    }

    static public function linkFsProductToStripeProduct($sk_stripe_index, $fs_idProduct, $st_product_id)
    {
        $stripe_ids = self::loadSkStripe();
        // Cargo el index del sk pasado a la función
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        $stripe = new \Stripe\StripeClient($stripe_id);

        $response = $stripe->products->retrieve($st_product_id);
        $response->metadata['fs_idProduct'] = $fs_idProduct;
        $response->save();
    }

    static public function getFsProductIdFromStripe($sk_stripe_index, $st_product_id)
    {
        $stripe_ids = self::loadSkStripe();
        // Cargo el index del sk pasado a la función
        $sk_stripe = $stripe_ids[$sk_stripe_index];
        if ($sk_stripe === '') {
            return ['status' => false, 'message' => 'No ha indicado el sk de stripe que desea consultar'];
        }
        $stripe_id = $sk_stripe['sk'];
        $stripe = new \Stripe\StripeClient($stripe_id);
        $product = $stripe->products->retrieve($st_product_id);
        return $product->metadata['fs_idProduct'] === null ? '' : $product->metadata['fs_idProduct'];
    }
}

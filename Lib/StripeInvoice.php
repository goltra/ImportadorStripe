<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Lib;

/**
 * Representación interna de una factura de Stripe ya parseada.
 */
class StripeInvoice
{
    public $id = '';
    public $numero = '';
    public $date = '';
    public $amount = 0.0;
    public $status = '';
    public $customer_id = '';
    public $customer_email = '';
    public $fs_idFsCustomer = '';
    public $fs_idFactura = null;
    public $starting_balance = null;
    public $fs_customerName = '';
    public $discount = 0.0;
    public $lines = [];
}

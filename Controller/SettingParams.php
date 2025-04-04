<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;
use FacturaScripts\Core\Session;

class SettingParams extends Controller
{

    public $sks_stripe = [];
    public $series = [];
    public $codcliente = '';
    public $codproducto = '';
    public $enviarEmail;
    public $adminEmail = '';
    public $mostrarStripeCus;


    public function privateCore(&$response, $user, $permissions)
    {
        $this->init();
        parent::privateCore($response, $user, $permissions);

    }
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Ajustes';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fas fa-search';
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    private function init()
    {
        $this->title='Configuración de Claves de stripe';
        $action = $this->request->query->get('action');
        $serieModel = new Serie();
        $this->series = $serieModel->all();

        switch ($action) {
            case 'add':
                $this->setSkStripe();
                $this->getAllSettings();

                break;
            case 'addSettings':
                $this->setSettings();
                $this->getAllSks();
                break;
            case 'del':
                $name = $this->request->query->get('name');
                if ($name !== null)
                    $this->delSkStripe($name);
                else
                    $this->toolBox()->log()->error('No se ha recibido el parametro necesario (name)');

                $this->getAllSettings();

            default:
                $this->getAllSks();
                $this->getAllSettings();
                break;
        }
    }

    private function getAllSks()
    {
        $this->sks_stripe = SettingStripeModel::getSks();
    }

    private function getAllSettings()
    {
        $this->codcliente = SettingStripeModel::getSetting('codcliente');
        $this->codproducto = SettingStripeModel::getSetting('codproducto');
        $this->enviarEmail = SettingStripeModel::getSetting('enviarEmail');
        $this->adminEmail = SettingStripeModel::getSetting('adminEmail');
        $this->mostrarStripeCus = SettingStripeModel::getSetting('mostrarStripeCus');
    }

    private function setSkStripe()
    {
        $data = $this->request->request->all();
        $name = $data['name'];
        $sk = $data['sk'];
        $codserie = $data['codserie'];

        if ($name !== null & $sk !== null) {
            SettingStripeModel::addSk($name, $sk, $codserie);
            $this->getAllSks();
            $this->toolBox()->log()->info('Guardado correctamente');
        } else {
            $this->toolbox()->log()->error('No se pudo guardar el SK');
        }

    }

    private function setSettings(){
        $data = $this->request->request->all();

        $this->codcliente = $data['codcliente'];
        $this->codproducto = $data['codproducto'];
        $this->enviarEmail = $data['enviarEmail'];
        $this->adminEmail = $data['adminEmail'];
        $this->mostrarStripeCus = $data['mostrarStripeCus'];

        $settings = [];

        if($this->codcliente !== null)
            $settings['codcliente'] = $this->codcliente;

        if($this->codproducto !== null)
            $settings['codproducto'] = $this->codproducto;

        if($this->enviarEmail !== null)
            $settings['enviarEmail'] = $this->enviarEmail;

        if($this->mostrarStripeCus !== null)
            $settings['mostrarStripeCus'] = $this->mostrarStripeCus;

        $settings['adminEmail'] = strlen($this->adminEmail) > 0 ? $this->adminEmail : Session::get('user')->email;

        SettingStripeModel::addSettings($settings);

//        sk_test_51ILOeaHDuQaJAlOmoxCwXO9mYqMKmXk6c9ByTDILdJ3vujXorxScbbyTNBrQeXb82oNeqq4UsioajKWiSaRMEGL700xoDW92tk


    }

    private function delSkStripe($name)
    {
        SettingStripeModel::removeSk($name);
        $this->getAllSks();
        $this->toolBox()->log()->info('Eliminado correctamente');
    }
}

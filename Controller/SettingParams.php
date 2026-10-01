<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\ImportadorStripe\Lib\StripeSettings;
use FacturaScripts\Core\Session;
use FacturaScripts\Plugins\ImportadorStripe\Model\StripeTransactionsQueue;

class SettingParams extends Controller
{
    public array $sks_stripe = [];
    public array $series = [];
    public string $codcliente = '';
    public string $codproducto = '';
    public string $clienteNombre = '';
    public string $productoNombre = '';
    public bool $enviarEmail;
    public string $satEmail = '';
    public string $adminEmail = '';
    public bool $mostrarStripeCus;
    public bool $remesasSEPA = false;
    public string $cuentaRemesaSEPA = '';
    public string $cuentaRemesaNombre = '';
    public bool $hayPluginRemesas = false;
    public bool $verifactu = false;
    public bool $hayPluginVerifactu = false;

    /**
     * @throws KernelException
     */
    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        if ($this->request->inputOrQuery('action') === 'autocomplete') {
            $this->setTemplate(false);
            $this->response->json($this->autocompleteAction());
            return;
        }

        $this->init();
    }

    /**
     * Devuelve los valores de cliente/producto para el widget de autocompletado.
     */
    protected function autocompleteAction(): array
    {
        $source = (string)$this->request->inputOrQuery('source', '');
        $fieldcode = (string)$this->request->inputOrQuery('fieldcode', '');
        $fieldtitle = (string)$this->request->inputOrQuery('fieldtitle', '');
        $term = (string)$this->request->inputOrQuery('term', '');

        $results = [];
        foreach (CodeModel::search($source, $fieldcode, $fieldtitle, $term) as $value) {
            $results[] = ['key' => $value->code, 'value' => $value->description];
        }

        return $results;
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Ajustes';
        $pageData['menu'] = 'Stripe';
        $pageData['icon'] = 'fa-solid fa-sliders';
        $pageData['showonmenu'] = true;
        return $pageData;
    }

    private function init(): void
    {
        $this->title='Configuración de Claves de stripe';
        AssetManager::addCss(FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.min.css');
        AssetManager::addJs(FS_ROUTE . '/node_modules/jquery-ui-dist/jquery-ui.min.js');
        AssetManager::addJs(FS_ROUTE . '/Dinamic/Assets/JS/WidgetAutocomplete.js');
        $action = $this->request->query->get('action');
        $serieModel = new Serie();
        $this->series = $serieModel->all();
        $this->hayPluginRemesas = StripeTransactionsQueue::canUseRemesas(true);
        $this->hayPluginVerifactu = StripeTransactionsQueue::canUseVerifactu(true);

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
                    Tools::log()->info('No se ha recibido el parametro necesario (name)');

                $this->getAllSettings();

            default:
                $this->getAllSks();
                $this->getAllSettings();
                break;
        }
    }

    private function getAllSks(): void
    {
        $this->sks_stripe = StripeSettings::getSks();
    }

    private function getAllSettings(): void
    {
        $this->codcliente = StripeSettings::getSetting('codcliente');
        $this->codproducto = StripeSettings::getSetting('codproducto');
        $this->enviarEmail = StripeSettings::getSetting('enviarEmail');
        $this->satEmail = StripeSettings::getSetting('satEmail') ?? Session::get('user')->email;
        $this->adminEmail = StripeSettings::getSetting('adminEmail') ?? Session::get('user')->email;
        $this->mostrarStripeCus = StripeSettings::getSetting('mostrarStripeCus');
        $this->remesasSEPA = StripeSettings::getSetting('remesasSEPA') ?? false;
        $this->cuentaRemesaSEPA = StripeSettings::getSetting('cuentaRemesaSEPA') ?? '';
        $this->verifactu = StripeSettings::getSetting('verifactu') ?? false;

        $codeModel = new CodeModel();
        $this->clienteNombre = $codeModel->getDescription('Cliente', 'codcliente', $this->codcliente, 'nombre');
        $this->productoNombre = $codeModel->getDescription('Producto', 'idproducto', $this->codproducto, 'referencia');
        $this->cuentaRemesaNombre = $codeModel->getDescription('cuentasbanco', 'codcuenta', $this->cuentaRemesaSEPA, 'descripcion');
    }

    private function setSkStripe(): void
    {
        $data = $this->request->request->all();
        $name = $data['name'];
        $sk = $data['sk'];
        $codserie = $data['codserie'];

        if ($name !== null & $sk !== null) {
            StripeSettings::addSk($name, $sk, $codserie);
            $this->getAllSks();
            Tools::log()->info('Guardado correctamente.');
        } else {
            Tools::log()->error('No se pudo guardar el SK.');
        }

    }

    private function setSettings(): void
    {
        $data = $this->request->request->all();

        $this->codcliente = $data['codcliente'];
        $this->codproducto = $data['codproducto'];
        $this->enviarEmail = $data['enviarEmail'];

        $this->satEmail = $data['satEmail'];
        $this->adminEmail = $data['adminEmail'];
        $this->mostrarStripeCus = $data['mostrarStripeCus'];
        $this->remesasSEPA = $data['remesasSEPA'];
        $this->cuentaRemesaSEPA = $data['cuentaRemesaSEPA'];
        $this->verifactu = $data['verifactu'];

        $settings = [];

        if($this->codcliente !== null)
            $settings['codcliente'] = $this->codcliente;

        if($this->codproducto !== null)
            $settings['codproducto'] = $this->codproducto;

        if($this->enviarEmail !== null)
            $settings['enviarEmail'] = $this->enviarEmail;

        if($this->mostrarStripeCus !== null)
            $settings['mostrarStripeCus'] = $this->mostrarStripeCus;


        if ($this->remesasSEPA !== '0') {
            if (!Plugins::isInstalled('RemesasSEPA')){
                Tools::log()->error('No tienes instalado el plugin Remesas SEPA.');
                return;
            }
            if (!Plugins::isEnabled('RemesasSEPA')){
                Tools::log()->error('No tienes activado el plugin Remesas SEPA.');
                return;
            }
        }

        $settings['remesasSEPA'] = $this->remesasSEPA;

        if($this->cuentaRemesaSEPA !== null)
            $settings['cuentaRemesaSEPA'] = $this->cuentaRemesaSEPA;


        if ($this->verifactu && $this->verifactu !== '0') {
            if (!Plugins::isInstalled('Verifactu')) {
                Tools::log()->error('No tienes instalado el plugin Verifactu.');
                return;
            }
            if (!Plugins::isEnabled('Verifactu')) {
                Tools::log()->error('No tienes activado el plugin Verifactu.');
                return;
            }
        }
        $settings['verifactu'] = $this->verifactu;

        $settings['satEmail'] = strlen($this->satEmail) > 0 ? $this->satEmail : Session::get('user')->email;
        $settings['adminEmail'] = strlen($this->adminEmail) > 0 ? $this->adminEmail : Session::get('user')->email;

        StripeSettings::addSettings($settings);

        Tools::log()->info('Guardado correctamente.');

    }

    private function delSkStripe($name): void
    {
        StripeSettings::removeSk($name);
        $this->getAllSks();
        Tools::log()->info('Eliminado correctamente');
    }
}

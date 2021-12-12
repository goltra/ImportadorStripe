<?php
/*
 * Copyright (c) 2021.
 * Desarrollado desde Goltratec S.L.
 * @author Francisco José García Alonso
 */

namespace FacturaScripts\Plugins\ImportadorStripe\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Plugins\ImportadorStripe\Model\SettingStripeModel;

class SettingParams extends Controller
{

    public $sks_stripe = [];

    public function privateCore(&$response, $user, $permissions)
    {
        $this->init();
        parent::privateCore($response, $user, $permissions);

    }
    public function getPageData()
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
        switch ($action) {
            case 'add':
                $this->setSkStripe();
                break;
            case 'del':
                $name = $this->request->query->get('name');
                if ($name !== null)
                    $this->delSkStripe($name);
                else
                    $this->toolBox()->log()->error('No se ha recibido el parametro necesario (name)');
            default:
                $this->getAllSks();
                break;
        }
    }

    private function getAllSks()
    {
        $this->sks_stripe = SettingStripeModel::getSks();
    }

    private function setSkStripe()
    {
        $data = $this->request->request->all();
        $name = $data['name'] ?? $data['name'];
        $sk = $data['sk'] ?? ['sk'];

        if ($name !== null & $sk !== null) {
            SettingStripeModel::addSk($name, $sk);
            $this->getAllSks();
            $this->toolBox()->log()->info('Guardado correctamente');
        } else {
            $this->toolbox()->log()->error('No se pudo guardar el SK');
        }
    }

    private function delSkStripe($name)
    {
        SettingStripeModel::removeSk($name);
        $this->getAllSks();
        $this->toolBox()->log()->info('Eliminado correctamente');
    }
}

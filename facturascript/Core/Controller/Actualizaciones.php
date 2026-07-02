<?php

/**
 * MOD ERICK - Pantalla de actualizaciones para usuarios no administradores.
 */

namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;

class Actualizaciones extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'updates';
        $data['icon'] = 'fas fa-sync-alt';
        return $data;
    }

    public function getAllVersions(): array
    {
        $list = [];
        try {
            if (Plugins::isEnabled('erp_interiberica') && class_exists('FacturaScripts\Dinamic\Model\Version')) {
                $version = new \FacturaScripts\Dinamic\Model\Version();
                $where = [];
                foreach ($version->all($where, ['id' => 'DESC']) as $ver) {
                    $list[] = $ver;
                }
            }
        } catch (\Exception $e) {
            Tools::log()->warning('Error getting versions: ' . $e->getMessage());
        }
        return $list;
    }

    public function getVersionActual()
    {
        try {
            if (Plugins::isEnabled('erp_interiberica') && class_exists('FacturaScripts\Dinamic\Model\Version')) {
                $version = new \FacturaScripts\Dinamic\Model\Version();
                $where = [new DataBaseWhere('estado', 1)];
                foreach ($version->all($where, ['id' => 'DESC']) as $act) {
                    return $act;
                }
            }
        } catch (\Exception $e) {
            Tools::log()->warning('Error getting current version: ' . $e->getMessage());
        }
        return null;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
    }
}

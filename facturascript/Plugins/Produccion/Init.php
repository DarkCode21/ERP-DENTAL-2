<?php
/**
 * This file is part of Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Settings;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\StockMovementManager;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\MigrateData;
use FacturaScripts\Plugins\Produccion\Lib\Produccion\MigrateReference;
use FacturaScripts\Plugins\Produccion\Model\OrdenProduccion;
use FacturaScripts\Plugins\Produccion\Model\RecetaHistorial;

/**
 * Description of Init
 *
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class Init extends InitClass
{
    /**
     * Code to load every time FacturaScripts starts.
     */
    public function init(): void
    {
        $this->loadExtension(new Extension\Model\Producto());
        $this->loadExtension(new Extension\Model\Variante());
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
        StockMovementManager::addMod(new Lib\Produccion\StockMovementManager());

        if (Plugins::isEnabled('PlantillasPDF')) {
            $this->loadExtension(new Extension\Lib\PlantillasPDF\Helper\BusinessDocLinesHelper());
        }
    }

    /**
     * Code that is executed when uninstalling a plugin.
     */
    public function uninstall(): void
    {
    }

    /**
     * Code to load every time the plugin is enabled or updated.
     */
    public function update(): void
    {
        Tools::log()->info('production-update-proccess');
        // Set default user nick to existing production orders without user.
        $database = new DataBase();
        $database->exec(
            'update produccion_ordenes set nick = (select t1.nick from users t1 where t1.admin limit 1) where nick is null or nick = \'\''
        );

        // Migrate settings to the new group 'production'.
        $this->migrateSettings();

        // Migrate old data (FS2015) to the new structure (FS2020).
        $migrateData = new MigrateData();
        if ($migrateData->run()) {
            $migrateData->deleteOldTables();
        }

        // Migrate reference from recipes to recipe products.
        $migrateReference = new MigrateReference();
        $count = $migrateReference->run();
        if ($count > 0) {
            Tools::log()->error('reference-migrate-error');
        } else if ($count === 0) {
            Tools::log()->notice('reference-migrate-complete', ['%count%' => $count]);
        }

        // Add historical of production orders if historial is empty.
        $where = [ new DataBaseWhere('docmodel', 'OrdenProduccion') ];
        $historical = new RecetaHistorial();
        if (false === $historical->loadWhere($where)) {
            $this->addOrdersToHistorical();
        }
    }

    private function addOrdersToHistorical(): void
    {
        $where = [ new DataBaseWhere('estado', OrdenProduccion::STATUS_FINISHED) ];
        $orderBy = [
            'fecha' => 'ASC',
            'hora' => 'ASC'
        ];
        foreach (OrdenProduccion::all($where, $orderBy) as $order) {
            $historical = new RecetaHistorial();
            $historical->cantidad = 1;
            $historical->docmodel = $order->modelClassName();
            $historical->idreceta = $order->idreceta;
            $historical->fecha = $order->fecha;
            $historical->hora = $order->hora;
            $historical->save();
        }
    }

    /**
     * Move default settings to the new config group: 'production'.
     *
     * @return void
     */
    private function migrateSettings(): void
    {
        $settings = new Settings();
        $settings->load('production');
        $settings->name = 'production';
        $settings->costupdatepolicy = Tools::settings('production', 'costupdatepolicy', 1);
        $settings->duplicatecode = Tools::settings('production', 'duplicatecode', 1);
        $settings->printrecipecost = Tools::settings('production', 'printrecipecost', true);
        $settings->confirmproductionorder = Tools::settings('production', 'confirmproductionorder', false);
        $settings->quantitydecimalorder = Tools::settings('production', 'quantitydecimalorder', false);
        $settings->numserieseparator = Tools::settings('production', 'numserieseparator', '');
        $settings->purchasescounter = Tools::settings('production', 'purchasescounter', 0);
        $settings->save();
    }
}

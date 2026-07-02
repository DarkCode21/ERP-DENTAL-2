<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Tarifa;
use FacturaScripts\Plugins\TarifasAvanzadas\Model\TarifaFamilia;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditGrupoClientes());
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Controller\EditTarifa());
        $this->loadExtension(new Extension\Controller\ListTarifa());
        $this->loadExtension(new Extension\Model\Base\SalesDocument());
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        $this->migrate2017();
        $this->setConfig();
    }

    private function migrate2017(): void
    {
        $database = new DataBase();
        if (false === $database->tableExists('tarifasav')) {
            return;
        }

        $sql = "SELECT * FROM tarifasav WHERE madre IS NOT NULL;";
        foreach ($database->select($sql) as $row) {
            $tarifa = new Tarifa();
            if (false === $tarifa->loadFromCode($row['madre'])) {
                continue;
            }

            $tarFam = new TarifaFamilia();
            $where = [
                new DataBaseWhere('codfamilia', $row['codfamilia']),
                new DataBaseWhere('codtarifa', $row['madre'])
            ];
            if ($tarFam->loadFromCode('', $where)) {
                continue;
            }

            // family exists?
            $family = new Familia();
            if ($row['codfamilia'] && false === $family->loadFromCode($row['codfamilia'])) {
                continue;
            }

            $tarFam->codfamilia = $row['codfamilia'];
            $tarFam->codtarifa = $row['madre'];

            if (Utils::str2bool($row['margen'])) {
                $tarFam->aplicar = TarifaFamilia::APPLY_COST;
                $tarFam->valorx = (float)$row['incporcentual'];
                $tarFam->valory = (float)$row['inclineal'];
                $tarFam->save();
                continue;
            }

            $tarFam->aplicar = TarifaFamilia::APPLY_PRICE;
            $tarFam->valorx = 0 - (float)$row['incporcentual'];
            $tarFam->valory = 0 - (float)$row['inclineal'];
            $tarFam->save();
        }

        // delete inconsistent data
        if ($database->tableExists('articulostarifas')) {
            $database->exec('DELETE FROM articulostarifas WHERE referencia NOT IN (SELECT referencia FROM productos);');
        }
    }

    private function setConfig(): void
    {
        Tools::settings('default', 'leveltarifasav', 0);
        Tools::settingsSave();
    }
}

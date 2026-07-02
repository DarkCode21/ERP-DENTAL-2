<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlazosPago;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\PlazosPago\Model\FormaPagoPlazo;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
final class Init extends InitClass
{
    public function init(): void
    {
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // migrate old data from plazos table
        $dataBase = new DataBase();
        if (!$dataBase->tableExists('plazos')) {
            return;
        }

        foreach ($dataBase->select('SELECT * FROM plazos;') as $row) {
            $plazo = new FormaPagoPlazo($row);
            $plazo->save();
        }

        $dataBase->exec('DELETE FROM plazos;');
    }
}

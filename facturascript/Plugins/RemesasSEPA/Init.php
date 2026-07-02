<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\RemesasSEPA;

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\RemesasSEPA\Model\RemesaSEPA;

/**
 * Description of Init
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
final class Init extends InitClass
{
    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditReciboCliente());
        $this->loadExtension(new Extension\Model\CuentaBancoCliente());
        $this->loadExtension(new Extension\Model\PagoCliente());
        $this->loadExtension(new Extension\Model\ReciboCliente());
    }

    public function update()
    {
        new RemesaSEPA();

        // updates previous data
        $dataBase = new DataBase();
        $idempresa = Tools::settings('default', 'idempresa');
        $sql = "UPDATE " . RemesaSEPA::tableName() . " SET tipo = " . $dataBase->var2str('CORE') . " WHERE tipo IS NULL;"
            . "UPDATE " . RemesaSEPA::tableName() . " SET idempresa = " . $dataBase->var2str($idempresa) . " WHERE idempresa IS NULL;";
        $dataBase->exec($sql);
    }
}

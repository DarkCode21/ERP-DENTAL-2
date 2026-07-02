<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria;

use FacturaScripts\Core\Base\InitClass; #MOD ERICK
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ManualTemplates\BankingMovements;
use FacturaScripts\Dinamic\Model\MovimientoBanco;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Model\Asiento());
        $this->loadExtension(new Extension\Model\ReciboCliente());
        $this->loadExtension(new Extension\Controller\EditRemesaSEPA());
        $this->loadExtension(new Extension\Controller\EditCSVfile());
        $this->loadExtension(new Extension\Controller\EditCuentaBanco());

        // comprobamos si en la clase CSVfile existe el método addManualTemplate
        if (method_exists(CSVfile::class, 'addManualTemplate')) {
            CSVfile::addManualTemplate('banking-movements', new BankingMovements());
        } else {
            Tools::log()->warning('update-plugin-csvimport');
        }
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        new MovimientoBanco();
    }
}

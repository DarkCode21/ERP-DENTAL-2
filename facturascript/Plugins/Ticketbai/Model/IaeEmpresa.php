<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CodigoIae;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;

/**
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class IaeEmpresa extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $idempresa;

    /** @var int */
    public $idiae;

    public function delete(): bool
    {
        // si hay líneas de factura de cliente asociadas a este IAE, no se puede eliminar
        $lineModel = new LineaFacturaCliente();
        $where = [new DataBaseWhere('tbai_idiae', $this->idiae)];
        if ($lineModel->count($where) > 0) {
            Tools::log()->warning('ticketbai-iae-exists-in-invoices');
            return false;
        }

        return parent::delete();
    }

    public function getIAE(): CodigoIae
    {
        $iae = new CodigoIae();
        $iae->loadFromCode($this->idiae);
        return $iae;
    }

    public function install(): string
    {
        // needed dependencies
        new Empresa();
        new CodigoIae();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'iae_empresas';
    }
}

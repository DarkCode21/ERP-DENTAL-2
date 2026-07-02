<?php
/**
 * Copyright (C) 2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Ticketbai\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Provincia;

/**
 * @author Alayn Gortazar Huete - Barnetik Koop <alayn@barnetik.com>
 */
class CodigoIae extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $idiae;

    /** @var string */
    public $codpais;

    /** @var int */
    public $idprovincia;

    /** @var string */
    public $iae;

    /** @var string */
    public $descripcion;

    public function delete(): bool
    {
        // eliminamos todas las asociaciones con empresas
        $iaeCompanyModel = new IaeEmpresa();
        $where = [new DataBaseWhere('idiae', $this->idiae)];
        foreach ($iaeCompanyModel->all($where, [], 0, 0) as $iaeCompany) {
            if (false === $iaeCompany->delete()) {
                return false;
            }
        }

        return parent::delete();
    }

    public function install(): string
    {
        // needed dependencies
        new Pais();
        new Provincia();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return "idiae";
    }

    public static function tableName(): string
    {
        return "codigos_iae";
    }

    public function url(string $type = 'auto', string $list = 'EditSettings?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}

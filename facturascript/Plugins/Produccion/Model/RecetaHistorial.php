<?php
/**
 * This file is part of the Produccion plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Produccion      Copyright (C) 2020-2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 * All Rights Reserved.
 */
namespace FacturaScripts\Plugins\Produccion\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;

/**
 * Class that manages the history of the production of a recipe.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class RecetaHistorial extends ModelClass
{
    use ModelTrait;

    /** @var float */
    public $cantidad;

    /** @var string */
    public $docmodel;

    /** @var int */
    public $id;

    /**
     * Link to the Recipe model.
     * @var int
     */
    public $idreceta;

    /** @var string */
    public $fecha;

    /** @var string */
    public $hora;

    /**
     * Link to the User model.
     * @var string
     */
    public $nick;

    /**
     * Reset the values of all model properties.
     */
    public function clear(): void
    {
        parent::clear();
        $this->cantidad = 0;
        $this->fecha = Date(Tools::DATE_STYLE);
        $this->hora = Date(Tools::HOUR_STYLE);
        $this->nick = Session::user()->nick;
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'produccion_recetashistorial';
    }
}

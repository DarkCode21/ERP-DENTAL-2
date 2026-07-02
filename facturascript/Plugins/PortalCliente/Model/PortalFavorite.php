<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Producto;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalFavorite extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idcontacto;

    /** @var int */
    public $idproducto;

    public function getContact(): Contacto
    {
        $model = new Contacto();
        $model->loadFromCode($this->idcontacto);
        return $model;
    }

    public function getProduct(): Producto
    {
        $model = new Producto();
        $model->loadFromCode($this->idproducto);
        return $model;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "portal_favorites";
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if (false === empty($this->id)) {
            return 'EditContacto?activetab=ListPortalFavoriteLine&code=' . $this->id;
        }

        if ($type === 'list') {
            return 'ListPortalCliente?activetab=ListPortalFavorite';
        }

        if ($type === 'edit') {
            return 'EditContacto?activetab=ListPortalFavoriteLine&code=' . $this->idcontacto;
        }

        return parent::url($type, $list);
    }
}
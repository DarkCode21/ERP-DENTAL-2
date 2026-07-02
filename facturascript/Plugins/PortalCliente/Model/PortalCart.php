<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalCart extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $creation_date;

    /** @var int */
    public $id;

    /** @var int */
    public $idcontacto;

    /** @var int */
    public $idvariante;

    /** @var string */
    public $last_update;

    /** @var float */
    public $quantity;

    public function clear()
    {
        parent::clear();
        $this->quantity = 0.0;
    }

    public function getContact(): Contacto
    {
        $model = new Contacto();
        $model->loadFromCode($this->idcontacto);
        return $model;
    }

    public function getVariant(): Variante
    {
        $model = new Variante();
        $model->loadFromCode($this->idvariante);
        return $model;
    }

    public static function primaryColumn(): string
    {
        return "id";
    }

    public static function tableName(): string
    {
        return "portal_carts";
    }

    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if (false === empty($this->id)) {
            return 'EditContacto?activetab=ListPortalCartLine&code=' . $this->id;
        }

        if ($type === 'list') {
            return 'ListPortalCliente?activetab=ListPortalCart';
        }

        if ($type === 'edit') {
            return 'EditContacto?activetab=ListPortalCartLine&code=' . $this->idcontacto;
        }

        return parent::url($type, $list);
    }

    protected function saveUpdate(array $values = []): bool
    {
        $this->last_update = Tools::dateTime();
        return parent::saveUpdate($values);
    }
}
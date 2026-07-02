<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;

/**
 * Description of CrmFuente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class CrmFuente extends Base\ModelClass
{

    use Base\ModelTrait;

    /**
     * @var string
     */
    public $descripcion;

    /**
     * @var string
     */
    public $fecha;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $nombre;

    /**
     * @var int
     */
    public $numcontactos;

    public function clear()
    {
        parent::clear();
        $this->fecha = date(self::DATE_STYLE);
        $this->numcontactos = 0;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public static function tableName(): string
    {
        return 'crm_fuentes2';
    }

    public function test(): bool
    {
        $this->descripcion = $this->toolBox()->utils()->noHtml($this->descripcion);
        $this->nombre = $this->toolBox()->utils()->noHtml($this->nombre);
        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListContacto?activetab=List'): string
    {
        return parent::url($type, $list);
    }

    protected function saveUpdate(array $values = []): bool
    {
        // get the number of contacts with this source
        $contact = new Contacto();
        $where = [new DataBaseWhere('idfuente', $this->primaryColumnValue())];
        $this->numcontactos = $contact->count($where);

        return parent::saveUpdate($values);
    }
}

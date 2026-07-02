<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Estación de preparación (Cocina, Bar, etc.).
 * Cada estación agrupa familias de producto para filtrar el panel de comandas.
 */
class RestEstacion extends ModelClass
{
    use ModelTrait;

    /** @var string */
    public $descripcion;

    /** @var string Tipo de conexión ESC/POS para KOT: 'tcp' o 'usb' */
    public $escpos_tipo;

    /** @var string IP de la impresora ESC/POS para KOT (solo si tipo=tcp) */
    public $escpos_ip;

    /** @var int Puerto TCP de la impresora ESC/POS para KOT */
    public $escpos_port;

    /** @var string Ruta del dispositivo USB (solo si tipo=usb, ej: /dev/usb/lp0 o LPT1:) */
    public $escpos_usb;

    /** @var string URL del relay ESC/POS de esta estación (vacío = usar el global de ajustes) */
    public $escpos_relay_url;

    /** @var int */
    public $idestacion;

    /** @var string */
    public $nombre;

    public static function primaryColumn(): string
    {
        return 'idestacion';
    }

    public static function tableName(): string
    {
        return 'rest_estaciones';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'nombre';
    }

    public function url(string $type = 'auto', string $list = 'AjustesRestauranteTPV#List'): string
    {
        return parent::url($type, $list);
    }

    public function clear(): void
    {
        parent::clear();
        $this->descripcion = '';
        $this->escpos_tipo      = 'tcp';
        $this->escpos_ip       = '';
        $this->escpos_port     = 9100;
        $this->escpos_usb      = '';
        $this->escpos_relay_url = '';
        $this->nombre          = '';
    }

    /**
     * Devuelve los códigos de familia asignados a esta estación.
     * @return string[]
     */
    public function getFamilias(): array
    {
        $db = new \FacturaScripts\Core\Base\DataBase();
        $sql = 'SELECT codfamilia FROM rest_estacion_familias WHERE idestacion = ' . $db->var2str($this->idestacion);
        $result = [];
        foreach ($db->select($sql) as $row) {
            $result[] = $row['codfamilia'];
        }
        return $result;
    }

    public function test(): bool
    {
        if (empty($this->nombre)) {
            self::toolBox()::log()->error('El nombre de la estación no puede estar vacío.');
            return false;
        }
        return parent::test();
    }
}

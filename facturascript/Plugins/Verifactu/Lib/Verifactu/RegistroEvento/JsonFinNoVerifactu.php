<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroEvento;

use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonTrait;

/**
 * Clase para generar el JSON de fin del modo No-Verifactu.
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonFinNoVerifactu
{
    use JsonTrait;
    use JsonRegistroEventoTrait;

    const FILE_NAME = 'fin-no-verifactu';

    public static function generate(Ejercicio $exercise): bool
    {
        self::$company = $exercise->getCompany();
        self::$exercise = $exercise;

        // si el ejercicio no existe, no hacemos nada
        if (!self::$exercise->exists()) {
            return true;
        } elseif (!self::$company->verifactuIsConfigured(false)) {
            // si la empresa no está configurada para Verifactu, no hacemos nada
            return true;
        }

        if (false === self::init()) {
            return false;
        }

        if (false === self::jsonTipoEvento()) {
            return false;
        }

        if (false === self::save(self::FILE_NAME)) {
            return false;
        }

        return true;
    }

    private static function jsonTipoEvento(): bool
    {
        self::$json['Evento']['TipoEvento'] = '02';
        return true;
    }
}
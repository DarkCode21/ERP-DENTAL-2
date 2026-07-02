<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib\Verifactu\RegistroFactura;

use FacturaScripts\Core\Tools;

/**
 * Clase para validar un JSON de alta, subsanación o anulación de factura en Verifactu.
 * Validaciones realizadas a fecha del 07-08-2025 en la versión 1.1.2 del registro de validaciones de Verifactu.
 * https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Validaciones_Errores_Veri-Factu.pdf
 *
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class JsonValidate
{
    public static function validate(array $json): bool
    {
        if (empty($json)) {
            Tools::log()->error('json-empty');
            return false;
        }

        $event = isset($json['RegistroAlta']) ? 'RegistroAlta' : (isset($json['RegistroAnulacion']) ? 'RegistroAnulacion' : null);
        if (empty($event)) {
            Tools::log()->error('json-not-event-found', [
                '%event%' => $event,
            ]);
            return false;
        }

        if ($event === 'RegistroAlta') {
            return JsonValidateRegistroAlta::validate($json);
        }

        if ($event === 'RegistroAnulacion') {
            return JsonValidateRegistroAnulacion::validate($json);
        }

        return false;
    }
}
<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Lib;

use FacturaScripts\Core\Lib\FiscalNumberValidator as parentFiscalNumberValidator;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class FiscalNumberValidator extends parentFiscalNumberValidator
{
    public static function normaliceCifNif(string $cifNif, string $pattern = '', string $default = ''): string
    {
        if (empty($cifNif)) {
            return $default;
        }

        // Eliminar lo que no sea alfanumérico
        $nif = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $cifNif));

        // Aplicar patrón si se proporciona
        if (!empty($pattern)) {
            if (!preg_match($pattern, $nif)) {
                return $default;
            }
        }

        return $nif;
    }
}
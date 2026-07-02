<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Model\Base\SalesDocument;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalDocShare
{
    public static function checkCode($doc, string $code): bool
    {
        if (false === $doc instanceof SalesDocument) {
            return false;
        }

        return $code === self::getCode($doc);
    }

    public static function getCode($doc): string
    {
        if (false === $doc instanceof SalesDocument) {
            return '';
        }

        return md5($doc->primaryColumnValue());
    }
}
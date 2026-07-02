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

namespace FacturaScripts\Plugins\RestauranteTPV\Extension\Controller;

use Closure;

/**
 * Extensión vacía. La gestión de familias usa el campo nativo codfamilia de FacturaScripts.
 */
class EditProducto
{
    public function createViews(): Closure
    {
        return function () {
            // sin cambios: el campo codfamilia es nativo de FacturaScripts
        };
    }
}

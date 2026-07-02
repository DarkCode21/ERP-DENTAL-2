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

namespace FacturaScripts\Plugins\RestauranteTPV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditRestModificador extends EditController
{
    public function getModelClassName(): string
    {
        return 'RestModificador';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'modifier';
        $data['icon']       = 'fa-solid fa-sliders';
        $data['showonmenu'] = false;
        return $data;
    }
}

<?php
/**
 * This file is part of Amortizaciones plugin for FacturaScripts.
 * FacturaScripts  Copyright (C) 2015-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 * Amortizaciones  Copyright (C) 2023-2024 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\Amortizaciones;

use FacturaScripts\Core\Base\InitClass;

/**
 * Description of Init
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Init extends InitClass
{

    public function init()
    {
        $this->loadExtension(new Extension\Model\Asiento());
    }

    public function update()
    {
    }
}

<?php
/**
 * Load Extension and mods 
 * @author Raúl Jiménez <raljopa@gmail.com>
 */
namespace FacturaScripts\Plugins\fsrettotal;

use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Core\Base\Calculator;
class Init extends InitClass
{
    public function init()
    {
        Calculator::addMod(new Mod\CalculatorMod());
    }
    public function update()
    {

    }
}
<?php
/**
 * Copyright (C) 2023 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\Traducciones\Extension\Controller;

use Closure;
use FacturaScripts\Plugins\Traducciones\Lib\LanguageTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditUser
{
    public function loadData(): Closure
    {
        return function($viewName, $view) {
            if ($viewName === 'EditUser') {
                $this->loadLanguages($viewName);
            }
        };
    }

    protected function loadLanguages(): Closure
    {
        return function (string $viewName) {
            $columnLangCode = $this->views[$viewName]->columnForName('language');
            if (empty($columnLangCode) || $columnLangCode->widget->getType() !== 'select') {
                return;
            }

            $langs = [];
            foreach (LanguageTrait::getAvailableLanguages() as $lang) {
                $langs[] = ['value' => $lang['codicu'], 'title' => $lang['title']];
            }

            $columnLangCode->widget->setValuesFromArray($langs, false);
        };
    }
}
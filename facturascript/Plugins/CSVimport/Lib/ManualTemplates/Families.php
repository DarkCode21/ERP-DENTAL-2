<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * Description of Families
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Families extends ManualTemplateClass implements ManualTemplateInterface
{
    public function getDataFields(): array
    {
        return [
            'codfamilia' => ['title' => 'code'],
            'descripcion' => ['title' => 'description']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'families';
    }

    public function getRequiredFieldsAnd(): array
    {
        return ['codfamilia'];
    }

    public function getRequiredFieldsOr(): array
    {
        return [];
    }

    public function importItem(array $item): bool
    {
        if (!isset($item['codfamilia']) || empty($item['codfamilia'])) {
            Tools::log()->warning('field-required', ['%field%' => 'codfamilia']);
            return false;
        }

        $family = new Familia();
        if ($family->loadFromCode($item['codfamilia']) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $family->loadFromCode($item['codfamilia']) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        $this->setModelValues($family, $item, '');
        return $family->save();
    }
}

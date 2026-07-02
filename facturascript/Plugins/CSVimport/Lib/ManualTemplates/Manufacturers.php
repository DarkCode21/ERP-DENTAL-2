<?php
/**
 * Copyright (C) 2020-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Lib\ManualTemplates;

use FacturaScripts\Dinamic\Model\Fabricante;
use FacturaScripts\Plugins\CSVimport\Contract\ManualTemplateInterface;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Manufacturers extends ManualTemplateClass implements ManualTemplateInterface
{
    /**
     *
     * @return array
     */
    public function getDataFields(): array
    {
        return [
            'codfabricante' => ['title' => 'code'],
            'nombre' => ['title' => 'name']
        ];
    }

    public function getFieldsToColumn(): array
    {
        return [];
    }

    public static function getProfile(): string
    {
        return 'manufacturers';
    }

    public function getRequiredFieldsAnd(): array
    {
        return ['codfabricante'];
    }

    public function getRequiredFieldsOr(): array
    {
        return [];
    }

    /**
     *
     * @param array $item
     *
     * @return bool
     */
    public function importItem(array $item): bool
    {
        if (!isset($item['codfabricante']) || empty($item['codfabricante'])) {
            return false;
        }

        $manufacturer = new Fabricante();
        if ($manufacturer->loadFromCode($item['codfabricante']) && $this->model->mode === CsvFileTools::INSERT_MODE
            || false === $manufacturer->loadFromCode($item['codfabricante']) && $this->model->mode === CsvFileTools::UPDATE_MODE) {
            return false;
        }

        $this->setModelValues($manufacturer, $item, '');
        return $manufacturer->save();
    }
}

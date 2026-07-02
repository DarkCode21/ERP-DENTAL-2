<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Trazabilidad\Lib\Widget;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Lib\Widget\WidgetSelect as parentWidget;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class WidgetSelectTraza extends parentWidget
{
    /** @var string */
    protected $fieldfilter;

    /** @var string */
    protected $onchange;

    /** @var string */
    protected $parent;

    public function __construct($data)
    {
        $this->source = $data['source'] ?? '';
        $this->parent = $data['parent'] ?? '';
        $this->onchange = $data['onchange'] ?? '';
        parent::__construct($data);
    }

    public function getDataSource(): array
    {
        $data = parent::getDataSource();
        $data['fieldfilter'] = $this->fieldfilter;
        return $data;
    }

    /**
     * Loads the value list from a given array.
     * The array must have one of the two following structures:
     * - If it's a value array, it must uses the value of each element as title and value
     * - If it's a multidimensional array, the indexes value and title must be set for each element
     *
     * @param array $items
     * @param bool $translate
     * @param bool $addEmpty
     * @param string $col1
     * @param string $col2
     * @param string $col3
     */
    public function setValuesFromArray($items, $translate = false, $addEmpty = false, $col1 = 'value', $col2 = 'title', $col3 = 'disabled')
    {
        $this->values = $addEmpty ? [['value' => null, 'title' => '------']] : [];
        foreach ($items as $item) {
            if (false === is_array($item)) {
                $this->values[] = ['value' => $item, 'title' => $item, 'disabled' => false];
                continue;
            } elseif (isset($item['tag']) && $item['tag'] !== 'values') {
                continue;
            }

            if (isset($item[$col1])) {
                $this->values[] = [
                    'value' => $item[$col1],
                    'title' => $item[$col2] ?? $item[$col1],
                    'disabled' => $item[$col3] ?? false,
                ];
            }
        }

        if ($translate) {
            $this->applyTranslations();
        }
    }

    protected function assets(): void
    {
        AssetManager::add('js', FS_ROUTE . '/Dinamic/Assets/JS/WidgetSelectTraza.js');
    }

    protected function inputHtml($type = 'text', $extraClass = '')
    {
        $class = $this->combineClasses($this->css('form-control'), $this->class, $extraClass);

        if ($this->parent != '') {
            $class = $class . ' parentSelectTraza';
        }

        if ($this->readonly()) {
            return '<input type="hidden" name="' . $this->fieldname . '" value="' . $this->value . '"/>'
                . '<input type="text" value="' . $this->show() . '" class="' . $class . '" readonly=""/>';
        }

        $found = false;
        $html = '<select'
            . ' name="' . $this->fieldname . '"'
            . ' id="' . $this->id . '"'
            . ' class="' . $class . '"'
            . $this->inputHtmlExtraParams()
            . ' parent="' . $this->parent . '"'
            . ' value="' . $this->value . '"'
            . ' data-field="' . $this->fieldname . '"'
            . ' data-source="' . $this->source . '"'
            . ' data-fieldcode="' . $this->fieldcode . '"'
            . ' data-fieldtitle="' . $this->fieldtitle . '"'
            . ' data-fieldfilter="' . $this->fieldfilter . '"'
            . ' onchange="' . $this->onchange . '"'
            . ' data-onloadTraza="' . $this->onchange . '"'
            . '>';
        foreach ($this->values as $option) {
            $title = empty($option['title']) ? $option['value'] : $option['title'];
            $disabled = isset($option['disabled']) && $option['disabled'] ? ' disabled' : '';

            // don't use strict comparison (===)
            if ($option['value'] == $this->value) {
                $found = true;
                $html .= '<option value="' . $option['value'] . '" selected="" ' . $disabled . '>' . $title . '</option>';
                continue;
            }

            $html .= '<option value="' . $option['value'] . '" ' . $disabled . '>' . $title . '</option>';
        }

        // value not found?
        if (!$found && !empty($this->value)) {
            $html .= '<option value="' . $this->value . '" selected="">'
                . static::$codeModel->getDescription($this->source, $this->fieldcode, $this->value, $this->fieldtitle)
                . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    protected function setSourceData(array $child, bool $loadData = true)
    {
        $this->fieldfilter = $child['fieldfilter'] ?? $this->fieldfilter;
        parent::setSourceData($child, $loadData);
    }

    /**
     *  Translate the fixed titles, if they exist
     */
    private function applyTranslations(): void
    {
        foreach ($this->values as $key => $value) {
            if (empty($value['title']) || '------' === $value['title']) {
                continue;
            }

            $this->values[$key]['title'] = Tools::lang()->trans($value['title']);
        }
    }
}

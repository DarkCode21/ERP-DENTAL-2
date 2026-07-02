<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FormatoDocumento;

class EditFormatoDocumento
{
    public function execPreviousAction(): Closure
    {
        return function (string $action) {
            if ($action !== 'copy-format') {
                return;
            } elseif (false === $this->validateFormToken()) {
                return;
            }

            $model = $this->getModel();
            if (false === $model->loadFromCode($this->request->get('code'))) {
                return;
            }

            $newFormat = new FormatoDocumento();
            $newFormat->loadFromData($model->toArray(), ['id', 'nombre']);
            $newFormat->nombre = Tools::lang()->trans('copy-reply') . ' ' . $model->nombre;

            // nos quedamos con los primeros 30 caracteres del nombre
            $newFormat->nombre = substr($newFormat->nombre, 0, 30);

            if (false === $newFormat->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            Tools::log()->notice('record-updated-correctly');
            $this->redirect($newFormat->url() . '&action=save-ok');
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $this->loadImageFiles($view, 'image-text');
            $this->loadImageFiles($view, 'image-footer');

            $this->addButton($viewName, [
                'action' => 'copy-format',
                'icon' => 'fas fa-cut',
                'label' => 'copy'
            ]);
        };
    }

    protected function loadImageFiles(): Closure
    {
        return function ($view, $column) {
            $column = $view->columnForName($column);
            if ($column && $column->widget->getType() === 'select') {
                $images = $this->codeModel->all('attached_files', 'idfile', 'filename', true, [
                    new DataBaseWhere('mimetype', 'image/gif,image/jpeg,image/png', 'IN')
                ]);
                $column->widget->setValuesFromCodeModel($images);
            }
        };
    }
}

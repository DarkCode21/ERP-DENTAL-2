<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\InformeSII\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditEmpresa
{
    public function execPreviousAction(): Closure
    {
        return function ($action) {
            $tab = $this->request->request->get('activetab');
            if ($tab !== $this->getMainViewName()) {
                return;
            }

            switch ($action) {
                case 'upload-new-file':
                    $this->uploadNewFileAction();
                    break;
            }
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName === $this->getMainViewName()
                && $view->model->exists()
                && false === empty($view->model->signature)) {
                $this->addButton($viewName, [
                    'action' => 'upload-new-file',
                    'color' => 'warning',
                    'icon' => 'fas fa-file-upload',
                    'label' => 'signature',
                    'type' => 'modal'
                ]);
            }
        };
    }

    protected function uploadNewFileAction(): Closure
    {
        return function () {
            if (false === $this->validateFormToken()) {
                return;
            }

            // obtenemos el modelo
            $model = $this->getViewModel();
            if (false === $model->loadFromCode($this->request->get('code'))) {
                self::toolBox()::i18nLog()->error('record-not-found');
                return;
            }

            $folder = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles';
            $uploadFile = $this->request->files->get('newfile');
            $filePath = $uploadFile->move($folder, $uploadFile->getClientOriginalName())->getRealPath();
            if (empty($filePath)) {
                $this->toolBox()->i18nLog()->warning('upload-file-error');
                $this->toolBox()->i18nLog()->warning($uploadFile->getClientOriginalName());
                return;
            }

            // asignamos el archivo al modelo
            $model->sii_signature = basename($filePath);
            if (false === $model->save()) {
                self::toolBox()::i18nLog()->error('record-save-error');
                return;
            }

            self::toolBox()::i18nLog()->notice('record-updated-correctly');
        };
    }
}

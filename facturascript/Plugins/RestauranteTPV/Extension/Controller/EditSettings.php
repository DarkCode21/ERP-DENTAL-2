<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
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

namespace FacturaScripts\Plugins\RestauranteTPV\Extension\Controller;

use Closure;
use FacturaScripts\Core\Model\Settings;

/**
 * Añade la pestaña de configuración de RestauranteTPV
 * a la página de Configuración del sistema (Admin > Configuración).
 */
class EditSettings
{
    public function createViews(): Closure
    {
        return function () {
            $viewName = 'SettingsRestauranteTPV';
            $this->addEditView($viewName, 'Settings', 'restaurant-tpv', 'fa-solid fa-utensils')
                ->setSettings('btnDelete', false)
                ->setSettings('btnNew', false);

            // Usar template personalizado para incluir el formulario de subida de audio
            $this->views[$viewName]->template = 'SettingsRestauranteTPV.html.twig';
        };
    }

    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'SettingsRestauranteTPV') {
                return;
            }

            $view->loadData('restaurantetpv');
            if ($view->model instanceof Settings && empty($view->model->name)) {
                $view->model->name = 'restaurantetpv';
            }

            // Aplicar valores por defecto si nunca se han guardado
            if ($view->model instanceof Settings) {
                if (null === $view->model->sonidos_activos) {
                    $view->model->sonidos_activos = true;
                }
                if (!in_array($view->model->servicio_defecto, ['in-table', 'take-away', 'delivery'])) {
                    $view->model->servicio_defecto = 'in-table';
                }
                if (empty($view->model->audio_beep1)) {
                    $view->model->audio_beep1 = 'beep.wav';
                }
                if (empty($view->model->audio_beep2)) {
                    $view->model->audio_beep2 = 'beep2.wav';
                }
                if (empty($view->model->escpos_port_ticket)) {
                    $view->model->escpos_port_ticket = 9100;
                }
            }

            // Listar archivos de sonido disponibles para mostrar en la vista
            $soundDir = __DIR__ . '/../../Assets/Sound/';
            $files = [];
            if (is_dir($soundDir)) {
                foreach (scandir($soundDir) as $f) {
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (in_array($ext, ['wav', 'mp3', 'ogg'])) {
                        $files[] = $f;
                    }
                }
            }
            $this->tpvSoundFiles = $files;
        };
    }

    public function editAction(): Closure
    {
        return function () {
            $soundDir = __DIR__ . '/../../Assets/Sound/';
            foreach (['audio_beep1_file' => 'audio_beep1', 'audio_beep2_file' => 'audio_beep2'] as $fileField => $settingKey) {
                $uploaded = $_FILES[$fileField] ?? null;
                if (!$uploaded || $uploaded['error'] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $filename = basename($uploaded['name']);
                // Solo permitir archivos de audio
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, ['wav', 'mp3', 'ogg'])) {
                    continue;
                }
                // Sanitizar nombre: solo alfanuméricos, guiones, puntos
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                if (move_uploaded_file($uploaded['tmp_name'], $soundDir . $filename)) {
                    $_POST[$settingKey] = $filename;
                }
            }
            // Devolver false para que continúe el flujo normal de guardado
            return false;
        };
    }
}

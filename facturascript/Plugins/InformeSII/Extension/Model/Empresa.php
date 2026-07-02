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

namespace FacturaScripts\Plugins\InformeSII\Extension\Model;

use Closure;
use FacturaScripts\Dinamic\Model\AttachedFile;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Empresa
{
    public function test(): Closure
    {
        return function() {
            $fileName = '';
            $filePath = '';
            $path = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles' . DIRECTORY_SEPARATOR;

            if ($this->sii_signature && strpos($this->sii_signature, 'MyFiles/') === false) {
                $fileName = $this->sii_signature;
                $filePath = $path . $this->sii_signature;
            }

            if (empty($fileName) || false === file_exists($filePath)) {
                return;
            }

            $attachedFile = new AttachedFile();
            $attachedFile->path = basename($filePath);
            if (false === $attachedFile->save()) {
                unlink($filePath);
                return false;
            }

            // eliminamos el fichero antiguo
            if ($this->sii_idfile) {
                $oldAttachedFile = new AttachedFile();
                $oldAttachedFile->loadFromCode($this->sii_idfile);
                $oldAttachedFile->delete();
            }

            $this->sii_idfile = $attachedFile->idfile;
            $this->sii_signature = $attachedFile->path;
        };
    }
}

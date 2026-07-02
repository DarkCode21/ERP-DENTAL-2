<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Atributo;
use FacturaScripts\Dinamic\Model\AtributoValor;
use FacturaScripts\Plugins\CSVimport\Lib\CsvFileTools;
use ParseCsv\Csv;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class ListAtributo
{
    public function createViews(): Closure
    {
        return function () {
            // import button
            if ($this->permissions->allowImport) {
                $this->addButton('ListAtributoValor', [
                    'action' => 'upload-attributes',
                    'icon' => 'fas fa-file-import',
                    'label' => 'import',
                    'type' => 'modal'
                ]);
            }
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action == 'upload-attributes') {
                $this->uploadAttributesAction();
            }
        };
    }

    public function uploadAttributesAction(): Closure
    {
        return function () {
            // comprobamos los permisos de importación
            if (false === $this->permissions->allowImport) {
                Tools::log()->warning('no-import-permission');
                return;
            }

            // comprobamos el token
            if (false === $this->validateFormToken()) {
                return;
            }

            // comprobamos el tamaño y tipo del archivo
            $uploadFile = $this->request->files->get('attributes_file');
            if (CsvFileTools::isBigFile($uploadFile) || false === CsvFileTools::isValidFile($uploadFile->getRealPath())) {
                return;
            }

            // cargamos el archivo csv
            $csv = new Csv();
            $csv->auto($uploadFile->getRealPath());

            // comprobamos que los títulos de las columnas sean id, codatributo, valor, descripcion y orden
            if ($csv->titles[0] !== 'id' || $csv->titles[1] !== 'codatributo' || $csv->titles[2] !== 'valor' || $csv->titles[3] !== 'descripcion' || $csv->titles[4] !== 'orden') {
                Tools::log()->warning('invalid-file');
                return;
            }

            // procesamos línea a línea
            foreach ($csv->data as $line) {
                // si el atributo no existe, lo creamos
                $attribute = new Atributo();
                if (false === $attribute->loadFromCode($line['codatributo'])) {
                    $attribute->codatributo = $line['codatributo'];
                    $attribute->nombre = $line['codatributo'];
                    if (false === $attribute->save()) {
                        Tools::log()->warning('error-saving-attribute');
                        return;
                    }
                }

                // si el valor no existe, lo creamos
                $valor = new AtributoValor();
                $where = [
                    new DataBaseWhere('codatributo', $line['codatributo']),
                    new DataBaseWhere('valor', Utils::noHtml($line['valor']))
                ];
                if (false === $valor->loadFromCode($line['id']) && false === $valor->loadFromCode('', $where)) {
                    $valor->id = $line['id'];
                }

                $valor->codatributo = $line['codatributo'];
                $valor->valor = $line['valor'];
                $valor->descripcion = $line['descripcion'];
                $valor->orden = $line['orden'];
                if (false === $valor->save()) {
                    Tools::log()->warning('error-saving-attribute-value');
                    return;
                }
            }

            Tools::log()->info('record-updated-correctly');
        };
    }
}

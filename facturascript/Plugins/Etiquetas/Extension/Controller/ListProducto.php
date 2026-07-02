<?php
/**
 * Copyright (C) 2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListProducto
{
    public function createViews(): Closure
    {
        return function () {
            $this->addButton('ListProducto', [
                'action' => 'barcode',
                'color' => 'light',
                'confirm' => true,
                'icon' => 'fas fa-barcode',
                'label' => 'generate-barcodes',
            ]);
        };
    }

    public function execPreviousAction(): Closure
    {
        return function ($action) {
            if ($action !== 'barcode') {
                return;
            }

            $codes = $this->request->get('code', []);
            if (empty($codes)) {
                ToolBox::i18nLog()->warning('no-selected-item');
                return;
            }

            $num = 0;
            foreach ($codes as $idproducto) {
                $modelVariant = new Variante();
                $where = [new DataBaseWhere('idproducto', $idproducto)];
                foreach ($modelVariant->all($where, [], 0, 0) as $variant) {
                    if ($variant->codbarras) {
                        continue;
                    }

                    $variant->codbarras = $variant->generateEAN();
                    if ($modelVariant->count([new DataBaseWhere('codbarras', $variant->codbarras)]) > 0) {
                        // ya existe el código de barras, descartamos
                        continue;
                    }

                    if ($variant->save()) {
                        $num++;
                    }
                }
            }

            ToolBox::i18nLog()->notice('barcode-generate-ok', ['%num%' => $num]);
        };
    }
}

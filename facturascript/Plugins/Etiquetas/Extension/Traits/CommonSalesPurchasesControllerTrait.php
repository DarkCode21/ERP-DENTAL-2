<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\Extension\Traits;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait CommonSalesPurchasesControllerTrait
{
    public function getAvailableTags(): Closure
    {
        return function () {
            $tags = [];
            $mainViewName = $this->getMainViewName();
            foreach ($this->views[$mainViewName]->model->getLines() as $key => $line) {
                $variant = new Variante();
                $where = [new DataBaseWhere('referencia', $line->referencia)];
                if (empty($line->referencia) || !$variant->loadFromCode('', $where)) {
                    continue;
                }

                $tags[$key] = [
                    'reference' => $variant->referencia,
                    'url' => $variant->url(),
                    'quantity' => (int)$line->cantidad
                ];
            }

            return $tags;
        };
    }
}

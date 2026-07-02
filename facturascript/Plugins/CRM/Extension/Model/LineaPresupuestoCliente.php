<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CRM\Extension\Model;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Plugins\CRM\Model\CrmInteres;
use FacturaScripts\Plugins\CRM\Model\CrmInteresContacto;

/**
 * Description of LineaPresupuestoCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class LineaPresupuestoCliente
{

    public function saveInsert(): Closure
    {
        return function () {
            $document = $this->getDocument();
            if (empty($document->idcontactofact)) {
                return;
            }

            /**
             * The product has an interest?
             */
            $interest = new CrmInteres();
            $product = $this->getProducto();
            if (!empty($product->idinteres) && $interest->loadFromCode($product->idinteres)) {
                // This contact has this interest?
                $interested = new CrmInteresContacto();
                $where = [
                    new DataBaseWhere('idinteres', $interest->primaryColumnValue()),
                    new DataBaseWhere('idcontacto', $document->idcontactofact)
                ];
                if (false === $interested->loadFromCode('', $where)) {
                    $interested->idcontacto = $document->idcontactofact;
                    $interested->idinteres = $interest->primaryColumnValue();
                    $interested->save();
                }
            }

            /**
             * The family has an interest.
             */
            $family = new Familia();
            if (empty($product->codfamilia) || false === $family->loadFromCode($product->codfamilia)) {
                return;
            } elseif (empty($family->idinteres) || false === $interest->loadFromCode($family->idinteres)) {
                return;
            }

            // This contact has this interest?
            $interested2 = new CrmInteresContacto();
            $where2 = [
                new DataBaseWhere('idinteres', $interest->primaryColumnValue()),
                new DataBaseWhere('idcontacto', $document->idcontactofact)
            ];
            if (false === $interested2->loadFromCode('', $where2)) {
                $interested2->idcontacto = $document->idcontactofact;
                $interested2->idinteres = $interest->primaryColumnValue();
                $interested2->save();
            }
        };
    }
}

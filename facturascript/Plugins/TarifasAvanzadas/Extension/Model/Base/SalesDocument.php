<?php
/**
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TarifasAvanzadas\Extension\Model\Base;

use Closure;
use FacturaScripts\Plugins\TarifasAvanzadas\Model\DescuentoCliente;

/**
 * Description of SalesDocument
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class SalesDocument
{
    public function getNewLine(): Closure
    {
        return function ($newLine, $data = [], $exclude = []) {
            if ($newLine->referencia) {
                // excluimos líneas de productos (ya se les aplica el descuento en getNewProductLine)
                return;
            }

            if (empty($newLine->cantidad) && empty($newLine->pvpunitario)) {
                // excluimos líneas de simple texto
                return;
            }

            if ($newLine->dtopor > 0) {
                // excluimos líneas con descuento
                return;
            }

            if (array_key_exists('dtopor', $data)) {
                // excluimos líneas copiadas de otros documentos (al aprobar o agrupar)
                return;
            }

            $subject = $this->getSubject();
            $discountModel = new DescuentoCliente();
            $order = ['prioridad' => 'DESC'];
            foreach ($discountModel->all([], $order, 0, 0) as $discount) {
                // excluimos descuentos inactivos
                // excluimos descuentos que no aplican al cliente
                // excluimos descuentos que aplican a productos o familias
                if (false === $discount->enabled() ||
                    false === $discount->appliesToCustomer($subject) ||
                    $discount->referencia || $discount->codfamilia) {
                    continue;
                }

                $newLine->dtopor = $discount->applyDiscount($newLine->dtopor);
                if (false === $discount->acumular) {
                    break;
                }
            }
        };
    }

    public function getNewProductLine(): Closure
    {
        return function ($newLine, $variant, $product) {
            if ($newLine->dtopor > 0) {
                // excluimos si ya hay asignado un descuento
                return;
            }

            $subject = $this->getSubject();
            $discountModel = new DescuentoCliente();
            $order = ['prioridad' => 'DESC'];
            foreach ($discountModel->all([], $order, 0, 0) as $discount) {
                // excluimos descuentos inactivos
                // excluimos descuentos que no aplican al cliente
                // excluimos descuentos que aplican al producto o familia
                if (false === $discount->enabled() ||
                    false === $discount->appliesToCustomer($subject) ||
                    false === $discount->appliesToProduct($product, $variant)) {
                    continue;
                }

                $newLine->dtopor = $discount->applyDiscount($newLine->dtopor);
                if (false === $discount->acumular) {
                    break;
                }
            }
        };
    }
}

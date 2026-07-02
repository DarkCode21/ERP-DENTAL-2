<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Contract;

use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Dinamic\Model\Contacto;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
interface PortalPaymentGatewayInterface
{
    public function getHtml(SalesDocument $model, Contacto $contact): string;

    public function name(): string;

    public function payAction(SalesDocument &$model, Request $request): bool;
}

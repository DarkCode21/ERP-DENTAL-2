<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Lib\PlantillasPDF;

use Closure;
use FacturaScripts\Core\Tools;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class BaseTemplate
{
    public function qrImageHeader(): Closure
	{
		return function () {
			if (empty($this->headerModel) || !method_exists($this->headerModel, 'modelClassName')) {
				return;
			}

			// si el modelo no es una factura de cliente, no añadimos el QR
			if ($this->headerModel->modelClassName() !== 'FacturaCliente') {
				return;
			}

			if ($this->headerModel->getCompany()->vf_debug_mode && !Tools::config('debug')) {
				return;
			}

			if (!$this->headerModel->verifactuCheckAlta()) {
				return;
			}

			return $this->headerModel->verifactuGetQr();
		};
	}

    public function qrTitleHeader(): Closure
    {
        return function () {
            if (empty($this->headerModel)
                || !method_exists($this->headerModel, 'modelClassName')
                || $this->headerModel->modelClassName() !== 'FacturaCliente'
                || !$this->headerModel->verifactuCheckAlta()) {
                return;
            }

            return 'Veri*Factu';
        };
    }
}
<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Verifactu\Model\VerifactuRegistroFactura;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditFacturaCliente
{
    public function createViews(): Closure
    {
        return function () {
            if ($this->getModel()->getCompany()->verifactuIsConfigured(false)) {
                $this->addHtmlView('verifactu', 'Tab/Verifactu', 'FacturaCliente', 'verifactu', 'fa-solid fa-qrcode');
            }
        };
    }

    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'verifactu-alta':
                    $this->verifactuAction(VerifactuRegistroFactura::EVENT_ALTA);
                    break;

                case 'verifactu-alta-manual':
                    $this->verifactuManualAction(VerifactuRegistroFactura::EVENT_ALTA);
                    break;

                case 'verifactu-anulacion':
                    $this->verifactuAction(VerifactuRegistroFactura::EVENT_ANULACION);
                    break;

                case 'verifactu-anulacion-manual':
                    $this->verifactuManualAction(VerifactuRegistroFactura::EVENT_ANULACION);
                    break;

                case 'verifactu-subsanacion':
                    $this->verifactuAction(VerifactuRegistroFactura::EVENT_SUBSANACION);
                    break;
            }
        };
    }

    protected function verifactuAction(): Closure
    {
        return function (string $event) {
            if (false === $this->validateFormToken()) {
                return;
            }

            // si la factura no existe, terminamos
            $model = $this->getModel();
            if (empty($model->primaryColumnValue())) {
                Tools::log()->warning('record-not-found');
                return;
            }

            switch ($event) {
                case VerifactuRegistroFactura::EVENT_ALTA:
                    $model->verifactuAlta();
                    break;

                case VerifactuRegistroFactura::EVENT_ANULACION:
                    $model->verifactuAnulacion();
                    break;

                case VerifactuRegistroFactura::EVENT_SUBSANACION:
                    $model->verifactuSubsanacion();
                    break;

                default:
                    Tools::log()->error('verifactu-event-not-found', [
                        '%event%' => $event,
                        'model-code' => $model->primaryColumnValue(),
                        'model-class' => $model->modelClassName(),
                    ]);
                    break;
            }
        };
    }

    protected function verifactuManualAction(): Closure
    {
        return function (string $event) {
            if (false === $this->validateFormToken()) {
                return;
            }

            // si la factura no existe, terminamos
            $model = $this->getModel();
            if (empty($model->primaryColumnValue())) {
                Tools::log()->warning('record-not-found');
                return;
            }

            switch ($event) {
                case VerifactuRegistroFactura::EVENT_ALTA:
                    $model->vf_sent = true;
                    $model->vf_manual_alta = true;
                    break;

                case VerifactuRegistroFactura::EVENT_ANULACION:
                    $model->vf_sent = true;
                    $model->vf_manual_anulacion = true;
                    break;

                default:
                    Tools::log()->error('verifactu-event-not-found', [
                        '%event%' => $event,
                        'model-code' => $model->primaryColumnValue(),
                        'model-class' => $model->modelClassName(),
                    ]);
                    break;
            }

            // guardamos el modelo
            if (false === $model->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            Tools::log()->notice('record-updated-correctly');
        };
    }
}

<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\PortalCliente\Contract\PortalPaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPaymentGatewayBank implements PortalPaymentGatewayInterface
{
    public function getHtml(SalesDocument $model, Contacto $contact): string
    {
        // obtenemos todas las cuentas de banco que se pueden mostrar en el portal
        // para la misma empresa del documento
        $bankAccountModel = new CuentaBanco();
        $where = [
            new DataBaseWhere('pc_show', true),
            new DataBaseWhere('idempresa', $model->idempresa),
        ];
        $bankAccounts = $bankAccountModel->all($where, ['descripcion' => 'ASC'], 0, 0);

        // si no hay cuentas de banco, terminamos
        if (empty($bankAccounts)) {
            return '';
        }

        $html = '<button type="button" class="btn btn-primary btn-block mb-3 btn-spin-action" data-toggle="modal" data-target="#transferPayModal">'
            . '<i class="fa-solid fa-building-columns mr-2"></i>'
            . Tools::lang()->trans('pay-by-bank-transfer')
            . '</button>'
            . '<div class="modal fade" id="transferPayModal" tabindex="-1" role="dialog" aria-labelledby="transferPayModalLabel" aria-hidden="true">'
            . '<div class="modal-dialog" role="document">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h5 class="modal-title" id="transferPayModalLabel">' . Tools::lang()->trans('pay-by-bank-transfer') . '</h5>'
            . '<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">'
            . '<span aria-hidden="true">&times;</span>'
            . '</button>'
            . '</div>'
            . '<div class="modal-body">'
            . '<p>' . Tools::lang()->trans('modal-bank-transfer-info') . '</p>';

        foreach ($bankAccounts as $bankAccount) {
            $html .= '<div class="mb-3 border bg-light p-2">'
                . '<div><strong>' . Tools::lang()->trans('titular') . ':</strong> ' . $model->getCompany()->nombre . '</div>'
                . '<div><strong>' . Tools::lang()->trans('iban') . ':</strong> ' . $bankAccount->iban . '</div>'
                . '<div><strong>' . Tools::lang()->trans('swift') . ':</strong> ' . $bankAccount->swift . '</div>'
                . '<div><strong>' . Tools::lang()->trans('concept') . ':</strong> ' . $model->codigo . '</div>'
                . '</div>';
        }

        $html .= '<p class="m-0">' . Tools::lang()->trans('modal-bank-transfer-desc', ['%email%' => $model->getCompany()->email]) . '</p>'
            . '</div>'
            . '<div class="modal-footer">'
            . '<button type="button" class="btn btn-secondary" data-dismiss="modal">' . Tools::lang()->trans('close') . '</button>'
            . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        return $html;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'transfer-bank';
    }

    public function payAction(SalesDocument &$model, Request $request): bool
    {
        return true;
    }
}

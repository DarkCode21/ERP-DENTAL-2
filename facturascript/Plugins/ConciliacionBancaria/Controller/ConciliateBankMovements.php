<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\ConciliacionBancaria\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Ejercicios;
use FacturaScripts\Core\DataSrc\FormasPago;
use FacturaScripts\Core\Model\Base\ModelCore;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\MovimientoBanco;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\ReciboProveedor;
use FacturaScripts\Dinamic\Model\RemesaSEPA;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ConciliateBankMovements extends Controller
{
    /** @var string */
    public $codcuenta;

    /** @var CuentaBanco */
    public $cuenta;

    /** @var array */
    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    public function getBankAccounts(): array
    {
        return CuentaBanco::all();
    }

    public function getPaymentMethods(): array
    {
        return FormasPago::all();
    }

    public function getSubaccounts(): array
    {
        foreach (Ejercicios::all() as $ejercicio) {
            if ($ejercicio->idempresa != $this->cuenta->idempresa) {
                continue;
            }

            if (!$ejercicio->isOpened()) {
                continue;
            }

            $where = [new DataBaseWhere('codejercicio', $ejercicio->codejercicio),];
            return Subcuenta::all($where, ['codsubcuenta' => 'ASC'], 0, 0);
        }

        return [];
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data["menu"] = "accounting";
        $data["title"] = "conciliate-bank-movements";
        $data["icon"] = "fas fa-check-double";
        $data["showonmenu"] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->codcuenta = $this->request->get('codcuenta', '');
        $this->cuenta = new CuentaBanco();
        if (false === $this->cuenta->loadFromCode($this->codcuenta)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        if ($this->request->get('ajax', false)) {
            $this->setTemplate(false);

            $action = $this->request->get('action', '');
            switch ($action) {
                case 'autocomplete-client':
                    $data = $this->autocompleteClientAction();
                    break;

                case 'autocomplete-supplier':
                    $data = $this->autocompleteSupplierAction();
                    break;

                case 'getAsientos':
                    $data = $this->getAsientos();
                    break;

                case 'getBankMovements':
                    $data = $this->getBankMovements();
                    break;

                case 'getReceiptsPurchases':
                    $data = $this->getReceiptsPurchases();
                    break;

                case 'getReceiptsSales':
                    $data = $this->getReceiptsSales();
                    break;

                case 'getRemesas':
                    $data = $this->getRemesas();
                    break;

                case 'reconciliate':
                    $data = $this->reconciliateAction();
                    break;
            }

            $content = array_merge(
                ['messages' => Tools::log()->read('master', $this->logLevels)],
                $data ?? []
            );
            $this->response->setContent(json_encode($content));
        }
    }

    protected function autocompleteClientAction(): array
    {
        $list = [];
        $client = new Cliente();
        $query = $this->request->get('query');
        foreach ($client->codeModelSearch($query, 'codcliente') as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        return ['clients' => $list];
    }

    protected function autocompleteSupplierAction(): array
    {
        $list = [];
        $supplier = new Proveedor();
        $query = $this->request->get('query');
        foreach ($supplier->codeModelSearch($query, 'codproveedor') as $value) {
            $list[] = [
                'key' => Tools::fixHtml($value->code),
                'value' => Tools::fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        return ['suppliers' => $list];
    }

    protected function getAsientos(): array
    {
        $html = '';
        $cuentaBanco = new CuentaBanco();
        if (false === $cuentaBanco->loadFromCode($this->request->get('codcuenta', ''))) {
            return [
                'getAsientos' => false,
                'html' => $html,
            ];
        }

        $concepto = $this->request->get('concepto', '');
        $amountGreaterOrEqual = $this->request->get('amountGreaterOrEqual', '');
        $amountLessThanOrEqual = $this->request->get('amountLessThanOrEqual', '');

        $asientoModel = new Asiento();
        $where = [
            new DataBaseWhere('idempresa', $cuentaBanco->idempresa),
            new DataBaseWhere('idbankmovement', null),
            new DataBaseWhere('editable', true),
        ];

        if ($amountGreaterOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountGreaterOrEqual, '>=');
        }

        if ($amountLessThanOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountLessThanOrEqual, '<=');
        }

        if ($concepto) {
            $where[] = new DataBaseWhere('concepto', $concepto, 'XLIKE');
        }

        foreach ($asientoModel->all($where, ['fecha' => 'ASC'], 0, 0) as $asiento) {
            $coddivisa = '';
            if ($asiento->documento) {
                $facturaCliente = new FacturaCliente();
                $facturaProveedor = new FacturaProveedor();
                $where = [
                    new DataBaseWhere('codigo', $asiento->documento),
                    new DataBaseWhere('idasiento', $asiento->idasiento),
                ];
                if ($facturaCliente->loadFromCode('', $where)) {
                    $coddivisa = $facturaCliente->coddivisa;
                } elseif ($facturaProveedor->loadFromCode('', $where)) {
                    $coddivisa = $facturaProveedor->coddivisa;
                }
            }

            $html .= '<tr>'
                . '<td class="align-middle text-center"><input type="checkbox" value="' . $asiento->idasiento . '"/></td>'
                . '<td class="align-middle">'
                . '<a href="' . $asiento->url() . '" target="_blank">' . $asiento->idasiento . '</a> - ' . $asiento->concepto
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . Tools::number($asiento->importe) . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . $asiento->fecha . '</td>'
                . '</tr>';
        }

        return [
            'getAsientos' => true,
            'html' => $html,
        ];
    }

    protected function getBankMovements(): array
    {
        $html = '';
        $bankMovements = new MovimientoBanco();
        $where = [
            new DataBaseWhere('codcuenta', $this->codcuenta),
            new DataBaseWhere('reconciled', false),
        ];

        foreach ($bankMovements->all($where, ['date' => 'ASC', 'id' => 'ASC'], 0, 0) as $bankMovement) {
            $typeModal = $bankMovement->amount > 0 ? 'showModalClients' : 'showModalSuppliers';
            $cssAmount = $bankMovement->amount > 0 ? 'text-success' : 'text-danger';

            $html .= '<tr data-idmovement="' . $bankMovement->id . '">'
                . '<td class="align-middle amount text-right text-nowrap ' . $cssAmount . '">'
                . Tools::number($bankMovement->amount) . '</td>'
                . '<td class="align-middle date text-nowrap">' . date(ModelCore::DATE_STYLE, strtotime($bankMovement->date)) . '</td>'
                . '<td class="align-middle observations">' . $bankMovement->observations . '</td>'
                . '<td class="align-middle text-right">'
                . '<div class="dropdown">'
                . '<button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">'
                . Tools::lang()->trans('link-up')
                . '</button>'
                . '<div class="dropdown-menu">'
                . '<button type="button" class="dropdown-item" data-bankMovementId="' . $bankMovement->id . '" onclick="showModalAsientos(this)">'
                . '<i class="fas fa-link mr-2"></i>' . Tools::lang()->trans('accounting-entries')
                . '</button>'
                . '<button type="button" class="dropdown-item" data-bankMovementId="' . $bankMovement->id . '" onclick="' . $typeModal . '(this)">'
                . '<i class="fas fa-link mr-2"></i>' . Tools::lang()->trans('receipts')
                . '</button>';
            if ($typeModal === 'showModalClients' && Plugins::isEnabled('RemesasSEPA')) {
                $html .= '<button type="button" class="dropdown-item" data-bankMovementId="' . $bankMovement->id . '" onclick="showModalRemesas(this)">'
                    . '<i class="fas fa-link mr-2"></i>' . Tools::lang()->trans('remittances')
                    . '</button>';
            }

            $html .= '</div>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        if (empty($html)) {
            $html = '<tr>'
                . '<td colspan="4" class="text-center table-warning">' . Tools::lang()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return [
            'getBankMovements' => true,
            'html' => $html,
        ];
    }

    protected function getReceiptsPurchases(): array
    {
        $html = '';
        $codproveedor = $this->request->get('codproveedor', '');
        $codpago = $this->request->get('codpago', '');
        $amountGreaterOrEqual = $this->request->get('amountGreaterOrEqual', '');
        $amountLessThanOrEqual = $this->request->get('amountLessThanOrEqual', '');

        $receipts = new ReciboProveedor();
        $where = [new DataBaseWhere('pagado', false)];

        if ($codproveedor) {
            $where[] = new DataBaseWhere('codproveedor', $codproveedor);
        }

        if ($codpago) {
            $where[] = new DataBaseWhere('codpago', $codpago);
        }

        if ($amountGreaterOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountGreaterOrEqual, '>=');
        }

        if ($amountLessThanOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountLessThanOrEqual, '<=');
        }

        $orderBy = ['vencimiento' => 'DESC', 'codproveedor' => 'ASC'];
        foreach ($receipts->all($where, $orderBy, 0, 0) as $receipt) {
            $html .= '<tr>'
                . '<td class="align-middle text-center"><input type="checkbox" value="' . $receipt->idrecibo . '"/></td>'
                . '<td>'
                . '<a href="' . $receipt->url() . '" target="_blank">' . $receipt->codigofactura . '-' . $receipt->numero . '</a> '
                . $receipt->getSubject()->nombre . ' ' . $receipt->observaciones
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . Tools::number($receipt->importe) . '</td>'
                . '<td class="align-middle">' . $receipt->getPaymentMethod()->descripcion . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . date(ModelCore::DATE_STYLE, strtotime($receipt->vencimiento)) . '</td>'
                . '</tr>';
        }

        if (empty($html)) {
            $html = '<tr>'
                . '<td colspan="5" class="text-center table-warning">' . Tools::lang()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return [
            'getReceiptsPurchases' => true,
            'html' => $html,
        ];
    }

    protected function getReceiptsSales(): array
    {
        $html = '';
        $codcliente = $this->request->get('codcliente', '');
        $codpago = $this->request->get('codpago', '');
        $amountGreaterOrEqual = $this->request->get('amountGreaterOrEqual', '');
        $amountLessThanOrEqual = $this->request->get('amountLessThanOrEqual', '');

        $receipts = new ReciboCliente();
        $where = [new DataBaseWhere('pagado', false)];

        if ($codcliente) {
            $where[] = new DataBaseWhere('codcliente', $codcliente);
        }

        if ($codpago) {
            $where[] = new DataBaseWhere('codpago', $codpago);
        }

        if ($amountGreaterOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountGreaterOrEqual, '>=');
        }

        if ($amountLessThanOrEqual) {
            $where[] = new DataBaseWhere('importe', $amountLessThanOrEqual, '<=');
        }

        if (Plugins::isEnabled('RemesasSEPA')) {
            $where[] = new DataBaseWhere('idremesa', null);
        }

        $orderBy = ['vencimiento' => 'DESC', 'codcliente' => 'ASC'];
        foreach ($receipts->all($where, $orderBy, 0, 0) as $receipt) {
            $html .= '<tr>'
                . '<td class="align-middle text-center"><input type="checkbox" value="' . $receipt->idrecibo . '"/></td>'
                . '<td>'
                . '<a href="' . $receipt->url() . '" target="_blank">' . $receipt->codigofactura . '-' . $receipt->numero . '</a> '
                . $receipt->getSubject()->nombre . ' ' . $receipt->observaciones
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . Tools::number($receipt->importe) . '</td>'
                . '<td class="align-middle">' . $receipt->getPaymentMethod()->descripcion . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . date(ModelCore::DATE_STYLE, strtotime($receipt->vencimiento)) . '</td>'
                . '</tr>';
        }

        if (empty($html)) {
            $html = '<tr>'
                . '<td colspan="5" class="text-center table-warning">' . Tools::lang()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return [
            'getReceiptsSales' => true,
            'html' => $html,
        ];
    }

    protected function getRemesas(): array
    {
        $html = '';
        $codcuenta = $this->request->get('codcuenta', '');
        if (false === Plugins::isEnabled('RemesasSEPA') || empty($codcuenta)) {
            return [
                'getRemesas' => false,
                'html' => $html,
            ];
        }

        $remesasModel = new RemesaSEPA();
        $where = [
            new DataBaseWhere('codcuenta', $codcuenta),
        ];
        foreach ($remesasModel->all($where, ['fechacargo' => 'ASC'], 0, 0) as $remesa) {
            $html .= '<tr>'
                . '<td class="align-middle text-center"><input type="checkbox" value="' . $remesa->idremesa . '"/></td>'
                . '<td class="align-middle">'
                . '<a href="' . $remesa->url() . '" target="_blank">' . $remesa->idremesa . '</a> - ' . $remesa->fecha
                . '</td>'
                . '<td class="align-middle">' . $remesa->descripcion . '</td>'
                . '<td class="align-middle text-right text-nowrap">' . Tools::number($remesa->total) . '</td>'
                . '<td class="align-middle text-right ">' . $remesa->fechacargo . '</td>'
                . '</tr>';
        }

        if (empty($html)) {
            $html = '<tr>'
                . '<td colspan="5" class="text-center table-warning">' . Tools::lang()->trans('no-data') . '</td>'
                . '</tr>';
        }

        return [
            'getRemesas' => true,
            'html' => $html,
        ];
    }

    protected function reconciliateAction(): array
    {
        $bankMovement = new MovimientoBanco();
        if (false === $bankMovement->loadFromCode($this->request->get('idMovement', ''))) {
            Tools::log()->warning('record-not-found');
            return ['reconciliate' => false];
        }

        switch ($this->request->get('type', '')) {
            case 'asientos':
                return ['reconciliate' => $this->reconciliateAsientosAction($bankMovement)];

            case 'new-asiento':
                return ['reconciliate' => $this->reconciliateNewAsientoAction($bankMovement)];

            case 'purchases':
            case 'sales':
                return ['reconciliate' => $this->reconciliateReceiptsAction($bankMovement)];

            case 'remesas':
                return ['reconciliate' => $this->reconciliateRemesasAction($bankMovement)];

            default:
                Tools::log()->warning('no-data');
                return ['reconciliate' => false];
        }
    }

    protected function reconciliateAsientosAction(MovimientoBanco $bankMovement): bool
    {
        $idAsientos = $this->request->get('idAsientos', []);
        if (empty($idAsientos)) {
            Tools::log()->warning('no-data');
            return false;
        }

        $this->dataBase->beginTransaction();

        $idAsientos = explode(',', $idAsientos);
        foreach ($idAsientos as $idAsiento) {
            // cargamos el asiento
            $asiento = new Asiento();

            if (false === $asiento->loadFromCode($idAsiento)) {
                continue;
            }

            $asiento->idbankmovement = $bankMovement->id;
            if (false === $asiento->save()) {
                $this->dataBase->rollback();
                Tools::log()->warning('record-save-error');
                return false;
            }
        }

        $bankMovement->reconciled = true;
        if (false === $bankMovement->save()) {
            $this->dataBase->rollback();
            Tools::log()->warning('record-save-error');
            return false;
        }


        $this->dataBase->commit();
        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function reconciliateNewAsientoAction(MovimientoBanco $bankMovement): bool
    {
        $this->dataBase->beginTransaction();

        // creamos el asiento
        $asiento = new Asiento();
        $asiento->idempresa = $this->cuenta->idempresa;
        $asiento->fecha = $bankMovement->date;
        $asiento->concepto = $bankMovement->observations;
        $asiento->importe = abs($bankMovement->amount);
        $asiento->idbankmovement = $bankMovement->id;
        if (false === $asiento->save()) {
            Tools::log()->warning('record-save-error');
            $this->dataBase->rollback();
            return false;
        }

        // añadimos la partida de la cuenta de banco
        $partida1 = $asiento->getNewLine();
        $subcuenta1 = $partida1->getSubcuenta($this->cuenta->codsubcuenta);
        $partida1->setAccount($subcuenta1);
        $partida1->debe = $bankMovement->amount > 0 ? $asiento->importe : 0;
        $partida1->haber = $bankMovement->amount < 0 ? $asiento->importe : 0;
        if (false === $partida1->save()) {
            Tools::log()->warning('record-save-error');
            $this->dataBase->rollback();
            return false;
        }

        // añadimos la otra partida
        $partida2 = $asiento->getNewLine();
        $subcuenta2 = $partida2->getSubcuenta($this->request->get('contra'));
        $partida2->setAccount($subcuenta2);
        $partida2->debe = $bankMovement->amount < 0 ? $asiento->importe : 0;
        $partida2->haber = $bankMovement->amount > 0 ? $asiento->importe : 0;
        if (false === $partida2->save()) {
            Tools::log()->warning('record-save-error');
            $this->dataBase->rollback();
            return false;
        }

        // guardamos el movimiento de banco
        $bankMovement->reconciled = true;
        $bankMovement->save();

        $this->dataBase->commit();
        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function reconciliateReceiptsAction(MovimientoBanco $bankMovement): bool
    {
        $idReceipts = $this->request->get('idReceipts', []);
        if (empty($idReceipts)) {
            Tools::log()->warning('no-data');
            return false;
        }

        $newTransaction = $this->dataBase->inTransaction();
        if (false === $newTransaction) {
            $newTransaction = true;
            $this->dataBase->beginTransaction();
        }

        $idReceipts = explode(',', $idReceipts);
        foreach ($idReceipts as $idReceipt) {
            // cargamos el modelo correspondiente
            $receipt = $bankMovement->amount > 0 ? new ReciboCliente() : new ReciboProveedor();

            // si el recibo no existe, lo saltamos
            if (false === $receipt->loadFromCode($idReceipt)) {
                continue;
            }

            // si el recibo ya está pagado, lo saltamos
            if ($receipt->pagado) {
                continue;
            }

            $receipt->idbankmovement = $bankMovement->id;
            $receipt->fechapago = $bankMovement->date;
            $receipt->pagado = true;

            if (false === $receipt->save()) {
                if ($newTransaction) {
                    $this->dataBase->rollback();
                }

                Tools::log()->warning('record-save-error');
                return false;
            }
        }

        $bankMovement->reconciled = true;
        if (false === $bankMovement->save()) {
            if ($newTransaction) {
                $this->dataBase->rollback();
            }

            Tools::log()->warning('record-save-error');
            return false;
        }

        if ($newTransaction) {
            $this->dataBase->commit();
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function reconciliateRemesasAction(MovimientoBanco $bankMovement): bool
    {
        $idRemesas = $this->request->get('idRemesas', []);
        if (empty($idRemesas)) {
            Tools::log()->warning('no-data');
            return false;
        }

        $newTransaction = $this->dataBase->inTransaction();
        if (false === $newTransaction) {
            $newTransaction = true;
            $this->dataBase->beginTransaction();
        }

        $idRemesas = explode(',', $idRemesas);
        foreach ($idRemesas as $idRemesa) {
            // cargamos el modelo remesa
            $remesa = new RemesaSEPA();
            if (false === $remesa->loadFromCode($idRemesa)) {
                continue;
            }

            // si la remesa ya esta pagada la saltamos
            if ($remesa->estado === RemesaSEPA::STATUS_DONE) {
                continue;
            }

            foreach ($remesa->getReceipts() as $receipt) {
                if (false === $receipt->pagado) {
                    $receipt->idbankmovement = $bankMovement->id;
                    $receipt->fechapago = $remesa->fechacargo;
                    $receipt->nick = $this->user->nick;
                    $receipt->pagado = true;
                    if (false === $receipt->save()) {
                        if ($newTransaction) {
                            $this->dataBase->rollback();
                        }

                        Tools::log()->warning('record-save-error');
                        return false;
                    }
                }
            }

            $remesa->idbankmovement = $bankMovement->id;
            $remesa->estado = RemesaSEPA::STATUS_DONE;

            if (false === $remesa->save()) {
                if ($newTransaction) {
                    $this->dataBase->rollback();
                }

                Tools::log()->warning('record-save-error');
                return false;
            }
        }

        $bankMovement->reconciled = true;
        if (false === $bankMovement->save()) {
            if ($newTransaction) {
                $this->dataBase->rollback();
            }

            Tools::log()->warning('record-save-error');
            return false;
        }

        if ($newTransaction) {
            $this->dataBase->commit();
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }
}

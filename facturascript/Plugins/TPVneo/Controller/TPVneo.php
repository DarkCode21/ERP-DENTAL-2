<?php
/**
 * Copyright (C) 2022-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\TPVneo\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Lib\TPVneo\ParkForm;
use FacturaScripts\Dinamic\Lib\TPVneo\ProductList;
use FacturaScripts\Dinamic\Lib\TPVneo\SaleEmail;
use FacturaScripts\Dinamic\Lib\TPVneo\SaleForm;
use FacturaScripts\Dinamic\Lib\TPVneo\SaleReturn;
use FacturaScripts\Dinamic\Lib\TPVneo\SaleTicket;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\CodeModel;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\PresupuestoCliente;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\TpvCaja;
use FacturaScripts\Dinamic\Model\TpvCoin;
use FacturaScripts\Dinamic\Model\TpvPago;
use FacturaScripts\Dinamic\Model\TpvTerminal;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\TPVneo\Lib\Tickets\BoxClosure;
use FacturaScripts\Plugins\TPVneo\Model\TpvAgente;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use FacturaScripts\Dinamic\Model\Variante; #ADD ERICK
use FacturaScripts\Dinamic\Model\Producto; #ADD ERICK
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\TPVneo\Lib\TicketESCPos;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TPVneo extends Controller
{
    use ExtensionsTrait;

    public $agente;
    public $caja;
    public $cookieCodagente;
    public $escposTipo = '';
    public $escposRelayUrl = '';
    public $prepagos = false;
    public $tpv;
    public $tpvs = [];
    public $tpvAgents = [];

    private $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    public function getAmount()
    {
        return SaleForm::amount($this->tpv);
    }

    public function getEscposTipo(): string
    {
        return (string) ToolBox::appSettings()::get('tpvneo', 'escpos_tipo', '');
    }

    public function getEscposRelayUrl(): string
    {
        return (string) ToolBox::appSettings()::get('tpvneo', 'escpos_relay_url', '');
    }

    public function getCoinTypes(): array
    {
        $modelCoin = new TpvCoin();
        $where = [new DataBaseWhere('coddivisa', $this->tpv->coddivisa)];
        return $modelCoin->all($where, ['name' => 'asc'], 0, 0);
    }

    public function getDivisa(): Divisa
    {
        $divisa = new Divisa();
        $divisa->loadFromCode($this->tpv->coddivisa);
        return $divisa;
    }

    public function getFormatMoney(float $money): string
    {
        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($this->getDivisa());
        return $this->toolBox()->coins()->format($money);
    }

    public function getNameClient(): string
    {
        $cliente = new Cliente();
        $cliente->loadFromCode($this->tpv->codcliente);
        return $cliente->codcliente . ' | ' . $cliente->nombre;
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData["title"] = "TPVneo";
        $pageData["menu"] = "sales";
        $pageData["icon"] = "fas fa-cash-register";
        return $pageData;
    }

    public function getParkCount()
    {
        return ParkForm::totalParks($this->tpv, $this->agente->codagente);
    }

    public function getPaymentMethodsTpv(): array
    {
        $payments[$this->tpv->codpago] = $this->tpv->getMehodPayment();

        $tpvPagos = new TpvPago();
        $where = [new DataBaseWhere('idtpv', $this->tpv->idtpv)];
        $tpvPayments = $tpvPagos->all($where, [], 0, 0);
        foreach ($tpvPayments as $tpvPayment) {
            if (false === isset($payments[$tpvPayment->codpago])) {
                $payments[$tpvPayment->codpago] = $tpvPayment->getMehodPayment();
            }
        }

        if (false === empty($tpvPayments)) {
            return $payments;
        }

        $pagosModel = new FormaPago();
        $whereEmpresa = [new DataBaseWhere('idempresa', $this->empresa->idempresa)];
        foreach ($pagosModel->all($whereEmpresa, [], 0, 0) as $formaPago) {
            if ($formaPago->activa && false === isset($payments[$formaPago->codpago])) {
                $payments[$formaPago->codpago] = $formaPago;
            }
        }

        return $payments;
    }

    public function getPaymentMethodsModalCollectMoneyHtml(): string
    {
        $html = '';
        $pagosModel = new FormaPago();
        $payments = $this->getPaymentMethodsTpv();
        $whereEmpresa = [new DataBaseWhere('idempresa', $this->empresa->idempresa)];

        foreach ($pagosModel->all($whereEmpresa, [], 0, 0) as $formaPago) {
            if (false === array_key_exists($formaPago->codpago, $payments)) {
                continue;
            }

            $html .= '<div class="payment input-group input-group-lg mb-3">'
                . '<div class="input-group-prepend">'
                . '<span class="input-group-text">' . $formaPago->descripcion . '</span>'
                . '</div>'
                . '<input codpago="' . $formaPago->codpago . '" type="number" max="" class="text-center form-control">';

            if ($formaPago->codpago !== $this->tpv->codpago) {
                $html .= '<div class="input-group-append">'
                    . '<button class="btn btn-outline-secondary px-1 setMaxPayment" type="button">'
                    . $this->toolBox()->i18n()->trans('max-abr') . '</button>'
                    . '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    public function getSelectValues($table, $code, $description, $empty = false): array
    {
        $values = $empty ? ['' => '------'] : [];
        foreach (CodeModel::all($table, $code, $description, $empty) as $row) {
            $values[$row->code] = $row->description;
        }
        return $values;
    }

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->caja = new TpvCaja();
        $this->tpv = new TpvTerminal();

        // preguntamos si existe el plugin PrePagos
        if (class_exists('\\FacturaScripts\\Dinamic\\Model\\PrePago')) {
            $this->prepagos = ParkForm::advancePaymentIsEnabled();
        }

        // obtenemos el agente de la cookie si hay sesión iniciada del agente
        $this->cookieCodagente = $this->request->cookies->get('tpvneoCodagente', '');
        $this->agente = new Agente();
        if ('' !== $this->cookieCodagente) {
            $this->agente->loadFromCode($this->cookieCodagente);
        }

        // si la petición es por ajax (solo desde el tpv iniciado)
        if ($this->request->get('ajax', false) && false === $this->checkAjax()) {
            $this->setTemplate(false);
            $content = ['refresh' => true];
            $this->response->setContent(json_encode($content));
            return;
        }

        $action = $this->request->request->get('action');
        if ($action === 'agent-auth') {
            // iniciar sesión del agente cuando sea necesario
            $this->agentAuthAction();
            return;
        } elseif ($action === 'agent-out') {
            // cerrar sesión del agente
            $this->agentOutAction();
            return;
        }

        // preguntamos si hay caja abierta
        if (false === $this->loadCaja()) {

            // si el tpv no tiene caja abierta, recargamos la página
            // esto es necesario cuando tenemos el tpv abierto
            // cerramos la caja desde otro sitio e intentamos operar en el tpv
            if ($this->request->get('ajax', false)) {
                $this->agentOutAction(true);
                $this->setTemplate(false);
                $content = ['refresh' => true];
                $this->response->setContent(json_encode($content));
                return;
            }

            switch ($action) {
                case 'select-tpv':
                    // seleccionamos el tpv
                    $this->selectTpvAction();
                    return;

                case 'starting-money':
                    // obtenemos el dinero inicial de la caja
                    $this->startingMoneyAction();
                    return;
            }

            // cargamos todos los terminales cuando no se seleccionó ningún tpv
            $this->loadTpvs();
            return;
        }

        // cargamos el tpv de la caja abierta y comprobamos que el tpv este activo
        if (false === $this->tpv->loadFromCode($this->caja->idtpv) || false === $this->tpv->active) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        $this->escposTipo     = (string) ToolBox::appSettings()::get('tpvneo', 'escpos_tipo', '');
        $this->escposRelayUrl = (string) ToolBox::appSettings()::get('tpvneo', 'escpos_relay_url', '');

        $this->loadTpvAgents();

        // si el tpv tiene agentes pero no hay agente iniciado > login
        if (count($this->tpvAgents) > 0 && '' === $this->cookieCodagente) {
            $this->setTemplate('TPVneo/login');
            return;
        }

        // si el tpv tiene agentes, pero tengo login sobre un agente que ya no existe en el tpv > login
        $foundAgente = false;
        foreach ($this->tpvAgents as $agent) {
            if ($this->cookieCodagente == $agent->codagente) {
                $foundAgente = true;
            }
        }

        if (count($this->tpvAgents) > 0 && $foundAgente === false) {
            $this->setTemplate('TPVneo/login');
            return;
        }

        // correcto cargamos el tpv
        $this->setTemplate('TPVneo/index');

        switch ($action) {
            case 'add-customer':
                $this->addCustomerAction();
                break;

            case 'autocomplete-customer':
                $this->autocompleteCustomerAction();
                return;

            case 'autocomplete-search-doc':
                $this->autocompleteSearchDocAction();
                return;
			case 'verify-barcode':
				$this->verifyBarCodeAction();
                break;
            case 'add-barcode':
            case 'add-product':
            case 'new-line':
            case 'recalculate':
            case 'rm-line':
                $this->recalculateAction(true);
                break;

            case 'recalculate-line':
                $this->recalculateAction(false);
                break;

            case 'clear-cart':
                $this->clearCart();
                break;

            case 'cash-in':
                $this->saveCashMovementAction('in');
                break;

            case 'cash-out':
                $this->saveCashMovementAction('out');
                break;

            case 'close-box':
                $this->closeBoxAction();
                break;

            case 'close-box-start':
                $this->closeBoxStartAction();
                break;

            case 'delete-park':
                $this->deletePark();
                break;

            case 'find-products':
                $this->findProductsAction();
                break;

            case 'load-park':
                $this->loadPark();
                break;

            case 'load-doc-print':
                $this->loadDocPrint();
                break;
			#INI MOD ERICK
			case 'load-doc-preview-print':
			    $this->loadDocPreviewPrint();
                break;
			#FIN MOD ERICK
            case 'load-doc-return':
                $this->loadDocReturn();
                break;

            case 'save-park':
                $this->saveParkAction();
                break;

            case 'modal-tickets':
                $this->getModalTickets();
                break;

            case 'modal-list-park':
                $this->getModalListPark();
                break;

            case 'open-drawer':
                $this->openDrawer();
                break;

            case 'print-ticket':
                $this->printTicket();
                break;

            case 'preview-escpos-ticket':
                $this->previewEscposTicket();
                return;

            case 'print-escpos-direct':
                $this->printEscposDirect();
                return;

            case 'save-cart':
                $this->saveCart();
                break;

            case 'save-return':
                $this->saveReturn();
                break;

            case 'send-doc':
                $this->sendDoc();
                break;
        }
    }

    public function renderProductList(string $codalmacen): string
    {
        return ProductList::render($this->tpv, $codalmacen);
    }

    public function renderSaleForm(): string
    {
        return SaleForm::render($this->tpv);
    }

    protected function addCustomerAction()
    {
        $this->setTemplate(false);

        $newContact = new Contacto();
        $newContact->verificado = 1;
        $newContact->nombre = $this->request->request->get('empresa');
        $newContact->cifnif = $this->request->request->get('cifnif');
        $newContact->direccion = $this->request->request->get('direccion');
        $newContact->apartado = $this->request->request->get('apartado');
        $newContact->codpostal = $this->request->request->get('codpostal');
        $newContact->ciudad = $this->request->request->get('ciudad');
        $newContact->provincia = $this->request->request->get('provincia');
        $newContact->telefono1 = $this->request->request->get('telefono1');
        $newContact->email = $this->request->request->get('email');
        $newContact->personafisica = $this->request->request->get('personafisica');
        $newContact->tipoidfiscal = $this->request->request->get('tipoidfiscal');
        $newContact->codpais = $this->request->request->get('codpais');
        $newContact->save();

        $customer = $newContact->getCustomer();
        if ($customer->exists() === false) {
            $this->toolBox()->i18nLog()->error('record-save-error');
        }

        $content = [
            'customer' => $customer,
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function agentAuthAction()
    {
        // comprobamos que existe el tpv
        $idtpv = $this->request->request->get('idtpv', '');
        if (empty($idtpv) || false === $this->tpv->loadFromCode($idtpv)) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        // comprobamos que el tpv sigue activo
        if (false === $this->tpv->active) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        // comprobamos que existe el agente
        $codagente = $this->request->request->get('codagente');
        if (empty($codagente) || false === $this->agente->loadFromCode($codagente)) {
            $this->loadTpvAgents();
            $this->setTemplate('TPVneo/login');
            return;
        }

        // comprobamos la contraseña del agente
        $passwd = $this->request->request->get('passwd');
        if ($passwd !== $this->agente->passwd && $this->agente->passwd !== null) {
            $this->loadTpvAgents();
            self::toolBox()::i18nLog()->warning('login-password-fail');
            $this->setTemplate('TPVneo/login');
            return;
        }

        // creamos la cookie del agente que inicio sesión en el tpv
        $expire = time() + FS_COOKIES_EXPIRE;
        $this->response->headers->setCookie(
            new Cookie('tpvneoCodagente', $this->agente->codagente, $expire, FS_ROUTE)
        );
        $this->response->headers->setCookie(
            new Cookie('tpvneoPasswd', sha1($passwd), $expire, FS_ROUTE)
        );
        $this->cookieCodagente = $this->agente->codagente;

        if (false === $this->tpv->isOpen()) {
            // si el tpv no está abierto mostramos la vista de abrir caja
            $this->setTemplate('TPVneo/start-tpv');
            return;
        }

        $this->loadTpvAgents();

        // si el tpv está abierto cargamos esa caja
        $where = [
            new DataBaseWhere('idtpv', $this->tpv->idtpv),
            new DataBaseWhere('fechafin', null)
        ];
        $this->caja->loadFromCode('', $where);
        $this->setTemplate('TPVneo/index');

    }

    protected function agentOutAction($ajax = false)
    {
        $this->deleteCookieAgent();

        // solo ejecutamos si la llamada viene por ajax desde el tpv abierto
        if ($ajax) {
            return;
        }

        // comprobamos que existe el tpv
        $idtpv = $this->request->request->get('idtpv', '');
        if (empty($idtpv) || false === $this->tpv->loadFromCode($idtpv)) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        $this->loadTpvAgents();
        if (count($this->tpvAgents) > 0) {
            // si el tpv tiene agentes asignados mostramos la vista de login
            $this->setTemplate('TPVneo/login');
        } else {
            // si no tiene agentes asignados mostramos la vista de terminales
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
        }
    }

    protected function autocompleteCustomerAction()
    {
        $this->setTemplate(false);

        $list = [];
        $cliente = new Cliente();
        $query = $this->request->get('query');
        foreach ($cliente->codeModelSearch($query, 'codcliente') as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

	protected function verifyBarCodeAction()
	{
	    $this->setTemplate(false);
        $query = $this->request->get('action-code');

        $list = [];
		$referencia = null;
		
        $variant = new Variante();
		$where = [new DataBaseWhere('codbarras', $query)];
        if ($variant->loadFromCode('', $where)) {
			$product = new Producto();
			$product->loadFromCode($variant->idproducto);
			$referencia = $product->referencia;
		}
       
		$list = ['referencia' => $referencia];
        
        $this->response->setContent(json_encode($list));
    }

    protected function autocompleteSearchDocAction()
    {
        $this->setTemplate(false);

        $list = [];
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->tpv->doctype;
        $doc = new $modelClass();
        $query = $this->request->get('query');

        foreach ($doc->codeModelSearch($query) as $value) {
            $list[] = [
                'key' => $this->toolBox()->utils()->fixHtml($value->code),
                'value' => $this->toolBox()->utils()->fixHtml($value->description)
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => $this->toolBox()->i18n()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
    }

    protected function checkAjax(): bool
    {
        // comprobamos que el tpv existe
        if (false === $this->tpv->loadFromCode($this->request->request->get('idtpv'))) {
            $this->agentOutAction(true);
            return false;
        }

        // comprobamos que el tpv sigue activo
        if (false === $this->tpv->active) {
            $this->agentOutAction(true);
            return false;
        }

        // comprobamos si el tpv tiene agentes, pero no hemos iniciado sesión con un agente
        if ('' === $this->cookieCodagente) {
            $tpvAgente = new TpvAgente();
            $where = [new DataBaseWhere('idtpv', $this->tpv->idtpv)];
            if ($tpvAgente->count($where) > 0) {
                $this->agentOutAction(true);
                return false;
            }
        }

        // preguntamos si hay agente con la sesión iniciada
        // entonces buscamos que ese agente siga teniendo permiso para acceder al tpv
        if ('' !== $this->cookieCodagente) {
            $tpvAgente = new TpvAgente();
            $where = [new DataBaseWhere('codagente', $this->cookieCodagente)];
            if (false === $tpvAgente->loadFromCode('', $where)) {
                $this->agentOutAction(true);
                return false;
            }
        }

        return true;
    }

    protected function clearCart()
    {
        $this->setTemplate(false);
        SaleForm::clearCart($this->tpv);
        $content = [
            'clearcart' => true,
            'amount' => SaleForm::amount($this->tpv),
            'cart' => SaleForm::render($this->tpv),
            'totalCart' => SaleForm::totalCart(),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function closeBoxAction()
    {
        $coinsTypes = $this->getCoinTypes();
        $data = $this->request->request->all();

        $finalAmount = 0;
        foreach ($coinsTypes as $coin) {
            $finalAmount += $coin->name * $data[str_replace('.', '_', $coin->name)];
        }

        $this->caja->close($finalAmount);
        $this->caja->observaciones = $this->request->request->get('observaciones', '');

        if ($this->caja->save()) {
            BoxClosure::print($this->caja);
            $this->toolBox()->i18nLog()->notice('box-closed-ok');
            $this->agentOutAction();

            $this->response->headers->setCookie(
                new Cookie('idcaja', '', 0, FS_ROUTE)
            );
        }
    }

    protected function closeBoxStartAction()
    {
        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($this->getDivisa());

        $this->setTemplate('TPVneo/close-box-start');
    }

    protected function deleteCookieAgent()
    {
        $this->response->headers->setCookie(
            new Cookie('tpvneoCodagente', '', 0, FS_ROUTE)
        );

        $this->response->headers->setCookie(
            new Cookie('tpvneoPasswd', '', 0, FS_ROUTE)
        );
    }

    protected function deletePark()
    {
        $this->setTemplate(false);
        $pr = new PresupuestoCliente();

        if ($pr->loadFromCode($this->request->request->get('codpark', ''))) {
            $pr->delete();
        }

        $content = [
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels),
            'totalParks' => ParkForm::totalParks($this->tpv, $this->agente->codagente)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function findProductsAction()
    {
        $this->setTemplate(false);
        $formData = $this->request->request->all();
        ProductList::apply($formData);
        $content = [
            'products' => ProductList::render($this->tpv),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function getModalListPark()
    {
        $this->setTemplate(false);
        $content = [
            'presupuestos' => ParkForm::renderModalPark($this->tpv, $this->agente->codagente),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function getModalTickets()
    {
        $this->setTemplate(false);
        $content = [
            'tickets' => SaleForm::renderModalTickets($this->caja, $this->request->get('codigo', '')),
            'boxEstimated' => $this->getFormatMoney($this->caja->ingresos),
            'boxIncomesManual' => $this->getFormatMoney($this->caja->getManualIncomes()),
            'boxOutcomesManual' => $this->getFormatMoney($this->caja->getManualOutcomes()),
            'boxTotal' => $this->getFormatMoney($this->caja->getTotalInBox()),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    /**
     * @return bool
     */
    protected function loadCaja(): bool
    {
        $where = [new DataBaseWhere('fechafin', null)];
        if ('' !== $this->cookieCodagente) {
            $where[] = new DataBaseWhere('idtpv', $this->request->request->get('idtpv', 0));
        } else {
            $where[] = new DataBaseWhere('idcaja', $this->request->request->get('idcaja', 0));
        }
        return $this->caja->loadFromCode('', $where);
    }

    protected function loadDocPrint()
    {
        $this->setTemplate(false);
        $idDoc = $this->request->get('idDoc', '');
        $content = [
            'docprint' => SaleForm::loadDocPrint($idDoc, $this->tpv),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

	#INI MOD ERICK
	protected function loadDocPreviewPrint()
    {
        $this->setTemplate(false);
        $idDoc = $this->request->get('idDoc', '');
        $content = [
            'docpreviewprint' => SaleForm::loadDocPreviewPrint($idDoc, $this->tpv),
			'docprintPDF' => SaleForm::loadDocPreviewPrintPDF($idDoc, $this->tpv),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }
	#FIN MOD ERICK

    protected function previewEscposTicket()
    {
        $this->setTemplate(false);
        $idDoc = $this->request->get('idDoc', '');

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->tpv->doctype;
        $doc = new $modelClass();
        if (!$doc->loadFromCode($idDoc)) {
            $this->response->setContent(json_encode(['html' => '', 'error' => 'Documento no encontrado']));
            return;
        }

        $empresa = new Empresa();
        $empresa->loadFromCode($doc->idempresa);

        $formaPago = new FormaPago();
        $formaPago->loadFromCode($doc->codpago);

        $logoPath = '';
        if (!empty($empresa->idlogo)) {
            $file = new \FacturaScripts\Dinamic\Model\AttachedFile();
            if ($file->loadFromCode($empresa->idlogo)) {
                $path = $file->getFullPath();
                if (file_exists($path)) {
                    $logoPath = $path;
                }
            }
        }

        $html = TicketESCPos::previewHtml($doc, $empresa, $formaPago, $logoPath);
        $this->response->setContent(json_encode(['html' => $html]));
    }

    protected function printEscposDirect()
    {
        $this->setTemplate(false);
        $idDoc = $this->request->get('idDoc', '');

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->tpv->doctype;
        $doc = new $modelClass();
        if (!$doc->loadFromCode($idDoc)) {
            $this->toolBox()->i18nLog()->error('record-not-found');
            $this->response->setContent(json_encode(['messages' => $this->toolBox()->log()->read('master', $this->logLevels)]));
            return;
        }

        $empresa = new Empresa();
        $empresa->loadFromCode($doc->idempresa);

        $formaPago = new FormaPago();
        $formaPago->loadFromCode($doc->codpago);

        $logoPath = '';
        if (!empty($empresa->idlogo)) {
            $file = new \FacturaScripts\Dinamic\Model\AttachedFile();
            if ($file->loadFromCode($empresa->idlogo)) {
                $path = $file->getFullPath();
                if (file_exists($path)) {
                    $logoPath = $path;
                }
            }
        }

        $tipo = (string) ToolBox::appSettings()::get('tpvneo', 'escpos_tipo', '');
        $ip   = (string) ToolBox::appSettings()::get('tpvneo', 'escpos_ip', '');
        $port = (int)    ToolBox::appSettings()::get('tpvneo', 'escpos_port', 9100);
        $usb  = (string) ToolBox::appSettings()::get('tpvneo', 'escpos_usb', '');

        $ticket = TicketESCPos::fromDoc($doc, $empresa, $formaPago, $logoPath);
        $buffer = $ticket->getBuffer();

        $resp = [
            'ok'     => true,
            'buffer' => base64_encode($buffer),
            'tipo'   => $tipo,
        ];
        if ($tipo === 'usb') {
            $resp['usb'] = $usb;
        } else {
            $resp['ip']   = $ip;
            $resp['port'] = $port;
        }
        $this->response->setContent(json_encode($resp));
    }

    protected function loadDocReturn()
    {
        $this->setTemplate(false);
        $idDoc = $this->request->get('idDoc', '');
        $content = [
            'idDoc' => $idDoc,
            'docreturn' => SaleReturn::loadDocReturn($idDoc, $this->tpv),
            'methodreturn' => SaleReturn::getMethodReturn($idDoc, $this->tpv),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadPark()
    {
        $this->setTemplate(false);
        $codpark = $this->request->get('codpark', '');
        ParkForm::loadPark($codpark, $this->user, $this->tpv);
        $content = [
            'advance-payments' => ParkForm::getAdvancePayments($codpark),
            'codpark' => $codpark,
            'amount' => SaleForm::amount($this->tpv),
            'cart' => SaleForm::render($this->tpv),
            'totalCart' => SaleForm::totalCart(),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function saveParkAction()
    {
        $this->setTemplate(false);
        $formData = $this->request->request->all();
        $content = [
            'savepark' => ParkForm::savePark($formData, $this->user, $this->tpv, $this->agente->codagente),
            'amount' => SaleForm::amount($this->tpv),
            'cart' => SaleForm::render($this->tpv),
            'totalCart' => SaleForm::totalCart(),
            'totalParks' => ParkForm::totalParks($this->tpv, $this->agente->codagente),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function loadTpvAgents()
    {
        $tpvAgentsModel = new TpvAgente();
        $where = [new DataBaseWhere('idtpv', $this->tpv->idtpv)];
        $this->tpvAgents = $tpvAgentsModel->all($where, [], 0, 0);
    }

    protected function loadTpvs()
    {
        $tpvModel = new TpvTerminal();
        $this->tpvs = $tpvModel->all([], [], 0, 0);
    }

    protected function openDrawer()
    {
        $this->setTemplate(false);
        if ($this->tpv->idprinter) {
            SaleTicket::openDrawer($this->tpv, $this->user, $this->agente);
        }
        $content = [
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function printTicket()
    {
        $this->setTemplate(false);

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->tpv->doctype;
        $doc = new $modelClass();
        if ($doc->loadFromCode($this->request->get('idDoc')) && $this->tpv->idprinter) {
            $printer = new TicketPrinter();
            $printer->loadFromCode($this->tpv->idprinter);
            $ticketClass = '\\FacturaScripts\\Dinamic\\Lib\\Tickets\\' . $this->request->get('ticketformat', 'Normal');
            $ticketClass::print($doc, $printer, $this->user, $this->agente);
        }

        $content = [
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function recalculateAction(bool $renderLines): void
    {
        $this->setTemplate(false);
        $formData = $this->request->request->all();
        SaleForm::apply($formData, $this->user, $this->tpv, $this->agente->codagente, $renderLines);
        $content = [
            'amount' => SaleForm::amount($this->tpv),
            'cart' => $renderLines ? SaleForm::render($this->tpv) : '',
            'cartMap' => $renderLines ? [] : SaleForm::map(),
            'totalCart' => SaleForm::totalCart(),
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function saveCart()
    {
        $this->setTemplate(false);
        $formData = $this->request->request->all();
        $content['savecart'] = SaleForm::saveDoc($formData, $this->user, $this->caja, $this->agente->codagente);
        if ($content['savecart']) {
            $content['amount'] = SaleForm::amount($this->tpv);
            $content['cart'] = SaleForm::render($this->tpv);
            $content['totalCart'] = SaleForm::totalCart();
            $content['document'] = SaleForm::getLastDocSave();
            $content['totalParks'] = ParkForm::totalParks($this->tpv, $this->agente->codagente);
        }
        $this->pipe('saveCartAfter', $content);
        $content['messages'] = $this->toolBox()->log()->read('master', $this->logLevels);
        $this->response->setContent(json_encode($content));
    }

    protected function saveCashMovementAction(string $type): void
    {
        $amount = (float) $this->request->request->get('cash-amount', 0);
        $concept = trim((string) $this->request->request->get('cash-concept', ''));

        if ($amount <= 0) {
            $this->toolBox()->i18nLog()->warning('amount-is-required');
            return;
        }

        if (empty($concept)) {
            $this->toolBox()->i18nLog()->warning('description-is-required');
            return;
        }

        if ($type === 'out' && $amount > $this->caja->getTotalInBox()) {
            $this->toolBox()->log()->warning('Saldo insuficiente en caja para registrar la salida.');
            return;
        }

        if ($this->caja->addMovement($type, $amount, $concept, $this->user->nick)) {
            $this->toolBox()->i18nLog()->notice('record-updated-correctly');
            return;
        }

        $this->toolBox()->i18nLog()->warning('record-save-error');
    }

    protected function saveReturn()
    {
        $this->setTemplate(false);
        $formData = $this->request->request->all();
        $content = [
            'savereturn' => SaleReturn::saveReturn($formData, $this->user, $this->caja, $this->agente->codagente),
            'document' => SaleReturn::$lastDocSave,
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function selectTpvAction()
    {
        // comprobamos que existe el tpv
        $idtpv = $this->request->request->get('idtpv', '');
        if (empty($idtpv) || false === $this->tpv->loadFromCode($idtpv)) {
            return;
        }

        // comprobamos que el tpv sigue activo
        if (false === $this->tpv->active) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        // obtenemos los agentes del tpv
        $this->loadTpvAgents();

        // comprobamos si el tpv tiene agentes
        if (count($this->tpvAgents) > 0) {
            // comprobamos si el agente con la sesión iniciada esta en la lista de agentes del tpv
            $this->loadTpvAgents();
            $found = false;
            foreach ($this->tpvAgents as $agent) {
                if ($agent->codagente == $this->cookieCodagente) {
                    $found = true;
                }
            }

            // preguntamos si encontró el agente en el tpv
            if ($found) {
                if ($this->tpv->isOpen()) {
                    // si el tpv está abierto cargamos el tpv
                    $this->setTemplate('TPVneo/index');
                } else {
                    // si el tpv está cerrado cargamos la apertura de caja
                    $this->setTemplate('TPVneo/start-tpv');
                }
            } else {
                // si no encontró el agente cargamos el login
                $this->setTemplate('TPVneo/login');
            }

            // el tpv no tiene agentes asignados y esta cerrado
        } elseif (false === $this->tpv->isOpen()) {
            $this->deleteCookieAgent();
            // si el tpv no está abierto mostramos la caja
            $this->setTemplate('TPVneo/start-tpv');

            // si el tpv no tiene agentes asignados y está abierto
        } else {
            $this->deleteCookieAgent();
            $where = [
                new DataBaseWhere('idtpv', $this->tpv->idtpv),
                new DataBaseWhere('fechafin', null)
            ];
            $this->caja->loadFromCode('', $where);
            $this->setTemplate('TPVneo/index');
        }
    }

    protected function sendDoc()
    {
        $this->setTemplate(false);

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->tpv->doctype;
        $doc = new $modelClass();
        $email = $this->request->get('email', '');

        $result = false;
        if ($email !== '' && $doc->loadFromCode($this->request->get('idDoc', ''))) {
            $result = SaleEmail::send($doc, $email);
        }

        $content = [
            'send-email' => $result,
            'messages' => $this->toolBox()->log()->read('master', $this->logLevels)
        ];
        $this->response->setContent(json_encode($content));
    }

    protected function startingMoneyAction()
    {
        // comprobamos que existe el tpv
        $idtpv = $this->request->request->get('idtpv');
        if (false === $this->tpv->loadFromCode($idtpv)) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        // comprobamos que el tpv sigue activo
        if (false === $this->tpv->active) {
            $this->loadTpvs();
            $this->setTemplate('TPVneo');
            return;
        }

        $coinsTypes = $this->getCoinTypes();
        $data = $this->request->request->all();

        $total = 0;
        foreach ($coinsTypes as $coin) {
            $total += $coin->name * $data[str_replace('.', '_', $coin->name)];
        }

        $this->caja->dineroini = $total;
        $this->caja->idtpv = $idtpv;
        $this->caja->nick = $this->user->nick;

        if ($this->caja->save()) {
            $this->loadTpvAgents();
            $this->setTemplate('TPVneo/index');
        }
    }
}
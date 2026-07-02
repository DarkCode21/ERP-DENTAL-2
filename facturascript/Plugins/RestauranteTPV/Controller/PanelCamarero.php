<?php
/**
 * This file is part of RestauranteTPV plugin for FacturaScripts
 * Copyright (C) 2026 Interibérica Informática
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\RestauranteTPV\Controller;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\DocTransformation;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\TpvCoin;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCaja;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComanda;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestComandaLinea;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestCajaFactura;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestMesa;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestEstacion;
use FacturaScripts\Plugins\RestauranteTPV\Model\RestZona;
use FacturaScripts\Plugins\RestauranteTPV\Lib\TicketESCPos;

/**
 * Pantalla táctil del camarero para gestionar la comanda de una mesa.
 * Muestra las líneas actuales y el catálogo de productos para añadir.
 */
class PanelCamarero extends Controller
{
    /** @var RestComanda|null */
    public $comanda = null;

    /** @var RestEstacion[] */
    public $estaciones = [];

    /** @var int|null Estación activa en el catálogo (Cocina, Bar, etc.) */
    public $idestacionActiva = null;

    /** @var Familia[] */
    public $familias = [];

    /** @var RestComandaLinea[] */
    public $lineas = [];

    /** @var RestMesa|null */
    public $mesa = null;

    /** @var array Productos cargados según familia seleccionada */
    public $productos = [];

    /** @var string Familia activa en el catálogo */
    public $codfamiliaActiva = '';

    /** @var array Lista reducida de clientes para el modal de cobro */
    public $clientes = [];

    /** @var string Cliente por defecto según configuración del plugin */
    public $codClienteDefault = '';

    /** @var array Usuarios disponibles para el selector de camarero */
    public $usuarios = [];

    /** @var RestZona[] Zonas con mesas para el modal de selección */
    public $zonas = [];

    /** @var RestComanda|null Comanda vinculada al ticket de impresión */
    public $ticketComanda = null;

    /** @var RestComandaLinea[] Líneas para el ticket de impresión */
    public $ticketLineas = [];

    /** @var array Líneas agrupadas por estación [['nombre'=>..., 'lineas'=>[...]]] para el ticket */
    public $ticketEstaciones = [];

    /** @var Cliente|null Cliente para el ticket */
    public $ticketCliente = null;

    /** @var string Nombre empresa para el ticket */
    public $ticketEmpresa = '';

    /** @var array Datos completos de empresa para el ticket */
    public $ticketEmpresaData = [];

    /** @var array Facturas con codserie='S' para el modal Cuenta */
    public $facturasS = [];

    /** @var bool Mostrar vista de pedidos en curso en lugar del TPV */
    public $vistaOrdenes = false;

    /** @var array Comandas abiertas con pedido asignado */
    public $ordenesAbiertas = [];

    /** @var array Formas de pago disponibles */
    public $formasPago = [];

    /** @var array Empresas disponibles para el selector por usuario */
    public $empresas = [];

    /** @var int Empresa seleccionada por el usuario en sesión (0 = todas) */
    public $idempresaActual = 0;

    /** @var float Importe dado por el cliente (para el ticket) */
    public $ticketEfectivo = 0.0;

    /** @var float Cambio devuelto al cliente (para el ticket) */
    public $ticketCambio = 0.0;

    /** @var string Código del documento generado (para el ticket) */
    public $ticketDocCodigo = '';

    /** @var float Neto del documento para el ticket */
    public $ticketNeto = 0.0;

    /** @var float Total del documento para el ticket */
    public $ticketTotal = 0.0;

    /** @var array Grupos de IVA [tasa => importe] para el ticket */
    public $ticketIvaGroups = [];

    /** @var string Descripción de la forma de pago para el ticket */
    public $ticketFormaPago = '';

    /** @var array Datos de múltiples tickets para split o multi-persona */
    public $ticketMulti = [];

    /** @var string Descripción de la serie del documento para el ticket */
    public $ticketSerieDesc = '';

    /** @var bool Sonidos habilitados (setting restaurantetpv) */
    public $settingSonidos = true;

    /** @var string Tipo de servicio por defecto al abrir mesa (in-table/take-away/delivery) */
    public $settingServicioDefecto = 'in-table';

    /** @var string Vista del modal de mesas: clasico | posicion */
    public $settingVistaMesas = 'clasico';

    /** @var string Nombre del archivo de sonido al añadir producto (relativo a Assets/Sound) */
    public $settingAudioBeep1 = 'beep.wav';

    /** @var string Nombre del archivo de sonido al cambiar cantidad (relativo a Assets/Sound) */
    public $settingAudioBeep2 = 'beep2.wav';

    /** @var string IP de la impresora de tickets ESC/POS (vacío = desactivado) */
    public $settingEscposIp = '';

    /** @var int Puerto TCP de la impresora de tickets ESC/POS */
    public $settingEscposPort = 9100;

    /** @var string Tipo de conexión impresora tickets: tcp o usb */
    public $settingEscposTipo = 'tcp';

    /** @var string Ruta USB de la impresora de tickets (si tipo=usb) */
    public $settingEscposUsb = '';

    /** @var string URL del relay ESC/POS (por defecto http://localhost:9091) */
    public $settingEscposRelayUrl = 'http://localhost:9091';

    /** @var string Serie de caja usada por RestauranteTPV */
    public $cashSerie = '';

    /** @var RestCaja|null Caja abierta del plugin RestauranteTPV */
    public $cashBox = null;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu']       = 'RestauranteTPV';
        $data['title']      = 'waiter-panel';
        $data['icon']       = 'fa-solid fa-utensils';
        $data['showonmenu'] = true;
        return $data;
    }

    public function getCoinTypes(): array
    {
        $modelCoin = new TpvCoin();
        $where = [new DataBaseWhere('coddivisa', $this->getDivisa()->coddivisa)];
        return $modelCoin->all($where, ['name' => 'asc'], 0, 0);
    }

    public function getComandasAbiertas(): array
    {
        $db = new DataBase();
        $rows = $db->select(
            "SELECT c.idcomanda, c.codcamarero, c.idmesa, m.nombre AS nombre_mesa,"
            . " (SELECT COUNT(*) FROM rest_comandas_lineas l WHERE l.idcomanda = c.idcomanda) AS nlineas"
            . " FROM rest_comandas c"
            . " LEFT JOIN rest_mesas m ON m.idmesa = c.idmesa"
            . " WHERE c.estado = " . $db->var2str(RestComanda::ESTADO_ABIERTA)
            . " ORDER BY c.idcomanda ASC"
        );
        return $rows ?: [];
    }

    public function getDivisa(): Divisa
    {
        $divisa = new Divisa();
        $divisa->loadFromCode(Tools::settings('default', 'coddivisa', 'EUR'));
        return $divisa;
    }

    public function getFormatMoney(float $money): string
    {
        $divisaTools = new DivisaTools();
        $divisaTools->findDivisa($this->getDivisa());
        return $this->toolBox()->coins()->format($money);
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $this->loadCashContext();

        $idmesa    = (int)$this->request->get('idmesa', 0);
        $getAction = $this->request->query->get('action', '');

        // Acciones GET
        if ($getAction === 'get-factura-html') {
            $this->actionGetFacturaHtml();
            return;
        }
        if ($getAction === 'print-escpos') {
            $this->actionPrintEscpos();
            return;
        }
        if ($getAction === 'print-kot') {
            $this->actionPrintKot();
            return;
        }
        if ($getAction === 'mark-kot-sent') {
            $this->actionMarkKotSent();
            return;
        }
        if ($getAction === 'search-facturas') {
            $this->actionSearchFacturas();
            return;
        }
        if ($getAction === 'open-mesa' && $idmesa > 0) {
            $this->actionOpenMesa($idmesa);
            return;
        }
        if ($getAction === 'cancel-order') {
            $this->actionCancelOrder($idmesa);
            return;
        }

        // Acciones POST que no requieren comanda
        $earlyPost = $this->request->request->get('action', '');
        if ($earlyPost === 'set-empresa') {
            $idempresa = (int)$this->request->request->get('idempresa', 0);
            if ($idempresa > 0) {
                Session::set('tpv_idempresa_' . $this->user->nick, $idempresa);
            }
            $db = new DataBase();
            $formasPago = $db->select(
                "SELECT codpago, descripcion FROM formaspago WHERE idempresa = $idempresa ORDER BY codpago ASC"
            ) ?: [];
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'formasPago' => $formasPago]);
            exit;
        }
        if ($earlyPost === 'cash-open') {
            $this->actionCashOpen();
            $this->redirectToCurrentContext();
            return;
        }
        if ($earlyPost === 'cash-open-start') {
            if ($this->isCashBoxOpen()) {
                $this->redirectToCurrentContext();
                return;
            }
            $this->loadEmpresas();
            $this->loadFormasPago();
            $this->setTemplate('RestauranteTPV/start-cash');
            return;
        }
        if ($earlyPost === 'cash-close-start') {
            if (false === $this->isCashBoxOpen()) {
                $this->toolBox()->log()->warning('No hay una caja abierta para cerrar.');
                $this->redirectToCurrentContext();
                return;
            }
            $this->loadEmpresas();
            $this->loadFormasPago();
            $this->setTemplate('RestauranteTPV/close-box-start');
            return;
        }
        if ($earlyPost === 'cash-close') {
            $this->actionCashClose();
            $this->redirectToCurrentContext();
            return;
        }
        if ($earlyPost === 'cash-in') {
            $this->actionCashMovement('in');
            $this->redirectToCurrentContext();
            return;
        }
        if ($earlyPost === 'cash-out') {
            $this->actionCashMovement('out');
            $this->redirectToCurrentContext();
            return;
        }
        if ($earlyPost === 'toggle-reserva') {
            $this->actionToggleReserva();
            return;
        }
        if ($earlyPost === 'save-mesa-pos') {
            $this->actionSaveMesaPos();
            return;
        }
        if ($earlyPost === 'factura-pedido') {
            $this->actionFacturarPedido();
            return;
        }
        if ($earlyPost === 'cancel-pedido') {
            $this->actionCancelarPedido();
            return;
        }
        if ($earlyPost === 'reabrir-comanda') {
            $this->actionReabrirComanda();
            return;
        }

        // Vista de pedidos en curso
        if ($this->request->get('vista', '') === 'ordenes') {
            $earlyOrdenes = $this->request->request->get('action', '');
            if ($earlyOrdenes === 'factura-pedido') {
                $this->actionFacturarPedido();
                return;
            }
            if ($earlyOrdenes === 'cancel-pedido') {
                $this->actionCancelarPedido();
                return;
            }
            $this->vistaOrdenes = true;
            $this->loadOrdenesAbiertas();
            $this->ticketEmpresa = Tools::settings('default', 'empresa', '');
            $this->loadTicketIfNeeded();
            return;
        }

        // Limpiar comandas vacías con más de 10 minutos sin productos
        $this->cleanupStaleOrders();

        // Cargar mesa si se especificó
        if ($idmesa > 0) {
            $this->mesa = new RestMesa();
            if (false === $this->mesa->loadFromCode($idmesa)) {
                $this->mesa = null;
                $idmesa = 0;
            }
        }

        // Cargar comanda según contexto
        $idcomanda = (int)$this->request->get('idcomanda', 0);
        if ($idmesa > 0) {
            // Comanda vinculada a una mesa — buscar activa (abierta o en proceso)
            $this->comanda = new RestComanda();
            $where = [
                new DataBaseWhere('idmesa', $idmesa),
                new DataBaseWhere('estado', implode(',', [RestComanda::ESTADO_ABIERTA, RestComanda::ESTADO_EN_PROCESO]), 'IN'),
            ];
            if (false === $this->comanda->loadFromCode('', $where)) {
                // No crear comanda aún — solo crearla cuando se añada el primer producto
                $postAction2 = $this->request->request->get('action', '');
                $actionsNeedComanda2 = ['add-producto', 'remove-linea', 'update-cantidad',
                    'update-precio', 'send-cocina', 'cobrar', 'update-tipo',
                    'add-producto-con-modif', 'update-linea-con-modif'];
                if (in_array($postAction2, $actionsNeedComanda2)) {
                    $this->comanda = new RestComanda();
                    $this->comanda->idmesa      = $idmesa;
                    $this->comanda->codcamarero = $user->nick;
                    $this->comanda->save();
                } else {
                    $this->comanda = null;
                }
            } elseif ($this->comanda->estado === RestComanda::ESTADO_EN_PROCESO && $this->comanda->idpedido) {
                $this->comanda->estado = RestComanda::ESTADO_ABIERTA;
                $this->comanda->save();
            }
        } elseif ($idcomanda > 0) {
            // Modificar pedido: cargar comanda específica
            $this->comanda = new RestComanda();
            if ($this->comanda->loadFromCode($idcomanda)) {
                if ($this->comanda->estado === RestComanda::ESTADO_EN_PROCESO) {
                    $this->comanda->estado = RestComanda::ESTADO_ABIERTA;
                    $this->comanda->save();
                }
                if ($this->comanda->idmesa) {
                    $this->mesa = new RestMesa();
                    if (false === $this->mesa->loadFromCode($this->comanda->idmesa)) {
                        $this->mesa = null;
                    }
                }
            } else {
                $this->comanda = null;
            }
        } else {
            // Sin mesa: buscar comanda abierta sin mesa para este camarero
            $candidata = new RestComanda();
            $whereNoMesa = [
                new DataBaseWhere('idmesa', null, 'IS'),
                new DataBaseWhere('codcamarero', $user->nick),
                new DataBaseWhere('estado', RestComanda::ESTADO_ABIERTA),   
            ];
            if ($candidata->loadFromCode('', $whereNoMesa)) {
                $this->comanda = $candidata;
            }

            // Si hay acción POST que requiere comanda, crear una si no existe aún
            $postAction = $this->request->request->get('action', '');
            $actionsNeedComanda = ['add-producto', 'remove-linea', 'update-cantidad',
                                   'update-precio', 'send-cocina', 'cobrar', 'update-tipo',
                                   'add-producto-con-modif', 'update-linea-con-modif'];
            if (null === $this->comanda && in_array($postAction, $actionsNeedComanda)) {
                $tipoDefault = Tools::settings('restaurantetpv', 'servicio_defecto', RestComanda::TIPO_PARA_LLEVAR);
                // Si el default es 'in-table' pero no hay mesa, usar take-away
                if ($tipoDefault === RestComanda::TIPO_MESA) {
                    $tipoDefault = RestComanda::TIPO_PARA_LLEVAR;
                }
                $this->comanda = new RestComanda();
                $this->comanda->codcamarero = $user->nick;
                $this->comanda->tipo = ($postAction === 'update-tipo')
                    ? $this->request->request->get('tipo', $tipoDefault)
                    : $tipoDefault;
                $this->comanda->save();
            } elseif (null === $this->comanda && $idmesa > 0 && in_array($postAction, $actionsNeedComanda)) {
                // Crear comanda vinculada a mesa solo al añadir el primer producto
                $this->comanda = new RestComanda();
                $this->comanda->idmesa      = $idmesa;
                $this->comanda->codcamarero = $user->nick;
                $this->comanda->save();
            } elseif (null === $this->comanda) {
                // Sin comanda y sin acción → pantalla de selección inicial
                $this->loadZonasYMesas();
                $this->loadCatalogo();
                $this->loadClientes();
                $this->loadUsuarios();
                $this->loadEmpresas();
                $this->loadOrdenesAbiertas();
                $this->loadFacturasS();
                $this->loadFormasPago();
                $this->ticketEmpresa = Tools::settings('default', 'empresa', '');
                $this->loadTicketIfNeeded();
                return;
            }
        }

        // Procesar acciones POST
        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'add-producto':
                $this->actionAddProducto();
                break;
            case 'remove-linea':
                $this->actionRemoveLinea();
                break;
            case 'update-cantidad':
                $this->actionUpdateCantidad();
                break;
            case 'update-precio':
                $this->actionUpdatePrecio();
                break;
            case 'update-linea':
                $this->actionUpdateLinea();
                break;
            case 'update-linea-con-modif':
                $this->actionUpdateLineaConModif();
                break;
            case 'add-producto-con-modif':
                $this->actionAddProductoConModif();
                break;
            case 'send-cocina':
                $this->actionSendCocina();
                break;
            case 'cobrar':
                $this->actionCobrar();
                return;
            case 'save-debug':
                $this->actionSaveDebug();
                return;
            case 'create-cliente':
                $this->actionCreateCliente();
                return;
            case 'update-cliente':
                $this->actionUpdateCliente();
                return;
            case 'update-tipo':
                $this->actionUpdateTipo();
                break;
            case 'toggle-reserva':
                $this->actionToggleReserva();
                return;
            case 'mover-comanda':
                $this->actionMoverComanda();
                return;
            case 'juntar-mesas':
                $this->actionJuntarMesas();
                return;
        }

        // Recargar comanda y líneas tras la acción
        $this->comanda->loadFromCode($this->comanda->idcomanda);
        $this->loadLineas();
        $this->loadCatalogo();
        $this->loadClientes();
        $this->loadUsuarios();
        $this->loadEmpresas();
        $this->loadZonasYMesas();
        $this->loadOrdenesAbiertas();
        $this->loadFacturasS();
        $this->loadFormasPago();

        // Detectar ticket de impresión pendiente
        $this->loadTicketIfNeeded();
    }

    /**
     * Mueve la comanda actual a otra mesa, liberando la mesa de origen.
     */
    protected function actionMoverComanda(): void
    {
        $idmesaNueva = (int)$this->request->request->get('idmesa_nueva', 0);
        $idmesaActual = $this->mesa ? (int)$this->mesa->idmesa : 0;

        if (null === $this->comanda || $idmesaNueva <= 0 || $idmesaNueva === $idmesaActual) {
            $this->redirect($this->url() . ($idmesaActual > 0 ? '?idmesa=' . $idmesaActual : ''));
            return;
        }

        $mesaNueva = new RestMesa();
        if (false === $mesaNueva->loadFromCode($idmesaNueva) || $mesaNueva->estado === RestMesa::ESTADO_OCUPADA) {
            $this->toolBox()->i18nLog()->warning('table-not-available');
            $this->redirect($this->url() . ($idmesaActual > 0 ? '?idmesa=' . $idmesaActual : ''));
            return;
        }

        // Liberar mesa de origen
        if ($this->mesa) {
            $this->mesa->estado = RestMesa::ESTADO_LIBRE;
            $this->mesa->save();
        }

        // Reasignar comanda a nueva mesa
        $this->comanda->idmesa = $idmesaNueva;
        $this->comanda->save();

        // Marcar nueva mesa como ocupada
        $mesaNueva->estado = RestMesa::ESTADO_OCUPADA;
        $mesaNueva->save();

        $this->redirect($this->url() . '?idmesa=' . $idmesaNueva);
    }

    /**
     * Junta la comanda de otra mesa (secundaria) con la comanda actual (principal),
     * moviendo todas sus líneas y liberando la mesa secundaria.
     */
    protected function actionJuntarMesas(): void
    {
        $idmesaSecundaria = (int)$this->request->request->get('idmesa_secundaria', 0);
        $idmesaActual = $this->mesa ? (int)$this->mesa->idmesa : 0;

        if (null === $this->comanda || $idmesaSecundaria <= 0 || $idmesaSecundaria === $idmesaActual) {
            $this->redirect($this->url() . ($idmesaActual > 0 ? '?idmesa=' . $idmesaActual : ''));
            return;
        }

        // Buscar la comanda activa de la mesa secundaria
        $comandaSecundaria = new RestComanda();
        $where = [
            new DataBaseWhere('idmesa', $idmesaSecundaria),
            new DataBaseWhere('estado', RestComanda::ESTADO_CANCELADA, '!='),
            new DataBaseWhere('estado', RestComanda::ESTADO_COBRADA, '!='),
        ];
        if (false === $comandaSecundaria->loadFromCode('', $where)) {
            $this->toolBox()->i18nLog()->warning('table-without-order');
            $this->redirect($this->url() . '?idmesa=' . $idmesaActual);
            return;
        }

        // Mover todas las líneas de la comanda secundaria a la principal
        $db = new DataBase();
        $db->exec('UPDATE rest_comandas_lineas SET idcomanda = ' . $db->var2str($this->comanda->idcomanda)
            . ' WHERE idcomanda = ' . $db->var2str($comandaSecundaria->idcomanda));

        // Cancelar el pedido vinculado a la comanda secundaria, si lo tiene
        if ($comandaSecundaria->idpedido) {
            $pedidoSec = new PedidoCliente();
            if ($pedidoSec->loadFromCode($comandaSecundaria->idpedido)) {
                $pedidoSec->idestado = 6; // cancelado
                $pedidoSec->save();
            }
        }

        // Cancelar la comanda secundaria
        $comandaSecundaria->estado = RestComanda::ESTADO_CANCELADA;
        $comandaSecundaria->save();

        // Liberar la mesa secundaria
        $mesaSecundaria = new RestMesa();
        if ($mesaSecundaria->loadFromCode($idmesaSecundaria)) {
            $mesaSecundaria->estado = RestMesa::ESTADO_LIBRE;
            $mesaSecundaria->save();
        }

        // Recalcular el total de la comanda principal con todas las líneas absorbidas
        $this->recalcularTotal();

        $this->redirect($this->url() . '?idmesa=' . $idmesaActual);
    }

    /**
     * Alterna el estado de una mesa entre libre y reservada.
     * Devuelve JSON { ok: true, estado: 'reservada'|'libre' }
     */
    protected function actionToggleReserva(): void
    {
        $idmesa = (int)$this->request->request->get('idmesa', 0);
        $mesa   = new RestMesa();
        if ($idmesa <= 0 || false === $mesa->loadFromCode($idmesa)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Mesa no encontrada']));
            $this->response->headers->set('Content-Type', 'application/json');
            return;
        }
        if ($mesa->estado === RestMesa::ESTADO_OCUPADA) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Mesa ocupada']));
            $this->response->headers->set('Content-Type', 'application/json');
            return;
        }
        $mesa->estado = ($mesa->estado === RestMesa::ESTADO_RESERVADA)
            ? RestMesa::ESTADO_LIBRE
            : RestMesa::ESTADO_RESERVADA;
        $mesa->save();
        $this->response->setContent(json_encode(['ok' => true, 'estado' => $mesa->estado]));
        $this->response->headers->set('Content-Type', 'application/json');
    }

    /**
     * Guarda la posición (pos_x, pos_y) de una mesa en el mapa.
     * Devuelve JSON { ok: true }
     */
    protected function actionSaveMesaPos(): void
    {
        $idmesa = (int)$this->request->request->get('idmesa', 0);
        $posX   = (int)$this->request->request->get('pos_x', 0);
        $posY   = (int)$this->request->request->get('pos_y', 0);
        $mesa   = new RestMesa();
        if ($idmesa <= 0 || false === $mesa->loadFromCode($idmesa)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Mesa no encontrada']));
            $this->response->headers->set('Content-Type', 'application/json');
            return;
        }
        $mesa->pos_x = max(0, $posX);
        $mesa->pos_y = max(0, $posY);
        $mesa->save();
        $this->response->setContent(json_encode(['ok' => true]));
        $this->response->headers->set('Content-Type', 'application/json');
    }

    /**
     * Añade un producto a la comanda. Si ya existe una línea con esa referencia,
     * incrementa la cantidad en 1.
     */
    protected function actionAddProducto(): void
    {
        $referencia = $this->request->request->get('referencia', '');
        if (empty($referencia)) {
            return;
        }

        // Buscar si ya existe esa línea en la comanda
        $linea = new RestComandaLinea();
        $where = [
            new DataBaseWhere('idcomanda', $this->comanda->idcomanda),
            new DataBaseWhere('referencia', $referencia),
            new DataBaseWhere('estado', RestComandaLinea::ESTADO_PENDIENTE),
        ];

        if ($linea->loadFromCode('', $where)) {
            // Incrementar cantidad
            $linea->cantidad += 1;
            $linea->save();
        } else {
            // Crear nueva línea con datos del producto
            $db = new DataBase();
            $sql = 'SELECT p.descripcion, v.precio, COALESCE(v.pricewithtaxes, v.precio) AS precio_iva'
                . ' FROM variantes v'
                . ' LEFT JOIN productos p ON v.idproducto = p.idproducto'
                . ' WHERE v.referencia = ' . $db->var2str($referencia)
                . ' LIMIT 1';
            $rows = $db->select($sql);

            $linea->idcomanda   = $this->comanda->idcomanda;
            $linea->referencia  = $referencia;
            $linea->descripcion = $rows[0]['descripcion'] ?? $referencia;
            $linea->pvpunitario = (float)($rows[0]['precio_iva'] ?? 0);
            $linea->cantidad    = 1;
            $linea->estado      = RestComandaLinea::ESTADO_PENDIENTE;
            $linea->save();
        }

        $this->recalcularTotal();
    }

    /**
     * Elimina una línea de la comanda.
     */
    protected function actionRemoveLinea(): void
    {
        $idlinea = (int)$this->request->request->get('idlinea', 0);
        $linea = new RestComandaLinea();
        if ($linea->loadFromCode($idlinea) && $linea->idcomanda === $this->comanda->idcomanda) {
            // Si es línea padre (no modificador), eliminar primero sus hijos modificadores
            if (empty($linea->idlinea_padre)) {
                $db = new DataBase();
                $db->exec('DELETE FROM rest_comandas_lineas WHERE idlinea_padre = ' . $db->var2str($idlinea));
            }
            $linea->delete();
            $this->recalcularTotal();
        }
    }

    /**
     * Actualiza la cantidad de una línea.
     */
    protected function actionUpdateCantidad(): void
    {
        $idlinea  = (int)$this->request->request->get('idlinea', 0);
        $cantidad = (float)$this->request->request->get('cantidad', 1);

        $linea = new RestComandaLinea();
        if (false === $linea->loadFromCode($idlinea) || $linea->idcomanda !== $this->comanda->idcomanda) {
            return;
        }

        // Si la línea ya fue enviada a cocina (PREPARADO o SERVIDO) y se incrementa la cantidad,
        // crear una nueva línea PENDIENTE con la diferencia en vez de modificar la existente.
        $estadosBloqueados = [RestComandaLinea::ESTADO_PREPARADO, RestComandaLinea::ESTADO_SERVIDO];
        if (in_array($linea->estado, $estadosBloqueados) && $cantidad > $linea->cantidad) {
            $diff = $cantidad - $linea->cantidad;
            $nueva = new RestComandaLinea();
            $nueva->idcomanda   = $linea->idcomanda;
            $nueva->referencia  = $linea->referencia;
            $nueva->descripcion = $linea->descripcion;
            $nueva->pvpunitario = $linea->pvpunitario;
            $nueva->cantidad    = $diff;
            $nueva->estado      = RestComandaLinea::ESTADO_PENDIENTE;
            $nueva->enviado     = 0;
            $nueva->save();
            $this->recalcularTotal();
            return;
        }

        if ($cantidad <= 0) {
            // Eliminar también los adicionales vinculados
            $db = new DataBase();
            $db->exec('DELETE FROM rest_comandas_lineas WHERE idlinea_padre = ' . $db->var2str($idlinea));
            $linea->delete();
        } else {
            $linea->cantidad = $cantidad;
            $linea->save();

            // Actualizar la cantidad de los adicionales vinculados a esta línea
            $hijos = (new RestComandaLinea())->all(
                [new DataBaseWhere('idlinea_padre', $idlinea)],
                [],
                0,
                0
            );
            foreach ($hijos as $hijo) {
                $hijo->cantidad = $cantidad;
                $hijo->save();
            }
        }

        $this->recalcularTotal();
    }

    protected function actionUpdatePrecio(): void
    {
        $idlinea = (int)$this->request->request->get('idlinea', 0);
        $precio  = (float)str_replace(',', '.', $this->request->request->get('precio', ''));

        $linea = new RestComandaLinea();
        if (false === $linea->loadFromCode($idlinea) || $linea->idcomanda !== $this->comanda->idcomanda) {
            return;
        }

        $linea->pvpunitario = $precio;
        $linea->save();
        $this->recalcularTotal();
    }

    protected function actionUpdateLinea(): void
    {
        $idlinea  = (int)$this->request->request->get('idlinea', 0);
        $cantidad = (float)str_replace(',', '.', $this->request->request->get('cantidad', '1'));
        $nota     = $this->request->request->get('nota', '');

        $linea = new RestComandaLinea();
        if (false === $linea->loadFromCode($idlinea) || $linea->idcomanda !== $this->comanda->idcomanda) {
            return;
        }

        if ($cantidad <= 0) {
            $linea->delete();
        } else {
            $linea->cantidad      = $cantidad;
            $linea->observaciones = $nota;
            $linea->save();
        }

        $this->recalcularTotal();
    }

    /**
     * Actualiza una línea y reemplaza sus líneas de modificadores vinculadas.
     */
    protected function actionUpdateLineaConModif(): void
    {
        $idlinea   = (int)$this->request->request->get('idlinea', 0);
        $cantidad  = (float)str_replace(',', '.', $this->request->request->get('cantidad', '1'));
        $nota      = $this->request->request->get('nota', '');
        $modifJson = $this->request->request->get('modificadores', '[]');
        $modifs    = json_decode($modifJson, true) ?: [];

        $linea = new RestComandaLinea();
        if (false === $linea->loadFromCode($idlinea) || $linea->idcomanda !== $this->comanda->idcomanda) {
            return;
        }

        $db = new DataBase();

        if ($cantidad <= 0) {
            // Borrar línea principal + todas sus hijas
            $db->exec('DELETE FROM rest_comandas_lineas WHERE idlinea_padre = ' . $db->var2str($idlinea));
            $linea->delete();
        } else {
            $linea->cantidad      = $cantidad;
            $linea->observaciones = $nota;
            $linea->save();

            // Eliminar hijas anteriores
            $db->exec('DELETE FROM rest_comandas_lineas WHERE idlinea_padre = ' . $db->var2str($idlinea));

            // Crear nuevas hijas para los modificadores seleccionados
            foreach ($modifs as $modif) {
                $nombre = strip_tags(trim($modif['nombre'] ?? ''));
                $precio = (float)($modif['precio'] ?? 0);
                if (empty($nombre)) {
                    continue;
                }
                $mLinea = new RestComandaLinea();
                $mLinea->idcomanda     = $this->comanda->idcomanda;
                $mLinea->referencia    = '';
                $mLinea->descripcion   = '+ ' . $nombre;
                $mLinea->pvpunitario   = $precio;
                $mLinea->cantidad      = $cantidad;
                $mLinea->estado        = RestComandaLinea::ESTADO_PENDIENTE;
                $mLinea->idlinea_padre = $idlinea;
                $mLinea->save();
            }
        }

        $this->recalcularTotal();
    }

    /**
     * Añade un producto con modificadores seleccionados a la comanda.
     * Crea una línea principal y una línea extra por cada modificador elegido.
     */
    protected function actionAddProductoConModif(): void
    {
        $referencia = $this->request->request->get('referencia', '');
        $cantidad   = max(1.0, (float)str_replace(',', '.', $this->request->request->get('cantidad', '1')));
        $nota       = $this->request->request->get('nota', '');
        $modifJson  = $this->request->request->get('modificadores', '[]');
        $modifs     = json_decode($modifJson, true) ?: [];

        if (empty($referencia)) {
            return;
        }

        $db = new DataBase();
        $sql = 'SELECT p.descripcion, COALESCE(v.pricewithtaxes, v.precio) AS precio_iva'
            . ' FROM variantes v'
            . ' LEFT JOIN productos p ON v.idproducto = p.idproducto'
            . ' WHERE v.referencia = ' . $db->var2str($referencia)
            . ' LIMIT 1';
        $rows = $db->select($sql);

        $linea = new RestComandaLinea();
        $linea->idcomanda    = $this->comanda->idcomanda;
        $linea->referencia   = $referencia;
        $linea->descripcion  = $rows[0]['descripcion'] ?? $referencia;
        $linea->pvpunitario  = (float)($rows[0]['precio_iva'] ?? 0);
        $linea->cantidad     = $cantidad;
        $linea->estado       = RestComandaLinea::ESTADO_PENDIENTE;
        $linea->observaciones = $nota;
        if (false === $linea->save()) {
            return;
        }

        // Crear una línea extra por cada modificador seleccionado
        foreach ($modifs as $modif) {
            $nombre = strip_tags(trim($modif['nombre'] ?? ''));
            $precio = (float)($modif['precio'] ?? 0);
            if (empty($nombre)) {
                continue;
            }
            $mLinea = new RestComandaLinea();
            $mLinea->idcomanda    = $this->comanda->idcomanda;
            $mLinea->referencia   = '';
            $mLinea->descripcion  = '+ ' . $nombre;
            $mLinea->pvpunitario  = $precio;
            $mLinea->cantidad     = $cantidad;
            $mLinea->estado       = RestComandaLinea::ESTADO_PENDIENTE;
            $mLinea->idlinea_padre = $linea->idlinea;
            $mLinea->save();
        }

        $this->recalcularTotal();
    }

    /**
     * Marca todas las líneas pendientes como enviadas a cocina.
     * Cambia el estado de 'pendiente' a 'preparado' para que la cocina las vea.
     * Las próximas líneas que añada el camarero empezarán como 'pendiente'
     * y sólo se enviarán en el siguiente relanzamiento.
     */
    protected function actionSendCocina(): void
    {
        if (!$this->comanda) {
            return;
        }
        // Marcar como enviadas todas las líneas pendientes aún no enviadas
        $db = new DataBase();
        $db->exec('UPDATE rest_comandas_lineas SET enviado = 1'
            . ' WHERE idcomanda = ' . $db->var2str($this->comanda->idcomanda)
            . ' AND enviado = 0');
    }

    /**
     * Genera un PresupuestoCliente o FacturaCliente con las líneas de la comanda,
     * marca la comanda como cobrada y libera la mesa.
     * Redirige al documento generado.
     */
    protected function actionCobrar(): void
    {
        // Si el POST incluye idcomanda (p.ej. split desde pedido sin mesa),
        // cargamos la comanda correcta en lugar de la que detectó privateCore.
        $idComandaPost = (int)$this->request->request->get('idcomanda', 0);
        if ($idComandaPost > 0 && ($this->comanda === null || $this->comanda->idcomanda != $idComandaPost)) {
            $comandaFromPost = new RestComanda();
            if ($comandaFromPost->loadFromCode($idComandaPost)) {
                $this->comanda = $comandaFromPost;
            }
        }

        $this->loadLineas();

        $backUrl = $this->mesa ? $this->url() . '?idmesa=' . $this->mesa->idmesa : $this->url();
        if (false === $this->isCashBoxOpen()) {
            $this->toolBox()->log()->warning('Debes abrir caja antes de cobrar.');
            $this->redirect($backUrl);
            return;
        }

        if (empty($this->lineas)) {
            $this->toolBox()->i18nLog()->warning('no-lines-yet');
            $this->redirect($backUrl);
            return;
        }
        if ($this->comanda && $this->comanda->tipo === RestComanda::TIPO_MESA && !$this->mesa) {
            $this->toolBox()->i18nLog()->warning('select-table-first');
            $this->redirect($backUrl);
            return;
        }

        // Resolver cliente: POST > configuración del plugin > primer cliente del sistema
        $codcliente = $this->request->request->get('codcliente', '');
        if (empty($codcliente)) {
            $codcliente = Tools::settings('restaurantetpv', 'codcliente', '');
        }
        $cliente = new Cliente();
        if (empty($codcliente) || false === $cliente->loadFromCode($codcliente)) {
            $todos = $cliente->all([], ['idcliente' => 'asc'], 0, 1);
            if (empty($todos)) {
                $this->toolBox()->i18nLog()->error('no-customer-found');
                $this->redirect($backUrl);
                return;
            }
            $cliente = $todos[0];
        }

        // Tipo documento: POST > configuración del plugin > pedidocliente
        $tipodoc = $this->request->request->get('tipodoc', '');
        if (empty($tipodoc)) {
            $tipodoc = Tools::settings('restaurantetpv', 'tipodoc', 'pedidocliente');
        }

        $nomOrden = $this->mesa ? 'Mesa: ' . $this->mesa->nombre : ucfirst($this->comanda->tipo);

        // Resolver codpago: primero el valor enviado por el modal, luego el de configuración
        $codpagoDefault = $this->request->request->get('codpago', '');
        if (empty($codpagoDefault)) {
            $codpagoDefault = Tools::settings('default', 'codpago', '');
        }
        if (empty($codpagoDefault)) {
            $db = new DataBase();
            $rows = $db->select('SELECT codpago FROM formaspago ORDER BY codpago ASC LIMIT 1');
            $codpagoDefault = $rows[0]['codpago'] ?? '';
        }

        // Importe dado por el cliente y cambio
        $importedado = (float)str_replace(',', '.', $this->request->request->get('importedado', '0'));
        $numPersonas = max(1, (int)$this->request->request->get('num_personas', 1));

        // Rama split por artículos: cada persona paga sus propios productos
        $splitJson = $this->request->request->get('split_asignaciones', '');
        if (!empty($splitJson) && ($tipodoc === 'factura' || $tipodoc === 'factura-pedido')) {
            $asignaciones = json_decode($splitJson, true) ?? [];
            // Si venía de un pedido, cancelarlo antes de crear las facturas
            if ($this->comanda->idpedido) {
                $pedidoViejo = new PedidoCliente();
                if ($pedidoViejo->loadFromCode($this->comanda->idpedido)) {
                    $pedidoViejo->idestado = 6; // cancelado
                    $pedidoViejo->save();
                }
                $this->comanda->idpedido = null;
                $this->comanda->save();
            }
            // Indexar líneas por idlinea
            $lineasPorId = [];
            foreach ($this->lineas as $l) {
                if ($l->estado !== RestComandaLinea::ESTADO_CANCELADO) {
                    $lineasPorId[$l->idlinea] = $l;
                }
            }
            // Agrupar líneas por persona con su cantidad asignada
            $personaLineas = [];
            foreach ($asignaciones as $item) {
                $idlinea = (int)($item['idlinea'] ?? 0);
                $persona = max(1, (int)($item['persona'] ?? 1));
                $cantidadAsig = (float)($item['cantidad'] ?? 0);
                if ($cantidadAsig > 0 && isset($lineasPorId[$idlinea])) {
                    $personaLineas[$persona][] = ['linea' => $lineasPorId[$idlinea], 'cantidad' => $cantidadAsig];
                }
            }
            if (empty($personaLineas)) {
                $this->toolBox()->i18nLog()->error('no-lines-yet');
                $this->redirect($backUrl);
                return;
            }
            $tpvSerieSplit = Tools::settings('restaurantetpv', 'codserie', '') ?: Tools::settings('default', 'codserie', '');
            $dadosPersonasSplitJson = $this->request->request->get('dados_personas', '');
            $dadosPersonasSplitArr = !empty($dadosPersonasSplitJson) ? (json_decode($dadosPersonasSplitJson, true) ?? []) : [];
            $dbSplit = new DataBase();
            $primerIdFactura = null;
            $facturaIds = [];
            foreach ($personaLineas as $persona => $lineasPersona) {
                $docS = new FacturaCliente();
                $docS->setAuthor($this->user);
                $docS->setSubject($cliente);
                if (!empty($tpvSerieSplit)) {
                    $docS->codserie = $tpvSerieSplit;
                }
                if (empty($docS->coddivisa)) {
                    $docS->coddivisa = Tools::settings('default', 'coddivisa', 'EUR');
                }
                $docS->codpago = $codpagoDefault;
                $docS->observaciones = $nomOrden . ' | Comanda #' . $this->comanda->idcomanda . ' | Persona ' . $persona;
                if (false === $docS->save()) {
                    $this->toolBox()->i18nLog()->error('record-save-error');
                    $this->redirect($backUrl);
                    return;
                }
                foreach ($lineasPersona as $item) {
                    $linea = $item['linea'];
                    if (!empty($linea->referencia)) {
                        $newLineS = $docS->getNewProductLine($linea->referencia);
                    } else {
                        $newLineS = $docS->getNewLine();
                        $newLineS->descripcion = $linea->descripcion;
                        $newLineS->pvpunitario = $linea->pvpunitario;
                    }
                    $newLineS->cantidad = $item['cantidad'];
                    if (false === $newLineS->save()) {
                        $this->toolBox()->i18nLog()->error('record-save-error');
                        $docS->delete();
                        $this->redirect($backUrl);
                        return;
                    }
                }
                $docLinesS = $docS->getLines();
                Calculator::calculate($docS, $docLinesS, true);
                $this->marcarRecibosPagados((int)$docS->idfactura);
                $this->registerCashSaleByInvoiceId((int) $docS->idfactura);
                $facturaIds[] = $docS->idfactura;
                $dadoSplit = isset($dadosPersonasSplitArr[$persona - 1]) ? (float)$dadosPersonasSplitArr[$persona - 1] : 0.0;
                $cambioSplit = $dadoSplit > 0 ? max(0.0, $dadoSplit - $docS->total) : 0.0;
                if ($dadoSplit > 0) {
                    $dbSplit->exec('UPDATE facturascli SET tpv_efectivo = ' . $dbSplit->var2str($dadoSplit)
                        . ', tpv_cambio = ' . $dbSplit->var2str($cambioSplit)
                        . ' WHERE idfactura = ' . $dbSplit->var2str($docS->idfactura));
                }
                if ($primerIdFactura === null) {
                    $primerIdFactura = $docS->idfactura;
                }
            }
            $this->comanda->idfactura = $primerIdFactura;
            $this->comanda->estado    = RestComanda::ESTADO_COBRADA;
            $this->comanda->save();
            if ($this->mesa) {
                $this->mesa->estado = RestMesa::ESTADO_LIBRE;
                $this->mesa->save();
            }
            $totalDadoSplit = array_sum($dadosPersonasSplitArr);
            $ticketUrl = $this->url() . '?ticket=' . $this->comanda->idcomanda
                . '&tcodcl=' . urlencode($cliente->codcliente)
                . '&tdado=' . $totalDadoSplit . '&tcambio=0'
                . (count($facturaIds) > 1 ? '&facturas=' . implode(',', $facturaIds) : '');
            $this->toolBox()->i18nLog()->info('record-updated-correctly');
            $this->redirect($ticketUrl);
            return;
        }

        // Rama multi-persona: crear N facturas independientes
        if (($tipodoc === 'factura' || $tipodoc === 'factura-pedido') && $numPersonas > 1) {
            // Si venía de un pedido, cancelarlo antes de crear las facturas
            if ($this->comanda->idpedido) {
                $pedidoViejoM = new PedidoCliente();
                if ($pedidoViejoM->loadFromCode($this->comanda->idpedido)) {
                    $pedidoViejoM->idestado = 6; // cancelado
                    $pedidoViejoM->save();
                }
                $this->comanda->idpedido = null;
                $this->comanda->save();
            }
            // Importes individuales por persona (JSON del frontend)
            $dadosPersonasJson = $this->request->request->get('dados_personas', '');
            $dadosPersonasArr = !empty($dadosPersonasJson) ? (json_decode($dadosPersonasJson, true) ?? []) : [];
            $dadoPorPersona = $importedado / $numPersonas; // fallback si no viene JSON
            $primerIdFactura = null;
            $totalPorPersona = 0.0;
            $facturaIds = [];
            $dbMulti = new DataBase();

            for ($i = 1; $i <= $numPersonas; $i++) {
                $docN = new FacturaCliente();
                $docN->setAuthor($this->user);
                $docN->setSubject($cliente);
                $tpvSerieN = Tools::settings('restaurantetpv', 'codserie', '') ?: Tools::settings('default', 'codserie', '');
                if (!empty($tpvSerieN)) {
                    $docN->codserie = $tpvSerieN;
                }
                if (empty($docN->coddivisa)) {
                    $docN->coddivisa = Tools::settings('default', 'coddivisa', 'EUR');
                }
                $docN->codpago = $codpagoDefault;
                $docN->observaciones = $nomOrden . ' | Comanda #' . $this->comanda->idcomanda . ' (' . $i . '/' . $numPersonas . ')';
                if (false === $docN->save()) {
                    $this->toolBox()->i18nLog()->error('record-save-error');
                    $this->redirect($backUrl);
                    return;
                }

                foreach ($this->lineas as $linea) {
                    if ($linea->estado === RestComandaLinea::ESTADO_CANCELADO) {
                        continue;
                    }
                    if (!empty($linea->referencia)) {
                        $newLineN = $docN->getNewProductLine($linea->referencia);
                    } else {
                        $newLineN = $docN->getNewLine();
                        $newLineN->descripcion = $linea->descripcion;
                        $newLineN->pvpunitario = $linea->pvpunitario;
                    }
                    $newLineN->cantidad = $linea->cantidad / $numPersonas;
                    if (false === $newLineN->save()) {
                        $this->toolBox()->i18nLog()->error('record-save-error');
                        $docN->delete();
                        $this->redirect($backUrl);
                        return;
                    }
                }

                $docLinesN = $docN->getLines();
                Calculator::calculate($docN, $docLinesN, true);
                $this->marcarRecibosPagados((int)$docN->idfactura);
                $this->registerCashSaleByInvoiceId((int) $docN->idfactura);
                $facturaIds[] = $docN->idfactura;

                // Importe dado por esta persona (individual si vino del JSON, equitativo como fallback)
                $dadoEstaPersona = isset($dadosPersonasArr[$i - 1]) ? (float)$dadosPersonasArr[$i - 1] : $dadoPorPersona;
                $tcambioPorPersona = $dadoEstaPersona > 0 ? max(0.0, $dadoEstaPersona - $docN->total) : 0.0;
                if ($dadoEstaPersona > 0) {
                    $dbMulti->exec('UPDATE facturascli SET tpv_efectivo = ' . $dbMulti->var2str($dadoEstaPersona)
                        . ', tpv_cambio = ' . $dbMulti->var2str($tcambioPorPersona)
                        . ' WHERE idfactura = ' . $dbMulti->var2str($docN->idfactura));
                }

                if ($primerIdFactura === null) {
                    $primerIdFactura = $docN->idfactura;
                    $totalPorPersona = $docN->total;
                }
            }

            $this->comanda->idfactura = $primerIdFactura;
            $this->comanda->estado    = RestComanda::ESTADO_COBRADA;
            $this->comanda->save();

            if ($this->mesa) {
                $this->mesa->estado = RestMesa::ESTADO_LIBRE;
                $this->mesa->save();
            }

            $tcambioTotal = $importedado > 0 ? max(0.0, $importedado - ($totalPorPersona * $numPersonas)) : 0.0;
            $ticketUrl = $this->url() . '?ticket=' . $this->comanda->idcomanda
                . '&tcodcl=' . urlencode($cliente->codcliente)
                . '&tdado=' . $importedado
                . '&tcambio=' . $tcambioTotal
                . (count($facturaIds) > 1 ? '&facturas=' . implode(',', $facturaIds) : '');

            $this->toolBox()->i18nLog()->info('record-updated-correctly');
            $this->redirect($ticketUrl);
            return;
        }

        $isNewDoc = true;
        if ($tipodoc === 'factura') {
            $doc = new FacturaCliente();
            $doc->setAuthor($this->user);
            $doc->setSubject($cliente);
            $tpvSerie = Tools::settings('restaurantetpv', 'codserie', '') ?: Tools::settings('default', 'codserie', '');
            if (!empty($tpvSerie)) {
                $doc->codserie = $tpvSerie;
            }
            if (empty($doc->coddivisa)) {
                $doc->coddivisa = Tools::settings('default', 'coddivisa', 'EUR');
            }
            if (empty($doc->codpago)) {
                $doc->codpago = $codpagoDefault;
            }
            $doc->observaciones = $nomOrden . ' | Comanda #' . $this->comanda->idcomanda;
            if (false === $doc->save()) {
                $this->toolBox()->i18nLog()->error('record-save-error');
                $this->redirect($backUrl);
                return;
            }
        } else {
            if ($this->comanda->idpedido) {
                // Actualizar pedido existente (comanda en modo modificar)
                $doc = new PedidoCliente();
                if (false === $doc->loadFromCode($this->comanda->idpedido)) {
                    $this->toolBox()->i18nLog()->error('record-not-found');
                    $this->redirect($backUrl);
                    return;
                }
                $isNewDoc = false;
                // Borrar líneas existentes del pedido
                foreach ($doc->getLines() as $lineaVieja) {
                    $lineaVieja->delete();
                }
            } else {
                // Crear nuevo pedido
                $doc = new PedidoCliente();
                $doc->setAuthor($this->user);
                $doc->setSubject($cliente);
                $tpvSerie = Tools::settings('restaurantetpv', 'codserie', '') ?: Tools::settings('default', 'codserie', '');
                if (!empty($tpvSerie)) {
                    $doc->codserie = $tpvSerie;
                }
                if (empty($doc->coddivisa)) {
                    $doc->coddivisa = Tools::settings('default', 'coddivisa', 'EUR');
                }
                if (empty($doc->codpago)) {
                    $doc->codpago = $codpagoDefault;
                }
                $doc->observaciones = $nomOrden . ' | Comanda #' . $this->comanda->idcomanda;
                if (false === $doc->save()) {
                    $this->toolBox()->i18nLog()->error('record-save-error');
                    $this->redirect($backUrl);
                    return;
                }
            }
        }

        // Construir y guardar líneas del documento
        foreach ($this->lineas as $linea) {
            if ($linea->estado === RestComandaLinea::ESTADO_CANCELADO) {
                continue;
            }
            if (!empty($linea->referencia)) {
                $newLine = $doc->getNewProductLine($linea->referencia);
            } else {
                $newLine = $doc->getNewLine();
                $newLine->descripcion = $linea->descripcion;
                $newLine->pvpunitario = $linea->pvpunitario;
            }
            $newLine->cantidad = $linea->cantidad;
            if (false === $newLine->save()) {
                $this->toolBox()->i18nLog()->error('record-save-error');
                if ($isNewDoc) { $doc->delete(); }
                $this->redirect($backUrl);
                return;
            }
        }

        // Recalcular totales del documento tras guardar líneas
        $docLines = $doc->getLines();
        if (false === Calculator::calculate($doc, $docLines, true)) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            if ($isNewDoc) { $doc->delete(); }
            $this->redirect($backUrl);
            return;
        }

        // Vincular comanda al documento generado
        if ($tipodoc === 'factura') {
            /** @var FacturaCliente $doc */
            $this->marcarRecibosPagados((int)$doc->idfactura);
            $this->registerCashSaleByInvoiceId((int) $doc->idfactura);
            $this->comanda->idfactura = $doc->idfactura;
            $this->comanda->estado    = RestComanda::ESTADO_COBRADA;
            $this->comanda->save();

            if ($this->mesa) {
                $this->mesa->estado = RestMesa::ESTADO_LIBRE;
                $this->mesa->save();
            }

            $tcambio = $importedado > 0 ? max(0, $importedado - $doc->total) : 0;
            // Guardar efectivo y cambio en la factura
            if ($importedado > 0) {
                $dbTpv = new DataBase();
                $dbTpv->exec('UPDATE facturascli SET tpv_efectivo = ' . $dbTpv->var2str($importedado)
                    . ', tpv_cambio = ' . $dbTpv->var2str($tcambio)
                    . ' WHERE idfactura = ' . $dbTpv->var2str($doc->idfactura));
            }
            $ticketUrl = $this->url() . '?ticket=' . $this->comanda->idcomanda
                . '&tcodcl=' . urlencode($cliente->codcliente)
                . '&tdado=' . $importedado
                . '&tcambio=' . $tcambio;
        } else {
            /** @var PedidoCliente $doc */
            $this->comanda->idpedido = $doc->idpedido;
            $this->comanda->estado   = RestComanda::ESTADO_EN_PROCESO;
            $this->comanda->save();

            // Marcar las nuevas líneas como enviadas (enviado=0 → enviado=1)
            $dbEnv = new DataBase();
            $dbEnv->exec('UPDATE rest_comandas_lineas SET enviado = 1'
                . ' WHERE idcomanda = ' . $dbEnv->var2str($this->comanda->idcomanda)
                . ' AND enviado = 0');

            $tcambio = $importedado > 0 ? max(0, $importedado - $doc->total) : 0;
            $ticketUrl = $this->url() . '?ticket=' . $this->comanda->idcomanda
                . '&tcodcl=' . urlencode($cliente->codcliente)
                . '&tdado=' . $importedado
                . '&tcambio=' . $tcambio;
        }

        $this->toolBox()->i18nLog()->info('record-updated-correctly');
        $this->redirect($ticketUrl);
    }

    /**
     * Reabre una comanda en_proceso para modificarla:
     * recupera las líneas del PedidoCliente y las devuelve al carrito.
     */
    protected function actionReabrirComanda(): void
    {
        $idcomanda = (int)$this->request->request->get('idcomanda', 0);
        $comanda   = new RestComanda();
        if ($idcomanda <= 0 || false === $comanda->loadFromCode($idcomanda)) {
            $this->redirect($this->url());
            return;
        }

        $comanda->estado = RestComanda::ESTADO_ABIERTA;
        $comanda->save();

        $backUrl = $comanda->idmesa
            ? $this->url() . '?idmesa=' . $comanda->idmesa
            : $this->url() . '?idcomanda=' . $idcomanda;
        $this->redirect($backUrl);
    }

    /**
     * Carga las líneas actuales de la comanda.
     */
    protected function loadLineas(): void
    {
        $linea = new RestComandaLinea();
        $this->lineas = $linea->all(
            [new DataBaseWhere('idcomanda', $this->comanda->idcomanda)],
            ['idlinea' => 'asc'],
            0,
            0
        );
    }

    /**
     * Carga el catálogo de productos y familias.
     */
    protected function loadCatalogo(): void
    {
        $this->codfamiliaActiva = $this->request->get('familia', '');
        $this->idestacionActiva = $this->request->get('estacion') ? (int)$this->request->get('estacion') : null;

        // Estaciones (Cocina, Bar, etc.)
        $estacionModel = new RestEstacion();
        $this->estaciones = $estacionModel->all([], ['nombre' => 'asc'], 0, 0);

        // Familias raíz
        $familia = new Familia();
        $where = empty($this->codfamiliaActiva)
            ? [new DataBaseWhere('madre', null, 'IS'), new DataBaseWhere('madre', '', '=', 'OR')]
            : [new DataBaseWhere('madre', $this->codfamiliaActiva)];
        $this->familias = $familia->all($where, ['descripcion' => 'asc'], 0, 0);

        // Productos de la familia activa (o todos si no hay familia)
        $db = new DataBase();
        $sql = 'SELECT v.referencia, p.descripcion, COALESCE(v.pricewithtaxes, v.precio) AS precio, p.codfamilia,'
            . ' (SELECT af.path FROM productos_imagenes pi'
            . '  LEFT JOIN attached_files af ON af.idfile = pi.idfile'
            . '  WHERE (pi.referencia = v.referencia OR pi.idproducto = p.idproducto)'
            . '  AND af.mimetype IN (\'image/jpeg\',\'image/png\',\'image/gif\',\'image/webp\')'
            . '  ORDER BY pi.orden ASC, pi.id ASC LIMIT 1) AS imagen_path'
            . ' FROM variantes v'
            . ' LEFT JOIN productos p ON v.idproducto = p.idproducto'
            . ' WHERE p.sevende = true AND p.bloqueado = false'
            . ' AND (p.nostock = true OR p.ventasinstock = true OR COALESCE((SELECT SUM(s.cantidad) FROM stocks s WHERE s.referencia = v.referencia), 0) > 0)';

        if (!empty($this->idestacionActiva)) {
            // Filtrar por las familias vinculadas a la estación seleccionada
            $famsByEst = $db->select(
                'SELECT codfamilia FROM rest_estacion_familias WHERE idestacion = '
                . $db->var2str($this->idestacionActiva)
            );
            $famCodes = array_column($famsByEst, 'codfamilia');
            if (!empty($famCodes)) {
                $inList = implode(',', array_map(fn($f) => $db->var2str($f), $famCodes));
                $sql .= ' AND p.codfamilia IN (' . $inList . ')';
            } else {
                $sql .= ' AND 1=0';
            }
        } elseif (!empty($this->codfamiliaActiva)) {
            $sql .= ' AND p.codfamilia = ' . $db->var2str($this->codfamiliaActiva);
        }

        $sql .= ' ORDER BY p.descripcion ASC';
        $this->productos = $db->select($sql);

        // Generar URL con token para cada imagen y cargar modificadores
        foreach ($this->productos as &$prod) {
            $prod['imagen_url'] = !empty($prod['imagen_path'])
                ? MyFilesToken::getUrl($prod['imagen_path'], false)
                : '';
            $prod['modificadores'] = [];
        }
        unset($prod);

        // Cargar modificadores asignados a los productos cargados
        if (!empty($this->productos)) {
            $refs = array_column($this->productos, 'referencia');
            $refList = implode(',', array_map(fn($r) => $db->var2str($r), $refs));
            $sqlMod = 'SELECT pm.referencia, m.idmodificador, m.nombre, m.precio'
                . ' FROM rest_prod_modificadores pm'
                . ' INNER JOIN rest_modificadores m ON m.idmodificador = pm.idmodificador'
                . ' WHERE pm.referencia IN (' . $refList . ')'
                . ' ORDER BY m.nombre ASC';
            $modRows = $db->select($sqlMod);
            $modByRef = [];
            foreach ($modRows as $row) {
                $modByRef[$row['referencia']][] = [
                    'id'     => (int)$row['idmodificador'],
                    'nombre' => $row['nombre'],
                    'precio' => (float)$row['precio'],
                ];
            }
            foreach ($this->productos as &$prod) {
                $prod['modificadores'] = $modByRef[$prod['referencia']] ?? [];
            }
            unset($prod);
        }
    }

    protected function cafeteriaCode(): string
    {
        return 'Cafeter';
    }

    protected function getCafeteriaFamilyCodes(): array
    {
        $db = new DataBase();
        $codes = [];
        $visited = [];
        $queue = [$this->cafeteriaCode()];

        while (!empty($queue)) {
            $parent = array_shift($queue);
            if (isset($visited[$parent])) {
                continue;
            }

            $visited[$parent] = true;
            $codes[$parent] = true;

            $rows = $db->select('SELECT codfamilia FROM familias WHERE madre = ' . $db->var2str($parent));
            foreach ($rows as $row) {
                $child = $row['codfamilia'] ?? '';
                if ($child !== '' && false === isset($visited[$child])) {
                    $queue[] = $child;
                }
            }
        }

        return array_keys($codes);
    }

    /**
     * Carga usuarios disponibles para el selector de camarero.
     * Si el usuario actual es admin, carga todos; si no, solo el propio.
     */
    protected function loadUsuarios(): void
    {
        if ($this->user->admin) {
            $userModel = new User();
            $this->usuarios = $userModel->all(
                [new DataBaseWhere('enabled', true)],
                ['nick' => 'asc'],
                0, 0
            );
        } else {
            $this->usuarios = [$this->user];
        }
    }

    /**
     * Carga la lista de clientes activos para el modal de cobro.
     * Límite de 300 para no saturar el datalist en sitios grandes.
     */
    protected function loadClientes(): void
    {
        $db = new DataBase();
        $sql = 'SELECT cl.codcliente, cl.nombre, cl.cifnif, cl.email, cl.telefono1,'
            . ' COALESCE(ct.direccion,\'\') AS direccion,'
            . ' COALESCE(ct.codpostal,\'\') AS codpostal,'
            . ' COALESCE(ct.ciudad,\'\') AS ciudad'
            . ' FROM clientes cl'
            . ' LEFT JOIN contactos ct ON ct.idcontacto = cl.idcontactofact'
            . ' WHERE cl.debaja = false'
            . ' ORDER BY cl.nombre ASC'
            . ' LIMIT 300';
        $this->clientes = $db->select($sql);

        $this->codClienteDefault       = Tools::settings('restaurantetpv', 'codcliente', '');

        // Sonidos: FacturaScripts guarda checkboxes como cadena "true"/"false",
        // por eso usamos filter_var para convertir correctamente.
        $sonidosRaw = Tools::settings('restaurantetpv', 'sonidos_activos', null);
        $this->settingSonidos = ($sonidosRaw === null)
            ? true
            : (bool)filter_var($sonidosRaw, FILTER_VALIDATE_BOOLEAN);

        $this->settingServicioDefecto = Tools::settings('restaurantetpv', 'servicio_defecto', 'in-table') ?: 'in-table';
        $this->settingVistaMesas      = Tools::settings('restaurantetpv', 'vista_mesas', 'clasico') ?: 'clasico';
        $this->settingAudioBeep1      = Tools::settings('restaurantetpv', 'audio_beep1', 'beep.wav') ?: 'beep.wav';
        $this->settingAudioBeep2      = Tools::settings('restaurantetpv', 'audio_beep2', 'beep2.wav') ?: 'beep2.wav';
        $this->settingEscposIp        = (string)(Tools::settings('restaurantetpv', 'escpos_ip_ticket', '') ?: '');
        $this->settingEscposPort      = (int)(Tools::settings('restaurantetpv', 'escpos_port_ticket', 9100) ?: 9100);
        $this->settingEscposTipo      = (string)(Tools::settings('restaurantetpv', 'escpos_tipo_ticket', 'tcp') ?: 'tcp');
        $this->settingEscposUsb       = (string)(Tools::settings('restaurantetpv', 'escpos_usb_ticket', '') ?: '');
        $this->settingEscposRelayUrl  = (string)(Tools::settings('restaurantetpv', 'escpos_relay_url', 'http://localhost:9091') ?: 'http://localhost:9091');
    }

    protected function actionMarkKotSent(): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        $idcomanda = (int)$this->request->query->get('idcomanda', 0);
        if ($idcomanda <= 0) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'idcomanda inválido']));
            return;
        }
        $db = new DataBase();
        $db->exec('UPDATE rest_comandas_lineas SET enviado = 1'
            . ' WHERE idcomanda = ' . $db->var2str($idcomanda)
            . ' AND enviado = 0');
        $this->response->setContent(json_encode(['ok' => true]));
    }

    /**
     * Genera buffers ESC/POS KOT por estación a partir de las líneas no enviadas de la comanda.
     * Devuelve JSON: { ok: true, kots: [ {ip, port, buffer, estacion}, ... ] }
     */
    protected function actionPrintKot(): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');

        $idcomanda = (int)$this->request->query->get('idcomanda', 0);
        if ($idcomanda <= 0) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'idcomanda inválido']));
            return;
        }

        $db = new DataBase();

        // Líneas pendientes de enviar agrupadas por familia de producto
        $sql = 'SELECT cl.idlinea, cl.idlinea_padre, cl.descripcion, cl.cantidad, cl.observaciones, p.codfamilia'
            . ' FROM rest_comandas_lineas cl'
            . ' LEFT JOIN productos p ON p.referencia = cl.referencia'
            . ' WHERE cl.idcomanda = ' . $db->var2str($idcomanda)
            . ' AND cl.estado != \'cancelado\''
            . ' ORDER BY cl.idlinea ASC';
        $rows = $db->select($sql);

        if (empty($rows)) {
            file_put_contents('kot_debug.json', json_encode(['step'=>'rows_vacio','idcomanda'=>$idcomanda], JSON_PRETTY_PRINT));
            $this->response->setContent(json_encode(['ok' => true, 'kots' => [], 'debug' => 'rows_vacio_idcomanda_' . $idcomanda]));
            return;
        }

        // Mapa codfamilia → idestacion
        $famMap = [];
        $sqlFam = 'SELECT codfamilia, idestacion FROM rest_estacion_familias';
        foreach ($db->select($sqlFam) as $r) {
            $famMap[$r['codfamilia']] = (int)$r['idestacion'];
        }

        file_put_contents('kot_debug.json', json_encode(['step'=>'tras_famMap','idcomanda'=>$idcomanda,'rows'=>$rows,'famMap'=>$famMap], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Mapa idestacion → RestEstacion
        $estMap = [];
        $estacion = new RestEstacion();
        foreach ($estacion->all([], [], 0, 0) as $est) {
            $estMap[$est->idestacion] = $est;
        }

        // Agrupar líneas por idestacion
        // Primero separar padres e hijos
        $childrenMap = []; // idlinea_padre → [hijos]
        $parentRows  = [];
        foreach ($rows as $row) {
            if (!empty($row['idlinea_padre'])) {
                $childrenMap[$row['idlinea_padre']][] = $row;
            } else {
                $parentRows[] = $row;
            }
        }

        $grupos     = []; // idestacion → [lineas]
        $sinEstacion = [];
        foreach ($parentRows as $row) {
            $codfam = $row['codfamilia'] ?? null;
            $idest  = $codfam ? ($famMap[$codfam] ?? null) : null;
            $mods = [];
            foreach ($childrenMap[$row['idlinea']] ?? [] as $child) {
                $mods[] = [
                    'desc' => $child['descripcion'],
                    'qty'  => (float)$child['cantidad'],
                ];
            }
            $linea = [
                'descripcion'   => $row['descripcion'],
                'cantidad'      => (float)$row['cantidad'],
                'observaciones' => $row['observaciones'] ?? '',
                'mods'          => $mods,
            ];
            if ($idest) {
                $grupos[$idest][] = $linea;
            } else {
                $sinEstacion[] = $linea;
            }
        }

        file_put_contents('kot_debug.json', json_encode(['step'=>'tras_grupos','grupos_keys'=>array_keys($grupos),'sinEstacion'=>$sinEstacion,'estMap'=>array_keys($estMap)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Recuperar nombre de mesa, doc y fecha de la comanda
        $comanda = new RestComanda();
        $mesaNombre = '';
        $docCodigo  = '';
        $fechaKot   = date('d-m-Y H:i');
        if ($comanda->loadFromCode($idcomanda)) {
            if ($comanda->idmesa) {
                $mesa = new RestMesa();
                if ($mesa->loadFromCode($comanda->idmesa)) {
                    $mesaNombre = $mesa->nombre;
                }
            } elseif ($comanda->tipo !== RestComanda::TIPO_MESA) {
                $mesaNombre = $comanda->tipo === RestComanda::TIPO_PARA_LLEVAR ? 'Para llevar' : 'Delivery';
            }
            // Número de documento si hay pedido/factura generado
            if (!empty($comanda->idpedido)) {
                $ped = new \FacturaScripts\Dinamic\Model\PedidoCliente();
                if ($ped->loadFromCode($comanda->idpedido)) {
                    $docCodigo = $ped->codigo;
                    $fechaKot  = date('d-m-Y H:i', strtotime($ped->fecha . ' ' . ($ped->hora ?? '00:00:00')));
                }
            } elseif (!empty($comanda->idfactura)) {
                $fac = new FacturaCliente();
                if ($fac->loadFromCode($comanda->idfactura)) {
                    $docCodigo = $fac->codigo;
                    $fechaKot  = date('d-m-Y H:i', strtotime($fac->fecha . ' ' . ($fac->hora ?? '00:00:00')));
                }
            }
        }

        // Generar buffers por estación
        $kots = [];
        foreach ($grupos as $idest => $lineas) {
            $est = $estMap[$idest] ?? null;
            if (!$est) {
                continue;
            }
            $tipo = ($est->escpos_tipo === 'usb') ? 'usb' : 'tcp';
            if ($tipo === 'tcp' && empty($est->escpos_ip)) {
                continue;
            }
            if ($tipo === 'usb' && empty($est->escpos_usb)) {
                continue;
            }
            $ticket = TicketESCPos::fromKOT(
                $est->nombre,
                $lineas,
                $mesaNombre,
                $idcomanda,
                $docCodigo,
                $fechaKot
            );
            $kot = [
                'tipo'     => $tipo,
                'buffer'   => base64_encode($ticket->getBuffer()),
                'estacion' => $est->nombre,
            ];
            if ($tipo === 'tcp') {
                $kot['ip']   = $est->escpos_ip;
                $kot['port'] = (int)($est->escpos_port ?: 9100);
            } else {
                $kot['usb'] = $est->escpos_usb;
            }
            $kot['relayUrl'] = (string)($est->escpos_relay_url ?? '');
            $kots[] = $kot;
        }

        $debug = [
            'idcomanda' => $idcomanda,
            'rows' => $rows,
            'famMap' => $famMap,
            'grupos' => $grupos,
            'sinEstacion' => $sinEstacion,
            'estMap' => array_map(function($e){ return ['id'=>$e->idestacion,'nombre'=>$e->nombre,'tipo'=>$e->escpos_tipo,'ip'=>$e->escpos_ip,'port'=>$e->escpos_port,'usb'=>$e->escpos_usb]; }, $estMap),
            'kots' => $kots,
        ];
        file_put_contents('kot_debug.json', json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->response->setContent(json_encode(['ok' => true, 'kots' => $kots]));
    }

    /**
     * Imprime un ticket de factura directamente en la impresora ESC/POS por TCP.
     */
    protected function getLogoPath(Empresa $empresa): string
    {
        if (empty($empresa->idlogo)) {
            return '';
        }
        $file = new \FacturaScripts\Dinamic\Model\AttachedFile();
        if ($file->loadFromCode($empresa->idlogo)) {
            $path = $file->getFullPath();
            if (file_exists($path)) {
                return $path;
            }
        }
        return '';
    }

    protected function actionPrintEscpos(): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');

        $logFile = 'escpos_debug.json';
        $log = ['ts' => date('Y-m-d H:i:s')];

        $ip   = (string)(Tools::settings('restaurantetpv', 'escpos_ip_ticket', '') ?: '');
        $port = (int)(Tools::settings('restaurantetpv', 'escpos_port_ticket', 9100) ?: 9100);
        $tipo = (string)(Tools::settings('restaurantetpv', 'escpos_tipo_ticket', 'tcp') ?: 'tcp');
        $usb  = (string)(Tools::settings('restaurantetpv', 'escpos_usb_ticket', '') ?: '');
        $log['ip']   = $ip;
        $log['port'] = $port;
        $log['tipo'] = $tipo;
        $log['usb']  = $usb;
        $log['php_server_ip'] = gethostbyname((string)gethostname());
        $log['php_hostname']  = gethostname();
        $log['fsockopen_available'] = function_exists('fsockopen');

        if ($tipo === 'usb' && empty($usb)) {
            $log['result'] = 'error';
            $log['error']  = 'ESC/POS USB no configurado';
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
            $this->response->setContent(json_encode(['ok' => false, 'error' => $log['error']]));
            return;
        }
        if ($tipo !== 'usb' && empty($ip)) {
            $log['result'] = 'error';
            $log['error']  = 'ESC/POS no configurado';
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
            $this->response->setContent(json_encode(['ok' => false, 'error' => $log['error']]));
            return;
        }

        try {
            $idfactura = (int)$this->request->query->get('idfactura', 0);
            $log['idfactura'] = $idfactura;
            if ($idfactura <= 0) {
                $log['result'] = 'error';
                $log['error']  = 'idfactura inválido';
                file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
                $this->response->setContent(json_encode(['ok' => false, 'error' => $log['error']]));
                return;
            }

            $doc = new FacturaCliente();
            if (!$doc->loadFromCode($idfactura)) {
                $log['result'] = 'error';
                $log['error']  = 'Factura no encontrada';
                file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
                $this->response->setContent(json_encode(['ok' => false, 'error' => $log['error']]));
                return;
            }
            $log['factura'] = $doc->codigo;

            $empresa = new Empresa();
            $empresa->loadFromCode($doc->idempresa);

            $formaPago = new FormaPago();
            $formaPago->loadFromCode($doc->codpago);

            // Datos de comanda/mesa
            $camarero   = $this->user->nick;
            $mesaNombre = '';
            $idComanda  = '';
            $efectivo   = 0.0;
            $cambio     = 0.0;

            $comanda = new RestComanda();
            $whereC  = [new DataBaseWhere('idfactura', $doc->idfactura)];
            if ($comanda->loadFromCode('', $whereC)) {
                if (!empty($comanda->codcamarero)) {
                    $camarero = $comanda->codcamarero;
                }
                $idComanda = (string)$comanda->idcomanda;
                if ($comanda->idmesa) {
                    $mesa = new RestMesa();
                    if ($mesa->loadFromCode($comanda->idmesa)) {
                        $mesaNombre = $mesa->nombre;
                    }
                }
                $efectivo = (float)($comanda->tpv_efectivo ?? 0.0);
                $cambio   = (float)($comanda->tpv_cambio   ?? 0.0);
            }
            // Fallback: leer de la factura si no está en la comanda
            if ($efectivo <= 0.0) {
                $efectivo = (float)($doc->tpv_efectivo ?? 0.0);
                $cambio   = (float)($doc->tpv_cambio   ?? 0.0);
            }

            $ticket = TicketESCPos::fromFactura(
                $doc, $empresa, $formaPago,
                $camarero, $mesaNombre, $idComanda,
                $efectivo, $cambio,
                $this->getLogoPath($empresa)
            );

            $buffer = $ticket->getBuffer();
            $log['buffer_bytes'] = strlen($buffer);
            $log['result'] = 'buffer_ok';
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
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
        } catch (\Throwable $e) {
            $log['result'] = 'exception';
            $log['error']  = $e->getMessage();
            $log['trace']  = $e->getTraceAsString();
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
            $this->response->setContent(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * Devuelve HTML de una factura S para el modal preview o para imprimir.
     */
    protected function actionGetFacturaHtml(): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        try {
            $idfactura = (int)$this->request->query->get('idfactura', 0);
            $mode      = $this->request->query->get('mode', 'preview');

            $doc = new FacturaCliente();
            if (!$doc->loadFromCode($idfactura)) {
                $this->response->setContent(json_encode(['error' => 'Factura no encontrada']));
                return;
            }

            $empresa = new Empresa();
            $empresa->loadFromCode($doc->idempresa);

            $formaPago = new FormaPago();
            $formaPago->loadFromCode($doc->codpago);

            $html = ($mode === 'pdf')
                ? $this->buildFacturaPdfHtml($doc, $empresa, $formaPago)
                : $this->buildFacturaPreviewHtml($doc, $empresa, $formaPago);

            $this->response->setContent(json_encode(['html' => $html, 'docCodigo' => $doc->codigo]));
        } catch (\Throwable $e) {
            $this->response->setContent(json_encode(['error' => $e->getMessage()]));
        }
    }

    /** HTML estilo modal — igual que el ticket JS */
    protected function buildFacturaPreviewHtml(FacturaCliente $doc, Empresa $empresa, FormaPago $formaPago): string
    {
        return $this->buildTicketHtml($doc, $empresa, $formaPago, false);
    }

    /** HTML estilo PDF/impresión — idéntico al ticket JS */
    protected function buildFacturaPdfHtml(FacturaCliente $doc, Empresa $empresa, FormaPago $formaPago): string
    {
        return $this->buildTicketHtml($doc, $empresa, $formaPago, true);
    }

    /** Genera HTML idéntico al ticket JS (factura rápida / facturar) */
    protected function buildTicketHtml(FacturaCliente $doc, Empresa $empresa, FormaPago $formaPago, bool $includeBarcode): string
    {
        // Camarero e info de comanda
        $camarero  = $this->user->nick;
        $idComanda = '';
        $mesaNombre = '';
        $comanda = new RestComanda();
        $whereC = [new DataBaseWhere('idfactura', $doc->idfactura)];
        if ($comanda->loadFromCode('', $whereC)) {
            if (!empty($comanda->codcamarero)) {
                $camarero = $comanda->codcamarero;
            }
            $idComanda = $comanda->idcomanda;
            if ($comanda->idmesa) {
                $mesa = new RestMesa();
                if ($mesa->loadFromCode($comanda->idmesa)) {
                    $mesaNombre = $mesa->nombre;
                }
            }
        }

        $esc   = fn($s) => htmlspecialchars((string)($s ?? ''));
        $fecha = date('d-m-Y', strtotime((string)$doc->fecha));
        $hora  = $doc->hora ? date('H:i:s', strtotime((string)$doc->hora)) : '';

        $html = '<div id="receipt-data">'
            . '<div style="text-align:center;">'
            . '<h3 style="text-transform:uppercase;">' . $esc($camarero) . '</h3>'
            . '<p>' . $esc($empresa->nombrecorto) . '<br/>'
            . $esc($empresa->direccion) . '<br/>'
            . 'Tel: ' . $esc($empresa->telefono1) . '<br/>'
            . 'NIF/CIF: ' . $esc($empresa->cifnif) . '</p>'
            . '</div>'
            . '<div style="text-align:center;">'
            . '<h4 style="font-weight:bold;">FACTURA SIMPLIFICADA</h4>'
            . '<p style="margin:2px 0;">N&ordm; ' . $esc($doc->codigo) . '</p>'
            . '</div>'
            . '<div class="sec-doc" style="text-align:left;padding-right:5px;padding-left:10px;">'
            . '<p>Fecha: ' . $fecha . ' ' . $hora . '<br/>'
            . 'Comanda: <strong>#' . $esc($idComanda) . '</strong>';
        if (!empty($mesaNombre)) {
            $html .= '<br/>Mesa: ' . $esc($mesaNombre);
        }
        if (!empty($doc->nombrecliente)) {
            $html .= '<br/>Cliente: ' . $esc($doc->nombrecliente);
        }
        $html .= '</p></div>'
            . '<table class="sec-doc" style="margin-right:15px;margin-left:5px;width:93.5%;">'
            . '<thead class="sec-doc" style="background-color:#f2f2f2;color:#000;"><tr>'
            . '<th style="padding-top:3px;padding-bottom:3px;text-align:left;">Descripci&oacute;n</th>'
            . '<th style="padding-top:3px;padding-bottom:3px;text-align:center;">Ud.</th>'
            . '<th style="padding-top:3px;padding-bottom:3px;text-align:right;">P/u</th>'
            . '<th style="padding-top:3px;padding-bottom:3px;text-align:right;">Total</th>'
            . '</tr></thead><tbody class="sec-doc">';

        $ivaGroups = [];
        foreach ($doc->getLines() as $item) {
            $qty      = $item->cantidad;
            $pvu      = $item->pvpunitario;
            $total    = round($item->cantidad * $item->pvpunitario, 2);
            $qtyStr   = ($qty == (int)$qty) ? (int)$qty : number_format($qty, 2, '.', ',');
            $html .= '<tr>'
                . '<td style="text-align:left;">' . $esc($item->descripcion) . '</td>'
                . '<td style="text-align:center;">' . $qtyStr . '</td>'
                . '<td style="text-align:right;">' . number_format($pvu, 2, '.', ',') . ' &euro;</td>'
                . '<td style="text-align:right;font-weight:bold;">' . number_format($total, 2, '.', ',') . ' &euro;</td>'
                . '</tr>';
            $ivaGroups[(float)$item->iva] = ($ivaGroups[(float)$item->iva] ?? 0) + round($item->pvptotal * $item->iva / 100, 2);
        }
        $html .= '</tbody></table>';

        // Tabla de totales
        $ivaRows = '';
        ksort($ivaGroups);
        foreach ($ivaGroups as $rate => $amount) {
            if ($amount <= 0) continue;
            $ivaRows .= '<tr><td style="text-align:right;font-weight:bold;">IVA (' . $rate . '%):</td>'
                . '<td style="text-align:right;">' . number_format($amount, 2, '.', ',') . ' &euro;</td></tr>';
        }
        $efectivo = (float)($doc->tpv_efectivo ?? 0);
        $cambio   = (float)($doc->tpv_cambio ?? 0);

        $html .= '<table class="sec-doc" style="margin-right:15px;margin-left:5px;width:93.5%;margin-top:6px;">'
            . '<tr><td style="text-align:right;font-weight:bold;">NETO:</td>'
            . '<td style="text-align:right;min-width:60px;">' . number_format($doc->neto, 2, '.', ',') . ' &euro;</td></tr>'
            . $ivaRows
            . '<tr><td style="text-align:right;font-weight:bold;border-top:1px solid #aaa;">TOTAL:</td>'
            . '<td style="text-align:right;font-weight:bold;border-top:1px solid #aaa;">' . number_format($doc->total, 2, '.', ',') . ' &euro;</td></tr>';
        if (!empty($formaPago->descripcion)) {
            $html .= '<tr><td style="text-align:right;font-style:italic;text-transform:uppercase;padding-top:3px;">' . $esc($formaPago->descripcion) . '</td><td></td></tr>';
        }
        if ($efectivo > 0) {
            $html .= '<tr><td style="text-align:right;font-weight:bold;">ENTREGADO:</td>'
                . '<td style="text-align:right;">' . number_format($efectivo, 2, '.', ',') . ' &euro;</td></tr>'
                . '<tr><td style="text-align:right;font-weight:bold;">CAMBIO:</td>'
                . '<td style="text-align:right;">' . number_format($cambio, 2, '.', ',') . ' &euro;</td></tr>';
        }
        $html .= '</table>';

        $html .= '<p class="sec-doc" style="text-align:center;margin-top:25px;margin-left:20px;margin-right:20px;"><span style="font-weight:bold;">Gracias por su visita.</span></p>';

        if ($includeBarcode) {
            $html .= '<div style="text-align:center;margin-top:12px;margin-bottom:8px;">'
                . '<svg id="ticket-barcode"></svg>'
                . '<div style="font-size:12px;font-weight:bold;color:#000;margin-top:4px;">' . $esc($doc->codigo) . '</div>'
                . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Recibe debug JSON desde el frontend y lo guarda en tpv_debug_js.json
     */
    protected function actionSaveDebug(): void
    {
        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        $body = $this->request->getContent();
        $data = json_decode($body, true) ?? ['raw' => $body];
        $data['server_time'] = date('Y-m-d H:i:s');
        $data['server_ip']   = $_SERVER['SERVER_ADDR'] ?? 'unknown';
        $data['remote_ip']   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $data['user']        = $this->user ? $this->user->nick : 'unknown';
        $logFile = FS_FOLDER . '/tpv_debug_js_' . date('Ymd_His') . '_' . uniqid() . '.json';
        file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->response->setContent(json_encode(['ok' => true]));
    }

    /**
     * Carga las últimas 100 facturas para el modal Cuenta.
     */
    protected function loadFacturasS(): void
    {
        $db = new DataBase();
        $rows = $db->select(
            "SELECT idfactura, codigo, fecha, total, nombrecliente"
            . " FROM facturascli"
            . " ORDER BY fecha DESC, idfactura DESC"
            . " LIMIT 100"
        );
        $this->facturasS = $rows ?: [];
    }

    protected function actionSearchFacturas(): void
    {
        $q      = trim($this->request->query->get('q', ''));
        $page   = max(1, (int)$this->request->query->get('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $db     = new DataBase();

        $where = '';
        if ($q !== '') {
            $qLike = $db->escapeString('%' . $q . '%');
            $where = " WHERE codigo LIKE '" . $qLike . "' OR nombrecliente LIKE '" . $qLike . "'";
        }

        $countRows = $db->select("SELECT COUNT(*) AS total FROM facturascli" . $where);
        $total     = (int)($countRows[0]['total'] ?? 0);

        $rows = $db->select(
            "SELECT idfactura, codigo, fecha, total, nombrecliente, tpv_efectivo, tpv_cambio"
            . " FROM facturascli"
            . $where
            . " ORDER BY fecha DESC, idfactura DESC"
            . " LIMIT " . $limit . " OFFSET " . $offset
        );

        header('Content-Type: application/json');
        echo json_encode([
            'rows'  => $rows ?: [],
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
        exit;
    }

    protected function loadFormasPago(): void
    {
        $db = new DataBase();
        $sessionKey = 'tpv_idempresa_' . $this->user->nick;
        $this->idempresaActual = (int)Session::get($sessionKey);
        if ($this->idempresaActual <= 0) {
            $this->idempresaActual = (int)$this->user->idempresa;
            Session::set($sessionKey, $this->idempresaActual);
        }
        $rows = $db->select(
            "SELECT codpago, descripcion FROM formaspago WHERE idempresa = {$this->idempresaActual} ORDER BY codpago ASC"
        );
        $this->formasPago = $rows ?: [];
    }

    protected function loadEmpresas(): void
    {
        $empresa = new Empresa();
        $this->empresas = $empresa->all([], ['nombre' => 'asc'], 0, 0);
    }

    /**
     * Carga todas las zonas con sus mesas para el modal de selección de mesa.
     */
    protected function loadZonasYMesas(): void
    {
        $zonaModel = new RestZona();
        $zonas = $zonaModel->all([], ['nombre' => 'asc'], 0, 0);
        $mesaModel = new RestMesa();
        foreach ($zonas as $zona) {
            $zona->mesas = $mesaModel->all(
                [new DataBaseWhere('idzona', $zona->idzona)],
                ['nombre' => 'asc'],
                0,
                0
            );
        }
        $this->zonas = $zonas;
    }

    /**
     * Carga datos de ticket si el parámetro ?ticket=X está presente en la URL.
     */
    protected function loadTicketIfNeeded(): void
    {
        $ticketId = (int)$this->request->get('ticket', 0);
        if ($ticketId <= 0) {
            return;
        }
        $tc = new RestComanda();
        if ($tc->loadFromCode($ticketId)) {
            $this->ticketComanda = $tc;
            $tl = new RestComandaLinea();
            $this->ticketLineas = $tl->all(
                [new DataBaseWhere('idcomanda', $ticketId)],
                ['idlinea' => 'asc'],
                0,
                0
            );

            // Agrupar líneas por estación
            $estaciones = (new RestEstacion())->all([], ['nombre' => 'asc'], 0, 0);
            if (!empty($estaciones)) {
                $db = new \FacturaScripts\Core\Base\DataBase();
                // Mapa idlinea_padre => [lineas hijo]
                $childrenMap = [];
                foreach ($this->ticketLineas as $linea) {
                    if (!empty($linea->idlinea_padre)) {
                        $childrenMap[$linea->idlinea_padre][] = $linea;
                    }
                }
                foreach ($estaciones as $est) {
                    $fams = $est->getFamilias();
                    if (empty($fams)) continue;
                    $lineasEst = [];
                    foreach ($this->ticketLineas as $linea) {
                        if (!empty($linea->idlinea_padre)) continue; // saltar hijos
                        if (empty($linea->referencia)) continue;
                        $sql = 'SELECT codfamilia FROM productos WHERE referencia = ' . $db->var2str($linea->referencia);
                        $rows = $db->select($sql);
                        $codFam = $rows[0]['codfamilia'] ?? null;
                        if ($codFam !== null && in_array($codFam, $fams)) {
                            $mods = [];
                            foreach ($childrenMap[$linea->idlinea] ?? [] as $child) {
                                $mods[] = ['desc' => $child->descripcion, 'qty' => (float)$child->cantidad];
                            }
                            $lineasEst[] = [
                                'desc' => $linea->descripcion,
                                'qty'  => (float)$linea->cantidad,
                                'obs'  => $linea->observaciones ?? '',
                                'mods' => $mods,
                            ];
                        }
                    }
                    if (!empty($lineasEst)) {
                        $this->ticketEstaciones[] = ['nombre' => $est->nombre, 'lineas' => $lineasEst];
                    }
                }
            }
            $codcl = $this->request->get('tcodcl', '');
            if (!empty($codcl)) {
                $cli = new Cliente();
                if ($cli->loadFromCode($codcl)) {
                    $this->ticketCliente = $cli;
                }
            }
            // Cargar datos financieros del documento generado
            if (!empty($tc->idfactura)) {
                $docT = new FacturaCliente();
                if ($docT->loadFromCode($tc->idfactura)) {
                    $this->ticketDocCodigo = $docT->codigo;
                    $serieT = new \FacturaScripts\Core\Model\Serie();
                    if ($serieT->loadFromCode($docT->codserie)) {
                        $this->ticketSerieDesc = $serieT->descripcion;
                    }
                    $this->ticketNeto  = (float)$docT->neto;
                    $this->ticketTotal = (float)$docT->total;
                    $ivaAcc = [];
                    foreach ($docT->getLines() as $ld) {
                        $r = (float)$ld->iva;
                        $ivaAcc[$r] = ($ivaAcc[$r] ?? 0.0) + round($ld->pvptotal * $r / 100, 2);
                    }
                    $this->ticketIvaGroups = $ivaAcc;
                    $fp = new FormaPago();
                    if ($fp->loadFromCode($docT->codpago)) {
                        $this->ticketFormaPago = (string)$fp->descripcion;
                    }
                }
            } elseif (!empty($tc->idpedido)) {
                $docT = new PedidoCliente();
                if ($docT->loadFromCode($tc->idpedido)) {
                    $this->ticketDocCodigo = $docT->codigo;
                    $serieT = new \FacturaScripts\Core\Model\Serie();
                    if ($serieT->loadFromCode($docT->codserie)) {
                        $this->ticketSerieDesc = $serieT->descripcion;
                    }
                    $this->ticketNeto  = (float)$docT->neto;
                    $this->ticketTotal = (float)$docT->total;
                    $ivaAcc = [];
                    foreach ($docT->getLines() as $ld) {
                        $r = (float)$ld->iva;
                        $ivaAcc[$r] = ($ivaAcc[$r] ?? 0.0) + round($ld->pvptotal * $r / 100, 2);
                    }
                    $this->ticketIvaGroups = $ivaAcc;
                    $fp = new FormaPago();
                    if ($fp->loadFromCode($docT->codpago)) {
                        $this->ticketFormaPago = (string)$fp->descripcion;
                    }
                }
            }
        }
        // Si se pasan múltiples idfactura (split/multi-persona), cargar cada una
        $facturasParam = $this->request->get('facturas', '');
        if (!empty($facturasParam)) {
            $facturaIdsArr = array_filter(array_map('intval', explode(',', $facturasParam)));
            foreach ($facturaIdsArr as $fid) {
                $docF = new FacturaCliente();
                if (!$docF->loadFromCode($fid)) {
                    continue;
                }
                $serieF = new \FacturaScripts\Core\Model\Serie();
                $serieDescF = '';
                if ($serieF->loadFromCode($docF->codserie)) {
                    $serieDescF = $serieF->descripcion;
                }
                $fpF = new FormaPago();
                $formaPagoF = '';
                if ($fpF->loadFromCode($docF->codpago)) {
                    $formaPagoF = (string)$fpF->descripcion;
                }
                $ivaAccF = [];
                $lineasF = [];
                foreach ($docF->getLines() as $ld) {
                    $r = (float)$ld->iva;
                    $ivaAccF[$r] = ($ivaAccF[$r] ?? 0.0) + round($ld->pvptotal * $r / 100, 2);
                    $lineasF[] = [
                        'desc'  => $ld->descripcion,
                        'qty'   => (float)$ld->cantidad,
                        'pvu'   => (float)$ld->pvpunitario,
                        'total' => (float)$ld->pvptotal,
                    ];
                }
                $this->ticketMulti[] = [
                    'idfactura' => (int)$docF->idfactura,
                    'docCodigo' => $docF->codigo,
                    'serieDesc' => $serieDescF,
                    'neto'      => (float)$docF->neto,
                    'total'     => (float)$docF->total,
                    'ivaGroups' => $ivaAccF,
                    'formaPago' => $formaPagoF,
                    'lineas'    => $lineasF,
                    'efectivo'  => (float)($docF->tpv_efectivo ?? 0),
                    'cambio'    => (float)($docF->tpv_cambio ?? 0),
                ];
            }
        }
        $this->ticketEmpresa = Tools::settings('default', 'empresa', '');
        $this->ticketEfectivo = (float)$this->request->get('tdado', 0);
        $this->ticketCambio   = (float)$this->request->get('tcambio', 0);
        $empresa = new Empresa();
        $idempresa = (int)Tools::settings('default', 'idempresa', 1);
        if ($empresa->loadFromCode($idempresa)) {
            $this->ticketEmpresaData = [
                'nombre'    => $empresa->nombrecorto,
                'direccion' => $empresa->direccion,
                'telefono'  => $empresa->telefono1,
                'cifnif'    => $empresa->cifnif,
            ];
        }
    }

    /**
     * Crea un cliente nuevo y devuelve su codcliente en JSON.
     */
    protected function actionCreateCliente(): void
    {
        $nombre = trim($this->request->request->get('nombre', ''));
        $result = ['ok' => false, 'error' => 'El nombre es obligatorio'];

        if (!empty($nombre)) {
            $cliente = new Cliente();
            $cliente->nombre    = $nombre;
            $cliente->cifnif    = trim($this->request->request->get('cifnif', ''));
            $cliente->email     = trim($this->request->request->get('email', ''));
            $cliente->telefono1 = trim($this->request->request->get('telefono1', ''));

            if ($cliente->save()) {
                // Guardar dirección en el contacto de facturación (creado automáticamente al guardar)
                $cliente->loadFromCode($cliente->codcliente);
                $this->saveClienteAddress($cliente);

                $result = [
                    'ok'         => true,
                    'codcliente' => $cliente->codcliente,
                    'nombre'     => $cliente->nombre,
                ];
            } else {
                $result = ['ok' => false, 'error' => 'Error al guardar el cliente'];
            }
        }

        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setContent(json_encode($result));
    }

    /**
     * Edita un cliente existente y devuelve JSON con el resultado.
     */
    protected function actionUpdateCliente(): void
    {
        $codcliente = trim($this->request->request->get('codcliente', ''));
        $result = ['ok' => false, 'error' => 'Código de cliente no válido'];

        if (!empty($codcliente)) {
            $cliente = new Cliente();
            if ($cliente->loadFromCode($codcliente)) {
                $nombre = trim($this->request->request->get('nombre', ''));
                if (empty($nombre)) {
                    $result = ['ok' => false, 'error' => 'El nombre es obligatorio'];
                } else {
                    $cliente->nombre    = $nombre;
                    $cliente->cifnif    = trim($this->request->request->get('cifnif', ''));
                    $cliente->email     = trim($this->request->request->get('email', ''));
                    $cliente->telefono1 = trim($this->request->request->get('telefono1', ''));
                    if ($cliente->save()) {
                        $this->saveClienteAddress($cliente);
                        $result = ['ok' => true, 'codcliente' => $cliente->codcliente, 'nombre' => $cliente->nombre];
                    } else {
                        $result = ['ok' => false, 'error' => 'Error al guardar el cliente'];
                    }
                }
            }
        }

        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setContent(json_encode($result));
    }

    /**
     * Guarda dirección en el contacto de facturación del cliente.
     */
    protected function saveClienteAddress(Cliente $cliente): void
    {
        $direccion = trim($this->request->request->get('direccion', ''));
        $codpostal = trim($this->request->request->get('codpostal', ''));
        $ciudad    = trim($this->request->request->get('ciudad', ''));
        if (empty($direccion) && empty($codpostal) && empty($ciudad)) {
            return;
        }
        if (empty($cliente->idcontactofact)) {
            return;
        }
        $contacto = new Contacto();
        if ($contacto->loadFromCode($cliente->idcontactofact)) {
            $contacto->direccion = $direccion;
            $contacto->codpostal = $codpostal;
            $contacto->ciudad    = $ciudad;
            $contacto->save();
        }
    }

    /**
     * Actualiza el tipo de la comanda (mesa / para-llevar / delivery).
     */
    protected function actionUpdateTipo(): void
    {
        $tipo = $this->request->request->get('tipo', RestComanda::TIPO_MESA);
        if (!in_array($tipo, [RestComanda::TIPO_MESA, RestComanda::TIPO_PARA_LLEVAR, RestComanda::TIPO_DELIVERY])) {
            $tipo = RestComanda::TIPO_MESA;
        }
        $this->comanda->tipo = $tipo;
        $this->comanda->save();
    }

    /**
     * Cancela la comanda activa (con o sin mesa) y libera la mesa si corresponde.
     * Redirige a la pantalla inicial del camarero.
     */
    protected function actionCancelOrder(int $idmesa): void
    {
        $db = new DataBase();
        // Comanda con mesa
        if ($idmesa > 0) {
            $comanda = new RestComanda();
            $where = [
                new DataBaseWhere('idmesa', $idmesa),
                new DataBaseWhere('estado', RestComanda::ESTADO_ABIERTA),
            ];
            if ($comanda->loadFromCode('', $where)) {
                if ($comanda->idpedido) {
                    // Estaba en modo modificar: descartar solo líneas nuevas (enviado=0)
                    $db->exec('DELETE FROM rest_comandas_lineas WHERE idcomanda = ' . $db->var2str($comanda->idcomanda)
                        . ' AND enviado = 0');
                    $comanda->estado = RestComanda::ESTADO_EN_PROCESO;
                } else {
                    // Si no tiene líneas, eliminar directamente en vez de guardar como cancelada
                    $mesa = new RestMesa();
                    if ($mesa->loadFromCode($idmesa)) {
                        $mesa->estado = RestMesa::ESTADO_LIBRE;
                        $mesa->save();
                    }
                    $cntRows = $db->select('SELECT COUNT(*) AS c FROM rest_comandas_lineas WHERE idcomanda = ' . $db->var2str($comanda->idcomanda));
                    if ((int)($cntRows[0]['c'] ?? 0) === 0) {
                        $comanda->delete();
                        $this->redirect($this->url());
                        return;
                    }
                    $comanda->estado = RestComanda::ESTADO_CANCELADA;
                }
                $comanda->save();
            } elseif (!$comanda->idpedido) {
                // Liberar la mesa si no hay comanda abierta
                $mesa = new RestMesa();
                if ($mesa->loadFromCode($idmesa)) {
                    $mesa->estado = RestMesa::ESTADO_LIBRE;
                    $mesa->save();
                }
            }
        } else {
            // Comanda sin mesa del usuario actual
            $comanda = new RestComanda();
            $where = [
                new DataBaseWhere('idmesa', null, 'IS'),
                new DataBaseWhere('codcamarero', $this->user->nick),
                new DataBaseWhere('estado', RestComanda::ESTADO_ABIERTA),
            ];
            if ($comanda->loadFromCode('', $where)) {
                if ($comanda->idpedido) {
                    // Estaba en modo modificar: descartar solo líneas nuevas (enviado=0)
                    $db->exec('DELETE FROM rest_comandas_lineas WHERE idcomanda = ' . $db->var2str($comanda->idcomanda)
                        . ' AND enviado = 0');
                    $comanda->estado = RestComanda::ESTADO_EN_PROCESO;
                } else {
                    // Si no tiene líneas, eliminar directamente
                    $cntRows2 = $db->select('SELECT COUNT(*) AS c FROM rest_comandas_lineas WHERE idcomanda = ' . $db->var2str($comanda->idcomanda));
                    if ((int)($cntRows2[0]['c'] ?? 0) === 0) {
                        $comanda->delete();
                        $this->redirect($this->url());
                        return;
                    }
                    $comanda->estado = RestComanda::ESTADO_CANCELADA;
                }
                $comanda->save();
            }
        }
        $this->redirect($this->url());
    }

    /**
     * Cancela comandas abiertas sin líneas que lleven más de 10 minutos creadas.
     * Se llama al cargar la página para evitar acumulación de comandas fantasma.
     */
    protected function cleanupStaleOrders(): void
    {
        $db = new DataBase();

        // Cancelar comandas abiertas sin productos con más de 10 minutos
        // Usamos LEFT JOIN para detectar comandas sin líneas (compatible con MariaDB)
        $sql = "UPDATE rest_comandas rc"
            . " LEFT JOIN rest_comandas_lineas rcl ON rcl.idcomanda = rc.idcomanda"
            . " SET rc.estado = 'cancelada'"
            . " WHERE rc.estado = 'abierta'"
            . " AND rc.total = 0"
            . " AND rc.idpedido IS NULL"
            . " AND rcl.idlinea IS NULL"
            . " AND TIMESTAMP(rc.fecha, rc.hora) < DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
        $db->exec($sql);

        // Liberar mesas cuya comanda fue cancelada (ya no tienen comanda abierta ni en proceso)
        $sqlMesas = "UPDATE rest_mesas rm"
            . " LEFT JOIN rest_comandas rc ON rc.idmesa = rm.idmesa AND rc.estado IN ('abierta', 'en-proceso')"
            . " SET rm.estado = 'libre'"
            . " WHERE rm.estado = 'ocupada'"
            . " AND rc.idcomanda IS NULL";
        $db->exec($sqlMesas);
    }

    /**
     * Cancela la comanda sin mesa abierta del usuario actual y vuelve a la pantalla inicial.
     * @deprecated Usar actionCancelOrder(0) en su lugar.
     */
    protected function actionCancelNoMesa(): void
    {
        $this->actionCancelOrder(0);
    }

    /**
     * Abre una mesa (cambia estado a ocupada) y crea su comanda si no existe.
     * Redirige a PanelCamarero con el idmesa para cargar la pantalla de trabajo.
     */
    protected function actionOpenMesa(int $idmesa): void
    {
        $mesa = new RestMesa();
        if (false === $mesa->loadFromCode($idmesa)) {
            $this->redirect($this->url());
            return;
        }

        $mesa->estado = RestMesa::ESTADO_OCUPADA;
        $mesa->save();

        $comanda = new RestComanda();
        $where = [
            new DataBaseWhere('idmesa', $idmesa),
            new DataBaseWhere('estado', RestComanda::ESTADO_ABIERTA),
        ];
        if (false === $comanda->loadFromCode('', $where)) {
            $comanda->idmesa      = $idmesa;
            $comanda->codcamarero = $this->user->nick;
            $comanda->tipo        = RestComanda::TIPO_MESA;
            $comanda->save();
        } elseif ($comanda->tipo !== RestComanda::TIPO_MESA) {
            // Corregir tipo si la comanda existente no es de mesa
            $comanda->tipo = RestComanda::TIPO_MESA;
            $comanda->save();
        }

        $this->redirect($this->url() . '?idmesa=' . $idmesa);
    }

    /**
     * Recalcula el total de la comanda sumando todas sus líneas.
     */
    protected function recalcularTotal(): void
    {
        $db = new DataBase();
        $sql = 'SELECT COALESCE(SUM(cantidad * pvpunitario), 0) as total'
            . ' FROM rest_comandas_lineas'
            . ' WHERE idcomanda = ' . $db->var2str($this->comanda->idcomanda);
        $rows = $db->select($sql);
        $this->comanda->total = (float)($rows[0]['total'] ?? 0);
        $this->comanda->save();
    }

    /**
     * Carga las comandas abiertas con pedido asignado para la vista de órdenes.
     */
    protected function loadOrdenesAbiertas(): void
    {
        $db  = new DataBase();
        $sql = "SELECT c.idcomanda, c.codcamarero, c.fecha, c.hora, c.tipo, c.total,"
             . " c.observaciones, c.idmesa, c.idpedido, p.codigo AS codigo_pedido,"
             . " p.totaliva, p.total AS total_pedido,"
             . " m.nombre AS mesa_nombre,"
             . " cl.nombre AS cliente_nombre, cl.codcliente"
             . " FROM rest_comandas c"
             . " LEFT JOIN rest_mesas m ON m.idmesa = c.idmesa"
             . " LEFT JOIN pedidoscli p ON p.idpedido = c.idpedido"
             . " LEFT JOIN clientes cl ON cl.codcliente = p.codcliente"
             . " WHERE c.estado = 'en-proceso'"
             . " ORDER BY c.fecha DESC, c.hora DESC";
        $rows = $db->select($sql);
        $lineaModel = new RestComandaLinea();
        foreach ($rows as $row) {
            $row['lineas'] = $lineaModel->all(
                [new DataBaseWhere('idcomanda', (int)$row['idcomanda'])],
                ['idlinea' => 'asc'],
                0, 0
            );
            $this->ordenesAbiertas[] = $row;
        }
    }

    /**
     * Genera una FacturaCliente desde las líneas de la comanda y cierra ésta.
     */
    protected function actionFacturarPedido(): void
    {
        $dbg = [];
        $idcomanda = (int)$this->request->request->get('idcomanda', 0);
        $comanda   = new RestComanda();
        if ($idcomanda <= 0 || false === $comanda->loadFromCode($idcomanda)) {
            $this->toolBox()->i18nLog()->error('record-not-found');
            $this->redirect($this->url());
            return;
        }

        if (false === $this->isCashBoxOpen()) {
            $this->toolBox()->log()->warning('Debes abrir caja antes de facturar y cerrar pedido.');
            $this->redirect($this->url());
            return;
        }

        // Leer forma de pago e importe entregado del POST
        $codpagoPost = $this->request->request->get('codpago', '');
        $importedado = (float)str_replace(',', '.', $this->request->request->get('importedado', '0'));
        $dbg['input'] = ['idcomanda' => $idcomanda, 'codpago' => $codpagoPost, 'importedado' => $importedado];
        $dbg['comanda'] = ['estado' => $comanda->estado, 'idpedido' => $comanda->idpedido, 'idfactura' => $comanda->idfactura, 'total' => $comanda->total];

        // Si la comanda tiene un PedidoCliente vinculado, usamos el mecanismo
        // nativo de FacturaScripts: cambiar el estado a 25 ("Facturar") genera
        // automáticamente una FacturaCliente mediante BusinessDocumentGenerator.
        if ($comanda->idpedido) {
            $pedido = new PedidoCliente();
            if (false === $pedido->loadFromCode($comanda->idpedido)) {
                $this->toolBox()->i18nLog()->error('record-not-found');
                $this->redirect($this->url());
                return;
            }

            $dbg['pedido_antes'] = ['idpedido' => $pedido->primaryColumnValue(), 'idestado' => $pedido->idestado, 'editable' => $pedido->editable, 'codpago' => $pedido->codpago, 'total' => $pedido->total];

            // Actualizar forma de pago antes de facturar
            if (!empty($codpagoPost)) {
                $pedido->codpago = $codpagoPost;
            }

            $pedido->idestado = 25; // Facturar → genera FacturaCliente
            $saveOk = $pedido->save();
            $dbg['pedido_save_ok'] = $saveOk;
            if (false === $saveOk) {
                $dbg['error'] = 'pedido-save-failed';
                file_put_contents(__DIR__ . '/../debug_factura.json', json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->toolBox()->i18nLog()->error('record-save-error');
                $this->redirect($this->url());
                return;
            }

            // Recuperar la FacturaCliente recién generada vía DocTransformation
            $docTrans = new DocTransformation();
            $whereT = [
                new DataBaseWhere('model1', 'PedidoCliente'),
                new DataBaseWhere('iddoc1', $comanda->idpedido),
                new DataBaseWhere('model2', 'FacturaCliente'),
            ];
            $transRows = $docTrans->all($whereT, ['id' => 'DESC'], 0, 3);
            $dbg['doc_transformations'] = [];
            foreach ($transRows as $trans) {
                $dbg['doc_transformations'][] = ['iddoc1' => $trans->iddoc1, 'iddoc2' => $trans->iddoc2];
                if (!$comanda->idfactura) { $comanda->idfactura = $trans->iddoc2; }
            }
            $dbg['idfactura_encontrada'] = $comanda->idfactura;

            if (!empty($comanda->idfactura)) {
                $this->registerCashSaleByInvoiceId((int) $comanda->idfactura);
            }

            // Guardar efectivo y cambio en la factura si se indicó importe
            if ($importedado > 0 && $comanda->idfactura) {
                $tcambio = max(0, $importedado - $pedido->total);
                $dbFac = new DataBase();
                $dbFac->exec('UPDATE facturascli SET tpv_efectivo = ' . $dbFac->var2str($importedado)
                    . ', tpv_cambio = ' . $dbFac->var2str($tcambio)
                    . ' WHERE idfactura = ' . $dbFac->var2str($comanda->idfactura));
            }

            $this->marcarRecibosPagados((int)$comanda->idfactura);
            $comanda->estado = RestComanda::ESTADO_COBRADA;
            $comanda->save();

            if ($comanda->idmesa) {
                $mesa = new RestMesa();
                if ($mesa->loadFromCode($comanda->idmesa)) {
                    $mesa->estado = RestMesa::ESTADO_LIBRE;
                    $mesa->save();
                }
            }

            file_put_contents(__DIR__ . '/../debug_factura.json', json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->toolBox()->i18nLog()->info('record-updated-correctly');
            $ticketUrl = $this->url() . '?ticket=' . $idcomanda
                . ($comanda->idfactura ? '&facturas=' . $comanda->idfactura : '');
            $this->redirect($ticketUrl);
            return;
        }

        // Sin PedidoCliente vinculado: crear factura directamente desde las líneas de comanda
        $lineaModel = new RestComandaLinea();
        $lineas = $lineaModel->all(
            [new DataBaseWhere('idcomanda', $idcomanda)],
            ['idlinea' => 'asc'], 0, 0
        );

        $cliente = new Cliente();
        $codcl = Tools::settings('restaurantetpv', 'codcliente', '');
        if (empty($codcl) || false === $cliente->loadFromCode($codcl)) {
            $todos = $cliente->all([], ['idcliente' => 'asc'], 0, 1);
            if (!empty($todos)) {
                $cliente = $todos[0];
            }
        }
        if (empty($cliente->codcliente)) {
            $this->toolBox()->i18nLog()->error('no-customer-found');
            $this->redirect($this->url());
            return;
        }

        $doc = new FacturaCliente();
        $doc->setAuthor($this->user);
        $doc->setSubject($cliente);
        $doc->observaciones = 'Comanda #' . $idcomanda;
        if (false === $doc->save()) {
            $this->toolBox()->i18nLog()->error('record-save-error');
            $this->redirect($this->url());
            return;
        }

        foreach ($lineas as $linea) {
            if ($linea->estado === RestComandaLinea::ESTADO_CANCELADO) {
                continue;
            }
            if (!empty($linea->referencia)) {
                $newLine = $doc->getNewProductLine($linea->referencia);
            } else {
                $newLine = $doc->getNewLine();
                $newLine->descripcion = $linea->descripcion;
                $newLine->pvpunitario = $linea->pvpunitario;
            }
            $newLine->cantidad = $linea->cantidad;
            if (false === $newLine->save()) {
                $doc->delete();
                $this->toolBox()->i18nLog()->error('record-save-error');
                $this->redirect($this->url());
                return;
            }
        }

        $docLines = $doc->getLines();
        Calculator::calculate($doc, $docLines, true);
        $this->marcarRecibosPagados((int)$doc->idfactura);
        $this->registerCashSaleByInvoiceId((int) $doc->idfactura);

        $comanda->idfactura = $doc->idfactura;
        $comanda->estado    = RestComanda::ESTADO_COBRADA;
        $comanda->save();

        if ($comanda->idmesa) {
            $mesa = new RestMesa();
            if ($mesa->loadFromCode($comanda->idmesa)) {
                $mesa->estado = RestMesa::ESTADO_LIBRE;
                $mesa->save();
            }
        }

        $dbg['ruta'] = 'sin-pedido-directo';
        $dbg['idfactura_creada'] = $doc->idfactura;
        $dbg['total_factura'] = $doc->total;
        file_put_contents(__DIR__ . '/../debug_factura.json', json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->toolBox()->i18nLog()->info('record-updated-correctly');
        $ticketUrl = $this->url() . '?ticket=' . $idcomanda . '&facturas=' . $doc->idfactura;
        $this->redirect($ticketUrl);
    }

    /**
     * Marca como pagados todos los recibos de una FacturaCliente.
     */
    private function marcarRecibosPagados(int $idfactura): void
    {
        if ($idfactura <= 0) {
            return;
        }
        $reciboModel = new ReciboCliente();
        $where = [new DataBaseWhere('idfactura', $idfactura)];
        foreach ($reciboModel->all($where, [], 0, 0) as $recibo) {
            if (!$recibo->pagado) {
                $recibo->pagado = true;
                $recibo->fechapago = Tools::date();
                $recibo->save();
            }
        }
    }

    private function loadCashContext(): void
    {
        $this->cashSerie = (string) (Tools::settings('restaurantetpv', 'codserie', '') ?: Tools::settings('default', 'codserie', ''));
        $this->cashBox = null;

        if ($this->cashSerie === '') {
            return;
        }

        $box = new RestCaja();
        $where = [
            new DataBaseWhere('codserie', $this->cashSerie),
            new DataBaseWhere('fechafin', null),
        ];

        if ($box->loadFromCode('', $where)) {
            $this->cashBox = $box;
        }
    }

    private function isCashBoxOpen(): bool
    {
        return null !== $this->cashBox;
    }

    private function redirectToCurrentContext(): void
    {
        $idmesa = (int) $this->request->get('idmesa', 0);
        if ($idmesa > 0) {
            $this->redirect($this->url() . '?idmesa=' . $idmesa);
            return;
        }

        if ($this->request->get('vista', '') === 'ordenes') {
            $this->redirect($this->url() . '?vista=ordenes');
            return;
        }

        $this->redirect($this->url());
    }

    private function actionCashOpen(): void
    {
        if ($this->cashSerie === '') {
            $this->toolBox()->log()->warning('No hay serie configurada para abrir caja de RestauranteTPV.');
            return;
        }

        if ($this->isCashBoxOpen()) {
            $this->toolBox()->log()->warning('La caja ya está abierta.');
            return;
        }

        $starting = (float) str_replace(',', '.', (string) $this->request->request->get('cash-starting', '0'));
        if ($starting < 0) {
            $this->toolBox()->log()->warning('El importe inicial no puede ser negativo.');
            return;
        }

        $box = new RestCaja();
        $box->codserie = $this->cashSerie;
        $box->nick = $this->user->nick;
        $box->dineroini = $starting;

        if ($box->save()) {
            $this->cashBox = $box;
            $this->toolBox()->i18nLog()->notice('record-updated-correctly');
            return;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
    }

    private function actionCashClose(): void
    {
        if (false === $this->isCashBoxOpen()) {
            $this->toolBox()->log()->warning('No hay caja abierta para cerrar.');
            return;
        }

        $final = $this->getCashCloseAmount();
        if ($final < 0) {
            $this->toolBox()->log()->warning('El importe final no puede ser negativo.');
            return;
        }

        $this->cashBox->close($final);
        $this->cashBox->observaciones = (string) $this->request->request->get('cash-note', '');

        if ($this->cashBox->save()) {
            // Eliminar todas las comandas abiertas vacías al cerrar caja y liberar sus mesas
            $db = new DataBase();
            $comandasAbiertas = $db->select(
                "SELECT idcomanda, idmesa FROM rest_comandas WHERE estado = " . $db->var2str(RestComanda::ESTADO_ABIERTA)
            );
            foreach ($comandasAbiertas as $row) {
                $idcomanda = (int)$row['idcomanda'];
                $cntV = $db->select('SELECT COUNT(*) AS c FROM rest_comandas_lineas WHERE idcomanda = ' . $db->var2str($idcomanda));
                if ((int)($cntV[0]['c'] ?? 0) === 0) {
                    // Liberar la mesa si la comanda tenía una asignada
                    if (!empty($row['idmesa'])) {
                        $mesaVacia = new RestMesa();
                        if ($mesaVacia->loadFromCode((int)$row['idmesa'])) {
                            $mesaVacia->estado = RestMesa::ESTADO_LIBRE;
                            $mesaVacia->save();
                        }
                    }
                    $comandaObj = new RestComanda();
                    if ($comandaObj->loadFromCode($idcomanda)) {
                        $comandaObj->delete();
                    }
                }
            }
            $this->cashBox = null;
            $this->toolBox()->i18nLog()->notice('box-closed-ok');
            return;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
    }

    private function getCashCloseAmount(): float
    {
        $coinsTypes = $this->getCoinTypes();
        $data = $this->request->request->all();

        if (!empty($coinsTypes)) {
            $total = 0.0;
            foreach ($coinsTypes as $coin) {
                $key = str_replace('.', '_', $coin->name);
                $total += (float) $coin->name * (float) ($data[$key] ?? 0);
            }
            return $total;
        }

        return (float) str_replace(',', '.', (string) $this->request->request->get('cash-final', '0'));
    }

    private function actionCashMovement(string $type): void
    {
        if (false === $this->isCashBoxOpen()) {
            $this->toolBox()->log()->warning('No hay caja abierta para registrar movimientos.');
            return;
        }

        $amount = (float) str_replace(',', '.', (string) $this->request->request->get('cash-amount', '0'));
        $concept = trim((string) $this->request->request->get('cash-concept', ''));

        if ($amount <= 0) {
            $this->toolBox()->i18nLog()->warning('amount-is-required');
            return;
        }

        if ($concept === '') {
            $this->toolBox()->i18nLog()->warning('description-is-required');
            return;
        }

        if ($type === 'out' && $amount > $this->cashBox->getTotalInBox()) {
            $this->toolBox()->log()->warning('Saldo insuficiente en caja para registrar la salida.');
            return;
        }

        if ($this->cashBox->addMovement($type, $amount, $concept, $this->user->nick)) {
            $this->toolBox()->i18nLog()->notice('record-updated-correctly');
            return;
        }

        $this->toolBox()->i18nLog()->error('record-save-error');
    }

    private function registerCashSaleByInvoiceId(int $idfactura): void
    {
        if ($idfactura <= 0 || false === $this->isCashBoxOpen()) {
            return;
        }

        $entry = new RestCajaFactura();
        $where = [new DataBaseWhere('idfactura', $idfactura)];
        if ($entry->loadFromCode('', $where)) {
            return;
        }

        $invoice = new FacturaCliente();
        if (false === $invoice->loadFromCode($idfactura)) {
            return;
        }

        $this->cashBox->addSale($idfactura, (float) $invoice->total, $this->user->nick);
    }

    /**
     * Cancela la comanda y libera la mesa.
     */
    protected function actionCancelarPedido(): void
    {
        $idcomanda = (int)$this->request->request->get('idcomanda', 0);
        $comanda   = new RestComanda();
        if ($idcomanda <= 0 || false === $comanda->loadFromCode($idcomanda)) {
            $this->redirect($this->url());
            return;
        }

        $comanda->estado = RestComanda::ESTADO_CANCELADA;
        $comanda->save();

        // Cancelar el pedido vinculado (estado 6 = cancelado)
        if ($comanda->idpedido) {
            $pedido = new PedidoCliente();
            if ($pedido->loadFromCode($comanda->idpedido)) {
                $pedido->idestado = 6;
                $pedido->save();
            }
        }

        if ($comanda->idmesa) {
            $mesa = new RestMesa();
            if ($mesa->loadFromCode($comanda->idmesa)) {
                $mesa->estado = RestMesa::ESTADO_LIBRE;
                $mesa->save();
            }
        }

        $this->toolBox()->i18nLog()->info('record-updated-correctly');
        $this->redirect($this->url());

    }
}

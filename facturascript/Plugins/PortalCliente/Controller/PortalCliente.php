<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Controller\PortalLogin;
use FacturaScripts\Dinamic\Model\PortalFavorite;
use FacturaScripts\Dinamic\Model\PortalCart;
use FacturaScripts\Dinamic\Lib\PortalCatalogue;
use FacturaScripts\Dinamic\Lib\PortalPanelController;
use FacturaScripts\Dinamic\Model\Ciudad;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Dinamic\Model\Provincia;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocCommonTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocFilesTrait;
use FacturaScripts\Plugins\Traducciones\Lib\TranslateModel as LibTranslateModel;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalCliente extends PortalPanelController
{
    use PortalDocFilesTrait;
    use PortalDocCommonTrait;

    const ACCOUNT_TEMPLATE = 'PortalCliente';

    public array $addresses = [];
    public Cliente $customer;
    public array $languages = [];
    private array $logLevels = ['critical', 'error', 'info', 'notice', 'warning'];

    /** @var PedidoCliente */
    protected $docCart;

    public function getAddresses(): array
    {
        // si el contacto es un cliente, devolvemos sus direcciones
        if ($this->customer->exists()) {
            return $this->customer->getAddresses();
        }

        // si no es cliente, devolvemos solo la dirección del contacto
        return [$this->contact];
    }

    public function getCities(): array
    {
        $model = new Ciudad();
        return $model->all([], ['ciudad' => 'ASC'], 0, 0);
    }

    public function getFamilies(): array
    {
        $results = $this->dataBase->select('SELECT DISTINCT(f.codfamilia), f.descripcion'
            . ' FROM productos as p'
            . ' LEFT JOIN familias as f ON f.codfamilia = p.codfamilia'
            . ' WHERE p.publico = ' . $this->dataBase->var2str(1) . ' AND p.codfamilia IS NOT NULL'
            . ' ORDER BY f.descripcion ASC;');

        if (empty($results)) {
            return [];
        }

        $families = [];
        foreach ($results as $result) {
            if (Plugins::isEnabled('Traducciones')) {
                $families[$result['codfamilia']] = LibTranslateModel::get($this->contact->langcode, 'Familia', 'descripcion', $result['codfamilia']);
            } else {
                $families[$result['codfamilia']] = $result['descripcion'];
            }
        }

        return $families;
    }

    public function getLanguages(): array
    {
        $langs = [];
        foreach (Tools::lang()->getAvailableLanguages() as $key => $value) {
            $langs[] = ['value' => $key, 'title' => $value];
        }
        return $langs;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'client-portal';
        $data['icon'] = 'fas fa-chalkboard-user';
        return $data;
    }

    public function getProductPriceMinMax(): array
    {
        $results = $this->dataBase->select('SELECT MIN(v.precio) as min, MAX(v.precio) as max'
            . ' FROM variantes as v'
            . ' LEFT JOIN productos as p ON p.idproducto = v.idproducto'
            . ' WHERE p.publico = ' . $this->dataBase->var2str(1) . ';');

        return [
            'priceMin' => $results[0]['min'],
            'priceMinLabel' => Tools::number($results[0]['min']),
            'priceMax' => $results[0]['max'],
            'priceMaxLabel' => Tools::number($results[0]['max']),
        ];
    }

    public function getProvinces(): array
    {
        $model = new Provincia();
        return $model->all([], ['provincia' => 'ASC'], 0, 0);
    }

    protected function addProductToCart(): array
    {
        $variant = new Variante();
        if (false === $variant->loadFromCode($this->request->get('idvariante'))) {
            return ['addProductToCart' => false];
        }

        $lineCart = new PortalCart();

        // buscamos si ya tenemos una línea para esta variante y contacto
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idvariante', $variant->idvariante),
        ];

        // si no la encontramos, creamos una nueva
        if (false === $lineCart->loadFromCode('', $where)) {
            $lineCart->idcontacto = $this->contact->idcontacto;
            $lineCart->idvariante = $variant->idvariante;
            $lineCart->quantity = 1;
        } else {
            $lineCart->quantity++;
        }

        if (false === $lineCart->save()) {
            return ['addProductToCart' => false];
        }

        return [
            'addProductToCart' => true,
            'nameModal' => $this->request->get('nameModal')
        ];
    }

    protected function createViews(): void
    {
        if (false === $this->contact->exists()) {
            $this->redirect('PortalLogin');
            return;
        }

        $this->customer = $this->contact->getCustomer(false);
        $this->languages = $this->getLanguages();
        $this->addresses = $this->getAddresses();

        $this->createViewsAccount();
        $this->createViewsCatalogue();
        $this->createViewsAddresses();
        $this->createViewsBudgets();
        $this->createViewsOrders();
        $this->createViewsDeliveryNotes();
        $this->createViewsInvoices();
        $this->createViewDocFiles();
        $this->createViewTickets();
        $this->createViewNotes();
    }

    protected function createViewsAccount(string $viewName = 'PortalAccount'): void
    {
        $this->setTemplate(self::ACCOUNT_TEMPLATE);
        $this->title = Tools::lang()->trans('my-profile');
        $this->addHtmlView($viewName, 'Tab/PortalAccount', 'Contacto', 'details', 'fas fa-user-circle');
    }

    protected function createViewsCatalogue(string $viewName = 'PortalCatalogue'): void
    {
        $title = $this->contact->pc_allow_buy ? 'shop' : 'catalogue';
        $this->addHtmlView($viewName, 'Tab/PortalCatalogue', 'Producto', $title, 'fas fa-shop');
    }

    protected function createViewsAddresses(string $viewName = 'PortalAddresses'): void
    {
        $this->addHtmlView($viewName, 'Tab/PortalAddresses', 'Contacto', 'addresses', 'fas fa-address-book');
    }

    protected function createViewsBudgets(string $viewName = 'ListPortalPresupuesto'): void
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'far fa-file-powerpoint')
            ->addOrderBy(['finoferta'], 'expiration');

        $this->docCommonFilters($viewName);
        $this->disableButtons($viewName);
    }

    protected function createViewsDeliveryNotes(string $viewName = 'ListPortalAlbaran'): void
    {
        $this->addListView($viewName, 'AlbaranCliente', 'delivery-notes', 'fas fa-dolly-flatbed');
        $this->docCommonFilters($viewName);
        $this->disableButtons($viewName);
    }

    protected function createViewsInvoices(string $viewName = 'ListPortalFactura'): void
    {
        $this->addListView($viewName, 'FacturaCliente', 'invoices', 'fas fa-file-invoice-dollar')
            ->addSearchFields(['codigorect']);

        $this->docCommonFilters($viewName);
        $this->tab($viewName)->addFilterSelectWhere('status', [
            ['label' => Tools::lang()->trans('paid-or-unpaid'), 'where' => []],
            ['label' => Tools::lang()->trans('paid'), 'where' => [new DataBaseWhere('pagada', true)]],
            ['label' => Tools::lang()->trans('unpaid'), 'where' => [new DataBaseWhere('pagada', false)]],
            ['label' => Tools::lang()->trans('expired-receipt'), 'where' => [new DataBaseWhere('vencida', true)]],
        ]);

        $this->disableButtons($viewName);
    }

    protected function createViewNotes(string $viewName = 'ListPortalNote'): void
    {
        $this->addListView($viewName, 'PortalNote', 'notes', 'far fa-sticky-note')
            ->addOrderBy(['creation_date'], 'creation-date', 2)
            ->addOrderBy(['last_update'], 'last-update')
            ->addSearchFields(['title', 'body'])
            ->disableColumn('customer', true)
            ->disableColumn('contact', true)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false);
    }

    protected function createViewsOrders(string $viewName = 'ListPortalPedido'): void
    {
        $this->addListView($viewName, 'PedidoCliente', 'orders', 'fas fa-file-powerpoint');
        $this->docCommonFilters($viewName);
        $this->disableButtons($viewName);
    }

    protected function createViewTickets(string $viewName = 'ListPortalTicket'): void
    {
        $this->addListView($viewName, 'PortalTicket', 'tickets', 'far fa-comment-dots')
            ->addOrderBy(['creation_date'], 'date')
            ->addOrderBy(['last_update'], 'last-update')
            ->addOrderBy(['closed'], 'closed', 1)
            ->addSearchFields(['id', 'body'])
            ->disableColumn('contact', true);

        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function deleteAction(): bool
    {
        return true;
    }

    protected function disableButtons(string $viewName): void
    {
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function docCommonFilters(string $viewName): void
    {
        $currencies = Divisas::codeModel();

        $this->tab($viewName)
            ->addOrderBy(['codigo'], 'code')
            ->addOrderBy(['fecha', $this->tableColToNumber('numero')], 'date', 2)
            ->addOrderBy([$this->tableColToNumber('numero')], 'number')
            ->addOrderBy(['numero2'], 'number2')
            ->addOrderBy(['total'], 'total')
            ->addSearchFields(['cifnif', 'codigo', 'codigoenv', 'nombrecliente', 'numero2', 'observaciones'])
            ->addFilterPeriod('date', 'period', 'fecha')
            ->addFilterNumber('min-total', 'total', 'total', '>=')
            ->addFilterNumber('max-total', 'total', 'total', '<=');

        if (count($currencies) > 2) {
            $this->tab($viewName)->addFilterSelect('coddivisa', 'currency', 'coddivisa', $currencies);
        }
    }

    protected function editAction(): bool
    {
        return true;
    }

    protected function editAccountAction(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->contact->exists()
            || $this->contact->idcontacto !== (int)$this->request->request->get('idcontacto')) {
            return true;
        }

        $this->contact->langcode = $this->request->request->get('langcode');
        $this->contact->admitemarketing = (bool)$this->request->request->get('admitemarketing', 0);


        // establecemos el idioma del contacto
        Tools::lang()->setDefaultLang($this->contact->langcode);

        if (false === $this->contact->save()) {
            Tools::log()->warning('record-save-error');

            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function editAddressAction(): bool
    {
        $contact = new Contacto();
        if (false === $this->validateFormToken()
            || false === $this->contact->exists()
            || false === $contact->loadFromCode($this->request->request->get('idcontacto'))
            || false === $this->contact->pc_allow_show_addresses) {
            return true;
        }

        $found = false;

        // buscamos si la dirección que queremos editar pertenece al contacto
        foreach ($this->getAddresses() as $address) {
            if ($address->idcontacto === $contact->idcontacto) {
                $found = true;
                break;
            }
        }

        // si no lo hemos encontrado, terminamos
        if (false === $found) {
            return true;
        }

        $fields = [
            'nombre', 'apellidos', 'empresa', 'tipoidfiscal', 'cifnif', 'direccion', 'apartado',
            'email', 'codpostal', 'ciudad', 'provincia', 'codpais', 'langcode', 'telefono1', 'descripcion'
        ];

        foreach ($fields as $field) {
            if (property_exists($contact, $field)) {
                $contact->{$field} = $this->request->request->get($field);
            }
        }

        if (false === $contact->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        // actualizamos la lista de direcciones
        $this->addresses = $this->getAddresses();

        // si no hemos cambiado la dirección de facturación, terminamos
        $billing = (bool)$this->request->request->get('billing', 0);
        if (false === $billing) {
            return true;
        }

        // si hemos cambiado la dirección de facturación, la actualizamos
        $this->customer->idcontactofact = $contact->idcontacto;
        if (false === $this->customer->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        return true;
    }

    protected function editPasswordAction(): bool
    {
        if (PortalLogin::isIpBlocked(Session::getClientIp())) {
            Tools::log()->error('ip-banned');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->contact->exists()
            || $this->contact->idcontacto !== (int)$this->request->request->get('idcontacto')) {
            PortalLogin::blockIp(Session::getClientIp());
            return true;
        }


        $fields = ['new_password', 'repeat_password'];
        foreach ($fields as $field) {
            $this->contact->{$field} = $this->request->request->get($field);
        }

        if (false === $this->contact->save()) {
            Tools::log()->warning('record-save-error');

            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        if ((bool)$this->request->get('ajax', false)) {
            $this->setTemplate(false);

            switch ($action) {
                case 'addProductToCart':
                    $data = $this->addProductToCart();
                    break;

                case 'finalizePurchase':
                    $data = $this->finalizePurchase();
                    break;

                case 'getCart':
                    $data = $this->getCart();
                    break;

                case 'getCatalogue':
                    $data = $this->getCatalogue();
                    break;

                case 'markAsFavorite':
                    $data = $this->markAsFavorite();
                    break;

                case 'removeLineCart':
                    $data = $this->removeLineCart();
                    break;

                case 'unmarkAsFavorite':
                    $data = $this->unmarkAsFavorite();
                    break;

                case 'updateProductToCart':
                    $data = $this->updateProductToCart();
                    break;
            }

            $content = array_merge(
                ['messages' => Tools::log()->read('master', $this->logLevels)],
                $data ?? []
            );
            $this->response->setContent(json_encode($content));
            return false;
        }

        return match ($action) {
            'edit-account' => $this->editAccountAction(),
            'edit-password' => $this->editPasswordAction(),
            'edit-address' => $this->editAddressAction(),
            'new-address' => $this->newAddressAction(),
            'logout' => $this->logoutAction(),
            default => parent::execPreviousAction($action),
        };
    }

    protected function finalizePurchase(): array
    {
        // obtenemos el carrito del contacto
        $linesCart = $this->contact->getPCCart();

        // iniciamos una transacción
        $newTransaction = $this->dataBase->inTransaction();
        if (false === $newTransaction) {
            $newTransaction = true;
            $this->dataBase->beginTransaction();
        }

        // creamos un documento
        $className = Tools::settings('portalcliente', 'shop_document', 'PedidoCliente');
        $modelClass = 'FacturaScripts\\Dinamic\\Model\\' . $className;
        $document = new $modelClass();
        $document->setSubject($this->contact->getCustomer());
        $document->observaciones = $this->request->get('observations');
        $document->codalmacen = Tools::settings('portalcliente', 'shop_warehouse');
        $document->pc_created = true;

        if (Tools::settings('portalcliente', 'shop_serie')) {
            $document->codserie = Tools::settings('portalcliente', 'shop_serie');
        }

        if (false === $document->save()) {
            if ($newTransaction) {
                $this->dataBase->rollback();
            }
            return ['finalizePurchase' => false];
        }

        // añadimos las líneas del carrito al documento
        foreach ($linesCart as $lineCart) {
            // obtenemos la variante
            $variant = $lineCart->getVariant();

            // creamos una nueva línea del documento
            $lineDocument = $document->getNewProductLine($variant->referencia);
            $lineDocument->cantidad = $lineCart->quantity;
            if (false === $lineDocument->save()) {
                if ($newTransaction) {
                    $this->dataBase->rollback();
                }
                return ['finalizePurchase' => false];
            }

            // eliminamos la línea del carrito
            if (false === $lineCart->delete()) {
                if ($newTransaction) {
                    $this->dataBase->rollback();
                }
                return ['finalizePurchase' => false];
            }
        }

        // calculamos los totales del documento
        $lines = $document->getLines();
        if (false === Calculator::calculate($document, $lines, true)) {
            if ($newTransaction) {
                $this->dataBase->rollback();
            }
            return ['finalizePurchase' => false];
        }

        // finalizamos la transacción
        if ($newTransaction) {
            $this->dataBase->commit();
        }

        return [
            'finalizePurchase' => true,
            'redirect' => $document->url('public'),
        ];
    }

    protected function getCatalogue(): array
    {
        $currentPageFilter = (int)$this->request->get('currentPageFilter', 1);
        $searchProductFilter = $this->request->get('searchProductFilter', '');
        $familiesProductFilter = $this->request->get('familiesProductFilter', '');
        $priceMinProductFilter = $this->request->get('priceMinProductFilter', '');
        $priceMaxProductFilter = $this->request->get('priceMaxProductFilter', '');
        $favoriteProductFilter = $this->request->get('favoriteProductFilter', false);
        $orderProductFilter = $this->request->get('orderProductFilter', 'ref-asc');

        return array_merge(['getCatalogue' => true],
            $this->getProductPriceMinMax(),
            PortalCatalogue::render($this->contact, $currentPageFilter, $orderProductFilter, $searchProductFilter, $familiesProductFilter, $priceMinProductFilter, $priceMaxProductFilter, $favoriteProductFilter),
        );
    }

    protected function getCart(): array
    {
        $cartHtml = '';
        $documentLines = [];
        $className = Tools::settings('portalcliente', 'shop_document', 'PedidoCliente');
        $modelClass = 'FacturaScripts\\Dinamic\\Model\\' . $className;

        // creamos el documento
        $document = new $modelClass();
        $document->setSubject($this->contact->getCustomer());

        // recorremos el carrito del contacto para crear el documento
        $linesCart = $this->contact->getPCCart();
        foreach ($linesCart as $lineCart) {
            $variant = $lineCart->getVariant();
            if (false === $variant->exists()) {
                $lineCart->delete();
                continue;
            }

            $newDocumentLine = $document->getNewProductLine($variant->referencia);
            $newDocumentLine->cantidad = $lineCart->quantity;
            $documentLines[] = $newDocumentLine;
        }

        // calculamos totales del documento
        Calculator::calculate($document, $documentLines, false);

        // recorremos las líneas para renderizar el html
        foreach ($documentLines as $documentLine) {
            $lineCart = null;
            $variant = $documentLine->getVariante();
            $product = $variant->getProducto();
            $images = $product->getImages();

            // obtenemos la línea del carrito
            foreach ($linesCart as $line) {
                if ($line->idvariante === $variant->idvariante) {
                    $lineCart = $line;
                    break;
                }
            }

            if (empty($lineCart)) {
                continue;
            }

            $cartHtml .= '<tr>'
                . '<td class="align-middle">'
                . PortalCatalogue::getGalleryImage($images, $documentLine->referencia)
                . '</td>';

            if (Plugins::isEnabled('Traducciones')) {
                $attributes = $variant->descriptionTranslate($this->contact->langcode, true);
                $description = $variant->descriptionTranslate($this->contact->langcode, false);
            } else {
                $attributes = $variant->description(true);
                $description = $variant->description(false);
            }

            $cartHtml .= '<td class="align-middle">'
                . '<div class="font-weight-bold" title="' . $description . '">'
                . '<i class="fa-solid fa-trash text-danger mr-1 btn-spin-action pointer" title="' . Tools::lang()->trans('delete') . '" onclick="removeLineCart(' . $lineCart->id . ')"></i>'
                . '<i class="fa-solid fa-circle-info mr-1"></i>'
                . $documentLine->referencia
                . '</div>'
                . '<div class="small">' . $attributes . '</div>'
                . '</td>'
                . '<td  class="align-middle text-right text-nowrap">'
                . '<span class="btn btn-link price" onclick="changeQtyPrompt(' . $lineCart->id . ')">' . $documentLine->cantidad . '</span>'
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">'
                . '<div class="small" title="' . Tools::lang()->trans('unit-price') . '">' . Tools::money($documentLine->pvpunitario, $document->coddivisa) . '</div>'
                . '<div>' . Tools::money($documentLine->pvpsindto, $document->coddivisa) . '</div>'
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">'
                . '<div class="small">' . Tools::number($documentLine->dtopor) . ' %</div>'
                . '<div>' . Tools::money($this->getLineTotalDiscount($documentLine, $document), $document->coddivisa) . '</div>'
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">'
                . '<div class="small">' . Tools::number($documentLine->iva) . ' %</div>'
                . '<div>' . Tools::money($this->getLineTotalTax($documentLine, $document), $document->coddivisa) . '</div>'
                . '</td>'
                . '<td class="align-middle text-right text-nowrap">'
                . '<div class="small">' . Tools::money($documentLine->pvptotal, $document->coddivisa) . '</div>'
                . '<div>' . Tools::money($this->getLineSubtotal($documentLine, $document), $document->coddivisa) . '</div>'
                . '</td>'
                . '</tr>';
        }

        if (empty($cartHtml)) {
            $cartHtml = '<tr class="table-warning"><td colspan="7" class="text-center">' . Tools::lang()->trans('no-data') . '</td></tr>';
        }

        return [
            'cartHtml' => $cartHtml,
            'getCart' => true,
            'totalCart' => $document->total,
            'totalCartLabel' => Tools::money($document->total, $document->coddivisa),
            'totalDto' => $document->dtopor1,
            'totalDto2' => $document->dtopor2,
            'totalLines' => count($documentLines),
            'totalNet' => $document->neto,
            'totalNetLabel' => Tools::money($document->neto, $document->coddivisa),
            'totalSubtotal' => $document->netosindto,
            'totalSubtotalLabel' => Tools::money($document->netosindto, $document->coddivisa),
            'totalTaxes' => $document->totaliva,
            'totalTaxesLabel' => Tools::money($document->totaliva, $document->coddivisa),
        ];
    }

    protected function insertAction(): bool
    {
        return true;
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view): void
    {
        $this->hasData = true;

        switch ($viewName) {
            case 'docfiles':
                $view->cursor = $this->getContactDocFiles();
                $view->count = count($view->cursor);
                $view->setSettings('active', $view->count > 0 && $this->contact->pc_allow_show_files);
                break;

            case 'ListPortalAlbaran':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', ($view->count > 0 || $view->showFilters) && $this->contact->pc_allow_show_delivery_note);
                break;

            case 'ListPortalFactura':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', ($view->count > 0 || $view->showFilters) && $this->contact->pc_allow_show_invoice);
                break;

            case 'ListPortalNote':
                $where = [
                    new DataBaseWhere('codcliente', $this->contact->codcliente),
                    new DataBaseWhere('idcontacto', $this->contact->idcontacto, '=', 'OR')
                ];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', $view->count > 0);
                break;

            case 'ListPortalPedido':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', ($view->count > 0 || $view->showFilters) && $this->contact->pc_allow_show_order);
                break;

            case 'ListPortalPresupuesto':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', ($view->count > 0 || $view->showFilters) && $this->contact->pc_allow_show_estimation);
                break;

            case 'ListPortalTicket':
                $where = [new DataBaseWhere('idcontacto', $this->contact->idcontacto)];
                $view->loadData('', $where);
                break;

            case 'PortalAddresses':
                $view->count = count($this->addresses);
                $this->setSettings($viewName, 'active', $this->contact->pc_allow_show_addresses);
                break;

            case 'PortalCatalogue':
                $this->setSettings($viewName, 'active', $this->contact->pc_allow_show_catalogue);
                break;
        }
    }

    protected function logoutAction(): bool
    {
        // restablecemos el idioma por defecto
        Tools::lang()->setLang(constant('FS_LANG'));

        setcookie('pc_idcontacto', '', time() - 3600, Tools::config('route', '/'));
        setcookie('pc_log_key', '', time() - 3600, Tools::config('route', '/'));

        if ($this->user) {
            $this->redirect($this->contact->url());
        } else {
            $this->redirect('PortalCliente');
        }

        return true;
    }

    protected function markAsFavorite(): array
    {
        // buscamos si ya existe el producto en favoritos, si no lo encontramos, lo creamos
        $favorite = new PortalFavorite();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idproducto', $this->request->get('idproducto')),
        ];

        if ($favorite->loadFromCode('', $where)) {
            Tools::log()->warning('record-already-exists');
            return [
                'idproducto' => $this->request->get('idproducto'),
                'markAsFavorite' => true
            ];
        }

        $favorite->idcontacto = $this->contact->idcontacto;
        $favorite->idproducto = $this->request->get('idproducto');
        if (false === $favorite->save()) {
            Tools::log()->warning('record-save-error');
            return ['markAsFavorite' => false];
        }

        return [
            'idproducto' => $this->request->get('idproducto'),
            'markAsFavorite' => true
        ];
    }

    protected function newAddressAction(): bool
    {
        if (false === $this->validateFormToken()
            || false === $this->contact->exists()
            || false === $this->customer->exists()
            || false === $this->contact->pc_allow_show_addresses) {
            return true;
        }

        $fields = [
            'nombre', 'apellidos', 'empresa', 'tipoidfiscal', 'cifnif', 'direccion', 'apartado',
            'email', 'codpostal', 'ciudad', 'provincia', 'codpais', 'langcode', 'telefono1', 'descripcion'
        ];

        $contact = new Contacto();
        $contact->codcliente = $this->customer->codcliente;
        foreach ($fields as $field) {
            $contact->{$field} = $this->request->request->get($field);
        }

        if (false === $contact->save()) {
            Tools::log()->warning('record-save-error');
            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function removeLineCart(): array
    {
        $lineCart = new PortalCart();
        if (false === $lineCart->loadFromCode($this->request->get('idlinecart'))) {
            return ['removeLineCart' => false];
        }

        if (false === $lineCart->delete()) {
            return ['removeLineCart' => false];
        }

        return ['removeLineCart' => true];
    }

    protected function unmarkAsFavorite(): array
    {
        $favorite = new PortalFavorite();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idproducto', $this->request->get('idproducto')),
        ];

        if (false === $favorite->loadFromCode('', $where)) {
            Tools::log()->warning('record-not-found');
            return ['unmarkAsFavorite' => false];
        }

        if (false === $favorite->delete()) {
            Tools::log()->warning('record-delete-error');
            return ['unmarkAsFavorite' => false];
        }

        return [
            'idproducto' => $this->request->get('idproducto'),
            'unmarkAsFavorite' => true
        ];
    }

    protected function updateProductToCart(): array
    {
        $lineCart = new PortalCart();
        if (false === $lineCart->loadFromCode($this->request->get('idlinecart'))) {
            return ['updateProductToCart' => false];
        }

        $lineCart->quantity = $this->request->get('quantity');
        if (false === $lineCart->save()) {
            return ['updateProductToCart' => false];
        }

        return ['updateProductToCart' => true];
    }

    private function tableColToNumber(string $name): string
    {
        return strtolower(FS_DB_TYPE) == 'postgresql' ?
            'CAST(' . $name . ' as integer)' :
            'CAST(' . $name . ' as unsigned)';
    }
}

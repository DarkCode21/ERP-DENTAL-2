<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PrePagos\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\PedidoProveedor;
use FacturaScripts\Dinamic\Model\Presupuestoproveedor;

class EditPrePagoProv extends EditController
{
    public function getPageData(): array
    {
        $page = parent::getPageData();
        $page['menu'] = 'purchases';
        $page['title'] = 'payments';
        $page['icon'] = 'fa-solid fa-coins';
        return $page;
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'link-document') {
            return $this->linkDocumentAction();
        }

        return parent::execPreviousAction($action);
    }

    public function getModelClassName(): string
    {
        return 'PrePagoProv';
    }

    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        parent::loadData($viewName, $view);

        if ($mvn === $viewName
            && $view->model->exists()
            && $view->model->modelname === 'Proveedor') {
            $this->addButton($viewName, [
                'type' => 'modal',
                'action' => 'link-document',
                'icon' => 'fa-solid fa-link',
                'label' => 'link-document',
                'color' => 'warning',
            ]);
        }
    }

    protected function linkDocumentAction(): bool
    {
        $model = $this->getModel();
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-to-update');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $model->loadFromCode($this->request->get('code'))) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        // obtenemos el documento
        $estimation = $this->request->request->get('estimation');
        $order = $this->request->request->get('order');
        $deliveryNote = $this->request->request->get('delivery_note');

        // si todos están vacíos, terminamos
        if (empty($estimation) && empty($order) && empty($deliveryNote)) {
            Tools::log()->warning('no-document-selected');
            return true;
        }

        // si hay más de uno seleccionado, terminamos
        if (!empty($estimation) && !empty($order)) {
            Tools::log()->warning('only-one-document-allowed');
            return true;
        } elseif (!empty($estimation) && !empty($deliveryNote)) {
            Tools::log()->warning('only-one-document-allowed');
            return true;
        } elseif (!empty($order) && !empty($deliveryNote)) {
            Tools::log()->warning('only-one-document-allowed');
            return true;
        }

        // obtenemos el documento
        $document = null;

        if (!empty($estimation)) {
            $document = new PresupuestoProveedor();
            $document->loadFromCode($estimation);
        } elseif (!empty($order)) {
            $document = new PedidoProveedor();
            $document->loadFromCode($order);
        } elseif (!empty($deliveryNote)) {
            $document = new AlbaranProveedor();
            $document->loadFromCode($deliveryNote);
        }

        // si el documento no existe, terminamos
        if (false === $document->exists()) {
            Tools::log()->warning('document-not-found');
            return true;
        }

        // si el documento no es editable, terminamos
        if (false === $document->editable) {
            Tools::log()->warning('document-not-editable');
            return true;
        }

        // asociamos el prepago al documento
        $model->modelid = $document->primaryColumnValue();
        $model->modelname = $document->modelClassName();
        if (false === $model->save()) {
            Tools::log()->info('record-save-error');
            return true;
        }

        Tools::log()->info('record-updated-correctly');
        $this->redirect($document->url(), 2);
        return true;
    }
}

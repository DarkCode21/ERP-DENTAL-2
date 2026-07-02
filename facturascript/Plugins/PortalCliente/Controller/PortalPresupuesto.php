<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\WorkQueue;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\PortalCatalogue;
use FacturaScripts\Dinamic\Lib\PortalViewController;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Dinamic\Model\LineaPresupuestoCliente;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocCommonTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocFilesTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocPaymentTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocPrePagoTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocShare;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPresupuesto extends PortalViewController
{
    use PortalDocFilesTrait;
    use PortalDocPaymentTrait;
    use PortalDocPrePagoTrait;
    use PortalDocCommonTrait;

    public function getImages(LineaPresupuestoCliente $line): string
    {
        $variant = $line->getVariante();
        $product = $line->getProducto();
        if (false === $variant->exists() || false === $product->exists()) {
            return '';
        }

        $images = $product->getImages();
        return PortalCatalogue::getGalleryImage($images, $variant->referencia);
    }

    public function getModelClassName(): string
    {
        return 'PresupuestoCliente';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'PortalCliente';
        $data['title'] = 'customer-estimation';
        $data['icon'] = 'far fa-file-powerpoint';
        return $data;
    }

    protected function acceptAction(): bool
    {
        $model = $this->preloadModel();
        $status = new EstadoDocumento();

        if (false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        } elseif (false === $status->loadFromCode(Tools::settings('portalcliente', 'status_approve_estimations'))) {
            Tools::log()->error('status-approve-estimations-not-configured');
            return true;
        }

        $model->idestado = $status->idestado;
        if ($model->save()) {
            WorkQueue::send(
                'PortalDocNotice',
                $model->primaryColumnValue(),
                [
                    'model_name' => 'PresupuestoCliente',
                    'notice_name' => 'group_approve_estimations',
                ]
            );
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->error('record-save-error');
        return true;
    }

    protected function cancelAction(): bool
    {
        $model = $this->preloadModel();
        $status = new EstadoDocumento();

        if (false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        } elseif (false === $status->loadFromCode(Tools::settings('portalcliente', 'status_cancel_estimations'))) {
            Tools::log()->error('status-cancel-estimations-not-configured');
            return true;
        }

        $model->idestado = $status->idestado;
        if ($model->save()) {
            WorkQueue::send(
                'PortalDocNotice',
                $model->primaryColumnValue(),
                [
                    'model_name' => 'PresupuestoCliente',
                    'notice_name' => 'group_cancel_estimations',
                ]
            );
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->error('record-save-error');
        return true;
    }

    protected function createViews(): void
    {
        $model = $this->preloadModel();
        if (false === $model->exists()) {
            $this->error404();
            return;
        }

        $this->setContactPermissions($model);
        if (false === $this->permissions->allowAccess) {
            $this->error403();
            return;
        }

        parent::createViews();
        $this->addHtmlView('info', 'Tab/PortalInfoPresupuesto', 'PresupuestoCliente', 'detail', 'fas fa-info-circle');
        $this->createViewDocFiles();

        if (Plugins::isEnabled('PrePagos')) {
            $this->createViewPrePagos();
        }
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'accept':
                return $this->acceptAction();

            case 'cancel':
                return $this->cancelAction();

            case 'pay':
                return $this->payAction();

            case 'pay-paypal-link':
                return $this->payPaypalLinkAction();

            case 'print':
                return $this->printAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function getComposeUrlColumn(): string
    {
        return 'pc_uuid';
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'docfiles':
                $view->cursor = $this->getModelDocFiles($this->views['main']->model);
                $view->count = count($view->cursor);
                $view->setSettings('active', $view->count > 0 && $this->contact->exists() && $this->contact->pc_allow_show_files);
                break;

            case 'ListPrePago':
                $this->loadDataPrePagos($view);
                break;

            case self::MAIN_VIEW_NAME:
                parent::loadData($viewName, $view);
                $this->title = Tools::lang()->trans('estimation') . ' ' . $view->model->codigo;
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    private function printAction(): bool
    {
        if (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        }

        $this->setTemplate(false);
        $exportManager = new ExportManager();
        $exportManager->newDoc($exportManager->defaultOption());
        $exportManager->addBusinessDocPage($this->preloadModel());
        $exportManager->show($this->response);
        return false;
    }

    private function setContactPermissions($model): void
    {
        // enlace de compartición
        $codeShare = $this->request->get('share');
        if ($codeShare && PortalDocShare::checkCode($model, $codeShare)) {
            $this->permissions->set(true, 1, false, false);
            return;
        }

        // anónimo
        if (false === $this->contact->exists()) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        // si no tiene permisos de ver
        if (false === $this->contact->pc_allow_show_estimation) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        // dirección de facturación o cliente
        if ($model->idcontactofact === $this->contact->idcontacto
            || $model->codcliente === $this->contact->codcliente) {
            $this->permissions->set(true, 1, false, false);
            return;
        }

        // no autorizado
        $this->permissions->set(false, 0, false, false);
    }
}

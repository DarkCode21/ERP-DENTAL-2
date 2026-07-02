<?php
/**
 * Copyright (C) 2021-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Facturae\Extension\Controller;

use Closure;
use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Facturae\Model\XmlFacturaE;

/**
 * Description of EditFacturaCliente
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditFacturaCliente
{
    protected function autofirma(): Closure
    {
        return function ($xmlFactura) {
            if (false === $xmlFactura->generate()) {
                return false;
            }

            $this->setTemplate(false);
            $result = ['idfactura' => $xmlFactura->idfactura, 'content' => file_get_contents($xmlFactura->getFilePath(false))];
            $this->response->setContent(json_encode($result, 1));
        };
    }

    protected function createViews(): Closure
    {
        return function () {
            $this->addHtmlView('facturae', 'Tab/Facturae', 'XmlFacturaE', 'facturae', 'fas fa-qrcode');

            // añadimos el javascript necesario
            AssetManager::add('js', FS_ROUTE . '/Plugins/Facturae/Assets/JS/autoscript.js');
            AssetManager::add('js', FS_ROUTE . '/Plugins/Facturae/Assets/JS/facturae.js');
        };
    }

    protected function deleteFacturaeAction(): Closure
    {
        return function () {
            if (false === $this->permissions->allowDelete) {
                Tools::log()->warning('not-allowed-delete');
                return;
            } elseif (false === $this->validateFormToken()) {
                return;
            }

            $xmlfacturae = new XmlFacturaE();
            $idfacturae = $this->request->request->get('idfacturae');
            if ($xmlfacturae->loadFromCode($idfacturae) && $xmlfacturae->delete()) {
                Tools::log()->notice('record-deleted-correctly');
                return;
            }

            Tools::log()->warning('record-deleted-error');
        };
    }

    protected function downloadFacturaeAction(): Closure
    {
        return function () {
            if (false === $this->validateFormToken()) {
                return;
            }

            $xmlFactura = new XmlFacturaE();
            $idfacturae = $this->request->request->get('idfacturae');
            if (false === $xmlFactura->loadFromCode($idfacturae)) {
                Tools::log()->warning('record-not-found');
                return;
            }

            // obtenemos la factura
            $factura = new FacturaCliente();
            if (false === $factura->loadFromCode($xmlFactura->idfactura)) {
                Tools::log()->warning('record-not-found');
                return;
            }

            // creamos el nombre del fichero, evitamos caracteres especiales
            $code = preg_replace('/[^a-zA-Z0-9]/', '', $factura->codigo);

            $this->setTemplate(false);
            $this->response->headers->set('Content-Type', 'application/xml');
            $this->response->headers->set('Content-Disposition', 'attachment;filename=facturae_' . $code . '.xsig');
            $this->response->setContent(file_get_contents($xmlFactura->getFilePath()));
            return false;
        };
    }

    protected function downloadXml(): Closure
    {
        return function ($xmlFactura) {
            if (false === $xmlFactura->generate()) {
                return false;
            }

            $this->setTemplate(false);
            $this->response->headers->set('Content-Type', 'application/xml');
            $this->response->headers->set('Content-Disposition', 'attachment;filename=facturae_' . $xmlFactura->idfactura . '.xml');
            $this->response->setContent(file_get_contents($xmlFactura->getFilePath(false)));
            return true;
        };
    }

    protected function editFacturaeAction(): Closure
    {
        return function () {
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-update');
                return;
            } elseif (false === $this->validateFormToken()) {
                return;
            }

            $factura = new FacturaCliente();
            $idfactura = $this->request->query->get('code');
            if (false === $factura->loadFromCode($idfactura)) {
                return;
            }

            $xmlFactura = new XmlFacturaE();
            $idfacturae = $this->request->request->get('idfacturae');
            if (empty($idfacturae) || $xmlFactura->loadFromCode($idfacturae)) {
                // new record
                $xmlFactura->idfactura = $factura->idfactura;
            }

            $xmlFactura->vencimiento = $this->request->request->get('vencimiento');
            $xmlFactura->iban = $this->request->request->get('iban');
            $xmlFactura->filereference = $this->request->request->get('filereference');
            $xmlFactura->observaciones = (bool)$this->request->request->get('observaciones', '0');
            $xmlFactura->receivertransref = $this->request->request->get('receivertransref', '');
            $xmlFactura->receivercontraref = $this->request->request->get('receivercontraref', '');
            $xmlFactura->issuertransref = $this->request->request->get('issuertransref', '');
            $xmlFactura->issuercontraref = $this->request->request->get('issuercontraref', '');
            $xmlFactura->description = $this->request->request->get('description', '');
            $xmlFactura->legalliterals = $this->request->request->get('legalliterals', '');

            $xmlFactura->startdate = $this->request->request->get('startdate', '') == '' ? null : $this->request->request->get('startdate', '');
            $xmlFactura->enddate = $this->request->request->get('enddate', '') == '' ? null : $this->request->request->get('enddate', '');

            // oficina contable
            $formData = $this->request->request->all();
            $xmlFactura->codoficina = $formData['codoficina'];
            if ($xmlFactura->codoficina) {
                $xmlFactura->nomoficina = empty($formData['nomoficina']) ? $factura->nombrecliente : $formData['nomoficina'];
                $xmlFactura->diroficina = empty($formData['diroficina']) ? $factura->direccion : $formData['diroficina'];
                $xmlFactura->cpoficina = empty($formData['cpoficina']) ? $factura->codpostal : $formData['cpoficina'];
                $xmlFactura->ciuoficina = empty($formData['ciuoficina']) ? $factura->ciudad : $formData['ciuoficina'];
                $xmlFactura->proficina = empty($formData['proficina']) ? $factura->provincia : $formData['proficina'];
            } else {
                $xmlFactura->nomoficina = $xmlFactura->diroficina = $xmlFactura->cpoficina = '';
                $xmlFactura->ciuoficina = $xmlFactura->proficina = '';
            }

            // órgano gestor
            $xmlFactura->codorgano = $formData['codorgano'];
            if ($xmlFactura->codorgano) {
                $xmlFactura->nomorgano = empty($formData['nomorgano']) ? $factura->nombrecliente : $formData['nomorgano'];
                $xmlFactura->dirorgano = empty($formData['dirorgano']) ? $factura->direccion : $formData['dirorgano'];
                $xmlFactura->cporgano = empty($formData['cporgano']) ? $factura->codpostal : $formData['cporgano'];
                $xmlFactura->ciuorgano = empty($formData['ciuorgano']) ? $factura->ciudad : $formData['ciuorgano'];
                $xmlFactura->prorgano = empty($formData['prorgano']) ? $factura->provincia : $formData['prorgano'];
            } else {
                $xmlFactura->nomorgano = $xmlFactura->dirorgano = $xmlFactura->cporgano = '';
                $xmlFactura->ciuorgano = $xmlFactura->prorgano = '';
            }

            // unidad tramitadora
            $xmlFactura->codunidad = $formData['codunidad'];
            if ($xmlFactura->codunidad) {
                $xmlFactura->nomunidad = empty($formData['nomunidad']) ? $factura->nombrecliente : $formData['nomunidad'];
                $xmlFactura->dirunidad = empty($formData['dirunidad']) ? $factura->direccion : $formData['dirunidad'];
                $xmlFactura->cpunidad = empty($formData['cpunidad']) ? $factura->codpostal : $formData['cpunidad'];
                $xmlFactura->ciuunidad = empty($formData['ciuunidad']) ? $factura->ciudad : $formData['ciuunidad'];
                $xmlFactura->prunidad = empty($formData['prunidad']) ? $factura->provincia : $formData['prunidad'];
            } else {
                $xmlFactura->nomunidad = $xmlFactura->dirunidad = $xmlFactura->cpunidad = '';
                $xmlFactura->ciuunidad = $xmlFactura->prunidad = '';
            }

            // órgano proponente
            $xmlFactura->desorganop = $formData['desorganop'];
            $xmlFactura->codorganop = $formData['codorganop'];
            if ($xmlFactura->codorganop) {
                $xmlFactura->nomorganop = empty($formData['nomorganop']) ? $factura->nombrecliente : $formData['nomorganop'];
                $xmlFactura->dirorganop = empty($formData['dirorganop']) ? $factura->direccion : $formData['dirorganop'];
                $xmlFactura->cporganop = empty($formData['cporganop']) ? $factura->codpostal : $formData['cporganop'];
                $xmlFactura->ciuorganop = empty($formData['ciuorganop']) ? $factura->ciudad : $formData['ciuorganop'];
                $xmlFactura->prorganop = empty($formData['prorganop']) ? $factura->provincia : $formData['prorganop'];
            } else {
                $xmlFactura->nomorganop = $xmlFactura->dirorganop = $xmlFactura->cporganop = '';
                $xmlFactura->ciuorganop = $xmlFactura->prorganop = '';
            }

            if (false === $xmlFactura->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            // ¿Firmamos o solo generamos el xml?
            $option = $this->request->request->get('sign', 2);
            switch ($option) {
                case 1:
                    $done = $this->signFacturae($xmlFactura);
                    break;

                case 2:
                    $done = $this->autofirma($xmlFactura);
                    break;

                default:
                    $done = $this->downloadXml($xmlFactura);
                    break;
            }
            if ($done) {
                Tools::log()->notice('record-updated-correctly');
                return;
            }

            $xmlFactura->delete();
        };
    }


    protected function execPreviousAction(): Closure
    {
        return function ($action) {
            switch ($action) {
                case 'delete-facturae':
                    $this->deleteFacturaeAction();
                    break;

                case 'download-facturae':
                    return $this->downloadFacturaeAction();

                case 'edit-facturae':
                    $this->editFacturaeAction();
                    break;

                case 'send-face':
                    $this->sendFaceAction();
                    break;
            }
        };
    }

    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'facturae') {
                return;
            }

            $mvn = $this->getMainViewName();
            $where = [new DataBaseWhere('idfactura', $this->views[$mvn]->model->primaryColumnValue())];
            $view->loadData('', $where);
            if ($view->model->exists()) {
                return;
            }

            // sets default data for the new facturae
            $view->model->iban = $this->views[$mvn]->model->getPaymentMethod()->getBankAccount()->iban;
            $view->model->idfactura = $this->views[$mvn]->model->idfactura;
            $this->setFacturaeValuesFromOldInvoices($view->model);
        };
    }

    protected function sendFaceAction(): Closure
    {
        return function () {
            if (false === $this->permissions->allowUpdate) {
                Tools::log()->warning('not-allowed-delete');
                return;
            } elseif (false === $this->validateFormToken()) {
                return;
            }

            $xmlFactura = new XmlFacturaE();
            $idfacturae = $this->request->request->get('idfacturae');
            if (false === $xmlFactura->loadFromCode($idfacturae)) {
                return;
            }

            $fcert = $this->request->files->get('fcert');
            $password = $this->request->request->get('certpass');
            if (empty($fcert) || empty($password)) {
                return;
            }

            try {
                if ($xmlFactura->sendFace($fcert->getPathname(), $password, $this->user->email)) {
                    Tools::log()->notice('record-updated-correctly');
                }
            } catch (Exception $e) {
                Tools::log()->error($e->getMessage());
            }
        };
    }

    protected function setFacturaeValuesFromOldInvoices(): Closure
    {
        return function ($xmlFactura) {
            $invoice = new FacturaCliente();
            $mvn = $this->getMainViewName();
            $where = [
                new DataBaseWhere('codcliente', $this->views[$mvn]->model->codcliente),
                new DataBaseWhere('idfactura', $this->views[$mvn]->model->idfactura, '!=')
            ];
            foreach ($invoice->all($where) as $oldInvoice) {
                $xmlFeModel = new XmlFacturaE();
                $where2 = [new DataBaseWhere('idfactura', $oldInvoice->idfactura)];
                foreach ($xmlFeModel->all($where2) as $oldXML) {
                    $xmlFactura->codoficina = $oldXML->codoficina;
                    $xmlFactura->nomoficina = $oldXML->nomoficina;
                    $xmlFactura->diroficina = $oldXML->diroficina;
                    $xmlFactura->cpoficina = $oldXML->cpoficina;
                    $xmlFactura->ciuoficina = $oldXML->ciuoficina;
                    $xmlFactura->proficina = $oldXML->proficina;
                    $xmlFactura->codorgano = $oldXML->codorgano;
                    $xmlFactura->nomorgano = $oldXML->nomorgano;
                    $xmlFactura->dirorgano = $oldXML->dirorgano;
                    $xmlFactura->cporgano = $oldXML->cporgano;
                    $xmlFactura->ciuorgano = $oldXML->ciuorgano;
                    $xmlFactura->prorgano = $oldXML->prorgano;
                    $xmlFactura->codunidad = $oldXML->codunidad;
                    $xmlFactura->nomunidad = $oldXML->nomunidad;
                    $xmlFactura->dirunidad = $oldXML->dirunidad;
                    $xmlFactura->cpunidad = $oldXML->cpunidad;
                    $xmlFactura->ciuunidad = $oldXML->ciuunidad;
                    $xmlFactura->prunidad = $oldXML->prunidad;
                    $xmlFactura->desorganop = $oldXML->desorganop;
                    $xmlFactura->codorganop = $oldXML->codorganop;
                    $xmlFactura->nomorganop = $oldXML->nomorganop;
                    $xmlFactura->dirorganop = $oldXML->dirorganop;
                    $xmlFactura->cporganop = $oldXML->cporganop;
                    $xmlFactura->ciuorganop = $oldXML->ciuorganop;
                    $xmlFactura->prorganop = $oldXML->prorganop;
                }
            }
        };
    }

    protected function signFacturae(): Closure
    {
        return function ($xmlFactura) {
            $cert = $this->request->files->get('fcert');
            $password = $this->request->request->get('certpass');
            if (empty($cert)) {
                Tools::log()->warning('certificate-required');
                return false;
            } elseif (empty($password)) {
                Tools::log()->warning('certificate-password-required');
                return false;
            }

            if (false === $xmlFactura->generate($cert->getPathname(), $password)) {
                return false;
            }

            $sendFace = (bool)$this->request->request->get('sendface', '0');
            if ($sendFace) {
                return $xmlFactura->sendFace($cert->getPathname(), $password, $this->user->email);
            }

            return true;
        };
    }
}

<?php
/**
 * This file is part of InformeSII plugin for FacturaScripts
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\InformeSII\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\InformeSII\Lib\SuministroFactEmitidas;
use FacturaScripts\Plugins\InformeSII\Lib\SuministroFactRecibidas;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportSii extends Controller
{
    /** @var string */
    public $datefrom;

    /** @var string */
    public $dateto;

    /** @var int */
    public $idempresa;

    /** @var string */
    public $codpago;

    /** @var string */
    public $codpais;

    /** @var string */
    public $codserie;

    /** @var string */
    public $provincia;

    /** @var array */
    public $sendXml = [];

    /** @var string */
    public $source;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'report-sii';
        $data['menu'] = 'reports';
        $data['icon'] = 'fas fa-file-invoice';
        return $data;
    }

    public function getFormasPagos(): array
    {
        $pagoModel = new FormaPago();
        return $pagoModel->all([], ['descripcion' => 'ASC'], 0, 0);
    }

    public function getSources(): array
    {
        return [
            'SuministroFactEmitidas' => $this->toolBox()->i18n()->trans('customer-invoices'),
            'SuministroFactRecibidas' => $this->toolBox()->i18n()->trans('supplier-invoices'),
        ];
    }

    public function getPaises(): array
    {
        $paisModel = new Pais();
        return $paisModel->all([], ['nombre' => 'ASC'], 0, 0);
    }

    public function getProvincias(): array
    {
        $sql = "SELECT DISTINCT provincia FROM facturascli WHERE provincia != '' ORDER BY provincia ASC;";
        return $this->dataBase->select($sql);
    }

    public function getSeries(): array
    {
        $serieModel = new Serie();
        return $serieModel->all([], ['descripcion' => 'ASC'], 0, 0);
    }

    /**
     * @param Response $response
     * @param User $user
     * @param ControllerPermissions $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->datefrom = $this->request->request->get('datefrom', date('Y-m-01'));
        $this->dateto = $this->request->request->get('dateto', date('Y-m-t'));
        $this->idempresa = (int)$this->request->request->get('idempresa', $this->empresa->idempresa);
        $this->codpago = $this->request->request->get('codpago');
        $this->codpais = $this->request->request->get('codpais');
        $this->codserie = $this->request->request->get('codserie');
        $this->provincia = $this->request->request->get('provincia');
        $this->source = $this->request->request->get('source');

        $report = null;
        switch ($this->source) {
            case 'SuministroFactEmitidas':
                $report = new SuministroFactEmitidas($this->idempresa, $this->datefrom, $this->dateto, $this->codserie, $this->codpais, $this->codpago, $this->provincia);
                break;

            case 'SuministroFactRecibidas':
                $report = new SuministroFactRecibidas($this->idempresa, $this->datefrom, $this->dateto, $this->codserie, $this->codpais, $this->codpago);
                break;
        }

        switch ($this->request->request->get('action')) {
            case 'send':
                $this->sendAction($report);
                break;

            case 'download':
                $this->downloadAction($report);
                break;
        }
    }

    protected function checkDecimals(): bool
    {
        if ((int)$this->toolBox()->appSettings()->get('default', 'decimals') !== 2) {
            $this->toolBox()->i18nLog()->warning('set-decimals-to-two');
            return false;
        }

        return true;
    }

    protected function downloadAction($report)
    {
        if (false === $this->checkDecimals()) {
            return;
        }

        $xml = $report->getXml();
        if (empty($xml)) {
            $this->toolBox()->i18nLog()->warning('no-data');
            return;
        }

        $this->setTemplate(false);
        $this->response->headers->set('Content-Type', 'text/xml');
        $fileName = $this->toolBox()->i18n()->trans('report-sii')
            . ' ' . $this->toolBox()->i18n()->trans($this->source)
            . ' ' . $this->datefrom
            . ' ' . $this->dateto
            . '.xml';
        $this->response->headers->set('Content-Disposition', 'attachment; filename=' . $fileName);
        $this->response->setContent($xml);
    }

    protected function sendAction($report)
    {
        if (false === $this->checkDecimals()) {
            return;
        }

        $this->sendXml = $report->sendXml();

        $success = $this->sendXml['success'] ?? 0;
        $errors = $this->sendXml['errors'] ?? 0;
        $warnings = $this->sendXml['warnings'] ?? 0;
        $invoices = $this->sendXml['invoices'] ?? 0;

        $this->toolBox()->i18nLog()->info('xml-processing', [
            '%num%' => $success + $warnings,
            '%total%' => $invoices
        ]);

        if ($success > 0) {
            $this->toolBox()->i18nLog()->notice('xml-sent-success', ['%num%' => $success]);
        }

        if ($errors > 0) {
            $this->toolBox()->i18nLog()->error('xml-sent-error', ['%num%' => $errors]);
        }

        if ($warnings > 0) {
            $this->toolBox()->i18nLog()->warning('xml-sent-warning', ['%num%' => $warnings]);
        }
    }
}

<?php

/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\ApiClient;
use FacturaScripts\Plugins\Verifactu\Lib\Verifactu\JsonConsulta;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ReportVerifactu extends ListController
{
    /** @var string */
    public $codejercicio;

    /** @var Cliente */
    public $customer;

    /** @var string */
    public $desde;

    /** @var string */
    public $hasta;

    /** @var array */
    public $invoices = [];

    public function getCompanies(): array
    {
        $companies = [];
        foreach (Empresas::all() as $company) {
            if ($company->verifactuIsConfigured(false)) {
                $companies[] = $company;
            }
        }
        return $companies;
    }

    public function getExercises(int $idempresa): array
    {
        $where = [
            new DataBaseWhere('idempresa', $idempresa),
            new DataBaseWhere('vf_mode', 'verifactu'),
        ];
        return Ejercicio::all($where);
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'verifactu';
        $data['icon'] = 'fa-solid fa-qrcode';
        return $data;
    }

    private function autocompleteCustomerAction(): bool
    {
        $this->setTemplate(false);

        $list = [];
        $query = $this->request->get('query');
        $fields = 'cifnif|codcliente|email|nombre|observaciones|razonsocial|telefono1|telefono2';

        $where = [
            new DataBaseWhere('fechabaja', null, 'IS'),
            new DataBaseWhere($fields, mb_strtolower($query, 'UTF8'), 'LIKE'),
        ];

        foreach (Cliente::all($where) as $customer) {
            $list[] = [
                'key' => $customer->codcliente,
                'value' => $customer->nombre,
            ];
        }

        if (empty($list)) {
            $list[] = ['key' => null, 'value' => Tools::lang()->trans('no-data')];
        }

        $this->response->setContent(json_encode($list));
        return true;
    }

    protected function createViews()
    {
        $this->createViewsReport();
        $this->createViewsInvoices();
        $this->createViewsEvents();
    }

    protected function createViewsInvoices(string $viewName = 'ListVerifactuRegistroFactura'): void
    {
        $companies = $this->codeModel->all('empresas', 'idempresa', 'nombrecorto');
        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'codejercicio');
        $events = [
            ['code' => 'alta', 'description' => 'alta'],
            ['code' => 'subsanacion', 'description' => 'subsanacion'],
            ['code' => 'anulacion', 'description' => 'anulacion'],
        ];
        $modes = [
            ['code' => 'verifactu', 'description' => 'verifactu'],
            ['code' => 'no-verifactu', 'description' => 'no-verifactu'],
        ];
        $statuses = [
            ['code' => 'Correcto', 'description' => 'Correcto'],
            ['code' => 'AceptadoConErrores', 'description' => 'AceptadoConErrores'],
        ];

        $this->addView($viewName, 'VerifactuRegistroFactura', 'invoices', 'fa-solid fa-file-invoice-dollar')
            ->addOrderBy(['id'], 'id', 2)
            ->addFilterSelect('company', 'company', 'idempresa', $companies)
            ->addFilterSelect('exercise', 'exercise', 'codejercicio', $exercises)
            ->addFilterAutocomplete('idfactura', 'invoice', 'idfactura', 'facturascli', 'idfactura', 'codigo')
            ->addFilterSelect('event', 'event', 'event', $events)
            ->addFilterSelect('mode', 'mode', 'mode', $modes)
            ->addFilterSelect('status', 'status', 'status', $statuses)
            ->addFilterPeriod('creation_date', 'creation-date', 'creation_date')
            ->addFilterCheckbox('hash', 'hash', 'hash')
            ->setSettings('clickable', false)
            ->setSettings('checkBoxes', false)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);
    }

    protected function createViewsEvents(string $viewName = 'ListVerifactuRegistroEvento'): void
    {
        $companies = $this->codeModel->all('empresas', 'idempresa', 'nombrecorto');
        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'codejercicio');
        $types = $this->codeModel->all('verifactu_registros_eventos', 'type_name', 'type_name');

        $this->addView($viewName, 'VerifactuRegistroEvento', 'events', 'fa-solid fa-list-check')
            ->addOrderBy(['id'], 'id', 2)
            ->addFilterSelect('company', 'company', 'idempresa', $companies)
            ->addFilterSelect('exercise', 'exercise', 'codejercicio', $exercises)
            ->addFilterSelect('type', 'type', 'type_name', $types)
            ->addFilterPeriod('creation_date', 'creation-date', 'creation_date')
            ->addFilterCheckbox('hash', 'hash', 'hash')
            ->setSettings('clickable', false)
            ->setSettings('checkBoxes', false)
            ->setSettings('btnDelete', false)
            ->setSettings('btnNew', false);
    }

    protected function createViewsReport(string $viewName = 'ListVerifactuRegistroFactura-2'): void
    {
        $this->addView($viewName, 'Settings', 'search', 'fas fa-search');
        $this->views[$viewName]->template = 'ReportVerifactu.html.twig';
    }

    private function downloadJsonAction(): bool
    {
        $this->setTemplate(false);

        $jsonData = $this->request->request->get('json_data');
        if (empty($jsonData)) {
            return true;
        }

        // Decodificar el string JSON recibido
        $decodedJson = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Si hay error de decodificación, devolver el string tal cual
            $decodedJson = $jsonData;
        }
        
        // Establecer las cabeceras para la descarga
        $name = Tools::slug($this->request->request->get('ref_externa', 'verifactu'));
        header('Content-disposition: attachment; filename=' . $name . '.json');
        header('Content-type: application/json');

        // Enviar el JSON formateado al navegador
        echo json_encode($decodedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return true;
    }

    protected function execPreviousAction($action)
    {
        $this->initFilters();

        return match ($action) {
            'autocomplete-customer' => $this->autocompleteCustomerAction(),
            'search' => $this->searchAction(),
            'download-json' => $this->downloadJsonAction(),
            default => parent::execPreviousAction($action),
        };
    }

    private function initFilters(): void
    {
        $this->customer = new Cliente();
        $this->codejercicio = $this->request->request->get('codejercicio');
        $this->desde = $this->request->request->get('desde', date('Y-m-01'));
        $this->hasta = $this->request->request->get('hasta', date('Y-m-t'));
        $this->customer->loadFromCode($this->request->request->get('codcliente'));
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ListVerifactuRegistroFactura':
            case 'ListVerifactuRegistroEvento':
                parent::loadData($viewName, $view);
                break;
        }
    }

    private function searchAction(): bool
    {
        if (false === $this->validateFormToken()) {
            return true;
        }

        // cargamos el ejercicio
        $exercise = new Ejercicio();
        if (false === $exercise->loadFromCode($this->codejercicio)) {
            Tools::log()->warning('not-found-exercise', [
                '%code%' => $this->codejercicio,
            ]);
            return true;
        }

        // obtenemos el json de la consulta
        $json = JsonConsulta::generate($exercise, $this->desde, $this->hasta, $this->customer);
        if (empty($json)) {
            Tools::log()->error('json-generation-failed');
            return true;
        }

        $company = Empresas::get($exercise->idempresa);
        $respuesta = ApiClient::sendSearch($company, $json);

        // pintamos los mensajes de error
        foreach ($respuesta['errors'] as $error) {
            Tools::log()->error($error);
        }

        $this->invoices = empty($respuesta['invoices'][0]['NumSerieFactura']) ? [] : $respuesta['invoices'];
        return true;
    }
}

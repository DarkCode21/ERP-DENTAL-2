<?php
/**
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\PanelController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormatoDocumento;

/**
 * Description of AdminPlantillasPDF
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class AdminPlantillasPDF extends PanelController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'pdf-templates';
        $data['icon'] = 'fas fa-print';
        return $data;
    }

    protected function checkFooterText(): void
    {
        if (strlen($this->request->request->get('footertext')) > 200 &&
            (int)$this->request->request->get('bottommargin') <= 20) {
            Tools::log()->warning('footer-text-long');
        }
    }

    protected function checkMultipleLogos(): void
    {
        // comprobamos si alguna de las empresas tiene logo
        $companyModel = new Empresa();
        $where = [new DataBaseWhere('idlogo', null, 'IS NOT')];
        $logoCompany = $companyModel->count($where) > 0;

        // comprobamos si tenemos logo en los formatos
        $formatModel = new FormatoDocumento();
        $logoFormat = $formatModel->count($where) > 0;

        // comprobamos si tenemos logo en la configuración
        $logoDefault = Tools::settings('plantillaspdf', 'idlogo') !== null;

        // si en 2 de los 3 sitios hay logo, mostramos mensaje
        if ($logoCompany + $logoFormat + $logoDefault > 1) {
            Tools::log()->info('alert-multiple-logos');
        }
    }

    protected function createViews()
    {
        $this->setTemplate('EditSettings');
        $this->createViewsEditConfig();
        $this->createViewsFormats();
    }

    protected function createViewsEditConfig(string $viewName = 'ConfigPlantillasPDF'): void
    {
        $this->addEditView($viewName, 'Settings', 'general');

        // disable buttons
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function createViewsFormats(string $viewName = 'ListFormatoDocumento'): void
    {
        $this->addListView($viewName, 'FormatoDocumento', 'printing-formats', 'fas fa-print')
            ->addSearchFields(['nombre', 'titulo', 'texto'])
            ->addOrderBy(['nombre'], 'name')
            ->addOrderBy(['titulo'], 'title');
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        if ($action == 'preview') {
            return $this->previewAction();
        }

        $activeTab = $this->request->request->get('activetab');
        if ($activeTab === 'ConfigPlantillasPDF' && $action === 'edit') {
            $this->checkFooterText();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'ConfigPlantillasPDF':
                $view->loadData('plantillaspdf');
                $view->model->name = 'plantillaspdf';
                $this->loadPdfTemplates($view);
                $this->loadFonts($view, 'font');
                $this->checkMultipleLogos();
                break;

            case 'ListFormatoDocumento':
                $view->loadData();
                break;
        }
    }

    protected function loadFonts(&$view, $column): void
    {
        $column = $view->columnForName($column);
        if ($column && $column->widget->getType() === 'select') {
            $customValues = [];
            foreach (Tools::folderScan(FS_FOLDER . '/Plugins/PlantillasPDF/vendor/mpdf/mpdf/ttfonts') as $fileName) {
                $customValues[] = substr($fileName, 0, -4);
            }
            $column->widget->setValuesFromArray($customValues);
        }
    }

    protected function loadPdfTemplates(BaseView &$view): void
    {
        // cargamos en la lista todas las plantillas de la carpeta
        $list = [];
        foreach (Tools::folderScan(FS_FOLDER . '/Dinamic/Lib/PlantillasPDF') as $fileName) {
            // excluimos si no es un archivo php
            if (substr($fileName, -4) != '.php') {
                continue;
            }

            // excluimos si es BaseTemplate.php
            if ($fileName == 'BaseTemplate.php') {
                continue;
            }

            $list[] = substr($fileName, 0, -4);
        }

        $templateColumn = $view->columnForName('template');
        if ($templateColumn && $templateColumn->widget->getType() === 'radio') {
            $templateColumn->widget->setValuesFromArray($list);
        }
    }

    protected function previewAction(): bool
    {
        $FacturaCliente = new FacturaCliente();
        $facturas = $FacturaCliente->all([], ['fecha' => 'DESC']);
        if (empty($facturas)) {
            Tools::log()->warning('no-invoices-to-preview');
            return true;
        }

        shuffle($facturas);
        foreach ($facturas as $factura) {
            $this->setTemplate(false);
            $this->exportManager->newDoc('PDF');
            $this->exportManager->addBusinessDocPage($factura);
            $this->exportManager->show($this->response);
            break;
        }

        return true;
    }
}

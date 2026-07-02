<?php
/**
 * ListHistorial
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\Dental\Model\Historial;

class ListHistorial extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'clinical-history';
        $data['icon'] = 'fas fa-file-medical';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewHistorial();
    }

    protected function createViewHistorial(string $viewName = 'ListHistorial')
    {
        $this->addView($viewName, 'Historial', 'clinical-history', 'fas fa-file-medical');
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addSearchFields($viewName, ['motivo_consulta', 'diagnostico']);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'ListHistorial' && !empty($view->cursor)) {
            foreach ($view->cursor as $historial) {
                if ($historial instanceof Historial) {
                    $paciente = $historial->getPaciente();
                    $historial->paciente_nombre = $paciente ? ($paciente->getCliente()->razonsocial ?? '') : '';
                }
            }
        }
    }
}

<?php
/**
 * ListTratamientoPaciente
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\Dental\Model\TratamientoPaciente;

class ListTratamientoPaciente extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'treatments';
        $data['icon'] = 'fas fa-tooth';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewTratamientos();
    }

    protected function createViewTratamientos(string $viewName = 'ListTratamientoPaciente')
    {
        $this->addView($viewName, 'TratamientoPaciente', 'treatments', 'fas fa-tooth');
        $this->addOrderBy($viewName, ['fecha_inicio'], 'date');
        $this->addSearchFields($viewName, ['observaciones']);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'ListTratamientoPaciente' && !empty($view->cursor)) {
            foreach ($view->cursor as $tratamiento) {
                if ($tratamiento instanceof TratamientoPaciente) {
                    $paciente = $tratamiento->getPaciente();
                    $tratamiento->paciente_nombre = $paciente ? ($paciente->getCliente()->razonsocial ?? '') : '';
                }
            }
        }
    }
}

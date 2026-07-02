<?php
/**
 * ListCita
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\Dental\Model\Cita;

class ListCita extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'appointments';
        $data['icon'] = 'fas fa-calendar';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewCitas();
    }

    protected function createViewCitas(string $viewName = 'ListCita')
    {
        $this->addView($viewName, 'Cita', 'appointments', 'fas fa-calendar');
        $this->addOrderBy($viewName, ['fecha', 'hora_inicio'], 'date');
        $this->addSearchFields($viewName, ['motivo']);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'ListCita' && !empty($view->cursor)) {
            foreach ($view->cursor as $cita) {
                if ($cita instanceof Cita) {
                    $paciente = $cita->getPaciente();
                    $cita->paciente_nombre = $paciente ? ($paciente->getCliente()->razonsocial ?? '') : '';
                }
            }
        }
    }
}

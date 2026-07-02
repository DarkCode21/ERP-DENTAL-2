<?php
/**
 * ListArchivo
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\Dental\Model\Archivo;

class ListArchivo extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'clinical-files';
        $data['icon'] = 'fas fa-file';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewArchivos();
    }

    protected function createViewArchivos(string $viewName = 'ListArchivo')
    {
        $this->addView($viewName, 'Archivo', 'clinical-files', 'fas fa-file');
        $this->addOrderBy($viewName, ['created_at'], 'date');
        $this->addSearchFields($viewName, ['nombre_original', 'descripcion']);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'ListArchivo' && !empty($view->cursor)) {
            foreach ($view->cursor as $archivo) {
                if ($archivo instanceof Archivo) {
                    $paciente = $archivo->getPaciente();
                    $archivo->paciente_nombre = $paciente ? ($paciente->getCliente()->razonsocial ?? '') : '';
                }
            }
        }
    }
}

<?php
/**
 * EditEspecialista
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Model\EspecialistaEspecialidad;

class EditEspecialista extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'specialist';
        $data['icon'] = 'fas fa-user-md';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Especialista';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->addEditListView('EditEspecialistaEspecialidad', 'EspecialistaEspecialidad', 'specialties', 'fas fa-tooth');
    }

    protected function loadData($viewName, $view)
    {
        if ($viewName === 'EditEspecialistaEspecialidad') {
            $idespecialista = $this->request->query->get('code');
            if (!empty($idespecialista)) {
                $where = [new DataBaseWhere('idespecialista', $idespecialista)];
                $view->loadData('', $where);
            }
            return;
        }
        parent::loadData($viewName, $view);
    }

    protected function insertAction()
    {
        if ($this->active === 'EditEspecialistaEspecialidad') {
            $idespecialista = $this->request->query->get('code');
            if (!empty($idespecialista)) {
                $this->views[$this->active]->model->idespecialista = $idespecialista;
            }
        }
        return parent::insertAction();
    }
}

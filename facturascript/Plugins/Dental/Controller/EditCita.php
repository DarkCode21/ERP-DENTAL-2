<?php
/**
 * EditCita
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Plugins\Dental\Lib\SalonBookingClient;
use FacturaScripts\Plugins\Dental\Model\Cita;

class EditCita extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'appointment';
        $data['icon'] = 'fas fa-calendar';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Cita';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->loadSelectValues('EditCita');
    }

    protected function loadSelectValues(string $viewName)
    {
        $columnPatient = $this->tab($viewName)->columnForName('patient');
        if ($columnPatient && $columnPatient->widget) {
            $sql = "SELECT p.id AS code, c.razonsocial AS description "
                . "FROM dental_pacientes p INNER JOIN clientes c ON c.codcliente = p.codcliente "
                . "ORDER BY 2 ASC";
            $results = [];
            foreach ($this->dataBase->select($sql) as $row) {
                $results[] = new CodeModel($row);
            }
            $columnPatient->widget->setValuesFromCodeModel($results);
        }
    }

    protected function editAction()
    {
        $saved = parent::editAction();
        if ($saved) {
            $this->syncSalonBooking();
        }

        return $saved;
    }

    protected function insertAction()
    {
        $saved = parent::insertAction();
        if ($saved) {
            $this->syncSalonBooking();
        }

        return $saved;
    }

    private function syncSalonBooking(): void
    {
        $model = $this->views[$this->active]->model ?? null;
        if ($model instanceof Cita) {
            (new SalonBookingClient())->syncCita($model);
        }
    }
}

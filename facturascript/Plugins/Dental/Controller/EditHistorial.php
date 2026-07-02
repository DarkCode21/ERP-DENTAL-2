<?php
/**
 * EditHistorial
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\CodeModel;

class EditHistorial extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['showonmenu'] = false;
        $data['title'] = 'clinical-record';
        $data['icon'] = 'fas fa-file-medical';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'Historial';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->loadSelectValues('EditHistorial');
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
}

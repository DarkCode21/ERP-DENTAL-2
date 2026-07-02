<?php
/**
 * EditTratamientoPaciente
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\CodeModel;

class EditTratamientoPaciente extends EditController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'treatment';
        $data['icon'] = 'fas fa-tooth';
        return $data;
    }

    public function getModelClassName(): string
    {
        return 'TratamientoPaciente';
    }

    protected function createViews()
    {
        parent::createViews();
        $this->loadSelectValues('EditTratamientoPaciente');
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

<?php
/**
 * PanelDental
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\PanelController;
use FacturaScripts\Plugins\Dental\Model\Cita;
use FacturaScripts\Plugins\Dental\Model\Historial;
use FacturaScripts\Plugins\Dental\Model\Paciente;
use FacturaScripts\Plugins\Dental\Model\TratamientoPaciente;

class PanelDental extends PanelController
{
    public $citasHoy;
    public $citasPendientes;
    public $pacientesNuevosMes;
    public $totalPacientes;
    public $noAsistieron;
    public $proximasCitas;
    public $revisionesPendientes;
    public $tratamientosActivos;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'dental-panel';
        $data['icon'] = 'fas fa-smile';
        return $data;
    }

    protected function createViews()
    {
        $this->setTemplate('PanelDental');
        $this->addEditListView('EditEspecialidad', 'Especialidad', 'specialties', 'fas fa-tooth');
        $this->addEditListView('EditGabinete', 'Gabinete', 'cabinets', 'fas fa-door-medical');
        $this->addEditListView('EditEspecialista', 'Especialista', 'specialists', 'fas fa-user-md');
        $this->addHtmlView('EditPaciente', 'Dental/Paciente/List', 'Paciente', 'patients', 'fas fa-user-injured');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditEspecialidad':
            case 'EditGabinete':
            case 'EditEspecialista':
                $view->loadData('', [], ['id' => 'DESC']);
                break;
        }
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->loadStats();
    }

    protected function loadStats(): void
    {
        $today = date('Y-m-d');
        $firstDayMonth = date('Y-m-01');

        $citaModel = new Cita();
        $pacienteModel = new Paciente();
        $historialModel = new Historial();
        $tratamientoModel = new TratamientoPaciente();

        $whereCitasHoy = [new DataBaseWhere('fecha', $today)];
        $this->citasHoy = $citaModel->count($whereCitasHoy);

        $wherePendientes = [
            new DataBaseWhere('estado', 'pendiente'),
            new DataBaseWhere('confirmada_paciente', false, '=', 'AND')
        ];
        $this->citasPendientes = $citaModel->count($wherePendientes);

        $whereNuevos = [new DataBaseWhere('fecha_alta', $firstDayMonth, '>=')];
        $this->pacientesNuevosMes = $pacienteModel->count($whereNuevos);

        $this->totalPacientes = $pacienteModel->count([]);

        $whereNoAsistieron = [new DataBaseWhere('estado', 'no_asistio')];
        $this->noAsistieron = $citaModel->count($whereNoAsistieron);

        $this->proximasCitas = $citaModel->all(
            [new DataBaseWhere('fecha', $today, '>=')],
            ['fecha' => 'ASC', 'hora_inicio' => 'ASC'],
            0,
            10
        );

        $whereRevisiones = [
            new DataBaseWhere('proxima_revision', $today, '<='),
            new DataBaseWhere('proxima_revision', null, 'IS NOT')
        ];
        $this->revisionesPendientes = $historialModel->count($whereRevisiones);

        $whereActivos = [new DataBaseWhere('estado_clinico', 'en_curso')];
        $this->tratamientosActivos = $tratamientoModel->count($whereActivos);
    }
}

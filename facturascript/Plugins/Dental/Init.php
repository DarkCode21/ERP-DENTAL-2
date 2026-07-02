<?php

namespace FacturaScripts\Plugins\Dental;

use FacturaScripts\Core\Kernel;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init(): void
    {
        Kernel::addRoute('/CalendarDental', Controller\CalendarDental::class, -1, 'dental-calendar');
    }

    public function update(): void
    {
        new Model\Especialidad();
        new Model\Gabinete();
        new Model\Especialista();
        new Model\EspecialistaEspecialidad();
        new Model\Paciente();
        new Model\Cita();
        new Model\Historial();
        new Model\Archivo();
        new Model\TratamientoPaciente();
        new Model\BloqueoAgenda();
    }

    public function uninstall(): void
    {
    }
}

<?php

namespace FacturaScripts\Plugins\Dental;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init(): void
    {
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

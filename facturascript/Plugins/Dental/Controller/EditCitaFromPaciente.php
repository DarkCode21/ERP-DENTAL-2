<?php
/**
 * EditCitaFromPaciente - Cita desde EditPaciente (paciente hidden)
 */
namespace FacturaScripts\Plugins\Dental\Controller;

class EditCitaFromPaciente extends EditCita
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'appointment';
        return $data;
    }
}

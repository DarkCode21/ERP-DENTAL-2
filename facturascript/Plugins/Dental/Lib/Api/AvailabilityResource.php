<?php
/**
 * AvailabilityResource
 * 
 * API para consultar disponibilidad de citas
 */

namespace FacturaScripts\Plugins\Dental\Lib\Api;

use FacturaScripts\Core\Lib\API\Base\APIResourceClass;
use FacturaScripts\Plugins\Dental\Lib\DentalAvailability;
use Symfony\Component\HttpFoundation\Response;

class AvailabilityResource extends APIResourceClass
{
    public function getResources(): array
    {
        return [
            'availability-slots' => $this->setResource('availability-slots'),
            'check-disponibility' => $this->setResource('check-disponibility'),
        ];
    }

    public function doGET(): bool
    {
        if (empty($this->params) || $this->params[0] === 'availability-slots') {
            return $this->getAvailableSlots();
        }

        if ($this->params[0] === 'check-disponibility') {
            return $this->checkDisponibility();
        }

        $this->setError('Resource not found', null, Response::HTTP_NOT_FOUND);
        return false;
    }

    protected function getAvailableSlots(): bool
    {
        $idespecialista = $this->request->request->get('idespecialista');
        $fecha = $this->request->request->get('fecha');
        $duracion = (int)$this->request->request->get('duracion', 30);
        $idgabinete = $this->request->request->get('idgabinete');

        if (empty($idespecialista) || empty($fecha)) {
            $this->setError('Missing required parameters: idespecialista, fecha');
            return false;
        }

        $slots = DentalAvailability::getAvailableSlots(
            (int)$idespecialista,
            $fecha,
            $duracion,
            $idgabinete ? (int)$idgabinete : null
        );

        $this->returnResult($slots);
        return true;
    }

    protected function checkDisponibility(): bool
    {
        $idespecialista = $this->request->request->get('idespecialista');
        $idgabinete = $this->request->request->get('idgabinete');
        $fecha = $this->request->request->get('fecha');
        $horaInicio = $this->request->request->get('hora_inicio');
        $horaFin = $this->request->request->get('hora_fin');
        $excludeCitaId = $this->request->request->get('exclude_cita_id');

        if (empty($fecha) || empty($horaInicio) || empty($horaFin)) {
            $this->setError('Missing required parameters');
            return false;
        }

        $result = [
            'especialista_disponible' => true,
            'gabinete_disponible' => true,
        ];

        if (!empty($idespecialista)) {
            $result['especialista_disponible'] = DentalAvailability::isEspecialistaAvailable(
                (int)$idespecialista,
                $fecha,
                $horaInicio,
                $horaFin,
                $excludeCitaId ? (int)$excludeCitaId : null
            );
        }

        if (!empty($idgabinete)) {
            $result['gabinete_disponible'] = DentalAvailability::isGabineteAvailable(
                (int)$idgabinete,
                $fecha,
                $horaInicio,
                $horaFin,
                $excludeCitaId ? (int)$excludeCitaId : null
            );
        }

        $this->returnResult($result);
        return true;
    }
}

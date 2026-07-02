<?php

/**
 * CitasResource
 * 
 * API para gestionar citas dentales
 */

namespace FacturaScripts\Plugins\Dental\Lib\Api;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\API\Base\APIResourceClass;
use FacturaScripts\Plugins\Dental\Model\Cita;
use Symfony\Component\HttpFoundation\Response;

class CitasResource extends APIResourceClass
{
    public function getResources(): array
    {
        return [
            'citas' => $this->setResource('citas'),
        ];
    }

    public function doGET(): bool
    {
        return $this->listCitas();
    }

    public function doPOST(): bool
    {
        return $this->createCita();
    }

    public function doPUT(): bool
    {
        return $this->updateCita();
    }

    public function doDELETE(): bool
    {
        return $this->cancelCita();
    }

    protected function listCitas(): bool
    {
        $fecha = $this->request->request->get('fecha');
        $idespecialista = $this->request->request->get('idespecialista');
        $idpaciente = $this->request->request->get('idpaciente');
        $estado = $this->request->request->get('estado');

        $where = [];
        if (!empty($fecha)) {
            $where[] = new DataBaseWhere('fecha', $fecha);
        }
        if (!empty($idespecialista)) {
            $where[] = new DataBaseWhere('idespecialista', $idespecialista);
        }
        if (!empty($idpaciente)) {
            $where[] = new DataBaseWhere('idpaciente', $idpaciente);
        }
        if (!empty($estado)) {
            $where[] = new DataBaseWhere('estado', $estado);
        }

        $citaModel = new Cita();
        $citas = $citaModel->all($where, ['fecha' => 'DESC', 'hora_inicio' => 'DESC']);

        $data = [];
        foreach ($citas as $cita) {
            $data[] = [
                'id' => $cita->id,
                'idpaciente' => $cita->idpaciente,
                'idespecialista' => $cita->idespecialista,
                'idgabinete' => $cita->idgabinete,
                'fecha' => $cita->fecha,
                'hora_inicio' => $cita->hora_inicio,
                'hora_fin' => $cita->hora_fin,
                'duracion' => $cita->duracion,
                'estado' => $cita->estado,
                'motivo' => $cita->motivo,
            ];
        }

        $this->returnResult($data);
        return true;
    }

    protected function createCita(): bool
    {
        $values = $this->request->request->all();
        if (empty($values)) {
            $this->setError('No data received');
            return false;
        }

        $cita = new Cita();
        foreach ($values as $key => $value) {
            if (property_exists($cita, $key)) {
                $cita->{$key} = $value;
            }
        }

        if ($cita->save()) {
            $this->setOk('Cita created', ['id' => $cita->id]);
            return true;
        }

        $this->setError('Failed to create cita');
        return false;
    }

    protected function updateCita(): bool
    {
        $id = $this->request->request->get('id');
        if (empty($id)) {
            $this->setError('Missing id parameter');
            return false;
        }

        $cita = new Cita();
        if (!$cita->loadFromCode($id)) {
            $this->setError('Cita not found', null, Response::HTTP_NOT_FOUND);
            return false;
        }

        $values = $this->request->request->all();
        foreach ($values as $key => $value) {
            if (property_exists($cita, $key)) {
                $cita->{$key} = $value;
            }
        }

        if ($cita->save()) {
            $this->setOk('Cita updated', ['id' => $cita->id]);
            return true;
        }

        $this->setError('Failed to update cita');
        return false;
    }

    protected function cancelCita(): bool
    {
        $id = $this->request->request->get('id');
        if (empty($id)) {
            $this->setError('Missing id parameter');
            return false;
        }

        $cita = new Cita();
        if (!$cita->loadFromCode($id)) {
            $this->setError('Cita not found', null, Response::HTTP_NOT_FOUND);
            return false;
        }

        $cita->estado = 'cancelada';

        if ($cita->save()) {
            $this->setOk('Cita cancelled', ['id' => $cita->id]);
            return true;
        }

        $this->setError('Failed to cancel cita');
        return false;
    }
}

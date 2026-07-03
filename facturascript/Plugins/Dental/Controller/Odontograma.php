<?php

namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Plugins\Dental\Model\Odontograma as OdontogramaModel;
use FacturaScripts\Plugins\Dental\Model\Paciente as PacienteModel;

class Odontograma extends ApiController
{
    protected function runResource(): void
    {
        $action = $this->getUriParam(3) ?: $this->request->request->get('action', '');

        switch ($this->request->getMethod()) {
            case 'GET':
                $this->getOdontograma();
                break;
            case 'POST':
                if ($action === 'list') {
                    $this->listPacientes();
                } else {
                    $this->saveOdontograma();
                }
                break;
            default:
                $this->response->setContent(json_encode(['error' => 'Method not allowed']));
                $this->response->setStatusCode(405);
        }
    }

    private function getOdontograma(): void
    {
        $idpaciente = $this->request->query->get('idpaciente', '');
        if (empty($idpaciente)) {
            $idpaciente = $this->getUriParam(3) ?: '';
        }

        if (empty($idpaciente)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Falta paciente']));
            $this->response->setStatusCode(400);
            return;
        }

        $where = [new DataBaseWhere('idpaciente', $idpaciente)];
        $odontogramaModel = new OdontogramaModel();
        $odontogramas = $odontogramaModel->all($where, [], 0, 1);

        if (!empty($odontogramas)) {
            $this->response->setContent(json_encode([
                'ok' => true,
                'id' => $odontogramas[0]->id,
                'datos' => $odontogramas[0]->datos
            ]));
        } else {
            $this->response->setContent(json_encode([
                'ok' => true,
                'id' => null,
                'datos' => '{}'
            ]));
        }
    }

    private function saveOdontograma(): void
    {
        $idpaciente = $this->getUriParam(3);

        if (empty($idpaciente)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => 'Falta paciente']));
            $this->response->setStatusCode(400);
            return;
        }

        $datos = $this->request->request->get('datos', '{}');

        $where = [new DataBaseWhere('idpaciente', $idpaciente)];
        $odontogramaModel = new OdontogramaModel();
        $odontogramas = $odontogramaModel->all($where, [], 0, 1);

        if (!empty($odontogramas)) {
            $odontogramaModel = $odontogramas[0];
        }

        $odontogramaModel->idpaciente = $idpaciente;
        $odontogramaModel->datos = $datos;

        if (false === $odontogramaModel->test()) {
            $this->response->setContent(json_encode([
                'ok' => false,
                'error' => 'Test failed'
            ]));
            $this->response->setStatusCode(500);
            return;
        }

        if (false === $odontogramaModel->save()) {
            $this->response->setContent(json_encode([
                'ok' => false,
                'error' => 'Save failed'
            ]));
            $this->response->setStatusCode(500);
            return;
        }

        $this->response->setContent(json_encode([
            'ok' => true,
            'id' => $odontogramaModel->id,
            'isNew' => empty($odontogramas)
        ]));
    }

    private function listPacientes(): void
    {
        $pacienteModel = new PacienteModel();
        $pacientes = $pacienteModel->all([], ['fecha_alta' => 'DESC']);

        $data = [];
        foreach ($pacientes as $paciente) {
            $cliente = $paciente->getCliente();
            $data[] = [
                'id' => $paciente->id,
                'razonsocial' => $cliente ? $cliente->razonsocial : ''
            ];
        }

        $this->response->setContent(json_encode(['ok' => true, 'data' => $data]));
    }
}

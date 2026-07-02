<?php

namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Cliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Dental\Model\Paciente as PacienteModel;

class Paciente extends Controller
{
    /** @var PacienteModel */
    public $paciente;
    public $pacientes = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'patient';
        $data['icon'] = 'fas fa-user-injured';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');
        $ajax = $this->request->query->get('ajax', '');

        if ($ajax === 'true') {
            $this->setTemplate(false);
            $this->response->setContent(json_encode($this->searchClientes()));
            return;
        }

        if ($action === 'save') {
            $this->savePaciente();
        }

        $model = new PacienteModel();
        $this->pacientes = $model->all([], ['fecha_alta' => 'DESC']);

        $editCode = $this->request->query->get('code', '');
        if (!empty($editCode)) {
            $this->paciente = new PacienteModel();
            $this->paciente->loadFromCode($editCode);
            $this->setTemplate('EditPaciente');
        } else {
            $this->setTemplate('Paciente');
        }
    }

    private function searchClientes(): array
    {
        $term = $this->request->query->get('term', '');
        if (strlen($term) < 2) {
            return ['results' => []];
        }

        $cliente = new Cliente();
        $where = [
            new DataBaseWhere('razonsocial', '%' . $term . '%', 'LIKE'),
        ];
        $results = $cliente->all($where, ['razonsocial' => 'ASC'], 0, 50);

        $data = [];
        foreach ($results as $cli) {
            $data[] = [
                'id' => $cli->codcliente,
                'text' => $cli->razonsocial . ' (' . $cli->codcliente . ')'
            ];
        }

        if (empty($data)) {
            $data[] = ['id' => '', 'text' => 'No encontrado'];
        }

        return ['results' => $data];
    }

    private function savePaciente(): void
    {
        $codcliente = $this->request->request->get('codcliente', '');
        if (empty($codcliente)) {
            Tools::log()->warning('customer-required');
            return;
        }

        $this->paciente = new PacienteModel();
        $this->paciente->codcliente = $codcliente;
        $this->paciente->alergias = $this->request->request->get('alergias', '');
        $this->paciente->medicacion = $this->request->request->get('medicacion', '');
        $this->paciente->antecedentes_medicos = $this->request->request->get('antecedentes_medicos', '');
        $this->paciente->antecedentes_odontologicos = $this->request->request->get('antecedentes_odontologicos', '');
        $this->paciente->aseguradora = $this->request->request->get('aseguradora', '');
        $this->paciente->numero_poliza = $this->request->request->get('numero_poliza', '');
        $this->paciente->estado = 'activo';

        if (!$this->paciente->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$this->paciente->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }
}

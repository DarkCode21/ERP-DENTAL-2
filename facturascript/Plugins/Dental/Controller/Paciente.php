<?php

namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Model\Cliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Dental\Model\Paciente as PacienteModel;
use FacturaScripts\Plugins\Dental\Model\TratamientoPaciente;
use FacturaScripts\Plugins\Dental\Model\Cita;
use FacturaScripts\Plugins\Dental\Model\Especialista;
use FacturaScripts\Plugins\Dental\Model\Gabinete;
use FacturaScripts\Plugins\Dental\Model\Historial;

class Paciente extends Controller
{
    /** @var PacienteModel */
    public $paciente;
    public $pacientes = [];
    public $especialistas = [];
    public $gabientes = [];
    public $tratamientos = [];
    public $historiales = [];

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
        } elseif ($action === 'save-salon-customer') {
            $this->saveSalonCustomerId();
        } elseif ($action === 'save-tratamiento') {
            $this->saveTratamiento();
        } elseif ($action === 'save-cita') {
            $this->saveCita();
        } elseif ($action === 'save-historial') {
            $this->saveHistorial();
        } elseif ($action === 'update-historial') {
            $this->updateHistorial();
        } elseif ($action === 'update-tratamiento') {
            $this->updateTratamiento();
        } elseif ($action === 'update-cita') {
            $this->updateCita();
        } elseif ($action === 'generar-factura') {
            $this->generarFactura();
        }

        $model = new PacienteModel();
        $this->pacientes = $model->all([], ['fecha_alta' => 'DESC']);

        $editCode = $this->request->query->get('code', '');
        if (!empty($editCode)) {
            $this->paciente = new PacienteModel();
            $this->paciente->loadFromCode($editCode);

            $espModel = new Especialista();
            $this->especialistas = $espModel->all([], ['nombre' => 'ASC']);

            $gabModel = new Gabinete();
            $this->gabientes = $gabModel->all([], ['nombre' => 'ASC']);

            $where = [new DataBaseWhere('idpaciente', $editCode)];
            $tratModel = new TratamientoPaciente();
            $this->tratamientos = $tratModel->all($where, ['fecha_inicio' => 'DESC']);

            $histModel = new Historial();
            $this->historiales = $histModel->all($where, ['fecha' => 'DESC']);

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
        $this->paciente->salon_customer_id = (int)$this->request->request->get('salon_customer_id', 0) ?: null;
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

    private function saveSalonCustomerId(): void
    {
        $id = (int)$this->request->request->get('id', 0);
        $paciente = new PacienteModel();
        if ($id < 1 || false === $paciente->loadFromCode($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $paciente->salon_customer_id = (int)$this->request->request->get('salon_customer_id', 0) ?: null;
        if ($paciente->save()) {
            Tools::log()->notice('record-saved-correctly');
            return;
        }

        Tools::log()->error('record-save-error');
    }

    private function saveTratamiento(): void
    {
        $idpaciente = $this->request->request->get('idpaciente', '');
        if (empty($idpaciente)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $tratamiento = new TratamientoPaciente();
        $tratamiento->idpaciente = $idpaciente;
        $tratamiento->referencia_servicio = $this->request->request->get('referencia_servicio', '');
        $tratamiento->precio = (float)$this->request->request->get('precio', 0);
        $tratamiento->descuento = (float)$this->request->request->get('descuento', 0);
        $tratamiento->estado_clinico = $this->request->request->get('estado_clinico', 'propuesto');
        $tratamiento->estado_economico = $this->request->request->get('estado_economico', 'pendiente');
        $tratamiento->observaciones = $this->request->request->get('observaciones', '');
        $tratamiento->fecha_inicio = $this->request->request->get('fecha_inicio', date('Y-m-d')) ?: date('Y-m-d');
        $tratamiento->fecha_fin = $this->request->request->get('fecha_fin', '') ?: null;

        if (!$tratamiento->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$tratamiento->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function saveCita(): void
    {
        $idpaciente = $this->request->request->get('idpaciente', '');
        if (empty($idpaciente)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $cita = new Cita();
        $cita->idpaciente = $idpaciente;
        $cita->idespecialista = $this->request->request->get('idespecialista', '');
        $cita->idgabinete = $this->request->request->get('idgabinete', '');
        $cita->idtratamiento = $this->request->request->get('idtratamiento', '') ?: null;
        $cita->fecha = $this->request->request->get('fecha', date('d-m-Y'));
        $cita->hora_inicio = $this->request->request->get('hora_inicio', '09:00');
        $cita->hora_fin = $this->request->request->get('hora_fin', '09:30');
        $cita->duracion = (int)$this->request->request->get('duracion', 30);
        $cita->motivo = $this->request->request->get('motivo', '');
        $cita->estado = $this->request->request->get('estado', 'pendiente');
        $cita->observaciones = $this->request->request->get('observaciones', '');

        if (!$cita->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$cita->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function saveHistorial(): void
    {
        $idpaciente = $this->request->request->get('idpaciente', '');
        if (empty($idpaciente)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $historial = new Historial();
        $historial->idpaciente = $idpaciente;
        $historial->fecha = $this->request->request->get('fecha', date('d-m-Y'));
        $historial->tipo = $this->request->request->get('tipo', 'nota');
        $historial->motivo_consulta = $this->request->request->get('motivo_consulta', '');
        $historial->diagnostico = $this->request->request->get('diagnostico', '');
        $historial->tratamiento_recomendado = $this->request->request->get('tratamiento_recomendado', '');
        $historial->tratamiento_realizado = $this->request->request->get('tratamiento_realizado', '');
        $historial->medicacion_prescrita = $this->request->request->get('medicacion_prescrita', '');
        $historial->observaciones_clinicas = $this->request->request->get('observaciones_clinicas', '');
        $historial->proxima_revision = $this->request->request->get('proxima_revision', '') ?: null;
        $historial->estado = 'activo';

        if (!$historial->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$historial->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function updateHistorial(): void
    {
        $id = $this->request->request->get('id', '');
        if (empty($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $historial = new Historial();
        if (!$historial->loadFromCode($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $historial->fecha = $this->request->request->get('fecha', $historial->fecha);
        $historial->tipo = $this->request->request->get('tipo', $historial->tipo);
        $historial->motivo_consulta = $this->request->request->get('motivo_consulta', '');
        $historial->diagnostico = $this->request->request->get('diagnostico', '');
        $historial->tratamiento_recomendado = $this->request->request->get('tratamiento_recomendado', '');
        $historial->tratamiento_realizado = $this->request->request->get('tratamiento_realizado', '');
        $historial->medicacion_prescrita = $this->request->request->get('medicacion_prescrita', '');
        $historial->observaciones_clinicas = $this->request->request->get('observaciones_clinicas', '');
        $historial->proxima_revision = $this->request->request->get('proxima_revision', '') ?: null;

        if (!$historial->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$historial->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function updateTratamiento(): void
    {
        $id = $this->request->request->get('id', '');
        if (empty($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $tratamiento = new TratamientoPaciente();
        if (!$tratamiento->loadFromCode($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $tratamiento->referencia_servicio = $this->request->request->get('referencia_servicio', $tratamiento->referencia_servicio);
        $tratamiento->precio = (float)$this->request->request->get('precio', $tratamiento->precio);
        $tratamiento->descuento = (float)$this->request->request->get('descuento', $tratamiento->descuento);
        $tratamiento->estado_clinico = $this->request->request->get('estado_clinico', $tratamiento->estado_clinico);
        $tratamiento->estado_economico = $this->request->request->get('estado_economico', $tratamiento->estado_economico);
        $tratamiento->observaciones = $this->request->request->get('observaciones', '');
        $tratamiento->fecha_inicio = $this->request->request->get('fecha_inicio', $tratamiento->fecha_inicio) ?: $tratamiento->fecha_inicio;
        $tratamiento->fecha_fin = $this->request->request->get('fecha_fin', '') ?: null;

        if (!$tratamiento->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$tratamiento->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function updateCita(): void
    {
        $id = $this->request->request->get('id', '');
        if (empty($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $cita = new Cita();
        if (!$cita->loadFromCode($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $cita->idespecialista = $this->request->request->get('idespecialista', $cita->idespecialista);
        $cita->idgabinete = $this->request->request->get('idgabinete', $cita->idgabinete);
        $cita->idtratamiento = $this->request->request->get('idtratamiento', $cita->idtratamiento) ?: null;
        $cita->fecha = $this->request->request->get('fecha', $cita->fecha);
        $cita->hora_inicio = $this->request->request->get('hora_inicio', $cita->hora_inicio);
        $cita->hora_fin = $this->request->request->get('hora_fin', $cita->hora_fin);
        $cita->duracion = (int)$this->request->request->get('duracion', $cita->duracion);
        $cita->motivo = $this->request->request->get('motivo', $cita->motivo);
        $cita->estado = $this->request->request->get('estado', $cita->estado);
        $cita->observaciones = $this->request->request->get('observaciones', '');

        if (!$cita->test()) {
            Tools::log()->error('record-save-error');
            return;
        }

        if (!$cita->save()) {
            Tools::log()->error('record-save-error');
            return;
        }

        Tools::log()->notice('record-saved-correctly');
    }

    private function generarFactura(): void
    {
        $id = $this->request->request->get('id', '');
        if (empty($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $tratamiento = new TratamientoPaciente();
        if (!$tratamiento->loadFromCode($id)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        if (!empty($tratamiento->idfactura)) {
            Tools::log()->warning('factura-ya-existe');
            return;
        }

        $paciente = $tratamiento->getPaciente();
        if (!$paciente) {
            Tools::log()->error('record-not-found');
            return;
        }

        $cliente = $paciente->getCliente();
        if (!$cliente) {
            Tools::log()->error('cliente-no-encontrado');
            return;
        }

        $db = new DataBase();
        $db->beginTransaction();

        $factura = new FacturaCliente();
        $factura->setSubject($cliente);
        $factura->fecha = date('Y-m-d');

        if (!$factura->save()) {
            $db->rollback();
            Tools::log()->error('record-save-error');
            return;
        }

        $linea = $factura->getNewLine();
        $linea->descripcion = $tratamiento->referencia_servicio;
        $linea->pvpunitario = $tratamiento->precio;
        $linea->dtopor = $tratamiento->descuento;
        $linea->cantidad = 1;

        if (!empty($tratamiento->observaciones)) {
            $linea->descripcion .= "\n" . $tratamiento->observaciones;
        }

        if (!$linea->save()) {
            $db->rollback();
            Tools::log()->error('record-save-error');
            return;
        }

        $tratamiento->idfactura = $factura->idfactura;
        if (!$tratamiento->save()) {
            $db->rollback();
            Tools::log()->error('record-save-error');
            return;
        }

        $db->commit();

        Tools::log()->notice('record-saved-correctly');

        $this->response->headers->set('Refresh', '0; url=EditFacturaCliente?code=' . $factura->idfactura);
    }
}

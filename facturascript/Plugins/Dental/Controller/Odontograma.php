<?php

/**
 * Odontograma
 */

namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ExtensionsTrait;
use FacturaScripts\Plugins\Dental\Model\Odontograma as OdontogramaModel;

class Odontograma extends Controller
{
    use ExtensionsTrait;

    public $idpaciente;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Odontograma';
        $pageData['menu'] = 'dental';
        $pageData['icon'] = 'fas fa-tooth';
        return $pageData;
    }

    protected function loadData($viewName, $view) {}

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');
        if ($action === 'save') {
            $this->saveOdontograma();
            return;
        }
        if ($action === 'list') {
            $this->listPacientes();
            return;
        }
        if ($action === 'get') {
            $this->getOdontograma();
            return;
        }

        $idpaciente = $this->request->query->get('idpaciente', '');
        if (empty($idpaciente)) {
            $idpaciente = $this->request->query->get('code', '');
        }
        $this->idpaciente = $idpaciente;
    }

    protected function createViews()
    {
        $idpaciente = $this->request->query->get('idpaciente', '');
        if (empty($idpaciente)) {
            $idpaciente = $this->request->query->get('code', '');
        }
        $this->idpaciente = $idpaciente;
        $this->addHtmlView('Odontograma', 'Odontograma', 'Paciente', 'dental', 'fas fa-tooth');
    }

    private function noCache(): void
    {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function getOdontograma(): void
    {
        $this->noCache();
        $idpaciente = $this->request->query->get('idpaciente', '');
        if (empty($idpaciente)) {
            $idpaciente = $this->request->query->get('code', '');
        }

        if (empty($idpaciente)) {
            echo json_encode(["ok" => false, "error" => "Falta paciente"]);
            exit;
        }

        $sql = "SELECT id, datos FROM dental_odontogramas WHERE idpaciente = " . intval($idpaciente);
        $rows = $this->dataBase->select($sql);

        if (!empty($rows) && isset($rows[0]['datos'])) {
            echo json_encode(["ok" => true, "id" => $rows[0]['id'], "datos" => $rows[0]['datos']]);
        } else {
            echo json_encode(["ok" => true, "id" => null, "datos" => "{}"]);
        }
        exit;
    }

    private function saveOdontograma(): void
    {
        $this->noCache();
        $idpaciente = $this->request->request->get('idpaciente', '');
        if (empty($idpaciente)) {
            $idpaciente = $this->request->request->get('code', '');
        }
        $datos = $this->request->request->get('datos', '{}');

        if (empty($idpaciente)) {
            echo json_encode(["ok" => false, "error" => "Falta paciente"]);
            exit;
        }

        $sql = "SELECT id FROM dental_odontogramas WHERE idpaciente = " . intval($idpaciente) . " LIMIT 1";
        $rows = $this->dataBase->select($sql);

        $odontograma = new OdontogramaModel();
        $isNew = true;
        if (!empty($rows) && isset($rows[0]['id'])) {
            $odontograma->loadFromCode($rows[0]['id']);
            $isNew = false;
        }

        $odontograma->idpaciente = $idpaciente;
        $odontograma->datos = $datos;

        if (false === $odontograma->test()) {
            echo json_encode(["ok" => false, "error" => "Test failed", "debug" => ["idpaciente" => $odontograma->idpaciente, "datosLen" => strlen($odontograma->datos)]]);
            exit;
        }

        if (false === $odontograma->save()) {
            echo json_encode(["ok" => false, "error" => "Save failed", "isNew" => $isNew]);
            exit;
        }

        echo json_encode(["ok" => true, "id" => $odontograma->id, "isNew" => $isNew]);
        exit;
    }

    private function listPacientes(): void
    {
        $this->noCache();
        $sql = "SELECT p.id, c.razonsocial "
            . "FROM dental_pacientes p "
            . "INNER JOIN clientes c ON c.codcliente = p.codcliente "
            . "ORDER BY c.razonsocial ASC";
        $pacientes = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $pacientes[] = [
                'id' => $row['id'],
                'razonsocial' => $row['razonsocial']
            ];
        }
        echo json_encode(['ok' => true, 'data' => $pacientes]);
        exit;
    }
}

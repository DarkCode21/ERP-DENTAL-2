<?php
/**
 * EditArchivo
 */
namespace FacturaScripts\Plugins\Dental\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\PanelController;
use FacturaScripts\Core\Model\AttachedFile;
use FacturaScripts\Core\Model\AttachedFileRelation;
use FacturaScripts\Plugins\Dental\Model\Archivo;
use FacturaScripts\Core\Tools;

class EditArchivo extends PanelController
{
    public $files = [];
    public $patients = [];
    public $specialists = [];
    public $appointments = [];
    public $treatments = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'dental';
        $data['title'] = 'clinical-file';
        $data['icon'] = 'fas fa-file';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        $this->addHtmlView('EditArchivo', 'Tab/ArchivoEdit', 'Archivo', 'files', 'fas fa-paperclip');
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'save') {
            return $this->saveAction();
        }
        if ($action === 'delete-file') {
            return $this->deleteFileAction();
        }
        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        $code = $this->request->query->get('code', '');
        $view->loadData($code);

        $this->loadPatients();
        $this->loadSpecialists();
        $this->loadAppointments();
        $this->loadTreatments();

        if (!empty($code)) {
            $this->loadFiles($code);
        }
    }

    private function saveAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        }

        $model = new Archivo();
        $code = $this->request->request->get('code', '');
        if (!empty($code)) {
            $model->loadFromCode($code);
        }

        $model->idpaciente = $this->request->request->get('idpaciente');
        $model->idespecialista = $this->request->request->get('idespecialista') ?: null;
        $model->idcita = $this->request->request->get('idcita') ?: null;
        $model->idtratamiento = $this->request->request->get('idtratamiento') ?: null;
        $model->categoria = $this->request->request->get('categoria');
        $model->descripcion = $this->request->request->get('descripcion', '');
        $model->estado = $this->request->request->get('estado', 'activo');

        if (false === $model->test()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        if (false === $model->save()) {
            Tools::log()->error('record-save-error');
            return true;
        }

        $files = $this->request->files->get('newfiles', []);
        if (!empty($files)) {
            foreach ($files as $uploadFile) {
                if ($uploadFile->isValid()) {
                    $uploadFile->move(FS_FOLDER . '/MyFiles', $uploadFile->getClientOriginalName());

                    $newFile = new AttachedFile();
                    $newFile->path = $uploadFile->getClientOriginalName();
                    if ($newFile->save()) {
                        $fileRelation = new AttachedFileRelation();
                        $fileRelation->idfile = $newFile->idfile;
                        $fileRelation->model = 'Archivo';
                        $fileRelation->modelid = $model->id;
                        $fileRelation->nick = $this->user->nick;
                        $fileRelation->save();
                    }
                }
            }
        }

        Tools::log()->notice('record-saved-correctly');
        $this->redirect($model->url());
        return true;
    }

    private function deleteFileAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return true;
        }

        $id = $this->request->request->get('id');
        $fileRelation = new AttachedFileRelation();
        if ($fileRelation->loadFromCode($id)) {
            $file = $fileRelation->getFile();
            $fileRelation->delete();
            $file->delete();
        }

        Tools::log()->notice('record-deleted-correctly');
        return true;
    }

    private function loadPatients()
    {
        $sql = "SELECT p.id, c.razonsocial "
            . "FROM dental_pacientes p INNER JOIN clientes c ON c.codcliente = p.codcliente "
            . "ORDER BY 2 ASC";
        foreach ($this->dataBase->select($sql) as $row) {
            $this->patients[] = $row;
        }
    }

    private function loadSpecialists()
    {
        $sql = "SELECT id, nombre FROM dental_especialistas ORDER BY nombre ASC";
        foreach ($this->dataBase->select($sql) as $row) {
            $this->specialists[] = $row;
        }
    }

    private function loadAppointments()
    {
        $sql = "SELECT id, fecha, idpaciente, idespecialista FROM dental_citas ORDER BY fecha DESC";
        foreach ($this->dataBase->select($sql) as $row) {
            $this->appointments[] = $row;
        }
    }

    private function loadTreatments()
    {
        $sql = "SELECT id, referencia_servicio, idpaciente FROM dental_tratamientos_paciente ORDER BY referencia_servicio ASC";
        foreach ($this->dataBase->select($sql) as $row) {
            $this->treatments[] = $row;
        }
    }

    private function loadFiles($modelid)
    {
        $fileRelation = new AttachedFileRelation();
        $where = [
            new DataBaseWhere('model', 'Archivo'),
            new DataBaseWhere('modelid', $modelid)
        ];
        $this->files = $fileRelation->all($where, ['creationdate' => 'DESC'], 0, 0);
    }
}

<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeDocument;

/**
 * Auxiliar Method for documents of the employee.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
trait EmployeeFilesTrait
{

    abstract protected function addHtmlView(string $viewName, string $fileName, string $modelName, string $viewTitle, string $viewIcon = 'fab fa-html5');

    abstract public static function toolBox();

    /**
     *
     * @param string $action
     * @return bool
     */
    protected function execFileAction($action): bool
    {
        switch ($action) {
            case 'add-file':
                return $this->addFileAction();

            case 'delete-file':
                return $this->deleteFileAction();

            case 'edit-file':
                return $this->editFileAction();
        }
        return false;
    }

    /**
     *
     * @return bool
     */
    private function addFileAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        }

        if ($this->multiRequestProtection->tokenExist($this->request->request->get('multireqtoken', ''))) {
            Tools::log()->warning('duplicated-request');
            return true;
        }

        $this->dataBase->beginTransaction();
        try {
            $idEmployeeDoc = $this->createEmployeeDoc();
            $this->createFilesAttached($idEmployeeDoc);
            $this->dataBase->commit();
            Tools::log()->notice('record-updated-correctly');
        } catch (Exception $ex) {
            Tools::log()->warning('fail');
            Tools::log()->error($ex->getMessage());
            $this->dataBase->rollback();
        }
        return true;
    }

    /**
     *
     * @param int $idEmployeeDoc
     * @throws Exception
     */
    private function createFilesAttached(int $idEmployeeDoc)
    {
        $uploadFiles = $this->request->files->get('new-files', []);
        foreach ($uploadFiles as $uploadFile) {
            if ($uploadFile && $uploadFile->move(\FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles', $uploadFile->getClientOriginalName())) {
                $newFile = new AttachedFile();
                $newFile->path = $uploadFile->getClientOriginalName();
                if (false === $newFile->save()) {
                    throw new Exception();
                }
            }
            $this->createFileRelation($idEmployeeDoc, $newFile->idfile);
        }
    }

    /**
     *
     * @return int
     * @throws Exception
     */
    private function createEmployeeDoc(): int
    {
        $employeeDoc = new EmployeeDocument();
        $employeeDoc->idemployee = $this->request->query->get('code');
        $employeeDoc->iddoctype = $this->request->request->get('iddoctype');
        $employeeDoc->year_group = $this->request->request->get('year_group', date('Y'));
        $employeeDoc->downloadable = $this->request->request->get('downloadable', 0);
        $employeeDoc->expires = $this->request->request->get('expires');
        $employeeDoc->note = $this->request->request->get('note');
        if (false === $employeeDoc->save()) {
            throw new Exception();
        }
        return $employeeDoc->id;
    }

    /**
     *
     * @param int $idEmployeeDoc
     * @param int $idfile
     * @throws Exception
     */
    private function createFileRelation(int $idEmployeeDoc, int $idfile)
    {
        $fileRelation = new AttachedFileRelation();
        $fileRelation->idfile = $idfile;
        $fileRelation->model = 'EmployeeDocument';
        $fileRelation->modelid = $idEmployeeDoc;
        $fileRelation->nick = $this->user->nick;
        if (false === $fileRelation->save()) {
            throw new Exception();
        }
    }

    /**
     *
     * @param string $viewName
     */
    private function createViewEmployeeFiles(string $viewName = 'EmployeeFiles')
    {
        $this->addHtmlView($viewName, 'Tab/EmployeeFiles', 'Join\EmployeeDocument', 'files', 'fa-solid fa-paperclip');
        AssetManager::add('js', \FS_ROUTE . '/Dinamic/Assets/JS/WidgetAutocomplete.js');
    }

    /**
     *
     * @return bool
     */
    private function deleteFileAction(): bool
    {
        if (false === $this->permissions->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return true;
        }

        $id = $this->request->request->get('id');
        $employeeDoc = new EmployeeDocument();
        if (false === $employeeDoc->loadFromCode($id)) {
            return true;
        }

        $fileRelation = $employeeDoc->getFile();
        $file = $fileRelation->getFile();
        $this->dataBase->beginTransaction();
        try {
            if ($fileRelation->delete() &&
                $file->delete() &&
                $employeeDoc->delete())
            {
                $this->dataBase->commit();
                Tools::log()->notice('record-deleted-correctly');
            }
        } finally {
            if ($this->dataBase->inTransaction()) {
                Tools::log()->warning('fail');
                Tools::log()->error($ex->getMessage());
                $this->dataBase->rollback();
            }
        }
        return true;
    }

    /**
     *
     * @return bool
     */
    private function editFileAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        }

        if ($this->multiRequestProtection->tokenExist($this->request->request->get('multireqtoken', ''))) {
            Tools::log()->warning('duplicated-request');
            return true;
        }

        $id = $this->request->request->get('id');
        $employeeDoc = new EmployeeDocument();
        if (false === $employeeDoc->loadFromCode($id)) {
            return true;
        }

        $employeeDoc->iddoctype = $this->request->request->get('iddoctype');
        $employeeDoc->year_group = $this->request->request->get('year_group', date('Y'));
        $employeeDoc->downloadable = $this->request->request->get('downloadable', 0);
        $employeeDoc->expires = $this->request->request->get('expires');
        $employeeDoc->note = $this->request->request->get('note');
        if ($employeeDoc->save()) {
            Tools::log()->notice('record-updated-correctly');
        }
        return true;
    }

    /**
     *
     * @param BaseView $view
     * @param int $idemployee
     */
    private function loadDataEmployeeFiles($view, int $idemployee)
    {
        $where = [ new DataBaseWhere('doc.idemployee', $idemployee) ];
        $view->loadData('', $where, ['rel.creationdate' => 'DESC']);
    }
}

<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Join;

use FacturaScripts\Core\Base\MyFilesToken;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\Base\JoinModel;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeDocument as EmployeeDocModel;

/**
 * List of Employee docs and types
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EmployeeDocument extends JoinModel
{

    /**
     * Constructor and class initializer.
     *
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        parent::__construct($data);
        $this->setMasterModel(new EmployeeDocModel());
    }

    /**
     * Returns the file extension in lowercase.
     *
     * @return string
     */
    public function getExtension()
    {
        $parts = \explode('.', \strtolower($this->filename));
        return \count($parts) > 1 ? \strtolower(\end($parts)) : '';
    }

    /**
     * Return the max file size that can be uploaded.
     *
     * @return int
     */
    public function getMaxFileUpload()
    {
        $docFile = new AttachedFileRelation();
        return $docFile->getMaxFileUpload();
    }

    /**
     * Get an authorized url to download the file.
     *
     * @return string
     */
    public function getUrlDownload()
    {
        return $this->path . '?myft=' . MyFilesToken::get($this->path, false);
    }

    /**
     *
     * @param int $size
     * @return string
     */
    public function getSize(int $size): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $unit[$i];
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        if (empty($this->idemployee)) {
            return '';
        }
        return 'EditEmployee?code=' . $this->idemployee . '&active=EmployeeFiles';
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'id' => 'doc.id',
            'idemployee' => 'doc.idemployee',
            'iddoctype' => 'doc.iddoctype',
            'downloadable' => 'doc.downloadable',
            'expires' => 'doc.expires',
            'note' => 'doc.note',
            'year_group' => 'doc.year_group',
            'employee' => 'employee.nombre',
            'doctype' => 'doctypes.name',
            'creationdate' => 'rel.creationdate',
            'idattached' => 'rel.id',
            'idfile' => 'rel.idfile',
            'nick' => 'rel.nick',
            'date' => 'att.date',
            'hour' => 'att.hour',
            'filename' => 'att.filename',
            'path' => 'att.path',
            'mimetype' => 'att.mimetype',
            'size' => 'att.size',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'rrhh_employeesdocs doc'
            . ' INNER JOIN rrhh_employees employee ON employee.id = doc.idemployee'
            . '  LEFT JOIN rrhh_documentstypes doctypes ON doctypes.id = doc.iddoctype'
            . ' INNER JOIN attached_files_rel rel ON rel.model = \'EmployeeDocument\' AND rel.modelid = doc.id'
            . ' INNER JOIN attached_files att ON att.idfile = rel.idfile';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            'rrhh_employeesdocs',
            'rrhh_employees',
            'attached_files_rel',
            'attached_files',
        ];
    }
}

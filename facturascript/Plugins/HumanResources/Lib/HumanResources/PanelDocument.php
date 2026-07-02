<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\DocumentType;
use FacturaScripts\Plugins\HumanResources\Model\Join\EmployeeDocument;

/**
 * Class for management public documents of the employee panel
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PanelDocument
{
    /**
     *
     * @var EmployeeDocument[]
     */
    public $data;

    /**
     *
     * @var DocumentType[]
     */
    public $docTypes;

    /**
     * Constructor and inicializate values
     */
    public function __construct()
    {
        $this->data = [];
        $this->docTypes = [];
    }

    /**
     * Load public documents of the employee.
     *
     * @param int $idemployee
     */
    public function load(int $idemployee)
    {
        $where = [
            new DataBaseWhere('doc.idemployee', $idemployee),
            new DataBaseWhere('doc.downloadable', true),
        ];
        $order = [
            'doctypes.name' => 'ASC',
            'doc.year_group' => 'DESC',
            'rel.creationdate' => 'DESC',
        ];

        $docTypeId = 0;
        $docs = new EmployeeDocument();
        foreach ($docs->all($where, $order) as $item) {
            $this->addDocType($item, $docTypeId);
            $this->data[$item->doctype][] = $item;
        }
    }

    /**
     *
     * @param EmployeeDocument $item
     * @param int $iddoctype
     */
    private function addDocType(EmployeeDocument $item, int &$iddoctype)
    {
        if ($iddoctype !== (int)$item->iddoctype) {
            $iddoctype = $item->iddoctype;
            $documentType = new DocumentType();
            $documentType->loadFromCode($iddoctype);
            $this->docTypes[] = $documentType;
        }
    }
}

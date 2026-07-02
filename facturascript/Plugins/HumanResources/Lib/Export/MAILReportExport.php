<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Export;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\HumanResources\Lib\Export\PDFReportExport as ParentClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mail export data from ModelReport.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class MAILReportExport extends ParentClass
{

    /**
     *
     * @var array
     */
    protected $sendParams = [];

    /**
     * Adds a new page with the model data.
     *
     * @param array $cursor
     * @param array $columns
     * @param string $title
     *
     * @return bool
     */
    public function addModelPage($cursor, $columns, $title = ''): bool
    {
        $this->sendParams['modelClassName'] = 'ModelReport';
        $this->sendParams['modelCode'] = '';
        return parent::addModelPage($cursor, $columns, $title);
    }

    /**
     *
     * @param Response $response
     */
    public function show(Response &$response)
    {
        $fileName = $this->getFileName() . '_mail_' . time() . '.pdf';
        $filePath = \FS_FOLDER . '/MyFiles/' . $fileName;
        if (false === \file_put_contents($filePath, $this->getDoc())) {
            Tools::log()->error('folder-not-writable');
            return;
        }

        $this->sendParams['fileName'] = $fileName;
        $response->headers->set('Refresh', '0; SendMail?' . \http_build_query($this->sendParams));
    }
}

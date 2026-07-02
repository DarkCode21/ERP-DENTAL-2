<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Lib\Export;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\ModelClass;
use Symfony\Component\HttpFoundation\Response;
use FacturaScripts\Plugins\Facturae\Model\XMLfacturae;

/**
 * XLS export data.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class XMLExport extends ExportBase
{
	public $xmlfacturae;
	public $where = [];
	
	public function addBusinessDocPage($model): bool
    {
        /// lines
        #$headers = $this->getModelHeaders($model);
   		return false;
    }
	
	/**
     * Adds a new page with a table listing all models data.
     *
     * @param ModelClass $model
     * @param DataBaseWhere[] $where
     * @param array $order
     * @param int $offset
     * @param array $columns
     * @param string $title
     *
     * @return bool
     */
    public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setFileName($title);
	    return true;
    }
	
	/**
     * Adds a new page with the model data.
     *
     * @param ModelClass $model
     * @param array $columns
     * @param string $title
     *
     * @return bool
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        return true;
    }
	
	/**
     * Blank document.
     *
     * @param string $title
     * @param int $idformat
     * @param string $langcode
     */
    public function newDoc(string $title, int $idformat, string $langcode=null)
    {
        $this->setFileName("facturae_$title");
        $this->xmlfacturae = new XMLfacturae();
		$this->where = [
			new DataBaseWhere('idfactura', $idformat),
		];
		$this->xmlfacturae->loadFromCode('', $this->where);
        #$this->writer->setAuthor('FacturaScripts');
        #$this->writer->setTitle($title);
    }

	
	/**
     * Set headers and output document content to response.
     *
     * @param Response $response
     */
    public function show(Response &$response)
    {
		$response->headers->set('Content-Type', 'application/xml');
		$response->headers->set('Content-Disposition', 'attachment;filename=' . $this->getFileName() . '.xsig');
		#$response->setContent(file_get_contents($xmlfacturae->getFilePath()));
        
        #$response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        #$response->headers->set('Content-Disposition', 'attachment;filename=' . $this->getFileName() . '.xlsx');
        $response->setContent($this->getDoc());
		
		    
	}

	/**
     * Return the full document.
     *
     * @return string
	 */
    public function getDoc()
    {	
		 #echo \FS_FOLDER.'/'.$this->xmlfacturae->getFilePath()."'";
	     return (string) file_get_contents(\FS_FOLDER .'/'.$this->xmlfacturae->getFilePath());
    }

	/**
     * Adds a new page with the table.
     *
     * @param array $headers
     * @param array $rows
     * @param array $options
     * @param string $title
     *
     * @return bool
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        return true;
    }

	/**
     * @param string $orientation
	 */
    public function setOrientation(string $orientation)
    {
        /// Not implemented
    }
}
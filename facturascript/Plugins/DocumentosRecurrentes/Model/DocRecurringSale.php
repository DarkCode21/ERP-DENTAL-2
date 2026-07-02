<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Dinamic\Model\DocRecurringSaleLine;
use FacturaScripts\Plugins\DocumentosRecurrentes\Model\Base\DocRecurring;

/**
 * Class that manages the data model of the document recurring sale.
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class DocRecurringSale extends DocRecurring
{

    use ModelTrait;

    /**
     * Link with the agent model
     *
     * @var string
     */
    public $codagente;

    /**
     * Link with the customer model
     *
     * @var string
     */
    public $codcliente;

    /**
     * Returns a new line for the document.
     *
     * @param array $data
     * @param array $exclude
     *
     * @return DocRecurringSaleLine
     */
    public function getNewLine(array $data = [], array $exclude = ['id', 'iddoc'])
    {
        $newLine = new DocRecurringSaleLine();
        $newLine->iddoc = $this->id;
        $newLine->loadFromData($data, $exclude);
        return $newLine;
    }
    /**
     * Returns the lines associated with the document.
     *
     * @return DocRecurringSaleLine[]
     */
    public function getLines()
    {
        $order = ['docrecurrentes_salelines.id' => 'ASC'];
        $where = [new DataBaseWhere('docrecurrentes_salelines.iddoc', $this->id)];

        $lineModel = new DocRecurringSaleLine();
        return $lineModel->all($where, $order, 0, 0);
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'docrecurrentes_sale';
    }
}

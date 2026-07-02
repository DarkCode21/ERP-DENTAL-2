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
use FacturaScripts\Dinamic\Model\EmployeeHoliday;
use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;

/**
 * Class for management Holidays data of the employee panel
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PanelHoliday
{
    /**
     *
     * @var EmployeeHoliday[]
     */
    public $data;

    /**
     *
     * @var int
     */
    public $enjoyed;

    /**
     *
     * @var int
     */
    public $total;

    /**
     * Constructor and inicializate values
     */
    public function __construct()
    {
        $this->data = [];
        $this->enjoyed = 0;
        $this->total = 0;
    }

    /**
     * Load holidays data structure for current year.
     *
     * @param int $idemployee
     */
    public function load(int $idemployee)
    {
        $where = [
            new DataBaseWhere('idemployee', $idemployee),
            new DataBaseWhere('startdate', date('Y-01-01'), '>='),
        ];
        $order = [ 'startdate' => 'ASC' ];

        $holidays = new EmployeeHoliday();
        foreach ($holidays->all($where, $order) as $item) {
            $item->canDelete = DateTimeTools::dateGreaterThan($item->startdate);
            $this->total += $item->totaldays;
            $this->enjoyed += $item->canDelete ? 0 : $item->totaldays;
            $this->data[] = $item;
        }
    }
}

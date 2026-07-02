<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Report\Data;

/**
 * Class to manage employee incidence data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceIncidenceData
{

    public $delays;
    public $entries;
    public $holidays;
    public $idemployee;
    public $justified;
    public $leaves;
    public $name;
    public $totaldays;
    public $withoutrest;

    public function __construct(int $idemployee, string $name)
    {
        $this->idemployee = $idemployee;
        $this->name = $name;
        $this->delays = 0;
        $this->entries = 0;
        $this->holidays = 0;
        $this->justified = 0;
        $this->leaves = 0;
        $this->totaldays = 0;
        $this->withoutrest = 0;
    }
}

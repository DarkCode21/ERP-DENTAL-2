<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model\Report;

use FacturaScripts\Plugins\HumanResources\Lib\DateTimeTools;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelReport;
use FacturaScripts\Plugins\HumanResources\Model\Report\Data\JournalRegisterData;

/**
 * Description of JournalRegisterReport
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class JournalRegisterReport extends ModelReport
{

    public function all($filters, $where, $order, $offset, $limit): array
    {
        return [];
    }

    public function emptyJournal(int $year, int $month): array
    {
        $result = [];
        $lastDay = DateTimeTools::lastDayMonth($year, $month);
        for ($day = 1; $day <= $lastDay; $day++) {
            $data = new JournalRegisterData();
            $data->day = $day;
            $result[] = $data;
        }
        return $result;
    }
}

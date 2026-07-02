<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\HumanResources;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeLeave;
use FacturaScripts\Plugins\HumanResources\Model\EmployeeHoliday;
use FacturaScripts\Plugins\HumanResources\Model\PublicHoliday;

/**
 * Class to manage employee attendance incidences
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceIncidence
{

    const INCIDENCE_LEAVE = 'LE';
    const INCIDENCE_HOLIDAY = 'HO';
    const INCIDENCE_PUBLICHOLIDAY = 'PH';
    const INCIDENCE_NO_INPUT = 'NI';
    const INCIDENCE_NO_EXIT = 'NE';
    const INCIDENCE_NO_ATTENDANCE = 'NA';
    const INCIDENCE_NO_WORKSHIFT = 'NW';
    const INCIDENCE_JUSTIFIED = 'JA';

    /**
     * It provides direct access to the database.
     *
     * @var DataBase
     */
    private $dataBase;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->dataBase = new DataBase();
    }

    /**
     * Indicates if the employee has an incidence of sick leave
     *
     * @param int $idemployee
     * @param date|string $date
     * @return bool
     */
    public function isLeave(int $idemployee, $date): bool
    {
        $sql = 'SELECT 1 FROM ' . EmployeeLeave::tableName()
            . ' WHERE idemployee = ' . $idemployee
            . ' AND startdate <= ' . $this->dataBase->var2str($date)
            . ' AND ((enddate >= ' . $this->dataBase->var2str($date) . ') OR (enddate IS NULL))'
            . ' LIMIT 1';

        return empty($this->dataBase->select($sql)) ? false : true;
    }

    /**
     * Indicates if the employee has a holiday incidence
     *
     * @param int $idemployee
     * @param date|string $date
     * @return bool
     */
    public function isHoliday(int $idemployee, $date): bool
    {
        $sql = 'SELECT 1 FROM ' . EmployeeHoliday::tableName()
            . ' WHERE idemployee = ' . $idemployee
            . ' AND ' . $this->dataBase->var2str($date) . ' BETWEEN startdate AND enddate'
            . ' LIMIT 1';

        return empty($this->dataBase->select($sql)) ? false : true;
    }

    /**
     * Indicate if the date is a public holiday
     *
     * @param date|string $date
     * @return bool
     */
    public function isPublicHoliday($date): bool
    {
        $sql = 'SELECT 1 FROM ' . PublicHoliday::tableName()
            . ' WHERE holiday = ' . $this->dataBase->var2str($date)
            . ' LIMIT 1';

        return empty($this->dataBase->select($sql)) ? false : true;
    }

    /**
     * Check if the employee has an incident for the indicated date
     *
     * @param int $idemployee
     * @param date|string $date
     * @return string
     */
    public function getIncidence(int $idemployee, $date): string
    {
        if ($this->isLeave($idemployee, $date)) {
            return self::INCIDENCE_LEAVE;
        }

        if ($this->isHoliday($idemployee, $date)) {
            return self::INCIDENCE_HOLIDAY;
        }

        if ($this->isPublicHoliday($date)) {
            return self::INCIDENCE_PUBLICHOLIDAY;
        }

        return '';
    }

    /**
     * Get the full description of the incident indicated in the user's language
     *
     * @param string $incidence
     * @return string
     */
    public static function getIncidenceDescription(string $incidence): string
    {
        return empty($incidence) ? '' : self::toolBox()->i18n()->trans('incidence-desc-' . $incidence);
    }

    /**
     * Get the acronym of the incidence indicated in the user's language
     *
     * @param string $incidence
     * @return string
     */
    public static function getIncidenceShortDesc(string $incidence): string
    {
        return empty($incidence) ? '' : self::toolBox()->i18n()->trans('incidence-code-' . $incidence);
    }

    /**
     *
     * @return ToolBox
     */
    public static function toolBox()
    {
        return new ToolBox();
    }
}

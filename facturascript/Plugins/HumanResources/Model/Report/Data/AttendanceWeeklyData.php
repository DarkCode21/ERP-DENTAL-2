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
 * Class to manage employee attendance weekly data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceWeeklyData
{

    const ATT_MAXDAYS = 7;

    /**
     * Employee record identifier
     *
     * @var int
     */
    public $idemployee;

    /**
     * Employee description
     *
     * @var string
     */
    public $name;

    /**
     * List of theoretical hours to work per day according to the work shift
     *
     * @var float[]
     */
    public $hours;

    /**
     * List of actual hours worked per day
     *
     * @var float[]
     */
    public $worked;

    /**
     * Difference between theoretical and actual hours worked per day
     *
     * @var float[]
     */
    public $difference;

    /**
     * Indicates if the employee has an impact on any of the days processed
     *
     * @var bool
     */
    public $hasIncidence;

    /**
     * List of incidence codes per day
     *
     * @var string[]
     */
    public $incidence;

    /**
     * Total number of days with input delay
     *
     * @var int
     */
    public $totalInputDelay;

    /**
     * Total number of vacation days
     *
     * @var int
     */
    public $totalHoliday;

    /**
     * Total number of theoretical hours to work
     *
     * @var float
     */
    public $totalHours;

    /**
     * Total number of hours worked
     *
     * @var float
     */
    public $totalWorked;

    /**
     * Difference between theoretical and actual hours worked
     *
     * @var float
     */
    public $totalDifference;

    /**
     * Date from start period calculated
     *
     * @var string
     */
    public $startdate;

    /**
     *
     * @var array
     */
    public $workperiods;

    /**
     * Class constructor
     *
     * @param int $idemployee
     * @param string $name
     */
    public function __construct(int $idemployee, string $name, string $startdate)
    {
        $this->idemployee = $idemployee;
        $this->name = $name;
        $this->startdate = $startdate;

        $this->hours = array_pad([], self::ATT_MAXDAYS, 0.0000);
        $this->worked = array_pad([], self::ATT_MAXDAYS, 0.0000);
        $this->difference = array_pad([], self::ATT_MAXDAYS, 0.0000);
        $this->incidence = array_pad([], self::ATT_MAXDAYS, '');
        $this->workperiods = array_pad([], self::ATT_MAXDAYS, []);

        $this->hasIncidence = false;
        $this->totalInputDelay = 0;
        $this->totalHoliday = 0;
        $this->totalHours = 0.00;
        $this->totalWorked = 0.00;
        $this->totalDifference = 0.00;
    }
}

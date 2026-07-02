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
 * Class to manage employee attendance summary data
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceSummaryData
{

    const ATT_SUMMARY_MAXDAYS = 366;

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
     * List of theoretical hours to work per day according to the work shift
     *
     * @var float[]
     */
    public $hours;

    /**
     * Employee record identifier
     *
     * @var int
     */
    public $idemployee;

    /**
     * List of incidence codes per day
     *
     * @var string[]
     */
    public $incidence;

    /**
     * Employee description
     *
     * @var string
     */
    public $name;

    /**
     * Date from start period calculated
     *
     * @var string
     */
    public $startdate;

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
     * Total number of days with input delay
     *
     * @var int
     */
    public $totalInputDelay;

    /**
     * Total number of leave days
     *
     * @var int
     */
    public $totalLeave;

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
     * List of actual hours worked per day
     *
     * @var float[]
     */
    public $worked;

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

        $this->hours = array_pad([], self::ATT_SUMMARY_MAXDAYS, 0.0000);
        $this->worked = array_pad([], self::ATT_SUMMARY_MAXDAYS, 0.0000);
        $this->difference = array_pad([], self::ATT_SUMMARY_MAXDAYS, 0.0000);
        $this->incidence = array_pad([], self::ATT_SUMMARY_MAXDAYS, '');

        $this->totalInputDelay = 0;
        $this->totalHoliday = 0;
        $this->totalLeave = 0;
        $this->totalHours = 0.00;
        $this->totalWorked = 0.00;
        $this->totalDifference = 0.00;
    }

    /**
     * Return a date from the start date and the number of days to add.
     *
     * @param $days
     * @return false|string
     */
    public function getDate($days)
    {
        $days = '+' . $days . ' day';
        $newDate = strtotime($days, strtotime($this->startdate));
        return date('d-m-Y', $newDate);
    }

    /**
     * Returns the master key value
     *
     * @return mixed
     */
    public function getMasterID()
    {
        return $this->idemployee;
    }

    /**
     * Returns the master key value.
     * For compatibility with checkbox selection of the list view.
     * 
     * @return mixed
     */
    public function primaryColumnValue()
    {
        return $this->getMasterID();
    }
}

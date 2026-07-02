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
 * Class to manage employee attendance data
 *
* @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AttendanceData
{

    /**
     * indicates if the input record is authorized.
     *
     * @var bool
     */
    public $authorized_input;

    /**
     * indicates if the exit record is authorized.
     *
     * @var bool
     */
    public $authorized_exit;

    /**
     * Indicates if the attendance can be deleted.
     *
     * @var bool
     */
    public $canDelete = false;

    /**
     * Day that is being processed
     *
     * @var string
     */
    public $date;

    /**
     * Time of departure of assistance
     *
     * @var string
     */
    public $exit;

    /**
     * Employee record identifier
     *
     * @var int
     */
    public $idemployee;

    /**
     * Attendance departure record identifier
     *
     * @var int
     */
    public $idexit;

    /**
     * Attendance entry record identifier
     *
     * @var int
     */
    public $idinput;

    /**
     * The Incident code Indicates if the day registers any incidence
     *
     * @var string
     */
    public $incidence;

    /**
     *
     * @var bool
     */
    public $incidenceError;

    /**
     * Time of attendance entry
     *
     * @var string
     */
    public $input;

    /**
     * Input delay in minutes.
     *
     * @var int
     */
    public $inputdelay;

    /**
     * Employee name
     *
     * @var type
     */
    public $name;

    /**
     * Total hours of attendance (in a decimal system)
     *
     * @var float
     */
    public $total;

    /**
     * Carried balance of hours per day (in a decimal system)
     *
     * @var float
     */
    public $totalday;

    /**
     * Translated incident code
     *
     * @var string
     */
    public $translatedIncidence;

    /**
     * Translated incident description
     *
     * @var string
     */
    public $translatedIncidenceDesc;
}

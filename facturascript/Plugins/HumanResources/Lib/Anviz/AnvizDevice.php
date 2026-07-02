<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Lib\Anviz;

/**
 * Description of AnvizDevice
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class AnvizDevice {

    /**
     *
     * @var int
     */
    public $deviceId;

    /**
     *
     * @var string
     */
    public $host;

    /**
     *
     * @var int
     */
    public $port;

    /**
     *
     * @var \DateTimeZone
     */
    public $dateTimeZone;

    /**
     *
     * @param string $timeZone
     * @param string $host
     * @param int $port
     * @param int $deviceId
     */
    function __construct(string $timeZone, string $host, int $port = 5010, int $deviceId = 1)
    {
        $this->dateTimeZone = new \DateTimeZone($timeZone);
        $this->deviceId = $deviceId;
        $this->host = $host;
        $this->port = $port;
    }
}

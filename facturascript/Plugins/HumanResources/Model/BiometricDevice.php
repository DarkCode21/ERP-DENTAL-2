<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources\Model;

use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\ModelExtended;
use FacturaScripts\Dinamic\Model\Ciudad;
use FacturaScripts\Dinamic\Model\Provincia;

/**
 * List of biometric devices used in the companies
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class BiometricDevice extends ModelExtended
{

    use ModelTrait;

    public const DEVICE_TYPE_ANVIZ = 1;

    /**
     * Sets whether attendances should be imported automatically.
     *
     * @var boolean
     */
    public $auto_import;

    /**
     * Device ID.
     *
     * @var integer
     */
    public $device;

    /**
     * Host address. Normaly IPv4.
     *
     * @var string
     */
    public $host;

    /**
     * Link to city model.
     *
     * @var int
     */
    public $idcity;

    /**
     *
     * @var int
     */
    public $idprovince;

    /**
     * Description of device
     *
     * @var string
     */
    public $name;

    /**
     * Notes of device
     *
     * @var string
     */
    public $note;

    /**
     * Port number for comunication.
     *
     * @var int
     */
    public $port;

    /**
     * Address of device
     *
     * @var string
     */
    public $roadname;

    /**
     *
     * @var string
     */
    public $timezone;

    /**
     * Device vendor or manufacturer.
     *
     * @var int
     */
    public $type;

    /**
     *
     * @var string
     */
    public $zipcode;

    /**
     * Reset the values of all model properties.
     */
    public function clear()
    {
        parent::clear();
        $this->auto_import = false;
        $this->device = 1;
        $this->port = 5010;
        $this->timezone = 'Europe/Madrid';
        $this->type = self::DEVICE_TYPE_ANVIZ;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'rrhh_devices';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->name = Utils::noHtml($this->name);
        $this->note = Utils::noHtml($this->note);
        $this->roadname = Utils::noHtml($this->roadname);
        $this->idprovince = $this->getProvince();
        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, 'ListAttendance?activetab=' . $list);
    }

    /**
     * Returns a list of fields to verify that they do not have html code
     *
     * @return array
     */
    protected function noHtmlFields(): array
    {
        return array_merge(parent::noHtmlFields(), ['host', 'note']);
    }

    /**
     * Get province id for selected city.
     *
     * @return string|null
     */
    private function getProvince()
    {
        $city = new Ciudad();
        if (false == $city->loadFromCode($this->idcity)) {
            return null;
        }

        $province = new Provincia();
        if (false == $province->loadFromCode($city->idprovincia)) {
            return null;
        }

        return $province->idprovincia;
    }
}

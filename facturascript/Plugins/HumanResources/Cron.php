<?php
/**
 * This file is part of HumanResources plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 * HumanResources Copyright (C) 2018-2025 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\HumanResources;

use FacturaScripts\Core\Base\CronClass;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\BiometricDevice;
use FacturaScripts\Plugins\HumanResources\Lib\HumanResources\BiometricAnviz;

/**
 * Description of Cron
 *
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class Cron extends CronClass
{

    /**
     * Cron to import all attendances from devices marked with automatic.
     */
    public function run()
    {
        if ($this->isTimeForJob('rrhh-devices-autoimport', '2 hours')) {
            $this->importAttendances();
            $this->jobDone('rrhh-devices-autoimport');
        }
    }

    /**
     * Perform an import of the biometric devices marked as auto import.
     */
    private function importAttendances()
    {
        $where = [ new DataBaseWhere('auto_import', true) ];
        $model = new BiometricDevice();
        $biometric = new BiometricAnviz();

        foreach ($model->all($where, [], 0, 0) as $device) {
            if ($device->type !== BiometricDevice::DEVICE_TYPE_ANVIZ) {
                continue;
            }
            $biometric->import($device);
        }
    }
}

<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas;

use FacturaScripts\Core\Base\CronClass;
use FacturaScripts\Plugins\Etiquetas\CronJob\AutoGenBarcode;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Cron extends CronClass
{
    public function run()
    {
        if ($this->isTimeForJob(AutoGenBarcode::JOB_NAME, AutoGenBarcode::JOB_PERIOD)) {
            AutoGenBarcode::run();
            $this->jobDone(AutoGenBarcode::JOB_NAME);
        }
    }
}

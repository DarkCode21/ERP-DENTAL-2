<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Verifactu;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Plugins\Verifactu\CronJob\VerifactuRegistroEventoCheck;
use FacturaScripts\Plugins\Verifactu\CronJob\VerifactuRegistroEventoHashSignature;
use FacturaScripts\Plugins\Verifactu\CronJob\VerifactuRegistroFacturaHashSignature;
use FacturaScripts\Plugins\Verifactu\CronJob\VerifactuRegistroFacturaSend;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Cron extends CronClass
{
    public function run(): void
    {
        $this->job(VerifactuRegistroFacturaHashSignature::JOB_NAME)
            ->every('60 seconds')
            ->run(function () {
                VerifactuRegistroFacturaHashSignature::run();
            });

        $this->job(VerifactuRegistroEventoHashSignature::JOB_NAME)
            ->every('60 seconds')
            ->run(function () {
                VerifactuRegistroEventoHashSignature::run();
            });

        $this->job(VerifactuRegistroFacturaSend::JOB_NAME)
            ->every('90 seconds')
            ->withoutOverlapping(VerifactuRegistroFacturaHashSignature::JOB_NAME)
            ->run(function () {
                VerifactuRegistroFacturaSend::run();
            });

        $this->job(VerifactuRegistroEventoCheck::JOB_NAME)
            ->everyMondayAt(4)
            ->withoutOverlapping(VerifactuRegistroFacturaHashSignature::JOB_NAME)
            ->withoutOverlapping(VerifactuRegistroEventoHashSignature::JOB_NAME)
            ->run(function () {
                VerifactuRegistroEventoCheck::run();
            });
    }
}

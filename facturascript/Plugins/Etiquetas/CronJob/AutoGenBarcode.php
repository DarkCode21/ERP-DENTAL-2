<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Etiquetas\CronJob;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\LogMessage;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class AutoGenBarcode
{
    const JOB_NAME = 'auto-gen-barcode';
    const JOB_PERIOD = '1 days';

    /** @var string */
    private static $echo = '';

    public static function run(): void
    {
        echo "\n* JOB: " . self::JOB_NAME . ' ... ';

        // comprobamos si está activado el autogenerado de códigos de barras
        if (false === (bool)Tools::settings('default', 'autogenbarcodes', false)) {
            return;
        }

        // obtenemos las variantes sin código de barras
        $variantModel = new Variante();
        $where = [new DataBaseWhere('codbarras', null),];
        foreach ($variantModel->all($where, [], 0, 1000) as $variant) {
            $variant->codbarras = $variant->generateEAN();
            if (false === $variant->save()) {
                self::echo("\n- Error al generar el código de barras de la variante: " . $variant->referencia);
                continue;
            }

            self::echo("\n- Generado código de barras de la variante: " . $variant->referencia);
        }

        self::saveEcho(self::JOB_NAME);
    }

    protected static function echo(string $text): void
    {
        echo $text;
        self::$echo .= $text;
    }

    protected static function getEcho(): string
    {
        return self::$echo;
    }

    protected static function saveEcho(string $jobName): void
    {
        if (empty($jobName) || empty(self::$echo)) {
            return;
        }

        $log = new LogMessage();
        $log->channel = $jobName;
        $log->level = 'info';
        $log->message = self::$echo;
        $log->save();
    }
}
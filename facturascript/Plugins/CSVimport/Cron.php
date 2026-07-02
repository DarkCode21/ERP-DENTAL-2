<?php
/**
 * Copyright (C) 2022-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\CSVimport;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\CSVfile;

/**
 * @author Daniel Fernandez Giménez <hola@danielfg.es>
 */
class Cron extends CronClass
{
    public function run(): void
    {
        $where = [
            new DataBaseWhere('url', null, 'IS NOT'),
            new DataBaseWhere('options', null, 'IS NOT'),
            new DataBaseWhere('expiration', 0, '>'),
        ];
        foreach (CSVfile::all($where, [], 0, 0) as $csv) {
            $jobName = 'csv-import-' . $csv->primaryColumnValue();
            $this->job($jobName)
                ->every($csv->expiration . ' ' . $csv->expirationtype)
                ->run(function () use ($csv, $jobName) {
                    if ($csv->download()) {
                        Tools::log($jobName)->notice('CSV file downloaded: ' . $csv->url);
                        $csv->getProfile(0, 0, null, null, false)->import();
                    }
                });
        }
    }
}

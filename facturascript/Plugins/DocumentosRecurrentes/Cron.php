<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes;

use FacturaScripts\Core\Base\CronClass;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Lib\DocumentosRecurrentes\DocRecurringManager;
use FacturaScripts\Dinamic\Model\DocRecurringPurchase;
use FacturaScripts\Dinamic\Model\DocRecurringSale;

/**
 * Description of Cron
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello <yopli2000@gmail.com>
 */
class Cron extends CronClass
{

    /**
     * Main process
     */
    public function run()
    {
        if ($this->isTimeForJob('docrecurring-autogenerate', '1 day')) {
            $this->generatePurchases();
            $this->generateSales();
            $this->jobDone('docrecurring-autogenerate');
        }
    }

    /**
     * Generate purchases documents from document recurring.
     */
    private function generatePurchases()
    {
        $where = [
            new DataBaseWhere('nextdate', $this->toolBox()->today(), '<='),
            new DataBaseWhere('termtype', DocRecurringPurchase::TERM_TYPE_MANUAL, '<>')
        ];

        $docRecurring = new DocRecurringManager();
        $model = new DocRecurringPurchase();
        foreach ($model->all($where, [], 0, 0) as $template) {
            $docRecurring->generatePurchaseDoc($template->id, ['date' => $template->nextdate]);
        }
    }

    /**
     * Generate sales documents from document recurring.
     */
    private function generateSales()
    {
        $where = [
            new DataBaseWhere('nextdate', $this->toolBox()->today(), '<='),
            new DataBaseWhere('termtype', DocRecurringSale::TERM_TYPE_MANUAL, '<>')
        ];

        $docRecurring = new DocRecurringManager();
        $model = new DocRecurringSale();
        foreach ($model->all($where, [], 0, 0) as $template) {
            $docRecurring->generateSaleDoc($template->id, ['date' => $template->nextdate]);
        }
    }
}

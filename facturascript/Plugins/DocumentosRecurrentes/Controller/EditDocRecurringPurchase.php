<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Controller;

use FacturaScripts\Dinamic\Lib\DocumentosRecurrentes\DocRecurringDuplicate;
use FacturaScripts\Dinamic\Lib\DocumentosRecurrentes\DocRecurringManager;
use FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes\EditDocRecurring;
use FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringPurchase;

/**
 * Description of EditDocRecurringSale
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditDocRecurringPurchase extends EditDocRecurring
{

    /**
     * Returns the class name of the model to use in the EditView.
     */
    public function getModelClassName(): string
    {
        return 'DocRecurringPurchase';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'purchases';
        return $pagedata;
    }

    protected function duplicateDocAction()
    {
        $data = $this->request->request->all();
        $docDuplicate = new DocRecurringDuplicate();
        $docDuplicate->duplicatePurchaseDoc($data['id'], $data['target'], $data['name']);
    }

    protected function generateDocsAction()
    {
        $code = $this->request->request->get('id');
        $recurringDoc = new DocRecurringPurchase();
        if (false === $recurringDoc->loadFromCode($code)) {
            return;
        }

        if (null === $recurringDoc->nextdate) {
            return;
        }

        $docGenerator = new DocRecurringManager();
        $docGenerator->generatePurchaseDoc($code, ['date' => $recurringDoc->nextdate]);
    }

    protected function generateManuallyAction()
    {
        $iddoc = $this->request->request->get('id');
        $date = $this->request->request->get('generatedate');
        $target = $this->request->request->get('target');
        $docGenerator = new DocRecurringManager();
        $docGenerator->generatePurchaseDoc($iddoc, ['date' => $date, 'target' => $target]);
    }
}

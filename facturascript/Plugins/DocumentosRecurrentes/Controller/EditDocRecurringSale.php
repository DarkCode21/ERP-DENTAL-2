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
use FacturaScripts\Plugins\DocumentosRecurrentes\Model\DocRecurringSale;

/**
 * Description of EditDocRecurringSale
 *
 * @author Carlos Garcia Gomez  <carlos@facturascripts.com>
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class EditDocRecurringSale extends EditDocRecurring
{

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'DocRecurringSale';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData(): array
    {
        $pagedata = parent::getPageData();
        $pagedata['menu'] = 'sales';
        return $pagedata;
    }

    protected function duplicateDocAction()
    {
        $data = $this->request->request->all();
        $docDuplicate = new DocRecurringDuplicate();
        $docDuplicate->duplicateSaleDoc($data['id'], $data['target'], $data['name']);
    }

    protected function generateDocsAction()
    {
        $code = $this->request->request->get('id');
        $docRecurring = new DocRecurringSale();
        if (false === $docRecurring->loadFromCode($code)) {
            return;
        }

        if (null === $docRecurring->nextdate) {
            return;
        }

        $docGenerator = new DocRecurringManager();
        $docGenerator->generateSaleDoc($code, ['date' => $docRecurring->nextdate]);
    }

    protected function generateManuallyAction()
    {
        $iddoc = $this->request->request->get('id');
        $date = $this->request->request->get('generatedate');
        $target = $this->request->request->get('target');
        $docGenerator = new DocRecurringManager();
        $docGenerator->generateSaleDoc($iddoc, ['date' => $date, 'target' => $target]);
    }
}

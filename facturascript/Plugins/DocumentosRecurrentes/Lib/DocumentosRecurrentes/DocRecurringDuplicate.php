<?php
/**
 * This file is part of DocumentosRecurrentes plugin for FacturaScripts.
 * FacturaScripts         Copyright (C) 2015-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DocumentosRecurrentes  Copyright (C) 2020-2021 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DocumentosRecurrentes\Lib\DocumentosRecurrentes;

use Exception;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\Base\DocRecurring;
use FacturaScripts\Dinamic\Model\DocRecurringPurchase;
use FacturaScripts\Dinamic\Model\DocRecurringSale;

/**
 * Create a duplicate of the indicated recurring document.
 *
 * @author Jose Antonio Cuello  <yopli2000@gmail.com>
 */
class DocRecurringDuplicate
{

    /**
     * Link to the document being created.
     *
     * @var DocRecurringPurchase|DocRecurringSale
     */
    protected $document;

    /**
     * Recurring document that acts as a template.
     *
     * @var DocRecurring
     */
    protected $template;

    /**
     * New description name for duplicate document.
     *
     * @var string
     */
    private $name;

    /**
     * Indicates if the new recurring document is a purchase document.
     *
     * @var bool
     */
    private $purchase = false;

    /**
     * New target for duplicate document.
     *
     * @var string
     */
    private $target;

    /**
     * Create a duplicate of the recurring purchase document.
     *
     * @param int $iddoc
     * @param string $target
     * @param string $name
     * @return bool
     */
    public function duplicatePurchaseDoc($iddoc, $target, $name = ''): bool
    {
        $this->template = new DocRecurringPurchase();
        if (false == $this->template->loadFromCode($iddoc)) {
            return false;
        }

        $this->name = empty($name) ? $this->template->name : $name;
        $this->target = $target;
        $this->purchase = true;

        return $this->generate();
    }

    /**
     * Create a duplicate of the recurring sales document.
     *
     * @param int $iddoc
     * @param string $target
     * @param string $name
     * @return bool
     */
    public function duplicateSaleDoc($iddoc, $target, $name = ''): bool
    {
        $this->template = new DocRecurringSale();
        if (false == $this->template->loadFromCode($iddoc)) {
            return false;
        }

        $this->name = empty($name) ? $this->template->name : $name;
        $this->target = $target;

        return $this->generate();
    }

    /**
     * Duplicate template document.
     *
     * @return bool
     */
    protected function duplicateDocument(): bool
    {
        $this->document = clone $this->template;

        $this->document->id = null;
        $this->document->name = $this->name;
        if ($this->purchase) {
            $this->document->codproveedor = $this->target;
        } else {
            $this->document->codcliente = $this->target;
        }

        if ($this->document->save()) {
            return true;
        }

        $this->toolBox()->i18nLog()->warning('doc-recurring-document-error');
        return false;
    }

    /**
     * Duplicate all document lines from template document.
     *
     * @return bool
     */
    protected function duplicateDocumentLines(): bool
    {
        foreach ($this->template->getLines() as $templateLine) {
            $newLine = clone $templateLine;
            $newLine->id = null;
            $newLine->iddoc = $this->document->id;
            if ($newLine->save() == false) {
                $this->toolBox()->i18nLog()->warning('doc-recurring-document-line-error');
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @return ToolBox
     */
    protected function toolBox()
    {
        return new ToolBox();
    }

    /**
     * Main Process.
     * - Duplicate document
     * - Duplicate document lines
     *
     * @return bool
     */
    private function generate(): bool
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();
        try {
            if ($this->duplicateDocument() &&
                $this->duplicateDocumentLines()) {
                $dataBase->commit();
                $this->toolBox()->i18nLog()->notice('doc-recurring-generate-ok');
                return true;
            }
        } catch (Exception $exc) {
            $this->toolBox()->log()->error($exc->getMessage());
        } finally {
            if ($dataBase->inTransaction()) {
                $dataBase->rollback();
            }
        }

        return false;
    }
}
